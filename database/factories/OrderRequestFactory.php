<?php

namespace Database\Factories;

use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderRequest>
 */
class OrderRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencyId = Currency::where('code', 'IDR')->value('id') ?? Currency::query()->inRandomOrder()->value('id');

        return [
            'request_number' => HelperController::generateRequestNumber(),
            'request_date' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'note' => $this->faker->sentence(),
            'created_by' => User::query()->inRandomOrder()->value('id') ?? User::factory()->create()->id,
            'currency_id' => $currencyId,
        ];
    }
}
