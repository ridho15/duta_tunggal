<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $now = now();

    $user = User::query()->where('email', 'superadmin@gmail.com')->first() ?? User::query()->orderBy('id')->first();
    if (! $user) {
        throw new RuntimeException('No user found for Bau Bau Delivery Schedule Playwright fixture');
    }

    $bauBau = Cabang::query()->updateOrCreate(
        ['kode' => 'PW-BAU-001'],
        [
            'nama' => 'Bau Bau',
            'alamat' => 'Playwright Fixture Address',
            'telepon' => '081234567890',
            'status' => true,
            'warna_background' => '#1f2937',
            'tipe_penjualan' => 'Semua',
        ]
    );

    User::query()->where('id', $user->id)->update([
        'manage_type' => 'all',
        'cabang_id' => $bauBau->id,
    ]);

    $warehouse = Warehouse::query()->updateOrCreate(
        ['kode' => 'PW-BAU-WH-001'],
        [
            'name' => 'Warehouse Bau Bau',
            'cabang_id' => $bauBau->id,
            'tipe' => 'Besar',
            'location' => 'Playwright Fixture Warehouse',
            'telepon' => '081234567891',
            'status' => true,
            'warna_background' => '#111827',
        ]
    );

    $driver = Driver::query()->updateOrCreate(
        ['license' => 'PW-BAU-DRV-001'],
        [
            'name' => 'Driver Bau Bau',
            'phone' => '081234567892',
            'cabang_id' => $bauBau->id,
        ]
    );

    $vehicle = Vehicle::query()->updateOrCreate(
        ['plate' => 'DT-BAU-001'],
        [
            'type' => 'Box',
            'capacity' => '1 ton',
            'cabang_id' => $bauBau->id,
        ]
    );

    $usedDeliveryOrder = DeliveryOrder::query()->updateOrCreate(
        ['do_number' => 'DO-PW-BAU-USED-001'],
        [
            'delivery_date' => $now,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'notes' => 'Playwright fixture used DO',
            'additional_cost' => 0,
            'additional_cost_description' => null,
            'created_by' => $user->id,
            'cabang_id' => $bauBau->id,
        ]
    );

    $usedSuratJalan = SuratJalan::query()->updateOrCreate(
        ['sj_number' => 'SJ-PW-BAU-USED-001'],
        [
            'issued_at' => $now,
            'signed_by' => $user->id,
            'status' => 1,
            'created_by' => $user->id,
            'document_path' => null,
            'cabang_id' => $bauBau->id,
        ]
    );

    $availableSuratJalan = SuratJalan::query()->updateOrCreate(
        ['sj_number' => 'SJ-PW-BAU-AVAILABLE-001'],
        [
            'issued_at' => $now,
            'signed_by' => $user->id,
            'status' => 1,
            'created_by' => $user->id,
            'document_path' => null,
            'cabang_id' => $bauBau->id,
        ]
    );

    $schedule = DeliverySchedule::query()->updateOrCreate(
        ['schedule_number' => 'SCH-PW-BAU-001'],
        [
            'scheduled_date' => $now,
            'delivery_method' => 'internal',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'pending',
            'notes' => 'Playwright fixture schedule',
            'created_by' => $user->id,
            'cabang_id' => $bauBau->id,
        ]
    );

    DB::table('delivery_schedule_surat_jalans')->updateOrInsert(
        [
            'delivery_schedule_id' => $schedule->id,
            'surat_jalan_id' => $usedSuratJalan->id,
        ],
        [
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    DB::table('surat_jalan_delivery_orders')->updateOrInsert(
        [
            'surat_jalan_id' => $usedSuratJalan->id,
            'delivery_order_id' => $usedDeliveryOrder->id,
        ],
        [
            'created_at' => $now,
            'updated_at' => $now,
        ]
    );

    echo "✅ Bau Bau Delivery Schedule fixture ready\n";
    echo "   Cabang    : {$bauBau->nama}\n";
    echo "   Used SJ   : {$usedSuratJalan->sj_number}\n";
    echo "   Available : {$availableSuratJalan->sj_number}\n";
    echo "   Schedule  : {$schedule->schedule_number}\n";
});