<?php

namespace Tests\Unit\Models;

use App\Models\EnrichmentBatch;
use App\Models\EnrichmentProcess;
use App\Enums\ResourceType;
use Tests\TestCase;

class EnrichmentBatchTest extends TestCase
{
    public function test_enrichment_batch_can_be_created()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertNotNull($batch->id);
        $this->assertEquals(1, $batch->batch_number);
        $this->assertEquals('PENDING', $batch->status);
        $this->assertEquals([1, 2, 3], $batch->item_ids);
    }

    public function test_enrichment_batch_belongs_to_process()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertEquals($process->id, $batch->process->id);
    }

    public function test_is_incomplete_returns_true_for_pending()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertTrue($batch->isIncomplete());
    }

    public function test_is_incomplete_returns_true_for_in_progress()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'IN_PROGRESS',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertTrue($batch->isIncomplete());
    }

    public function test_is_incomplete_returns_false_for_completed()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
            'items_processed' => 3,
        ]);

        $this->assertFalse($batch->isIncomplete());
    }

    public function test_is_incomplete_returns_false_for_failed()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'FAILED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
            'error_message' => 'API timeout',
        ]);

        $this->assertFalse($batch->isIncomplete());
    }

    public function test_is_failed()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'FAILED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
            'error_message' => 'API timeout',
        ]);

        $this->assertTrue($batch->isFailed());
        $this->assertFalse($batch->isPending());
        $this->assertFalse($batch->isCompleted());
    }

    public function test_is_pending()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertTrue($batch->isPending());
        $this->assertFalse($batch->isFailed());
        $this->assertFalse($batch->isCompleted());
    }

    public function test_is_in_progress()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'IN_PROGRESS',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $this->assertTrue($batch->isInProgress());
        $this->assertFalse($batch->isPending());
        $this->assertFalse($batch->isCompleted());
    }

    public function test_is_completed()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'COMPLETED',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
            'items_processed' => 3,
        ]);

        $this->assertTrue($batch->isCompleted());
        $this->assertFalse($batch->isPending());
        $this->assertFalse($batch->isFailed());
    }

    public function test_item_count_attribute()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => [1, 2, 3, 4, 5],
            'batch_size' => 5,
        ]);

        $this->assertEquals(5, $batch->item_count);
    }

    public function test_on_fail_sets_status_error_and_timestamp()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'IN_PROGRESS',
            'item_ids' => [1, 2, 3],
            'batch_size' => 3,
        ]);

        $exception = new \Exception('API timeout');
        $batch->onFail($exception);

        $this->assertEquals('FAILED', $batch->status);
        $this->assertEquals('API timeout', $batch->error_message);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_item_ids_are_cast_to_array()
    {
        $process = EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'PENDING',
            'total_items' => 100,
        ]);

        $batch = EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'status' => 'PENDING',
            'item_ids' => ['case_1', 'case_2', 'case_3'],
            'batch_size' => 3,
        ]);

        // Refresh to test casting
        $batch = $batch->fresh();

        $this->assertIsArray($batch->item_ids);
        $this->assertEquals(['case_1', 'case_2', 'case_3'], $batch->item_ids);
    }
}
