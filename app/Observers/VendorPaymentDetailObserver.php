<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Deposit;
use App\Models\VendorPaymentDetail;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VendorPaymentDetailObserver
{
    public function creating(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $this->guardDepositRequirements($vendorPaymentDetail);
    }

    /**
     * Handle the VendorPaymentDetail "created" event.
     */
    public function created(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $vendorPayment = $vendorPaymentDetail->vendorPayment;

        $detailMethod = strtolower($vendorPaymentDetail->method ?? $vendorPayment->payment_method ?? '');
        if ($detailMethod === 'deposit') {
            $availableDeposits = Deposit::where('from_model_type', 'App\Models\Supplier')
                ->where('from_model_id', $vendorPayment->supplier_id)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->orderBy('created_at', 'asc')
                ->get();

            $remainingPaymentAmount = $vendorPaymentDetail->amount;

            foreach ($availableDeposits as $deposit) {
                if ($remainingPaymentAmount <= 0) {
                    break;
                }

                $amountToUse = min($remainingPaymentAmount, $deposit->remaining_amount);

                $deposit->remaining_amount -= $amountToUse;
                $deposit->used_amount += $amountToUse;

                if ($deposit->remaining_amount <= 0) {
                    $deposit->status = 'closed';
                }
                $deposit->save();

                // Create deposit log for this usage
                $vendorPaymentDetail->depositLog()->create([
                    'deposit_id' => $deposit->id,
                    'amount' => $amountToUse,
                    'type' => 'use',
                    'created_by' => Auth::id(),
                ]);

                $remainingPaymentAmount -= $amountToUse;
            }

            if ($remainingPaymentAmount > 0) {
                Log::warning("Insufficient deposit balance for vendor payment detail ID {$vendorPaymentDetail->id}. Remaining amount: {$remainingPaymentAmount}");
            }
        }

        $accountPayable = AccountPayable::where('invoice_id', $vendorPaymentDetail->invoice_id)->first();
        if ($accountPayable) {
            $totalReduction = $vendorPaymentDetail->amount + ($vendorPaymentDetail->adjustment_amount ?? 0);
            $newPaid = min($accountPayable->paid + $vendorPaymentDetail->amount, $accountPayable->total);
            $newRemaining = max(0, $accountPayable->remaining - $totalReduction);
            $accountPayable->update([
                'paid' => $newPaid,
                'remaining' => $newRemaining,
                'status' => $newRemaining <= 0.01 ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value,
            ]);
        }

        if ($vendorPaymentDetail->coa_id) {
                $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($vendorPaymentDetail);
                $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($vendorPaymentDetail);
                $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($vendorPaymentDetail);
                // Temporarily disable journal posting to avoid double entries
                // $vendorPaymentDetail->journalEntry()->create([
                //     'coa_id' => $vendorPaymentDetail->coa_id,
                //     'date' => Carbon::now(),
                //     'description' => 'Vendor Payment Detail',
                //     'credit' => $vendorPaymentDetail->amount,
                //     'journal_type' => 'Purchase',
                //     'cabang_id' => $branchId,
                //     'department_id' => $departmentId,
                //     'project_id' => $projectId,
                // ]);
        }
    }

    /**
     * Handle the VendorPaymentDetail "updated" event.
     */
    public function updated(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    public function updating(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $willUseDeposit = strtolower((string) ($vendorPaymentDetail->method ?? $vendorPaymentDetail->vendorPayment?->payment_method)) === 'deposit';

        if ($willUseDeposit && (
            $vendorPaymentDetail->isDirty('method')
            || $vendorPaymentDetail->isDirty('amount')
            || $vendorPaymentDetail->isDirty('vendor_payment_id')
        )) {
            $this->guardDepositRequirements($vendorPaymentDetail);
        }
    }

    /**
     * Handle the VendorPaymentDetail "deleted" event.
     */
    public function deleted(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    /**
     * Handle the VendorPaymentDetail "restored" event.
     */
    public function restored(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    /**
     * Handle the VendorPaymentDetail "force deleted" event.
     */
    public function forceDeleted(VendorPaymentDetail $vendorPaymentDetail): void
    {
        //
    }

    protected function guardDepositRequirements(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $vendorPayment = $vendorPaymentDetail->vendorPayment;
        $detailMethod = strtolower((string) ($vendorPaymentDetail->method ?? $vendorPayment?->payment_method));

        if ($detailMethod !== 'deposit') {
            return;
        }

        if (!$vendorPayment) {
            throw new \RuntimeException('Vendor payment tidak ditemukan untuk detail pembayaran deposit.');
        }

        $supplierName = $vendorPayment->supplier?->perusahaan ?? $vendorPayment->supplier?->name ?? 'supplier ini';
        $availableDeposits = Deposit::where('from_model_type', 'App\Models\Supplier')
            ->where('from_model_id', $vendorPayment->supplier_id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($availableDeposits->isEmpty()) {
            $message = "Supplier {$supplierName} tidak memiliki deposit aktif yang tersedia untuk pembayaran ini.";
            $this->notifyDepositValidationFailure('Deposit Tidak Tersedia', $message);
            throw new \RuntimeException($message);
        }

        $requiredAmount = (float) $vendorPaymentDetail->amount;
        $totalAvailableDeposit = (float) $availableDeposits->sum('remaining_amount');
        if ($totalAvailableDeposit + 0.0001 < $requiredAmount) {
            $message = "Saldo deposit supplier {$supplierName} tidak mencukupi. Tersedia Rp "
                . number_format($totalAvailableDeposit, 0, ',', '.')
                . ', dibutuhkan Rp '
                . number_format($requiredAmount, 0, ',', '.');
            $this->notifyDepositValidationFailure('Saldo Deposit Tidak Mencukupi', $message);
            throw new \RuntimeException($message);
        }

        if (!$this->resolveDepositCoa()) {
            $message = 'Akun deposit / uang muka supplier tidak ditemukan. Simpan COA deposit lebih dulu sebelum detail pembayaran dibuat.';
            $this->notifyDepositValidationFailure('COA Deposit Belum Dikonfigurasi', $message);
            throw new \RuntimeException($message);
        }
    }

    protected function resolveDepositCoa(): ?ChartOfAccount
    {
        foreach (['1150.01', '1150.02', '1150'] as $code) {
            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa) {
                return $coa;
            }
        }

        return ChartOfAccount::where('name', 'LIKE', '%UANG MUKA%')->first();
    }

    protected function notifyDepositValidationFailure(string $title, string $message): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        Log::warning($title . ': ' . $message);
    }
}
