<?php

namespace App\Jobs;

use App\Services\EnrichmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EnrichSessionsJob implements ShouldQueue
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
            'total_shallow_sessions' => 0,
            'already_enriched' => 0,
            'newly_enriched' => 0,
            'failed' => 0,
            'errors' => []
        ]);

        // Mark this as the active enrichment job
        self::setActiveJobId($this->jobId);
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
            $stats = $enrichmentService->enrichAllSessions();

            // Check if process was paused
            if ($stats['paused'] ?? false) {
                $this->updateJobStatus('paused', $stats);
                // Keep active job marker (job is paused, not completed)
                Log::info("Background enrichment job {$this->jobId} paused: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
            } else {
                // Update final status
                $this->updateJobStatus('completed', $stats);
                // Clear active job marker (job completed successfully)
                self::clearActiveJobId();
                Log::info("Background enrichment job {$this->jobId} completed: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
            }
        } catch (\Exception $e) {
            Log::error("Background enrichment job {$this->jobId} failed: " . $e->getMessage());

            $this->updateJobStatus('failed', [
                'error' => $e->getMessage()
            ]);

            // Clear active job marker (job failed)
            self::clearActiveJobId();

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

        // Clear active job marker
        self::clearActiveJobId();
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

    /**
     * Set the active enrichment job ID
     */
    protected static function setActiveJobId(string $jobId): void
    {
        Cache::put('enrichment:active_job_id', $jobId, 86400); // 24 hours
    }

    /**
     * Get the currently active enrichment job ID
     */
    public static function getActiveJobId(): ?string
    {
        return Cache::get('enrichment:active_job_id');
    }

    /**
     * Clear the active enrichment job ID
     */
    protected static function clearActiveJobId(): void
    {
        Cache::forget('enrichment:active_job_id');
    }
}
