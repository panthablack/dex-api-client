<?php

namespace App\Services;

use App\Enums\DataMigrationStatus;
use App\Enums\DataMigrationBatchStatus;
use App\Enums\FilterType;
use App\Enums\ResourceType;
use App\Helpers\ObjectHelpers;
use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedShallowCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use App\Jobs\ProcessDataMigrationBatch;
use App\Resources\Filters;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DataMigrationService
{
    protected $dataExchangeService;

    public function __construct(DataExchangeService $dataExchangeService)
    {
        $this->dataExchangeService = $dataExchangeService;
    }

    /**
     * Create a new data migration
     */
    public function createMigration(array $data): DataMigration
    {
        $filters = $data['filters'] ?? [];
        $resourceType = ResourceType::resolve($data['resource_type']);;

        $callback = function () use ($data, $filters, $resourceType) {
            // Lock the table and check for existing active migrations for this resource type
            $activeMigrations = DataMigration::lockForUpdate()
                ->active()
                ->where('resource_type', $resourceType)
                ->count();

            if ($activeMigrations > 0) {
                throw new \Exception(
                    "Cannot create migration: There is already an active migration for resource type '{$resourceType->value}'. " .
                        "Please wait for the existing migration to complete or cancel it before starting a new one."
                );
            }

            // Calculate total items upfront using the fixed getTotalItemsForResource method
            $totalItems = $this->getTotalItemsForResource($resourceType, $filters);
            if (env('DETAILED_LOGGING'))
                Log::info("Found {$totalItems} items for {$resourceType->value}");

            return DataMigration::create([
                'name' => $data['name'],
                'resource_type' => $resourceType,
                'filters' => $filters->all,
                'batch_size' => $data['batch_size'],
                'total_items' => $totalItems,  // Set total items immediately
                'status' => DataMigrationStatus::PENDING
            ]);
        };

        // Only wrap in transaction if we're not already in one (for testing compatibility)
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        return DB::transaction($callback);
    }

    /**
     * Start a data migration by creating batches and dispatching jobs
     */
    public function startMigration(DataMigration $migration): void
    {
        $callback = function () use ($migration) {
            $migration->update([
                'status' => DataMigrationStatus::IN_PROGRESS,
                'started_at' => now()
            ]);

            // Create batches for all resource types, but respect dependencies
            $resourceType = ResourceType::resolve($migration->resource_type);
            $this->createBatchesForResource($migration, $resourceType);
        };

        // Only wrap in transaction if we're not already in one (for testing compatibility)
        if (DB::transactionLevel() === 0) {
            DB::transaction($callback);
        } else {
            $callback();
        }

        // Refresh the migration to ensure we have the latest batch data
        $migration->refresh();

        // Dispatch first batch of independent resource types only
        if (env('DETAILED_LOGGING'))
            Log::info("Starting to dispatch independent batches for migration {$migration->id}");

        // Get incomplete batches
        $batches = $migration->incompleteBatches();

        // if incomplete batches are found, process them
        if ($batches->count() > 0) {
            $this->dispatchBatches($migration, 3);
        } else {
            // else, set migration status completed
            $migration->update(['status' => DataMigrationStatus::COMPLETED]);
        }
    }

    /**
     * Get dependent batches for a migration
     */
    public function getDependentBatches(DataMigration $migration): Collection
    {
        return $migration->batches->filter(
            fn($batch) => in_array(
                ResourceType::resolve($batch->resource_type),
                ResourceType::getDependentResourceTypes()
            )
        );
    }

    /**
     * Get independent batches for a migration
     */
    public function getIndependentBatches(DataMigration $migration): Collection
    {
        return $migration->batches->filter(
            fn($batch) => in_array(
                ResourceType::resolve($batch->resource_type),
                ResourceType::getIndependentResourceTypes()
            )
        );
    }

    /**
     * Create batches for a specific resource type
     */
    protected function createBatchesForResource(DataMigration $migration, ResourceType $resourceType): void
    {
        // reconstruct filters object
        $filters = new Filters($migration->filters);

        // Get total count to determine number of batches needed
        $totalItems = $this->getTotalItemsForResource($resourceType, $filters);

        if ($totalItems === 0) {
            if (env('DETAILED_LOGGING'))
                Log::info("No items found for resource type: {$resourceType->value}");
            return;
        }

        $totalBatches = ceil($totalItems / $migration->batch_size);

        if (env('DETAILED_LOGGING'))
            Log::info("Creating {$totalBatches} batches for {$resourceType->value} ({$totalItems} total items)");

        for ($batchNumber = 1; $batchNumber <= $totalBatches; $batchNumber++) {
            $pageIndex = $batchNumber; // DSS API uses 1-based indexing

            // Calculate expected batch size (last batch may be smaller)
            $expectedBatchSize = $batchNumber < $totalBatches
                ? $migration->batch_size
                : $totalItems - (($batchNumber - 1) * $migration->batch_size);

            // create batch filters
            $batchfilters = $filters;
            $batchfilters->set(FilterType::PAGE_INDEX, $pageIndex);
            $batchfilters->set(FilterType::PAGE_SIZE, $migration->batch_size);

            DataMigrationBatch::create([
                'data_migration_id' => $migration->id,
                'resource_type' => $resourceType,
                'batch_number' => $batchNumber,
                'batch_size' => $expectedBatchSize,
                'page_index' => $pageIndex,
                'page_size' => $migration->batch_size,
                'status' => DataMigrationBatchStatus::PENDING,
                'api_filters' => $batchfilters->toJson()
            ]);
        }

        if (env('DETAILED_LOGGING'))
            Log::info("Created {$totalBatches} batches for {$resourceType->value} (total items already set during migration creation)");
    }

    /**
     * Get the sort column for a resource type for filtering Search calls via to the Dex API
     */
    public static function getDexSortColumn(ResourceType $resourceType): string | null
    {
        return FilterType::getDexFilter(FilterType::CREATED_DATE);
    }

    /**
     * Calculate total items to be processed for a resource type using Search APIs
     */
    protected function getTotalItemsForResource(ResourceType $resourceType, Filters $filters): int
    {
        try {
            // add minimum filter parameters to extract metadata from the api
            $filters->set(FilterType::PAGE_INDEX, 1);
            $filters->set(FilterType::PAGE_SIZE, 1);

            if (env('DETAILED_LOGGING'))
                Log::info("Getting total items count for {$resourceType->value}", ['filters' => $filters->all]);

            $response = null;
            if ($resourceType === ResourceType::CLIENT) {
                $response = $this->dataExchangeService->getClientData($filters);
            } else if ($resourceType === ResourceType::CASE) {
                $response = $this->dataExchangeService->getCaseData($filters);
            } else if ($resourceType === ResourceType::SHALLOW_CASE) {
                // SHALLOW_CASE uses same SearchCase API as CASE
                $response = $this->dataExchangeService->getCaseData($filters);
            } else if ($resourceType === ResourceType::SHALLOW_CLOSED_CASE) {
                // SHALLOW_CLOSED_CASE uses SearchCase API with END_DATE_TO filter
                $response = $this->dataExchangeService->getClosedCaseData($filters);
            } else if ($resourceType === ResourceType::CLOSED_CASE) {
                $response = $this->dataExchangeService->getClosedCaseData($filters);
            } else if ($resourceType === ResourceType::CASE_CLIENT) {
                $response = $this->dataExchangeService->getCaseClientData($filters);
            } else if ($resourceType === ResourceType::SESSION) {
                $response = $this->dataExchangeService->getSessionData($filters);
            } else {
                throw new \InvalidArgumentException(
                    "Unknown resource type: {$resourceType->value}"
                );
            }

            // Extract TotalCount from SOAP response metadata
            $totalCount = $this->extractTotalCountFromResponse($response);

            if ($totalCount > 0) {
                if (env('DETAILED_LOGGING'))
                    Log::info("Found {$totalCount} total items for {$resourceType->value}");
                return $totalCount;
            }
            throw new \Exception("Could not determine total items for {$resourceType->value}, using estimate");
        } catch (\Exception $e) {
            Log::error("Failed to get total items for {$resourceType->value}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract TotalCount from SOAP API response metadata
     */
    protected function extractTotalCountFromResponse($response): int
    {
        // Handle different response structures from SOAP APIs
        if (is_object($response)) {
            $response = json_decode(json_encode($response), true);
        }

        if (!is_array($response)) {
            return 0;
        }

        // Check for TotalCount in various possible locations
        $possiblePaths = [
            'TotalCount',
            'totalCount',
            'total_count',
            'pagination.total_items',
            'pagination.TotalCount',
            'Pagination.TotalCount',
            'SearchResult.TotalCount',
            'Result.TotalCount'
        ];

        foreach ($possiblePaths as $path) {
            $value = $this->getNestedValue($response, $path);
            if (is_numeric($value) && $value > 0) {
                return (int) $value;
            }
        }

        return 0;
    }

    /**
     * Get nested array value using dot notation
     */
    protected function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Check if a resource type can start processing (dependencies completed)
     */
    protected function canProcessResourceType(DataMigration $migration, ResourceType $resourceType): bool
    {
        $dependencies = [
            ResourceType::CLIENT->value => [],
            ResourceType::CASE->value => [],
            ResourceType::SESSION->value => [ResourceType::CASE] // Sessions depend on cases
        ];

        $requiredDependencies = $dependencies[$resourceType->value] ?? [];

        foreach ($requiredDependencies as $dependency) {
            // Check if all batches for this dependency are completed
            $incompleteBatches = $migration->batches()
                ->where('resource_type', $dependency)
                ->where('status', '!=', DataMigrationStatus::COMPLETED)
                ->count();

            if ($incompleteBatches > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Restart a stuck migration by re-dispatching pending batches
     */
    public function restartMigration(DataMigration $migration): void
    {
        if (env('DETAILED_LOGGING'))
            Log::info("Restarting migration {$migration->id}");

        $migration->update([
            'status' => DataMigrationStatus::IN_PROGRESS,
            'started_at' => now()
        ]);

        $incompleteBatches = $migration->incompleteBatches();

        foreach ($incompleteBatches as $batch)
            $batch->update(['status' => DataMigrationBatchStatus::PENDING]);

        // Dispatch pending batches
        $this->dispatchBatches($migration);
    }

    /**
     * Dispatch pending batches for processing
     */
    public function dispatchBatches(DataMigration $migration, int $limit = 1): void
    {
        $dispatchedCount = 0;

        // Get all pending batches ordered by dependency priority
        $allPendingBatches = $migration->pendingBatches();

        foreach ($allPendingBatches as $batch) {
            if ($dispatchedCount >= $limit) break;

            $resourceType = ResourceType::resolve($batch->resource_type);

            // Check if this resource type can be processed (dependencies met)
            if ($this->canProcessResourceType($migration, $resourceType)) {
                ProcessDataMigrationBatch::dispatch($batch);

                // Refresh from DB to handle sync mode where job executes immediately
                $batch->refresh();

                // Only update to IN_PROGRESS if still PENDING (job may have already completed in sync mode)
                if ($batch->status === DataMigrationBatchStatus::PENDING) {
                    $batch->update(['status' => DataMigrationBatchStatus::IN_PROGRESS->value]);
                }

                $dispatchedCount++;

                if (env('DETAILED_LOGGING'))
                    Log::info("Dispatched {$resourceType->value} batch {$batch->batch_number} (dependencies satisfied)");
            } else {
                if (env('DETAILED_LOGGING'))
                    Log::info("Waiting for dependencies: {$resourceType->value} batch {$batch->batch_number}");
            }
        }

        if ($dispatchedCount === 0) {
            if (env('DETAILED_LOGGING'))
                Log::info("No batches ready for dispatch - waiting for dependencies to complete");
        }
    }

    /**
     * Process a single batch
     */
    public function processBatch(DataMigrationBatch $batch): void
    {
        try {
            $batch->update([
                'status' => DataMigrationBatchStatus::IN_PROGRESS,
                'started_at' => now()
            ]);

            if (env('DETAILED_LOGGING'))
                Log::info("Processing batch {$batch->batch_number} for {$batch->resource_type}");

            $expectedBatchSize = $batch->batch_size;
            $data = $this->fetchDataForBatch($batch);
            $storedCount = $this->storeData($batch, $data);
            $receivedCount = count($data);

            // Determine batch status based on storage success
            $errorMessage = null;

            if ($receivedCount === 0) {
                $status = DataMigrationBatchStatus::FAILED;
                $errorMessage = "No data received from API";
            } elseif ($storedCount < $receivedCount) {
                $status = DataMigrationBatchStatus::FAILED;
                $errorMessage = "Only stored {$storedCount} out of {$receivedCount} items - partial storage not allowed";
            } elseif ($receivedCount < $expectedBatchSize) {
                $status = DataMigrationBatchStatus::FAILED;
                $errorMessage = "Only received {$receivedCount} out of {$expectedBatchSize} items. ";
            } else {
                $status = DataMigrationBatchStatus::COMPLETED;
            }

            if ($errorMessage) Log::error($errorMessage);

            $batch->update([
                'status' => $status,
                'items_received' => $receivedCount,
                'items_stored' => $storedCount,
                'completed_at' => now()
            ]);

            // Update migration progress
            $this->updateMigrationProgress($batch->dataMigration);

            // Dispatch next batch if available
            $this->dispatchBatches($batch->dataMigration);
        } catch (\Exception $e) {
            $batch->refresh();
            if ($batch->status === DataMigrationBatchStatus::COMPLETED) {
                Log::error("Batch processing completed, but errored out: " . $e->getMessage());
            } else {
                $this->handleBatchFailure($batch);
            }
        }
    }

    /**
     * Fetch data for a specific batch
     */
    protected function fetchDataForBatch(DataMigrationBatch $batch): array
    {
        $decodedFilters = ObjectHelpers::toArray(json_decode($batch->api_filters));
        $filters = new Filters($decodedFilters);
        $resourceType = ResourceType::resolve($batch->resource_type);

        switch ($resourceType) {
            case ResourceType::CLIENT:
                $response = $this->dataExchangeService->getClientDataWithPagination($filters);
                return $this->extractClientsFromResponse($response);

            case ResourceType::SHALLOW_CASE:
                // SHALLOW_CASE uses SearchCase with pagination (same pattern as CLIENT)
                $response = $this->dataExchangeService->getCaseDataWithPagination($filters);
                return $this->extractCasesFromResponse($response);

            case ResourceType::SHALLOW_CLOSED_CASE:
                // SHALLOW_CLOSED_CASE uses SearchCase with END_DATE_TO filter and pagination
                $response = $this->dataExchangeService->getClosedCaseDataWithPagination($filters);
                return $this->extractCasesFromResponse($response);

            case ResourceType::CASE_CLIENT:
                throw new \Exception('Case clients not supported, yet.');

            case ResourceType::CASE:
                $response = $this->dataExchangeService->fetchFullCaseData($filters);
                return $this->extractCasesFromResponse($response);

            case ResourceType::CLOSED_CASE:
                $response = $this->dataExchangeService->fetchFullCaseData($filters);
                return $this->extractCasesFromResponse($response);

            case ResourceType::SESSION:
                return $this->fetchSessionsForMigratedCases($batch);

            default:
                throw new \InvalidArgumentException("Unknown resource type: {$resourceType->value}");
        }
    }

    /**
     * Store fetched data in local database
     */
    protected function storeData(DataMigrationBatch $batch, array $data): int
    {
        $storedCount = 0;
        $batchId = $batch->id;
        $migrationId = $batch->data_migration_id;
        $resourceType = ResourceType::resolve($batch->resource_type);

        foreach ($data as $item) {
            try {
                // Convert stdClass to array if needed
                $itemArray = is_object($item) ? json_decode(json_encode($item), true) : $item;

                switch ($resourceType) {
                    case ResourceType::CLIENT:
                        $this->storeClient($itemArray, $batchId, $migrationId);
                        break;
                    case ResourceType::SHALLOW_CASE:
                        $this->storeShallowCase($itemArray, $batchId);
                        break;
                    case ResourceType::SHALLOW_CLOSED_CASE:
                        // SHALLOW_CLOSED_CASE uses same storage as SHALLOW_CASE
                        $this->storeShallowCase($itemArray, $batchId);
                        break;
                    case ResourceType::CASE_CLIENT:
                        $this->storeClient($itemArray, $batchId, $migrationId);
                        break;
                    case ResourceType::CASE:
                        $this->storeCase($itemArray, $batchId, $migrationId);
                        break;
                    case ResourceType::CLOSED_CASE:
                        $this->storeCase($itemArray, $batchId, $migrationId);
                        break;
                    case ResourceType::SESSION:
                        $this->storeSession($itemArray, $batchId, $migrationId);
                        break;
                    default:
                        throw new \Exception('ResourceType can\'t be stored');
                }
                $storedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to store {$resourceType->value} item: " . $e->getMessage(), [
                    'id' => $batchId,
                    'resource_type' => $resourceType->value,
                    'item_data' => $itemArray ?? 'failed_to_convert',
                    'exception' => $e->getTraceAsString()
                ]);
                // Continue processing other items instead of failing the entire batch
            }
        }

        return $storedCount;
    }

    /**
     * Store a client record
     */
    protected function storeClient(array $clientData, int|string $batchId)
    {
        // Prepare residential address JSON from individual fields
        $residentialAddress = null;
        if (isset($clientData['ResidentialAddress']) && is_array($clientData['ResidentialAddress'])) {
            $residentialAddress = $clientData['ResidentialAddress'];
        } else {
            // Fallback for individual fields if they exist
            $addressFields = [];
            if (isset($clientData['suburb']) || isset($clientData['Suburb'])) {
                $addressFields['Suburb'] = $clientData['suburb'] ?? $clientData['Suburb'];
            }
            if (isset($clientData['state']) || isset($clientData['State'])) {
                $addressFields['State'] = $clientData['state'] ?? $clientData['State'];
            }
            if (isset($clientData['postal_code']) || isset($clientData['Postcode'])) {
                $addressFields['Postcode'] = $clientData['postal_code'] ?? $clientData['Postcode'];
            }
            if (!empty($addressFields)) {
                $residentialAddress = $addressFields;
            }
        }

        $res = MigratedClient::updateOrCreate(
            ['client_id' => $clientData['client_id'] ?? $clientData['ClientId']],
            [
                'slk' => $clientData['slk'] ?? $clientData['SLK'] ?? null,
                'consent_to_provide_details' => $clientData['consent_to_provide_details'] ?? $clientData['ConsentToProvideDetails'] ?? false,
                'consented_for_future_contacts' => $clientData['consented_for_future_contacts'] ?? $clientData['ConsentedForFutureContacts'] ?? false,
                'given_name' => $clientData['given_name'] ?? $clientData['GivenName'] ?? null,
                'family_name' => $clientData['family_name'] ?? $clientData['FamilyName'] ?? null,
                'is_using_psuedonym' => $clientData['is_using_psuedonym'] ?? $clientData['IsUsingPsuedonym'] ?? false,
                'birth_date' => $clientData['birth_date'] ?? $clientData['BirthDate'] ?? null,
                'is_birth_date_an_estimate' => $clientData['is_birth_date_an_estimate'] ?? $clientData['IsBirthDateAnEstimate'] ?? false,
                'gender_code' => $clientData['gender_code'] ?? $clientData['GenderCode'] ?? null,
                'gender_details' => $clientData['gender_details'] ?? $clientData['GenderDetails'] ?? null,
                'residential_address' => $residentialAddress,
                'country_of_birth_code' => $clientData['country_of_birth_code'] ?? $clientData['CountryOfBirthCode'] ?? null,
                'language_spoken_at_home_code' => $clientData['language_spoken_at_home_code'] ?? $clientData['LanguageSpokenAtHomeCode'] ?? null,
                'aboriginal_or_torres_strait_islander_origin_code' => $clientData['aboriginal_or_torres_strait_islander_origin_code'] ?? $clientData['AboriginalOrTorresStraitIslanderOriginCode'] ?? null,
                'has_disabilities' => $clientData['has_disabilities'] ?? $clientData['HasDisabilities'] ?? false,
                'api_response' => $clientData,
                'data_migration_batch_id' => $batchId,
            ]
        );
        return $res;
    }

    /**
     * Store shallow case record from SearchCase result
     */
    protected function storeShallowCase(array $caseData, string $batchId): void
    {
        $caseId = $this->extractCaseId($caseData);
        if (!$caseId) {
            throw new \Exception("Could not extract case ID from shallow case data");
        }

        MigratedShallowCase::updateOrCreate(
            ['case_id' => $caseId],
            [
                'outlet_name' => $caseData['outlet_name']
                    ?? $caseData['OutletName']
                    ?? null,
                'created_date_time' => $caseData['created_date_time']
                    ?? $caseData['CreatedDateTime']
                    ?? null,
                'client_attendance_profile_code' => $caseData['client_attendance_profile_code']
                    ?? $caseData['ClientAttendanceProfileCode']
                    ?? null,
                'api_response' => $caseData,
                'data_migration_batch_id' => $batchId,
            ]
        );
    }

    /**
     * Store a case record
     */
    protected function storeCase(array $caseData, string $batchId, int $migrationId): void
    {
        // Extract case ID from various possible locations
        $caseId = $this->extractCaseId($caseData);
        if (!$caseId) {
            throw new \Exception("Could not extract case ID from case data: " . json_encode($caseData));
        }

        // Extract client IDs array from various possible locations
        $clientIds = $this->extractClientIds($caseData);

        // Extract session IDs using robust nested extraction
        $sessions = $this->extractSessions($caseData);

        // Extract other fields with robust fallbacks
        $outletName = $caseData['outlet_name']
            ?? $caseData['OutletName']
            ?? data_get($caseData, 'CaseDetail.OutletName')
            ?? null;

        $outletActivityId = $caseData['outlet_activity_id']
            ?? $caseData['OutletActivityId']
            ?? data_get($caseData, 'CaseDetail.OutletActivityId')
            ?? 0;

        $totalNumberOfUnidentifiedClients = $caseData['total_number_of_unidentified_clients']
            ?? $caseData['TotalNumberOfUnidentifiedClients']
            ?? data_get($caseData, 'CaseDetail.TotalNumberOfUnidentifiedClients')
            ?? null;

        $clientAttendanceProfileCode = $caseData['client_attendance_profile_code']
            ?? $caseData['ClientAttendanceProfileCode']
            ?? data_get($caseData, 'CaseDetail.ClientAttendanceProfileCode')
            ?? null;

        $createdDateTime = $caseData['created_date_time']
            ?? $caseData['CreatedDateTime']
            ?? data_get($caseData, 'CaseDetail.CreatedDateTime')
            ?? null;

        $endDate = $caseData['end_date']
            ?? $caseData['EndDate']
            ?? data_get($caseData, 'CaseDetail.EndDate')
            ?? null;

        $exitReasonCode = $caseData['exit_reason_code']
            ?? $caseData['ExitReasonCode']
            ?? data_get($caseData, 'CaseDetail.ExitReasonCode')
            ?? null;

        $agBusinessTypeCode = $caseData['ag_business_type_code']
            ?? $caseData['AgBusinessTypeCode']
            ?? data_get($caseData, 'CaseDetail.AgBusinessTypeCode')
            ?? null;

        $programActivityName = $caseData['program_activity_name']
            ?? $caseData['ProgramActivityName']
            ?? data_get($caseData, 'CaseDetail.ProgramActivityName')
            ?? null;

        if (env('DETAILED_LOGGING'))
            Log::info("Storing case: {$caseId} with clients: " . json_encode($clientIds));

        MigratedCase::updateOrCreate(
            ['case_id' => $caseId],
            [
                'outlet_name' => $outletName,
                'client_ids' => $clientIds,
                'outlet_activity_id' => $outletActivityId,
                'total_number_of_unidentified_clients' => $totalNumberOfUnidentifiedClients,
                'client_attendance_profile_code' => $clientAttendanceProfileCode,
                'created_date_time' => $createdDateTime,
                'end_date' => $endDate,
                'exit_reason_code' => $exitReasonCode,
                'ag_business_type_code' => $agBusinessTypeCode,
                'program_activity_name' => $programActivityName,
                'sessions' => $sessions,
                'api_response' => $caseData,
                'data_migration_batch_id' => $batchId,
            ]
        );
    }

    /**
     * Extract case ID from various data structures
     */
    protected function extractCaseId(array $caseData): ?string
    {
        return $caseData['case_id']
            ?? $caseData['CaseId']
            ?? data_get($caseData, 'CaseDetail.CaseId')
            ?? null;
    }

    /**
     * Extract client ID from various data structures
     */
    protected function extractClientId(array $caseData): ?string
    {
        return $caseData['client_id']
            ?? $caseData['ClientId']
            ?? data_get($caseData, 'Clients.CaseClient.ClientId')
            ?? null;
    }

    /**
     * Extract client IDs array from various data structures
     */
    protected function extractClientIds(array $caseData): ?array
    {
        // Try to extract as array first
        if (isset($caseData['client_ids']) && is_array($caseData['client_ids'])) {
            return $caseData['client_ids'];
        }

        if (isset($caseData['ClientIds']) && is_array($caseData['ClientIds'])) {
            return $caseData['ClientIds'];
        }

        // Check for nested client data structures
        $clientsData = data_get($caseData, 'Clients');
        if ($clientsData && is_array($clientsData)) {
            $clientIds = [];

            // Handle various nested structures
            if (isset($clientsData['CaseClient'])) {
                if (is_array($clientsData['CaseClient']) && isset($clientsData['CaseClient'][0])) {
                    // Multiple clients
                    foreach ($clientsData['CaseClient'] as $client) {
                        if ($clientId = $client['ClientId'] ?? null) {
                            $clientIds[] = $clientId;
                        }
                    }
                } elseif (isset($clientsData['CaseClient']['ClientId'])) {
                    // Single client
                    $clientIds[] = $clientsData['CaseClient']['ClientId'];
                }
            }

            return !empty($clientIds) ? $clientIds : null;
        }

        // Fallback to single client ID as array
        $singleClientId = $this->extractClientId($caseData);
        return $singleClientId ? [$singleClientId] : null;
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
     * Store a session record
     */
    protected function storeSession(array $sessionData, string $batchId, int $migrationId): void
    {
        // Extract session date with fallback to current date if null (required field)
        $sessionDate = $sessionData['session_date']
            ?? $sessionData['SessionDate']
            ?? now()->toDateString();

        // Extract total number of unidentified clients with default 0 (required field)
        $totalNumberOfUnidentifiedClients = $sessionData['total_number_of_unidentified_clients']
            ?? $sessionData['TotalNumberOfUnidentifiedClients']
            ?? data_get($sessionData, 'SessionDetail.TotalNumberOfUnidentifiedClients')
            ?? 0;

        // Extract fees charged
        $feesCharged = $sessionData['fees_charged']
            ?? $sessionData['FeesCharged']
            ?? data_get($sessionData, 'SessionDetail.FeesCharged')
            ?? null;

        // Extract money/business/community education workshop code
        $workshopCode = $sessionData['money_business_community_education_workshop_code']
            ?? $sessionData['MoneyBusinessCommunityEducationWorkshopCode']
            ?? data_get($sessionData, 'SessionDetail.MoneyBusinessCommunityEducationWorkshopCode')
            ?? null;

        // Extract interpreter present flag
        $interpreterPresent = $sessionData['interpreter_present']
            ?? $sessionData['InterpreterPresent']
            ?? data_get($sessionData, 'SessionDetail.InterpreterPresent')
            ?? false;

        // Extract service setting code
        $serviceSettingCode = $sessionData['service_setting_code']
            ?? $sessionData['ServiceSettingCode']
            ?? data_get($sessionData, 'SessionDetail.ServiceSettingCode')
            ?? null;

        MigratedSession::updateOrCreate(
            ['session_id' => $sessionData['session_id'] ?? $sessionData['SessionId']],
            [
                'case_id' => $sessionData['case_id'] ?? $sessionData['CaseId'],
                'session_date' => $sessionDate,
                'service_type_id' => $sessionData['service_type_id'] ?? $sessionData['ServiceTypeId'] ?? 0,
                'total_number_of_unidentified_clients' => $totalNumberOfUnidentifiedClients,
                'fees_charged' => $feesCharged,
                'money_business_community_education_workshop_code' => $workshopCode,
                'interpreter_present' => $interpreterPresent,
                'service_setting_code' => $serviceSettingCode,
                'api_response' => $sessionData,
                'data_migration_batch_id' => $batchId,
            ]
        );
    }

    /**
     * Extract clients from API response
     */
    protected function extractClientsFromResponse($response): array
    {
        if (empty($response)) {
            return [];
        }

        // Handle different response structures
        if (is_array($response) && isset($response['data'])) {
            return is_array($response['data']) ? $response['data'] : [];
        }

        if (is_array($response) && isset($response[0])) {
            return $response;
        }

        // Check nested structures
        $possiblePaths = ['Clients.Client', 'Client', 'clients'];
        foreach ($possiblePaths as $path) {
            $result = data_get($response, $path);
            if (!empty($result)) {
                return is_array($result) && isset($result[0]) ? $result : [$result];
            }
        }

        return [];
    }

    /**
     * Extract cases from API response
     */
    protected function extractCasesFromResponse($response): array
    {
        if (empty($response)) {
            return [];
        }

        if (is_array($response) && isset($response['data'])) {
            return is_array($response['data']) ? $response['data'] : [];
        }

        if (is_array($response) && isset($response[0])) {
            return $response;
        }

        $possiblePaths = ['Cases.Case', 'Case', 'cases'];
        foreach ($possiblePaths as $path) {
            $result = data_get($response, $path);
            if (!empty($result)) {
                return is_array($result) && isset($result[0]) ? $result : [$result];
            }
        }

        return [];
    }

    /**
     * Extract sessions from API response
     */
    protected function extractSessionsFromResponse($response): array
    {
        if (empty($response)) {
            return [];
        }

        if (is_array($response) && isset($response['data'])) {
            return is_array($response['data']) ? $response['data'] : [];
        }

        if (is_array($response) && isset($response[0])) {
            return $response;
        }

        $possiblePaths = ['Sessions.Session', 'Session', 'sessions'];
        foreach ($possiblePaths as $path) {
            $result = data_get($response, $path);
            if (!empty($result)) {
                return is_array($result) && isset($result[0]) ? $result : [$result];
            }
        }

        return [];
    }

    /**
     * Fetch sessions for already migrated cases only
     * This solves the core API limitation problem by working with case-specific data
     */
    protected function fetchSessionsForMigratedCases(DataMigrationBatch $batch): array
    {
        $batchSize = $batch->page_size;
        $batchNumber = $batch->batch_number;

        // Get migrated cases for this migration, paginated for this batch
        $migratedCases = MigratedCase::where('data_migration_batch_id', $batch->data_migration_id)
            ->orderBy('case_id')
            ->skip(($batchNumber - 1) * $batchSize)
            ->take($batchSize)
            ->get();

        if ($migratedCases->isEmpty()) {
            if (env('DETAILED_LOGGING'))
                Log::info("No migrated cases found for session batch {$batch->batch_number}");
            return [];
        }

        $allSessions = [];

        foreach ($migratedCases as $migratedCase) {
            try {
                // Fetch sessions for this specific case
                $filters = new Filters(['case_id' => $migratedCase->case_id]);
                $response = $this->dataExchangeService->fetchFullSessionData($filters);

                // Extract sessions from response
                $caseSessions = $this->extractSessionsFromResponse($response);

                if (!empty($caseSessions)) {
                    $allSessions = array_merge($allSessions, $caseSessions);
                    if (env('DETAILED_LOGGING'))
                        Log::info("Found " . count($caseSessions) . " sessions for case {$migratedCase->case_id}");
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch sessions for case {$migratedCase->case_id}: " . $e->getMessage());
                // Continue with other cases instead of failing the entire batch
            }
        }

        if (env('DETAILED_LOGGING'))
            Log::info("Session batch {$batch->batch_number}: processed " . count($migratedCases) . " cases, found " . count($allSessions) . " total sessions");

        return $allSessions;
    }

    /**
     * Update migration progress
     */
    protected function updateMigrationProgress(DataMigration $migration): void
    {
        $failedBatches = $migration->batches()->where('status', DataMigrationBatchStatus::FAILED)->get();

        // Check if migration is complete
        $totalBatches = $migration->batches()->count();
        $completedOrFailedBatches = $migration->batches()
            ->whereIn('status', [DataMigrationBatchStatus::COMPLETED, DataMigrationBatchStatus::FAILED])
            ->count();

        if ($totalBatches > 0 && $completedOrFailedBatches >= $totalBatches) {
            $status = $failedBatches->count() > 0 ? DataMigrationStatus::FAILED : DataMigrationStatus::COMPLETED;

            $migration->update([
                'status' => $status,
                'completed_at' => now(),
            ]);

            if (env('DETAILED_LOGGING'))
                Log::info("Data migration {$migration->id} completed with status: {$status->value}");
        }
    }

    /**
     * Handle batch failure
     */
    protected function handleBatchFailure(DataMigrationBatch $batch): void
    {
        // Mark batch as failed
        $batch->update(['status' => DataMigrationBatchStatus::FAILED->value]);

        // Update migration with failure
        $migration = $batch->dataMigration;

        // Check if we should retry or continue with next batch
        $failedBatches = $migration->batches()->where(
            'status',
            DataMigrationBatchStatus::FAILED->value
        )->count();
        $totalBatches = $migration->batches()->count();

        // If too many failures, mark migration as failed
        if ($failedBatches > $totalBatches * 0.5) {
            $migration->onFail(new \Exception('Too many batch failures'));
        } else {
            // Continue with next batch
            $this->dispatchBatches($migration);
        }
    }

    /**
     * Get migration status for API
     */
    public function getMigrationStatus(DataMigration $migration): array
    {
        $migration->load('batches');

        return [
            'id' => $migration->id,
            'name' => $migration->name,
            'status' => $migration->status->value,
            'progress_percentage' => $migration->progress_percentage,
            'processed_items' => $migration->processed_items,
            'success_rate' => $migration->success_rate,
            'total_items' => $migration->total_items,
            'started_at' => $migration->started_at?->toISOString(),
            'completed_at' => $migration->completed_at?->toISOString(),
            'resource_type' => $migration->resource_type,
            'batches' => $migration->batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'batch_size' => $batch->batch_size,
                    'resource_type' => $batch->resource_type,
                    'status' => $batch->status->value,
                    'items_received' => $batch->items_received ?? 0,
                    'items_stored' => $batch->items_stored ?? 0,
                    'success_rate' => $batch->success_rate,
                    'started_at' => $batch->started_at?->toISOString(),
                    'completed_at' => $batch->completed_at?->toISOString()
                ];
            })
        ];
    }

    /**
     * Cancel a migration
     */
    public function cancelMigration(DataMigration $migration): void
    {
        $migration->update([
            'status' => DataMigrationStatus::CANCELLED,
            'completed_at' => now()
        ]);

        // Cancel pending batches (mark as failed)
        $migration->batches()
            ->where('status', DataMigrationBatchStatus::PENDING)
            ->update(['status' => DataMigrationBatchStatus::FAILED]);
    }

    /**
     * Retry failed batches
     */
    public function retryFailedBatches(DataMigration $migration): void
    {
        $failedBatches = $migration->failedBatches();

        foreach ($failedBatches as $batch)
            $batch->update([
                'status' => DataMigrationBatchStatus::PENDING,
                'started_at' => null,
                'completed_at' => null
            ]);

        if ($failedBatches->count() > 0) {
            $migration->update(['status' => DataMigrationStatus::IN_PROGRESS]);
            $this->dispatchBatches($migration);
        }
    }
}
