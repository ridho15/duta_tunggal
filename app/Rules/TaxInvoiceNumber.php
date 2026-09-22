<?php

namespace App\Rules;

use App\Models\Invoice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Nomor Faktur Pajak (NSFP) 16 digit: `010.000-26.12345678` (kode transaksi 3 digit · seri 3 digit · tahun 2 digit · nomor 8 digit).
 * Pemisah `.` `-` dan spasi opsional; disimpan dalam bentuk baku `000.000-00.00000000`.
 *
 * Kosong dianggap lolos (keputusan D16: faktur pajak boleh diisi belakangan — hanya diperingatkan, tidak diblokir).
 * Bila diisi: format harus benar dan nomor tidak boleh dipakai invoice lain (penjualan maupun pembelian).
 */
class TaxInvoiceNumber implements ValidationRule
{
    public function __construct(private readonly ?int $exceptInvoiceId = null) {}

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    public static function isValidFormat(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match('/^[\d.\-\s]+$/', $value) === 1 && strlen(self::digits($value)) === 16;
    }

    /** Bentuk baku bila formatnya sah; selain itu nilai apa adanya (dipangkas); kosong → null. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! self::isValidFormat($value)) {
            return $value;
        }

        $d = self::digits($value);

        return substr($d, 0, 3).'.'.substr($d, 3, 3).'-'.substr($d, 6, 2).'.'.substr($d, 8, 8);
    }

    public static function isUsed(string $value, ?int $exceptInvoiceId = null): bool
    {
        return Invoice::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotNull('tax_invoice_number')
            ->whereRaw("REPLACE(REPLACE(REPLACE(tax_invoice_number, '.', ''), '-', ''), ' ', '') = ?", [self::digits($value)])
            ->when($exceptInvoiceId, fn ($query) => $query->where('id', '!=', $exceptInvoiceId))
            ->exists();
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (trim((string) $value) === '') {
            return;
        }

        if (! self::isValidFormat((string) $value)) {
            $fail('Format nomor faktur pajak tidak valid. Gunakan 16 digit, mis. 010.000-26.12345678.');

            return;
        }

        if (self::isUsed((string) $value, $this->exceptInvoiceId)) {
            $fail("Nomor faktur pajak '".self::normalize((string) $value)."' sudah digunakan pada invoice lain.");
        }
    }
}
