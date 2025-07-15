<?php

namespace App\Observers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use Carbon\Carbon;

class InvoiceObserver
{
    public function created(Invoice $invoice)
    {
        if ($invoice->from_model_type == 'App\Models\PurchaseOrder') {
            // Create Account Payable
            $accountPayable = AccountPayable::create([
                'invoice_id' => $invoice->id,
                'supplier_id' => $invoice->fromModel->supplier_id,
                'total' => $invoice->total,
                'paid' => 0,
                'remaining' => $invoice->total,
                'status' => 'Belum Lunas'
            ]);
            // Create Ageing Schedule
            $accountPayable->ageingSchedule()->create([
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'days_outstanding' => Carbon::parse($invoice->invoice_date)->diffInDays($invoice->due_date),
                'bucket' => 'Current'
            ]);
        } elseif ($invoice->from_model_type == 'App\Models\SaleOrder') {
            // Create Account Receivable
            $accountReceivable = AccountReceivable::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->fromModel->customer_id,
                'total' => $invoice->total,
                'paid' => 0,
                'remaining' => $invoice->total,
                'status' => "Belum Lunas"
            ]);
            // Create Ageing Schedule
            $accountReceivable->ageingSchedule()->create([
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'days_outstanding' => Carbon::parse($invoice->invoice_date)->diffInDays($invoice->due_date),
                'bucket' => 'Current'
            ]);
        }
    }
}
