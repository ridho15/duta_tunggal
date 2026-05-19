<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$now = now();
$fixture = [
    'do_number' => 'DO-TEST-SJ-SELECT',
    'sj_number' => 'SJ-TEST-SJ-SELECT',
];

DB::transaction(function () use ($now, $fixture) {
    $user = DB::table('users')->where('email', 'ralamzah@gmail.com')->first() ?? DB::table('users')->orderBy('id')->first();
    if (!$user) {
        throw new RuntimeException('No user found to create surat jalan fixture.');
    }

    $warehouseId = DB::table('warehouses')->where('cabang_id', $user->cabang_id)->value('id')
        ?? DB::table('warehouses')->value('id');

    $existingSj = DB::table('surat_jalans')->where('sj_number', $fixture['sj_number'])->first();
    $existingDo = DB::table('delivery_orders')->where('do_number', $fixture['do_number'])->first();

    if ($existingSj && $existingDo) {
        $pivotExists = DB::table('surat_jalan_delivery_orders')
            ->where('surat_jalan_id', $existingSj->id)
            ->where('delivery_order_id', $existingDo->id)
            ->exists();

        if (!$pivotExists) {
            DB::table('surat_jalan_delivery_orders')->insert([
                'surat_jalan_id' => $existingSj->id,
                'delivery_order_id' => $existingDo->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        echo "Surat Jalan fixture already exists\n";
        return;
    }

    if ($existingSj) {
        DB::table('surat_jalan_delivery_orders')->where('surat_jalan_id', $existingSj->id)->delete();
        DB::table('surat_jalans')->where('id', $existingSj->id)->delete();
    }

    if ($existingDo) {
        DB::table('surat_jalan_delivery_orders')->where('delivery_order_id', $existingDo->id)->delete();
        DB::table('delivery_orders')->where('id', $existingDo->id)->delete();
    }

    $doRow = [
        'delivery_date' => $now,
        'driver_id' => null,
        'vehicle_id' => null,
        'status' => 'approved',
        'notes' => 'Fixture for surat-jalan-select playwright test',
        'additional_cost' => 0,
        'additional_cost_description' => null,
        'created_by' => $user->id,
        'do_number' => $fixture['do_number'],
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (Schema::hasColumn('delivery_orders', 'warehouse_id')) {
        $doRow['warehouse_id'] = $warehouseId;
    }
    if (Schema::hasColumn('delivery_orders', 'cabang_id')) {
        $doRow['cabang_id'] = $user->cabang_id;
    }

    $doId = DB::table('delivery_orders')->insertGetId($doRow);

    $sjRow = [
        'sj_number' => $fixture['sj_number'],
        'issued_at' => $now,
        'signed_by' => null,
        'status' => 1,
        'created_by' => $user->id,
        'document_path' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if (Schema::hasColumn('surat_jalans', 'cabang_id')) {
        $sjRow['cabang_id'] = $user->cabang_id;
    }

    $sjId = DB::table('surat_jalans')->insertGetId($sjRow);

    DB::table('surat_jalan_delivery_orders')->insert([
        'surat_jalan_id' => $sjId,
        'delivery_order_id' => $doId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    echo "Created Surat Jalan fixture: {$fixture['sj_number']} linked to {$fixture['do_number']}\n";
});
