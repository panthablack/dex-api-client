<?php

namespace App\Http\Controllers;

use App\Services\EnrichmentService;
use App\Services\ExportService;
use App\Jobs\EnrichCasesJob;
use App\Enums\ResourceType;
use App\Models\MigratedEnrichedCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CaseEnrichmentController extends Controller
{
    protected EnrichmentService $enrichmentService;
    protected ExportService $exportService;

    public function __construct(EnrichmentService $enrichmentService, ExportService $exportService)
    {
        $this->enrichmentService = $enrichmentService;
        $this->exportService = $exportService;
    }

    /**
     * Display the enrichment dashboard
     */
    public function index()
    {
        // Check if SHALLOW_CASE migration is completed
        $canEnrich = ResourceType::canEnrichCases();

        // Get current enrichment progress
        $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

        return view('enrichment.cases.index', [
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
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

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
                'message' => 'Enrichment will pause after completing the current case'
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

            // Start a new enrichment job (will auto-skip already enriched cases)
            Log::info('Resuming enrichment - dispatching new job');

            $job = new EnrichCasesJob();
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
     * Generates shallow sessions from enriched cases.
     * POST /enrichment/sessions/generate
     */
    public function generateShallowSessions()
    {
        $cases = MigratedEnrichedCase::all();
        $caseIds = $cases->pluck('case_id');
        $sessions = $cases->pluck('sessions');
        $sessionIds = $cases->pluck('case_id');
    }

    /**
     * Restart enrichment (truncates all enriched cases and starts fresh)
     * POST /enrichment/restart
     */
    public function restart(): JsonResponse
    {
        try {
            // Check if enrichment is allowed
            if (!ResourceType::canEnrichCases()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot restart enrichment. Please complete a SHALLOW_CASE migration first.'
                ], 422);
            }

            Log::info('Restarting enrichment - truncating all enriched cases');

            // Truncate the migrated_enriched_cases table
            DB::table('migrated_enriched_cases')->truncate();

            // Clear pause flag
            $this->enrichmentService->clearPaused();

            // Start a new enrichment job
            Log::info('Dispatching new enrichment job after restart');

            $job = new EnrichCasesJob();
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
     * Export enriched case data
     * GET /enrichment/cases/api/export
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
            $data = MigratedEnrichedCase::all();

            if ($data->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No enriched case data found'
                ], 404);
            }

            $filename = 'enriched_cases_' . now()->format('Y-m-d_H-i-s');
            $headerMapping = self::getEnrichedCaseHeaderMapping();

            return $this->exportService->export($data, $headerMapping, $format, $filename);
        } catch (\Exception $e) {
            Log::error('Failed to export enriched cases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get header mapping for enriched cases
     * Static method to share with other controllers/services
     */
    public static function getEnrichedCaseHeaderMapping(): array
    {
        return [
            'case_id' => 'Case ID',
            'shallow_case_id' => 'Shallow Case ID',
            'outlet_name' => 'Outlet Name',
            'client_ids' => 'Client IDs (JSON)',
            'outlet_activity_id' => 'Outlet Activity ID',
            'total_number_of_unidentified_clients' => 'Total Unidentified Clients',
            'client_attendance_profile_code' => 'Client Attendance Profile Code',
            'created_date_time' => 'Created Date',
            'end_date' => 'End Date',
            'exit_reason_code' => 'Exit Reason Code',
            'ag_business_type_code' => 'AG Business Type Code',
            'program_activity_name' => 'Program Activity Name',
            'sessions' => 'Sessions (JSON)',
            'enriched_at' => 'Enriched At',
            'verification_status' => 'Verification Status',
            'verified_at' => 'Verified Date',
            'verification_error' => 'Verification Error',
            'api_response' => 'Raw API Data (JSON)',
        ];
    }
}
