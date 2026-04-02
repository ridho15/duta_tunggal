<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerReceiptItemObserver
{
    public function created(CustomerReceiptItem $customerReceiptItem): void
    {
        $customerReceipt = $customerReceiptItem->customerReceipt;
        
        // Update customer receipt status based on all selected invoices
        $this->updateCustomerReceiptStatus($customerReceipt);

        $deposit = Deposit::where('from_model_type', 'App\Models\Customer')
            ->where('from_model_id', $customerReceipt->customer_id)->where('status', 'active')->first();
        if ($deposit) {
            $deposit->remaining_amount = $deposit->remaining_amount - $customerReceiptItem->amount;
            $deposit->used_amount = $deposit->used_amount + $customerReceiptItem->amount;
            $deposit->save();

            $customerReceiptItem->depositLog()->create([
                'deposit_id' => $deposit->id,
                'amount' => $customerReceiptItem->amount,
                'type' => 'use',
                'created_by' => Auth::user() ? Auth::user()->id : 1 // Fallback to user ID 1 if no auth context
            ]);
        }

        // Handle deposit usage for customer receipts (similar to vendor payments)
        if ($customerReceipt->payment_method === 'Deposit' || $customerReceiptItem->method === 'Deposit') {
            // Get all active deposits for this customer with remaining balance, ordered by creation date (FIFO)
            $availableDeposits = Deposit::where('from_model_type', 'App\Models\Customer')
                ->where('from_model_id', $customerReceipt->customer_id)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->orderBy('created_at', 'asc') // FIFO - oldest deposits first
                ->get();

            if ($availableDeposits->isNotEmpty()) {
                $remainingPaymentAmount = $customerReceiptItem->amount;

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
                    $customerReceiptItem->depositLog()->create([
                        'deposit_id' => $deposit->id,
                        'amount' => $amountToUse,
                        'type' => 'use',
                        'created_by' => Auth::user() ? Auth::user()->id : 1
                    ]);

                    $remainingPaymentAmount -= $amountToUse;
                }

                // If payment couldn't be fully covered by available deposits
                if ($remainingPaymentAmount > 0) {
                    Log::warning("Insufficient deposit balance for customer receipt item ID {$customerReceiptItem->id}. Remaining amount: {$remainingPaymentAmount}");
                }
            } else {
                Log::warning("No available deposits found for customer ID {$customerReceipt->customer_id} in customer receipt item ID {$customerReceiptItem->id}");
            }
        }

        // Journal posting is handled at the CustomerReceipt level via LedgerPostingService.
        // Item observer only keeps deposits and AR status in sync.
    }
    
    private function updateCustomerReceiptStatus($customerReceipt)
    {
        // Check status of all invoices in selected_invoices
        if (!empty($customerReceipt->selected_invoices)) {
            $allPaid = true;
            $anyPartial = false;
            
            foreach ($customerReceipt->selected_invoices as $invoiceId) {
                $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                if ($accountReceivable) {
                    if ($accountReceivable->remaining > 0) {
                        $allPaid = false;
                        if ($accountReceivable->paid > 0) {
                            $anyPartial = true;
                        }
                    }
                }
            }
            
            if ($allPaid) {
                $customerReceipt->update(['status' => 'Paid']);
            } elseif ($anyPartial) {
                $customerReceipt->update(['status' => 'Partial']);
            }
        }
    }
}
