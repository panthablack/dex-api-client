<?php

namespace Database\Factories;

use App\Enums\DataMigrationStatus;
use App\Enums\ResourceType;
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
            'name' => 'Clients, Cases, Sessions Migration - ' . fake()->dateTimeThisMonth()->format('Y-m-d H:i:s'),
            'resource_types' => [
                ResourceType::CLIENT,
                ResourceType::CASE,
                ResourceType::SESSION
            ],
            'filters' => [
                'date_from' => fake()->date('Y-m-d'),
                'date_to' => fake()->date('Y-m-d'),
            ],
            'status' => DataMigrationStatus::COMPLETED,
            'total_items' => 100,
            'batch_size' => 50,
            'error_message' => null,
            'summary' => null,
            'started_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }
}
