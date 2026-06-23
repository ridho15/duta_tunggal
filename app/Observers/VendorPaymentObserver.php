<?php

namespace App\Observers;

use App\Enums\PaymentStatus;
use App\Models\ChartOfAccount;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\VendorPayment;
use App\Services\LedgerPostingService;
use App\Support\ProcurementFailureNotifier;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorPaymentObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function creating(VendorPayment $payment): void
    {
        $this->guardDepositRequirements($payment);
    }

    public function updating(VendorPayment $payment): void
    {
        $this->guardDepositRequirements($payment);
    }

    public function updated(VendorPayment $payment)
    {
        // Handle amount changes - reverse old journals and post new ones
        if ($payment->wasChanged('total_payment') && $payment->journalEntries()->exists()) {
            $this->reverseJournalEntries($payment);
            // Re-post with new amount if payment is still active
            if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
                try {
                    $this->ledger->postVendorPayment($payment);
                } catch (Throwable $exception) {
                    Log::error('VendorPaymentObserver: failed to post vendor payment after amount change', [
                        'payment_id' => $payment->id,
                        'error' => $exception->getMessage(),
                    ]);
                    if (! app()->runningInConsole()) {
                        ProcurementFailureNotifier::danger(
                            'Gagal Posting Jurnal Pembayaran Vendor',
                            $exception,
                            'Perubahan pembayaran vendor berhasil disimpan, tetapi jurnal belum dapat diposting.'
                        );
                    }
                }
            }
        }
        
        // Post journal for both partial and full payments (only if no journals exist)
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            if (!$payment->journalEntries()->exists()) {
                $this->ledger->postVendorPayment($payment);
            }
        }
        
        // Update AP status for both partial and paid
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            $this->updateAccountPayableAndInvoiceStatus($payment);
        }
    }

    public function created(VendorPayment $payment)
    {
        // Validate payment amount against remaining balances before creating details
        if (!empty($payment->selected_invoices)) {
            $this->validatePaymentAmount($payment);
        }

        // Create VendorPaymentDetail from selected_invoices if none exist
        if ($payment->vendorPaymentDetail()->count() == 0 && !empty($payment->selected_invoices)) {
            $this->createPaymentDetailsFromSelectedInvoices($payment);
        }

        // Post journal for both partial and full payments
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            // Avoid double posting journals: only post if none exist yet
            if (!$payment->journalEntries()->exists()) {
                try {
                    $this->ledger->postVendorPayment($payment);
                } catch (Throwable $exception) {
                    Log::error('VendorPaymentObserver: failed to post vendor payment on create', [
                        'payment_id' => $payment->id,
                        'error' => $exception->getMessage(),
                    ]);
                    if (! app()->runningInConsole()) {
                        ProcurementFailureNotifier::danger(
                            'Gagal Posting Jurnal Pembayaran Vendor',
                            $exception,
                            'Pembayaran vendor berhasil disimpan, tetapi jurnal belum dapat diposting.'
                        );
                    }
                }
            }
        }
        
        // Update AP status for both partial and paid
        if (in_array(strtolower($payment->status ?? ''), ['partial', 'paid'])) {
            $this->updateAccountPayableAndInvoiceStatus($payment);
        }
    }

    public function deleted(VendorPayment $payment)
    {
        // Reverse account payable updates when payment is deleted
        $this->reverseAccountPayableAndInvoiceStatus($payment);

        // Reverse journal entries when payment is deleted
        $this->reverseJournalEntries($payment);

        // Soft delete related vendor payment details
        $payment->vendorPaymentDetail()->delete();
    }

    public function updateAccountPayableAndInvoiceStatus(VendorPayment $payment)
    {
        // Get all invoices from payment details
        $paymentDetails = $payment->vendorPaymentDetail()->get();

        foreach ($paymentDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $paidAmount = (float) $detail->amount;
            $adjustmentAmount = (float) ($detail->adjustment_amount ?? 0);

            // Update Account Payable
            $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
            if (!$accountPayable) {
                throw new \Exception("Account payable not found for invoice {$invoiceId}");
            }

            // Recalculate paid and remaining based on all payment details for this invoice
            $totalPaidOriginalForInvoice = \App\Models\VendorPaymentDetail::where('invoice_id', $invoiceId)
                ->whereHas('vendorPayment', function($query) {
                    $query->whereIn('status', ['partial', 'paid']);
                })
                ->sum('amount');

            $totalAdjustmentOriginalForInvoice = \App\Models\VendorPaymentDetail::where('invoice_id', $invoiceId)
                ->whereHas('vendorPayment', function($query) {
                    $query->whereIn('status', ['partial', 'paid']);
                })
                ->sum('adjustment_amount');

            $exchangeRate = (float) ($accountPayable->exchange_rate ?? $accountPayable->invoice?->exchange_rate ?? 1);
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
            $totalOriginal = (float) ($accountPayable->total_original ?? ((float) $accountPayable->total / $exchangeRate));
            $newPaidOriginal = min((float) $totalPaidOriginalForInvoice, $totalOriginal);
            $newRemainingOriginal = max(0, $totalOriginal - $newPaidOriginal - (float) $totalAdjustmentOriginalForInvoice);

            $accountPayable->paid_original = $newPaidOriginal;
            $accountPayable->remaining_original = $newRemainingOriginal;
            $accountPayable->paid = round($newPaidOriginal * $exchangeRate, 2);
            $accountPayable->remaining = round($newRemainingOriginal * $exchangeRate, 2);
            $accountPayable->status = $newRemainingOriginal <= 0.01 ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value;
            $accountPayable->save();

            // Sync invoice status with AP
            if ($accountPayable->invoice) {
                $accountPayable->invoice->status = $newRemainingOriginal <= 0.01
                    ? Invoice::STATUS_PAID
                    : ($newPaidOriginal > 0 ? Invoice::STATUS_PARTIALLY_PAID : $accountPayable->invoice->status);
                $accountPayable->invoice->save();
            }
        }
    }

    public function reverseAccountPayableAndInvoiceStatus(VendorPayment $payment)
    {
        // Get all invoices from payment details (including soft deleted ones)
        $paymentDetails = $payment->vendorPaymentDetail()->withTrashed()->get();

        foreach ($paymentDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $paidAmount = (float) $detail->amount;
            $adjustmentAmount = (float) ($detail->adjustment_amount ?? 0);

            // Update Account Payable - subtract the payment amount
            $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
            if (!$accountPayable) {
                continue; // Skip if AP not found
            }

            $exchangeRate = (float) ($accountPayable->exchange_rate ?? $accountPayable->invoice?->exchange_rate ?? 1);
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
            $totalOriginal = (float) ($accountPayable->total_original ?? ((float) $accountPayable->total / $exchangeRate));

            $newPaidOriginal = max(0, (float) ($accountPayable->paid_original ?? 0) - $paidAmount);
            $newRemainingOriginal = min($totalOriginal, (float) ($accountPayable->remaining_original ?? 0) + $paidAmount + $adjustmentAmount);

            $accountPayable->paid_original = $newPaidOriginal;
            $accountPayable->remaining_original = $newRemainingOriginal;
            $accountPayable->paid = round($newPaidOriginal * $exchangeRate, 2);
            $accountPayable->remaining = round($newRemainingOriginal * $exchangeRate, 2);
            $accountPayable->status = $newRemainingOriginal <= 0.01 ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value;
            $accountPayable->save();

            // Sync invoice status with AP
            if ($accountPayable->invoice) {
                $accountPayable->invoice->status = $newRemainingOriginal <= 0.01
                    ? Invoice::STATUS_PAID
                    : ($newPaidOriginal > 0 ? Invoice::STATUS_PARTIALLY_PAID : Invoice::STATUS_SENT);
                $accountPayable->invoice->save();
            }
        }
    }

    protected function reverseJournalEntries(VendorPayment $payment)
    {
        // Delete existing journal entries to prepare for re-posting
        $payment->journalEntries()->delete();
    }

    protected function validatePaymentAmount(VendorPayment $payment)
    {
        $selectedInvoices = $payment->selected_invoices;
        if (!$selectedInvoices) {
            return;
        }

        if (!is_array($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        if (empty($selectedInvoices)) {
            return;
        }

        // Extract invoice IDs
        $invoiceIds = [];
        foreach ($selectedInvoices as $item) {
            if (is_numeric($item)) {
                $invoiceIds[] = (int) $item;
            } elseif (is_array($item) && isset($item['invoice_id'])) {
                $invoiceIds[] = (int) $item['invoice_id'];
            } elseif (is_object($item) && isset($item->invoice_id)) {
                $invoiceIds[] = (int) $item->invoice_id;
            }
        }

        $invoiceIds = array_unique($invoiceIds);

        if (empty($invoiceIds)) {
            return;
        }

        // Calculate total remaining balance
        $totalRemaining = \App\Models\Invoice::whereIn('id', $invoiceIds)
            ->with('accountPayable')
            ->get()
            ->sum(function ($invoice) {
                return $invoice->accountPayable?->remaining_original
                    ?? $invoice->accountPayable?->remaining
                    ?? $invoice->total;
            });

        // Check if payment amount exceeds total remaining balance
        if ($payment->total_payment > $totalRemaining) {
            throw new \Exception("Payment amount ({$payment->total_payment}) exceeds total remaining balance ({$totalRemaining}). Overpayment is not allowed.");
        }
    }

    protected function createPaymentDetailsFromSelectedInvoices(VendorPayment $payment)
    {
        $selectedInvoices = $payment->selected_invoices;
        if (!$selectedInvoices) {
            return;
        }

        if (!is_array($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        if (empty($selectedInvoices)) {
            return;
        }

        // Handle different data formats:
        // 1. Array of invoice IDs: [1, 2, 3]
        // 2. Array of objects: [['invoice_id' => 1, 'amount' => 1000], ...]
        $invoiceIds = [];
        $hasPaymentDetails = false;

        foreach ($selectedInvoices as $item) {
            if (is_numeric($item)) {
                // Format 1: direct invoice ID
                $invoiceIds[] = (int) $item;
            } elseif (is_array($item) && isset($item['invoice_id'])) {
                // Format 2: object with invoice_id
                $invoiceIds[] = (int) $item['invoice_id'];
                $hasPaymentDetails = true;
            } elseif (is_object($item) && isset($item->invoice_id)) {
                // Format 2: object with invoice_id
                $invoiceIds[] = (int) $item->invoice_id;
                $hasPaymentDetails = true;
            }
        }

        $invoiceIds = array_unique($invoiceIds);

        if (empty($invoiceIds)) {
            return;
        }

        $invoices = \App\Models\Invoice::whereIn('id', $invoiceIds)
            ->with('accountPayable')
            ->get();

        // If we already have payment details format, don't create additional details
        if ($hasPaymentDetails) {
            return;
        }

        $totalPayment = $payment->total_payment ?? 0;
        $remainingPayment = $totalPayment;

        foreach ($invoices as $invoice) {
            if ($remainingPayment <= 0) break;

            $remainingAmount = $invoice->accountPayable?->remaining_original
                ?? $invoice->accountPayable?->remaining
                ?? $invoice->total;
            $paymentAmount = min($remainingAmount, $remainingPayment);
            $currencyId = is_numeric($invoice->currency_id ?? null) ? (int) $invoice->currency_id : null;
            $exchangeRate = (float) ($invoice->exchange_rate ?? 1);
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;

            \App\Models\VendorPaymentDetail::create([
                'vendor_payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'amount' => $paymentAmount,
                'amount_idr' => round($paymentAmount * $exchangeRate, 2),
                'method' => $payment->payment_method ?? 'Cash',
                'payment_date' => $payment->payment_date,
                'coa_id' => $payment->coa_id,
            ]);

            $remainingPayment -= $paymentAmount;
        }
    }

    protected function guardDepositRequirements(VendorPayment $payment): void
    {
        if (!$this->shouldValidateDepositRequirements($payment)) {
            return;
        }

        $depositAmount = $this->resolveRequestedDepositAmount($payment);
        if ($depositAmount <= 0) {
            return;
        }

        $supplierName = $payment->supplier?->perusahaan ?? $payment->supplier?->name ?? 'supplier ini';
        $availableDeposits = Deposit::where('from_model_type', 'App\Models\Supplier')
            ->where('from_model_id', $payment->supplier_id)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($availableDeposits->isEmpty()) {
            $message = "Supplier {$supplierName} tidak memiliki deposit aktif yang tersedia untuk pembayaran ini.";
            $this->notifyDepositValidationFailure('Deposit Tidak Tersedia', $message);
            throw new \RuntimeException($message);
        }

        $totalAvailableDeposit = (float) $availableDeposits->sum('remaining_amount');
        if ($totalAvailableDeposit + 0.0001 < $depositAmount) {
            $message = "Saldo deposit supplier {$supplierName} tidak mencukupi. Tersedia Rp "
                . number_format($totalAvailableDeposit, 0, ',', '.')
                . ', dibutuhkan Rp '
                . number_format($depositAmount, 0, ',', '.');
            $this->notifyDepositValidationFailure('Saldo Deposit Tidak Mencukupi', $message);
            throw new \RuntimeException($message);
        }

        if (!$this->resolveDepositCoa()) {
            $message = 'Akun deposit / uang muka supplier tidak ditemukan. Simpan COA deposit lebih dulu sebelum pembayaran diproses.';
            $this->notifyDepositValidationFailure('COA Deposit Belum Dikonfigurasi', $message);
            throw new \RuntimeException($message);
        }
    }

    protected function shouldValidateDepositRequirements(VendorPayment $payment): bool
    {
        $activeStatuses = ['partial', 'paid'];
        $currentStatus = strtolower((string) $payment->status);
        $originalStatus = strtolower((string) $payment->getOriginal('status'));
        $paymentMethod = strtolower((string) $payment->payment_method);

        $statusRequiresPosting = in_array($currentStatus, $activeStatuses, true);
        $statusJustActivated = $payment->exists && $currentStatus !== $originalStatus && in_array($currentStatus, $activeStatuses, true);
        $depositMethod = $paymentMethod === 'deposit';
        $detailsContainDeposit = $payment->exists
            ? $payment->vendorPaymentDetail()->whereRaw('LOWER(method) = ?', ['deposit'])->exists()
            : false;

        return ($statusRequiresPosting || $statusJustActivated) && ($depositMethod || $detailsContainDeposit);
    }

    protected function resolveRequestedDepositAmount(VendorPayment $payment): float
    {
        $details = $payment->exists ? $payment->vendorPaymentDetail()->get() : collect();
        $depositDetailsAmount = (float) $details
            ->filter(fn ($detail) => strtolower((string) $detail->method) === 'deposit')
            ->sum('amount');

        if ($depositDetailsAmount > 0) {
            return $depositDetailsAmount;
        }

        return strtolower((string) $payment->payment_method) === 'deposit'
            ? (float) $payment->total_payment
            : 0.0;
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
