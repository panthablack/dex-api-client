<?php

namespace Database\Factories;

use App\Models\MigratedShallowSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MigratedShallowSession>
 */
class MigratedShallowSessionFactory extends Factory
{
    protected $model = MigratedShallowSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => 'SESSION' . $this->faker->unique()->numerify('###'),
            'case_id' => 'CASE' . $this->faker->numerify('###'),
        ];
    }
}
