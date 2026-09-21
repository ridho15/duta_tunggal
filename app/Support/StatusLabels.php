<?php

namespace App\Support;

use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\SaleOrder;
use Illuminate\Support\Str;

/**
 * Label status TERPUSAT (T7.1, usulan 15): satu tempat untuk menampilkan status dokumen penjualan/keuangan dalam Bahasa Indonesia.
 * Konstanta `STATUS_LABELS` di model tetap menjadi sumber; di sini disatukan + dilengkapi status yang belum punya label.
 * Nilai yang tak dikenal tidak pernah tampil mentah/Inggris: dijadikan judul ("partial_confirmed" → "Partial Confirmed") sebagai jaring terakhir.
 */
class StatusLabels
{
    /** Label tambahan untuk status yang belum tercakup konstanta model. */
    private const EXTRA = [
        'invoice' => ['unpaid' => 'Belum Dibayar', 'canceled' => 'Dibatalkan'],
        'customer_receipt' => ['Draft' => 'Draf', 'Partial' => 'Sebagian', 'Paid' => 'Lunas', 'Cancelled' => 'Dibatalkan'],
        'warehouse_confirmation' => ['request' => 'Menunggu Konfirmasi', 'confirmed' => 'Dikonfirmasi', 'partial_confirmed' => 'Dikonfirmasi Sebagian', 'rejected' => 'Ditolak'],
        'other_sale' => ['draft' => 'Draf', 'posted' => 'Diposting', 'cancelled' => 'Dibatalkan'],
        'deposit' => ['active' => 'Aktif', 'closed' => 'Ditutup', 'used' => 'Terpakai'],
    ];

    /** @return array<string, string> */
    public static function map(string $domain): array
    {
        $base = match ($domain) {
            'invoice' => Invoice::STATUS_LABELS,
            'sale_order' => SaleOrder::STATUS_LABELS,
            'quotation' => Quotation::STATUS_LABELS,
            'delivery_order' => DeliveryOrder::STATUS_LABELS,
            'delivery_schedule' => DeliverySchedule::STATUS_LABELS,
            'customer_return' => CustomerReturn::STATUS_LABELS,
            'credit_note' => CreditNote::STATUS_LABELS,
            default => [],
        };

        return $base + (self::EXTRA[$domain] ?? []);
    }

    public static function label(string $domain, mixed $state): string
    {
        if ($state instanceof \BackedEnum) {
            $state = $state->value;
        }
        if ($state === null || $state === '') {
            return '-';
        }

        $state = (string) $state;
        $map = self::map($domain);

        return $map[$state] ?? $map[strtolower($state)] ?? Str::headline(str_replace('_', ' ', $state));
    }

    /** Untuk `->formatStateUsing(StatusLabels::formatter('invoice'))` pada kolom/entri Filament. */
    public static function formatter(string $domain): \Closure
    {
        return fn ($state): string => self::label($domain, $state);
    }
}
