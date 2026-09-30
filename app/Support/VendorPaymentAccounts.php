<?php

namespace App\Support;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * Akun (COA) yang boleh dipilih pada Pembayaran Vendor menurut metode pembayaran — satu definisi
 * untuk form Filament, validasi server, dan checklist kesiapan.
 *
 *  Cash                         → akun kas pembayar (is_cash_bank / cash candidates)
 *  Bank Transfer / Transfer     → akun bank pembayar (is_cash_bank / bank candidates)
 *  Deposit                      → akun uang muka vendor / aset
 *  Credit                       → akun hutang usaha / liabilitas
 *
 * Akun induk (1110/1111/1112), DEPOSITO, dan INVESTASI TIDAK pernah muncul (lihat ChartOfAccount::scopeCashBank).
 */
class VendorPaymentAccounts
{
    public static function query(?string $paymentMethod): Builder
    {
        $method = strtolower(trim((string) $paymentMethod));

        return match ($method) {
            'cash' => ChartOfAccount::query()->cashBank('cash'),
            'bank transfer', 'transfer' => ChartOfAccount::query()->cashBank('bank'),
            'deposit' => ChartOfAccount::query()
                ->where('chart_of_accounts.is_active', true)
                ->where(function ($builder) {
                    $builder->where('chart_of_accounts.code', config('coa.vendor_deposit', '1113'))
                        ->orWhere(function ($nested) {
                            $nested->where('chart_of_accounts.type', 'asset')
                                ->where(function ($assetBuilder) {
                                    $assetBuilder->where('chart_of_accounts.name', 'LIKE', '%deposit%')
                                        ->orWhere('chart_of_accounts.name', 'LIKE', '%uang muka%');
                                });
                        });
                })
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('chart_of_accounts as child')
                        ->whereNull('child.deleted_at')
                        ->where(function ($c) {
                            $c->whereColumn('child.parent_id', 'chart_of_accounts.id')
                                ->orWhereRaw("child.code LIKE CONCAT(chart_of_accounts.code, '.%')");
                        });
                }),
            'credit' => ChartOfAccount::query()
                ->where('chart_of_accounts.is_active', true)
                ->where(function ($builder) {
                    $builder->where('chart_of_accounts.code', 'LIKE', '21%')
                        ->orWhere('chart_of_accounts.type', 'liability');
                })
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('chart_of_accounts as child')
                        ->whereNull('child.deleted_at')
                        ->where(function ($c) {
                            $c->whereColumn('child.parent_id', 'chart_of_accounts.id')
                                ->orWhereRaw("child.code LIKE CONCAT(chart_of_accounts.code, '.%')");
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
     * Default akun: ambil kandidat pertama yang valid dan terurut berdasarkan kode.
     */
    public static function defaultId(?string $paymentMethod): ?int
    {
        $candidate = static::query($paymentMethod)
            ->orderBy('chart_of_accounts.code')
            ->first();

        return $candidate?->id;
    }

    public static function isAllowed(?int $coaId, ?string $paymentMethod): bool
    {
        if (! $coaId) {
            return false;
        }

        return static::query($paymentMethod)->where('chart_of_accounts.id', $coaId)->exists();
    }
}
