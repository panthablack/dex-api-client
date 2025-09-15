<?php

namespace App\Services;

use App\Models\DataMigration;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DataVerificationService
{
    protected DataExchangeService $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        $this->dataExchangeService = $dataExchangeService;
    }

    /**
     * Verify all data for a migration
     */
    public function verifyMigration(DataMigration $migration): array
    {
        $results = [
            'migration_id' => $migration->id,
            'migration_name' => $migration->name,
            'verification_started_at' => now()->toISOString(),
            'resource_results' => [],
            'overall_summary' => []
        ];

        foreach ($migration->resource_types as $resourceType) {
            Log::info("Verifying {$resourceType} data for migration {$migration->id}");

            try {
                $resourceResult = $this->verifyResourceType($migration, $resourceType);
                $results['resource_results'][$resourceType] = $resourceResult;
            } catch (\Exception $e) {
                Log::error("Failed to verify {$resourceType} for migration {$migration->id}: " . $e->getMessage());
                $results['resource_results'][$resourceType] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'verified_count' => 0,
                    'discrepancy_count' => 0,
                    'missing_count' => 0
                ];
            }
        }

        $results['overall_summary'] = $this->calculateOverallSummary($results['resource_results']);
        $results['verification_completed_at'] = now()->toISOString();

        return $results;
    }

    /**
     * Verify a specific resource type for a migration
     */
    protected function verifyResourceType(DataMigration $migration, string $resourceType): array
    {
        $batchIds = $migration->batches()
            ->where('resource_type', $resourceType)
            ->where('status', 'completed')
            ->pluck('batch_id');

        if ($batchIds->isEmpty()) {
            return [
                'status' => 'no_data',
                'message' => "No completed batches found for {$resourceType}",
                'verified_count' => 0,
                'discrepancy_count' => 0,
                'missing_count' => 0,
                'discrepancies' => []
            ];
        }

        // Get migrated records
        $migratedRecords = $this->getMigratedRecords($resourceType, $batchIds);

        if ($migratedRecords->isEmpty()) {
            return [
                'status' => 'no_data',
                'message' => "No migrated records found for {$resourceType}",
                'verified_count' => 0,
                'discrepancy_count' => 0,
                'missing_count' => 0,
                'discrepancies' => []
            ];
        }

        // Sample verification (verify a subset to avoid overwhelming the API)
        $sampleSize = min(20, $migratedRecords->count());
        $sampleRecords = $migratedRecords->random($sampleSize);

        $verificationResults = [
            'status' => 'completed',
            'total_migrated' => $migratedRecords->count(),
            'sample_size' => $sampleSize,
            'verified_count' => 0,
            'discrepancy_count' => 0,
            'missing_count' => 0,
            'discrepancies' => []
        ];

        foreach ($sampleRecords as $record) {
            try {
                $verification = $this->verifyRecord($resourceType, $record);

                if ($verification['status'] === 'verified') {
                    $verificationResults['verified_count']++;
                } elseif ($verification['status'] === 'discrepancy') {
                    $verificationResults['discrepancy_count']++;
                    $verificationResults['discrepancies'][] = $verification;
                } elseif ($verification['status'] === 'missing') {
                    $verificationResults['missing_count']++;
                    $verificationResults['discrepancies'][] = $verification;
                }
            } catch (\Exception $e) {
                Log::warning("Failed to verify record {$record->id}: " . $e->getMessage());
                $verificationResults['discrepancies'][] = [
                    'record_id' => $this->getRecordId($resourceType, $record),
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $verificationResults;
    }

    /**
     * Verify a single record against API data
     */
    protected function verifyRecord(string $resourceType, $record): array
    {
        $recordId = $this->getRecordId($resourceType, $record);

        try {
            // Fetch current data from API
            $apiData = $this->fetchFromApi($resourceType, $recordId, $record);

            if (!$apiData) {
                return [
                    'record_id' => $recordId,
                    'status' => 'missing',
                    'message' => 'Record not found in API'
                ];
            }

            // Compare key fields
            $discrepancies = $this->compareRecords($resourceType, $record, $apiData);

            if (empty($discrepancies)) {
                return [
                    'record_id' => $recordId,
                    'status' => 'verified',
                    'message' => 'Record matches API data'
                ];
            } else {
                return [
                    'record_id' => $recordId,
                    'status' => 'discrepancy',
                    'message' => 'Record has discrepancies with API data',
                    'discrepancies' => $discrepancies
                ];
            }
        } catch (\Exception $e) {
            return [
                'record_id' => $recordId,
                'status' => 'error',
                'message' => 'Failed to verify record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get migrated records for a resource type
     */
    protected function getMigratedRecords(string $resourceType, Collection $batchIds): Collection
    {
        switch ($resourceType) {
            case 'clients':
                return MigratedClient::whereIn('migration_batch_id', $batchIds)->get();
            case 'cases':
                return MigratedCase::whereIn('migration_batch_id', $batchIds)->get();
            case 'sessions':
                return MigratedSession::whereIn('migration_batch_id', $batchIds)->get();
            default:
                throw new \InvalidArgumentException("Unknown resource type: {$resourceType}");
        }
    }

    /**
     * Get record ID for a given resource type
     */
    protected function getRecordId(string $resourceType, $record): string
    {
        switch ($resourceType) {
            case 'clients':
                return $record->client_id;
            case 'cases':
                return $record->case_id;
            case 'sessions':
                return $record->session_id;
            default:
                return (string) $record->id;
        }
    }

    /**
     * Fetch record from API
     */
    protected function fetchFromApi(string $resourceType, string $recordId, $record)
    {
        switch ($resourceType) {
            case 'clients':
                return $this->dataExchangeService->getClientById($recordId);

            case 'cases':
                return $this->dataExchangeService->getCaseById($recordId);

            case 'sessions':
                // Sessions require case_id
                $caseId = $record->case_id;
                return $this->dataExchangeService->getSessionById($recordId, $caseId);

            default:
                throw new \InvalidArgumentException("Unknown resource type: {$resourceType}");
        }
    }

    /**
     * Compare migrated record with API data
     */
    protected function compareRecords(string $resourceType, $migratedRecord, $apiData): array
    {
        $discrepancies = [];

        switch ($resourceType) {
            case 'clients':
                $discrepancies = $this->compareClientRecords($migratedRecord, $apiData);
                break;
            case 'cases':
                $discrepancies = $this->compareCaseRecords($migratedRecord, $apiData);
                break;
            case 'sessions':
                $discrepancies = $this->compareSessionRecords($migratedRecord, $apiData);
                break;
        }

        return $discrepancies;
    }

    /**
     * Compare client records
     */
    protected function compareClientRecords($migrated, $api): array
    {
        $discrepancies = [];

        // Convert API data to array if it's an object
        if (is_object($api)) {
            $api = json_decode(json_encode($api), true);
        }

        // Define key fields to compare
        $fieldsToCompare = [
            'first_name' => ['FirstName', 'first_name', 'given_name'],
            'last_name' => ['LastName', 'last_name', 'family_name'],
            'date_of_birth' => ['DateOfBirth', 'date_of_birth', 'birth_date'],
            'gender' => ['Gender', 'gender', 'gender_code'],
            'suburb' => ['Suburb', 'suburb'],
            'state' => ['State', 'state'],
            'postal_code' => ['PostalCode', 'postal_code', 'postcode']
        ];

        foreach ($fieldsToCompare as $migratedField => $apiFields) {
            $migratedValue = $migrated->{$migratedField};
            $apiValue = $this->getApiValue($api, $apiFields);

            if ($this->normalizeValue($migratedValue) !== $this->normalizeValue($apiValue)) {
                $discrepancies[] = [
                    'field' => $migratedField,
                    'migrated_value' => $migratedValue,
                    'api_value' => $apiValue
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Compare case records
     */
    protected function compareCaseRecords($migrated, $api): array
    {
        $discrepancies = [];

        if (is_object($api)) {
            $api = json_decode(json_encode($api), true);
        }

        $fieldsToCompare = [
            'client_id' => ['ClientId', 'client_id'],
            'outlet_activity_id' => ['OutletActivityId', 'outlet_activity_id'],
            'referral_source_code' => ['ReferralSourceCode', 'referral_source_code'],
            'end_date' => ['EndDate', 'end_date']
        ];

        foreach ($fieldsToCompare as $migratedField => $apiFields) {
            $migratedValue = $migrated->{$migratedField};
            $apiValue = $this->getApiValue($api, $apiFields);

            if ($this->normalizeValue($migratedValue) !== $this->normalizeValue($apiValue)) {
                $discrepancies[] = [
                    'field' => $migratedField,
                    'migrated_value' => $migratedValue,
                    'api_value' => $apiValue
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Compare session records
     */
    protected function compareSessionRecords($migrated, $api): array
    {
        $discrepancies = [];

        if (is_object($api)) {
            $api = json_decode(json_encode($api), true);
        }

        $fieldsToCompare = [
            'case_id' => ['CaseId', 'case_id'],
            'service_type_id' => ['ServiceTypeId', 'service_type_id'],
            'session_date' => ['SessionDate', 'session_date'],
            'duration_minutes' => ['DurationMinutes', 'duration_minutes']
        ];

        foreach ($fieldsToCompare as $migratedField => $apiFields) {
            $migratedValue = $migrated->{$migratedField};
            $apiValue = $this->getApiValue($api, $apiFields);

            if ($this->normalizeValue($migratedValue) !== $this->normalizeValue($apiValue)) {
                $discrepancies[] = [
                    'field' => $migratedField,
                    'migrated_value' => $migratedValue,
                    'api_value' => $apiValue
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Get value from API data using multiple possible field names
     */
    protected function getApiValue($api, array $possibleFields)
    {
        foreach ($possibleFields as $field) {
            if (isset($api[$field])) {
                return $api[$field];
            }
        }
        return null;
    }

    /**
     * Normalize values for comparison
     */
    protected function normalizeValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return trim(strtolower($value));
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Calculate overall summary from resource results
     */
    protected function calculateOverallSummary(array $resourceResults): array
    {
        $summary = [
            'total_verified' => 0,
            'total_discrepancies' => 0,
            'total_missing' => 0,
            'total_errors' => 0,
            'success_rate' => 0,
            'resources_verified' => count($resourceResults),
            'overall_status' => 'unknown'
        ];

        foreach ($resourceResults as $result) {
            if (isset($result['verified_count'])) {
                $summary['total_verified'] += $result['verified_count'];
            }
            if (isset($result['discrepancy_count'])) {
                $summary['total_discrepancies'] += $result['discrepancy_count'];
            }
            if (isset($result['missing_count'])) {
                $summary['total_missing'] += $result['missing_count'];
            }
            if ($result['status'] === 'error') {
                $summary['total_errors']++;
            }
        }

        $totalChecked = $summary['total_verified'] + $summary['total_discrepancies'] + $summary['total_missing'];

        if ($totalChecked > 0) {
            $summary['success_rate'] = round(($summary['total_verified'] / $totalChecked) * 100, 2);
        }

        // Determine overall status
        if ($summary['total_errors'] > 0) {
            $summary['overall_status'] = 'error';
        } elseif ($summary['total_discrepancies'] > 0 || $summary['total_missing'] > 0) {
            $summary['overall_status'] = 'discrepancies_found';
        } elseif ($summary['total_verified'] > 0) {
            $summary['overall_status'] = 'verified';
        } else {
            $summary['overall_status'] = 'no_data';
        }

        return $summary;
    }

    /**
     * Quick verification of a small sample
     */
    public function quickVerify(DataMigration $migration, int $sampleSize = 5): array
    {
        $results = [
            'migration_id' => $migration->id,
            'sample_size' => $sampleSize,
            'quick_verification' => true,
            'verified_at' => now()->toISOString(),
            'results' => []
        ];

        foreach ($migration->resource_types as $resourceType) {
            try {
                $batchIds = $migration->batches()
                    ->where('resource_type', $resourceType)
                    ->where('status', 'completed')
                    ->pluck('batch_id');

                if ($batchIds->isEmpty()) {
                    $results['results'][$resourceType] = [
                        'status' => 'no_data',
                        'verified' => 0,
                        'total_checked' => 0
                    ];
                    continue;
                }

                $records = $this->getMigratedRecords($resourceType, $batchIds);
                $sample = $records->random(min($sampleSize, $records->count()));

                $verified = 0;
                foreach ($sample as $record) {
                    $verification = $this->verifyRecord($resourceType, $record);
                    if ($verification['status'] === 'verified') {
                        $verified++;
                    }
                }

                $results['results'][$resourceType] = [
                    'status' => 'completed',
                    'verified' => $verified,
                    'total_checked' => $sample->count(),
                    'success_rate' => $sample->count() > 0 ? round(($verified / $sample->count()) * 100, 2) : 0
                ];
            } catch (\Exception $e) {
                $results['results'][$resourceType] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'verified' => 0,
                    'total_checked' => 0
                ];
            }
        }

        return $results;
    }
}
