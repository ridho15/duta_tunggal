<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Auth;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SuratJalan>
 */
class SuratJalanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sj_number' => 'SJ-' . $this->faker->unique()->numerify('####-##-###'),
            'issued_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'signed_by' => User::inRandomOrder()->first()->id,
            'status' => $this->faker->boolean(10), // 0 = tidak, 1 = terbit,
            'created_by' => User::inRandomOrder()->first()->id,
        ];
    }
}
