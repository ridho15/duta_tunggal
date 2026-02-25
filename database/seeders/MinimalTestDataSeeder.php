<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;

class MinimalTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating minimal test data...');

        // ensure entire COA tree exists for tests requiring specific accounts
        $this->call(ChartOfAccountSeeder::class);


        // Check and create minimal chart of accounts
        $kasAccount = ChartOfAccount::firstOrCreate(['code' => '1-1000'], [
            'code' => '1-1000',
            'name' => 'Kas',
            'type' => 'Asset',
            'parent_id' => null
        ]);

        $piutangAccount = ChartOfAccount::firstOrCreate(['code' => '1-1200'], [
            'code' => '1-1200',
            'name' => 'Piutang Usaha',
            'type' => 'Asset',
            'parent_id' => null
        ]);

        $hutangAccount = ChartOfAccount::firstOrCreate(['code' => '2-1000'], [
            'code' => '2-1000',
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'parent_id' => null
        ]);

        $penyesuaianPiutangAccount = ChartOfAccount::firstOrCreate(['code' => '1-1400'], [
            'code' => '1-1400',
            'name' => 'Penyesuaian Piutang',
            'type' => 'Asset',
            'parent_id' => null
        ]);

        $penyesuaianHutangAccount = ChartOfAccount::firstOrCreate(['code' => '2-1100'], [
            'code' => '2-1100',
            'name' => 'Penyesuaian Hutang',
            'type' => 'Liability',
            'parent_id' => null
        ]);

        // Create currency
        $idr = Currency::firstOrCreate(['code' => 'IDR'], [
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp'
        ]);

        // Create simple customers
        $customer1 = Customer::firstOrCreate(['code' => 'CUST001'], [
            'name' => 'PT Test Customer',
            'code' => 'CUST001',
            'email' => 'test@customer.com',
            'phone' => '021-11111111',
            'telephone' => '021-11111111',
            'address' => 'Jl. Test Customer No. 1',
            'perusahaan' => 'PT Test Customer',
            'tipe' => 'PKP',
            'fax' => '021-11111112',
            'nik_npwp' => '1234567890123456',
            'tempo_kredit' => 30,
            'kredit_limit' => 50000000,
            'tipe_pembayaran' => 'Kredit'
        ]);

        // Create simple suppliers
        $supplier1 = Supplier::firstOrCreate(['code' => 'SUPP001'], [
            'name' => 'PT Test Supplier',
            'code' => 'SUPP001',
            'email' => 'test@supplier.com',
            'phone' => '021-22222222',
            'handphone' => '081234567890',
            'fax' => '021-22222223',
            'npwp' => '1234567890123456',
            'address' => 'Jl. Test Supplier No. 1',
            'perusahaan' => 'PT Test Supplier',
            'tempo_hutang' => 30
        ]);

        // Create simple products
        $product1 = Product::firstOrCreate(['sku' => 'PRD001'], [
            'sku' => 'PRD001',
            'name' => 'Test Product 1',
            'description' => 'Test product untuk testing',
            'cabang_id' => 1,
            'product_category_id' => 1,
            'cost_price' => 1000000,
            'sell_price' => 1500000,
            'uom_id' => 1,
            'kode_merk' => 'TEST001'
        ]);

        $product2 = Product::firstOrCreate(['sku' => 'PRD002'], [
            'sku' => 'PRD002',
            'name' => 'Test Product 2',
            'description' => 'Test product kedua untuk testing',
            'cabang_id' => 1,
            'product_category_id' => 1,
            'cost_price' => 2000000,
            'sell_price' => 3000000,
            'uom_id' => 1,
            'kode_merk' => 'TEST002'
        ]);

        // Create Sales Orders and Invoices
        for ($i = 1; $i <= 3; $i++) {
            $saleOrder = SaleOrder::firstOrCreate(['so_number' => "SO-2024-00{$i}"], [
                'so_number' => "SO-2024-00{$i}",
                'customer_id' => $customer1->id,
                'order_date' => now()->subDays(30 - $i),
                'delivery_date' => now()->subDays(25 - $i),
                'status' => 'completed',
                'total_amount' => 0
            ]);

            // Create sale order items
            $saleOrderItem1 = SaleOrderItem::firstOrCreate([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product1->id
            ], [
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product1->id,
                'quantity' => 2,
                'unit_price' => $product1->sell_price,
                'discount' => 0,
                'tax' => 0,
                'warehouse_id' => 1,
                'rak_id' => 1
            ]);

            $saleOrderItem2 = SaleOrderItem::firstOrCreate([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product2->id
            ], [
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product2->id,
                'quantity' => 1,
                'unit_price' => $product2->sell_price,
                'discount' => 0,
                'tax' => 0,
                'warehouse_id' => 1,
                'rak_id' => 1
            ]);

            // Update sale order total
            $total = ($saleOrderItem1->quantity * $saleOrderItem1->unit_price) + 
                    ($saleOrderItem2->quantity * $saleOrderItem2->unit_price);
            $saleOrder->update(['total_amount' => $total]);

            // Create invoice from sale order
            $invoice = Invoice::firstOrCreate(['invoice_number' => "INV-2024-00{$i}"], [
                'invoice_number' => "INV-2024-00{$i}",
                'from_model_type' => 'App\Models\SaleOrder',
                'from_model_id' => $saleOrder->id,
                'customer_name' => $customer1->name,
                'customer_phone' => $customer1->phone,
                'invoice_date' => now()->subDays(20 - $i),
                'due_date' => now()->subDays(20 - $i)->addDays(30),
                'subtotal' => $total,
                'other_fee' => 0,
                'dpp' => $total,
                'tax' => 0,
                'ppn_rate' => 0,
                'total' => $total,
                'status' => 'sent'
            ]);

            // Create invoice items
            foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                InvoiceItem::firstOrCreate([
                    'invoice_id' => $invoice->id,
                    'product_id' => $saleOrderItem->product_id
                ], [
                    'invoice_id' => $invoice->id,
                    'product_id' => $saleOrderItem->product_id,
                    'quantity' => $saleOrderItem->quantity,
                    'price' => $saleOrderItem->unit_price,
                    'total' => $saleOrderItem->quantity * $saleOrderItem->unit_price
                ]);
            }

            // Create Account Receivable
            AccountReceivable::firstOrCreate([
                'invoice_id' => $invoice->id
            ], [
                'invoice_id' => $invoice->id,
                'customer_id' => $customer1->id,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date,
                'original_amount' => $invoice->total,
                'remaining_amount' => $invoice->total * 0.7, // Simulate partial payment
                'status' => 'partial',
                'cabang_id' => 1
            ]);
        }

        // Create Purchase Orders and Invoices
        for ($i = 1; $i <= 2; $i++) {
            $purchaseOrder = PurchaseOrder::firstOrCreate(['po_number' => "PO-2024-00{$i}"], [
                'po_number' => "PO-2024-00{$i}",
                'supplier_id' => $supplier1->id,
                'order_date' => now()->subDays(25 - $i),
                'expected_date' => now()->subDays(20 - $i),
                'status' => 'completed',
                'total_amount' => 0,
                'warehouse_id' => 1,
                'tempo_hutang' => 30
            ]);

            // Create purchase order items
            $purchaseOrderItem1 = PurchaseOrderItem::firstOrCreate([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product1->id
            ], [
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product1->id,
                'quantity' => 10,
                'unit_price' => $product1->cost_price,
                'discount' => 0,
                'tax' => 0,
                'currency_id' => $idr->id
            ]);

            // Update purchase order total
            $total = $purchaseOrderItem1->quantity * $purchaseOrderItem1->unit_price;
            $purchaseOrder->update(['total_amount' => $total]);

            // Create invoice from purchase order
            $invoice = Invoice::firstOrCreate(['invoice_number' => "PINV-2024-00{$i}"], [
                'invoice_number' => "PINV-2024-00{$i}",
                'from_model_type' => 'App\Models\PurchaseOrder',
                'from_model_id' => $purchaseOrder->id,
                'customer_name' => $supplier1->name,
                'customer_phone' => $supplier1->phone,
                'invoice_date' => now()->subDays(15 - $i),
                'due_date' => now()->subDays(15 - $i)->addDays(30),
                'subtotal' => $total,
                'other_fee' => 0,
                'dpp' => $total,
                'tax' => 0,
                'ppn_rate' => 0,
                'total' => $total,
                'status' => 'sent'
            ]);

            // Create invoice items
            foreach ($purchaseOrder->purchaseOrderItem as $purchaseOrderItem) {
                InvoiceItem::firstOrCreate([
                    'invoice_id' => $invoice->id,
                    'product_id' => $purchaseOrderItem->product_id
                ], [
                    'invoice_id' => $invoice->id,
                    'product_id' => $purchaseOrderItem->product_id,
                    'quantity' => $purchaseOrderItem->quantity,
                    'price' => $purchaseOrderItem->unit_price,
                    'total' => $purchaseOrderItem->quantity * $purchaseOrderItem->unit_price
                ]);
            }

            // Create Account Payable
            AccountPayable::firstOrCreate([
                'invoice_id' => $invoice->id
            ], [
                'invoice_id' => $invoice->id,
                'supplier_id' => $supplier1->id,
                'invoice_number' => $invoice->invoice_number,
                'due_date' => $invoice->due_date,
                'original_amount' => $invoice->total,
                'remaining_amount' => $invoice->total * 0.8, // Simulate partial payment
                'status' => 'partial',
                'cabang_id' => 1
            ]);
        }

        $this->command->info('Minimal test data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- ' . Customer::count() . ' customers');
        $this->command->info('- ' . Supplier::count() . ' suppliers');
        $this->command->info('- ' . Product::count() . ' products');
        $this->command->info('- ' . SaleOrder::count() . ' sale orders');
        $this->command->info('- ' . PurchaseOrder::count() . ' purchase orders');
        $this->command->info('- ' . Invoice::count() . ' invoices');
        $this->command->info('- ' . AccountReceivable::count() . ' account receivables');
        $this->command->info('- ' . AccountPayable::count() . ' account payables');
    }
}
