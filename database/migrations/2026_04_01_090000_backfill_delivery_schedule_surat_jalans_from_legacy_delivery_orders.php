<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('delivery_schedule_delivery_orders')->exists()) {
            return;
        }

        $now = now();
        $inserted = 0;

        DB::table('delivery_schedule_delivery_orders')
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$inserted, $now): void {
                foreach ($rows as $row) {
                    $suratJalanIds = DB::table('surat_jalan_delivery_orders')
                        ->where('delivery_order_id', $row->delivery_order_id)
                        ->pluck('surat_jalan_id');

                    if ($suratJalanIds->isEmpty()) {
                        continue;
                    }

                    $records = $suratJalanIds->map(fn ($suratJalanId) => [
                        'delivery_schedule_id' => $row->delivery_schedule_id,
                        'surat_jalan_id' => $suratJalanId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('delivery_schedule_surat_jalans')->insertOrIgnore($records);
                    $inserted += count($records);
                }
            });

        if ($inserted > 0) {
            logger()->info('Backfilled delivery_schedule_surat_jalans from legacy delivery_schedule_delivery_orders', [
                'inserted' => $inserted,
            ]);
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally not reversed automatically.
    }
};