<?php

namespace App\Http\Controllers;

use App\Services\EnrichmentService;
use App\Services\ExportService;
use App\Services\SessionShallowGenerationService;
use App\Jobs\EnrichSessionsJob;
use App\Enums\ResourceType;
use App\Models\MigratedEnrichedSession;
use App\Models\MigratedCase;
use App\Models\MigratedEnrichedCase;
use App\Models\MigratedShallowSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionEnrichmentController extends Controller
{
    protected EnrichmentService $enrichmentService;
    protected ExportService $exportService;
    protected SessionShallowGenerationService $generationService;

    public function __construct(
        EnrichmentService $enrichmentService,
        ExportService $exportService,
        SessionShallowGenerationService $generationService
    ) {
        $this->enrichmentService = $enrichmentService;
        $this->exportService = $exportService;
        $this->generationService = $generationService;
    }

    /**
     * Display the enrichment dashboard
     */
    public function index()
    {
        // Check if cases are available (prerequisite)
        $hasAvailableCases = MigratedCase::count() > 0 || MigratedEnrichedCase::count() > 0;

        // Check if shallow sessions have been generated
        $hasShallowSessions = MigratedShallowSession::count() > 0;

        // Check if we can enrich sessions
        $canEnrich = ResourceType::canEnrichSessions();

        // Get current enrichment progress
        $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::SESSION);

        // Get generation status
        $availableSource = $this->generationService->getAvailableSource();

        return view('enrichment.sessions.index', [
            'hasAvailableCases' => $hasAvailableCases,
            'hasShallowSessions' => $hasShallowSessions,
            'canEnrich' => $canEnrich,
            'progress' => $progress,
            'availableSource' => $availableSource,
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
            if (!ResourceType::canEnrichSessions()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot start enrichment. Please complete a SHALLOW_SESSION migration first.'
                ], 422);
            }

            // Always dispatch to background queue
            Log::info('Dispatching session enrichment to background queue');

            $job = new EnrichSessionsJob();
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
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::SESSION);

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
     * Get list of unenriched session IDs
     * GET /enrichment/unenriched
     */
    public function unenriched(): JsonResponse
    {
        try {
            $unenrichedSessionIds = $this->enrichmentService->getUnenrichedSessionIds();

            return response()->json([
                'success' => true,
                'data' => [
                    'unenriched_session_ids' => $unenrichedSessionIds,
                    'count' => $unenrichedSessionIds->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get unenriched sessions: ' . $e->getMessage());

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
            $status = EnrichSessionsJob::getJobStatus($jobId);

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
            $activeJobId = EnrichSessionsJob::getActiveJobId();

            if (!$activeJobId) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ]);
            }

            // Get the job status
            $status = EnrichSessionsJob::getJobStatus($activeJobId);

            // If job status exists and is still active (queued, processing, or paused)
            if ($status && in_array($status['status'], ['queued', 'processing', 'paused'])) {
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

    /**
     * Pause the currently running enrichment process
     * POST /enrichment/pause
     */
    public function pause(): JsonResponse
    {
        try {
            // Set pause flag
            $this->enrichmentService->setPaused();

            Log::info('Enrichment pause requested');

            return response()->json([
                'success' => true,
                'message' => 'Enrichment will pause after completing the current session'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to pause enrichment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resume enrichment (clears pause flag and starts new job)
     * POST /enrichment/resume
     */
    public function resume(): JsonResponse
    {
        try {
            // Clear pause flag
            $this->enrichmentService->clearPaused();

            // Start a new enrichment job (will auto-skip already enriched sessions)
            Log::info('Resuming enrichment - dispatching new job');

            $job = new EnrichSessionsJob();
            dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment resumed',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'background' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resume enrichment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if shallow sessions can be generated
     * GET /enrichment/sessions/api/can-generate
     */
    public function canGenerateShallowSessions(): JsonResponse
    {
        try {
            $canGenerate = $this->generationService->canGenerate();
            $availableSource = $this->generationService->getAvailableSource();

            return response()->json([
                'success' => true,
                'data' => [
                    'can_generate' => $canGenerate,
                    'source' => $availableSource,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check generation status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate shallow sessions from case data
     * POST /enrichment/sessions/api/generate
     */
    public function generateShallowSessions(): JsonResponse
    {
        try {
            // Check if generation is possible
            if (!$this->generationService->canGenerate()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot generate shallow sessions. Please complete a Case migration first.'
                ], 422);
            }

            Log::info('Starting shallow session generation');

            // Generate shallow sessions
            $stats = $this->generationService->generateShallowSessions();

            return response()->json([
                'success' => true,
                'message' => "Generated {$stats['newly_created']} new shallow sessions",
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate shallow sessions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart enrichment (truncates all enriched sessions and starts fresh)
     * POST /enrichment/restart
     */
    public function restart(): JsonResponse
    {
        try {
            // Check if enrichment is allowed
            if (!ResourceType::canEnrichSessions()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot restart enrichment. Please complete a SHALLOW_SESSION migration first.'
                ], 422);
            }

            Log::info('Restarting enrichment - truncating all enriched sessions');

            // Truncate the migrated_enriched_sessions table
            DB::table('migrated_enriched_sessions')->truncate();

            // Clear pause flag
            $this->enrichmentService->clearPaused();

            // Start a new enrichment job
            Log::info('Dispatching new enrichment job after restart');

            $job = new EnrichSessionsJob();
            dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment restarted. All previous enriched data has been cleared.',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'background' => true
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restart enrichment: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export enriched session data
     * GET /enrichment/sessions/api/export
     */
    public function export(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'format' => 'required|in:csv,json,xlsx'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $format = $request->format;
            $data = MigratedEnrichedSession::all();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No enriched session data found'
                ], 404);
            }

            $filename = 'enriched_sessions_' . now()->format('Y-m-d_H-i-s');
            $headerMapping = self::getEnrichedSessionHeaderMapping();

            return $this->exportService->export($data, $headerMapping, $format, $filename);
        } catch (\Exception $e) {
            Log::error('Failed to export enriched sessions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get header mapping for enriched sessions
     * Static method to share with other controllers/services
     */
    public static function getEnrichedSessionHeaderMapping(): array
    {
        return [
            'session_id' => 'Session ID',
            'case_id' => 'Case ID',
            'session_date' => 'Session Date',
            'service_type_id' => 'Service Type ID',
            'total_number_of_unidentified_clients' => 'Total Unidentified Clients',
            'fees_charged' => 'Fees Charged',
            'money_business_community_education_workshop_code' => 'Money/Business/Community Education Workshop Code',
            'interpreter_present' => 'Interpreter Present',
            'service_setting_code' => 'Service Setting Code',
            'enriched_at' => 'Enriched At',
            'verification_status' => 'Verification Status',
            'verified_at' => 'Verified Date',
            'verification_error' => 'Verification Error',
            'data_migration_batch_id' => 'Data Migration Batch ID',
            'api_response' => 'Raw API Data (JSON)',
        ];
    }
}
