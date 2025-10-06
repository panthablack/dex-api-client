<?php

namespace Tests\Unit;

use App\Enums\ResourceType;
use App\Enums\DataMigrationStatus;
use App\Models\DataMigration;
use App\Models\MigratedShallowCase;
use App\Models\DataMigrationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shallow_case_is_in_migratable_resources(): void
    {
        $this->assertContains(
            ResourceType::SHALLOW_CASE,
            ResourceType::MIGRATABLE_RESOURCES
        );
    }

    public function test_enriched_case_is_not_in_migratable_resources(): void
    {
        $this->assertNotContains(
            ResourceType::ENRICHED_CASE,
            ResourceType::MIGRATABLE_RESOURCES
        );
    }

    public function test_shallow_case_is_independent_resource_type(): void
    {
        $this->assertContains(
            ResourceType::SHALLOW_CASE,
            ResourceType::getIndependentResourceTypes()
        );
    }

    public function test_shallow_case_get_table_name(): void
    {
        $this->assertEquals(
            'migrated_shallow_cases',
            ResourceType::SHALLOW_CASE->getTableName()
        );
    }

    public function test_enriched_case_get_table_name(): void
    {
        $this->assertEquals(
            'migrated_enriched_cases',
            ResourceType::ENRICHED_CASE->getTableName()
        );
    }

    public function test_resources_available_for_shallow_case_returns_false_when_empty(): void
    {
        $this->assertFalse(
            ResourceType::resourcesAvailable(ResourceType::SHALLOW_CASE)
        );
    }

    public function test_resources_available_for_shallow_case_returns_true_when_cases_exist(): void
    {
        $batch = DataMigrationBatch::factory()->create();
        MigratedShallowCase::create([
            'case_id' => 'TEST-001',
            'api_response' => [],
            'data_migration_batch_id' => $batch->id,
        ]);

        $this->assertTrue(
            ResourceType::resourcesAvailable(ResourceType::SHALLOW_CASE)
        );
    }

    public function test_can_enrich_cases_returns_false_when_no_completed_migration(): void
    {
        $this->assertFalse(ResourceType::canEnrichCases());
    }

    public function test_can_enrich_cases_returns_false_when_migration_in_progress(): void
    {
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::IN_PROGRESS,
        ]);

        $this->assertFalse(ResourceType::canEnrichCases());
    }

    public function test_can_enrich_cases_returns_true_when_migration_completed(): void
    {
        DataMigration::factory()->create([
            'resource_type' => ResourceType::SHALLOW_CASE,
            'status' => DataMigrationStatus::COMPLETED,
        ]);

        $this->assertTrue(ResourceType::canEnrichCases());
    }

    public function test_shallow_case_is_migratable(): void
    {
        $this->assertTrue(
            ResourceType::isMigratable(ResourceType::SHALLOW_CASE)
        );
    }
}
