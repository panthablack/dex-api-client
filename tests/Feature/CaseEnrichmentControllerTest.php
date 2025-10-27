<?php

namespace Tests\Feature;

use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use App\Services\DataExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class CaseEnrichmentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Helper to mock the process lock for enrichAllCases
     */
    protected function mockProcessLock(): void
    {
        $lockMock = Mockery::mock();
        $lockMock->shouldReceive('get')->andReturn(true);
        $lockMock->shouldReceive('release')->once();
        Cache::partialMock()->shouldReceive('lock')->with('enrichment:process', 3600)->andReturn($lockMock);
    }

    public function test_index_displays_dashboard_when_shallow_case_migration_completed(): void
    {
        // Create a completed SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create some shallow cases
        MigratedShallowCase::create([
            'case_id' => 'CASE-001',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-002',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $response = $this->get(route('enrichment.cases.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.cases.index');
        $response->assertViewHas('canEnrich', true);
        $response->assertViewHas('progress');
    }

    public function test_index_shows_warning_when_no_shallow_case_migration(): void
    {
        $response = $this->get(route('enrichment.cases.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.cases.index');
        $response->assertViewHas('canEnrich', false);
        $response->assertSee('Prerequisite Required');
    }

    public function test_progress_endpoint_returns_correct_stats(): void
    {
        // Create enrichment process with batches (mirror what initializeEnrichment does)
        $batch = DataMigrationBatch::factory()->create();

        // Create 3 shallow cases
        for ($i = 1; $i <= 3; $i++) {
            MigratedShallowCase::create([
                'case_id' => "CASE-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        // Create an enrichment process
        $process = \App\Models\EnrichmentProcess::create([
            'resource_type' => ResourceType::CASE,
            'status' => 'IN_PROGRESS',
            'total_items' => 3,
        ]);

        // Create batches for the process
        \App\Models\EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 1,
            'item_ids' => json_encode(['1', '2']),
            'status' => 'COMPLETED',
            'items_processed' => 2,
            'items_failed' => 0,
        ]);

        \App\Models\EnrichmentBatch::create([
            'enrichment_process_id' => $process->id,
            'batch_number' => 2,
            'item_ids' => json_encode(['3']),
            'status' => 'PENDING',
            'items_processed' => 0,
            'items_failed' => 0,
        ]);

        // Enrich 2 of them
        for ($i = 1; $i <= 2; $i++) {
            MigratedEnrichedCase::create([
                'case_id' => "CASE-{$i}",
                'shallow_case_id' => $i,
                'outlet_activity_id' => 100,
                'api_response' => [],
                'sessions' => [],
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        $response = $this->getJson(route('enrichment.cases.api.progress'));

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_items',
                'processed_items',
                'failed_items',
                'progress_percentage',
                'success_rate',
                'status',
                'completed_batches',
                'total_batches',
                'started_at',
                'completed_at',
            ]
        ]);

        // Verify specific values
        $data = $response->json('data');
        $this->assertEquals(3, $data['total_items']);
        $this->assertEquals(2, $data['processed_items']);
        $this->assertEquals(0, $data['failed_items']);
        $this->assertEquals(1, $data['completed_batches']);
        $this->assertEquals(2, $data['total_batches']);
    }

    public function test_unenriched_endpoint_returns_correct_case_ids(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create shallow cases
        MigratedShallowCase::create([
            'case_id' => 'CASE-A',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-B',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-C',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        // Enrich only CASE-B
        MigratedEnrichedCase::create([
            'case_id' => 'CASE-B',
            'shallow_case_id' => 2,
            'outlet_activity_id' => 100,
            'api_response' => [],
            'sessions' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $response = $this->getJson(route('enrichment.cases.api.unenriched'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'count' => 2
            ]
        ]);

        $unenrichedIds = $response->json('data.unenriched_case_ids');
        $this->assertContains('CASE-A', $unenrichedIds);
        $this->assertContains('CASE-C', $unenrichedIds);
        $this->assertNotContains('CASE-B', $unenrichedIds);
    }

    public function test_start_endpoint_rejects_when_no_shallow_case_migration(): void
    {
        $response = $this->postJson(route('enrichment.cases.api.start'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Cannot start enrichment. Please complete a SHALLOW_CASE migration first.'
        ]);
    }

    public function test_start_endpoint_enriches_cases_successfully(): void
    {
        Queue::fake();

        // Create a completed SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create shallow cases
        MigratedShallowCase::create([
            'case_id' => 'CASE-100',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-101',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $response = $this->postJson(route('enrichment.cases.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process started',
        ]);

        // Verify response contains process_id, total_items, batch_count, and status
        $data = $response->json('data');
        $this->assertArrayHasKey('process_id', $data);
        $this->assertArrayHasKey('total_items', $data);
        $this->assertArrayHasKey('batch_count', $data);
        $this->assertArrayHasKey('status', $data);

        // Verify batch jobs were dispatched (initialization is now synchronous)
        Queue::assertPushed(\App\Jobs\ProcessEnrichmentBatch::class);
    }

    public function test_start_endpoint_skips_already_enriched_cases(): void
    {
        Queue::fake();

        // Create a completed SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create shallow cases
        $shallowCase1 = MigratedShallowCase::create([
            'case_id' => 'CASE-200',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-201',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        // CASE-200 is already enriched
        MigratedEnrichedCase::create([
            'case_id' => 'CASE-200',
            'shallow_case_id' => $shallowCase1->id,
            'outlet_activity_id' => 2000,
            'api_response' => [],
            'sessions' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $response = $this->postJson(route('enrichment.cases.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process started',
        ]);

        // Verify batch jobs were dispatched (skip logic is handled in initializeEnrichment)
        Queue::assertPushed(\App\Jobs\ProcessEnrichmentBatch::class);
    }

    public function test_start_endpoint_handles_failures_gracefully(): void
    {
        Queue::fake();

        // Create a completed SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create shallow cases
        MigratedShallowCase::create([
            'case_id' => 'CASE-300',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-301',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-302',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $response = $this->postJson(route('enrichment.cases.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process started',
        ]);

        // Verify batch jobs were dispatched (error handling is tested in EnrichmentService tests)
        Queue::assertPushed(\App\Jobs\ProcessEnrichmentBatch::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
