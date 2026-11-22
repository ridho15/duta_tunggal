<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\ApprovalOverride;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\User;
use App\Observers\CustomerReceiptObserver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifikasi Kasir untuk SO COD yang TERKAIT SALDO TITIP customer (UAT 13 Okt 2026, B11).
 *
 * Dulu verifikasi hanya catatan (opsional memakai saldo). Sekarang:
 *  - saldo titip customer WAJIB cukup menutup total SO (barang + ongkos kirim) — kurang → ditolak dengan selisih yang jelas
 *    (arahkan kasir menambah Saldo Titip Customer lebih dulu);
 *  - total SO DICADANGKAN dari saldo (log `reserve`, bereferensi SO) dalam satu transaksi dengan baris saldo dikunci (tidak ada
 *    dua verifikasi yang memakai saldo yang sama). Belum ada jurnal dan saldo deposit belum berkurang: uang titipan masih utang
 *    kepada customer (2160.04) sampai invoice SO terbit;
 *  - saat invoice SO tersebut terbit, cadangan direalisasi menjadi Penerimaan Customer metode Deposit (Dr 2160.04 / Cr Piutang)
 *    yang melunasi piutang invoice (settleInvoice) — subledger deposit tetap = GL 2160.04, subledger piutang = GL 1120;
 *  - Owner / Super Admin / Finance Manager dapat mengecualikan saldo kurang dengan alasan >= 10 karakter (tercatat di ApprovalOverride);
 *    pada pengecualian saldo yang ada dicadangkan sebesar yang tersedia (sisanya dibayar saat pengiriman);
 *  - pembatalan SO melepas cadangan (log `release`).
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
            // Saldo titip bebas = Σ deposit aktif customer − cadangan COD SO lain yang belum direalisasi.
            $deposits = $this->activeDeposits($locked);
            $balance = $locked->customer_id ? Deposit::availableForCustomer((int) $locked->customer_id) : 0.0;
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

            // Cadangan dicatat pada deposit terlama yang masih bersaldo (log wajib menunjuk satu baris deposit); jumlahnya yang mengikat.
            $use = round(min($required, $balance), 2);
            if ($use > 0.005) {
                $deposits->first()->depositLog()->create([
                    'type' => 'reserve',
                    'reference_type' => SaleOrder::class,
                    'reference_id' => $locked->id,
                    'amount' => $use,
                    'note' => "Dicadangkan untuk verifikasi kasir COD SO {$locked->so_number}".($overridden ? ' (PENGECUALIAN saldo kurang)' : '').(filled($note) ? " ({$note})" : ''),
                    'created_by' => $actor?->getKey() ?? $deposits->first()->created_by,
                ]);
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
     * Kembalikan saldo titip SO ini (dipanggil saat SO dibatalkan). Idempoten:
     *  - cadangan yang belum direalisasi (reserve − release) dilepas;
     *  - data lama (sebelum cadangan) yang sudah memotong saldo (use − return) dikembalikan ke baris depositnya.
     */
    public function refund(SaleOrder $saleOrder, ?User $actor = null): float
    {
        $actor ??= Auth::user();

        return DB::transaction(function () use ($saleOrder, $actor) {
            if (! $saleOrder->customer) {
                return 0.0;
            }

            $released = $this->release($saleOrder, Deposit::reservedForCustomer((int) $saleOrder->customer_id, (int) $saleOrder->id),
                "Dilepas: SO {$saleOrder->so_number} dibatalkan", $actor);

            // Data lama: kembalikan ke tiap baris deposit sebesar yang dipakai SO ini dari baris itu (use − return).
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

            return round($total + $released, 2);
        });
    }

    /**
     * Invoice SO COD terbit: realisasikan cadangan saldo titip SO menjadi Penerimaan Customer metode Deposit
     * (Dr Deposit Pelanggan / Cr Piutang) sebesar min(cadangan, sisa piutang). Idempoten — cadangan yang sudah dilepas tidak dipakai lagi.
     *
     * @return float nominal piutang yang dilunasi dari deposit
     *
     * @throws \RuntimeException akun Deposit Pelanggan belum ada (invoice dibatalkan bersama transaksi pemanggil)
     */
    public function settleInvoice(Invoice $invoice): float
    {
        $saleOrder = $this->saleOrderOf($invoice);
        if (! $saleOrder || ! $saleOrder->customer_id || ! $saleOrder->isCashierApproved()) {
            return 0.0;
        }

        return DB::transaction(function () use ($invoice, $saleOrder) {
            Deposit::query()->where('from_model_type', Customer::class)->where('from_model_id', $saleOrder->customer_id)->lockForUpdate()->get();
            $reserved = Deposit::reservedForCustomer((int) $saleOrder->customer_id, (int) $saleOrder->id);
            $ar = AccountReceivable::query()->where('invoice_id', $invoice->id)->lockForUpdate()->first();
            $amount = round(min($reserved, (float) ($ar?->remaining ?? 0)), 2);
            if ($amount <= 0.005) {
                return 0.0;
            }

            $depositAccount = Deposit::customerDepositAccount();
            if (! $depositAccount) {
                throw new \RuntimeException('Akun Deposit Pelanggan ('.config('coa.customer_deposit').") belum ada; saldo titip COD SO {$saleOrder->so_number} tidak dapat melunasi invoice {$invoice->invoice_number}. Tambahkan akun tersebut lalu ulangi.");
            }

            $this->release($saleOrder, $amount, "Direalisasi: melunasi invoice {$invoice->invoice_number}", Auth::user());

            $date = $invoice->invoice_date ? Carbon::parse($invoice->invoice_date)->toDateString() : now()->toDateString();
            $receipt = CustomerReceipt::create([
                'customer_id' => (int) $saleOrder->customer_id,
                'cabang_id' => $invoice->cabang_id,
                'invoice_id' => $invoice->id,
                'selected_invoices' => [$invoice->id],
                'invoice_receipts' => [$invoice->id => $amount],
                'payment_date' => $date,
                'payment_method' => 'Deposit',
                'coa_id' => $depositAccount->id,
                'total_payment' => $amount,
                'total_payment_idr' => $amount,
                'notes' => "Otomatis: saldo titip COD SO {$saleOrder->so_number} (verifikasi kasir) melunasi invoice {$invoice->invoice_number}",
                'status' => 'Draft',
                'created_by' => Auth::id() ?? $saleOrder->cashier_approved_by,
                'currency_id' => $invoice->currency_id,
                'exchange_rate' => (float) ($invoice->exchange_rate ?: 1),
            ]);
            CustomerReceiptObserver::markArUpdatedInCreate($receipt->id);

            CustomerReceiptItem::create([
                'customer_receipt_id' => $receipt->id,
                'invoice_id' => $invoice->id,
                'method' => 'Deposit',
                'amount' => $amount,
                'amount_idr' => $amount,
                'coa_id' => $depositAccount->id,
                'payment_date' => $date,
                'selected_invoices' => [$invoice->id],
            ]);

            $remaining = round(max(0.0, (float) $ar->remaining - $amount), 2);
            $rate = (float) ($ar->exchange_rate ?: 1) ?: 1.0;
            $paid = round((float) $ar->paid + $amount, 2);
            $ar->update([
                'paid' => $paid,
                'remaining' => $remaining,
                'paid_original' => round($paid / $rate, 4),
                'remaining_original' => round($remaining / $rate, 4),
                'status' => $remaining <= 0.005 ? PaymentStatus::PAID->value : $ar->status,
            ]);
            if ($remaining <= 0.005) {
                $ar->ageingSchedule?->delete();
            }

            $invoice->forceFill(['status' => $remaining <= 0.005 ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID])->saveQuietly();
            $receipt->update(['status' => $remaining <= 0.005 ? 'Paid' : 'Partial']);
            app(LedgerPostingService::class)->postCustomerReceipt($receipt->fresh());

            return $amount;
        });
    }

    /** Lepas cadangan SO sebesar $amount (log `release` pada baris deposit tempat cadangan dicatat). */
    private function release(SaleOrder $saleOrder, float $amount, string $note, ?User $actor): float
    {
        $amount = round($amount, 2);
        if ($amount <= 0.005) {
            return 0.0;
        }

        $reserveLog = DepositLog::query()->where('type', 'reserve')->where('reference_type', SaleOrder::class)
            ->where('reference_id', $saleOrder->id)->orderBy('id')->firstOrFail();

        DepositLog::create([
            'deposit_id' => $reserveLog->deposit_id,
            'type' => 'release',
            'reference_type' => SaleOrder::class,
            'reference_id' => $saleOrder->id,
            'amount' => $amount,
            'note' => $note,
            'created_by' => $actor?->getKey() ?? $reserveLog->created_by,
        ]);

        return $amount;
    }

    /** SO sumber invoice penjualan: langsung (from_model SO) atau lewat Surat Jalan → DO → SO. */
    private function saleOrderOf(Invoice $invoice): ?SaleOrder
    {
        if ($invoice->from_model_type === SaleOrder::class) {
            return SaleOrder::withoutGlobalScopes()->find($invoice->from_model_id);
        }

        $sjIds = array_filter((array) ($invoice->surat_jalan_ids ?? []));
        if ($sjIds === [] && $invoice->from_model_type === SuratJalan::class) {
            $sjIds = [$invoice->from_model_id];
        }
        if ($sjIds === []) {
            return null;
        }

        return SuratJalan::withoutGlobalScopes()->with('deliveryOrder.salesOrders')->whereIn('id', $sjIds)->get()
            ->flatMap(fn (SuratJalan $sj) => $sj->deliveryOrder)->flatMap(fn ($do) => $do->salesOrders)->first();
    }
}
