<?php

namespace App\Observers;

use App\Models\SaleOrder;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\InvoiceItem;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
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

        // Jika status berubah ke 'approved', buat warehouse confirmation otomatis
        if ($originalStatus !== 'approved' && $newStatus === 'approved') {
            $this->createWarehouseConfirmationForApprovedSaleOrder($saleOrder);
        }

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
        $saleOrder->loadMissing('saleOrderItem.product', 'deliveryOrder');

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
                'price' => $item->unit_price,
                'discount' => $item->discount,
                'tax_rate' => $item->tax,
                'tax_amount' => $lineTax,
                'subtotal' => $lineSubtotal,
                'total' => $lineSubtotal + $lineTax,
            ];
        }

        // Hitung biaya tambahan dari delivery orders yang terkait
        $additionalCosts = 0;
        $otherFees = [];

        foreach ($saleOrder->deliveryOrder as $deliveryOrder) {
            if ($deliveryOrder->additional_cost > 0) {
                $additionalCosts += $deliveryOrder->additional_cost;
                $otherFees[] = [
                    'amount' => $deliveryOrder->additional_cost,
                    'description' => $deliveryOrder->additional_cost_description ?: 'Biaya pengiriman DO ' . $deliveryOrder->do_number,
                    'type' => 'delivery_cost',
                    'reference' => $deliveryOrder->do_number,
                ];
            }
        }

        $total = $subtotal + $tax + $additionalCosts;

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
            'other_fee' => $otherFees, // Tambahkan biaya tambahan dari delivery orders
            'delivery_orders' => $saleOrder->deliveryOrder->pluck('id')->toArray(), // Tambahkan delivery order IDs
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
            'subtotal' => $subtotal,
            'tax' => $tax,
            'additional_costs' => $additionalCosts,
            'total' => $total,
            'delivery_orders_count' => $saleOrder->deliveryOrder->count(),
        ]);
    }

    /**
     * Create warehouse confirmation automatically when Sale Order is approved
     */
    protected function createWarehouseConfirmationForApprovedSaleOrder(SaleOrder $saleOrder): void
    {
        Log::info('SaleOrderObserver: Creating warehouse confirmation for approved sale order', [
            'sale_order_id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
        ]);

        // Load relationships
        $saleOrder->loadMissing('saleOrderItem.product');

        // Cek apakah sudah ada warehouse confirmation untuk sale order ini
        $existingWC = WarehouseConfirmation::where('sale_order_id', $saleOrder->id)->first();

        if ($existingWC) {
            Log::info('Warehouse confirmation already exists for sale order', ['wc_id' => $existingWC->id]);
            return;
        }

        // Hanya buat warehouse confirmation untuk tipe pengiriman 'Kirim Langsung'
        if ($saleOrder->tipe_pengiriman !== 'Kirim Langsung') {
            Log::info('Skipping warehouse confirmation creation - not "Kirim Langsung" type', [
                'tipe_pengiriman' => $saleOrder->tipe_pengiriman
            ]);
            return;
        }

        // Cek apakah stock mencukupi untuk semua items
        $hasInsufficientStock = $saleOrder->hasInsufficientStock();
        $wcStatus = $hasInsufficientStock ? 'request' : 'confirmed';
        $itemStatus = $hasInsufficientStock ? 'request' : 'confirmed';

        Log::info('SaleOrderObserver: Stock check result', [
            'sale_order_id' => $saleOrder->id,
            'has_insufficient_stock' => $hasInsufficientStock,
            'wc_status' => $wcStatus,
            'item_status' => $itemStatus,
        ]);

        // Buat warehouse confirmation dengan status sesuai stock availability
        $warehouseConfirmation = WarehouseConfirmation::create([
            'sale_order_id' => $saleOrder->id,
            'confirmation_type' => 'sales_order',
            'status' => $wcStatus,
            'note' => 'Auto-generated from approved Sale Order ' . $saleOrder->so_number,
            'confirmed_by' => $hasInsufficientStock ? null : $saleOrder->approve_by, // Set confirmed_by jika auto-approved
            'confirmed_at' => $hasInsufficientStock ? null : now(),
        ]);

        // Buat warehouse confirmation items
        foreach ($saleOrder->saleOrderItem as $item) {
            // Skip item yang tidak memiliki warehouse_id
            if (!$item->warehouse_id) {
                Log::warning('Skipping warehouse confirmation item - no warehouse_id', [
                    'sale_order_item_id' => $item->id,
                    'product_name' => $item->product->name ?? 'Unknown'
                ]);
                continue;
            }

            WarehouseConfirmationItem::create([
                'warehouse_confirmation_id' => $warehouseConfirmation->id,
                'sale_order_item_id' => $item->id,
                'product_name' => $item->product->name ?? 'Unknown Product',
                'requested_qty' => $item->quantity,
                'confirmed_qty' => $item->quantity, // Default to full quantity
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id, // Now nullable
                'status' => $itemStatus
            ]);
        }

        Log::info('Warehouse confirmation created successfully', [
            'wc_id' => $warehouseConfirmation->id,
            'sale_order_id' => $saleOrder->id,
            'status' => $wcStatus,
            'auto_approved' => !$hasInsufficientStock,
        ]);

        // Jika WC status adalah confirmed, buat delivery order otomatis
        if ($wcStatus === 'confirmed') {
            // Load relationships yang diperlukan
            $warehouseConfirmation->load('warehouseConfirmationItems.saleOrderItem.product');
            
            // Panggil method untuk membuat delivery order
            \App\Models\WarehouseConfirmation::createDeliveryOrderForConfirmedWarehouseConfirmation($warehouseConfirmation);
        }
    }
}