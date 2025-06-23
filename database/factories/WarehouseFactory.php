<?php

namespace Database\Factories;

use App\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'GDG-' . $this->faker->unique()->numerify('###'),
            'name' => 'Gudang ' . $this->faker->word,
            'cabang_id' => Cabang::inRandomOrder()->first()?->id ?? Cabang::factory(),
            'tipe' => $this->faker->randomElement(['Kecil', 'Besar']),
            'location' => $this->faker->address,
            'telepon' => $this->faker->phoneNumber,
            'status' => $this->faker->boolean,
            'warna_background' => $this->faker->safeHexColor,
        ];
    }
}
