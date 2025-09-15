<?php

namespace App\Jobs;

use App\Models\DataMigration;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VerifyMigrationJob implements ShouldQueue
{
    use Queueable;

    public DataMigration $migration;
    public string $verificationId;

    /**
     * Create a new job instance.
     */
    public function __construct(DataMigration $migration, string $verificationId)
    {
        $this->migration = $migration;
        $this->verificationId = $verificationId;
        $this->onQueue('data-verification');
    }

    /**
     * Execute the job.
     */
    public function handle(VerificationService $verificationService): void
    {
        Log::info("VerifyMigrationJob STARTED", [
            'migration_id' => $this->migration->id,
            'verification_id' => $this->verificationId,
            'migration_name' => $this->migration->name,
            'resource_types' => $this->migration->resource_types,
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'queue' => $this->job->getQueue() ?? 'default',
            'memory_usage' => memory_get_usage(true)
        ]);

        // Set initial status
        Cache::put("verification_status_{$this->verificationId}", [
            'status' => 'starting',
            'total' => 0,
            'processed' => 0,
            'verified' => 0,
            'current_activity' => 'Initializing verification...'
        ], 3600);

        try {
            $totalRecords = 0;
            $processedRecords = 0;
            $verifiedRecords = 0;
            $resourceProgress = [];
            $allErrors = [];

            // Count total records to process
            foreach ($this->migration->resource_types as $resourceType) {
                $modelClass = $this->getModelClass($resourceType);
                if ($modelClass) {
                    // Get the batch IDs for this migration and resource type
                    $batchIds = $this->migration->batches()
                        ->where('resource_type', $resourceType)
                        ->where('status', 'completed')
                        ->pluck('batch_id')
                        ->toArray();

                    if (!empty($batchIds)) {
                        $count = $modelClass::whereIn('migration_batch_id', $batchIds)->count();
                        $totalRecords += $count;
                        $resourceProgress[$resourceType] = [
                            'total' => $count,
                            'processed' => 0
                        ];
                    } else {
                        $resourceProgress[$resourceType] = [
                            'total' => 0,
                            'processed' => 0
                        ];
                    }
                }
            }

            // Update status with total count
            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'in_progress',
                'total' => $totalRecords,
                'processed' => 0,
                'verified' => 0,
                'current_activity' => 'Processing records...',
                'resource_progress' => $resourceProgress
            ], 3600);

            // Process each resource type
            foreach ($this->migration->resource_types as $resourceType) {
                $this->processResourceType($resourceType, $verificationService, $totalRecords, $processedRecords, $verifiedRecords, $resourceProgress, $allErrors);
            }

            // Get final results from verification service
            $finalResults = $verificationService->getMigrationVerificationStats($this->migration);

            // Add collected errors to results
            foreach ($allErrors as $resourceType => $errors) {
                if (isset($finalResults['results'][$resourceType])) {
                    $finalResults['results'][$resourceType]['errors'] = array_slice($errors, 0, 100); // Limit to 100 errors
                }
            }

            // Mark as completed
            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $processedRecords,
                'verified' => $verifiedRecords,
                'current_activity' => 'Verification completed',
                'resource_progress' => $resourceProgress,
                'results' => $finalResults['results'] ?? []
            ], 3600);

            Log::info("VerifyMigrationJob COMPLETED SUCCESSFULLY", [
                'migration_id' => $this->migration->id,
                'verification_id' => $this->verificationId,
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'verified_records' => $verifiedRecords,
                'success_rate' => $totalRecords > 0 ? round(($verifiedRecords / $totalRecords) * 100, 2) : 0,
                'duration_seconds' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
                'memory_peak' => memory_get_peak_usage(true)
            ]);
        } catch (\Exception $e) {
            Log::error("VerifyMigrationJob FAILED", [
                'migration_id' => $this->migration->id,
                'verification_id' => $this->verificationId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true)
            ]);

            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'current_activity' => 'Verification failed'
            ], 3600);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("VerifyMigrationJob PERMANENTLY FAILED", [
            'migration_id' => $this->migration->id,
            'verification_id' => $this->verificationId,
            'exception_message' => $exception?->getMessage(),
            'exception_file' => $exception?->getFile(),
            'exception_line' => $exception?->getLine(),
            'exception_trace' => $exception?->getTraceAsString()
        ]);

        // Update cache to show failure
        Cache::put("verification_status_{$this->verificationId}", [
            'status' => 'failed',
            'error' => $exception?->getMessage() ?? 'Unknown job failure',
            'current_activity' => 'Job failed permanently'
        ], 3600);
    }

    private function processResourceType(string $resourceType, VerificationService $verificationService, int $totalRecords, int &$processedRecords, int &$verifiedRecords, array &$resourceProgress, array &$allErrors): void
    {
        $modelClass = $this->getModelClass($resourceType);
        if (!$modelClass) return;

        // Get the batch IDs for this migration and resource type
        $batchIds = $this->migration->batches()
            ->where('resource_type', $resourceType)
            ->where('status', 'completed')
            ->pluck('batch_id')
            ->toArray();

        if (empty($batchIds)) {
            Log::info("No completed batches found for resource type", [
                'migration_id' => $this->migration->id,
                'resource_type' => $resourceType,
                'verification_id' => $this->verificationId
            ]);
            return;
        }

        Log::info("Processing resource type", [
            'migration_id' => $this->migration->id,
            'verification_id' => $this->verificationId,
            'resource_type' => $resourceType,
            'batch_count' => count($batchIds),
            'batch_ids' => $batchIds
        ]);

        // Initialize error collection for this resource type
        if (!isset($allErrors[$resourceType])) {
            $allErrors[$resourceType] = [];
        }

        // Update activity
        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}...", $resourceProgress);

        // Process in chunks to avoid memory issues
        $modelClass::whereIn('migration_batch_id', $batchIds)
            ->chunk(50, function ($records) use ($resourceType, $verificationService, $totalRecords, &$processedRecords, &$verifiedRecords, &$resourceProgress, &$allErrors) {
                foreach ($records as $record) {
                    // Skip already verified records
                    if ($record->verified) {
                        $verifiedRecords++;
                        $processedRecords++;
                        $resourceProgress[$resourceType]['processed']++;
                        continue;
                    }

                    // Verify the record using same method as Quick Verify
                    try {
                        $success = $verificationService->verifyRecord($resourceType, $record);

                        // VerificationService handles all database updates
                        // Just track if it was successful for progress counting
                        if ($success) {
                            $verifiedRecords++;
                        } else {
                            // Record was updated with verification_error by VerificationService
                            // Just collect error for cache display
                            $record->refresh(); // Get updated verification_error
                            if ($record->verification_error) {
                                $allErrors[$resourceType][] = $record->verification_error;
                            }
                        }
                    } catch (\Exception $e) {
                        // Log exception but VerificationService should have handled DB update
                        Log::warning("Exception during verification", [
                            'migration_id' => $this->migration->id,
                            'resource_type' => $resourceType,
                            'record_id' => $record->id,
                            'error' => $e->getMessage()
                        ]);
                        $allErrors[$resourceType][] = "Error verifying record: " . $e->getMessage();
                    }

                    $processedRecords++;
                    $resourceProgress[$resourceType]['processed']++;

                    // Update progress every 10 records
                    if ($processedRecords % 10 === 0) {
                        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}... ({$processedRecords}/{$totalRecords})", $resourceProgress);
                    }
                }
            });
    }

    private function updateStatus(int $total, int $processed, int $verified, string $activity, array $resourceProgress): void
    {
        Cache::put("verification_status_{$this->verificationId}", [
            'status' => 'in_progress',
            'total' => $total,
            'processed' => $processed,
            'verified' => $verified,
            'current_activity' => $activity,
            'resource_progress' => $resourceProgress
        ], 3600);
    }

    private function getModelClass(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => MigratedClient::class,
            'cases' => MigratedCase::class,
            'sessions' => MigratedSession::class,
            default => null
        };
    }
}
