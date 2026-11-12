<?php

namespace App\Services;

use App\Models\ApprovalOverride;
use App\Models\Deposit;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi Kasir untuk SO COD yang TERKAIT SALDO TITIP customer (UAT 13 Okt 2026, B11).
 *
 * Dulu verifikasi hanya catatan (opsional memakai saldo). Sekarang:
 *  - saldo titip customer WAJIB cukup menutup total SO (barang + ongkos kirim) — kurang → ditolak dengan selisih yang jelas
 *    (arahkan kasir menambah Saldo Titip Customer lebih dulu);
 *  - total SO otomatis dipotong dari saldo dalam satu transaksi dengan baris saldo dikunci (tidak ada dua verifikasi yang
 *    memakai saldo yang sama) dan tercatat di log saldo (tipe `use`, bereferensi SO);
 *  - Owner / Super Admin / Finance Manager dapat mengecualikan saldo kurang dengan alasan >= 10 karakter (tercatat di ApprovalOverride);
 *    pada pengecualian saldo yang ada dipakai sebesar yang tersedia (sisanya dibayar saat pengiriman);
 *  - pembatalan SO mengembalikan saldo yang dipakai (tipe `return`).
 */
class SaleOrderCashierVerification
{
    public const OVERRIDE_ROLES = ['Owner', 'Super Admin', 'Finance Manager'];

    /**
     * @return array{used: float, shortfall: float, overridden: bool}
     *
     * @throws ValidationException
     */
    public function verify(SaleOrder $saleOrder, ?string $note = null, ?string $overrideReason = null, ?User $actor = null): array
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($saleOrder, $note, $overrideReason, $actor) {
            $locked = SaleOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($saleOrder->getKey());

            if (! $locked->isCod()) {
                throw ValidationException::withMessages(['cashier' => "Sales Order {$locked->so_number} bukan tipe pembayaran COD sehingga tidak memerlukan verifikasi kasir."]);
            }
            if ($locked->isCashierApproved()) {
                throw ValidationException::withMessages(['cashier' => "Sales Order {$locked->so_number} sudah diverifikasi kasir."]);
            }

            $required = $locked->grandTotal();
            // Saldo titip = Σ semua deposit aktif customer (setiap setoran / kelebihan bayar adalah baris deposit sendiri).
            $deposits = $this->activeDeposits($locked);
            $balance = (float) $deposits->sum('remaining_amount');
            $shortfall = max(0.0, round($required - $balance, 2));
            $overridden = false;

            if ($shortfall > 0.005) {
                $canOverride = $actor && $actor->hasRole(self::OVERRIDE_ROLES);
                $reason = trim((string) $overrideReason);

                if (! $canOverride || mb_strlen($reason) < 10) {
                    $message = sprintf(
                        'Saldo Titip Customer tidak mencukupi untuk SO %s: dibutuhkan Rp %s (barang + ongkos kirim), saldo Rp %s, kurang Rp %s. Tambahkan Saldo Titip Customer terlebih dahulu.',
                        $locked->so_number,
                        number_format($required, 0, ',', '.'),
                        number_format($balance, 0, ',', '.'),
                        number_format($shortfall, 0, ',', '.')
                    );
                    if ($canOverride) {
                        $message .= ' Atau isi alasan pengecualian (minimal 10 karakter).';
                    }

                    throw ValidationException::withMessages(['cashier' => $message]);
                }

                ApprovalOverride::create([
                    'document_type' => 'sale_order',
                    'document_id' => $locked->getKey(),
                    'user_id' => $actor->getKey(),
                    'amount' => $required,
                    'reason' => $reason,
                    'context' => ['kind' => 'cashier_deposit_shortfall', 'document_number' => $locked->so_number, 'balance' => $balance, 'shortfall' => $shortfall],
                ]);
                $overridden = true;
            }

            $use = round(min($required, $balance), 2);
            $left = $use;
            foreach ($deposits as $deposit) {   // setoran terlama dipakai lebih dulu
                if ($left <= 0.005) {
                    break;
                }
                $take = round(min($left, (float) $deposit->remaining_amount), 2);
                if ($take <= 0.005) {
                    continue;
                }
                $deposit->update([
                    'used_amount' => (float) $deposit->used_amount + $take,
                    'remaining_amount' => round((float) $deposit->remaining_amount - $take, 2),
                ]);
                $deposit->depositLog()->create([
                    'type' => 'use',
                    'reference_type' => SaleOrder::class,
                    'reference_id' => $locked->id,
                    'amount' => $take,
                    'note' => "Dipakai untuk verifikasi kasir COD SO {$locked->so_number}".($overridden ? ' (PENGECUALIAN saldo kurang)' : '').(filled($note) ? " ({$note})" : ''),
                    'created_by' => $actor?->getKey(),
                ]);
                $left = round($left - $take, 2);
            }

            $locked->update([
                'cashier_approved_by' => $actor?->getKey(),
                'cashier_approved_at' => now(),
                'cashier_note' => $note,
            ]);

            return ['used' => round($use, 2), 'shortfall' => $shortfall, 'overridden' => $overridden];
        });
    }

    /** Deposit aktif customer bersaldo, terlama dulu, dikunci untuk transaksi ini. */
    private function activeDeposits(SaleOrder $saleOrder): \Illuminate\Support\Collection
    {
        if (! $saleOrder->customer) {
            return collect();
        }

        return Deposit::query()
            ->where('from_model_type', $saleOrder->customer::class)->where('from_model_id', $saleOrder->customer_id)
            ->where('status', 'active')->where('remaining_amount', '>', 0)
            ->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * Kembalikan saldo titip yang dipakai SO ini (dipanggil saat SO dibatalkan). Idempoten: hanya sisa (use − return) yang dikembalikan.
     */
    public function refund(SaleOrder $saleOrder, ?User $actor = null): float
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($saleOrder, $actor) {
            if (! $saleOrder->customer) {
                return 0.0;
            }

            // Kembalikan ke tiap baris deposit sebesar yang dipakai SO ini dari baris itu (use − return).
            $deposits = Deposit::query()->where('from_model_type', $saleOrder->customer::class)->where('from_model_id', $saleOrder->customer_id)
                ->orderBy('id')->lockForUpdate()->get();
            $total = 0.0;
            foreach ($deposits as $deposit) {
                $logs = $deposit->depositLog()->where('reference_type', SaleOrder::class)->where('reference_id', $saleOrder->id);
                $used = (float) (clone $logs)->where('type', 'use')->sum('amount');
                $returned = (float) (clone $logs)->where('type', 'return')->sum('amount');
                $refundable = round($used - $returned, 2);
                if ($refundable <= 0.005) {
                    continue;
                }

                $deposit->update([
                    'used_amount' => max(0.0, (float) $deposit->used_amount - $refundable),
                    'remaining_amount' => (float) $deposit->remaining_amount + $refundable,
                ]);
                $deposit->depositLog()->create([
                    'type' => 'return',
                    'reference_type' => SaleOrder::class,
                    'reference_id' => $saleOrder->id,
                    'amount' => $refundable,
                    'note' => "Dikembalikan: SO {$saleOrder->so_number} dibatalkan",
                    'created_by' => $actor?->getKey(),
                ]);
                $total += $refundable;
            }

            return round($total, 2);
        });
    }
}
