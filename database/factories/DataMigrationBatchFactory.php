<?php

namespace Database\Factories;

use App\Enums\DataMigrationBatchStatus;
use App\Enums\ResourceType;
use App\Models\DataMigrationBatch;
use App\Models\DataMigration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataMigrationBatch>
 */
class DataMigrationBatchFactory extends Factory
{
    protected $model = DataMigrationBatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'data_migration_id' => DataMigration::factory(),
            'batch_number' => 1,
            'page_index' => 1,
            'page_size' => 50,
            'resource_type' => fake()->randomElement([
                ResourceType::CLIENT,
                ResourceType::CASE,
                ResourceType::SESSION
            ]),
            'status' => DataMigrationBatchStatus::COMPLETED,
            'items_requested' => 50,
            'items_received' => 50,
            'items_stored' => 45,
            'api_filters' => [],
            'api_response_summary' => null,
            'started_at' => fake()->dateTimeBetween('-1 day', '-1 hour'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    /**
     * Create a completed batch
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DataMigrationBatchStatus::COMPLETED,
            'items_received' => $attributes['items_requested'] ?? 50,
            'items_stored' => $attributes['items_requested'] ?? 50,
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Create a pending batch
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DataMigrationBatchStatus::PENDING,
            'items_received' => 0,
            'items_stored' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Create a failed batch
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => DataMigrationBatchStatus::FAILED,
            'items_received' => fake()->numberBetween(1, $attributes['items_requested'] ?? 50),
            'items_stored' => 0,
            'completed_at' => null,
        ]);
    }
}
