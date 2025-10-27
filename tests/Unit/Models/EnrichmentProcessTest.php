<?php

namespace Tests\Unit\Models;

use App\Models\EnrichmentBatch;
use App\Models\EnrichmentProcess;
use App\Enums\ResourceType;
use Tests\TestCase;

class EnrichmentProcessTest extends TestCase
{
    public function test_enrichment_process_can_be_created()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $this->assertNotNull($process->id);
        $this->assertEquals(ResourceType::CASE, $process->resource_type);
        $this->assertEquals('PENDING', $process->status);
        $this->assertEquals(100, $process->total_items);
    }

    public function test_enrichment_process_has_batches()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 300,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'status' => 'PENDING',
            'item_ids' => [4, 5, 6],
            'batch_size' => 100,
        ]);

        $this->assertEquals(2, $process->batches()->count());
    }

    public function test_processed_items_attribute()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 300,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
            'items_processed' => 3,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'status' => 'COMPLETED',
            'item_ids' => [4, 5, 6],
            'batch_size' => 3,
            'items_processed' => 3,
        ]);

        // Reload to refresh relationships
        $process = $process->fresh();

        $this->assertEquals(6, $process->processed_items);
    }

    public function test_progress_percentage_attribute()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 50,
            'items_processed' => 50,
        ]);

        // Reload to refresh relationships
        $process = $process->fresh();

        $this->assertEquals(50.0, $process->progress_percentage);
    }

    public function test_failed_items_attribute()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 50,
            'items_processed' => 48,
            'items_failed' => 2,
        ]);

        // Reload to refresh relationships
        $process = $process->fresh();

        $this->assertEquals(2, $process->failed_items);
    }

    public function test_success_rate_attribute()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 50,
            'items_processed' => 40,
            'items_failed' => 10,
        ]);

        // Reload to refresh relationships
        $process = $process->fresh();

        $this->assertEquals(80.0, $process->success_rate);
    }

    public function test_pending_batches_collection()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 300,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'status' => 'IN_PROGRESS',
            'item_ids' => [4, 5, 6],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 3,
            'status' => 'COMPLETED',
            'item_ids' => [7, 8, 9],
            'batch_size' => 100,
            'items_processed' => 100,
        ]);

        $pending = $process->pendingBatches();

        $this->assertEquals(1, $pending->count());
        $this->assertEquals(1, $pending->first()->batch_number);
    }

    public function test_incomplete_batches_collection()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 300,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'status' => 'IN_PROGRESS',
            'item_ids' => [4, 5, 6],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 3,
            'status' => 'COMPLETED',
            'item_ids' => [7, 8, 9],
            'batch_size' => 100,
            'items_processed' => 100,
        ]);

        $incomplete = $process->incompleteBatches();

        $this->assertEquals(2, $incomplete->count());
    }

    public function test_failed_batches_collection()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 300,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 100,
        ]);

        EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'status' => 'FAILED',
            'item_ids' => [4, 5, 6],
            'batch_size' => 100,
            'error_message' => 'API timeout',
        ]);

        $failed = $process->failedBatches();

        $this->assertEquals(1, $failed->count());
        $this->assertEquals('FAILED', $failed->first()->status);
    }

    public function test_scope_active()
    {
        EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        EnrichmentProcess::create([
            'resource_type' => ResourceType::SESSION,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'COMPLETED',
            'total_items' => 100,
        ]);

        $active = EnrichmentProcess::active()->get();

        $this->assertEquals(2, $active->count());
    }

    public function test_scope_completed()
    {
        EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        EnrichmentProcess::create([
            'resource_type' => ResourceType::SESSION,
            'status' => 'COMPLETED',
            'total_items' => 100,
        ]);

        EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'COMPLETED',
            'total_items' => 100,
        ]);

        $completed = EnrichmentProcess::completed()->get();

        $this->assertEquals(2, $completed->count());
    }

    public function test_on_fail_sets_status_and_timestamp()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $exception = new \Exception('Test failure');
        $process->onFail($exception);

        $this->assertEquals('FAILED', $process->status);
        $this->assertNotNull($process->completed_at);
    }
}
