<?php

namespace App\Services;

use App\Models\DataMigration;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use Exception;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    protected DataExchangeService $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        $this->dataExchangeService = $dataExchangeService;
    }

    /**
     * Verify a single client record by calling the DSS API
     */
    public function verifyClient(MigratedClient $client): bool
    {
        try {
            // Use the client_id to retrieve the record from DSS
            $response = $this->dataExchangeService->getClientById($client->client_id);

            if ($response && $this->validateClientData($client, $response)) {
                $client->update([
                    'verified' => true,
                    'verified_at' => now(),
                    'verification_error' => null
                ]);
                return true;
            } else {
                $client->update([
                    'verified' => false,
                    'verified_at' => now(),
                    'verification_error' => 'Client data validation failed or record not found in DSS'
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Client verification failed', [
                'client_id' => $client->client_id,
                'error' => $e->getMessage()
            ]);

            $client->update([
                'verified' => false,
                'verified_at' => now(),
                'verification_error' => 'API Error: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify a single case record by calling the DSS API
     */
    public function verifyCase(MigratedCase $case): bool
    {
        try {
            // Use the case_id to retrieve the record from DSS
            $response = $this->dataExchangeService->getCaseById($case->case_id);

            if ($response && $this->validateCaseData($case, $response)) {
                $case->update([
                    'verified' => true,
                    'verified_at' => now(),
                    'verification_error' => null
                ]);
                return true;
            } else {
                $case->update([
                    'verified' => false,
                    'verified_at' => now(),
                    'verification_error' => 'Case data validation failed or record not found in DSS'
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Case verification failed', [
                'case_id' => $case->case_id,
                'error' => $e->getMessage()
            ]);

            $case->update([
                'verified' => false,
                'verified_at' => now(),
                'verification_error' => 'API Error: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify a single session record by calling the DSS API
     */
    public function verifySession(MigratedSession $session): bool
    {
        try {
            // Use the session_id to retrieve the record from DSS
            $response = $this->dataExchangeService->getSessionById($session->session_id, $session->case_id);

            if ($response && $this->validateSessionData($session, $response)) {
                $session->update([
                    'verified' => true,
                    'verified_at' => now(),
                    'verification_error' => null
                ]);
                return true;
            } else {
                $session->update([
                    'verified' => false,
                    'verified_at' => now(),
                    'verification_error' => 'Session data validation failed or record not found in DSS'
                ]);
                return false;
            }
        } catch (Exception $e) {
            Log::error('Session verification failed', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage()
            ]);

            $session->update([
                'verified' => false,
                'verified_at' => now(),
                'verification_error' => 'API Error: ' . $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get verification statistics for a data migration
     */
    public function getMigrationVerificationStats(DataMigration $migration): array
    {
        $stats = [
            'total' => 0,
            'verified' => 0,
            'failed' => 0,
            'success_rate' => 0,
            'results' => []
        ];

        // Get stats for each resource type
        foreach ($migration->resource_types as $resourceType) {
            $modelClass = $this->getModelClass($resourceType);

            if ($modelClass) {
                // Get the batch IDs for this migration and resource type
                $batchIds = $migration->batches()
                    ->where('resource_type', $resourceType)
                    ->where('status', 'completed')
                    ->pluck('batch_id')
                    ->toArray();

                $query = $modelClass::whereIn('migration_batch_id', $batchIds);

                $total = $query->count();
                $verified = $query->where('verified', true)->count();
                $failed = $total - $verified;

                $stats['results'][$resourceType] = [
                    'total' => $total,
                    'verified' => $verified,
                    'failed' => $failed,
                    'success_rate' => $total > 0 ? round(($verified / $total) * 100) : 0
                ];

                $stats['total'] += $total;
                $stats['verified'] += $verified;
                $stats['failed'] += $failed;
            }
        }

        $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['verified'] / $stats['total']) * 100) : 0;

        return $stats;
    }

    /**
     * Quick verification - sample a subset of records
     */
    public function quickVerifyMigration(DataMigration $migration, int $sampleSize = 10): array
    {
        $results = [];

        foreach ($migration->resource_types as $resourceType) {
            $modelClass = $this->getModelClass($resourceType);

            if ($modelClass) {
                // Get the batch IDs for this migration and resource type
                $batchIds = $migration->batches()
                    ->where('resource_type', $resourceType)
                    ->where('status', 'completed')
                    ->pluck('batch_id')
                    ->toArray();

                $records = $modelClass::whereIn('migration_batch_id', $batchIds)
                    ->inRandomOrder()
                    ->limit($sampleSize)
                    ->get();

                $verified = 0;
                foreach ($records as $record) {
                    if ($this->verifyRecord($resourceType, $record)) {
                        $verified++;
                    }
                }

                $results[$resourceType] = [
                    'total_checked' => $records->count(),
                    'verified' => $verified,
                    'failed' => $records->count() - $verified,
                    'success_rate' => $records->count() > 0 ? round(($verified / $records->count()) * 100) : 0,
                    'status' => $records->count() > 0 ? 'completed' : 'no_data'
                ];
            }
        }

        return [
            'sample_size' => $sampleSize,
            'results' => $results
        ];
    }

    /**
     * Verify a record based on its type
     */
    public function verifyRecord(string $resourceType, $record): bool
    {
        switch ($resourceType) {
            case 'clients':
                return $this->verifyClient($record);
            case 'cases':
                return $this->verifyCase($record);
            case 'sessions':
                return $this->verifySession($record);
            default:
                return false;
        }
    }

    /**
     * Get the model class for a resource type
     */
    private function getModelClass(string $resourceType): ?string
    {
        return match ($resourceType) {
            'clients' => MigratedClient::class,
            'cases' => MigratedCase::class,
            'sessions' => MigratedSession::class,
            default => null
        };
    }

    /**
     * Validate client data against DSS response
     */
    private function validateClientData(MigratedClient $client, $dssData): bool
    {
        // Convert stdClass to array if needed
        if (is_object($dssData)) {
            $dssData = json_decode(json_encode($dssData), true);
        }

        // Basic validation - ensure key fields exist
        $dssClient = $dssData['Client'] ?? null;
        $dssId = $dssClient['ClientId'] ?? null;
        $dssFirstName = $dssClient['GivenName'] ?? null;
        $dssFamilyName = $dssClient['FamilyName'] ?? null;
        if (!($dssClient && $dssFirstName && $dssFamilyName)) return false;

        // ensure key fields match
        return
            $dssId == $client->client_id &&
            $dssFirstName == $client->first_name &&
            $dssFamilyName == $client->last_name;
    }

    /**
     * Validate case data against DSS response
     */
    private function validateCaseData(MigratedCase $case, $dssData): bool
    {
        // Convert stdClass to array if needed
        if (is_object($dssData)) {
            $dssData = json_decode(json_encode($dssData), true);
        }

        // Basic validation - ensure key fields match
        return isset($dssData['case_id']) &&
            $dssData['case_id'] == $case->case_id &&
            isset($dssData['client_id']) &&
            $dssData['client_id'] == $case->client_id;
    }

    /**
     * Validate session data against DSS response
     */
    private function validateSessionData(MigratedSession $session, $dssData): bool
    {
        // Convert stdClass to array if needed
        if (is_object($dssData)) {
            $dssData = json_decode(json_encode($dssData), true);
        }

        // Basic validation - ensure key fields match
        return isset($dssData['session_id']) &&
            $dssData['session_id'] == $session->session_id &&
            isset($dssData['case_id']) &&
            $dssData['case_id'] == $session->case_id;
    }
}
