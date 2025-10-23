<?php

namespace App\Http\Controllers;

use App\Enums\DataMigrationStatus;
use App\Enums\DataMigrationBatchStatus;
use App\Enums\FilterType;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use App\Helpers\ArrayHelpers;
use App\Resources\Filters;
use App\Models\DataMigration;
use App\Services\DataMigrationService;
use App\Services\VerificationService;
use App\Jobs\InitiateDataMigration;
use App\Models\MigratedCase;
use App\Models\MigratedClient;
use App\Models\MigratedSession;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DataMigrationController extends Controller
{
    protected DataMigrationService $migrationService;
    protected VerificationService $newVerificationService;
    protected ExportService $exportService;

    public function __construct(
        DataMigrationService $migrationService,
        VerificationService $newVerificationService,
        ExportService $exportService,
    ) {
        $this->migrationService = $migrationService;
        $this->newVerificationService = $newVerificationService;
        $this->exportService = $exportService;
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

        $resourceType = ResourceType::resolve($request->resource_type);

        // Additional validation to check resource is migratable
        if (!ResourceType::isMigratable($resourceType)) {
            $validator->after(function ($validator) use ($resourceType) {
                $validator->errors()->add('resource_type', "The resource type '$resourceType' cannot be migrated.");
            });
        }

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $filters = new Filters();

            $filters->set(FilterType::IS_ASCENDING, true);
            $filters->set(
                FilterType::SORT_COLUMN,
                DataMigrationService::getDexSortColumn($resourceType)
            );

            if ($request->date_from)
                $filters->set(FilterType::CREATED_DATE_FROM, $request->date_from);

            if ($request->date_to)
                $filters->set(FilterType::CREATED_DATE_TO, $request->date_to);

            $migration = $this->migrationService->createMigration([
                'name' => $request->name,
                'resource_type' => $resourceType,
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
            $failedBatches = $migration->failedBatches();
            $pendingBatches = $migration->pendingBatches();
            $failedBatchesCount = $failedBatches->count();
            $pendingBatchesCount = $pendingBatches->count();

            // Handle stuck/pending migrations
            if ($migration->status === DataMigrationStatus::PENDING || ($failedBatchesCount === 0 && $pendingBatchesCount > 0)) {
                $this->migrationService->restartMigration($migration);
                return response()->json([
                    'success' => true,
                    'message' => "Restarted stuck migration with {$pendingBatchesCount} pending batches"
                ]);
            }

            // Handle failed batches
            if ($failedBatchesCount === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No failed batches to retry'
                ], 400);
            }

            $this->migrationService->retryFailedBatches($migration);

            return response()->json([
                'success' => true,
                'message' => "Retrying {$failedBatchesCount} failed batches"
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
            ResourceType::CLOSED_CASE->value,
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
                    $data = MigratedClient::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
                case ResourceType::CASE:
                    $data = MigratedCase::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
                case ResourceType::CLOSED_CASE:
                    $data = MigratedCase::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
                case ResourceType::SESSION:
                    $data = MigratedSession::whereIn('data_migration_batch_id', $batchIds)->get();
                    break;
            }

            $filename = "{$migration->name}_{$resourceType->value}_" . now()->format('Y-m-d_H-i-s');
            $headerMapping = self::getHeaderMappingForResourceType($resourceType);

            return $this->exportService->export($data, $headerMapping, $format, $filename);
        } catch (\Exception $e) {
            Log::error('Failed to export migration data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
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
     * Get header mapping for a resource type
     * Static method to share with other controllers
     */
    public static function getHeaderMappingForResourceType(ResourceType $resourceType): array
    {
        $caseMappings = [
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
            'sessions' => 'Sessions',
            'verification_status' => 'Verification Status',
            'verified_at' => 'Verified Date',
            'api_response' => 'Raw API Data (JSON)'
        ];

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
            ResourceType::CASE => $caseMappings,
            ResourceType::CLOSED_CASE => $caseMappings,
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

    public function getMigration(DataMigration $migration)
    {
        return $migration;
    }
}
