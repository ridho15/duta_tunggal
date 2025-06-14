<?php

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rak>
 */
class RakFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true); // Contoh: "Rak Besi"
        $code = 'RAK-' . strtoupper(Str::random(4)); // Contoh: RAK-X1ZP

        return [
            'name' => $name,
            'code' => $code,
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id,
        ];
    }
}
