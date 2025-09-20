<?php

namespace App\Jobs;

use App\Models\DataMigrationBatch;
use App\Services\DataMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDataMigrationBatch implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Retry up to 3 times
    public $maxExceptions = 1;

    protected DataMigrationBatch $batch;

    /**
     * Create a new job instance.
     */
    public function __construct(DataMigrationBatch $batch)
    {
        $this->batch = $batch;
        $this->onQueue('data-migration');
    }

    /**
     * Execute the job.
     */
    public function handle(DataMigrationService $migrationService): void
    {
        Log::info("Processing data migration batch {$this->batch->id} for {$this->batch->resource_type}");

        try {
            $migrationService->processBatch($this->batch);
            Log::info("Successfully processed data migration batch {$this->batch->id}");
        } catch (\Exception $e) {
            Log::error("Failed to process data migration batch {$this->batch->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("Data migration batch job failed: " . $e->getMessage());
        $this->batch->onFail($e);
    }
}
