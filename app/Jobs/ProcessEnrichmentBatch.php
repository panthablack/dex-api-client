<?php

namespace App\Jobs;

use App\Models\EnrichmentBatch;
use App\Services\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEnrichmentBatch implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 300; // 5 minutes timeout per batch
    public $tries = 3; // Retry up to 3 times on failure
    public $maxExceptions = 1;

    protected EnrichmentBatch $batch;

    /**
     * Create a new job instance.
     */
    public function __construct(EnrichmentBatch $batch)
    {
        $this->batch = $batch;
        $this->onQueue('data-migration');
    }

    /**
     * Execute the job.
     */
    public function handle(EnrichmentService $enrichmentService): void
    {
        try {
            if (env('DETAILED_LOGGING')) {
                Log::info("Processing enrichment batch {$this->batch->id} for {$this->batch->process->resource_type->value}");
            }

            $enrichmentService->processBatch($this->batch);

            if (env('DETAILED_LOGGING')) {
                Log::info("Enrichment batch {$this->batch->id} completed successfully");
            }
        } catch (\Exception $e) {
            Log::error("Failed to process enrichment batch {$this->batch->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("Enrichment batch job {$this->batch->id} failed permanently: " . $e->getMessage());

        // Ensure batch is marked as failed
        if ($this->batch->status !== 'FAILED') {
            $this->batch->onFail($e);
        }
    }
}
