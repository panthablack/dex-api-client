<?php

namespace Tests\Unit;

use App\Models\MigratedEnrichedCase;
use App\Models\MigratedShallowCase;
use App\Models\MigratedSession;
use App\Models\MigratedClient;
use App\Models\DataMigrationBatch;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigratedEnrichedCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_enriched_case(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-100',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-100',
            'shallow_case_id' => $shallowCase->id,
            'outlet_name' => 'Test Outlet',
            'client_ids' => ['CLIENT-001', 'CLIENT-002'],
            'outlet_activity_id' => 123,
            'api_response' => ['enriched' => 'data'],
            'enriched_at' => now(),
        ]);

        $this->assertDatabaseHas('migrated_enriched_cases', [
            'case_id' => 'TEST-CASE-100',
            'outlet_name' => 'Test Outlet',
        ]);

        $this->assertEquals('TEST-CASE-100', $enrichedCase->case_id);
        $this->assertEquals(['CLIENT-001', 'CLIENT-002'], $enrichedCase->client_ids);
    }

    public function test_belongs_to_shallow_case(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-101',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-101',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        $this->assertInstanceOf(MigratedShallowCase::class, $enrichedCase->shallowCase);
        $this->assertEquals($shallowCase->id, $enrichedCase->shallowCase->id);
    }

    public function test_has_many_sessions_relationship(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-102',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-102',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        // Test relationship exists and returns correct type
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $enrichedCase->sessions());

        // Test empty collection when no sessions
        $this->assertCount(0, $enrichedCase->sessions);
    }

    public function test_clients_method_returns_collection(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-103',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $client1 = MigratedClient::create([
            'client_id' => 'CLIENT-100',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $client2 = MigratedClient::create([
            'client_id' => 'CLIENT-101',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-103',
            'shallow_case_id' => $shallowCase->id,
            'client_ids' => ['CLIENT-100', 'CLIENT-101'],
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        $clients = $enrichedCase->clients();

        $this->assertCount(2, $clients);
        $this->assertTrue($clients->contains('client_id', 'CLIENT-100'));
        $this->assertTrue($clients->contains('client_id', 'CLIENT-101'));
    }

    public function test_clients_method_returns_empty_collection_when_no_client_ids(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-104',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-104',
            'shallow_case_id' => $shallowCase->id,
            'client_ids' => null,
            'outlet_activity_id' => 1,
            'api_response' => [],
        ]);

        $clients = $enrichedCase->clients();

        $this->assertCount(0, $clients);
    }

    public function test_verification_status_defaults_to_pending(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-105',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-105',
            'shallow_case_id' => $shallowCase->id,
            'outlet_activity_id' => 1,
            'api_response' => [],
            'verification_status' => VerificationStatus::PENDING,
        ]);

        $retrieved = MigratedEnrichedCase::find($enrichedCase->id);

        $this->assertEquals(VerificationStatus::PENDING, $retrieved->verification_status);
    }

    public function test_casts_work_correctly(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        $shallowCase = MigratedShallowCase::create([
            'case_id' => 'TEST-CASE-106',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $enrichedCase = MigratedEnrichedCase::create([
            'case_id' => 'TEST-CASE-106',
            'shallow_case_id' => $shallowCase->id,
            'client_ids' => ['CLIENT-001', 'CLIENT-002'],
            'created_date_time' => '2025-01-01',
            'end_date' => '2025-12-31',
            'outlet_activity_id' => 1,
            'api_response' => ['key' => 'value'],
            'enriched_at' => '2025-01-15 10:00:00',
        ]);

        $retrieved = MigratedEnrichedCase::find($enrichedCase->id);

        $this->assertIsArray($retrieved->client_ids);
        $this->assertIsArray($retrieved->api_response);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $retrieved->enriched_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $retrieved->created_date_time);
    }
}
