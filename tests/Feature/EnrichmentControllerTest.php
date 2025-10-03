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
        // Create a completed SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create shallow cases
        $shallowCase1 = MigratedShallowCase::create([
            'case_id' => 'CASE-100',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $shallowCase2 = MigratedShallowCase::create([
            'case_id' => 'CASE-101',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        // Mock the DataExchangeService
        $mockService = Mockery::mock(DataExchangeService::class);

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-100')
            ->once()
            ->andReturn([
                'case_id' => 'CASE-100',
                'OutletName' => 'Test Outlet 100',
                'OutletActivityId' => 1000,
                'ClientIds' => ['CLIENT-001', 'CLIENT-002'],
            ]);

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-101')
            ->once()
            ->andReturn([
                'case_id' => 'CASE-101',
                'OutletName' => 'Test Outlet 101',
                'OutletActivityId' => 1001,
                'ClientIds' => ['CLIENT-003'],
            ]);

        $this->app->instance(DataExchangeService::class, $mockService);
        $this->mockProcessLock();

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Enrichment process completed',
        ]);

        $stats = $response->json('data');
        $this->assertEquals(2, $stats['total_shallow_cases']);
        $this->assertEquals(2, $stats['newly_enriched']);
        $this->assertEquals(0, $stats['already_enriched']);
        $this->assertEquals(0, $stats['failed']);

        // Verify enriched cases were created
        $this->assertDatabaseHas('migrated_enriched_cases', [
            'case_id' => 'CASE-100',
            'outlet_name' => 'Test Outlet 100',
        ]);

        $this->assertDatabaseHas('migrated_enriched_cases', [
            'case_id' => 'CASE-101',
            'outlet_name' => 'Test Outlet 101',
        ]);
    }

    public function test_start_endpoint_skips_already_enriched_cases(): void
    {
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

        $shallowCase2 = MigratedShallowCase::create([
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
            'verification_status' => VerificationStatus::PENDING,
        ]);

        // Mock the DataExchangeService - should only be called for CASE-201
        $mockService = Mockery::mock(DataExchangeService::class);

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-201')
            ->once()
            ->andReturn([
                'case_id' => 'CASE-201',
                'OutletName' => 'Test Outlet 201',
                'OutletActivityId' => 2001,
            ]);

        $this->app->instance(DataExchangeService::class, $mockService);
        $this->mockProcessLock();

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $stats = $response->json('data');
        $this->assertEquals(2, $stats['total_shallow_cases']);
        $this->assertEquals(1, $stats['newly_enriched']);
        $this->assertEquals(1, $stats['already_enriched']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function test_start_endpoint_handles_failures_gracefully(): void
    {
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

        // Mock the DataExchangeService - fail on CASE-301
        $mockService = Mockery::mock(DataExchangeService::class);

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-300')
            ->once()
            ->andReturn([
                'case_id' => 'CASE-300',
                'OutletActivityId' => 3000,
            ]);

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-301')
            ->once()
            ->andReturn(null); // Simulate API failure

        $mockService->shouldReceive('getCaseById')
            ->with('CASE-302')
            ->once()
            ->andReturn([
                'case_id' => 'CASE-302',
                'OutletActivityId' => 3002,
            ]);

        $this->app->instance(DataExchangeService::class, $mockService);
        $this->mockProcessLock();

        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertOk();
        $stats = $response->json('data');
        $this->assertEquals(3, $stats['total_shallow_cases']);
        $this->assertEquals(2, $stats['newly_enriched']);
        $this->assertEquals(0, $stats['already_enriched']);
        $this->assertEquals(1, $stats['failed']);

        // Verify error details
        $this->assertCount(1, $stats['errors']);
        $this->assertEquals('CASE-301', $stats['errors'][0]['case_id']);

        // Verify successful cases were enriched despite failure
        $this->assertDatabaseHas('migrated_enriched_cases', ['case_id' => 'CASE-300']);
        $this->assertDatabaseHas('migrated_enriched_cases', ['case_id' => 'CASE-302']);
        $this->assertDatabaseMissing('migrated_enriched_cases', ['case_id' => 'CASE-301']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
