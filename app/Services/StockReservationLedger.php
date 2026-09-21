<?php

namespace App\Services;

use App\Models\StockReservation;
use App\Models\StockReservationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Buku besar reservasi stok penjualan (T2.1) — SATU-SATUNYA penulis reservasi SO/DO.
 *
 * Setiap perubahan dijalankan dalam transaksi bersama efeknya pada `inventory_stocks.qty_reserved`
 * (dilakukan StockReservationObserver, simetris dan tidak pernah negatif) dan dicatat sebagai
 * `stock_reservation_events` (append-only) agar selalu dapat dijawab "kenapa stok ini tertahan / terlepas".
 *
 * Reservasi Material Issue TIDAK lewat sini (memakai StockReservationService); model dan observernya dipakai bersama.
 */
class StockReservationLedger
{
    /**
     * Buku besar aktif bila salah satu flag stok hidup: `ledger`, `strict_dispatch` (idempotensi pengiriman/pengembalian gagal-kirim)
     * atau `reserve_on_so_approve` (reservasi SO dan DO harus terpetakan ke item SO agar tidak terhitung ganda).
     */
    public static function enabled(): bool
    {
        return (bool) (config('sales.stock.ledger', false)
            || config('sales.stock.strict_dispatch', false)
            || config('sales.stock.reserve_on_so_approve', false));
    }

    /**
     * @param  array<string, mixed>  $attributes  product_id, warehouse_id, quantity, rak_id?, sale_order_id?, sale_order_item_id?, delivery_order_id?
     */
    public function reserve(array $attributes, string $reason): StockReservation
    {
        return DB::transaction(function () use ($attributes, $reason) {
            $reservation = StockReservation::create($attributes);
            $this->record($reservation, StockReservationEvent::RESERVED, (float) $reservation->quantity, $reason);

            return $reservation;
        });
    }

    /** Ubah kuantitas ke $newQty (≤ 0 = lepas). Perubahan tercatat sebagai selisih bertanda. */
    public function adjust(StockReservation $reservation, float $newQty, string $reason): void
    {
        if ($newQty <= 0.00001) {
            $this->release($reservation, $reason);

            return;
        }

        $old = (float) $reservation->quantity;
        if (abs($newQty - $old) < 0.00001) {
            return;
        }

        DB::transaction(function () use ($reservation, $newQty, $old, $reason) {
            $reservation->update(['quantity' => $newQty]);
            $this->record($reservation, StockReservationEvent::ADJUSTED, $newQty - $old, $reason);
        });
    }

    /** Lepas reservasi (stok bebas kembali). */
    public function release(StockReservation $reservation, string $reason, string $event = StockReservationEvent::RELEASED): void
    {
        DB::transaction(function () use ($reservation, $reason, $event) {
            $quantity = (float) $reservation->quantity;
            $snapshot = $reservation->replicate();
            $snapshot->id = $reservation->id;

            $reservation->delete();   // observer menurunkan qty_reserved

            $this->record($snapshot, $event, $quantity, $reason);
        });
    }

    /** Reservasi terkonsumsi = barang benar-benar keluar (stok fisik sudah berkurang oleh gerakan stok). */
    public function consume(StockReservation $reservation, string $reason): void
    {
        $this->release($reservation, $reason, StockReservationEvent::CONSUMED);
    }

    /**
     * Lepas/konsumsi semua reservasi yang cocok dengan $scope. Mengembalikan jumlah baris.
     *
     * @param  callable(Builder): mixed  $scope
     */
    public function releaseWhere(callable $scope, string $reason, string $event = StockReservationEvent::RELEASED): int
    {
        $query = StockReservation::query()->whereNull('material_issue_id');
        $scope($query);

        $count = 0;
        foreach ($query->orderBy('id')->get() as $reservation) {
            $this->release($reservation, $reason, $event);
            $count++;
        }

        return $count;
    }

    /** Lepas/konsumsi semua reservasi milik satu DO. */
    public function releaseForDeliveryOrder(int $deliveryOrderId, string $reason, string $event = StockReservationEvent::RELEASED): int
    {
        return $this->releaseWhere(fn (Builder $q) => $q->where('delivery_order_id', $deliveryOrderId), $reason, $event);
    }

    /** Lepas semua reservasi milik satu SO (level SO dan DO-nya). */
    public function releaseForSaleOrder(int $saleOrderId, string $reason, string $event = StockReservationEvent::RELEASED): int
    {
        return $this->releaseWhere(fn (Builder $q) => $q->where('sale_order_id', $saleOrderId), $reason, $event);
    }

    private function record(StockReservation $reservation, string $event, float $quantity, string $reason): void
    {
        try {
            StockReservationEvent::create([
                'stock_reservation_id' => $reservation->id,
                'sale_order_id' => $reservation->sale_order_id,
                'sale_order_item_id' => $reservation->sale_order_item_id ?? null,
                'delivery_order_id' => $reservation->delivery_order_id,
                'product_id' => $reservation->product_id,
                'warehouse_id' => $reservation->warehouse_id,
                'quantity' => $quantity,
                'event' => $event,
                'reason' => mb_substr($reason, 0, 255),
                'actor_id' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            // Riwayat tidak boleh menggagalkan operasi stok (mis. migrasi belum dijalankan).
            Log::warning('StockReservationLedger: gagal mencatat event reservasi', ['error' => $e->getMessage(), 'event' => $event]);
        }
    }
}
