<?php

namespace App\Jobs;

use App\Services\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnrichCasesJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 3600; // 1 hour timeout (for large datasets)
    public $tries = 1; // No automatic retries (user can manually re-run)
    public $maxExceptions = 1;

    protected string $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $jobId = null)
    {
        $this->jobId = $jobId ?? uniqid('enrich_', true);
        $this->onQueue('data-migration');

        // Initialize job status in cache
        $this->updateJobStatus('queued', [
            'total_shallow_cases' => 0,
            'already_enriched' => 0,
            'newly_enriched' => 0,
            'failed' => 0,
            'errors' => []
        ]);
    }

    /**
     * Get the job ID for status tracking
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Execute the job.
     */
    public function handle(EnrichmentService $enrichmentService): void
    {
        try {
            Log::info("Starting background enrichment job {$this->jobId}");

            $this->updateJobStatus('processing');

            // Run the enrichment process
            $stats = $enrichmentService->enrichAllCases();

            // Update final status
            $this->updateJobStatus('completed', $stats);

            Log::info("Background enrichment job {$this->jobId} completed: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
        } catch (\Exception $e) {
            Log::error("Background enrichment job {$this->jobId} failed: " . $e->getMessage());

            $this->updateJobStatus('failed', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("Enrichment job {$this->jobId} failed: " . $e->getMessage());

        $this->updateJobStatus('failed', [
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Update job status in cache for UI polling
     */
    protected function updateJobStatus(string $status, array $data = []): void
    {
        $jobStatus = [
            'job_id' => $this->jobId,
            'status' => $status,
            'updated_at' => now()->toIso8601String(),
            'data' => $data
        ];

        // Store in cache for 24 hours
        Cache::put("enrichment:job:{$this->jobId}", $jobStatus, 86400);
    }

    /**
     * Get job status from cache (static helper)
     */
    public static function getJobStatus(string $jobId): ?array
    {
        return Cache::get("enrichment:job:{$jobId}");
    }
}
