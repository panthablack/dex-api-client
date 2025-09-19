<?php

namespace App\Services;

use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use App\Models\DataMigration;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
        return $this->verifyWithRetry('client', $client, function () use ($client) {
            return $this->dataExchangeService->getClientById($client->client_id);
        }, function ($response) use ($client) {
            return $this->validateClientData($client, $response);
        });
    }

    /**
     * Verify a single case record by calling the DSS API
     */
    public function verifyCase(MigratedCase $case): bool
    {
        return $this->verifyWithRetry('case', $case, function () use ($case) {
            return $this->dataExchangeService->getCaseById($case->case_id);
        }, function ($response) use ($case) {
            return $this->validateCaseData($case, $response);
        });
    }

    /**
     * Verify a single session record by calling the DSS API
     */
    public function verifySession(MigratedSession $session): bool
    {
        return $this->verifyWithRetry('session', $session, function () use ($session) {
            return $this->dataExchangeService->getSessionById($session->session_id, $session->case_id);
        }, function ($response) use ($session) {
            return $this->validateSessionData($session, $response);
        });
    }

    /**
     * Verify with retry logic and circuit breaker pattern - PREVENTION MEASURE
     */
    protected function verifyWithRetry(string $type, $record, callable $apiCall, callable $validator): bool
    {
        $circuitBreakerKey = 'verification_circuit_breaker';
        $failureThreshold = 10; // Number of consecutive failures before opening circuit
        $timeoutPeriod = 300; // 5 minutes before retrying

        // Check circuit breaker status
        $circuitState = Cache::get($circuitBreakerKey, ['failures' => 0, 'last_failure' => null]);

        if (
            $circuitState['failures'] >= $failureThreshold &&
            $circuitState['last_failure'] &&
            (time() - $circuitState['last_failure']) < $timeoutPeriod
        ) {

            // Circuit is open - don't make API calls, leave record as pending
            Log::warning('Circuit breaker open, skipping verification', [
                'type' => $type,
                'record_id' => $this->getRecordId($record),
                'failures' => $circuitState['failures'],
                'last_failure' => $circuitState['last_failure']
            ]);
            return false; // Don't update record status - leave as pending for retry
        }

        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount <= $maxRetries) {
            try {
                $response = $apiCall();

                if ($response && $validator($response)) {
                    // Success - reset circuit breaker
                    if ($circuitState['failures'] > 0) {
                        Cache::forget($circuitBreakerKey);
                        Log::info('Circuit breaker reset after successful verification', [
                            'type' => $type,
                            'record_id' => $this->getRecordId($record)
                        ]);
                    }

                    $record->update([
                        'verification_status' => VerificationStatus::VERIFIED,
                        'verified_at' => now(),
                        'verification_error' => null
                    ]);
                    return true;
                } else {
                    // Validation failed
                    $record->update([
                        'verification_status' => VerificationStatus::FAILED,
                        'verified_at' => now(),
                        'verification_error' => ucfirst($type) . ' data validation failed or record not found in DSS'
                    ]);
                    return false;
                }
            } catch (Exception $e) {
                $retryCount++;
                $isConnectionError = $this->isConnectionError($e);

                Log::warning(ucfirst($type) . ' verification attempt failed', [
                    'record_id' => $this->getRecordId($record),
                    'error' => $e->getMessage(),
                    'retry_count' => $retryCount,
                    'max_retries' => $maxRetries,
                    'is_connection_error' => $isConnectionError
                ]);

                // If it's a connection error and we've exhausted retries
                if ($isConnectionError && $retryCount > $maxRetries) {
                    // Update circuit breaker
                    $circuitState['failures']++;
                    $circuitState['last_failure'] = time();
                    Cache::put($circuitBreakerKey, $circuitState, 3600);

                    // Don't mark as failed - leave as pending for later retry
                    Log::error('Verification failed after retries, leaving as pending', [
                        'type' => $type,
                        'record_id' => $this->getRecordId($record),
                        'circuit_failures' => $circuitState['failures']
                    ]);
                    return false;
                }

                // For non-connection errors, mark as failed immediately
                if (!$isConnectionError) {
                    $record->update([
                        'verification_status' => VerificationStatus::FAILED,
                        'verified_at' => now(),
                        'verification_error' => 'API Error: ' . $e->getMessage()
                    ]);
                    return false;
                }

                // Wait before retry (exponential backoff)
                if ($retryCount <= $maxRetries) {
                    sleep(pow(2, $retryCount)); // 2, 4, 8 seconds
                }
            }
        }

        return false;
    }

    /**
     * Check if the exception is a connection-related error
     */
    protected function isConnectionError(Exception $e): bool
    {
        $connectionErrors = [
            'Could not connect to host',
            'Connection timed out',
            'Connection refused',
            'Network is unreachable',
            'No route to host',
            'SOAP call failed'
        ];

        foreach ($connectionErrors as $error) {
            if (stripos($e->getMessage(), $error) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get record ID for logging purposes
     */
    protected function getRecordId($record): string
    {
        if ($record instanceof MigratedClient) {
            return $record->client_id;
        } elseif ($record instanceof MigratedCase) {
            return $record->case_id;
        } elseif ($record instanceof MigratedSession) {
            return $record->session_id;
        }
        return 'unknown';
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
                    ->pluck('id')
                    ->toArray();

                $query = $modelClass::whereIn('migration_batch_id', $batchIds);

                $total = $query->count();
                $verified = $query->where('verification_status', VerificationStatus::VERIFIED)->count();
                $failed = $query->where('verification_status', VerificationStatus::FAILED)->count();

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
                    ->pluck('id')
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
    public function verifyRecord(ResourceType $resourceType, $record): bool
    {
        switch ($resourceType) {
            case ResourceType::CLIENT:
                return $this->verifyClient($record);
            case ResourceType::CASE:
                return $this->verifyCase($record);
            case ResourceType::SESSION:
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
            ResourceType::CLIENT->value => MigratedClient::class,
            ResourceType::CASE->value => MigratedCase::class,
            ResourceType::SESSION->value => MigratedSession::class,
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
