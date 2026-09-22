<?php

namespace App\Support;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * Akun (COA) yang boleh dipilih pada Penerimaan Customer menurut metode pembayaran — satu definisi
 * untuk form, validasi server, dan checklist kesiapan.
 *
 *  Cash                         → akun kas penerima uang (is_cash_bank)
 *  Transfer / Giro / Cheque     → akun bank penerima uang (is_cash_bank)
 *  Deposit                      → akun deposit/uang muka pelanggan (liabilitas)
 *
 * Akun induk (1110/1111/1112), DEPOSITO, dan INVESTASI TIDAK pernah muncul (lihat ChartOfAccount::scopeCashBank).
 */
class CustomerReceiptAccounts
{
    public static function query(?string $paymentMethod): Builder
    {
        $method = strtolower(trim((string) $paymentMethod));

        return match ($method) {
            'cash' => ChartOfAccount::query()->cashBank('cash'),
            'transfer', 'bank transfer', 'cheque', 'giro' => ChartOfAccount::query()->cashBank('bank'),
            'deposit' => ChartOfAccount::query()
                ->where('is_active', true)
                ->where(function ($builder) {
                    $builder->where('code', config('coa.customer_deposit'))
                        ->orWhere(function ($nested) {
                            $nested->where('type', 'liability')
                                ->where(function ($liabilityBuilder) {
                                    $liabilityBuilder->where('name', 'LIKE', '%deposit%')
                                        ->orWhere('name', 'LIKE', '%titipan%')
                                        ->orWhere('name', 'LIKE', '%uang muka pelanggan%');
                                });
                        });
                }),
            default => ChartOfAccount::query()->cashBank(),
        };
    }

    /** @return array<int, string> id => "(kode) nama" */
    public static function options(?string $paymentMethod): array
    {
        return static::query($paymentMethod)
            ->orderBy('chart_of_accounts.code')
            ->get()
            ->mapWithKeys(fn (ChartOfAccount $coa) => [$coa->id => "({$coa->code}) {$coa->name}"])
            ->toArray();
    }

    /**
     * Default akun: HANYA bila kandidatnya tepat satu. Bila lebih dari satu, dikosongkan supaya user
     * memilih sendiri (dahulu selalu jatuh ke akun induk 1110).
     */
    public static function defaultId(?string $paymentMethod): ?int
    {
        $ids = static::query($paymentMethod)->orderBy('chart_of_accounts.code')->limit(2)->pluck('chart_of_accounts.id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    public static function isAllowed(?int $coaId, ?string $paymentMethod): bool
    {
        if (! $coaId) {
            return false;
        }

        return static::query($paymentMethod)->where('chart_of_accounts.id', $coaId)->exists();
    }
}
