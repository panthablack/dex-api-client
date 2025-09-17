<?php

namespace App\Jobs;

use App\Enums\VerificationStatus;
use App\Models\DataMigration;
use App\Services\VerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SimpleVerifyMigrationJob implements ShouldQueue
{
    use Queueable;

    public DataMigration $migration;
    public string $verificationType; // 'full' or 'continue'

    public function __construct(DataMigration $migration, string $verificationType = 'full')
    {
        $this->migration = $migration;
        $this->verificationType = $verificationType;
        $this->onQueue('data-verification');
    }

    public function handle(VerificationService $verificationService): void
    {
        Log::info("SimpleVerifyMigrationJob started", [
            'migration_id' => $this->migration->id,
            'migration_name' => $this->migration->name,
            'verification_type' => $this->verificationType,
            'resource_types' => $this->migration->resource_types
        ]);

        try {
            // If full verification, reset all statuses first
            if ($this->verificationType === 'full') {
                $this->resetAllVerificationStatuses();
            }

            $totalProcessed = 0;
            $totalVerified = 0;

            // Process each resource type
            foreach ($this->migration->resource_types as $resourceType) {
                $processed = $this->processResourceType($resourceType, $verificationService);
                $totalProcessed += $processed['processed'];
                $totalVerified += $processed['verified'];

                Log::info("Processed resource type", [
                    'migration_id' => $this->migration->id,
                    'resource_type' => $resourceType,
                    'processed' => $processed['processed'],
                    'verified' => $processed['verified'],
                    'failed' => $processed['failed']
                ]);
            }

            Log::info("SimpleVerifyMigrationJob completed", [
                'migration_id' => $this->migration->id,
                'verification_type' => $this->verificationType,
                'total_processed' => $totalProcessed,
                'total_verified' => $totalVerified,
                'total_failed' => $totalProcessed - $totalVerified
            ]);

        } catch (\Exception $e) {
            Log::error("SimpleVerifyMigrationJob failed", [
                'migration_id' => $this->migration->id,
                'verification_type' => $this->verificationType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function resetAllVerificationStatuses(): void
    {
        foreach ($this->migration->resource_types as $resourceType) {
            $relationMethod = $this->getRelationMethod($resourceType);

            if ($relationMethod) {
                $this->migration->$relationMethod()->update([
                    'verification_status' => VerificationStatus::PENDING,
                    'verified_at' => null,
                    'verification_error' => null
                ]);
            }
        }

        Log::info("Reset verification statuses for full verification", [
            'migration_id' => $this->migration->id
        ]);
    }

    private function processResourceType(string $resourceType, VerificationService $verificationService): array
    {
        $relationMethod = $this->getRelationMethod($resourceType);

        if (!$relationMethod) {
            return ['processed' => 0, 'verified' => 0, 'failed' => 0];
        }

        $query = $this->migration->$relationMethod();

        // In continue mode, only process failed and pending records
        if ($this->verificationType === 'continue') {
            $query = $query->whereIn('verification_status', [VerificationStatus::FAILED, VerificationStatus::PENDING]);
        }

        $processed = 0;
        $verified = 0;
        $failed = 0;

        // Process in chunks to avoid memory issues
        $query->chunk(50, function ($records) use ($resourceType, $verificationService, &$processed, &$verified, &$failed) {
            foreach ($records as $record) {
                try {
                    $success = $verificationService->verifyRecord($resourceType, $record);

                    $processed++;

                    if ($success) {
                        $verified++;
                    } else {
                        $failed++;
                    }

                    // Log progress every 10 records
                    if ($processed % 10 === 0) {
                        Log::info("Verification progress", [
                            'migration_id' => $this->migration->id,
                            'resource_type' => $resourceType,
                            'processed' => $processed,
                            'verified' => $verified,
                            'failed' => $failed
                        ]);
                    }

                } catch (\Exception $e) {
                    $processed++;
                    $failed++;

                    Log::warning("Record verification failed", [
                        'migration_id' => $this->migration->id,
                        'resource_type' => $resourceType,
                        'record_id' => $record->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        });

        return ['processed' => $processed, 'verified' => $verified, 'failed' => $failed];
    }

    private function getRelationMethod(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => 'clients',
            'cases' => 'cases',
            'sessions' => 'sessions',
            default => null
        };
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("SimpleVerifyMigrationJob permanently failed", [
            'migration_id' => $this->migration->id,
            'verification_type' => $this->verificationType,
            'exception' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString()
        ]);
    }
}