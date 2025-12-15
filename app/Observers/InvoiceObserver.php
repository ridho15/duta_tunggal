<?php

namespace App\Observers;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Services\LedgerPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InvoiceObserver
{
    protected $ledger;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function created(Invoice $invoice)
    {
        Log::info('InvoiceObserver: created method called', [
            'invoice_id' => $invoice->id,
            'customer_name_before' => $invoice->customer_name,
            'customer_phone_before' => $invoice->customer_phone,
            'from_model_type' => $invoice->from_model_type,
        ]);

        // Create AP or AR depending on source
        if ($invoice->from_model_type == 'App\\Models\\PurchaseOrder') {
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

            // Post journal entries for purchase invoice (accrual basis)
            $this->ledger->postInvoice($invoice);
        } elseif ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
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

            // Note: postSalesInvoice will be called manually by SaleOrderObserver after invoice items are created
        }

        // If invoice already paid on creation, post to ledger
        if (strtolower($invoice->status) === 'paid') {
            $this->ledger->postInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice)
    {
        Log::info('InvoiceObserver: updated method called', [
            'invoice_id' => $invoice->id,
            'customer_name_before' => $invoice->getOriginal('customer_name'),
            'customer_name_after' => $invoice->customer_name,
            'customer_phone_before' => $invoice->getOriginal('customer_phone'),
            'customer_phone_after' => $invoice->customer_phone,
            'changed_attributes' => $invoice->getChanges(),
        ]);

        // Check if critical financial fields changed (amounts, dates, etc.)
        $financialFields = ['subtotal', 'total', 'ppn_rate', 'invoice_date', 'other_fee'];
        $financialChanged = false;
        foreach ($financialFields as $field) {
            if ($invoice->wasChanged($field)) {
                $financialChanged = true;
                break;
            }
        }

        // If financial fields changed, reverse existing journal entries and re-post
        if ($financialChanged) {
            Log::info('Invoice financial fields changed, reversing and re-posting journal entries', [
                'invoice_id' => $invoice->id,
                'changed_fields' => array_intersect_key($invoice->getChanges(), array_flip($financialFields))
            ]);

            // Delete existing journal entries
            \App\Models\JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->delete();

            // Re-post journal entries with new amounts
            if ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
                $this->postSalesInvoice($invoice);
            } else {
                $this->ledger->postInvoice($invoice);
            }
        }

