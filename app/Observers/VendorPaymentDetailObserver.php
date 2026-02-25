<?php

namespace App\Observers;

use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\Log;
use App\Models\VendorPaymentDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class VendorPaymentDetailObserver
{
    /**
     * Handle the VendorPaymentDetail "created" event.
     */
    public function created(VendorPaymentDetail $vendorPaymentDetail): void
    {
        $accountPayable = AccountPayable::where('invoice_id', $vendorPaymentDetail->invoice_id)->first();
        $vendorPayment = $vendorPaymentDetail->vendorPayment;
        // Update account payable
        if ($accountPayable) {
            $totalReduction = $vendorPaymentDetail->amount + ($vendorPaymentDetail->adjustment_amount ?? 0);
            $newPaid = min($accountPayable->paid + $vendorPaymentDetail->amount, $accountPayable->total); // hanya kas masuk dicatat di paid
            $newRemaining = max(0, $accountPayable->remaining - $totalReduction);
            $accountPayable->update([
                'paid' => $newPaid,
                'remaining' => $newRemaining,
                'status' => $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas',
            ]);
        }
        // Status & invoice synchronization now handled centrally after header creation.

        $detailMethod = strtolower($vendorPaymentDetail->method ?? $vendorPayment->payment_method ?? '');
        if ($detailMethod === 'deposit') {
            // VALIDATION: Check if supplier has available deposits before processing
            $availableDeposits = Deposit::where('from_model_type', 'App\Models\Supplier')
                ->where('from_model_id', $vendorPayment->supplier_id)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->orderBy('created_at', 'asc') // FIFO - oldest deposits first
                ->get();

            if ($availableDeposits->isEmpty()) {
                // Use notification instead of exception for better UX
                Notification::make()
                    ->title('Deposit Tidak Tersedia')
                    ->body("Supplier {$vendorPayment->supplier->perusahaan} tidak memiliki deposit yang tersedia untuk pembayaran. Silakan pilih metode pembayaran lain atau buat deposit terlebih dahulu.")
                    ->danger()
                    ->persistent()
                    ->send();

                // Log for debugging but don't stop the process
                Log::warning("No available deposits for supplier {$vendorPayment->supplier->perusahaan} during payment creation");

                // Skip deposit processing but continue with other logic
                return;
            }

            // Check if total available deposit balance is sufficient
            $totalAvailableDeposit = $availableDeposits->sum('remaining_amount');
            if ($totalAvailableDeposit < $vendorPaymentDetail->amount) {
                // Use notification instead of exception
                Notification::make()
                    ->title('Saldo Deposit Tidak Mencukupi')
                    ->body("Saldo deposit supplier {$vendorPayment->supplier->perusahaan} tidak mencukupi. Saldo tersedia: Rp " . number_format($totalAvailableDeposit, 0, ',', '.') . ", dibutuhkan: Rp " . number_format($vendorPaymentDetail->amount, 0, ',', '.'))
                    ->danger()
                    ->persistent()
                    ->send();

                // Log for debugging
                Log::warning("Insufficient deposit balance for supplier {$vendorPayment->supplier->perusahaan}. Available: {$totalAvailableDeposit}, Required: {$vendorPaymentDetail->amount}");

                // Skip deposit processing but continue with other logic
                return;
            }

            // Process deposits if validation passes
            $remainingPaymentAmount = $vendorPaymentDetail->amount;

            foreach ($availableDeposits as $deposit) {
                if ($remainingPaymentAmount <= 0) {
                    break; // Payment fully covered
                }

                $amountToUse = min($remainingPaymentAmount, $deposit->remaining_amount);

                // Update deposit balances
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

            // If payment couldn't be fully covered by available deposits (shouldn't happen due to validation above)
            if ($remainingPaymentAmount > 0) {
                // Log warning or handle insufficient deposit balance
                Log::warning("Insufficient deposit balance for vendor payment detail ID {$vendorPaymentDetail->id}. Remaining amount: {$remainingPaymentAmount}");
            }
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
}
