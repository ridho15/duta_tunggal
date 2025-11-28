<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseTransactionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createPurchaseTransactions();
        });
    }

    private function createPurchaseTransactions(): void
    {
        // Get existing suppliers and products
        $suppliers = Supplier::take(8)->get();
        $products = Product::take(15)->get();
        $user = User::first();

        if ($suppliers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No suppliers or products found. Please run SupplierSeeder and ProductSeeder first.');
            return;
        }

        $this->command->info('Creating purchase transactions...');

        foreach ($suppliers as $index => $supplier) {
            // Create 2-4 purchase orders per supplier
            $orderCount = rand(2, 4);
            
            for ($i = 0; $i < $orderCount; $i++) {
                $this->createPurchaseTransaction($supplier, $products, $user, $index, $i);
            }
        }

        $this->command->info('Purchase transactions created successfully!');
    }

    private function createPurchaseTransaction($supplier, $products, $user, $supplierIndex, $orderIndex): void
    {
        // Create Purchase Order
        $orderDate = Carbon::now()->subDays(rand(20, 120));
        $expectedDelivery = $orderDate->copy()->addDays(rand(7, 21));
        
        $purchaseOrder = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-' . date('Ymd') . '-' . str_pad(($supplierIndex * 10 + $orderIndex + 1), 4, '0', STR_PAD_LEFT),
            'order_date' => $orderDate,
            'status' => 'received', // Set to received so it can be invoiced
            'expected_delivery_date' => $expectedDelivery,
            'total_amount' => 0,
            'created_by' => $user->id ?? 1,
            'created_at' => $orderDate,
            'updated_at' => $orderDate,
        ]);

        // Add Purchase Order Items
        $itemCount = rand(2, 6);
        $totalAmount = 0;
        $selectedProducts = $products->random($itemCount);

        foreach ($selectedProducts as $product) {
            $quantity = rand(10, 100);
            $unitPrice = ($product->price ?? rand(5000, 300000)) * 0.7; // Purchase price typically lower
            $subtotal = $quantity * $unitPrice;
            $totalAmount += $subtotal;

            PurchaseOrderItem::create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            // Update inventory stock (simulate receiving goods)
            $this->updateInventoryStock($product->id, $quantity, $expectedDelivery);
        }

        // Update purchase order total
        $purchaseOrder->update(['total_amount' => $totalAmount]);

        // Create Invoice (supplier invoice received after goods receipt)
        $invoiceDate = $expectedDelivery->copy()->addDays(rand(0, 5));
        $dueDate = $invoiceDate->copy()->addDays(rand(15, 45)); // 15-45 days payment terms

        $invoice = Invoice::create([
            'from_model_type' => 'App\Models\PurchaseOrder',
            'from_model_id' => $purchaseOrder->id,
            'invoice_number' => 'PINV-' . $invoiceDate->format('Ymd') . '-' . str_pad($purchaseOrder->id, 4, '0', STR_PAD_LEFT),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'total' => $totalAmount,
            'status' => rand(0, 10) > 6 ? 'paid' : 'unpaid', // 40% chance of being paid
            'created_at' => $invoiceDate,
            'updated_at' => $invoiceDate,
        ]);

        // Create Invoice Items
        foreach ($purchaseOrder->purchaseOrderItem as $orderItem) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $orderItem->product_id,
                'quantity' => $orderItem->quantity,
                'unit_price' => $orderItem->unit_price,
                'subtotal' => $orderItem->subtotal,
                'created_at' => $invoiceDate,
                'updated_at' => $invoiceDate,
            ]);
        }

        // Create Account Payable
        $paidAmount = 0;
        $remainingAmount = $totalAmount;

        // Simulate partial payments for some invoices
        if ($invoice->status === 'paid') {
            $paidAmount = $totalAmount;
            $remainingAmount = 0;
        } elseif (rand(0, 10) > 7) { // 30% chance of partial payment
            $paidAmount = rand(2, 7) * ($totalAmount / 10); // 20%-70% payment
            $remainingAmount = $totalAmount - $paidAmount;
        }

        $accountPayable = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'total' => $totalAmount,
            'paid' => $paidAmount,
            'remaining' => $remainingAmount,
            'status' => $remainingAmount > 0 ? 'Belum Lunas' : 'Lunas',
            'created_at' => $invoiceDate,
            'updated_at' => $invoiceDate,
        ]);

        // Create Ageing Schedule for unpaid invoices
        if ($remainingAmount > 0) {
            $daysOverdue = Carbon::now()->diffInDays($dueDate, false);
            
            AgeingSchedule::create([
                'account_payable_id' => $accountPayable->id,
                'invoice_id' => $invoice->id,
                'supplier_id' => $supplier->id,
                'amount' => $remainingAmount,
                'due_date' => $dueDate,
                'days_overdue' => max(0, $daysOverdue),
                'ageing_bucket' => $this->getAgeingBucket($daysOverdue),
                'created_at' => $invoiceDate,
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info("Created purchase transaction: {$purchaseOrder->po_number} -> {$invoice->invoice_number} (Supplier: {$supplier->name})");
    }

    private function updateInventoryStock($productId, $quantity, $date): void
    {
        $stock = InventoryStock::where('product_id', $productId)->first();
        
        if ($stock) {
            $stock->update([
                'quantity' => $stock->quantity + $quantity,
                'updated_at' => $date,
            ]);
        } else {
            // Create initial stock
            InventoryStock::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function getAgeingBucket($daysOverdue): string
    {
        if ($daysOverdue <= 0) return 'Current';
        if ($daysOverdue <= 30) return '1-30 days';
        if ($daysOverdue <= 60) return '31-60 days';
        if ($daysOverdue <= 90) return '61-90 days';
        return '90+ days';
    }
}
