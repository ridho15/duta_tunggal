<?php

namespace Database\Factories;

use App\Models\TaxSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxSetting>
 */
class TaxSettingFactory extends Factory
{
    protected $model = TaxSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['PPN', 'PPH', 'CUSTOM'];
        $type = $this->faker->randomElement($types);

        return [
            'name'           => $type . ' ' . $this->faker->numberBetween(1, 20) . '%',
            'rate'           => $this->faker->randomFloat(2, 1, 20),
            'effective_date' => $this->faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status'         => $this->faker->boolean(80), // 80% chance active
            'type'           => $type,
        ];
    }

    /**
     * Create an active PPN tax setting.
     */
    public function ppn(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'   => 'PPN 11%',
            'rate'   => 11,
            'status' => true,
            'type'   => 'PPN',
        ]);
    }

    /**
     * Create an active PPH tax setting.
     */
    public function pph(): static
    {
        return $this->state(fn (array $attributes) => [
            'name'   => 'PPH 23%',
            'rate'   => 23,
            'status' => true,
            'type'   => 'PPH',
        ]);
    }
}
