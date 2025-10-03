<?php

namespace App\Services;

use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Enums\VerificationStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class EnrichmentService
{
    protected $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        $this->dataExchangeService = $dataExchangeService;
    }

    /**
     * Enrich all shallow cases that haven't been enriched yet
     * One-at-a-time processing for maximum fault tolerance
     * Uses process locking to prevent concurrent enrichment
     *
     * @return array Statistics about the enrichment process
     * @throws \Exception If unable to acquire lock (another process is running)
     */
    public function enrichAllCases(): array
    {
        // Acquire lock to prevent concurrent enrichment processes
        $lock = Cache::lock('enrichment:process', 3600); // 1 hour timeout

        if (!$lock->get()) {
            throw new \Exception('Another enrichment process is already running. Please wait for it to complete.');
        }

        try {
            $stats = [
                'total_shallow_cases' => 0,
                'already_enriched' => 0,
                'newly_enriched' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // Get all shallow cases that need enrichment
            $shallowCases = MigratedShallowCase::all();
            $stats['total_shallow_cases'] = $shallowCases->count();

            if ($stats['total_shallow_cases'] === 0) {
                Log::info("No shallow cases found to enrich");
                return $stats;
            }

            Log::info("Starting enrichment for {$stats['total_shallow_cases']} shallow cases");

            // Process each case one at a time
            foreach ($shallowCases as $shallowCase) {
                try {
                    // Skip if already enriched
                    if ($this->isAlreadyEnriched($shallowCase->case_id)) {
                        $stats['already_enriched']++;
                        continue;
                    }

                    // Enrich this case
                    $this->enrichCase($shallowCase);
                    $stats['newly_enriched']++;

                    if (env('DETAILED_LOGGING')) {
                        Log::info("Enriched case {$shallowCase->case_id} ({$stats['newly_enriched']}/{$stats['total_shallow_cases']})");
                    }
                } catch (\Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'case_id' => $shallowCase->case_id,
                        'error' => $e->getMessage()
                    ];

                    Log::error("Failed to enrich case {$shallowCase->case_id}: {$e->getMessage()}");
                    // Continue to next case - don't let one failure stop the whole process
                }
            }

            Log::info("Enrichment complete: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");

            return $stats;
        } finally {
            // Always release the lock when done
            $lock->release();
        }
    }

    /**
     * Enrich a single shallow case by fetching full case data from GetCase API
     *
     * @param MigratedShallowCase $shallowCase
     * @return MigratedEnrichedCase
     */
    public function enrichCase(MigratedShallowCase $shallowCase): MigratedEnrichedCase
    {
        // Fetch full case data using GetCase API (one-at-a-time)
        $caseData = $this->dataExchangeService->getCaseById($shallowCase->case_id);

        if (!$caseData) {
            throw new \Exception("Failed to fetch case data for {$shallowCase->case_id}");
        }

        // Store the enriched case
        return $this->storeEnrichedCase($shallowCase, $caseData);
    }

    /**
     * Store enriched case data in the database
     *
     * @param MigratedShallowCase $shallowCase
     * @param array $caseData Full case data from GetCase API
     * @return MigratedEnrichedCase
     */
    protected function storeEnrichedCase(MigratedShallowCase $shallowCase, array $caseData): MigratedEnrichedCase
    {
        $clientIds = $this->extractClientIds($caseData);

        return MigratedEnrichedCase::updateOrCreate(
            ['case_id' => $shallowCase->case_id],
            [
                'shallow_case_id' => $shallowCase->id,
                'outlet_name' => $caseData['outlet_name'] ?? $caseData['OutletName'] ?? null,
                'client_ids' => $clientIds,
                'outlet_activity_id' => $caseData['outlet_activity_id'] ?? $caseData['OutletActivityId'] ?? 0,
                'created_date_time' => $caseData['created_date_time'] ?? $caseData['CreatedDateTime'] ?? null,
                'end_date' => $caseData['end_date'] ?? $caseData['EndDate'] ?? null,
                'client_attendance_profile_code' => $caseData['client_attendance_profile_code'] ?? $caseData['ClientAttendanceProfileCode'] ?? null,
                'client_count' => $caseData['client_count'] ?? $caseData['ClientCount'] ?? count($clientIds),
                'api_response' => $caseData,
                'enriched_at' => now(),
                'verification_status' => VerificationStatus::PENDING,
            ]
        );
    }

    /**
     * Extract client IDs from case data
     * Handles various possible formats from the API
     *
     * @param array $caseData
     * @return array
     */
    protected function extractClientIds(array $caseData): array
    {
        // Try different possible field names
        $clientIds = $caseData['client_ids'] ??
                    $caseData['ClientIds'] ??
                    $caseData['client_id_list'] ??
                    $caseData['ClientIdList'] ??
                    [];

        // If we got a string, try to parse it
        if (is_string($clientIds)) {
            $clientIds = array_map('trim', explode(',', $clientIds));
        }

        // Ensure it's an array
        if (!is_array($clientIds)) {
            $clientIds = [];
        }

        // Filter out empty values
        return array_values(array_filter($clientIds));
    }

    /**
     * Check if a case has already been enriched
     *
     * @param string $caseId
     * @return bool
     */
    protected function isAlreadyEnriched(string $caseId): bool
    {
        return MigratedEnrichedCase::where('case_id', $caseId)->exists();
    }

    /**
     * Get enrichment progress statistics
     * Used for UI progress display
     *
     * @return array
     */
    public function getEnrichmentProgress(): array
    {
        $totalShallowCases = MigratedShallowCase::count();
        $enrichedCases = MigratedEnrichedCase::count();
        $unenrichedCount = $totalShallowCases - $enrichedCases;

        return [
            'total_shallow_cases' => $totalShallowCases,
            'enriched_cases' => $enrichedCases,
            'unenriched_cases' => $unenrichedCount,
            'progress_percentage' => $totalShallowCases > 0
                ? round(($enrichedCases / $totalShallowCases) * 100, 2)
                : 0,
        ];
    }

    /**
     * Get case IDs that haven't been enriched yet
     * Helper method for testing and batch processing
     *
     * @return Collection
     */
    public function getUnenrichedCaseIds(): Collection
    {
        $enrichedCaseIds = MigratedEnrichedCase::pluck('case_id');

        return MigratedShallowCase::whereNotIn('case_id', $enrichedCaseIds)
            ->pluck('case_id');
    }
}
