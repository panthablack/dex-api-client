<?php

namespace Tests\Feature;

use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
use App\Enums\VerificationStatus;
use App\Services\EnrichmentService;
use App\Services\DataExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;

/**
 * Integration tests for the full SHALLOW_CASE → Enrichment workflow
 */
class EnrichmentWorkflowTest extends TestCase
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

    public function test_full_workflow_shallow_case_migration_then_enrichment(): void
    {
        // Step 1: Create a completed SHALLOW_CASE migration
        $migration = DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
            'total_items' => 3,
        ]);

        $batch = DataMigrationBatch::factory()->create([
            'data_migration_id' => $migration->id,
        ]);

        // Step 2: Simulate SHALLOW_CASE migration results
        $shallowCases = [];
        for ($i = 1; $i <= 3; $i++) {
            $shallowCases[] = MigratedShallowCase::create([
                'case_id' => "CASE-WF-{$i}",
                'outlet_name' => "Outlet {$i}",
                'created_date_time' => now()->subDays($i),
                'api_response' => ['SearchCase' => 'data'],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        // Verify shallow cases created
        $this->assertCount(3, MigratedShallowCase::all());
        $this->assertEquals(0, MigratedEnrichedCase::count());

        // Step 3: Verify ResourceType::canEnrichCases() returns true
        $this->assertTrue(ResourceType::canEnrichCases());

        // Step 4: Mock DataExchangeService for enrichment
        $mockService = Mockery::mock(DataExchangeService::class);

        foreach ($shallowCases as $index => $shallowCase) {
            $mockService->shouldReceive('getCaseById')
                ->with($shallowCase->case_id)
                ->once()
                ->andReturn([
                    'case_id' => $shallowCase->case_id,
                    'OutletName' => "Full Outlet {$index}",
                    'OutletActivityId' => 1000 + $index,
                    'ClientIds' => ["CLIENT-{$index}-1", "CLIENT-{$index}-2"],
                    'CreatedDateTime' => now()->subDays($index)->format('Y-m-d'),
                    'ClientCount' => 2,
                ]);
        }

        // Step 5: Run enrichment
        $this->mockProcessLock();
        $enrichmentService = new EnrichmentService($mockService);
        $stats = $enrichmentService->enrichAllCases();

        // Step 6: Verify enrichment results
        $this->assertEquals(3, $stats['total_shallow_cases']);
        $this->assertEquals(3, $stats['newly_enriched']);
        $this->assertEquals(0, $stats['already_enriched']);
        $this->assertEquals(0, $stats['failed']);

        // Step 7: Verify enriched cases were created
        $this->assertEquals(3, MigratedEnrichedCase::count());

        foreach ($shallowCases as $index => $shallowCase) {
            $enrichedCase = MigratedEnrichedCase::where('case_id', $shallowCase->case_id)->first();

            $this->assertNotNull($enrichedCase);
            $this->assertEquals($shallowCase->id, $enrichedCase->shallow_case_id);
            $this->assertEquals("Full Outlet {$index}", $enrichedCase->outlet_name);
            $this->assertEquals(1000 + $index, $enrichedCase->outlet_activity_id);
            $this->assertEquals(["CLIENT-{$index}-1", "CLIENT-{$index}-2"], $enrichedCase->client_ids);
            $this->assertNotNull($enrichedCase->enriched_at);
            $this->assertEquals(VerificationStatus::PENDING, $enrichedCase->verification_status);
        }

        // Step 8: Verify relationships work
        foreach ($shallowCases as $shallowCase) {
            $shallowCase->refresh();
            $this->assertTrue($shallowCase->isEnriched());
            $this->assertNotNull($shallowCase->enrichedCase);
        }
    }

    public function test_resume_capability_enrichment_can_be_rerun_safely(): void
    {
        // Create completed migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create 5 shallow cases
        for ($i = 1; $i <= 5; $i++) {
            MigratedShallowCase::create([
                'case_id' => "RESUME-CASE-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        // Manually enrich 2 cases (simulating partial enrichment)
        for ($i = 1; $i <= 2; $i++) {
            MigratedEnrichedCase::create([
                'case_id' => "RESUME-CASE-{$i}",
                'shallow_case_id' => $i,
                'outlet_activity_id' => 100 + $i,
                'api_response' => ['manual' => true],
                'enriched_at' => now()->subHours(1),
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        $this->assertEquals(2, MigratedEnrichedCase::count());

        // Mock service should only be called for unenriched cases (3, 4, 5)
        $mockService = Mockery::mock(DataExchangeService::class);

        for ($i = 3; $i <= 5; $i++) {
            $mockService->shouldReceive('getCaseById')
                ->with("RESUME-CASE-{$i}")
                ->once()
                ->andReturn([
                    'case_id' => "RESUME-CASE-{$i}",
                    'OutletActivityId' => 200 + $i,
                ]);
        }

        // Run enrichment again (resume)
        $this->mockProcessLock();
        $enrichmentService = new EnrichmentService($mockService);
        $stats = $enrichmentService->enrichAllCases();

        // Verify stats
        $this->assertEquals(5, $stats['total_shallow_cases']);
        $this->assertEquals(3, $stats['newly_enriched']); // Only 3 new ones
        $this->assertEquals(2, $stats['already_enriched']); // 2 were skipped
        $this->assertEquals(0, $stats['failed']);

        // Verify all 5 cases are now enriched
        $this->assertEquals(5, MigratedEnrichedCase::count());

        // Verify the original 2 cases were NOT modified
        for ($i = 1; $i <= 2; $i++) {
            $enrichedCase = MigratedEnrichedCase::where('case_id', "RESUME-CASE-{$i}")->first();
            $this->assertEquals(['manual' => true], $enrichedCase->api_response);
            $this->assertTrue($enrichedCase->enriched_at->lt(now()->subMinutes(30)));
        }
    }

    public function test_dependency_enforcement_cannot_enrich_without_shallow_case_migration(): void
    {
        // No SHALLOW_CASE migration exists
        $this->assertFalse(ResourceType::canEnrichCases());

        // Try to access enrichment dashboard
        $response = $this->get(route('enrichment.index'));

        $response->assertOk();
        $response->assertViewHas('canEnrich', false);
        $response->assertSee('Prerequisite Required');

        // Try to start enrichment via API
        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Cannot start enrichment. Please complete a SHALLOW_CASE migration first.'
        ]);
    }

    public function test_dependency_enforcement_cannot_enrich_with_incomplete_migration(): void
    {
        // Create SHALLOW_CASE migration that's still in progress
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::IN_PROGRESS,
        ]);

        $this->assertFalse(ResourceType::canEnrichCases());

        // Try to start enrichment
        $response = $this->postJson(route('enrichment.api.start'));

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'Cannot start enrichment. Please complete a SHALLOW_CASE migration first.'
        ]);
    }

    public function test_dependency_enforcement_can_enrich_only_with_completed_migration(): void
    {
        // Create COMPLETED SHALLOW_CASE migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $this->assertTrue(ResourceType::canEnrichCases());

        // Dashboard should allow enrichment
        $response = $this->get(route('enrichment.index'));
        $response->assertViewHas('canEnrich', true);
    }

    public function test_error_handling_continues_processing_after_failures(): void
    {
        // Create completed migration
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $batch = DataMigrationBatch::factory()->create();

        // Create 5 shallow cases
        for ($i = 1; $i <= 5; $i++) {
            MigratedShallowCase::create([
                'case_id' => "ERROR-CASE-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        // Mock service - fail on cases 2 and 4
        $mockService = Mockery::mock(DataExchangeService::class);

        // Case 1: Success
        $mockService->shouldReceive('getCaseById')
            ->with('ERROR-CASE-1')
            ->once()
            ->andReturn(['case_id' => 'ERROR-CASE-1', 'OutletActivityId' => 1]);

        // Case 2: Failure (API returns null)
        $mockService->shouldReceive('getCaseById')
            ->with('ERROR-CASE-2')
            ->once()
            ->andReturn(null);

        // Case 3: Success
        $mockService->shouldReceive('getCaseById')
            ->with('ERROR-CASE-3')
            ->once()
            ->andReturn(['case_id' => 'ERROR-CASE-3', 'OutletActivityId' => 3]);

        // Case 4: Failure (API returns null)
        $mockService->shouldReceive('getCaseById')
            ->with('ERROR-CASE-4')
            ->once()
            ->andReturn(null);

        // Case 5: Success
        $mockService->shouldReceive('getCaseById')
            ->with('ERROR-CASE-5')
            ->once()
            ->andReturn(['case_id' => 'ERROR-CASE-5', 'OutletActivityId' => 5]);

        $this->mockProcessLock();
        $enrichmentService = new EnrichmentService($mockService);
        $stats = $enrichmentService->enrichAllCases();

        // Verify stats
        $this->assertEquals(5, $stats['total_shallow_cases']);
        $this->assertEquals(3, $stats['newly_enriched']);
        $this->assertEquals(0, $stats['already_enriched']);
        $this->assertEquals(2, $stats['failed']);
        $this->assertCount(2, $stats['errors']);

        // Verify error details
        $errorCaseIds = array_column($stats['errors'], 'case_id');
        $this->assertContains('ERROR-CASE-2', $errorCaseIds);
        $this->assertContains('ERROR-CASE-4', $errorCaseIds);

        // Verify successful cases were enriched
        $this->assertDatabaseHas('migrated_enriched_cases', ['case_id' => 'ERROR-CASE-1']);
        $this->assertDatabaseHas('migrated_enriched_cases', ['case_id' => 'ERROR-CASE-3']);
        $this->assertDatabaseHas('migrated_enriched_cases', ['case_id' => 'ERROR-CASE-5']);

        // Verify failed cases were NOT enriched
        $this->assertDatabaseMissing('migrated_enriched_cases', ['case_id' => 'ERROR-CASE-2']);
        $this->assertDatabaseMissing('migrated_enriched_cases', ['case_id' => 'ERROR-CASE-4']);
    }

    public function test_enrichment_progress_tracking(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create 10 shallow cases
        for ($i = 1; $i <= 10; $i++) {
            MigratedShallowCase::create([
                'case_id' => "PROGRESS-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        $enrichmentService = new EnrichmentService(Mockery::mock(DataExchangeService::class));

        // Initial progress: 0%
        $progress = $enrichmentService->getEnrichmentProgress();
        $this->assertEquals(10, $progress['total_shallow_cases']);
        $this->assertEquals(0, $progress['enriched_cases']);
        $this->assertEquals(10, $progress['unenriched_cases']);
        $this->assertEquals(0, $progress['progress_percentage']);

        // Enrich 3 cases
        for ($i = 1; $i <= 3; $i++) {
            MigratedEnrichedCase::create([
                'case_id' => "PROGRESS-{$i}",
                'shallow_case_id' => $i,
                'outlet_activity_id' => 100,
                'api_response' => [],
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        // Progress: 30%
        $progress = $enrichmentService->getEnrichmentProgress();
        $this->assertEquals(10, $progress['total_shallow_cases']);
        $this->assertEquals(3, $progress['enriched_cases']);
        $this->assertEquals(7, $progress['unenriched_cases']);
        $this->assertEquals(30.0, $progress['progress_percentage']);

        // Enrich 7 more cases (total 10)
        for ($i = 4; $i <= 10; $i++) {
            MigratedEnrichedCase::create([
                'case_id' => "PROGRESS-{$i}",
                'shallow_case_id' => $i,
                'outlet_activity_id' => 100,
                'api_response' => [],
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        // Progress: 100%
        $progress = $enrichmentService->getEnrichmentProgress();
        $this->assertEquals(10, $progress['total_shallow_cases']);
        $this->assertEquals(10, $progress['enriched_cases']);
        $this->assertEquals(0, $progress['unenriched_cases']);
        $this->assertEquals(100.0, $progress['progress_percentage']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
