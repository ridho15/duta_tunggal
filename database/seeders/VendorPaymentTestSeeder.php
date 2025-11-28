<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountPayable;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Carbon\Carbon;

class VendorPaymentTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Test Suppliers
        $supplier1 = Supplier::firstOrCreate(
            ['code' => 'TEST-SUP001'],
            [
                'code' => 'TEST-SUP001',
                'name' => 'PT Test Supplier Satu',
                'perusahaan' => 'PT Test Supplier Satu',
                'email' => 'test1@supplier.com',
                'phone' => '021-1111111',
                'handphone' => '0811-1111-1111',
                'fax' => '021-1111112',
                'npwp' => '11.111.111.1-111.111',
                'address' => 'Jl. Test No. 1, Jakarta',
                'kontak_person' => 'Test Person 1',
                'tempo_hutang' => 30,
                'keterangan' => 'Test supplier untuk vendor payment',
            ]
        );

        $supplier2 = Supplier::firstOrCreate(
            ['code' => 'TEST-SUP002'],
            [
                'code' => 'TEST-SUP002',
                'name' => 'CV Test Supplier Dua',
                'perusahaan' => 'CV Test Supplier Dua',
                'email' => 'test2@supplier.com',
                'phone' => '021-2222222',
                'handphone' => '0822-2222-2222',
                'fax' => '021-2222223',
                'npwp' => '22.222.222.2-222.222',
                'address' => 'Jl. Test No. 2, Jakarta',
                'kontak_person' => 'Test Person 2',
                'tempo_hutang' => 14,
                'keterangan' => 'Test supplier untuk vendor payment',
            ]
        );

        // 2. Create Test Purchase Orders and Invoices
        $invoicesData = [
            [
                'supplier' => $supplier1,
                'number' => 'TEST-INV-001',
                'po_number' => 'TEST-PO-001',
                'date' => Carbon::now()->subDays(30),
                'due_date' => Carbon::now()->subDays(5), // Overdue
                'total' => 5000000,
                'items' => [
                    ['name' => 'Barang A', 'qty' => 10, 'price' => 400000],
                    ['name' => 'Barang B', 'qty' => 5, 'price' => 200000],
                ]
            ],
            [
                'supplier' => $supplier1,
                'number' => 'TEST-INV-002',
                'po_number' => 'TEST-PO-002',
                'date' => Carbon::now()->subDays(20),
                'due_date' => Carbon::now()->addDays(10),
                'total' => 3000000,
                'items' => [
                    ['name' => 'Barang C', 'qty' => 15, 'price' => 180000],
                ]
            ],
            [
                'supplier' => $supplier2,
                'number' => 'TEST-INV-003',
                'po_number' => 'TEST-PO-003',
                'date' => Carbon::now()->subDays(15),
                'due_date' => Carbon::now()->addDays(15),
                'total' => 7500000,
                'items' => [
                    ['name' => 'Barang D', 'qty' => 25, 'price' => 270000],
                ]
            ],
            [
                'supplier' => $supplier2,
                'number' => 'TEST-INV-004',
                'po_number' => 'TEST-PO-004',
                'date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->addDays(20),
                'total' => 2500000,
                'items' => [
                    ['name' => 'Barang E', 'qty' => 8, 'price' => 281250],
                ]
            ],
        ];

        foreach ($invoicesData as $invoiceData) {
            // Calculate subtotal and tax
            $subtotal = $invoiceData['total'] / 1.11; // Remove PPN 11%
            $tax = $invoiceData['total'] - $subtotal;

            // Create Purchase Order first
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $invoiceData['po_number'],
                'supplier_id' => $invoiceData['supplier']->id,
                'order_date' => $invoiceData['date']->subDays(5), // PO created 5 days before invoice
                'expected_date' => $invoiceData['due_date'],
                'status' => 'completed',
                'note' => 'Test purchase order untuk vendor payment testing',
                'total_amount' => $invoiceData['total'],
                'tempo_hutang' => 30,
                'warehouse_id' => 1,
                'is_asset' => false,
                'created_at' => $invoiceData['date']->subDays(5),
                'updated_at' => $invoiceData['date']->subDays(5),
            ]);

            // Create Purchase Order Items
            foreach ($invoiceData['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => 1, // Use any existing product
                    'quantity' => $item['qty'],
                    'unit_price' => $item['price'],
                    'discount' => 0,
                    'tax' => 0,
                    'tipe_pajak' => 'Non Pajak',
                    'currency_id' => 1,
                ]);
            }

            // Create Invoice from Purchase Order
            $invoice = Invoice::create([
                'invoice_number' => $invoiceData['number'],
                'from_model_type' => 'App\Models\PurchaseOrder', // Crucial: Use PurchaseOrder!
                'from_model_id' => $purchaseOrder->id,
                'invoice_date' => $invoiceData['date'],
                'due_date' => $invoiceData['due_date'],
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $invoiceData['total'],
                'status' => 'draft',
                'ppn_rate' => 11,
                'dpp' => $subtotal,
                'supplier_name' => $invoiceData['supplier']->name,
                'supplier_phone' => $invoiceData['supplier']->phone,
                'created_at' => $invoiceData['date'],
                'updated_at' => $invoiceData['date'],
            ]);

            // Create Invoice Items (simplified)
            foreach ($invoiceData['items'] as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => 1, // Use any existing product
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $item['qty'] * $item['price'],
                ]);
            }

            // Create Account Payable
            AccountPayable::create([
                'invoice_id' => $invoice->id,
                'supplier_id' => $invoiceData['supplier']->id, // Use supplier from invoiceData
                'total' => $invoiceData['total'],
                'paid' => 0,
                'remaining' => $invoiceData['total'],
                'status' => 'Belum Lunas',
            ]);
        }

        // 3. Create one partial payment for testing
        $firstInvoice = Invoice::where('invoice_number', 'TEST-INV-001')->first();
        if ($firstInvoice) {
            $accountPayable = AccountPayable::where('invoice_id', $firstInvoice->id)->first();
            if ($accountPayable) {
                $partialPayment = $accountPayable->total * 0.3; // 30% payment
                $accountPayable->update([
                    'paid' => $partialPayment,
                    'remaining' => $accountPayable->total - $partialPayment,
                    'status' => 'Belum Lunas'
                ]);
            }
        }

        $this->command->info('Vendor Payment Test Data created successfully!');
        $this->command->info('Test Suppliers: 2');
        $this->command->info('Test Purchase Orders: 4');
        $this->command->info('Test Invoices: 4 (linked to Purchase Orders)');
        $this->command->info('- TEST-INV-001: Rp 5,000,000 (30% paid, overdue)');
        $this->command->info('- TEST-INV-002: Rp 3,000,000 (unpaid)');
        $this->command->info('- TEST-INV-003: Rp 7,500,000 (unpaid)');
        $this->command->info('- TEST-INV-004: Rp 2,500,000 (unpaid)');
        $this->command->info('');
        $this->command->info('These invoices will now appear in Invoice Pembelian page!');
        $this->command->info('Now you can test Vendor Payment with proper invoice data!');
    }
}
