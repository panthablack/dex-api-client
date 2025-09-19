<?php

namespace App\Jobs;

use App\Models\DataMigration;
use App\Services\DataMigrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InitiateDataMigration implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 600; // 10 minutes timeout for setup
    public $tries = 2;

    protected DataMigration $migration;

    /**
     * Create a new job instance.
     */
    public function __construct(DataMigration $migration)
    {
        $this->migration = $migration;
        $this->onQueue('data-migration');
    }

    /**
     * Execute the job.
     */
    public function handle(DataMigrationService $migrationService): void
    {
        Log::info("Initiating data migration {$this->migration->id}: {$this->migration->name}");

        try {
            $migrationService->startMigration($this->migration);
            Log::info("Successfully initiated data migration {$this->migration->id}");
        } catch (\Exception $e) {
            Log::error("Failed to initiate data migration {$this->migration->id}: " . $e->getMessage());
            $this->migration->failed($e);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("Data migration initiation job failed permanently: " . $e->getMessage());
        $this->migration->onFail($e);
    }
}
