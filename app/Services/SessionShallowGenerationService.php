<?php

namespace App\Services;

use App\Models\MigratedCase;
use App\Models\MigratedEnrichedCase;
use App\Models\MigratedShallowSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SessionShallowGenerationService
{
    /**
     * Check if shallow sessions can be generated
     * Returns true if either migrated_cases or migrated_enriched_cases has data
     */
    public function canGenerate(): bool
    {
        return $this->getAvailableSource() !== null;
    }

    /**
     * Get the available source for session extraction
     * Prefers migrated_cases, falls back to migrated_enriched_cases
     *
     * @return string|null 'migrated_cases', 'migrated_enriched_cases', or null
     */
    public function getAvailableSource(): ?string
    {
        if (MigratedCase::count() > 0) {
            return 'migrated_cases';
        }

        if (MigratedEnrichedCase::count() > 0) {
            return 'migrated_enriched_cases';
        }

        return null;
    }

    /**
     * Generate shallow sessions from local case data
     * Extracts session IDs and case IDs from nested sessions array
     *
     * @return array {
     *   total_sessions_found: int,
     *   newly_created: int,
     *   already_existed: int,
     *   source: string,
     *   errors: array
     * }
     */
    public function generateShallowSessions(): array
    {
        $source = $this->getAvailableSource();

        if (!$source) {
            throw new \Exception('No case data available. Please complete a Case migration first.');
        }

        Log::info("Starting shallow session generation from source: {$source}");

        try {
            $cases = $this->getCasesFromSource($source);
            $sessionPairs = $this->extractSessionPairs($cases);

            $stats = [
                'total_sessions_found' => count($sessionPairs),
                'newly_created' => 0,
                'already_existed' => 0,
                'source' => $source,
                'errors' => [],
            ];

            foreach ($sessionPairs as $pair) {
                try {
                    $shallow = MigratedShallowSession::updateOrCreate(
                        [
                            'case_id' => $pair['case_id'],
                            'session_id' => $pair['session_id']
                        ],
                        []
                    );

                    // Track if this was newly created
                    if ($shallow->wasRecentlyCreated) {
                        $stats['newly_created']++;
                    } else {
                        $stats['already_existed']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors'][] = [
                        'session_id' => $pair['session_id'],
                        'case_id' => $pair['case_id'],
                        'error' => $e->getMessage(),
                    ];

                    Log::error(
                        "Failed to create shallow session for {$pair['session_id']}: {$e->getMessage()}"
                    );
                }
            }

            Log::info('Shallow session generation completed', $stats);

            return $stats;
        } catch (\Exception $e) {
            Log::error("Shallow session generation failed: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Get cases from the appropriate source
     *
     * @param string $source 'migrated_cases' or 'migrated_enriched_cases'
     * @return Collection
     */
    private function getCasesFromSource(string $source): Collection
    {
        if ($source === 'migrated_cases') {
            return MigratedCase::whereNotNull('sessions')->get();
        }

        return MigratedEnrichedCase::whereNotNull('sessions')->get();
    }

    /**
     * Extract session ID and case ID pairs from cases
     * Handles both single session and array of sessions
     *
     * @param Collection $cases
     * @return array Array of {session_id, case_id} pairs
     */
    private function extractSessionPairs(Collection $cases): array
    {
        $pairs = [];

        foreach ($cases as $case) {
            $caseId = $case->case_id;

            // Handle null or empty sessions
            if (!$case->sessions) {
                continue;
            }

            $sessions = $case->sessions;

            // Handle single session object (convert to array)
            if (is_object($sessions)) {
                $sessions = [$sessions];
            }

            // Ensure it's an array
            if (!is_array($sessions)) {
                Log::warning("Unexpected sessions format for case {$caseId}: " . gettype($sessions));
                continue;
            }

            // Extract session IDs
            foreach ($sessions as $session) {
                $sessionId = $this->extractSessionId($session);

                if ($sessionId) {
                    $pairs[] = [
                        'session_id' => $sessionId,
                        'case_id' => $caseId,
                    ];
                }
            }
        }

        return $pairs;
    }

    /**
     * Extract session ID from a session object/array
     * Handles various API response formats
     *
     * @param mixed $session
     * @return string|null
     */
    private function extractSessionId($session): ?string
    {
        // Handle null
        if ($session === null) {
            return null;
        }

        // Convert object to array if needed
        if (is_object($session)) {
            $session = json_decode(json_encode($session), true);
        }

        // If it's a string, assume it's already a session ID
        if (is_string($session)) {
            return $session;
        }

        // Handle array format
        if (is_array($session)) {
            // Try various possible keys
            $sessionId = $session['session_id']
                ?? $session['SessionId']
                ?? $session['Session']
                ?? $session['session']
                ?? null;

            // If we got an array instead of string, try extracting ID from nested structure
            if (is_array($sessionId)) {
                $sessionId = $sessionId['session_id'] ?? $sessionId['SessionId'] ?? null;
            }

            return $sessionId ? (string)$sessionId : null;
        }

        // Fallback for numeric values
        if (is_numeric($session)) {
            return (string)$session;
        }

        return null;
    }
}
