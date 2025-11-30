<?php

namespace App\Observers;

use App\Models\SaleOrder;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\Log;

class SaleOrderObserver
{
    /**
     * Handle the SaleOrder "updated" event.
     */
    public function updated(SaleOrder $saleOrder): void
    {
        $originalStatus = $saleOrder->getOriginal('status');
        $newStatus = $saleOrder->status;

        // Jika status berubah ke 'completed', buat invoice otomatis
        if ($originalStatus !== 'completed' && $newStatus === 'completed') {
            $this->createInvoiceForCompletedSaleOrder($saleOrder);
        }
    }

    /**
     * Create invoice automatically when Sale Order is completed
     */
    protected function createInvoiceForCompletedSaleOrder(SaleOrder $saleOrder): void
    {
        Log::info('SaleOrderObserver: Creating invoice for completed sale order', [
            'sale_order_id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
        ]);

        // Load relationships
        $saleOrder->loadMissing('saleOrderItem.product');

        // Cek apakah sudah ada invoice untuk sale order ini
        $existingInvoice = Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $saleOrder->id)
            ->first();

        if ($existingInvoice) {
            Log::info('Invoice already exists for sale order', ['invoice_id' => $existingInvoice->id]);
            return;
        }

        // Hitung subtotal, tax, dll dari sale order items
        $subtotal = 0;
        $tax = 0;
        $invoiceItems = [];

        foreach ($saleOrder->saleOrderItem as $item) {
            $lineSubtotal = $item->quantity * ($item->unit_price - $item->discount);
            $lineTax = $lineSubtotal * ($item->tax / 100);
            $subtotal += $lineSubtotal;
            $tax += $lineTax;

            $invoiceItems[] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount' => $item->discount,
                'tax' => $item->tax,
                'subtotal' => $lineSubtotal + $lineTax,
                'notes' => $item->notes,
            ];
        }

        $total = $subtotal + $tax;

        // Buat invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-' . $saleOrder->so_number . '-' . now()->format('YmdHis'),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_id' => $saleOrder->customer_id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), // Default 30 hari
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'status' => 'unpaid', // Atau sesuai logic
            'notes' => 'Auto-generated from completed Sale Order ' . $saleOrder->so_number,
        ]);

        // Buat invoice items
        foreach ($invoiceItems as $itemData) {
            InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
        }

        // InvoiceObserver akan handle creation of AR dan journal entries

        Log::info('Invoice created successfully', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);
    }
}