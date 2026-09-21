<?php

namespace App\Console\Commands;

use App\Services\DeliveryOrderTransitions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * T2.3 — status item DO lama sering tertinggal (mis. DO sudah Dikirim tetapi itemnya masih "requested", karena jadwal mengubah
 * status DO langsung). Perintah ini melaporkan (dan dengan --apply memperbaiki) status item dari status DO-nya.
 */
class ResyncDeliveryOrderItemStatus extends Command
{
    protected $signature = 'delivery-orders:resync-item-status {--apply : Tulis perbaikan (tanpa ini hanya melaporkan)}';

    protected $description = 'Selaraskan status item Delivery Order dengan status DO (laporan; --apply untuk memperbaiki)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $total = 0;

        foreach (['request_stock', 'approved', 'delivery_failed', 'reject', 'partial', 'sent', 'received', 'completed'] as $doStatus) {
            $expected = DeliveryOrderTransitions::itemStatusFor($doStatus);
            if ($expected === null) {
                continue;
            }

            $query = DB::table('delivery_order_items as i')
                ->join('delivery_orders as d', 'd.id', '=', 'i.delivery_order_id')
                ->whereNull('i.deleted_at')->whereNull('d.deleted_at')
                ->where('d.status', $doStatus)
                ->where(fn ($q) => $q->where('i.status', '!=', $expected)->orWhereNull('i.status'));

            $count = (clone $query)->count();
            if ($count === 0) {
                continue;
            }

            $rows[] = [$doStatus, $expected, $count];
            $total += $count;

            if ($apply) {
                DB::table('delivery_order_items')
                    ->whereIn('id', (clone $query)->pluck('i.id'))
                    ->update(['status' => $expected, 'updated_at' => now()]);
            }
        }

        if ($rows === []) {
            $this->info('Status item DO sudah selaras dengan status DO.');

            return self::SUCCESS;
        }

        $this->table(['Status DO', 'Seharusnya item', 'Jumlah item tidak selaras'], $rows);
        $this->line($apply ? "Diperbaiki: {$total} item." : "Terdeteksi: {$total} item. Jalankan dengan --apply untuk memperbaiki.");

        return self::SUCCESS;
    }
}
