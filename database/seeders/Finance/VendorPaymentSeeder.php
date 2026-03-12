<?php

namespace Database\Seeders\Finance;

use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountPayable;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\ChartOfAccount;

class VendorPaymentSeeder extends Seeder
{
    public function run()
    {
        // make sure there is at least one cash/bank coa
        $coa = ChartOfAccount::firstOrCreate([
            'code' => '1101',
        ], [
            'name' => 'Kas Kecil',
            'type' => 'Asset',
            'is_current' => true,
        ]);

        $apCoa = ChartOfAccount::firstOrCreate([
            'code' => '2110',
        ], [
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_current' => true,
        ]);

        // Supplier and related purchase order + invoice
        $supplier = Supplier::factory()->create([ 'tempo_hutang' => 30 ]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed',
        ]);
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => \App\Models\Product::factory(),
            'quantity' => 5,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-'.now()->format('Ymd').'-001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'supplier_name' => $supplier->perusahaan,
            'invoice_date' => now(),
            'due_date' => now()->addDays($supplier->tempo_hutang),
            'subtotal' => 500000,
            'tax' => 0,
            'total' => 500000,
            'status' => 'draft',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $poItem->product_id,
            'quantity' => 5,
            'price' => 100000,
            'total' => 500000,
        ]);

        // create payable record (simulating system behavior)
        $ap = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'total' => 500000,
            'paid' => 0,
            'remaining' => 500000,
            'status' => 'Belum Lunas',
            'created_by' => 1,
        ]);

        // finally a vendor payment that covers the invoice
        $vendorPayment = VendorPayment::create([
            'supplier_id' => $supplier->id,
            'payment_date' => now(),
            'ntpn' => 'NTPN'.now()->format('Ymd').'123',
            'total_payment' => 500000,
            'coa_id' => $coa->id,
            'payment_method' => 'Cash',
            'notes' => 'Pelunasan invoice '. $invoice->invoice_number,
            'status' => 'Paid',
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $vendorPayment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'amount' => 500000,
            'coa_id' => $coa->id,
            'payment_date' => now(),
            'notes' => 'Bayar penuh',
        ]);
    }
}
