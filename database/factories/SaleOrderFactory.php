<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrder>
 */
class SaleOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id'          => Customer::inRandomOrder()->first()?->id ?? Customer::factory()->create()->id,
            'so_number'            => 'SO-' . strtoupper(Str::random(6)),
            'order_date'           => now()->subDays(rand(1, 30)),
            'status'               => $this->faker->randomElement([
                'draft',
                'request_approve',
                'approved',
                'closed',
                'completed',
                'confirmed',
                'received',
                'canceled',
                'reject'
            ]),
            'delivery_date'        => now()->addDays(rand(2, 10)),
            'total_amount'         => $this->faker->numberBetween(100000, 5000000),
            'request_approve_by'   => 1,
            'request_approve_at'   => now(),
            'request_close_by'     => 1,
            'request_close_at'     => now(),
            'approve_by'           => 1,
            'approve_at'           => now(),
            'close_by'             => 1,
            'close_at'             => now(),
            'completed_at'         => now(),
            'shipped_to'           => $this->faker->address(),
            'reject_by'            => 1,
            'reject_at'            => now(),
            'reason_close'         => $this->faker->optional()->sentence(),
            'tipe_pengiriman'      => $this->faker->randomElement(['Ambil Sendiri', 'Kirim Langsung']),
            'cabang_id'            => Cabang::inRandomOrder()->first()->id ?? 1,
        ];
    }
}
