<?php

namespace App\Jobs;

use App\Enums\VerificationStatus;
use App\Models\DataMigration;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Models\VerificationSession;
use App\Services\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyMigrationJob implements ShouldQueue
{
    use Queueable;

    public VerificationSession $verificationSession;

    /**
     * Create a new job instance.
     */
    public function __construct(VerificationSession $verificationSession)
    {
        $this->verificationSession = $verificationSession;
        $this->onQueue('data-verification');
    }

    /**
     * Execute the job.
     */
    public function handle(VerificationService $verificationService): void
    {
        $migration = $this->verificationSession->migration;

        Log::info("VerifyMigrationJob STARTED", [
            'migration_id' => $migration->id,
            'verification_session_id' => $this->verificationSession->id,
            'migration_name' => $migration->name,
            'resource_types' => $migration->resource_types,
            'verification_type' => $this->verificationSession->type,
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'queue' => $this->job->getQueue() ?? 'default',
            'memory_usage' => memory_get_usage(true)
        ]);

        // Update session to in_progress
        $this->verificationSession->update([
            'status' => 'in_progress',
            'current_activity' => 'Initializing verification...',
            'started_at' => now()
        ]);

        try {
            $totalRecords = 0;
            $processedRecords = 0;
            $verifiedRecords = 0;
            $resourceProgress = [];
            $allErrors = [];

            // Count total records to process
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
                        $query = $modelClass::whereIn('migration_batch_id', $batchIds);

                        // In continue mode, only count failed and pending records
                        if ($this->verificationSession->type === 'continue') {
                            $query->whereIn('verification_status', [VerificationStatus::FAILED, VerificationStatus::PENDING]);
                        }

                        $count = $query->count();

                        Log::info("Resource count for verification", [
                            'migration_id' => $migration->id,
                            'resource_type' => $resourceType,
                            'verification_type' => $this->verificationSession->type,
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

            // Update session with total count
            $this->verificationSession->updateProgress(
                $totalRecords,
                0,
                0,
                0,
                $resourceProgress
            );
            $this->verificationSession->updateActivity('Processing records...');

            // Process each resource type
            foreach ($migration->resource_types as $resourceType) {
                $this->processResourceType($resourceType, $verificationService, $totalRecords, $processedRecords, $verifiedRecords, $resourceProgress, $allErrors);
            }

            // Check if all records were actually processed
            $actualPendingCount = 0;
            foreach ($migration->resource_types as $resourceType) {
                $modelClass = $this->getModelClass($resourceType);
                if ($modelClass) {
                    $batchIds = $migration->batches()
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
            $finalActivity = 'Verification completed successfully';

            if ($actualPendingCount > 0) {
                $finalStatus = 'partial';
                $finalActivity = "Verification partially completed. {$actualPendingCount} records still pending due to API issues. Use 'Continue Verification' to retry.";

                Log::warning("VerifyMigrationJob completed with pending records", [
                    'migration_id' => $migration->id,
                    'verification_session_id' => $this->verificationSession->id,
                    'pending_count' => $actualPendingCount,
                    'processed_count' => $processedRecords,
                    'total_count' => $totalRecords
                ]);
            }

            // Mark verification session as completed
            $this->verificationSession->markCompleted($finalStatus, $finalActivity);

            // Log completion
            $logMessage = $finalStatus === 'completed'
                ? "VerifyMigrationJob COMPLETED SUCCESSFULLY"
                : "VerifyMigrationJob COMPLETED PARTIALLY";

            Log::info($logMessage, [
                'migration_id' => $migration->id,
                'verification_session_id' => $this->verificationSession->id,
                'final_status' => $finalStatus,
                'total_records' => $totalRecords,
                'processed_records' => $processedRecords,
                'verified_records' => $verifiedRecords,
                'pending_records' => $actualPendingCount,
                'success_rate' => $totalRecords > 0 ? round(($verifiedRecords / $totalRecords) * 100, 2) : 0,
                'completion_rate' => $totalRecords > 0 ? round((($processedRecords) / $totalRecords) * 100, 2) : 0,
                'memory_peak' => memory_get_peak_usage(true)
            ]);
        } catch (\Exception $e) {
            Log::error("VerifyMigrationJob FAILED", [
                'migration_id' => $migration->id,
                'verification_session_id' => $this->verificationSession->id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true)
            ]);

            $this->verificationSession->markCompleted('failed', 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("VerifyMigrationJob PERMANENTLY FAILED", [
            'migration_id' => $this->verificationSession->migration_id,
            'verification_session_id' => $this->verificationSession->id,
            'exception_message' => $exception?->getMessage(),
            'exception_file' => $exception?->getFile(),
            'exception_line' => $exception?->getLine(),
            'exception_trace' => $exception?->getTraceAsString()
        ]);

        // Update verification session to show failure
        $this->verificationSession->markCompleted(
            'failed',
            'Job failed permanently: ' . ($exception?->getMessage() ?? 'Unknown job failure')
        );
    }

    private function processResourceType(string $resourceType, VerificationService $verificationService, int $totalRecords, int &$processedRecords, int &$verifiedRecords, array &$resourceProgress, array &$allErrors): void
    {
        $migration = $this->verificationSession->migration;
        $modelClass = $this->getModelClass($resourceType);
        if (!$modelClass) return;

        // Get the batch IDs for this migration and resource type
        $batchIds = $migration->batches()
            ->where('resource_type', $resourceType)
            ->where('status', 'completed')
            ->pluck('batch_id')
            ->toArray();

        if (empty($batchIds)) {
            Log::info("No completed batches found for resource type", [
                'migration_id' => $migration->id,
                'resource_type' => $resourceType,
                'verification_session_id' => $this->verificationSession->id
            ]);
            return;
        }

        Log::info("Processing resource type", [
            'migration_id' => $migration->id,
            'verification_session_id' => $this->verificationSession->id,
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
        if ($this->verificationSession->type === 'continue') {
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
                                'migration_id' => $this->verificationSession->migration_id,
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
                            'migration_id' => $this->verificationSession->migration_id,
                            'resource_type' => $resourceType,
                            'record_id' => $record->id,
                            'error' => $e->getMessage()
                        ]);
                        $allErrors[$resourceType][] = "Error verifying record: " . $e->getMessage();
                    }

                    // Update progress every 10 records
                    if ($processedRecords % 10 === 0) {
                        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}... ({$processedRecords}/{$totalRecords})", $resourceProgress);

                        // Check if job should be stopped
                        if ($this->shouldStop()) {
                            Log::info("VerifyMigrationJob stopping due to external request", [
                                'migration_id' => $this->verificationSession->migration_id,
                                'verification_session_id' => $this->verificationSession->id,
                                'processed_so_far' => $processedRecords
                            ]);

                            // Mark session as stopped
                            $this->verificationSession->markCompleted('stopped', 'Verification stopped by user request');
                            return; // Exit the processing
                        }
                    }
                }
            });
    }

    private function updateStatus(int $total, int $processed, int $verified, string $activity, array $resourceProgress): void
    {
        $this->verificationSession->updateProgress($total, $processed, $verified, $processed - $verified, $resourceProgress);
        $this->verificationSession->updateActivity($activity);
    }

    /**
     * Check if job should be stopped based on external signals
     */
    private function shouldStop(): bool
    {
        // Refresh the session to get the latest status
        $this->verificationSession->refresh();
        return $this->verificationSession->status === 'stopping';
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
