<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentRequestFactory extends Factory
{
    protected $model = PaymentRequest::class;

    public function definition(): array
    {
        static $sequence = 1;

        return [
            'request_number'    => 'PR-' . now()->format('Ymd') . '-' . str_pad($sequence++, 4, '0', STR_PAD_LEFT),
            'supplier_id'       => Supplier::factory(),
            'cabang_id'         => fn () => Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id,
            'requested_by'      => User::factory(),
            'approved_by'       => null,
            'request_date'      => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'payment_date'      => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'total_amount'      => $this->faker->numberBetween(1_000_000, 50_000_000),
            'selected_invoices' => [],
            'notes'             => null,
            'approval_notes'    => null,
            'status'            => PaymentRequest::STATUS_DRAFT,
            'approved_at'       => null,
            'vendor_payment_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PaymentRequest::STATUS_DRAFT]);
    }

    public function pendingApproval(): static
    {
        return $this->state(['status' => PaymentRequest::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => PaymentRequest::STATUS_APPROVED,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => PaymentRequest::STATUS_REJECTED,
            'approved_by'    => User::factory(),
            'approved_at'    => now(),
            'approval_notes' => 'Rejected for testing purposes.',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => PaymentRequest::STATUS_PAID,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
