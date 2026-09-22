<?php

namespace Database\Seeders;

use App\Models\DeliverySchedule;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a variety of delivery schedules with different statuses
        DeliverySchedule::factory()
            ->count(15)
            ->create();

        // Create specific schedules for each status
        DeliverySchedule::factory()->pending()->count(5)->create();
        DeliverySchedule::factory()->onTheWay()->count(3)->create();
        DeliverySchedule::factory()->delivered()->count(4)->create();
        DeliverySchedule::factory()->failed()->count(2)->create();
        DeliverySchedule::factory()->cancelled()->count(1)->create();

        // Create schedules with different delivery methods
        DeliverySchedule::factory()->internal()->count(8)->create();
        DeliverySchedule::factory()->kurirInternal()->count(5)->create();
        DeliverySchedule::factory()->ekspedisi()->count(7)->create();
    }
}