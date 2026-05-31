<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliverySchedule>
 */
class DeliveryScheduleFactory extends Factory
{
    protected $model = DeliverySchedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cabang =Cabang::factory()->create();
        $driver = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $deliveryMethods = ['internal', 'kurir_internal', 'ekspedisi'];
        $method = $this->faker->randomElement($deliveryMethods);

        $statuses = ['pending', 'pending', 'pending', 'on_the_way', 'on_the_way', 'delivered', 'delivered', 'failed', 'cancelled'];
        $status = $this->faker->randomElement($statuses);

        return [
            'schedule_number' => 'DS-' . $this->faker->unique()->numerify('####-##-###'),
            'scheduled_date' => $this->faker->dateTimeBetween('-1 week', '+2 weeks'),
            'delivery_method' => $method,
            'driver_id' => in_array($method, ['internal', 'kurir_internal']) ? $driver->id : null,
            'vehicle_id' => in_array($method, ['internal', 'kurir_internal']) ? $vehicle->id : null,
            'driver_name' => $method === 'ekspedisi' ? $this->faker->name() : null,
            'vehicle_info' => $method === 'ekspedisi' ? $this->faker->randomElement([
                $this->faker->vehicleRegistration(),
                'JNE - ' . $this->faker->trackingNumber(),
                'SiCepat - ' . $this->faker->trackingNumber(),
                'J&T - ' . $this->faker->trackingNumber(),
            ]) : null,
            'status' => $status,
            'notes' => $this->faker->optional(0.7)->sentence(),
            'created_by' => User::inRandomOrder()->first()->id,
            'cabang_id' => $cabang->id,
        ];
    }

    /**
     * Indicate that the schedule is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the schedule is on the way.
     */
    public function onTheWay(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'on_the_way',
        ]);
    }

    /**
     * Indicate that the schedule is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
        ]);
    }

    /**
     * Indicate that the schedule is failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Indicate that the schedule is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Use internal delivery method.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => 'internal',
            'driver_id' => Driver::factory(),
            'vehicle_id' => Vehicle::factory(),
            'driver_name' => null,
            'vehicle_info' => null,
        ]);
    }

    /**
     * Use kurir internal delivery method.
     */
    public function kurirInternal(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => 'kurir_internal',
            'driver_id' => Driver::factory(),
            'vehicle_id' => Vehicle::factory(),
            'driver_name' => null,
            'vehicle_info' => null,
        ]);
    }

    /**
     * Use ekspedisi (third party) delivery method.
     */
    public function ekspedisi(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => 'ekspedisi',
            'driver_id' => null,
            'vehicle_id' => null,
            'driver_name' => $this->faker->randomElement(['JNE Express', 'SiCepat', 'J&T Express', 'Pos Indonesia', 'TIKI', 'Ninja Van']),
            'vehicle_info' => $this->faker->randomElement([
                'Resi: ' . $this->faker->numerify('JN########'),
                'Resi: ' . $this->faker->numerify('SC########'),
                'Resi: ' . $this->faker->numerify('JT########'),
            ]),
        ]);
    }

    /**
     * Create schedule for a specific cabang.
     */
    public function forCabang(Cabang $cabang): static
    {
        return $this->state(fn (array $attributes) => [
            'cabang_id' => $cabang->id,
        ]);
    }
}