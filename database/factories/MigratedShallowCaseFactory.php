<?php

namespace Database\Factories;

use App\Models\MigratedShallowCase;
use App\Models\DataMigrationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MigratedShallowCase>
 */
class MigratedShallowCaseFactory extends Factory
{
    protected $model = MigratedShallowCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_id' => 'CASE' . $this->faker->unique()->numerify('###'),
            'outlet_name' => $this->faker->company(),
            'created_date_time' => $this->faker->date(),
            'client_attendance_profile_code' => $this->faker->bothify('??-???'),
            'api_response' => [],
            'data_migration_batch_id' => DataMigrationBatch::factory(),
        ];
    }
}
