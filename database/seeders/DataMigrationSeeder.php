<?php

namespace Database\Seeders;

use App\Models\DataMigration;
use App\Models\DataMigrationBatch;
use Illuminate\Database\Seeder;

class DataMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a pending migration (ID 1 for tests)
        $pendingMigration = DataMigration::factory()->pending()->create([
            'id' => 1,
            'name' => 'Data Migration - 2025-09-10',
        ]);

        // Create batches for pending migration (no completed batches)
        DataMigrationBatch::factory()->pending()->create([
            'data_migration_id' => $pendingMigration->id,
            'batch_number' => 1,
            'resource_type' => 'clients',
        ]);

        // Create a completed migration (ID 2)
        $completedMigration = DataMigration::factory()->completed()->create([
            'id' => 2,
            'name' => 'Data Migration - Completed',
        ]);

        // Create completed batches for completed migration
        DataMigrationBatch::factory()->completed()->create([
            'data_migration_id' => $completedMigration->id,
            'batch_number' => 1,
            'resource_type' => 'clients',
        ]);

        DataMigrationBatch::factory()->completed()->create([
            'data_migration_id' => $completedMigration->id,
            'batch_number' => 2,
            'resource_type' => 'cases',
        ]);

        // Create an in-progress migration (ID 3)
        $inProgressMigration = DataMigration::factory()->inProgress()->create([
            'id' => 3,
            'name' => 'Data Migration - In Progress',
        ]);

        // Create mixed batches for in-progress migration
        DataMigrationBatch::factory()->completed()->create([
            'data_migration_id' => $inProgressMigration->id,
            'batch_number' => 1,
            'resource_type' => 'clients',
        ]);

        DataMigrationBatch::factory()->pending()->create([
            'data_migration_id' => $inProgressMigration->id,
            'batch_number' => 2,
            'resource_type' => 'cases',
        ]);

        // Create a failed migration (ID 4)
        $failedMigration = DataMigration::factory()->failed()->create([
            'id' => 4,
            'name' => 'Data Migration - Failed',
        ]);

        // Create some failed batches
        DataMigrationBatch::factory()->failed()->create([
            'data_migration_id' => $failedMigration->id,
            'batch_number' => 1,
            'resource_type' => 'clients',
        ]);
    }
}
