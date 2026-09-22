<?php

namespace Database\Seeders\Finance;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\InvoiceItem;

class PaymentRequestInvoiceSeeder extends Seeder
{
    public function run()
    {
        // Create a supplier with some terms
        $supplier = Supplier::factory()->create([ 'tempo_hutang' => 14 ]);

        // Generate a few purchase orders & invoices for the supplier
        for ($i = 1; $i <= 3; $i++) {
            $po = PurchaseOrder::factory()->create([
                'supplier_id' => $supplier->id,
                'status' => 'completed',
            ]);

            $item = PurchaseOrderItem::factory()->create([
                'purchase_order_id' => $po->id,
                'product_id' => \App\Models\Product::factory(),
                'quantity' => 2 + $i,
                'unit_price' => 75000 + ($i * 5000),
                'discount' => 0,
                'tax' => 0,
            ]);

            $total = ($item->quantity * $item->unit_price);

            $invoice = Invoice::factory()->create([
                'invoice_number' => 'PINV-'.now()->format('Ymd').'-00'.$i,
                'from_model_type' => PurchaseOrder::class,
                'from_model_id' => $po->id,
                'supplier_name' => $supplier->perusahaan,
                'invoice_date' => now()->subDays($i * 3),
                'due_date' => now()->subDays($i * 3)->addDays($supplier->tempo_hutang),
                'subtotal' => $total,
                'tax' => 0,
                'total' => $total,
                'status' => 'sent', // make them selectable
            ]);

            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->unit_price,
                'total' => $total,
            ]);
        }
    }
}
