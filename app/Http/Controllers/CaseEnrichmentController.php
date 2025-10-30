<?php

namespace App\Http\Controllers;

use App\Services\EnrichmentService;
use App\Services\ExportService;
use App\Models\EnrichmentProcess;
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
     * Creates batches and dispatches initial batch jobs (asynchronously)
     * Returns immediately with initial setup stats
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

            Log::info('Initializing case enrichment process');

            // Initialize enrichment (creates process, batches, and dispatches initial batch jobs)
            $process = $this->enrichmentService->initializeEnrichment(ResourceType::CASE);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment process started. Processing in background...',
                'data' => [
                    'process_id' => $process->id,
                    'total_items' => $process->total_items,
                    'batches_created' => $process->batches()->count(),
                    'batches_dispatched' => $process->batches()->where('status', '!=', 'PENDING')->count(),
                    'status' => $process->status,
                    'progress' => $progress
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
     *
     * Returns progress stats in format expected by the frontend
     */
    public function progress(): JsonResponse
    {
        try {
            // Return the same format as the index page
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
     * Pause the currently running enrichment process
     * POST /enrichment/pause
     *
     * Sets paused_at timestamp on the active process
     */
    public function pause(): JsonResponse
    {
        try {
            // Find the active case enrichment process
            $process = EnrichmentProcess::where('resource_type', ResourceType::CASE)
                ->where('status', 'IN_PROGRESS')
                ->latest()
                ->first();

            if (!$process) {
                return response()->json([
                    'success' => false,
                    'error' => 'No active enrichment process found'
                ], 404);
            }

            // Set pause timestamp
            $process->update(['paused_at' => now()]);

            Log::info("Enrichment process {$process->id} paused");

            return response()->json([
                'success' => true,
                'message' => 'Enrichment paused. Current batch will complete before stopping.',
                'data' => [
                    'process_id' => $process->id,
                    'paused_at' => $process->paused_at
                ]
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
     * Resume enrichment (clears pause flag and redispatches pending batches)
     * POST /enrichment/resume
     */
    public function resume(): JsonResponse
    {
        try {
            // Find the paused case enrichment process
            $process = EnrichmentProcess::where('resource_type', ResourceType::CASE)
                ->where('status', 'IN_PROGRESS')
                ->whereNotNull('paused_at')
                ->latest()
                ->first();

            if (!$process) {
                return response()->json([
                    'success' => false,
                    'error' => 'No paused enrichment process found'
                ], 404);
            }

            // Clear pause timestamp
            $process->update(['paused_at' => null]);

            Log::info("Resuming enrichment process {$process->id}");

            // Redispatch pending batches
            $this->enrichmentService->dispatchBatches($process, 3);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment resumed. Processing in background...',
                'data' => [
                    'process_id' => $process->id,
                    'status' => $process->status,
                    'pending_batches' => $this->enrichmentService->getPendingBatches($process)->count(),
                    'progress' => $progress
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

            Log::info('Restarting enrichment - clearing all enriched cases');

            // Truncate the migrated_enriched_cases table
            DB::table('migrated_enriched_cases')->truncate();

            // Mark old process as failed (if exists)
            $oldProcess = EnrichmentProcess::where('resource_type', ResourceType::CASE)
                ->where('status', '!=', 'COMPLETED')
                ->update(['status' => 'FAILED', 'completed_at' => now()]);

            Log::info('Creating new enrichment process');

            // Create new enrichment process with batches and dispatch initial jobs
            $process = $this->enrichmentService->initializeEnrichment(ResourceType::CASE);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

            return response()->json([
                'success' => true,
                'message' => 'Enrichment restarted. All previous enriched data has been cleared. Processing in background...',
                'data' => [
                    'process_id' => $process->id,
                    'total_items' => $process->total_items,
                    'batches_created' => $process->batches()->count(),
                    'batches_dispatched' => $process->batches()->where('status', '!=', 'PENDING')->count(),
                    'status' => $process->status,
                    'progress' => $progress
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
     * Retry enrichment of failed items
     * POST /enrichment/cases/api/retry
     *
     * Retries enrichment of items that were not successfully enriched in previous attempts
     * Keeps successfully enriched items and creates a new process for remaining items
     */
    public function retry(): JsonResponse
    {
        try {
            // Check if enrichment is allowed
            if (!ResourceType::canEnrichCases()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cannot retry enrichment. Please complete a SHALLOW_CASE migration first.'
                ], 422);
            }

            Log::info('Retrying enrichment of failed cases');

            // Mark old process as completed if not already
            EnrichmentProcess::where('resource_type', ResourceType::CASE)
                ->where('status', '!=', 'COMPLETED')
                ->update(['status' => 'COMPLETED', 'completed_at' => now()]);

            Log::info('Creating new enrichment process for unenriched items');

            // Create new enrichment process with batches and dispatch initial jobs
            // initializeEnrichment will only include unenriched items
            $process = $this->enrichmentService->initializeEnrichment(ResourceType::CASE);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::CASE);

            return response()->json([
                'success' => true,
                'message' => 'Retrying enrichment for remaining items. Processing in background...',
                'data' => [
                    'process_id' => $process->id,
                    'total_items' => $process->total_items,
                    'batches_created' => $process->batches()->count(),
                    'batches_dispatched' => $process->batches()->where('status', '!=', 'PENDING')->count(),
                    'status' => $process->status,
                    'progress' => $progress
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retry enrichment: ' . $e->getMessage());

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
