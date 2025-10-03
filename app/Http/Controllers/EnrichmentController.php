<?php

namespace App\Http\Controllers;

use App\Services\EnrichmentService;
use App\Jobs\EnrichCasesJob;
use App\Enums\ResourceType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EnrichmentController extends Controller
{
    protected EnrichmentService $enrichmentService;

    public function __construct(EnrichmentService $enrichmentService)
    {
        $this->enrichmentService = $enrichmentService;
    }

    /**
     * Display the enrichment dashboard
     */
    public function index()
    {
        // Check if SHALLOW_CASE migration is completed
        $canEnrich = ResourceType::canEnrichCases();

        // Get current enrichment progress
        $progress = $this->enrichmentService->getEnrichmentProgress();

        return view('enrichment.index', [
            'canEnrich' => $canEnrich,
            'progress' => $progress,
        ]);
    }

    /**
     * Start the enrichment process
     * POST /enrichment/start
     *
     * Always runs in background mode to prevent browser timeout
     */
    public function start(Request $request): JsonResponse
    {
        try {
            // Check if enrichment is allowed
            if (!ResourceType::canEnrichCases()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot start enrichment. Please complete a SHALLOW_CASE migration first.'
                ], 422);
            }

            // Always dispatch to background queue
            Log::info('Dispatching case enrichment to background queue');

            $job = new EnrichCasesJob();
            dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment job dispatched to background queue',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'background' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Enrichment failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Enrichment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enrichment progress
     * GET /enrichment/progress
     */
    public function progress(): JsonResponse
    {
        try {
            $progress = $this->enrichmentService->getEnrichmentProgress();

            return response()->json([
                'success' => true,
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get enrichment progress: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get list of unenriched case IDs
     * GET /enrichment/unenriched
     */
    public function unenriched(): JsonResponse
    {
        try {
            $unenrichedCaseIds = $this->enrichmentService->getUnenrichedCaseIds();

            return response()->json([
                'success' => true,
                'data' => [
                    'unenriched_case_ids' => $unenrichedCaseIds,
                    'count' => $unenrichedCaseIds->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get unenriched cases: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get background job status
     * GET /enrichment/job-status/{jobId}
     */
    public function jobStatus(string $jobId): JsonResponse
    {
        try {
            $status = EnrichCasesJob::getJobStatus($jobId);

            if (!$status) {
                return response()->json([
                    'success' => false,
                    'error' => 'Job not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get job status for {$jobId}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get currently active enrichment job
     * GET /enrichment/active-job
     */
    public function activeJob(): JsonResponse
    {
        try {
            $activeJobId = EnrichCasesJob::getActiveJobId();

            if (!$activeJobId) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ]);
            }

            // Get the job status
            $status = EnrichCasesJob::getJobStatus($activeJobId);

            // If job status exists and is still active (queued or processing)
            if ($status && in_array($status['status'], ['queued', 'processing'])) {
                return response()->json([
                    'success' => true,
                    'data' => $status
                ]);
            }

            // Job completed or failed, clear active marker
            if ($status && in_array($status['status'], ['completed', 'failed'])) {
                // Return the completed/failed job one last time
                return response()->json([
                    'success' => true,
                    'data' => $status
                ]);
            }

            // No active job
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get active job: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
