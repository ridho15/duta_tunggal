<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StockAdjustment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = $this->faker->date();
        $number = 'ADJ-' . date('Ymd', strtotime($date)) . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);

        return [
            'adjustment_number' => $number,
            'adjustment_date' => $date,
            'warehouse_id' => Warehouse::factory(),
            'adjustment_type' => $this->faker->randomElement(['increase', 'decrease']),
            'reason' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['draft', 'approved', 'rejected']),
            'created_by' => User::factory(),
            'approved_by' => $this->faker->optional()->randomElement([User::factory(), null]),
            'approved_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the stock adjustment is for increasing stock.
     */
    public function increase(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => 'increase',
        ]);
    }

    /**
     * Indicate that the stock adjustment is for decreasing stock.
     */
    public function decrease(): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustment_type' => 'decrease',
        ]);
    }

    /**
     * Indicate that the stock adjustment is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the stock adjustment is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the stock adjustment is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}