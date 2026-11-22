<?php

namespace App\Observers;

use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CustomerReceiptItemObserver
{
    public function creating(CustomerReceiptItem $customerReceiptItem): void
    {
        if ($customerReceiptItem->invoice_id) {
            $invoice = \App\Models\Invoice::find($customerReceiptItem->invoice_id);
            $rate = (float) ($invoice?->exchange_rate ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;

            $customerReceiptItem->currency_id = is_numeric($invoice?->currency_id ?? null) ? (int) $invoice->currency_id : null;
            $customerReceiptItem->exchange_rate = $rate;
        } else {
            $customerReceiptItem->exchange_rate = (float) ($customerReceiptItem->exchange_rate ?? 1) ?: 1;
        }

        $customerReceiptItem->amount_idr = (float) ($customerReceiptItem->amount_idr ?: $customerReceiptItem->amount);
    }

    public function created(CustomerReceiptItem $customerReceiptItem): void
    {
        $customerReceipt = $customerReceiptItem->customerReceipt;
        
        // Update customer receipt status based on all selected invoices
        $this->updateCustomerReceiptStatus($customerReceipt);

        // Saldo deposit dipotong HANYA untuk rincian bermetode Deposit — satu kali, FIFO lintas baris deposit aktif.
        // Rincian Tunai/Transfer/Giro/Cek/Pembulatan tidak menyentuh saldo titip customer.
        $method = strtolower((string) ($customerReceiptItem->method ?: $customerReceipt->payment_method));
        if ($method === 'deposit') {
            $this->consumeDeposits($customerReceiptItem, (int) $customerReceipt->customer_id);
        }

        // Journal posting is handled at the CustomerReceipt level via LedgerPostingService.
        // Item observer only keeps deposits and AR status in sync.
    }
    
    /**
     * @throws ValidationException saldo deposit customer tidak cukup (jurnal Dr Deposit Pelanggan tidak boleh melebihi subledger)
     */
    private function consumeDeposits(CustomerReceiptItem $item, int $customerId): void
    {
        $need = round((float) $item->amount, 2);
        $deposits = Deposit::where('from_model_type', \App\Models\Customer::class)
            ->where('from_model_id', $customerId)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->orderBy('created_at')->orderBy('id')   // FIFO — setoran terlama dipakai lebih dulu
            ->lockForUpdate()
            ->get();

        $available = round((float) $deposits->sum('remaining_amount'), 2);
        if ($available + 0.005 < $need) {
            throw ValidationException::withMessages([
                'payment_method' => sprintf(
                    'Saldo Deposit Pelanggan tidak mencukupi: dibutuhkan Rp %s, saldo Rp %s. Kurangi nominal atau pilih metode Tunai/Transfer untuk sisanya.',
                    number_format($need, 2, ',', '.'),
                    number_format($available, 2, ',', '.')
                ),
            ]);
        }

        foreach ($deposits as $deposit) {
            if ($need <= 0.005) {
                break;
            }

            $take = round(min($need, (float) $deposit->remaining_amount), 2);
            $deposit->remaining_amount = round((float) $deposit->remaining_amount - $take, 2);
            $deposit->used_amount = round((float) $deposit->used_amount + $take, 2);
            if ($deposit->remaining_amount <= 0.005) {
                $deposit->status = 'closed';
            }
            $deposit->save();

            $item->depositLog()->create([
                'deposit_id' => $deposit->id,
                'amount' => $take,
                'type' => 'use',
                'created_by' => Auth::id() ?? $deposit->created_by,
            ]);

            $need = round($need - $take, 2);
        }
    }

    private function updateCustomerReceiptStatus($customerReceipt)
    {
        // Check status of all invoices in selected_invoices
        if (!empty($customerReceipt->selected_invoices)) {
            $selectedInvoiceIds = array_values(array_unique(array_filter(array_map(
                'intval',
                is_array($customerReceipt->selected_invoices)
                    ? $customerReceipt->selected_invoices
                    : (json_decode($customerReceipt->selected_invoices, true) ?? [])
            ))));

            $allPaid = true;
            $anyPartial = false;
            
            foreach ($selectedInvoiceIds as $invoiceId) {
                $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                if ($accountReceivable) {
                    if ($accountReceivable->remaining > 1.00) {
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
