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

        // Jika status berubah ke 'completed', buat invoice otomatis dan kurangi stock untuk Ambil Sendiri
        if ($originalStatus !== 'completed' && $newStatus === 'completed') {
            $this->createInvoiceForCompletedSaleOrder($saleOrder);
            $this->handleStockReductionForSelfPickup($saleOrder);
        }

        // Sync related journal entries if total amount changed
        if ($saleOrder->wasChanged('total_amount')) {
            $this->syncJournalEntries($saleOrder);
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
        $saleOrder->loadMissing('saleOrderItem.product', 'deliveryOrder', 'customer');

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
            // Calculate subtotal using HelperController for consistency
            $lineSubtotal = \App\Http\Controllers\HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax, $item->tipe_pajak ?? null);
            // Use TaxService to get correct breakdown
            $taxService = \App\Services\TaxService::class;
            $baseAmount = $item->quantity * $item->unit_price * (1 - $item->discount / 100);
            $taxResult = $taxService::compute($baseAmount, $item->tax, $item->tipe_pajak ?? null);
            $lineTax = $taxResult['ppn'];
            $subtotalBeforeTax = $taxResult['dpp'];
            $subtotal += $subtotalBeforeTax;
            $tax += $lineTax;

            // Get sales COA from product
            $salesCoaId = $item->product?->sales_coa_id;

            $invoiceItems[] = [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'discount' => $item->discount,
                'tax_rate' => $item->tax,
                'tax_amount' => $lineTax,
                'subtotal' => $subtotalBeforeTax,
                'total' => $lineSubtotal,
                'coa_id' => $salesCoaId, // Add sales COA ID from product
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

        // Load customer data
        $saleOrder->load('customer');

        Log::info('SaleOrderObserver: Customer data before invoice creation', [
            'sale_order_id' => $saleOrder->id,
            'customer_exists' => $saleOrder->customer ? true : false,
            'customer_name' => $saleOrder->customer?->name,
            'customer_phone' => $saleOrder->customer?->phone,
            'customer_object' => $saleOrder->customer,
        ]);

        $invoiceData = [
            'invoice_number' => 'INV-' . $saleOrder->so_number . '-' . now()->format('YmdHis'),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_name' => $saleOrder->customer?->name,
            'customer_phone' => $saleOrder->customer?->phone,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(), // Default 30 hari
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'other_fee' => $otherFees, // Tambahkan biaya tambahan dari delivery orders
            'delivery_orders' => $saleOrder->deliveryOrder->pluck('id')->toArray(), // Tambahkan delivery order IDs
            'status' => 'unpaid', // Atau sesuai logic
            'notes' => 'Auto-generated from completed Sale Order ' . $saleOrder->so_number,
        ];

        Log::info('SaleOrderObserver: Invoice data to be created', $invoiceData);

        // Buat invoice
        $invoice = new Invoice($invoiceData);
        $invoice->save();

        Log::info('SaleOrderObserver: Invoice created successfully', [
            'sale_order_id' => $saleOrder->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ]);

        // Buat invoice items
        foreach ($invoiceItems as $itemData) {
            InvoiceItem::create(array_merge($itemData, ['invoice_id' => $invoice->id]));
        }

        // Post journal entries after invoice and items are created
        $invoiceObserver = new \App\Observers\InvoiceObserver();
        $invoiceObserver->postSalesInvoice($invoice);
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

    /**
     * Sync journal entries when sale order amounts change
     */
    protected function syncJournalEntries(SaleOrder $saleOrder): void
    {
        $journalEntries = $saleOrder->journalEntries()
            ->where('journal_type', 'sales')
            ->get();

        if ($journalEntries->isEmpty()) {
            return;
        }

        $reference = 'SO-' . $saleOrder->so_number;
        $description = 'Sales Order: ' . $saleOrder->so_number;

        foreach ($journalEntries as $entry) {
            // Only update if the entry is directly linked to the SO
            // (not through invoice, which should have its own sync logic)
            if ($entry->source_type === 'App\\Models\\SaleOrder') {
                $updates = [
                    'reference' => $reference,
                    'description' => $description,
                    'date' => $saleOrder->order_date,
                ];

                // Update credit amount if this is a simple credit entry (no debit)
                if ($entry->credit > 0 && $entry->debit == 0) {
                    $updates['credit'] = $saleOrder->total_amount;
                }

                $entry->update($updates);
            }
        }

        Log::info('SaleOrder journal entries synced', [
            'sale_order_id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
            'entries_updated' => $journalEntries->count(),
        ]);
    }

    /**
     * Handle stock reduction for self-pickup sales orders
     */
    protected function handleStockReductionForSelfPickup(SaleOrder $saleOrder): void
    {
        // Only handle stock reduction for 'Ambil Sendiri' type
        if ($saleOrder->tipe_pengiriman !== 'Ambil Sendiri') {
            return;
        }

        Log::info('SaleOrderObserver: Handling stock reduction for self-pickup', [
            'sale_order_id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
        ]);

        // Load sale order items with product data
        $saleOrder->loadMissing('saleOrderItem.product');

        $date = $saleOrder->order_date ?? now()->toDateString();

        // Create stock movements for each item to reduce physical inventory
        foreach ($saleOrder->saleOrderItem as $item) {
            $qtySold = max(0, $item->quantity ?? 0);
            if ($qtySold <= 0) {
                continue;
            }

            $product = $item->product;
            if (!$product) {
                continue;
            }

            // Skip if warehouse_id is null
            if (!$item->warehouse_id) {
                continue;
            }

            // Create sales stock movement to reduce physical inventory
            $productService = app(\App\Services\ProductService::class);
            $productService->createStockMovement(
                product_id: $product->id,
                warehouse_id: $item->warehouse_id,
                quantity: $qtySold,
                type: 'sales',
                date: $date,
                notes: "Self-pickup sales for SO {$saleOrder->so_number}",
                rak_id: $item->rak_id,
                fromModel: null,
                value: $product->cost_price * $qtySold
            );
        }
    }
}