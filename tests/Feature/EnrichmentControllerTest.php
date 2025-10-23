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
use App\Jobs\EnrichCasesJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Mockery;

class EnrichmentControllerTest extends TestCase
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

        $response = $this->get(route('enrichment.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.index');
        $response->assertViewHas('canEnrich', true);
        $response->assertViewHas('progress');
    }

    public function test_index_shows_warning_when_no_shallow_case_migration(): void
    {
        $response = $this->get(route('enrichment.index'));

        $response->assertOk();
        $response->assertViewIs('enrichment.index');
        $response->assertViewHas('canEnrich', false);
        $response->assertSee('Prerequisite Required');
    }

    public function test_progress_endpoint_returns_correct_stats(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create 3 shallow cases
        for ($i = 1; $i <= 3; $i++) {
            MigratedShallowCase::create([
                'case_id' => "CASE-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

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

        $response = $this->getJson(route('enrichment.api.progress'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'total_shallow_cases' => 3,
                'enriched_cases' => 2,
                'unenriched_cases' => 1,
                'progress_percentage' => 66.67,
            ]
        ]);
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

        $response = $this->getJson(route('enrichment.api.unenriched'));

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
        $response = $this->postJson(route('enrichment.api.start'));

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

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment job dispatched to background queue',
        ]);

        // Verify response contains job_id and background flag
        $data = $response->json('data');
        $this->assertArrayHasKey('job_id', $data);
        $this->assertArrayHasKey('background', $data);
        $this->assertTrue($data['background']);

        // Verify job was dispatched
        Queue::assertPushed(EnrichCasesJob::class);
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

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment job dispatched to background queue',
        ]);

        // Verify job was dispatched (skip logic is tested in EnrichmentService tests)
        Queue::assertPushed(EnrichCasesJob::class);
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

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment job dispatched to background queue',
        ]);

        // Verify job was dispatched (error handling is tested in EnrichmentService tests)
        Queue::assertPushed(EnrichCasesJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
