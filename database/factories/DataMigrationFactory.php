<?php

namespace Database\Factories;

use App\Models\DataMigration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataMigration>
 */
class DataMigrationFactory extends Factory
{
    protected $model = DataMigration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Data Migration - ' . fake()->date('Y-m-d'),
            'resource_types' => ['clients', 'cases', 'sessions'],
            'filters' => [
                'date_from' => fake()->date('Y-m-d'),
                'date_to' => fake()->date('Y-m-d'),
            ],
            'status' => 'completed',
            'total_items' => 100,
            'processed_items' => 100,
            'successful_items' => 90,
            'failed_items' => 10,
            'batch_size' => 50,
            'error_message' => null,
            'summary' => null,
            'started_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    /**
     * Create a pending migration
     */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'successful_items' => 0,
            'failed_items' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    /**
     * Create an in-progress migration
     */
    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'in_progress',
            'total_items' => 100,
            'processed_items' => 50,
            'successful_items' => 45,
            'failed_items' => 5,
            'started_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
            'completed_at' => null,
        ]);
    }

    /**
     * Create a completed migration
     */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
            'total_items' => 100,
            'processed_items' => 100,
            'successful_items' => 90,
            'failed_items' => 10,
            'started_at' => fake()->dateTimeBetween('-1 day', '-2 hours'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Create a failed migration
     */
    public function failed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'failed',
            'total_items' => 100,
            'processed_items' => 30,
            'successful_items' => 10,
            'failed_items' => 20,
            'error_message' => 'Migration failed due to network issues',
            'started_at' => fake()->dateTimeBetween('-1 day', '-2 hours'),
            'completed_at' => null,
        ]);
    }
}
