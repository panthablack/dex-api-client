<?php

namespace Tests\Unit;

use App\Services\EnrichmentService;
use App\Services\DataExchangeService;
use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Models\DataMigrationBatch;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Mockery;

class EnrichmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $dataExchangeService;
    protected $enrichmentService;

    public function setUp(): void
    {
        parent::setUp();

        // Mock the DataExchangeService
        $this->dataExchangeService = Mockery::mock(DataExchangeService::class);
        $this->enrichmentService = new EnrichmentService($this->dataExchangeService);
    }

    /**
     * Helper to mock the process lock for enrichAllCases
     */
    protected function mockProcessLock(): void
    {
        $lockMock = Mockery::mock();
        $lockMock->shouldReceive('get')->andReturn(true);
        $lockMock->shouldReceive('release')->once();

        Cache::shouldReceive('lock')
            ->with('enrichment:process', 3600)
            ->andReturn($lockMock);

        Cache::shouldReceive('get')
            ->with('enrichment:paused', false)
            ->andReturn(false);

        Cache::shouldReceive('forget')
            ->with('enrichment:paused')
            ->andReturn(true);
    }

    public function test_enrich_case_fetches_and_stores_enriched_data(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'CASE-001',
            'outlet_name' => 'Test Outlet',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        // Mock the API response
        $mockCaseData = [
            'case_id' => 'CASE-001',
            'OutletName' => 'Test Outlet Full',
            'ClientIds' => ['CLIENT-001', 'CLIENT-002'],
            'OutletActivityId' => 123,
            'CreatedDateTime' => '2025-01-01',
            'ClientCount' => 2,
        ];

        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->once()
            ->with('CASE-001')
            ->andReturn($mockCaseData);

        // Enrich the case
        $enrichedCase = $this->enrichmentService->enrichCase($shallowCase);

        // Verify the enriched case was created
        $this->assertInstanceOf(MigratedEnrichedCase::class, $enrichedCase);
        $this->assertEquals('CASE-001', $enrichedCase->case_id);
        $this->assertEquals($shallowCase->id, $enrichedCase->shallow_case_id);
        $this->assertEquals('Test Outlet Full', $enrichedCase->outlet_name);
        $this->assertEquals(['CLIENT-001', 'CLIENT-002'], $enrichedCase->client_ids);
        $this->assertEquals(123, $enrichedCase->outlet_activity_id);
        $this->assertNotNull($enrichedCase->enriched_at);
        $this->assertEquals(VerificationStatus::PENDING, $enrichedCase->verification_status);
    }

    public function test_enrich_case_throws_exception_when_api_fails(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'CASE-002',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->once()
            ->with('CASE-002')
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch case data for CASE-002');

        $this->enrichmentService->enrichCase($shallowCase);
    }

    public function test_is_already_enriched_returns_true_when_enriched(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'CASE-003',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedEnrichedCase::create([
            'case_id' => 'CASE-003',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
            'sessions' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $result = $this->invokeMethod($this->enrichmentService, 'isAlreadyEnriched', ['CASE-003']);
        $this->assertTrue($result);
    }

    public function test_is_already_enriched_returns_false_when_not_enriched(): void
    {
        $result = $this->invokeMethod($this->enrichmentService, 'isAlreadyEnriched', ['CASE-999']);
        $this->assertFalse($result);
    }

    public function test_enrich_all_cases_skips_already_enriched(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create two shallow cases
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

        // CASE-100 is already enriched
        MigratedEnrichedCase::create([
            'case_id' => 'CASE-100',
            'shallow_case_id' => $shallowCase1->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
            'sessions' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        // Mock API call for CASE-101 only (CASE-100 should be skipped)
        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->once()
            ->with('CASE-101')
            ->andReturn([
                'case_id' => 'CASE-101',
                'OutletName' => 'New Outlet',
                'OutletActivityId' => 200,
            ]);

        // Mock process lock
        $this->mockProcessLock();

        // Run enrichment
        $stats = $this->enrichmentService->enrichAllCases();

        // Verify stats
        $this->assertEquals(2, $stats['total_shallow_cases']);
        $this->assertEquals(1, $stats['already_enriched']);
        $this->assertEquals(1, $stats['newly_enriched']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function test_enrich_all_cases_continues_on_failure(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create three shallow cases
        MigratedShallowCase::create([
            'case_id' => 'CASE-200',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-201',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedShallowCase::create([
            'case_id' => 'CASE-202',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        // Mock API: first succeeds, second fails, third succeeds
        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->with('CASE-200')
            ->andReturn(['case_id' => 'CASE-200', 'OutletActivityId' => 1]);

        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->with('CASE-201')
            ->andReturn(null); // This will cause enrichCase to throw

        $this->dataExchangeService
            ->shouldReceive('getCaseById')
            ->with('CASE-202')
            ->andReturn(['case_id' => 'CASE-202', 'OutletActivityId' => 2]);

        // Mock process lock
        $this->mockProcessLock();

        // Run enrichment
        $stats = $this->enrichmentService->enrichAllCases();

        // Verify stats: 2 succeeded, 1 failed
        $this->assertEquals(3, $stats['total_shallow_cases']);
        $this->assertEquals(2, $stats['newly_enriched']);
        $this->assertEquals(1, $stats['failed']);
        $this->assertCount(1, $stats['errors']);
        $this->assertEquals('CASE-201', $stats['errors'][0]['case_id']);
    }

    public function test_get_enrichment_progress_returns_correct_stats(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        // Create 5 shallow cases
        for ($i = 1; $i <= 5; $i++) {
            MigratedShallowCase::create([
                'case_id' => "CASE-{$i}",
                'api_response' => [],
                'data_migration_batch_id' => $batch->id,
            ]);
        }

        // Enrich 3 of them
        for ($i = 1; $i <= 3; $i++) {
            MigratedEnrichedCase::create([
                'case_id' => "CASE-{$i}",
                'shallow_case_id' => $i,
                'outlet_activity_id' => 1,
                'api_response' => [],
                'sessions' => [],
                'verification_status' => VerificationStatus::PENDING,
            ]);
        }

        $progress = $this->enrichmentService->getEnrichmentProgress();

        $this->assertEquals(5, $progress['total_shallow_cases']);
        $this->assertEquals(3, $progress['enriched_cases']);
        $this->assertEquals(2, $progress['unenriched_cases']);
        $this->assertEquals(60.0, $progress['progress_percentage']);
    }

    public function test_get_unenriched_case_ids_returns_correct_ids(): void
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

        // Enrich only CASE-A
        MigratedEnrichedCase::create([
            'case_id' => 'CASE-A',
            'shallow_case_id' => 1,
            'outlet_activity_id' => 1,
            'api_response' => [],
            'sessions' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $unenrichedIds = $this->enrichmentService->getUnenrichedCaseIds();

        $this->assertCount(2, $unenrichedIds);
        $this->assertTrue($unenrichedIds->contains('CASE-B'));
        $this->assertTrue($unenrichedIds->contains('CASE-C'));
        $this->assertFalse($unenrichedIds->contains('CASE-A'));
    }

    public function test_extract_client_ids_handles_array(): void
    {
        $caseData = [
            'client_ids' => ['CLIENT-001', 'CLIENT-002', 'CLIENT-003']
        ];

        $result = $this->invokeMethod($this->enrichmentService, 'extractClientIds', [$caseData]);

        $this->assertEquals(['CLIENT-001', 'CLIENT-002', 'CLIENT-003'], $result);
    }

    public function test_extract_client_ids_handles_string(): void
    {
        $caseData = [
            'ClientIds' => 'CLIENT-001, CLIENT-002, CLIENT-003'
        ];

        $result = $this->invokeMethod($this->enrichmentService, 'extractClientIds', [$caseData]);

        $this->assertEquals(['CLIENT-001', 'CLIENT-002', 'CLIENT-003'], $result);
    }

    public function test_extract_client_ids_handles_empty(): void
    {
        $caseData = [];

        $result = $this->invokeMethod($this->enrichmentService, 'extractClientIds', [$caseData]);

        $this->assertEquals([], $result);
    }

    public function test_extract_client_ids_filters_empty_values(): void
    {
        $caseData = [
            'client_ids' => ['CLIENT-001', '', 'CLIENT-002', null, 'CLIENT-003']
        ];

        $result = $this->invokeMethod($this->enrichmentService, 'extractClientIds', [$caseData]);

        $this->assertEquals(['CLIENT-001', 'CLIENT-002', 'CLIENT-003'], $result);
    }

    /**
     * Helper method to invoke protected/private methods for testing
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
