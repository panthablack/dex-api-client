<?php

namespace App\Http\Controllers;

use App\Services\EnrichmentService;
use App\Services\ExportService;
use App\Services\SessionShallowGenerationService;
use App\Enums\ResourceType;
use App\Models\EnrichmentProcess;
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
     * Creates batches and dispatches initial batch jobs
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

            Log::info('Initializing session enrichment process');

            // Initialize enrichment (creates process, batches, and dispatches initial batch jobs)
            $process = $this->enrichmentService->initializeEnrichment(ResourceType::SESSION);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::SESSION);

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
     * Returns progress from the current/latest enrichment process
     */
    public function progress(): JsonResponse
    {
        try {
            // Return the same format as the index page
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
     * Pause the currently running enrichment process
     * POST /enrichment/pause
     *
     * Sets paused_at timestamp on the active process
     */
    public function pause(): JsonResponse
    {
        try {
            // Find the active session enrichment process
            $process = EnrichmentProcess::where('resource_type', ResourceType::SESSION)
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
            // Find the paused session enrichment process
            $process = EnrichmentProcess::where('resource_type', ResourceType::SESSION)
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
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::SESSION);

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

            Log::info('Restarting enrichment - clearing all enriched sessions');

            // Truncate the migrated_enriched_sessions table
            DB::table('migrated_enriched_sessions')->truncate();

            // Mark old process as failed (if exists)
            EnrichmentProcess::where('resource_type', ResourceType::SESSION)
                ->where('status', '!=', 'COMPLETED')
                ->update(['status' => 'FAILED', 'completed_at' => now()]);

            Log::info('Creating new enrichment process');

            // Create new enrichment process with batches and dispatch initial jobs
            $process = $this->enrichmentService->initializeEnrichment(ResourceType::SESSION);

            // Get current progress
            $progress = $this->enrichmentService->getEnrichmentProgress(ResourceType::SESSION);

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
