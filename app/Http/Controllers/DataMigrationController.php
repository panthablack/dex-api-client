<?php

namespace App\Http\Controllers;

use App\Enums\VerificationStatus;
use App\Models\DataMigration;
use App\Models\VerificationSession;
use App\Services\DataMigrationService;
use App\Services\DataVerificationService;
use App\Services\VerificationService;
use App\Jobs\InitiateDataMigration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DataMigrationController extends Controller
{
    protected DataMigrationService $migrationService;
    protected DataVerificationService $verificationService;
    protected VerificationService $newVerificationService;

    public function __construct(
        DataMigrationService $migrationService,
        DataVerificationService $verificationService,
        VerificationService $newVerificationService
    ) {
        $this->migrationService = $migrationService;
        $this->verificationService = $verificationService;
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
            'resource_types' => 'required|array|min:1',
            'resource_types.*' => 'in:clients,cases,sessions',
            'batch_size' => 'integer|min:10|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

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
                'resource_types' => $request->resource_types,
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
            if ($migration->status === 'pending' || ($failedBatches === 0 && $pendingBatches > 0)) {
                // First try to restart normally
                $this->migrationService->restartMigration($migration);

                // If still stuck, try synchronous processing
                if ($pendingBatches > 0) {
                    Log::info("Attempting synchronous processing for stuck migration {$migration->id}");
                    $this->migrationService->processMigrationSynchronously($migration);
                }

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
            if ($migration->status === 'in_progress') {
                return redirect()->back()
                    ->with('error', 'Cannot delete migration that is currently in progress. Cancel it first.');
            }

            // Delete related migrated data based on batch IDs
            $batchIds = $migration->batches()->pluck('batch_id');

            if ($batchIds->isNotEmpty()) {
                // Delete migrated records
                \App\Models\MigratedClient::whereIn('migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedCase::whereIn('migration_batch_id', $batchIds)->delete();
                \App\Models\MigratedSession::whereIn('migration_batch_id', $batchIds)->delete();
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
                        'resource_types' => $migration->resource_types,
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
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|in:clients,cases,sessions',
            'format' => 'required|in:csv,json,xlsx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $resourceType = $request->resource_type;
            $format = $request->format;

            $batchIds = $migration->batches()
                ->where('resource_type', $resourceType)
                ->where('status', 'completed')
                ->pluck('batch_id');

            if ($batchIds->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => "No completed {$resourceType} data found for this migration"
                ], 404);
            }

            // Get the data based on resource type
            switch ($resourceType) {
                case 'clients':
                    $data = \App\Models\MigratedClient::whereIn('migration_batch_id', $batchIds)->get();
                    break;
                case 'cases':
                    $data = \App\Models\MigratedCase::whereIn('migration_batch_id', $batchIds)->get();
                    break;
                case 'sessions':
                    $data = \App\Models\MigratedSession::whereIn('migration_batch_id', $batchIds)->get();
                    break;
            }

            // Convert to the requested format
            $filename = "{$migration->name}_{$resourceType}_" . now()->format('Y-m-d_H-i-s');

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
                        $value instanceof \App\Enums\VerificationStatus => $value->value,

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
            if (!in_array($migration->status, ['completed', 'failed']) && $migration->batches->where('status', 'completed')->count() === 0) {
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
     * Perform full verification of migrated data
     */
    public function fullVerify(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['completed', 'failed'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only verify completed or failed migrations'
                ], 400);
            }

            // Check if there's already an active verification session
            $activeSession = $migration->activeVerificationSession()->first();
            if ($activeSession) {
                return response()->json([
                    'success' => false,
                    'error' => 'Verification is already in progress'
                ], 400);
            }

            // Reset all verification states before starting new verification
            $this->resetVerificationStates($migration);

            Log::info("Starting full verification for migration {$migration->id}", [
                'migration_id' => $migration->id,
                'migration_name' => $migration->name,
                'resource_types' => $migration->resource_types,
                'queue_connection' => config('queue.default')
            ]);

            // Create verification session
            $verificationSession = VerificationSession::create([
                'migration_id' => $migration->id,
                'type' => 'full',
                'status' => 'starting',
                'current_activity' => 'Initializing full verification...',
            ]);

            // Dispatch async verification job
            try {
                \App\Jobs\VerifyMigrationJob::dispatch($verificationSession);
                Log::info("VerifyMigrationJob dispatched successfully", [
                    'migration_id' => $migration->id,
                    'verification_session_id' => $verificationSession->id,
                    'queue_connection' => config('queue.default')
                ]);
            } catch (\Exception $e) {
                // Clean up the session if job dispatch fails
                $verificationSession->delete();
                Log::error("Failed to dispatch VerifyMigrationJob", [
                    'migration_id' => $migration->id,
                    'verification_session_id' => $verificationSession->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_session_id' => $verificationSession->id,
                    'status' => 'starting',
                    'message' => 'Full verification started. Check status for progress.'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Full verification failed: ' . $e->getMessage());
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
     * Continue verification of failed and pending records only
     */
    public function continueVerification(DataMigration $migration): JsonResponse
    {
        try {
            if (!in_array($migration->status, ['completed', 'failed'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Can only verify completed or failed migrations'
                ], 400);
            }

            // Check if there's already an active verification session
            $activeSession = $migration->activeVerificationSession()->first();
            if ($activeSession) {
                return response()->json([
                    'success' => false,
                    'error' => 'Verification is already in progress'
                ], 400);
            }

            // Check if there are any failed or pending records to continue with
            $hasUnverifiedRecords = false;
            foreach ($migration->resource_types as $resourceType) {
                $modelClass = $this->getModelClass($resourceType);
                if ($modelClass) {
                    $batchIds = $migration->batches()
                        ->where('resource_type', $resourceType)
                        ->where('status', 'completed')
                        ->pluck('batch_id')
                        ->toArray();

                    if (!empty($batchIds)) {
                        $unverifiedCount = $modelClass::whereIn('migration_batch_id', $batchIds)
                            ->whereIn('verification_status', [VerificationStatus::FAILED, VerificationStatus::PENDING])
                            ->count();

                        if ($unverifiedCount > 0) {
                            $hasUnverifiedRecords = true;
                            break;
                        }
                    }
                }
            }

            if (!$hasUnverifiedRecords) {
                return response()->json([
                    'success' => false,
                    'error' => 'No failed or pending records to continue verification with'
                ], 400);
            }

            Log::info("Continuing verification for migration {$migration->id}", [
                'migration_id' => $migration->id,
                'migration_name' => $migration->name,
                'resource_types' => $migration->resource_types,
                'queue_connection' => config('queue.default')
            ]);

            // Create verification session
            $verificationSession = VerificationSession::create([
                'migration_id' => $migration->id,
                'type' => 'continue',
                'status' => 'starting',
                'current_activity' => 'Initializing continue verification...',
            ]);

            // Dispatch async verification job in continue mode
            try {
                \App\Jobs\VerifyMigrationJob::dispatch($verificationSession);
                Log::info("Continue VerifyMigrationJob dispatched successfully", [
                    'migration_id' => $migration->id,
                    'verification_session_id' => $verificationSession->id,
                    'queue_connection' => config('queue.default')
                ]);
            } catch (\Exception $e) {
                // Clean up the session if job dispatch fails
                $verificationSession->delete();
                Log::error("Failed to dispatch continue VerifyMigrationJob", [
                    'migration_id' => $migration->id,
                    'verification_session_id' => $verificationSession->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'verification_session_id' => $verificationSession->id,
                    'status' => 'starting',
                    'message' => 'Continue verification started. Only failed and pending records will be re-verified.'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Continue verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Stop an in-progress verification
     */
    public function stopVerification(DataMigration $migration): JsonResponse
    {
        try {
            // Find active verification session
            $activeSession = $migration->activeVerificationSession()->first();

            if (!$activeSession) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'idle',
                        'stopped' => false,
                        'message' => 'No active verification found to stop.'
                    ]
                ]);
            }

            // Mark the session as stopping
            $activeSession->update([
                'status' => 'stopping',
                'current_activity' => 'Stopping verification...'
            ]);

            Log::info("Verification stop requested for migration {$migration->id}", [
                'migration_id' => $migration->id,
                'verification_session_id' => $activeSession->id
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'stopping',
                    'stopped' => true,
                    'message' => 'Verification stop requested. The process will halt at the next safe checkpoint.'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Stop verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get verification status for a migration
     */
    public function verificationStatus(DataMigration $migration, Request $request): JsonResponse
    {
        try {
            // Get the latest verification session (if any)
            $verificationSession = $migration->latestVerificationSession()->first();

            // Get verification stats from the VerificationService
            $stats = $this->newVerificationService->getMigrationVerificationStats($migration);

            // Build resource progress
            $resourceProgress = [];
            foreach ($migration->resource_types as $resourceType) {
                $resultData = $stats['results'][$resourceType] ?? ['total' => 0, 'verified' => 0, 'failed' => 0];
                $resourceProgress[$resourceType] = [
                    'total' => $resultData['total'],
                    'processed' => $resultData['verified'] + $resultData['failed']
                ];
            }

            // If there's an active verification session, use its data
            if ($verificationSession && $verificationSession->isActive()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => $verificationSession->status,
                        'total' => $verificationSession->total_records,
                        'processed' => $verificationSession->processed_records,
                        'verified' => $verificationSession->verified_records,
                        'current_activity' => $verificationSession->current_activity,
                        'resource_progress' => $verificationSession->resource_progress ?: $resourceProgress,
                        'results' => $stats['results'] ?? []
                    ]
                ]);
            }

            // Determine status based on database verification state
            $verificationStatus = 'idle';
            $currentActivity = 'No verification has been run yet';

            if ($stats['verified'] > 0 || $stats['failed'] > 0) {
                $totalProcessed = $stats['verified'] + $stats['failed'];
                if ($totalProcessed === $stats['total']) {
                    $verificationStatus = 'completed';
                    $currentActivity = "All records processed: {$stats['verified']} verified, {$stats['failed']} failed";
                } else {
                    $verificationStatus = 'partial';
                    $currentActivity = "Partial verification: {$totalProcessed} of {$stats['total']} records processed";
                }
            }

            // Return database state (default case)
            Log::info("Returning database verification status", [
                'migration_id' => $migration->id,
                'status' => $verificationStatus,
                'total' => $stats['total'],
                'verified' => $stats['verified']
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => $verificationStatus,
                    'total' => $stats['total'],
                    'processed' => $stats['verified'] + $stats['failed'],
                    'verified' => $stats['verified'],
                    'current_activity' => $currentActivity,
                    'resource_progress' => $resourceProgress,
                    'results' => $stats['results'] ?? []
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Verification status check failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset verification states for all records in a migration
     */
    private function resetVerificationStates(DataMigration $migration): void
    {
        Log::info("Resetting verification states for migration {$migration->id}");

        $totalReset = 0;

        foreach ($migration->resource_types as $resourceType) {
            $modelClass = $this->getModelClass($resourceType);
            if ($modelClass) {
                // Get the batch IDs for this migration and resource type
                $batchIds = $migration->batches()
                    ->where('resource_type', $resourceType)
                    ->where('status', 'completed')
                    ->pluck('batch_id')
                    ->toArray();

                if (!empty($batchIds)) {
                    $resetCount = $modelClass::whereIn('migration_batch_id', $batchIds)
                        ->update([
                            'verification_status' => VerificationStatus::PENDING,
                            'verified_at' => null,
                            'verification_error' => null
                        ]);

                    $totalReset += $resetCount;

                    Log::info("Reset verification states for resource type", [
                        'migration_id' => $migration->id,
                        'resource_type' => $resourceType,
                        'records_reset' => $resetCount
                    ]);
                }
            }
        }

        Log::info("Verification state reset completed", [
            'migration_id' => $migration->id,
            'total_records_reset' => $totalReset
        ]);
    }

    /**
     * Get header mappings for CSV export
     */
    private function getHeaderMapping(string $resourceType): array
    {
        return match ($resourceType) {
            'clients' => [
                'client_id' => 'Client ID',
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'date_of_birth' => 'Date of Birth',
                'is_birth_date_estimate' => 'Birth Date Estimate',
                'gender' => 'Gender',
                'suburb' => 'Suburb',
                'state' => 'State',
                'postal_code' => 'Postal Code',
                'country_of_birth' => 'Country of Birth',
                'primary_language' => 'Primary Language',
                'indigenous_status' => 'Indigenous Status',
                'interpreter_required' => 'Interpreter Required',
                'disability_flag' => 'Disability Flag',
                'is_using_pseudonym' => 'Using Pseudonym',
                'consent_to_provide_details' => 'Consent to Provide Details',
                'consent_to_be_contacted' => 'Consent to be Contacted',
                'client_type' => 'Client Type',
                'migrated_at' => 'Migration Date',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date'
            ],
            'cases' => [
                'case_id' => 'Case ID',
                'client_id' => 'Client ID',
                'outlet_activity_id' => 'Outlet Activity ID',
                'referral_source_code' => 'Referral Source Code',
                'reasons_for_assistance' => 'Reasons for Assistance',
                'total_unidentified_clients' => 'Total Unidentified Clients',
                'client_attendance_profile_code' => 'Client Attendance Profile Code',
                'end_date' => 'End Date',
                'exit_reason_code' => 'Exit Reason Code',
                'ag_business_type_code' => 'AG Business Type Code',
                'migrated_at' => 'Migration Date',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date'
            ],
            'sessions' => [
                'session_id' => 'Session ID',
                'case_id' => 'Case ID',
                'service_type_id' => 'Service Type ID',
                'session_date' => 'Session Date',
                'duration_minutes' => 'Duration (Minutes)',
                'location' => 'Location',
                'session_status' => 'Session Status',
                'attendees' => 'Attendees',
                'outcome' => 'Outcome',
                'notes' => 'Notes',
                'migrated_at' => 'Migration Date',
                'verification_status' => 'Verification Status',
                'verified_at' => 'Verified Date'
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
                $value instanceof \App\Enums\VerificationStatus => $value->value,

                // Dates
                $value instanceof \Carbon\Carbon => $value->format('Y-m-d H:i:s'),

                // Arrays (like reasons_for_assistance)
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
    private function getResourceTypeFromModel(string $modelClass): string
    {
        return match ($modelClass) {
            \App\Models\MigratedClient::class => 'clients',
            \App\Models\MigratedCase::class => 'cases',
            \App\Models\MigratedSession::class => 'sessions',
            default => 'unknown'
        };
    }

    /**
     * Get the model class for a resource type
     */
    private function getModelClass(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => \App\Models\MigratedClient::class,
            'cases' => \App\Models\MigratedCase::class,
            'sessions' => \App\Models\MigratedSession::class,
            default => null
        };
    }
}
