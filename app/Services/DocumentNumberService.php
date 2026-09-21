<?php

namespace App\Services;

use App\Models\Cabang;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen penjualan terpusat (T4.1, D11/D31): satu layanan, satu format, urutan ATOMIK (baris urutan dikunci) —
 * dua proses bersamaan tidak pernah mendapat nomor yang sama.
 *
 *   Format : {PREFIX}-{KODECABANG}-{YYMM}-{SEQ4}   mis. SO-JKT-2609-0001
 *   Reset  : bulanan, per cabang (dan per jenis)
 *   Nomor lama TIDAK diubah; hanya nomor baru (flag `sales.controls.central_numbering`) yang memakai format ini.
 */
class DocumentNumberService
{
    /**
     * Jenis dokumen → tabel, kolom nomor, dan prefiks bawaan.
     *
     * @var array<string, array{table: string, column: string, prefix: string, label: string}>
     */
    public const TYPES = [
        'quotation' => ['table' => 'quotations', 'column' => 'quotation_number', 'prefix' => 'QO', 'label' => 'Quotation'],
        'sale_order' => ['table' => 'sale_orders', 'column' => 'so_number', 'prefix' => 'SO', 'label' => 'Sales Order'],
        'delivery_order' => ['table' => 'delivery_orders', 'column' => 'do_number', 'prefix' => 'DO', 'label' => 'Delivery Order'],
        'surat_jalan' => ['table' => 'surat_jalans', 'column' => 'sj_number', 'prefix' => 'SJ', 'label' => 'Surat Jalan'],
        'delivery_schedule' => ['table' => 'delivery_schedules', 'column' => 'schedule_number', 'prefix' => 'SCH', 'label' => 'Jadwal Pengiriman'],
        'invoice' => ['table' => 'invoices', 'column' => 'invoice_number', 'prefix' => 'INV', 'label' => 'Invoice'],
        'invoice_tax' => ['table' => 'invoices', 'column' => 'invoice_number', 'prefix' => 'INV-PJK', 'label' => 'Invoice Pajak'],
        'invoice_non_tax' => ['table' => 'invoices', 'column' => 'invoice_number', 'prefix' => 'INV-NPJK', 'label' => 'Invoice Non-Pajak'],
        'customer_return' => ['table' => 'customer_returns', 'column' => 'return_number', 'prefix' => 'CR', 'label' => 'Retur Customer'],
        // Kode customer (D32): global, tanpa cabang/periode — CUST-00001
        'customer' => ['table' => 'customers', 'column' => 'code', 'prefix' => 'CUST', 'label' => 'Kode Customer'],
    ];

    public static function enabled(): bool
    {
        return (bool) config('sales.controls.central_numbering', false);
    }

    /**
     * Nomor berikutnya — atomik. $cabangId null → cabang pengguna yang login (kode cabang "PST" bila tidak ada).
     */
    public function next(string $type, ?int $cabangId = null, ?CarbonInterface $date = null): string
    {
        $definition = self::TYPES[$type] ?? throw new \InvalidArgumentException("Jenis dokumen \"{$type}\" tidak dikenal.");
        $global = $type === 'customer';   // kode customer: global (cabang 0), tanpa periode
        $cabangId = $global ? null : ($cabangId ?? (Auth::user()?->cabang_id ? (int) Auth::user()->cabang_id : null));
        $period = $global ? '0000' : ($date ?? now())->format('ym');

        return DB::transaction(function () use ($type, $definition, $cabangId, $period, $global) {
            $key = ['type' => $type, 'cabang_id' => (int) $cabangId, 'period' => $period];

            $row = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
            if (! $row) {
                // Dua proses pertama kali bersamaan: yang kalah mendapat pelanggaran unik → ulangi dengan baris yang sudah ada.
                try {
                    DB::table('document_sequences')->insert($key + ['last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // baris sudah dibuat proses lain
                }
                $row = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
            }

            $next = (int) $row->last_number;
            $prefix = $this->prefix($type, $definition, $cabangId);
            $cabangCode = $this->cabangCode($cabangId);

            // Pengaman: lewati nomor yang ternyata sudah ada di tabel dokumen (mis. diinput manual) — urutan tetap monoton.
            do {
                $next++;
                $number = $global
                    ? sprintf('%s-%s', $prefix, str_pad((string) $next, 5, '0', STR_PAD_LEFT))
                    : sprintf('%s-%s-%s-%s', $prefix, $cabangCode, $period, str_pad((string) $next, 4, '0', STR_PAD_LEFT));
                $exists = DB::table($definition['table'])->where($definition['column'], $number)->exists();
            } while ($exists);

            DB::table('document_sequences')->where($key)->update(['last_number' => $next, 'updated_at' => now()]);

            return $number;
        });
    }

    /**
     * Prefiks jenis. Invoice pajak/non-pajak memakai kode Cabang bila terisi (mis. "INV-PJK-001" → "INV-PJK"), selain itu bawaan.
     */
    private function prefix(string $type, array $definition, ?int $cabangId): string
    {
        $column = match ($type) {
            'invoice_tax' => 'kode_invoice_pajak',
            'invoice_non_tax' => 'kode_invoice_non_pajak',
            default => null,
        };

        if ($column && $cabangId) {
            $configured = (string) Cabang::withoutGlobalScopes()->whereKey($cabangId)->value($column);
            $stripped = trim((string) preg_replace('/[-\s]*\d+$/', '', $configured));
            if ($stripped !== '') {
                return strtoupper($stripped);
            }
        }

        return $definition['prefix'];
    }

    private function cabangCode(?int $cabangId): string
    {
        $code = $cabangId ? (string) Cabang::withoutGlobalScopes()->whereKey($cabangId)->value('kode') : '';
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return $code !== '' ? $code : 'PST';
    }

    /**
     * Pola regex nomor berformat baru untuk sebuah jenis (dipakai benih & pemeriksaan): PREFIX-KODE-YYMM-SEQ4.
     */
    public static function pattern(string $type): string
    {
        $definition = self::TYPES[$type];

        return '/^'.preg_quote($definition['prefix'], '/').'-([A-Z0-9]+)-(\d{4})-(\d{4})$/';
    }
}