        // When invoice status becomes 'paid', post to ledger (if not already posted)
        if (strtolower($invoice->status) === 'paid') {
            if ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
                $this->postSalesInvoice($invoice);
            } else {
                $this->ledger->postInvoice($invoice);
            }
        }

        // When invoice status becomes 'approved', post sales invoice journal entries
        if ($invoice->wasChanged('status') && strtolower($invoice->status) === 'approved') {
            if ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
                $this->postSalesInvoice($invoice);
            }
        }
    }

    public function deleting(Invoice $invoice)
    {
        // Hapus account payable dan account receivable ketika invoice dihapus
        if ($invoice->from_model_type == 'App\\Models\\PurchaseOrder') {
            $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
            if ($accountPayable) {
                $accountPayable->delete(); // Ini akan trigger deleting di AccountPayable yang menghapus ageing schedule
            }
        } elseif ($invoice->from_model_type == 'App\\Models\\SaleOrder') {
            $accountReceivable = AccountReceivable::where('invoice_id', $invoice->id)->first();
            if ($accountReceivable) {
                $accountReceivable->delete(); // Asumsikan AccountReceivable juga punya logic serupa
            }
        }
    }

    public function deleted(Invoice $invoice)
    {
        // Hapus journal entries yang terkait dengan invoice yang dihapus
        \App\Models\JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->delete();

        Log::info('Invoice deleted, related journal entries cleaned up', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }

    public function postSalesInvoice(Invoice $invoice)
    {
        // Prevent duplicate posting
        if (\App\Models\JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->exists()) {
            return;
        }

        $date = $invoice->invoice_date ?? Carbon::now()->toDateString();

        // Get COAs from invoice or fallback to defaults
        $arCoa = $invoice->arCoa ?? \App\Models\ChartOfAccount::where('code', '1120')->first(); // Accounts Receivable
        $revenueCoa = $invoice->revenueCoa ?? \App\Models\ChartOfAccount::where('code', '4000')->first(); // Revenue/Sales
        $ppnKeluaranCoa = $invoice->ppnKeluaranCoa ?? \App\Models\ChartOfAccount::where('code', '2120.06')->first(); // PPn Keluaran
        $discountCoa = \App\Models\ChartOfAccount::where('code', '4100.01')->first(); // Sales Discount
        $biayaPengirimanCoa = $invoice->biayaPengirimanCoa ?? \App\Models\ChartOfAccount::where('code', '6100.02')->first(); // Biaya Pengiriman

        if (!$arCoa || !$revenueCoa) {
            dd('COAs not found', ['ar_coa' => $arCoa, 'revenue_coa' => $revenueCoa, 'all_coas' => \App\Models\ChartOfAccount::all()->pluck('code')->toArray()]);
            Log::error('Essential COAs not found for sales invoice', [
                'invoice_id' => $invoice->id,
                'ar_coa' => $arCoa ? $arCoa->id : null,
                'revenue_coa' => $revenueCoa ? $revenueCoa->id : null,
            ]);
            return; // Skip if essential COAs not found
        }

        $invoice->loadMissing('invoiceItem.product');

        // Calculate totals from invoice items for detailed breakdown
        $totalRevenue = 0;
        $totalTax = 0;
        $totalDiscount = 0;
        $otherFeeTotal = $invoice->getOtherFeeTotalAttribute();

        // DEBIT: Accounts Receivable (customer owes money) - total amount
        $grandTotal = $invoice->total;

        \App\Models\JournalEntry::create([
            'coa_id' => $arCoa->id,
            'date' => $date,
            'reference' => $invoice->invoice_number,
            'description' => 'Sales Invoice - Accounts Receivable',
            'debit' => $grandTotal,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        // Create detailed CREDIT entries for each invoice item
        foreach ($invoice->invoiceItem as $item) {
            $productName = $item->product->name ?? 'Unknown Product';
            $subtotal = (float) $item->subtotal; // Revenue after discount
            $taxAmount = (float) $item->tax_amount;
            $discountAmount = (float) $item->discount * (float) $item->quantity;

            // CREDIT: Revenue/Sales for this item
            if ($item->total > 0) {
                $itemCoaId = $item->product->sales_coa_id ?? $revenueCoa->id;
                \App\Models\JournalEntry::create([
                    'coa_id' => $itemCoaId,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => "Sales Invoice - Revenue: {$productName}",
                    'debit' => 0,
                    'credit' => $item->subtotal, // Use subtotal (revenue before tax)
                    'journal_type' => 'sales',
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
                $totalRevenue += $item->subtotal;
            }

            // DEBIT: Sales Discount for this item (if any)
            if ($discountAmount > 0 && $discountCoa) {
                \App\Models\JournalEntry::create([
                    'coa_id' => $discountCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => "Sales Invoice - Discount: {$productName}",
                    'debit' => $discountAmount,
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
                $totalDiscount += $discountAmount;
            }

        }

        // CREDIT: PPn Keluaran at invoice level (if any tax difference)
        $invoiceTax = (float) $invoice->tax;
        $calculatedTax = (float) $invoice->total - (float) $invoice->subtotal - $otherFeeTotal;
        $totalTaxAmount = max($invoiceTax, $calculatedTax);
        
        if ($totalTaxAmount > 0 && $ppnKeluaranCoa) {
            \App\Models\JournalEntry::create([
                'coa_id' => $ppnKeluaranCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - PPn Keluaran',
                'debit' => 0,
                'credit' => $totalTaxAmount,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
            ]);
        }

        // CREDIT: Biaya Pengiriman (shipping/other costs)
        if ($otherFeeTotal > 0 && $biayaPengirimanCoa) {
            \App\Models\JournalEntry::create([
                'coa_id' => $biayaPengirimanCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Biaya Pengiriman',
                'debit' => 0,
                'credit' => $otherFeeTotal,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
            ]);
        }

        Log::info('Sales invoice journal entries created', [
            'invoice_id' => $invoice->id,
            'total_revenue' => $totalRevenue,
            'total_tax' => $totalTax,
            'total_discount' => $totalDiscount,
            'other_fees' => $otherFeeTotal,
            'grand_total' => $grandTotal,
        ]);

        $this->postCostOfSalesEntries($invoice, $date);
    }

    protected function postCostOfSalesEntries(Invoice $invoice, string $date): void
    {
        $invoice->loadMissing([
            'invoiceItem.product.cogsCoa',
            'invoiceItem.product.goodsDeliveryCoa',
        ]);

        // Allow fallback sources (delivery orders) when invoice items are absent

        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::where('code', '1140.20')->first()
            ?? \App\Models\ChartOfAccount::where('code', '1180.10')->first();
        $defaultCogsCoa = \App\Models\ChartOfAccount::where('code', '5100.10')->first()
            ?? \App\Models\ChartOfAccount::where('code', '5000')->first();

        $debitTotals = [];
        $creditTotals = [];

        foreach ($invoice->invoiceItem as $item) {
            $quantity = max(0, (float) ($item->quantity ?? 0));
            $costPrice = (float) ($item->product?->cost_price ?? 0);

            if ($quantity <= 0 || $costPrice <= 0) {
                continue;
            }

            $lineAmount = round($quantity * $costPrice, 2);
            if ($lineAmount <= 0) {
                continue;
            }

            $cogsCoa = $item->product?->cogsCoa?->exists ? $item->product->cogsCoa : $defaultCogsCoa;
            $goodsDeliveryCoa = $item->product?->goodsDeliveryCoa?->exists ? $item->product->goodsDeliveryCoa : $defaultGoodsDeliveryCoa;

            $this->pushCostTotals($debitTotals, $creditTotals, $lineAmount, $cogsCoa, $goodsDeliveryCoa);
        }

        if (empty($debitTotals) || empty($creditTotals)) {
            $this->accumulateFromDeliveryOrders($invoice, $debitTotals, $creditTotals, $defaultCogsCoa, $defaultGoodsDeliveryCoa);
        }

        if (empty($debitTotals) || empty($creditTotals)) {
            return;
        }

        foreach ($debitTotals as $debitData) {
            \App\Models\JournalEntry::create([
                'coa_id' => $debitData['coa']->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Cost of Goods Sold for ' . $invoice->invoice_number,
                'debit' => round($debitData['amount'], 2),
                'credit' => 0,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
            ]);
        }

        foreach ($creditTotals as $creditData) {
            \App\Models\JournalEntry::create([
                'coa_id' => $creditData['coa']->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Release Barang Terkirim for ' . $invoice->invoice_number,
                'debit' => 0,
                'credit' => round($creditData['amount'], 2),
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
            ]);
        }
    }

    protected function accumulateFromDeliveryOrders(Invoice $invoice, array &$debitTotals, array &$creditTotals, $defaultCogsCoa, $defaultGoodsDeliveryCoa): void
    {
        $deliveryOrderIds = array_filter((array) $invoice->delivery_orders);
        if (empty($deliveryOrderIds)) {
            return;
        }

        $deliveryOrders = \App\Models\DeliveryOrder::with([
            'deliveryOrderItem.product.cogsCoa',
            'deliveryOrderItem.product.goodsDeliveryCoa',
        ])->whereIn('id', $deliveryOrderIds)->get();

        foreach ($deliveryOrders as $deliveryOrder) {
            foreach ($deliveryOrder->deliveryOrderItem as $item) {
                $quantity = max(0, (float) ($item->quantity ?? 0));
                $costPrice = (float) ($item->product?->cost_price ?? 0);

                if ($quantity <= 0 || $costPrice <= 0) {
                    continue;
                }

                $amount = round($quantity * $costPrice, 2);
                $cogsCoa = $item->product?->cogsCoa?->exists ? $item->product->cogsCoa : $defaultCogsCoa;
                $goodsDeliveryCoa = $item->product?->goodsDeliveryCoa?->exists ? $item->product->goodsDeliveryCoa : $defaultGoodsDeliveryCoa;

                $this->pushCostTotals($debitTotals, $creditTotals, $amount, $cogsCoa, $goodsDeliveryCoa);
            }
        }
    }

    protected function pushCostTotals(array &$debitTotals, array &$creditTotals, float $amount, $cogsCoa, $goodsDeliveryCoa): void
    {
        if (! $cogsCoa || ! $goodsDeliveryCoa || $amount <= 0) {
            return;
        }

        $debitTotals[$cogsCoa->id]['coa'] = $cogsCoa;
        $debitTotals[$cogsCoa->id]['amount'] = ($debitTotals[$cogsCoa->id]['amount'] ?? 0) + $amount;

        $creditTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
        $creditTotals[$goodsDeliveryCoa->id]['amount'] = ($creditTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $amount;
    }
}
