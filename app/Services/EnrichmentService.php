<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Enums\VerificationStatus;
use App\Models\MigratedEnrichedSession;
use App\Models\MigratedShallowSession;
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

            if ($stats['total_shallow_cases'] > 0) {
                Log::info("Starting enrichment for {$stats['total_shallow_cases']} shallow cases");

                // Process each case one at a time
                foreach ($shallowCases as $shallowCase) {
                    // Check for pause request before processing each case
                    if ($this->isPaused()) {
                        Log::info("Enrichment paused by user request. Progress: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
                        $stats['paused'] = true;
                        break;
                    }

                    try {
                        // Skip if already enriched
                        if ($this->isAlreadyEnriched(ResourceType::CASE, $shallowCase->case_id)) {
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
            } else {
                Log::info("No shallow cases found to enrich");
            }

            Log::info("Enrichment complete: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");

            return $stats;
        } catch (\Exception $e) {
            throw $e;
        } finally {
            // Always release the lock when done
            $lock->release();
        }
    }

    /**
     * Enrich all shallow sessions that haven't been enriched yet
     * One-at-a-time processing for maximum fault tolerance
     * Uses process locking to prevent concurrent enrichment
     *
     * @return array Statistics about the enrichment process
     * @throws \Exception If unable to acquire lock (another process is running)
     */
    public function enrichAllSessions(): array
    {
        // Acquire lock to prevent concurrent enrichment processes
        $lock = Cache::lock('enrichment:process', 3600); // 1 hour timeout

        if (!$lock->get()) {
            throw new \Exception('Another enrichment process is already running. Please wait for it to complete.');
        }

        try {
            $stats = [
                'total_shallow_sessions' => 0,
                'already_enriched' => 0,
                'newly_enriched' => 0,
                'failed' => 0,
                'errors' => [],
                'paused' => false
            ];

            // Clear pause flag at start
            $this->clearPaused();

            // Get all shallow sessions that need enrichment
            $shallowSessions = MigratedShallowSession::all();
            $stats['total_shallow_sessions'] = $shallowSessions->count();

            if ($stats['total_shallow_sessions'] > 0) {
                Log::info("Starting enrichment for {$stats['total_shallow_sessions']} shallow sessions");

                // Process each session one at a time
                foreach ($shallowSessions as $shallowSession) {
                    // Check for pause request before processing each session
                    if ($this->isPaused()) {
                        Log::info("Enrichment paused by user request. Progress: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");
                        $stats['paused'] = true;
                        break;
                    }

                    try {
                        // Skip if already enriched
                        if ($this->isAlreadyEnriched(ResourceType::SESSION, $shallowSession->session_id)) {
                            $stats['already_enriched']++;
                            continue;
                        }

                        // Enrich this session
                        $this->enrichSession($shallowSession);
                        $stats['newly_enriched']++;

                        if (env('DETAILED_LOGGING')) {
                            Log::info("Enriched session {$shallowSession->session_id} ({$stats['newly_enriched']}/{$stats['total_shallow_sessions']})");
                        }
                    } catch (\Exception $e) {
                        $stats['failed']++;
                        $stats['errors'][] = [
                            'session_id' => $shallowSession->session_id,
                            'error' => $e->getMessage()
                        ];

                        Log::error("Failed to enrich session {$shallowSession->session_id}: {$e->getMessage()}");
                        // Continue to next session - don't let one failure stop the whole process
                    }
                }
            } else {
                Log::info("No shallow sessions found to enrich");
            }

            Log::info("Enrichment complete: {$stats['newly_enriched']} newly enriched, {$stats['already_enriched']} already enriched, {$stats['failed']} failed");

            return $stats;
        } catch (\Exception $e) {
            throw $e;
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
     * Enrich a single shallow session by fetching full session data from GetSession API
     *
     * @param MigratedShallowSession $shallowSession
     * @return MigratedEnrichedSession
     */
    public function enrichSession(MigratedShallowSession $shallowSession): MigratedEnrichedSession
    {
        // Fetch full session data using GetSession API (one-at-a-time)
        $sessionData = $this->dataExchangeService->getSessionById($shallowSession->session_id);

        if (!$sessionData) {
            throw new \Exception("Failed to fetch session data for {$shallowSession->session_id}");
        }

        // Convert stdClass to array (SOAP responses return objects)
        $sessionDataArray = json_decode(json_encode($sessionData), true);

        // Store the enriched session
        return $this->storeEnrichedSession($shallowSession, $sessionDataArray);
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

        // Extract session IDs using robust nested extraction
        $sessions = $this->extractSessions($caseData);

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
                'sessions' => $sessions,
                'api_response' => $caseData,
                'enriched_at' => now(),
                'verification_status' => VerificationStatus::PENDING,
            ]
        );
    }

    /**
     * Store enriched session data in the database
     * Uses fallback patterns to handle API response variations
     *
     * @param MigratedShallowSession $shallowSession
     * @param array $sessionData Full session data from GetSession API
     * @return MigratedEnrichedSession
     */
    protected function storeEnrichedSession(MigratedShallowSession $shallowSession, array $sessionData): MigratedEnrichedSession
    {
        // Extract session-specific fields with fallback patterns
        // API response structure varies: Session.FieldName or SessionDetail.FieldName or direct key
        $caseId = $shallowSession->case_id;

        $sessionDate = $sessionData['session_date']
            ?? $sessionData['SessionDate']
            ?? data_get($sessionData, 'Session.SessionDate')
            ?? data_get($sessionData, 'SessionDetail.SessionDate')
            ?? null;

        $serviceTypeId = $sessionData['service_type_id']
            ?? $sessionData['ServiceTypeId']
            ?? data_get($sessionData, 'Session.ServiceTypeId')
            ?? data_get($sessionData, 'SessionDetail.ServiceTypeId')
            ?? 0;

        $totalNumberOfUnidentifiedClients = $sessionData['total_number_of_unidentified_clients']
            ?? $sessionData['TotalNumberOfUnidentifiedClients']
            ?? data_get($sessionData, 'Session.TotalNumberOfUnidentifiedClients')
            ?? data_get($sessionData, 'SessionDetail.TotalNumberOfUnidentifiedClients')
            ?? 0;

        $feesCharged = $sessionData['fees_charged']
            ?? $sessionData['FeesCharged']
            ?? data_get($sessionData, 'Session.FeesCharged')
            ?? data_get($sessionData, 'SessionDetail.FeesCharged')
            ?? null;

        $moneyBusinessCommunityEducationWorkshopCode = $sessionData['money_business_community_education_workshop_code']
            ?? $sessionData['MoneyBusinessCommunityEducationWorkshopCode']
            ?? data_get($sessionData, 'Session.MoneyBusinessCommunityEducationWorkshopCode')
            ?? data_get($sessionData, 'SessionDetail.MoneyBusinessCommunityEducationWorkshopCode')
            ?? null;

        $interpreterPresent = $sessionData['interpreter_present']
            ?? $sessionData['InterpreterPresent']
            ?? data_get($sessionData, 'Session.InterpreterPresent')
            ?? data_get($sessionData, 'SessionDetail.InterpreterPresent')
            ?? false;

        $serviceSettingCode = $sessionData['service_setting_code']
            ?? $sessionData['ServiceSettingCode']
            ?? data_get($sessionData, 'Session.ServiceSettingCode')
            ?? data_get($sessionData, 'SessionDetail.ServiceSettingCode')
            ?? null;

        return MigratedEnrichedSession::updateOrCreate(
            ['session_id' => $shallowSession->session_id],
            [
                'case_id' => $caseId,
                'shallow_session_id' => $shallowSession->id,
                'session_date' => $sessionDate,
                'service_type_id' => $serviceTypeId,
                'total_number_of_unidentified_clients' => $totalNumberOfUnidentifiedClients,
                'fees_charged' => $feesCharged,
                'money_business_community_education_workshop_code' => $moneyBusinessCommunityEducationWorkshopCode,
                'interpreter_present' => $interpreterPresent,
                'service_setting_code' => $serviceSettingCode,
                'api_response' => $sessionData,
                'enriched_at' => now(),
                'verification_status' => VerificationStatus::PENDING,
            ]
        );
    }

    /**
     * Extract session IDs from case data
     * Handles various possible formats from the API
     *
     * @param array $caseData
     * @return array
     */
    protected function extractSessions(array $caseData): array
    {
        // Try to extract as array first
        if (isset($caseData['sessions']) && is_array($caseData['sessions'])) {
            return array_values(array_filter($caseData['sessions']));
        }

        // Check for Sessions key with SessionId subkey (typical API format)
        if (isset($caseData['Sessions']) && is_array($caseData['Sessions'])) {
            if (isset($caseData['Sessions']['SessionId'])) {
                $sessionIds = $caseData['Sessions']['SessionId'];
                // Convert single string to array
                if (is_string($sessionIds)) {
                    return [$sessionIds];
                }
                if (is_array($sessionIds)) {
                    return array_values(array_filter($sessionIds));
                }
            }
            // If Sessions is just an array of IDs
            return array_values(array_filter($caseData['Sessions']));
        }

        // Check for nested structure (Case.Sessions)
        $sessionsData = data_get($caseData, 'Case.Sessions');
        if ($sessionsData && is_array($sessionsData)) {
            if (isset($sessionsData['SessionId'])) {
                $sessionIds = $sessionsData['SessionId'];
                if (is_string($sessionIds)) {
                    return [$sessionIds];
                }
                if (is_array($sessionIds)) {
                    return array_values(array_filter($sessionIds));
                }
            }
            return array_values(array_filter($sessionsData));
        }

        // If we got a string, try to parse it
        $sessionsString = $caseData['sessions'] ?? $caseData['Sessions'] ?? null;
        if (is_string($sessionsString)) {
            $sessionIds = array_map('trim', explode(',', $sessionsString));
            return array_values(array_filter($sessionIds));
        }

        // Return empty array if nothing found
        return [];
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
     * Check if a case/session has already been enriched
     *
     * @param ResourceType $type The resource type being checked
     * @param string $id The case_id or session_id to check
     * @return bool
     */
    protected function isAlreadyEnriched(ResourceType $type, string $id): bool
    {
        if ($type === ResourceType::CASE) {
            return MigratedEnrichedCase::where('case_id', $id)->exists();
        }
        if ($type === ResourceType::SESSION) {
            return MigratedEnrichedSession::where('session_id', $id)->exists();
        }
        return false;
    }

    /**
     * Get enrichment progress statistics
     * Used for UI progress display
     *
     * @return array
     */
    public function getEnrichmentProgress(ResourceType $type): array
    {
        if ($type === ResourceType::CASE) {

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
        } else if ($type === ResourceType::SESSION) {

            $totalShallowSessions = MigratedShallowSession::count();
            $enrichedSessions = MigratedEnrichedSession::count();
            $unenrichedCount = $totalShallowSessions - $enrichedSessions;

            return [
                'total_shallow_sessions' => $totalShallowSessions,
                'enriched_sessions' => $enrichedSessions,
                'unenriched_sessions' => $unenrichedCount,
                'progress_percentage' => $totalShallowSessions > 0
                    ? round(($enrichedSessions / $totalShallowSessions) * 100, 2)
                    : 0,
            ];
        } else return [];
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
     * Get session IDs that haven't been enriched yet
     * Helper method for testing and batch processing
     *
     * @return Collection
     */
    public function getUnenrichedSessionIds(): Collection
    {
        $enrichedSessionIds = MigratedEnrichedSession::pluck('session_id');

        return MigratedShallowSession::whereNotIn('session_id', $enrichedSessionIds)
            ->pluck('session_id');
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
