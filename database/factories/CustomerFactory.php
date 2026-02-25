<?php

namespace Database\Factories;

use App\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => $this->faker->name(),
            'code'              => $this->faker->unique()->regexify('CUST-[A-Z0-9]{5}'),
            'address'           => $this->faker->address(),
            'telephone'         => $this->faker->numerify('021#######'),
            'phone'             => $this->faker->numerify('08##########'),
            'email'             => $this->faker->unique()->safeEmail(),
            'perusahaan'        => $this->faker->company(),
            'tipe'              => $this->faker->randomElement(['PKP', 'PRI']),
            'fax'               => $this->faker->numerify('021#######'),
            'tempo_kredit'      => $this->faker->randomElement([0, 15, 30, 45, 60]),
            'kredit_limit'      => $this->faker->numberBetween(1000000, 10000000),
            'tipe_pembayaran'   => $this->faker->randomElement(['Bebas','COD (Bayar Lunas)','Kredit']),
            'nik_npwp'          => $this->faker->numerify('################'),
            'keterangan'        => $this->faker->optional()->sentence(),
            'isSpecial'         => $this->faker->boolean(50),
            'cabang_id'         => function () {
                return Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id;
            },
        ];
    }
}
