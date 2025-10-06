<?php

namespace Tests\Unit;

use App\Models\MigratedShallowCase;
use App\Models\MigratedEnrichedCase;
use App\Models\DataMigrationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigratedShallowCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_shallow_case(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-001',
            'outlet_name' => 'Test Outlet',
            'created_date_time' => '2025-01-01',
            'client_attendance_profile_code' => 'TEST-CODE',
            'api_response' => ['test' => 'data'],
            'data_migration_batch_id' => $batch->id,
        ]);

        $this->assertDatabaseHas('migrated_shallow_cases', [
            'case_id' => 'TEST-CASE-001',
            'outlet_name' => 'Test Outlet',
        ]);

        $this->assertEquals('TEST-CASE-001', $shallowCase->case_id);
        $this->assertEquals(['test' => 'data'], $shallowCase->api_response);
    }

    public function test_belongs_to_batch(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-002',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $this->assertInstanceOf(DataMigrationBatch::class, $shallowCase->batch);
        $this->assertEquals($batch->id, $shallowCase->batch->id);
    }

    public function test_has_one_enriched_case(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-003',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-003',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        $this->assertInstanceOf(MigratedEnrichedCase::class, $shallowCase->enrichedCase);
        $this->assertEquals($enrichedCase->id, $shallowCase->enrichedCase->id);
    }

    public function test_is_enriched_returns_false_when_not_enriched(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-004',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $this->assertFalse($shallowCase->isEnriched());
    }

    public function test_is_enriched_returns_true_when_enriched(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-005',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-005',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        $this->assertTrue($shallowCase->isEnriched());
    }

    public function test_casts_api_response_to_array(): void
    {
        $batch = DataMigrationBatch::factory()->create();

        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-006',
            'api_response' => ['key' => 'value', 'nested' => ['data' => 123]],
            'data_migration_batch_id' => $batch->id,
        ]);

        $retrieved = MigratedShallowCase::find($shallowCase->id);

        $this->assertIsArray($retrieved->api_response);
        $this->assertEquals('value', $retrieved->api_response['key']);
        $this->assertEquals(123, $retrieved->api_response['nested']['data']);
    }
}
