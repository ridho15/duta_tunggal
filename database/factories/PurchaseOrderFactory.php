<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::inRandomOrder()->first()->id, // ganti sesuai seeder supplier
            'po_number' => 'PO-' . strtoupper(Str::random(6)),
            'order_date' => now()->subDays(rand(1, 30)),
            'status' => $this->faker->randomElement(['draft', 'approved', 'partially_received', 'completed', 'closed']),
            'expected_date' => now()->addDays(rand(3, 14)),
            'total_amount' => $this->faker->numberBetween(50000, 2000000),
            'is_asset' => false,
            'close_reason' => $this->faker->optional()->sentence(),
            'date_approved' => now(),
            'approved_by' => 1, // user id
            'warehouse_id' => 1, // warehouse id
            'tempo_hutang' => rand(0, 60),
            'note' => $this->faker->optional()->sentence(),
            'close_requested_by' => 1,
            'close_requested_at' => now(),
            'closed_by' => 1,
            'closed_at' => now(),
            'completed_by' => 1,
            'completed_at' => now(),
            'created_by' => 1,
            'refer_model_type' => null,
            'refer_model_id' => null,
            'is_import' => false,
            'ppn_option' => 'standard',
            'cabang_id' => function () {
                return Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id;
            },
        ];
    }
}
