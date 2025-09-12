<?php

namespace App\Services;

use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use App\Models\MigratedClient;
use App\Models\MigratedCase;
use App\Models\MigratedSession;
use App\Services\DataExchangeService;
use App\Jobs\ProcessDataMigrationBatch;
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
            $resourceTotal = $this->getTotalItemsForResource($resourceType, $filters);
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
            'status' => 'pending'
        ]);
    }

    /**
     * Start a data migration by creating batches and dispatching jobs
     */
    public function startMigration(DataMigration $migration): void
    {
        DB::transaction(function () use ($migration) {
            $migration->update([
                'status' => 'in_progress',
                'started_at' => now()
            ]);

            foreach ($migration->resource_types as $resourceType) {
                $this->createBatchesForResource($migration, $resourceType);
            }
        });

        // Dispatch first batch of each resource type
        $this->dispatchNextBatches($migration);
    }

    /**
     * Create batches for a specific resource type
     */
    protected function createBatchesForResource(DataMigration $migration, string $resourceType): void
    {
        // Get total count to determine number of batches needed
        $totalItems = $this->getTotalItemsForResource($resourceType, $migration->filters);

        if ($totalItems === 0) {
            Log::info("No items found for resource type: {$resourceType}");
            return;
        }

        $totalBatches = ceil($totalItems / $migration->batch_size);

        Log::info("Creating {$totalBatches} batches for {$resourceType} ({$totalItems} total items)");

        for ($batchNumber = 1; $batchNumber <= $totalBatches; $batchNumber++) {
            $pageIndex = $batchNumber; // DSS API uses 1-based indexing

            DataMigrationBatch::create([
                'batch_id' => Str::uuid(),
                'data_migration_id' => $migration->id,
                'resource_type' => $resourceType,
                'batch_number' => $batchNumber,
                'page_index' => $pageIndex,
                'page_size' => $migration->batch_size,
                'status' => 'pending',
                'api_filters' => array_merge($migration->filters, [
                    'page_index' => $pageIndex,
                    'page_size' => $migration->batch_size
                ])
            ]);
        }

        Log::info("Created {$totalBatches} batches for {$resourceType} (total items already set during migration creation)");
    }

    /**
     * Calculate total items to be processed for a resource type using Search APIs
     */
    protected function getTotalItemsForResource(string $resourceType, array $filters): int
    {
        try {
            // Use Search APIs with PageSize=1 to get accurate TotalCount metadata
            $searchFilters = array_merge($filters, [
                'page_index' => 1,
                'page_size' => 1 // Just get one item to extract TotalCount from metadata
            ]);

            Log::info("Getting total items count for {$resourceType}", ['filters' => $searchFilters]);

            switch ($resourceType) {
                case 'clients':
                    // Use SearchClient API directly for accurate count
                    $response = $this->dataExchangeService->getClientData($searchFilters);
                    break;
                case 'cases':
                    // Use SearchCase API directly for accurate count
                    $response = $this->dataExchangeService->getCaseData($searchFilters);
                    break;
                case 'sessions':
                    // Sessions require case_id filtering via SearchCase
                    $response = $this->dataExchangeService->getSessionData($searchFilters);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown resource type: {$resourceType}");
            }

            // Extract TotalCount from SOAP response metadata
            $totalCount = $this->extractTotalCountFromResponse($response);

            if ($totalCount > 0) {
                Log::info("Found {$totalCount} total items for {$resourceType}");
                return $totalCount;
            }

            // Fallback: estimate based on reasonable assumptions
            Log::warning("Could not determine total items for {$resourceType}, using estimate");
            return 1000; // Conservative estimate

        } catch (\Exception $e) {
            Log::error("Failed to get total items for {$resourceType}: " . $e->getMessage());
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
     * Dispatch next pending batches for processing
     */
    public function dispatchNextBatches(DataMigration $migration, int $limit = 3): void
    {
        $pendingBatches = $migration->batches()
            ->where('status', 'pending')
            ->orderBy('resource_type')
            ->orderBy('batch_number')
            ->limit($limit)
            ->get();

        foreach ($pendingBatches as $batch) {
            ProcessDataMigrationBatch::dispatch($batch);
            $batch->update(['status' => 'processing']);
        }
    }

    /**
     * Process a single batch
     */
    public function processBatch(DataMigrationBatch $batch): void
    {
        try {
            $batch->update([
                'status' => 'processing',
                'started_at' => now()
            ]);

            Log::info("Processing batch {$batch->batch_number} for {$batch->resource_type}");

            $data = $this->fetchDataForBatch($batch);
            $storedCount = $this->storeData($batch, $data);

            $batch->update([
                'status' => 'completed',
                'items_received' => count($data),
                'items_stored' => $storedCount,
                'completed_at' => now()
            ]);

            // Update migration progress
            $this->updateMigrationProgress($batch->dataMigration);

            // Dispatch next batch if available
            $this->dispatchNextBatches($batch->dataMigration, 1);
        } catch (\Exception $e) {
            Log::error("Batch processing failed: " . $e->getMessage());

            $batch->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            $this->handleBatchFailure($batch);
        }
    }

    /**
     * Fetch data for a specific batch
     */
    protected function fetchDataForBatch(DataMigrationBatch $batch): array
    {
        $filters = $batch->api_filters;

        switch ($batch->resource_type) {
            case 'clients':
                $response = $this->dataExchangeService->getClientDataWithPagination($filters);
                return $this->extractClientsFromResponse($response);

            case 'cases':
                $response = $this->dataExchangeService->fetchFullCaseData($filters);
                return $this->extractCasesFromResponse($response);

            case 'sessions':
                $response = $this->dataExchangeService->fetchFullSessionData($filters);
                return $this->extractSessionsFromResponse($response);

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
        $batchId = $batch->batch_id;

        foreach ($data as $item) {
            try {
                // Convert stdClass to array if needed
                $itemArray = is_object($item) ? json_decode(json_encode($item), true) : $item;

                switch ($batch->resource_type) {
                    case 'clients':
                        $this->storeClient($itemArray, $batchId);
                        break;
                    case 'cases':
                        $this->storeCase($itemArray, $batchId);
                        break;
                    case 'sessions':
                        $this->storeSession($itemArray, $batchId);
                        break;
                }
                $storedCount++;
            } catch (\Exception $e) {
                Log::warning("Failed to store {$batch->resource_type} item: " . $e->getMessage());
            }
        }

        return $storedCount;
    }

    /**
     * Store a client record
     */
    protected function storeClient(array $clientData, string $batchId)
    {
        $res = MigratedClient::updateOrCreate(
            ['client_id' => $clientData['client_id'] ?? $clientData['ClientId']],
            [
                'first_name' => $clientData['first_name'] ?? $clientData['GivenName'] ?? null,
                'last_name' => $clientData['last_name'] ?? $clientData['FamilyName'] ?? null,
                'date_of_birth' => $clientData['date_of_birth'] ?? $clientData['BirthDate'] ?? null,
                'gender' => $clientData['gender'] ?? $clientData['GenderCode'] ?? null,
                'suburb' => $clientData['suburb'] ?? $clientData['ResidentialAddress']['Suburb'] ?? null,
                'state' => $clientData['state'] ?? $clientData['ResidentialAddress']['State'] ?? null,
                'postal_code' => $clientData['postal_code'] ?? $clientData['ResidentialAddress']['Postcode'] ?? null,
                'api_response' => $clientData,
                'migration_batch_id' => $batchId,
                'migrated_at' => now()
            ]
        );
        return $res;
    }

    /**
     * Store a case record
     */
    protected function storeCase(array $caseData, string $batchId): void
    {
        MigratedCase::updateOrCreate(
            ['case_id' => $caseData['case_id'] ?? $caseData['CaseId']],
            [
                'client_id' => $caseData['client_id'] ?? $caseData['ClientId'],
                'outlet_activity_id' => $caseData['outlet_activity_id'] ?? $caseData['OutletActivityId'] ?? 0,
                'referral_source_code' => $caseData['referral_source_code'] ?? $caseData['ReferralSourceCode'] ?? '',
                'reasons_for_assistance' => $caseData['reasons_for_assistance'] ?? $caseData['ReasonsForAssistance'] ?? [],
                'api_response' => $caseData,
                'migration_batch_id' => $batchId,
                'migrated_at' => now()
            ]
        );
    }

    /**
     * Store a session record
     */
    protected function storeSession(array $sessionData, string $batchId): void
    {
        MigratedSession::updateOrCreate(
            ['session_id' => $sessionData['session_id'] ?? $sessionData['SessionId']],
            [
                'case_id' => $sessionData['case_id'] ?? $sessionData['CaseId'],
                'service_type_id' => $sessionData['service_type_id'] ?? $sessionData['ServiceTypeId'] ?? 0,
                'session_date' => $sessionData['session_date'] ?? $sessionData['SessionDate'] ?? null,
                'duration_minutes' => $sessionData['duration_minutes'] ?? $sessionData['DurationMinutes'] ?? 0,
                'location' => $sessionData['location'] ?? $sessionData['Location'] ?? null,
                'api_response' => $sessionData,
                'migration_batch_id' => $batchId,
                'migrated_at' => now()
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
     * Update migration progress
     */
    protected function updateMigrationProgress(DataMigration $migration): void
    {
        $completedBatches = $migration->batches()->where('status', 'completed')->get();
        $failedBatches = $migration->batches()->where('status', 'failed')->get();

        $processedItems = $completedBatches->sum('items_stored');
        $successfulItems = $processedItems; // Items that were successfully stored
        $failedItems = $failedBatches->sum('items_received');

        $migration->update([
            'processed_items' => $processedItems + $failedItems,
            'successful_items' => $successfulItems,
            'failed_items' => $failedItems
        ]);

        // Check if migration is complete
        $totalBatches = $migration->batches()->count();
        $completedOrFailedBatches = $migration->batches()
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        if ($totalBatches > 0 && $completedOrFailedBatches >= $totalBatches) {
            $status = $failedBatches->count() > 0 ? 'completed' : 'completed';

            $migration->update([
                'status' => $status,
                'completed_at' => now(),
                'summary' => $this->generateMigrationSummary($migration)
            ]);

            Log::info("Data migration {$migration->id} completed with status: {$status}");
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
        $migration->increment('failed_items', $batch->items_requested);

        // Check if we should retry or continue with next batch
        $failedBatches = $migration->batches()->where('status', 'failed')->count();
        $totalBatches = $migration->batches()->count();

        // If too many failures, mark migration as failed
        if ($failedBatches > $totalBatches * 0.5) {
            $migration->update([
                'status' => 'failed',
                'error_message' => 'Too many batch failures',
                'completed_at' => now()
            ]);
        } else {
            // Continue with next batch
            $this->dispatchNextBatches($migration, 1);
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
            'status' => $migration->status,
            'progress_percentage' => $migration->progress_percentage,
            'success_rate' => $migration->success_rate,
            'total_items' => $migration->total_items,
            'processed_items' => $migration->processed_items,
            'successful_items' => $migration->successful_items,
            'failed_items' => $migration->failed_items,
            'started_at' => $migration->started_at?->toISOString(),
            'completed_at' => $migration->completed_at?->toISOString(),
            'resource_types' => $migration->resource_types,
            'batches' => $migration->batches->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'resource_type' => $batch->resource_type,
                    'status' => $batch->status,
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
            'status' => 'cancelled',
            'completed_at' => now()
        ]);

        // Cancel pending batches
        $migration->batches()
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);
    }

    /**
     * Retry failed batches
     */
    public function retryFailedBatches(DataMigration $migration): void
    {
        $failedBatches = $migration->batches()->where('status', 'failed')->get();

        foreach ($failedBatches as $batch) {
            $batch->update([
                'status' => 'pending',
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null
            ]);
        }

        if ($failedBatches->count() > 0) {
            $migration->update(['status' => 'in_progress']);
            $this->dispatchNextBatches($migration);
        }
    }
}
