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
    }

    /**
     * Execute the job.
     */
    public function handle(VerificationService $verificationService): void
    {
        Log::info("Starting full verification job for migration {$this->migration->id}");

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

            // Count total records to process
            foreach ($this->migration->resource_types as $resourceType) {
                $modelClass = $this->getModelClass($resourceType);
                if ($modelClass) {
                    $count = $modelClass::where('migration_batch_id', $this->migration->id)->count();
                    $totalRecords += $count;
                }
            }

            // Update status with total count
            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'in_progress',
                'total' => $totalRecords,
                'processed' => 0,
                'verified' => 0,
                'current_activity' => 'Processing records...'
            ], 3600);

            // Process each resource type
            foreach ($this->migration->resource_types as $resourceType) {
                $this->processResourceType($resourceType, $verificationService, $totalRecords, $processedRecords, $verifiedRecords);
            }

            // Mark as completed
            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'completed',
                'total' => $totalRecords,
                'processed' => $processedRecords,
                'verified' => $verifiedRecords,
                'current_activity' => 'Verification completed',
                'results' => $verificationService->getMigrationVerificationStats($this->migration)
            ], 3600);

            Log::info("Verification job completed for migration {$this->migration->id}");
        } catch (\Exception $e) {
            Log::error("Verification job failed for migration {$this->migration->id}: " . $e->getMessage());

            Cache::put("verification_status_{$this->verificationId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'current_activity' => 'Verification failed'
            ], 3600);
        }
    }

    private function processResourceType(string $resourceType, VerificationService $verificationService, int $totalRecords, int &$processedRecords, int &$verifiedRecords): void
    {
        $modelClass = $this->getModelClass($resourceType);
        if (!$modelClass) return;

        // Update activity
        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}...");

        // Process in chunks to avoid memory issues
        $modelClass::where('migration_batch_id', $this->migration->id)
            ->chunk(50, function ($records) use ($resourceType, $verificationService, $totalRecords, &$processedRecords, &$verifiedRecords) {
                foreach ($records as $record) {
                    // Skip already verified records
                    if ($record->verified) {
                        $verifiedRecords++;
                        $processedRecords++;
                        continue;
                    }

                    // Verify the record
                    $success = match ($resourceType) {
                        'clients' => $verificationService->verifyClient($record),
                        'cases' => $verificationService->verifyCase($record),
                        'sessions' => $verificationService->verifySession($record),
                        default => false
                    };

                    if ($success) {
                        $verifiedRecords++;
                    }
                    $processedRecords++;

                    // Update progress every 10 records
                    if ($processedRecords % 10 === 0) {
                        $this->updateStatus($totalRecords, $processedRecords, $verifiedRecords, "Processing {$resourceType}... ({$processedRecords}/{$totalRecords})");
                    }
                }
            });
    }

    private function updateStatus(int $total, int $processed, int $verified, string $activity): void
    {
        Cache::put("verification_status_{$this->verificationId}", [
            'status' => 'in_progress',
            'total' => $total,
            'processed' => $processed,
            'verified' => $verified,
            'current_activity' => $activity
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
