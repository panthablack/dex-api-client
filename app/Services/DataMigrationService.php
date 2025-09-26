<?php

namespace App\Services;

use App\Enums\DataMigrationStatus;
use App\Enums\DataMigrationBatchStatus;
use App\Enums\ResourceType;
use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use App\Jobs\ProcessDataMigrationBatch;
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
        // Calculate total items upfront using the fixed getTotalItemsForResource method
        $totalItems = 0;
        $filters = $data['filters'] ?? [];

        foreach ($data['resource_types'] as $resourceType) {
            $resolvedType = ResourceType::resolve($resourceType);
            $resourceTotal = $this->getTotalItemsForResource($resolvedType, $filters);
            $totalItems += $resourceTotal;
            Log::info("Found {$resourceTotal} items for {$resourceType}");
        }

        Log::info("Total items to migrate: {$totalItems}");

        return DataMigration::create([
            'name' => $data['name'],
            'resource_types' => $data['resource_types'],
            'filters' => $data['filters'] ?? [],
            'batch_size' => $data['batch_size'] ?? 100,
            'total_items' => $totalItems,  // Set total items immediately
            'status' => DataMigrationStatus::PENDING
        ]);
    }

    /**
     * Start a data migration by creating batches and dispatching jobs
     */
    public function startMigration(DataMigration $migration): void
    {
        DB::transaction(function () use ($migration) {
            $migration->update([
                'status' => DataMigrationStatus::IN_PROGRESS,
                'started_at' => now()
            ]);

            // Create batches for all resource types, but respect dependencies
            $orderedResourceTypes = $this->getOrderedResourceTypes($migration->resource_types);
            foreach ($orderedResourceTypes as $resourceType) {
                $resolvedType = ResourceType::resolve($resourceType);
                $this->createBatchesForResource($migration, $resolvedType);
            }
        });

        // Refresh the migration to ensure we have the latest batch data
        $migration->refresh();

        // Dispatch first batch of independent resource types only
        Log::info("Starting to dispatch independent batches for migration {$migration->id}");

        // get independent batches
        $independentBatches = $this->getIndependentBatches($migration);

        // if independent batches are found incomplete, process them
        if (!empty($independentBatches)) {
            $this->dispatchIndependentBatches($migration);
        } else {
            // else get dependent batches
            $dependentBatches = $this->getDependentBatches($migration);
            if (!empty($dependentBatches)) {
                // if dependent batches are found incomplete, process them
                $this->dispatchDependentBatches($migration);
            } else {
                // else, set migration status completed
                $migration->update(['status' => DataMigrationStatus::COMPLETED]);
            }
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
        // Get total count to determine number of batches needed
        $totalItems = $this->getTotalItemsForResource($resourceType, $migration->filters);

        if ($totalItems === 0) {
            Log::info("No items found for resource type: {$resourceType->value}");
            return;
        }

        $totalBatches = ceil($totalItems / $migration->batch_size);

        Log::info("Creating {$totalBatches} batches for {$resourceType->value} ({$totalItems} total items)");

        for ($batchNumber = 1; $batchNumber <= $totalBatches; $batchNumber++) {
            $pageIndex = $batchNumber; // DSS API uses 1-based indexing

            DataMigrationBatch::create([
                'data_migration_id' => $migration->id,
                'resource_type' => $resourceType,
                'batch_number' => $batchNumber,
                'page_index' => $pageIndex,
                'page_size' => $migration->batch_size,
                'status' => DataMigrationBatchStatus::PENDING,
                'api_filters' => array_merge($migration->filters, [
                    'page_index' => $pageIndex,
                    'page_size' => $migration->batch_size
                ])
            ]);
        }

        Log::info("Created {$totalBatches} batches for {$resourceType->value} (total items already set during migration creation)");
    }

    /**
     * Calculate total items to be processed for a resource type using Search APIs
     */
    protected function getTotalItemsForResource(ResourceType $resourceType, array $filters): int
    {
        try {
            // Use Search APIs with PageSize=1 to get accurate TotalCount metadata
            $searchFilters = array_merge($filters, [
                'page_index' => 1,
                'page_size' => 1 // Just get one item to extract TotalCount from metadata
            ]);

            Log::info("Getting total items count for {$resourceType->value}", ['filters' => $searchFilters]);

            switch ($resourceType) {
                case ResourceType::CLIENT:
                    // Use SearchClient API directly for accurate count
                    $response = $this->dataExchangeService->getClientData($searchFilters);
                    break;
                case ResourceType::CASE:
                    // Use SearchCase API directly for accurate count
                    $response = $this->dataExchangeService->getCaseData($searchFilters);
                    break;
                case ResourceType::SESSION:
                    // Sessions require case_id filtering via SearchCase
                    $response = $this->dataExchangeService->getSessionData($searchFilters);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown resource type: {$resourceType->value}");
            }

            // Extract TotalCount from SOAP response metadata
            $totalCount = $this->extractTotalCountFromResponse($response);

            if ($totalCount > 0) {
                Log::info("Found {$totalCount} total items for {$resourceType->value}");
                return $totalCount;
            }

            // Fallback: estimate based on reasonable assumptions
            Log::warning("Could not determine total items for {$resourceType->value}, using estimate");
            return 1000; // Conservative estimate

        } catch (\Exception $e) {
            Log::error("Failed to get total items for {$resourceType->value}: " . $e->getMessage());
            return 0;
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
     * Get resource types ordered by dependencies
     */
    protected function getOrderedResourceTypes(array $resourceTypes): array
    {
        $dependencies = [
            ResourceType::CLIENT->value => [],
            ResourceType::CASE->value => [],
            ResourceType::SESSION->value => [ResourceType::CASE] // Sessions depend on cases
        ];

        $ordered = [];
        $processed = [];

        // Function to add a resource type and its dependencies
        $addResourceType = function ($resourceType) use (&$addResourceType, $dependencies, &$ordered, &$processed, $resourceTypes) {
            if (in_array($resourceType, $processed) || !in_array($resourceType, $resourceTypes)) {
                return;
            }

            // Add dependencies first
            foreach ($dependencies[$resourceType] ?? [] as $dependency) {
                $addResourceType($dependency);
            }

            $ordered[] = $resourceType;
            $processed[] = $resourceType;
        };

        // Process all requested resource types
        foreach ($resourceTypes as $resourceType) {
            $addResourceType($resourceType);
        }

        return $ordered;
    }

    /**
     * Dispatch batches for independent resource types (no dependencies)
     */
    protected function dispatchDependentBatches(DataMigration $migration, int $limit = 3): void
    {
        $dependentTypes = [ResourceType::SESSION => ResourceType::CASE];

        Log::debug("Dispatching batches for " . implode(', ', $dependentTypes));

        // $pendingBatches = $migration->batches()
        //     ->whereIn('resource_type', $independentTypes)
        //     ->where('status', 'pending')
        //     ->orderBy('resource_type')
        //     ->orderBy('batch_number')
        //     ->limit($limit)
        //     ->get();

        // Log::info("Found {$pendingBatches->count()} pending batches for independent types: " . implode(', ', $independentTypes));

        // foreach ($pendingBatches as $batch) {
        //     Log::info("Dispatching independent batch: {$batch->resource_type} batch {$batch->batch_number} (ID: {$batch->id})");

        //     try {
        //         // For debugging, try synchronous processing if dispatch fails
        //         ProcessDataMigrationBatch::dispatch($batch);
        //         $batch->update(['status' => 'processing']);
        //         Log::info("Successfully dispatched and marked batch {$batch->id} as processing");
        //     } catch (\Exception $e) {
        //         Log::error("Failed to dispatch batch {$batch->id}: " . $e->getMessage());
        //         Log::info("Attempting synchronous processing as fallback for batch {$batch->id}");

        //         try {
        //             $this->processBatch($batch);
        //             Log::info("Successfully processed batch {$batch->id} synchronously");
        //         } catch (\Exception $syncError) {
        //             Log::error("Synchronous processing also failed for batch {$batch->id}: " . $syncError->getMessage());
        //         }
        //     }
        // }

        // if (count($pendingBatches) === 0) {
        //     Log::warning("No independent batches found to dispatch for migration {$migration->id}. Available resource types in migration: " . implode(', ', $migration->resource_types));
        // }
    }

    /**
     * Dispatch batches for independent resource types (no dependencies)
     */
    protected function dispatchIndependentBatches(DataMigration $migration, int $limit = 3): void
    {
        $independentTypes = [ResourceType::CLIENT->value, ResourceType::CASE->value];

        Log::debug("Dispatching batches for " . implode(', ', $independentTypes));

        $pendingBatches = $migration->batches()
            ->whereIn('resource_type', $independentTypes)
            ->where('status', 'pending')
            ->orderBy('resource_type')
            ->orderBy('batch_number')
            ->limit($limit)
            ->get();

        Log::info("Found {$pendingBatches->count()} pending batches for independent types: " . implode(', ', $independentTypes));

        foreach ($pendingBatches as $batch) {
            Log::info("Dispatching independent batch: {$batch->resource_type} batch {$batch->batch_number} (ID: {$batch->id})");

            try {
                // For debugging, try synchronous processing if dispatch fails
                ProcessDataMigrationBatch::dispatch($batch);
                $batch->update(['status' => DataMigrationBatchStatus::IN_PROGRESS]);
                Log::info("Successfully dispatched and marked batch {$batch->id} as processing");
            } catch (\Exception $e) {
                Log::error("Failed to dispatch batch {$batch->id}: " . $e->getMessage());
                $batch->failed($e);
            }
        }

        if (count($pendingBatches) === 0) {
            Log::warning("No independent batches found to dispatch for migration {$migration->id}. Available resource types in migration: " . implode(', ', $migration->resource_types));
        }
    }

    /**
     * Check if a resource type can start processing (dependencies completed)
     */
    protected function canProcessResourceType(DataMigration $migration, string $resourceType): bool
    {
        $dependencies = [
            ResourceType::CLIENT->value => [],
            ResourceType::CASE->value => [],
            ResourceType::SESSION->value => [ResourceType::CASE] // Sessions depend on cases
        ];

        $requiredDependencies = $dependencies[$resourceType] ?? [];

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
        Log::info("Restarting migration {$migration->id}");

        $migration->update([
            'status' => DataMigrationStatus::IN_PROGRESS,
            'started_at' => now()
        ]);

        // *****************************************************************************************
        // TODO: Steps below
        // get independent batches
        // get dependent batches
        // if independent batches are found incomplete, process them
        // else, if dependent batches are found incomplete, process them
        // else, set migration status completed and return
        // *****************************************************************************************


        // Check if there are any pending batches for independent resource types
        // $this->dispatchIndependentBatches($migration);

        // Also check if any dependent batches can now be dispatched
        // $this->dispatchNextBatches($migration);
    }

    /**
     * Dispatch next pending batches for processing (respecting dependencies)
     */
    public function dispatchNextBatches(DataMigration $migration, int $limit = 1): void
    {
        $dispatchedCount = 0;

        // Get all pending batches ordered by dependency priority
        $allPendingBatches = $migration->batches()
            ->where('status', 'pending')
            ->orderBy('resource_type')
            ->orderBy('batch_number')
            ->get();

        foreach ($allPendingBatches as $batch) {
            if ($dispatchedCount >= $limit) {
                break;
            }

            // Check if this resource type can be processed (dependencies met)
            if ($this->canProcessResourceType($migration, $batch->resource_type)) {
                ProcessDataMigrationBatch::dispatch($batch);
                $batch->update(['status' => DataMigrationBatchStatus::IN_PROGRESS]);
                $dispatchedCount++;

                Log::info("Dispatched {$batch->resource_type} batch {$batch->batch_number} (dependencies satisfied)");
            } else {
                Log::info("Waiting for dependencies: {$batch->resource_type} batch {$batch->batch_number}");
            }
        }

        if ($dispatchedCount === 0) {
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

            Log::info("Processing batch {$batch->batch_number} for {$batch->resource_type}");

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
            } else {
                $status = DataMigrationBatchStatus::COMPLETED;
            }

            $batch->update([
                'status' => $status,
                'items_received' => $receivedCount,
                'items_stored' => $storedCount,
                'error_message' => $errorMessage,
                'completed_at' => now()
            ]);

            // Update migration progress
            $this->updateMigrationProgress($batch->dataMigration);

            // Dispatch next batch if available
            $this->dispatchNextBatches($batch->dataMigration);
        } catch (\Exception $e) {
            Log::error("Batch processing failed: " . $e->getMessage());
            $batch->onFail($e);
            $this->handleBatchFailure($batch);
        }
    }

    /**
     * Fetch data for a specific batch
     */
    protected function fetchDataForBatch(DataMigrationBatch $batch): array
    {
        $filters = $batch->api_filters;
        $resolvedType = ResourceType::resolve($batch->resource_type);

        switch ($resolvedType) {
            case ResourceType::CLIENT:
                $response = $this->dataExchangeService->getClientDataWithPagination($filters);
                return $this->extractClientsFromResponse($response);

            case ResourceType::CASE:
                $response = $this->dataExchangeService->fetchFullCaseData($filters);
                return $this->extractCasesFromResponse($response);

            case ResourceType::SESSION:
                return $this->fetchSessionsForMigratedCases($batch);

            default:
                throw new \InvalidArgumentException("Unknown resource type: {$batch->resource_type}");
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
                    case ResourceType::CASE:
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
                Log::error("Failed to store {$batch->resource_type} item: " . $e->getMessage(), [
                    'id' => $batchId,
                    'resource_type' => $batch->resource_type,
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
            Log::info("No migrated cases found for session batch {$batch->batch_number}");
            return [];
        }

        $allSessions = [];

        foreach ($migratedCases as $migratedCase) {
            try {
                // Fetch sessions for this specific case
                $filters = ['case_id' => $migratedCase->case_id];
                $response = $this->dataExchangeService->fetchFullSessionData($filters);

                // Extract sessions from response
                $caseSessions = $this->extractSessionsFromResponse($response);

                if (!empty($caseSessions)) {
                    $allSessions = array_merge($allSessions, $caseSessions);
                    Log::info("Found " . count($caseSessions) . " sessions for case {$migratedCase->case_id}");
                }
            } catch (\Exception $e) {
                Log::warning("Failed to fetch sessions for case {$migratedCase->case_id}: " . $e->getMessage());
                // Continue with other cases instead of failing the entire batch
            }
        }

        Log::info("Session batch {$batch->batch_number}: processed " . count($migratedCases) . " cases, found " . count($allSessions) . " total sessions");

        return $allSessions;
    }

    /**
     * Update migration progress
     */
    protected function updateMigrationProgress(DataMigration $migration): void
    {
        $failedBatches = $migration->batches()->where('status', 'failed')->get();

        // Check if migration is complete
        $totalBatches = $migration->batches()->count();
        $completedOrFailedBatches = $migration->batches()
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        if ($totalBatches > 0 && $completedOrFailedBatches >= $totalBatches) {
            $status = $failedBatches->count() > 0 ? DataMigrationStatus::FAILED : DataMigrationStatus::COMPLETED;

            $migration->update([
                'status' => $status,
                'completed_at' => now(),
                'summary' => $this->generateMigrationSummary($migration)
            ]);

            Log::info("Data migration {$migration->id} completed with status: {$status->value}");
        }
    }

    /**
     * Generate migration summary
     */
    protected function generateMigrationSummary(DataMigration $migration): array
    {
        $summary = [
            'total_batches' => $migration->batches()->count(),
            'completed_batches' => $migration->batches()->where('status', 'completed')->count(),
            'failed_batches' => $migration->batches()->where('status', 'failed')->count(),
            'resources' => []
        ];

        foreach ($migration->resource_types as $resourceType) {
            $batches = $migration->batches()->where('resource_type', $resourceType);

            $summary['resources'][$resourceType] = [
                'total_batches' => $batches->count(),
                'completed_batches' => $batches->where('status', 'completed')->count(),
                'failed_batches' => $batches->where('status', 'failed')->count(),
                'items_migrated' => $batches->where('status', 'completed')->sum('items_stored')
            ];
        }

        return $summary;
    }

    /**
     * Handle batch failure
     */
    protected function handleBatchFailure(DataMigrationBatch $batch): void
    {
        // Update migration with failure
        $migration = $batch->dataMigration;

        // Check if we should retry or continue with next batch
        $failedBatches = $migration->batches()->where('status', 'failed')->count();
        $totalBatches = $migration->batches()->count();

        // If too many failures, mark migration as failed
        if ($failedBatches > $totalBatches * 0.5) {
            $migration->onFail(new \Exception('Too many batch failures'));
        } else {
            // Continue with next batch
            $this->dispatchNextBatches($migration);
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
            'resource_types' => $migration->resource_types,
            'batches' => $migration->batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'resource_type' => $batch->resource_type,
                    'status' => $batch->status->value,
                    'items_requested' => $batch->items_requested,
                    'items_received' => $batch->items_received ?? 0,
                    'items_stored' => $batch->items_stored ?? 0,
                    'success_rate' => $batch->success_rate,
                    'error_message' => $batch->error_message,
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

        // Cancel pending batches
        $migration->batches()
            ->where('status', 'pending')
            ->update(['status' => DataMigrationStatus::CANCELLED]);
    }

    /**
     * Retry failed batches
     */
    public function retryFailedBatches(DataMigration $migration): void
    {
        $failedBatches = $migration->batches()->where('status', 'failed')->get();

        foreach ($failedBatches as $batch) {
            $batch->update([
                'status' => DataMigrationBatchStatus::PENDING,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ]);
        }

        if ($failedBatches->count() > 0) {
            $migration->update(['status' => DataMigrationStatus::IN_PROGRESS]);
            $this->dispatchNextBatches($migration);
        }
    }
}
