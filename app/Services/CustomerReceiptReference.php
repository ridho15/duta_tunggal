<?php

namespace App\Services;

use App\Models\CustomerReceipt;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Aturan referensi & bukti pada Penerimaan Customer non-tunai (T1.3).
 *  - Referensi WAJIB untuk Transfer, Giro, Cheque; Cash dan Deposit tidak memerlukannya.
 *  - Bukti (berkas) opsional; daftar menandai non-tunai tanpa bukti.
 *  - Kombinasi referensi + akun + nominal yang sama pada penerimaan lain hanya DIPERINGATKAN (bukan diblokir).
 */
class CustomerReceiptReference
{
    /** @var array<int, string> */
    public const NON_CASH_METHODS = ['Transfer', 'Giro', 'Cheque'];

    public static function isNonCash(?string $method): bool
    {
        return in_array(strtolower((string) $method), array_map('strtolower', self::NON_CASH_METHODS), true);
    }

    public static function requiresReference(?string $method): bool
    {
        return self::isNonCash($method);
    }

    public static function label(?string $method): string
    {
        return match (strtolower((string) $method)) {
            'transfer' => 'No. Referensi Transfer',
            'giro' => 'No. Giro',
            'cheque' => 'No. Cek',
            default => 'No. Referensi',
        };
    }

    /** @return Collection<int, CustomerReceipt> penerimaan lain dengan referensi + akun + nominal yang sama */
    public function duplicatesOf(CustomerReceipt $receipt): Collection
    {
        $reference = trim((string) $receipt->payment_reference);
        if ($reference === '') {
            return collect();
        }

        return CustomerReceipt::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(payment_reference)) = ?', [mb_strtolower($reference)])
            ->where('coa_id', $receipt->coa_id)
            ->whereRaw('ROUND(total_payment, 2) = ?', [round((float) $receipt->total_payment, 2)])
            ->where('id', '!=', $receipt->getKey())
            ->with('customer:id,name')
            ->get();
    }

    public function warnIfDuplicate(CustomerReceipt $receipt): void
    {
        $duplicates = $this->duplicatesOf($receipt);
        if ($duplicates->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Kemungkinan penerimaan ganda')
            ->body('Referensi "'.$receipt->payment_reference.'" dengan akun dan nominal yang sama sudah tercatat pada penerimaan #'
                .$duplicates->pluck('id')->implode(', #').' ('.$duplicates->map(fn ($d) => $d->customer?->name)->filter()->unique()->implode(', ').'). '
                .'Penerimaan ini tetap tersimpan — pastikan bukan pencatatan ganda.')
            ->warning()
            ->persistent()
            ->send();
    }
}
