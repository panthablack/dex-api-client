<?php

namespace App\Jobs;

use App\Enums\VerificationStatus;
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
    public bool $continueMode;

    /**
     * Create a new job instance.
     */
    public function __construct(DataMigration $migration, string $verificationId, bool $continueMode = false)
    {
        $this->migration = $migration;
        $this->verificationId = $verificationId;
        $this->continueMode = $continueMode;
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
            'continue_mode' => $this->continueMode,
            'continue_mode_type' => gettype($this->continueMode),
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
                        $query = $modelClass::whereIn('migration_batch_id', $batchIds);

                        // In continue mode, only count failed and pending records
                        if ($this->continueMode) {
                            $query->whereIn('verification_status', [VerificationStatus::FAILED, VerificationStatus::PENDING]);
                        }

                        $count = $query->count();

                        Log::info("Resource count in continue mode", [
                            'migration_id' => $this->migration->id,
                            'resource_type' => $resourceType,
                            'continue_mode' => $this->continueMode,
                            'total_records_query' => $count,
                            'batch_ids' => $batchIds
                        ]);

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

            // Check if all records were actually processed - PREVENTION MEASURE
            $actualPendingCount = 0;
            foreach ($this->migration->resource_types as $resourceType) {
                $modelClass = $this->getModelClass($resourceType);
                if ($modelClass) {
                    $batchIds = $this->migration->batches()
                        ->where('resource_type', $resourceType)
                        ->where('status', 'completed')
                        ->pluck('batch_id')
                        ->toArray();

                    if (!empty($batchIds)) {
                        $pending = $modelClass::whereIn('migration_batch_id', $batchIds)
                            ->where('verification_status', VerificationStatus::PENDING)
                            ->count();
                        $actualPendingCount += $pending;
                    }
                }
            }

            // Determine final status based on actual completion
            $finalStatus = 'completed';
            $finalActivity = 'Verification completed';

            if ($actualPendingCount > 0) {
                $finalStatus = 'partial';
                $finalActivity = "Verification partially completed. {$actualPendingCount} records still pending due to API issues. Use 'Continue Verification' to retry.";

                Log::warning("VerifyMigrationJob completed with pending records", [
                    'migration_id' => $this->migration->id,
                    'verification_id' => $this->verificationId,
                    'pending_count' => $actualPendingCount,
                    'processed_count' => $processedRecords,
                    'total_count' => $totalRecords
                ]);
            }

            // Mark with appropriate status
            Cache::put("verification_status_{$this->verificationId}", [
                'status' => $finalStatus,
                'total' => $totalRecords,
                'processed' => $processedRecords,
                'verified' => $verifiedRecords,
                'pending_count' => $actualPendingCount,
                'current_activity' => $finalActivity,
                'resource_progress' => $resourceProgress,
                'results' => $finalResults['results'] ?? []
            ], 3600);

            // Log with appropriate status based on actual completion
            $logMessage = $finalStatus === 'completed'
                ? "VerifyMigrationJob COMPLETED SUCCESSFULLY"
                : "VerifyMigrationJob COMPLETED PARTIALLY";

            Log::info($logMessage, [
                'migration_id' => $this->migration->id,
                'verification_id' => $this->verificationId,
                'final_status' => $finalStatus,
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'verified_records' => $verifiedRecords,
                'pending_records' => $actualPendingCount,
                'success_rate' => $totalRecords > 0 ? round(($verifiedRecords / $totalRecords) * 100, 2) : 0,
                'completion_rate' => $totalRecords > 0 ? round((($processedRecords) / $totalRecords) * 100, 2) : 0,
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
        $query = $modelClass::whereIn('migration_batch_id', $batchIds);

        // In continue mode, only process failed and pending records
        if ($this->continueMode) {
            $query->whereIn('verification_status', [VerificationStatus::FAILED, VerificationStatus::PENDING]);
        }

        $query->chunk(50, function ($records) use ($resourceType, $verificationService, $totalRecords, &$processedRecords, &$verifiedRecords, &$resourceProgress, &$allErrors) {
                foreach ($records as $record) {
                    // In full mode, records should only be PENDING (due to reset)
                    // In continue mode, we only loaded FAILED and PENDING records
                    // So we can verify all loaded records
                    try {
                        $success = $verificationService->verifyRecord($resourceType, $record);

                        // Only count as processed if verification was actually attempted
                        // (circuit breaker might skip some records)
                        $record->refresh(); // Get current verification status

                        if ($record->verification_status !== VerificationStatus::PENDING) {
                            // Record status changed, so it was processed (either verified or failed)
                            $processedRecords++;
                            $resourceProgress[$resourceType]['processed']++;

                            if ($success) {
                                $verifiedRecords++;
                            } else {
                                // Record was updated with verification_error by VerificationService
                                if ($record->verification_error) {
                                    $allErrors[$resourceType][] = $record->verification_error;
                                }
                            }
                        } else {
                            // Record is still pending - circuit breaker likely skipped it
                            Log::debug("Record skipped due to circuit breaker", [
                                'migration_id' => $this->migration->id,
                                'resource_type' => $resourceType,
                                'record_id' => $this->getRecordId($resourceType, $record)
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Count exceptions as processed since we attempted verification
                        $processedRecords++;
                        $resourceProgress[$resourceType]['processed']++;

                        // Log exception but VerificationService should have handled DB update
                        Log::warning("Exception during verification", [
                            'migration_id' => $this->migration->id,
                            'resource_type' => $resourceType,
                            'record_id' => $record->id,
                            'error' => $e->getMessage()
                        ]);
                        $allErrors[$resourceType][] = "Error verifying record: " . $e->getMessage();
                    }

                    // Update progress every 10 records + heartbeat
                    if ($processedRecords % 10 === 0) {
                        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}... ({$processedRecords}/{$totalRecords})", $resourceProgress);

                        // Heartbeat mechanism - check if job should be stopped
                        $this->updateHeartbeat();
                        if ($this->shouldStop()) {
                            Log::info("VerifyMigrationJob stopping due to external request", [
                                'migration_id' => $this->migration->id,
                                'verification_id' => $this->verificationId,
                                'processed_so_far' => $processedRecords
                            ]);

                            $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Verification stopped by user request", $resourceProgress);
                            return; // Exit the processing
                        }
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
            'resource_progress' => $resourceProgress,
            'last_heartbeat' => time() // Heartbeat timestamp
        ], 3600);
    }

    /**
     * Update heartbeat to show job is still alive - PREVENTION MEASURE
     */
    private function updateHeartbeat(): void
    {
        $heartbeatKey = "verification_heartbeat_{$this->verificationId}";
        Cache::put($heartbeatKey, [
            'timestamp' => time(),
            'migration_id' => $this->migration->id,
            'verification_id' => $this->verificationId,
            'status' => 'running'
        ], 1800); // 30 minutes TTL
    }

    /**
     * Check if job should be stopped based on external signals
     */
    private function shouldStop(): bool
    {
        $stopKey = "verification_stop_{$this->verificationId}";
        return Cache::has($stopKey);
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

    /**
     * Get record ID for logging purposes
     */
    private function getRecordId(string $resourceType, $record): string
    {
        return match ($resourceType) {
            'clients' => $record->client_id ?? $record->id,
            'cases' => $record->case_id ?? $record->id,
            'sessions' => $record->session_id ?? $record->id,
            default => $record->id ?? 'unknown'
        };
    }
}
