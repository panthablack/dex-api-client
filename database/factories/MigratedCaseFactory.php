<?php

namespace Database\Factories;

use App\Models\MigratedCase;
use App\Models\DataMigrationBatch;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MigratedCase>
 */
class MigratedCaseFactory extends Factory
{
    protected $model = MigratedCase::class;

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
            'client_ids' => [],
            'outlet_activity_id' => $this->faker->numerify('###'),
            'total_number_of_unidentified_clients' => $this->faker->numberBetween(0, 10),
            'client_attendance_profile_code' => $this->faker->bothify('??-???'),
            'created_date_time' => $this->faker->date(),
            'end_date' => $this->faker->dateTimeThisMonth(),
            'exit_reason_code' => $this->faker->numerify('##'),
            'ag_business_type_code' => $this->faker->bothify('??#'),
            'program_activity_name' => $this->faker->words(3, true),
            'sessions' => [],
            'api_response' => [],
            'data_migration_batch_id' => DataMigrationBatch::factory(),
            'verification_status' => VerificationStatus::PENDING->value,
        ];
    }
}
