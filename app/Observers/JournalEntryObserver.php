<?php

namespace App\Observers;

use App\Models\JournalEntry;
use App\Models\User;
use App\Notifications\JournalEntryCreated;
use App\Notifications\JournalEntryUpdated;
use App\Notifications\JournalEntryDeleted;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JournalEntryObserver
{
    public function created(JournalEntry $entry): void
    {
        // Try to get current authenticated user first
        $currentUser = Auth::user();

        // If no authenticated user, try to get user from source model
        if (!$currentUser && $entry->source_type && $entry->source_id) {
            try {
                $source = $entry->source;
                if ($source && method_exists($source, 'creator')) {
                    $currentUser = $source->creator;
                } elseif ($source && isset($source->created_by)) {
                    $currentUser = User::find($source->created_by);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get user from source model', [
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // If still no user, send to admin users
        if (!$currentUser) {
            $adminUsers = User::where('email', 'like', '%admin%')->get();
            if ($adminUsers->isEmpty()) {
                // Fallback to first user
                $currentUser = User::first();
            } else {
                // Send to all admin users
                foreach ($adminUsers as $adminUser) {
                    $adminUser->notify(new JournalEntryCreated($entry));
                }
                Log::info('Journal Entry notification sent to admin users', [
                    'journal_entry_id' => $entry->id,
                    'reference' => $entry->reference,
                    'admin_count' => $adminUsers->count(),
                ]);
                return;
            }
        }

        // Send notification to the determined user
        if ($currentUser) {
            $currentUser->notify(new JournalEntryCreated($entry));

            // Log the notification for audit
            Log::info('Journal Entry notification sent', [
                'journal_entry_id' => $entry->id,
                'reference' => $entry->reference,
                'user_id' => $currentUser->id,
                'user_method' => Auth::user() ? 'auth' : 'source_model',
            ]);
        }
    }

    public function updated(JournalEntry $entry): void
    {
        // Send notification only to the currently authenticated user
        $currentUser = Auth::user();
        if ($currentUser) {
            $currentUser->notify(new JournalEntryUpdated($entry));
        }

        // Log the notification for audit
        Log::info('Journal Entry updated notification sent', [
            'journal_entry_id' => $entry->id,
            'reference' => $entry->reference,
            'current_user_id' => $currentUser ? $currentUser->id : null,
        ]);
    }

    public function deleted(JournalEntry $entry): void
    {
        // Send notification only to the currently authenticated user
        $currentUser = Auth::user();
        if ($currentUser) {
            $currentUser->notify(new JournalEntryDeleted($entry));
        }

        // Log the deletion for audit purposes
        Log::info('Journal Entry deleted', [
            'journal_entry_id' => $entry->id,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'journal_type' => $entry->journal_type,
            'reference' => $entry->reference,
            'description' => $entry->description,
            'debit' => $entry->debit,
            'credit' => $entry->credit,
        ]);

        // Handle cleanup based on journal type and source type
        $this->cleanupRelatedData($entry);
    }

    protected function cleanupRelatedData(JournalEntry $entry): void
    {
        // Get all journal entries for the same source to check if this is the last one
        $relatedEntries = JournalEntry::where('source_type', $entry->source_type)
            ->where('source_id', $entry->source_id)
            ->where('id', '!=', $entry->id) // Exclude the one being deleted
            ->count();

        // If this is the last journal entry for the source, we may need to reverse related data
        if ($relatedEntries === 0) {
            $this->reverseRelatedData($entry);
        }
    }

    protected function reverseRelatedData(JournalEntry $entry): void
    {
        switch ($entry->source_type) {
            case 'App\Models\VendorPayment':
                $this->reverseVendorPaymentData($entry);
                break;

            case 'App\Models\CustomerReceipt':
                $this->reverseCustomerReceiptData($entry);
                break;

            case 'App\Models\Invoice':
                $this->reverseInvoiceData($entry);
                break;

            case 'App\Models\PurchaseOrder':
                $this->reversePurchaseOrderData($entry);
                break;

            case 'App\Models\SaleOrder':
                $this->reverseSaleOrderData($entry);
                break;

            case 'App\Models\Deposit':
                $this->reverseDepositData($entry);
                break;

            case 'App\Models\CashBankTransaction':
                $this->reverseCashBankTransactionData($entry);
                break;

            case 'App\Models\CashBankTransfer':
                $this->reverseCashBankTransferData($entry);
                break;

            default:
                // For unknown source types, just log
                Log::warning('Unknown source type for journal entry cleanup', [
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ]);
                break;
        }
    }

    protected function reverseVendorPaymentData(JournalEntry $entry): void
    {
        // Reverse account payable updates when all journal entries for vendor payment are deleted
        $vendorPayment = $entry->source;
        if ($vendorPayment && $vendorPayment->exists) {
            // Reverse account payable and invoice status
            $this->reverseAccountPayableAndInvoiceStatus($vendorPayment);
        }
    }

    protected function reverseCustomerReceiptData(JournalEntry $entry): void
    {
        // Reverse account receivable updates when all journal entries for customer receipt are deleted
        $customerReceipt = $entry->source;
        if ($customerReceipt && $customerReceipt->exists) {
            $this->reverseAccountReceivableAndInvoiceStatus($customerReceipt);
        }
    }

    protected function reverseInvoiceData(JournalEntry $entry): void
    {
        // Handle invoice-related cleanup
        $invoice = $entry->source;
        if ($invoice && $invoice->exists) {
            // Update related account payable/receivable if needed
            if ($invoice->type === 'purchase') {
                $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoice->id)->first();
                if ($accountPayable) {
                    // Reset account payable to original state
                    $accountPayable->paid = 0;
                    $accountPayable->remaining = $accountPayable->total;
                    $accountPayable->status = 'Belum Lunas';
                    $accountPayable->save();
                }
            }
        }
    }

    protected function reversePurchaseOrderData(JournalEntry $entry): void
    {
        // Handle purchase order related cleanup
        $purchaseOrder = $entry->source;
        if ($purchaseOrder && $purchaseOrder->exists) {
            // Log the reversal
            Log::info('Purchase order journal entries reversed', [
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
            ]);
        }
    }

    protected function reverseSaleOrderData(JournalEntry $entry): void
    {
        // Handle sale order related cleanup
        $saleOrder = $entry->source;
        if ($saleOrder && $saleOrder->exists) {
            // Log the reversal
            Log::info('Sale order journal entries reversed', [
                'sale_order_id' => $saleOrder->id,
                'so_number' => $saleOrder->so_number,
            ]);
        }
    }

    protected function reverseDepositData(JournalEntry $entry): void
    {
        // Handle deposit related cleanup
        $deposit = $entry->source;
        if ($deposit && $deposit->exists) {
            // Reverse deposit logs if needed
            Log::info('Deposit journal entries reversed', [
                'deposit_id' => $deposit->id,
                'deposit_number' => $deposit->deposit_number,
            ]);
        }
    }

    protected function reverseCashBankTransactionData(JournalEntry $entry): void
    {
        // Handle cash/bank transaction related cleanup
        $transaction = $entry->source;
        if ($transaction && $transaction->exists) {
            // Reverse transaction details if needed
            Log::info('Cash/Bank transaction journal entries reversed', [
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number ?? 'N/A',
            ]);
        }
    }

    protected function reverseCashBankTransferData(JournalEntry $entry): void
    {
        // Handle cash/bank transfer related cleanup
        $transfer = $entry->source;
        if ($transfer && $transfer->exists) {
            // If transfer is posted and all journal entries are deleted, reset status to draft
            $remainingEntries = JournalEntry::where('source_type', 'App\Models\CashBankTransfer')
                ->where('source_id', $transfer->id)
                ->count();

            if ($remainingEntries === 0 && $transfer->status === 'posted') {
                $transfer->update(['status' => 'draft']);
                Log::info('Cash/Bank transfer status reset to draft due to journal deletion', [
                    'transfer_id' => $transfer->id,
                    'transfer_number' => $transfer->number,
                ]);
            }

            Log::info('Cash/Bank transfer journal entries reversed', [
                'transfer_id' => $transfer->id,
                'transfer_number' => $transfer->number,
            ]);
        }
    }

    protected function reverseAccountPayableAndInvoiceStatus($payment): void
    {
        // Get all invoices from payment details (including soft deleted ones)
        $paymentDetails = $payment->vendorPaymentDetail()->withTrashed()->get();

        foreach ($paymentDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $paidAmount = $detail->amount;

            // Update Account Payable - subtract the payment amount
            $accountPayable = \App\Models\AccountPayable::where('invoice_id', $invoiceId)->first();
            if (!$accountPayable) {
                continue; // Skip if AP not found
            }

            // Subtract the payment amount directly from paid and add to remaining
            $newPaid = max(0, $accountPayable->paid - $paidAmount);
            $newRemaining = $accountPayable->total - $newPaid;

            $accountPayable->paid = $newPaid;
            $accountPayable->remaining = $newRemaining;
            $accountPayable->status = $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas';
            $accountPayable->save();

            // Sync invoice status with AP
            if ($accountPayable->invoice) {
                $accountPayable->invoice->status = $newRemaining <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'unpaid');
                $accountPayable->invoice->save();
            }
        }
    }

    protected function reverseAccountReceivableAndInvoiceStatus($receipt): void
    {
        // Similar logic for customer receipts
        $receiptDetails = $receipt->customerReceiptItem()->withTrashed()->get();

        foreach ($receiptDetails as $detail) {
            $invoiceId = $detail->invoice_id;
            $receivedAmount = $detail->amount;

            // Update Account Receivable - subtract the receipt amount
            $accountReceivable = \App\Models\AccountReceivable::where('invoice_id', $invoiceId)->first();
            if (!$accountReceivable) {
                continue; // Skip if AR not found
            }

            // Subtract the receipt amount directly from paid and add to remaining
            $newPaid = max(0, $accountReceivable->paid - $receivedAmount);
            $newRemaining = $accountReceivable->total - $newPaid;

            $accountReceivable->paid = $newPaid;
            $accountReceivable->remaining = $newRemaining;
            $accountReceivable->status = $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas';
            $accountReceivable->save();

            // Sync invoice status with AR
            if ($accountReceivable->invoice) {
                $accountReceivable->invoice->status = $newRemaining <= 0.01 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'unpaid');
                $accountReceivable->invoice->save();
            }
        }
    }
}
