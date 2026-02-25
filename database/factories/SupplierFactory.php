<?php

namespace Database\Factories;

use App\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = $this->faker->company();
        
        return [
            'code'           => 'SUP-' . strtoupper(Str::random(5)),
            // 'name' is handled by accessor/mutator, maps to perusahaan
            'perusahaan'     => $companyName,
            'address'        => $this->faker->address(),
            'phone'          => $this->faker->numerify('021#######'),
            'email'          => $this->faker->unique()->safeEmail(),
            'handphone'      => $this->faker->numerify('08##########'),
            'fax'            => $this->faker->numerify('021#######'),
            'npwp'           => $this->faker->numerify('##.###.###.#-###.###'),
            'tempo_hutang'   => $this->faker->randomElement([0, 15, 30, 45, 60]),
            'kontak_person'  => $this->faker->name(),
            'keterangan'     => $this->faker->optional()->sentence(),
            'cabang_id'      => function () {
                return Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id;
            },
        ];
    }
}
