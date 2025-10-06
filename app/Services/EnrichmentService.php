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
                'errors' => [],
                'paused' => false
            ];

            // Clear pause flag at start
            $this->clearPaused();

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
                // Check for pause request before processing each case
                if ($this->isPaused()) {
                    Log::info("Enrichment paused by user request. Progress: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
                    $stats['paused'] = true;
                    return $stats;
                }

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

        // Convert stdClass to array (SOAP responses return objects)
        $caseDataArray = json_decode(json_encode($caseData), true);

        // Store the enriched case
        return $this->storeEnrichedCase($shallowCase, $caseDataArray);
    }

    /**
     * Store enriched case data in the database
     * Uses same extraction logic as DataMigrationService for consistency
     *
     * @param MigratedShallowCase $shallowCase
     * @param array $caseData Full case data from GetCase API
     * @return MigratedEnrichedCase
     */
    protected function storeEnrichedCase(MigratedShallowCase $shallowCase, array $caseData): MigratedEnrichedCase
    {
        // Extract client IDs using robust nested extraction
        $clientIds = $this->extractClientIds($caseData);

        // Extract fields using data_get() for nested structures (same as DataMigrationService)
        // API response structure: Case.CaseDetail.FieldName or Case.FieldName
        $outletName = $caseData['outlet_name']
            ?? $caseData['OutletName']
            ?? data_get($caseData, 'Case.OutletName')
            ?? data_get($caseData, 'CaseDetail.OutletName')
            ?? null;

        $outletActivityId = $caseData['outlet_activity_id']
            ?? $caseData['OutletActivityId']
            ?? data_get($caseData, 'Case.CaseDetail.OutletActivityId')
            ?? data_get($caseData, 'CaseDetail.OutletActivityId')
            ?? 0;

        $clientAttendanceProfileCode = $caseData['client_attendance_profile_code']
            ?? $caseData['ClientAttendanceProfileCode']
            ?? data_get($caseData, 'Case.CaseDetail.ClientAttendanceProfileCode')
            ?? data_get($caseData, 'CaseDetail.ClientAttendanceProfileCode')
            ?? null;

        $createdDateTime = $caseData['created_date_time']
            ?? $caseData['CreatedDateTime']
            ?? data_get($caseData, 'Case.CreatedDateTime')
            ?? data_get($caseData, 'CaseDetail.CreatedDateTime')
            ?? null;

        $endDate = $caseData['end_date']
            ?? $caseData['EndDate']
            ?? data_get($caseData, 'Case.CaseDetail.EndDate')
            ?? data_get($caseData, 'CaseDetail.EndDate')
            ?? null;

        $totalNumberOfUnidentifiedClients = $caseData['total_number_of_unidentified_clients']
            ?? $caseData['TotalNumberOfUnidentifiedClients']
            ?? data_get($caseData, 'Case.CaseDetail.TotalNumberOfUnidentifiedClients')
            ?? data_get($caseData, 'CaseDetail.TotalNumberOfUnidentifiedClients')
            ?? null;

        return MigratedEnrichedCase::updateOrCreate(
            ['case_id' => $shallowCase->case_id],
            [
                'shallow_case_id' => $shallowCase->id,
                'outlet_name' => $outletName,
                'client_ids' => $clientIds,
                'outlet_activity_id' => $outletActivityId,
                'created_date_time' => $createdDateTime,
                'end_date' => $endDate,
                'client_attendance_profile_code' => $clientAttendanceProfileCode,
                'client_count' => $totalNumberOfUnidentifiedClients ?? count($clientIds ?? []),
                'api_response' => $caseData,
                'enriched_at' => now(),
                'verification_status' => VerificationStatus::PENDING,
            ]
        );
    }

    /**
     * Extract client IDs from case data
     * Handles various possible formats from the API (same logic as DataMigrationService)
     *
     * @param array $caseData
     * @return array
     */
    protected function extractClientIds(array $caseData): array
    {
        // Try to extract as array first
        if (isset($caseData['client_ids']) && is_array($caseData['client_ids'])) {
            // Filter out empty values and re-index
            return array_values(array_filter($caseData['client_ids']));
        }

        if (isset($caseData['ClientIds']) && is_array($caseData['ClientIds'])) {
            // Filter out empty values and re-index
            return array_values(array_filter($caseData['ClientIds']));
        }

        // Check for nested client data structures (Case.Clients or just Clients)
        $clientsData = data_get($caseData, 'Case.Clients') ?? data_get($caseData, 'Clients');

        if ($clientsData && is_array($clientsData)) {
            $clientIds = [];

            // Handle various nested structures
            if (isset($clientsData['CaseClient'])) {
                if (is_array($clientsData['CaseClient']) && isset($clientsData['CaseClient'][0])) {
                    // Multiple clients (array of client objects)
                    foreach ($clientsData['CaseClient'] as $client) {
                        if ($clientId = $client['ClientId'] ?? null) {
                            $clientIds[] = $clientId;
                        }
                    }
                } elseif (isset($clientsData['CaseClient']['ClientId'])) {
                    // Single client (single client object)
                    $clientIds[] = $clientsData['CaseClient']['ClientId'];
                }
            }

            if (!empty($clientIds)) {
                // Filter out empty values and re-index
                return array_values(array_filter($clientIds));
            }
        }

        // If we got a string, try to parse it
        $clientIdsString = $caseData['client_ids'] ?? $caseData['ClientIds'] ?? null;
        if (is_string($clientIdsString)) {
            $clientIds = array_map('trim', explode(',', $clientIdsString));
            return array_values(array_filter($clientIds));
        }

        // Return empty array if nothing found
        return [];
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

    /**
     * Check if the enrichment process is paused
     *
     * @return bool
     */
    public function isPaused(): bool
    {
        return Cache::get('enrichment:paused', false);
    }

    /**
     * Set the pause flag to pause enrichment
     *
     * @return void
     */
    public function setPaused(): void
    {
        Cache::put('enrichment:paused', true, 86400); // 24 hours
        Log::info('Enrichment pause flag set');
    }

    /**
     * Clear the pause flag to resume enrichment
     *
     * @return void
     */
    public function clearPaused(): void
    {
        Cache::forget('enrichment:paused');
        Log::info('Enrichment pause flag cleared');
    }
}
