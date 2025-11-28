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
            
            // Post journal entries for sales invoice
            $this->postSalesInvoice($invoice);
        }

        // If invoice already paid on creation, post to ledger
        if (strtolower($invoice->status) === 'paid') {
            $this->ledger->postInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice)
    {
        // When invoice status becomes 'paid', post to ledger
        if (strtolower($invoice->status) === 'paid') {
            $this->ledger->postInvoice($invoice);
        }
    }

    protected function postSalesInvoice(Invoice $invoice)
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
        $biayaPengirimanCoa = $invoice->biayaPengirimanCoa ?? \App\Models\ChartOfAccount::where('code', '6100.02')->first(); // Biaya Pengiriman

        if (!$arCoa || !$revenueCoa) {
            return; // Skip if essential COAs not found
        }

        $subtotal = (float) $invoice->subtotal;
        $tax = (float) $invoice->tax;
        $otherFeeTotal = $invoice->getOtherFeeTotalAttribute();

        // DEBIT: Accounts Receivable (customer owes money)
        \App\Models\JournalEntry::create([
            'coa_id' => $arCoa->id,
            'date' => $date,
            'reference' => $invoice->invoice_number,
            'description' => 'Sales Invoice - Accounts Receivable',
            'debit' => $subtotal + $tax + $otherFeeTotal,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        // CREDIT: Revenue/Sales (income from sales)
        if ($subtotal > 0) {
            \App\Models\JournalEntry::create([
                'coa_id' => $revenueCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - Revenue',
                'debit' => 0,
                'credit' => $subtotal,
                'journal_type' => 'sales',
                'source_type' => Invoice::class,
                'source_id' => $invoice->id,
            ]);
        }

        // CREDIT: PPn Keluaran (output VAT)
        if ($tax > 0 && $ppnKeluaranCoa) {
            \App\Models\JournalEntry::create([
                'coa_id' => $ppnKeluaranCoa->id,
                'date' => $date,
                'reference' => $invoice->invoice_number,
                'description' => 'Sales Invoice - PPn Keluaran',
                'debit' => 0,
                'credit' => $tax,
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
