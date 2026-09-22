<?php

namespace App\Observers;

use App\Models\InventoryStock;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menjaga `inventory_stocks.qty_reserved` selaras dengan baris `stock_reservations`.
 *
 * Perbaikan T2.1:
 *  - SIMETRIS: kuantitas yang TURUN kini juga menurunkan qty_reserved (sebelumnya hanya kenaikan yang ditangani,
 *    sehingga penurunan harus dikerjakan manual oleh pemanggil);
 *  - baris stok dipilih DETERMINISTIK (rak cocok → rak null → id terkecil), bukan `first()` sembarang;
 *  - penurunan menguras baris terpilih lalu baris lain produk×gudang yang sama, dan TIDAK PERNAH membuat qty_reserved negatif.
 * qty_reserved tetap dijaga per produk×gudang (jumlahnya sumber kebenaran; pembagian antar baris rak hanya teknis).
 */
class StockReservationObserver
{
    public function created(StockReservation $stockReservation): void
    {
        $this->applyDelta($stockReservation, (float) $stockReservation->quantity);
    }

    public function updated(StockReservation $stockReservation): void
    {
        $delta = (float) $stockReservation->quantity - (float) $stockReservation->getOriginal('quantity');

        if (abs($delta) > 0.00001) {
            $this->applyDelta($stockReservation, $delta);
        }
    }

    public function deleted(StockReservation $stockReservation): void
    {
        $this->applyDelta($stockReservation, -1 * (float) $stockReservation->getOriginal('quantity', $stockReservation->quantity));
    }

    public function restored(StockReservation $stockReservation): void
    {
        $this->applyDelta($stockReservation, (float) $stockReservation->quantity);
    }

    public function forceDeleted(StockReservation $stockReservation): void
    {
        $this->applyDelta($stockReservation, -1 * (float) $stockReservation->quantity);
    }

    /** delta > 0 menaikkan, delta < 0 menurunkan qty_reserved produk×gudang reservasi ini. */
    private function applyDelta(StockReservation $reservation, float $delta): void
    {
        Log::debug('StockReservationObserver: applyDelta', [
            'reservation_id' => $reservation->id,
            'product_id' => $reservation->product_id,
            'warehouse_id' => $reservation->warehouse_id,
            'delta' => $delta,
        ]);

        DB::transaction(function () use ($reservation, $delta) {
            $rows = InventoryStock::where('product_id', $reservation->product_id)
                ->where('warehouse_id', $reservation->warehouse_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($rows->isEmpty()) {
                if ($delta <= 0) {
                    return;
                }

                $rows = collect([InventoryStock::create([
                    'product_id' => $reservation->product_id,
                    'warehouse_id' => $reservation->warehouse_id,
                    'rak_id' => $reservation->rak_id,
                    'qty_available' => 0,
                    'qty_reserved' => 0,
                    'qty_min' => 0,
                ])]);
            }

            $preferred = self::pickRow($rows, $reservation->rak_id);

            if ($delta > 0) {
                $preferred->increment('qty_reserved', $delta);

                return;
            }

            // Penurunan: kuras baris terpilih dulu, lalu baris lain; jangan pernah di bawah nol.
            $remaining = abs($delta);
            $ordered = $rows->sortBy(fn ($row) => $row->id === $preferred->id ? 0 : 1)->values();

            foreach ($ordered as $row) {
                if ($remaining <= 0.00001) {
                    break;
                }

                $take = min($remaining, max(0.0, (float) $row->qty_reserved));
                if ($take > 0) {
                    $row->decrement('qty_reserved', $take);
                    $remaining -= $take;
                }
            }

            if ($remaining > 0.00001) {
                Log::warning('StockReservationObserver: qty_reserved lebih kecil dari reservasi yang dilepas (tidak dibuat negatif)', [
                    'reservation_id' => $reservation->id,
                    'product_id' => $reservation->product_id,
                    'warehouse_id' => $reservation->warehouse_id,
                    'tidak_terkuras' => round($remaining, 4),
                ]);
            }
        });
    }

    /** Rak cocok → rak null → id terkecil. */
    public static function pickRow($rows, ?int $rakId): InventoryStock
    {
        if ($rakId) {
            $match = $rows->firstWhere('rak_id', $rakId);
            if ($match) {
                return $match;
            }
        }

        return $rows->first(fn ($row) => $row->rak_id === null) ?? $rows->first();
    }
}
