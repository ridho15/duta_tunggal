<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Kode error yang ditampilkan ke pengguna (Spesifikasi UI/UX 6.6):
 * "Terjadi kesalahan sistem. Silakan hubungi admin (kode: SJ-500-7KQ2)."
 *
 * Format: {MODUL}-{STATUS}-{REF}. REF sama dengan `error_ref` di log,
 * jadi admin bisa mencari detail error di storage/logs berdasarkan kode itu.
 */
class ErrorCode
{
    protected static ?string $ref = null;

    /** Segmen URL /admin/{segmen} → singkatan modul. */
    public const MODULES = [
        'quotations' => 'QO',
        'sale-orders' => 'SO',
        'delivery-orders' => 'DO',
        'surat-jalans' => 'SJ',
        'sales-invoices' => 'INV',
        'invoices' => 'INV',
        'customer-receipts' => 'BYR',
        'customer-returns' => 'RTJ',
        'return-products' => 'RDO',
        'warehouse-confirmations' => 'GDG',
        'purchase-receipts' => 'TB',
        'quality-control-purchases' => 'QC',
        'inventory-stocks' => 'STK',
        'stock-transfers' => 'PS',
        'stock-opnames' => 'SOP',
        'stock-adjustments' => 'ADJ',
        'order-requests' => 'PR',
        'purchase-orders' => 'PO',
        'purchase-invoices' => 'PINV',
        'vendor-payments' => 'BS',
        'purchase-returns' => 'RTB',
        'cash-bank-transactions' => 'KAS',
        'deposits' => 'KAS',
        'account-receivables' => 'AR',
        'account-payables' => 'AP',
        'payment-requests' => 'DANA',
        'journal-entries' => 'JRN',
        'products' => 'PRD',
        'customers' => 'CUS',
        'suppliers' => 'SUP',
        'users' => 'USR',
    ];

    /** Referensi unik per request (dipakai di log dan di layar). */
    public static function ref(): string
    {
        return static::$ref ??= strtoupper(Str::random(4));
    }

    public static function module(?Request $request = null): string
    {
        $request ??= request();

        $path = (string) $request->path();

        // Request Livewire (/livewire/update) → ambil halaman asal dari Referer.
        if (str_starts_with($path, 'livewire') && ($referer = $request->headers->get('referer'))) {
            $path = ltrim((string) parse_url($referer, PHP_URL_PATH), '/');
        }

        $segments = explode('/', $path);
        $segment = ($segments[0] ?? '') === 'admin' ? ($segments[1] ?? '') : ($segments[0] ?? '');

        return static::MODULES[$segment] ?? 'SYS';
    }

    public static function make(int $status = 500, ?Request $request = null): string
    {
        return static::module($request) . '-' . $status . '-' . static::ref();
    }

    /** Untuk test. */
    public static function reset(): void
    {
        static::$ref = null;
    }
}
