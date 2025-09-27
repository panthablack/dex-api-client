<?php

namespace App\Http\Controllers;

use App\Enums\DataMigrationStatus;
use App\Enums\DataMigrationBatchStatus;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use App\Helpers\ArrayHelpers;
use App\Models\DataMigration;
use App\Services\DataMigrationService;
use App\Services\VerificationService;
use App\Jobs\InitiateDataMigration;
use App\Models\MigratedCase;
use App\Models\MigratedClient;
use App\Models\MigratedSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DataMigrationController extends Controller
{
    protected DataMigrationService $migrationService;
    protected VerificationService $newVerificationService;

    public function __construct(
        DataMigrationService $migrationService,
        VerificationService $newVerificationService,
    ) {
        $this->migrationService = $migrationService;
        $this->newVerificationService = $newVerificationService;
    }

    /**
     * Display the main data migration dashboard
     */
    public function index()
    {
        $migrations = DataMigration::with('batches')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('data-exchange.migration.index', compact('migrations'));
    }

    /**
     * Show the form for creating a new migration
     */
    public function create()
    {
        return view('data-exchange.migration.create');
    }

    /**
     * Store a newly created migration
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'resource_type' => 'required',
            'batch_size' => 'integer|min:10|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $resourceType = $request->resource_type;

        // Additional validation to check resource is migratable
        if (!ResourceType::isMigratable($resourceType)) {
            $validator->after(function ($validator) use ($resourceType) {
                $validator->errors()->add('resource_type', "The resource type '$resourceType' cannot be migrated.");
            });
        }

        // Additional validation for sessions - require existing cases
        if ($request->resource_type === 'sessions') {
            if (!MigratedCase::exists()) {
                $validator->after(function ($validator) {
                    $validator->errors()->add('resource_type', 'Sessions can only be migrated when cases have been migrated first.');
                });
            }
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $filters = [];
            if ($request->date_from) {
                $filters['date_from'] = $request->date_from;
            }
            if ($request->date_to) {
                $filters['date_to'] = $request->date_to;
            }

            $migration = $this->migrationService->createMigration([
                'name' => $request->name,
                'resource_type' => $request->resource_type,
                'filters' => $filters,
                'batch_size' => $request->batch_size ?? 100
            ]);

            // Dispatch job to initiate the migration
            InitiateDataMigration::dispatch($migration);

            return redirect()->route('data-migration.show', $migration)
                ->with('success', 'Data migration created and initiated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to create data migration: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create migration: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified migration
     */
    public function show(DataMigration $migration)
    {
        $migration->load(['batches' => function ($query) {
            $query->orderBy('resource_type')->orderBy('batch_number');
        }]);

        return view('data-exchange.migration.show', compact('migration'));
    }

    /**
     * Get migration status via API
     */
    public function status(DataMigration $migration): JsonResponse
    {
        try {
            $status = $this->migrationService->getMigrationStatus($migration);
            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a migration
     */
    public function cancel(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['pending', 'in_progress'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot cancel migration with status: ' . $migration->status
                ], 400);
            }

            $this->migrationService->cancelMigration($migration);

            return response()->json([
                'success' => true,
                'message' => 'Migration cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retry failed batches
     */
    public function retry(DataMigration $migration): JsonResponse
    {
        try {
            $failedBatches = $migration->batches()->where('status', 'failed')->count();
            $pendingBatches = $migration->batches()->where('status', 'pending')->count();

            // Handle stuck/pending migrations
            if ($migration->status === DataMigrationStatus::PENDING || ($failedBatches === 0 && $pendingBatches > 0)) {
                $this->migrationService->restartMigration($migration);
                return response()->json([
                    'success' => true,
                    'message' => "Restarted stuck migration with {$pendingBatches} pending batches"
                ]);
            }

            // Handle failed batches
            if ($failedBatches === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No failed batches to retry'
                ], 400);
            }

            $this->migrationService->retryFailedBatches($migration);

            return response()->json([
                'success' => true,
                'message' => "Retrying {$failedBatches} failed batches"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a migration and its data
     */
    public function destroy(DataMigration $migration)
    {
        try {
            if ($migration->status === DataMigrationStatus::IN_PROGRESS) {
                return redirect()->back()
                    ->with('error', 'Cannot delete migration that is currently in progress. Cancel it first.');
            }

            // Delete related migrated data based on batch IDs
            $batchIds = $migration->batches()->pluck('id');

            if ($batchIds->isNotEmpty()) {
                // Delete migrated records
                \App\Models\MigratedClient::whereIn('data_migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedCase::whereIn('data_migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedSession::whereIn('data_migration_batch_id', $batchIds)->delete();
            }

            // Delete migration and its batches (cascaded)
            $migration->delete();

            return redirect()->route('data-migration.index')
                ->with('success', 'Migration and all associated data deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete data migration: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete migration: ' . $e->getMessage());
        }
    }

    /**
     * Get all migrations with their status for dashboard
     */
    public function dashboard(): JsonResponse
    {
        try {
            $migrations = DataMigration::with('batches')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($migration) {
                    return [
                        'id' => $migration->id,
                        'name' => $migration->name,
                        'status' => $migration->status,
                        'progress_percentage' => $migration->progress_percentage,
                        'success_rate' => $migration->success_rate,
                        'resource_type' => $migration->resource_type,
                        'created_at' => $migration->created_at->toISOString(),
                        'total_batches' => $migration->batches->count(),
                        'completed_batches' => $migration->batches->where('status', 'completed')->count(),
                        'failed_batches' => $migration->batches->where('status', 'failed')->count()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $migrations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get migration statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_migrations' => DataMigration::count(),
                'active_migrations' => DataMigration::active()->count(),
                'completed_migrations' => DataMigration::completed()->count(),
                'failed_migrations' => DataMigration::failed()->count(),
                'total_clients_migrated' => \App\Models\MigratedClient::count(),
                'total_cases_migrated' => \App\Models\MigratedCase::count(),
                'total_sessions_migrated' => \App\Models\MigratedSession::count(),
                'recent_activity' => DataMigration::orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($migration) {
                        return [
                            'id' => $migration->id,
                            'name' => $migration->name,
                            'status' => $migration->status,
                            'updated_at' => $migration->updated_at->diffForHumans()
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export migrated data
     */
    public function export(DataMigration $migration, Request $request)
    {
        $validResources = [
            ResourceType::CLIENT->value,
            ResourceType::CASE->value,
            ResourceType::SESSION->value,
        ];

        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:' . implode(',', $validResources),
            'format' => 'required|in:csv,json,xlsx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $resourceType = ResourceType::resolve($request->resource_type);
            $format = $request->format;

            $batchIds = $migration->batches()
                ->where('resource_type', $resourceType->value)
                ->where('status', 'completed')
                ->pluck('id');

            if ($batchIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => "No completed {$resourceType->value} data found for this migration"
                ], 404);
            }

            // Get the data based on resource type
            switch ($resourceType) {
                case ResourceType::CLIENT:
                    $data = \App\Models\MigratedClient::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
                case ResourceType::CASE:
                    $data = \App\Models\MigratedCase::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
                case ResourceType::SESSION:
                    $data = \App\Models\MigratedSession::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
            }

            // Convert to the requested format
            $filename = "{$migration->name}_{$resourceType->value}_" . now()->format('Y-m-d_H-i-s');

            switch ($format) {
                case 'csv':
                    return $this->exportToCsv($data, $filename);
                case 'json':
                    return $this->exportToJson($data, $filename);
                case 'xlsx':
                    return $this->exportToExcel($data, $filename);
            }
        } catch (\Exception $e) {
            Log::error('Failed to export migration data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    protected function exportToCsv($data, $filename)
    {
        $csv = \League\Csv\Writer::createFromString('');

        if ($data->isNotEmpty()) {
            // Get the resource type from the model class
            $modelClass = get_class($data->first());
            $resourceType = $this->getResourceTypeFromModel($modelClass);

            // Get header mapping and field order
            $headerMapping = $this->getHeaderMapping($resourceType);
            $fieldOrder = array_keys($headerMapping);

            // Add headers (human-readable)
            $headers = array_values($headerMapping);
            $csv->insertOne($headers);

            // Add data rows with proper formatting
            foreach ($data as $row) {
                $formattedRow = $this->formatRowForExport($row, $fieldOrder);
                $csv->insertOne($formattedRow);
            }
        }

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\""
        ]);
    }

    /**
     * Export data to JSON
     */
    protected function exportToJson($data, $filename)
    {
        if ($data->isNotEmpty()) {
            // Get the resource type from the model class
            $modelClass = get_class($data->first());
            $resourceType = $this->getResourceTypeFromModel($modelClass);

            // Get field order (same as CSV for consistency)
            $headerMapping = $this->getHeaderMapping($resourceType);
            $fieldOrder = array_keys($headerMapping);

            // Transform data to only include relevant fields
            $transformedData = $data->map(function ($row) use ($fieldOrder) {
                $filteredRow = [];
                foreach ($fieldOrder as $field) {
                    $value = $row->$field;

                    // Transform specific types for JSON
                    $value = match (true) {
                        // VerificationStatus enum
                        $value instanceof VerificationStatus => $value->value,

                        // Keep dates as ISO strings for JSON
                        $value instanceof \Carbon\Carbon => $value->toISOString(),

                        // Keep other values as-is for JSON (booleans, arrays, etc.)
                        default => $value
                    };

                    $filteredRow[$field] = $value;
                }
                return $filteredRow;
            });

            return response()->json($transformedData, 200, [
                'Content-Disposition' => "attachment; filename=\"{$filename}.json\""
            ]);
        }

        return response()->json([], 200, [
            'Content-Disposition' => "attachment; filename=\"{$filename}.json\""
        ]);
    }

    /**
     * Export data to Excel (simplified)
     */
    protected function exportToExcel($data, $filename)
    {
        // For now, export as CSV with .xlsx extension
        return $this->exportToCsv($data, $filename . '.xlsx');
    }

    /**
     * Perform quick verification of migrated data
     */
    public function quickVerify(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, [DataMigrationStatus::COMPLETED, DataMigrationStatus::FAILED]) && $migration->batches->where('status', DataMigrationBatchStatus::COMPLETED)->count() === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only verify migrations with completed batches'
                ], 400);
            }

            $results = $this->newVerificationService->quickVerifyMigration($migration, 10);

            return response()->json([
                'success' => true,
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Quick verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show verification results for a migration
     */
    public function showVerification(DataMigration $migration)
    {
        return view('data-exchange.migration.verification', compact('migration'));
    }


    /**
     * Get header mappings for CSV export
     */
    private function getHeaderMapping(ResourceType $resourceType): array
    {
        return match ($resourceType) {
            ResourceType::CLIENT => [
                'client_id' => 'Client ID',
                'slk' => 'Statistical Linkage Key',
                'consent_to_provide_details' => 'Consent to Provide Details',
                'consented_for_future_contacts' => 'Consented for Future Contacts',
                'given_name' => 'Given Name',
                'family_name' => 'Family Name',
                'is_using_psuedonym' => 'Using Pseudonym',
                'birth_date' => 'Birth Date',
                'is_birth_date_an_estimate' => 'Birth Date is Estimate',
                'gender_code' => 'Gender Code',
                'gender_details' => 'Gender Details',
                'residential_address' => 'Residential Address (JSON)',
                'country_of_birth_code' => 'Country of Birth Code',
                'language_spoken_at_home_code' => 'Language Spoken at Home Code',
                'aboriginal_or_torres_strait_islander_origin_code' => 'Aboriginal or Torres Strait Islander Origin Code',
                'has_disabilities' => 'Has Disabilities',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date',
                'api_response' => 'Raw API Data (JSON)'
            ],
            ResourceType::CASE => [
                'case_id' => 'Case ID',
                'outlet_name' => 'Outlet Name',
                'client_ids' => 'Client IDs (JSON)',
                'outlet_activity_id' => 'Outlet Activity ID',
                'total_number_of_unidentified_clients' => 'Total Number of Unidentified Clients',
                'client_attendance_profile_code' => 'Client Attendance Profile Code',
                'created_date_time' => 'Created Date Time',
                'end_date' => 'End Date',
                'exit_reason_code' => 'Exit Reason Code',
                'ag_business_type_code' => 'AG Business Type Code',
                'program_activity_name' => 'Program Activity Name',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date',
                'api_response' => 'Raw API Data (JSON)'
            ],
            ResourceType::SESSION => [
                'session_id' => 'Session ID',
                'case_id' => 'Case ID',
                'session_date' => 'Session Date',
                'service_type_id' => 'Service Type ID',
                'total_number_of_unidentified_clients' => 'Total Number of Unidentified Clients',
                'fees_charged' => 'Fees Charged',
                'money_business_community_education_workshop_code' => 'Money/Business/Community Education Workshop Code',
                'interpreter_present' => 'Interpreter Present',
                'service_setting_code' => 'Service Setting Code',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date',
                'api_response' => 'Raw API Data (JSON)'
            ],
            default => []
        };
    }

    /**
     * Format a row for CSV export with proper value transformation
     */
    private function formatRowForExport($row, array $fieldOrder): array
    {
        $formattedRow = [];

        foreach ($fieldOrder as $field) {
            $value = $row->$field ?? '';

            // Transform values based on type
            $value = match (true) {
                // Boolean values
                is_bool($value) => $value ? 'Yes' : 'No',

                // VerificationStatus enum
                $value instanceof VerificationStatus => $value->value,

                // Dates
                $value instanceof \Carbon\Carbon => $value->format('Y-m-d H:i:s'),

                // Make sure deep arrays and objects are JSON strings before conversion.
                ArrayHelpers::isDeepArray($value) => json_encode($value),

                // Simple Arrays (like reasons_for_assistance)
                is_array($value) => implode('; ', $value),

                // Null values
                is_null($value) => '',

                // Default: convert to string
                default => (string) $value
            };

            $formattedRow[] = $value;
        }

        return $formattedRow;
    }

    /**
     * Get resource type from model class
     */
    private function getResourceTypeFromModel(string $modelClass): ResourceType
    {
        return match ($modelClass) {
            MigratedClient::class => ResourceType::CLIENT,
            MigratedCase::class => ResourceType::CASE,
            MigratedSession::class => ResourceType::SESSION,
            default => ResourceType::UNKNOWN
        };
    }

    public function getMigration(DataMigration $migration)
    {
        return $migration;
    }
}
