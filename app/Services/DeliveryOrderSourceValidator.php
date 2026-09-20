<?php

namespace App\Services;

use App\Models\SaleOrder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tunggal "SO mana yang boleh dijadikan sumber Delivery Order".
 *
 * Dipanggil dari SEMUA pintu masuk pembuatan/perubahan DO (form DO, halaman Create/Edit,
 * relation manager Driver & Kendaraan) supaya aturannya tidak berbeda-beda:
 *  1. status SO harus SaleOrder::DELIVERABLE_STATUSES (bukan completed/closed/canceled/...)
 *  2. SO masih punya sisa kuantitas yang belum terikat DO manapun
 *  3. bila lebih dari satu SO digabung: customer SAMA dan alamat kirim SAMA
 */
class DeliveryOrderSourceValidator
{
    public function __construct(private SaleOrderDeliveryProgress $progress)
    {
    }

    /**
     * Normalisasi alamat untuk perbandingan: huruf kecil, spasi tunggal, tanpa tanda baca di ujung.
     */
    public static function normalizeAddress(?string $address): string
    {
        $value = Str::of((string) $address)->lower()->squish()->trim(" \t\n\r\0\x0B,.;-")->toString();

        return $value;
    }

    /**
     * @param  array<int|string>  $saleOrderIds
     * @param  int|null  $excludeDeliveryOrderId  DO yang sedang diedit (kuantitasnya tidak dihitung sebagai "terikat")
     * @param  array<int>  $alreadyLinkedIds  SO yang sudah terhubung ke DO tsb: status & sisa tidak diperiksa ulang
     * @return array<int, string> daftar pesan kesalahan (kosong = valid)
     */
    public function errors(array $saleOrderIds, ?int $excludeDeliveryOrderId = null, array $alreadyLinkedIds = []): array
    {
        $ids = collect($saleOrderIds)->flatten()->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $orders = SaleOrder::withoutGlobalScopes()->with('customer')->whereIn('id', $ids)->get()->keyBy('id');
        $linked = collect($alreadyLinkedIds)->map(fn ($v) => (int) $v)->all();
        $errors = [];

        foreach ($ids as $id) {
            $order = $orders->get($id);
            if (! $order) {
                $errors[] = "Sales Order dengan ID {$id} tidak ditemukan.";
                continue;
            }

            if (in_array($id, $linked, true)) {
                continue;
            }

            if (! in_array($order->status, SaleOrder::DELIVERABLE_STATUSES, true)) {
                $errors[] = "Sales Order {$order->so_number} berstatus \"" . SaleOrder::statusLabel($order->status)
                    . '" sehingga tidak dapat dipakai untuk Delivery Order. Hanya SO yang Disetujui / Dikonfirmasi / Dikirim Sebagian.';
                continue;
            }

            $available = $this->progress->forSaleOrder($order, $excludeDeliveryOrderId)['totals']['available'];
            if ($available <= 0) {
                $errors[] = "Sales Order {$order->so_number} tidak memiliki sisa kuantitas yang dapat dikirim "
                    . '(seluruhnya sudah terkirim atau sudah dialokasikan ke Delivery Order lain).';
            }
        }

        return array_merge($errors, $this->mixErrors($ids->all()));
    }

    /**
     * Pemeriksaan penggabungan: customer dan alamat kirim harus sama. Dipisah agar form dapat
     * langsung mereset pilihan saat user menambah SO yang tidak cocok.
     *
     * @param  array<int|string>  $saleOrderIds
     * @return array<int, string>
     */
    public function mixErrors(array $saleOrderIds): array
    {
        $ids = collect($saleOrderIds)->flatten()->filter()->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->count() < 2) {
            return [];
        }

        $orders = SaleOrder::withoutGlobalScopes()->whereIn('id', $ids)->get();
        $errors = [];

        if ($orders->pluck('customer_id')->unique()->count() > 1) {
            $errors[] = 'Semua Sales Order dalam satu Delivery Order harus berasal dari customer yang sama.';
        }

        $empty = $orders->filter(fn ($o) => self::normalizeAddress($o->shipped_to) === '');
        if ($empty->isNotEmpty()) {
            $errors[] = 'Alamat kirim belum diisi pada Sales Order ' . $empty->pluck('so_number')->implode(', ')
                . '. Lengkapi alamat kirim sebelum digabung dalam satu Delivery Order.';
        } elseif ($orders->map(fn ($o) => self::normalizeAddress($o->shipped_to))->unique()->count() > 1) {
            $errors[] = 'Sales Order yang digabung dalam satu Delivery Order harus memiliki alamat kirim yang sama ('
                . $orders->pluck('so_number')->implode(', ') . ').';
        }

        return $errors;
    }

    /**
     * @throws ValidationException
     */
    public function assertValid(array $saleOrderIds, ?int $excludeDeliveryOrderId = null, array $alreadyLinkedIds = [], string $field = 'salesOrders'): void
    {
        $errors = $this->errors($saleOrderIds, $excludeDeliveryOrderId, $alreadyLinkedIds);

        if ($errors !== []) {
            throw ValidationException::withMessages([$field => $errors]);
        }
    }
}
