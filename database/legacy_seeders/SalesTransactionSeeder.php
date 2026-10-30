<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use App\Models\InventoryStock;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesTransactionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createSalesTransactions();
        });
    }

    private function createSalesTransactions(): void
    {
        // Get existing customers and products
        $customers = Customer::take(10)->get();
        $products = Product::take(20)->get();
        $user = User::first();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No customers or products found. Please run CustomerSeeder and ProductSeeder first.');
            return;
        }

        $this->command->info('Creating sales transactions...');

        foreach ($customers as $index => $customer) {
            // Create 2-5 sale orders per customer
            $orderCount = rand(2, 5);
            
            for ($i = 0; $i < $orderCount; $i++) {
                $this->createSalesTransaction($customer, $products, $user, $index, $i);
            }
        }

        $this->command->info('Sales transactions created successfully!');
    }

    private function createSalesTransaction($customer, $products, $user, $customerIndex, $orderIndex): void
    {
        // Create Sale Order
        $orderDate = Carbon::now()->subDays(rand(30, 180));
        $deliveryDate = $orderDate->copy()->addDays(rand(1, 14));
        
        $saleOrder = SaleOrder::create([
            'customer_id' => $customer->id,
            'so_number' => 'SO-' . date('Ymd') . '-' . str_pad(($customerIndex * 10 + $orderIndex + 1), 4, '0', STR_PAD_LEFT),
            'order_date' => $orderDate,
            'status' => 'confirmed', // Set to confirmed so it can be invoiced
            'delivery_date' => $deliveryDate,
            'total_amount' => 0,
            'created_by' => $user->id ?? 1,
            'created_at' => $orderDate,
            'updated_at' => $orderDate,
        ]);

        // Add Sale Order Items
        $itemCount = rand(2, 5);
        $totalAmount = 0;
        $selectedProducts = $products->random($itemCount);

        foreach ($selectedProducts as $product) {
            $quantity = rand(1, 10);
            $unitPrice = $product->price ?? rand(10000, 500000);
            $subtotal = $quantity * $unitPrice;
            $totalAmount += $subtotal;

            SaleOrderItem::create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'created_at' => $orderDate,
                'updated_at' => $orderDate,
            ]);

            // Update inventory stock (simulate delivery)
            $this->updateInventoryStock($product->id, -$quantity, $orderDate);
        }

        // Update sale order total
        $saleOrder->update(['total_amount' => $totalAmount]);

        // Create Invoice (simulate invoicing after delivery)
        $invoiceDate = $deliveryDate->copy()->addDays(rand(0, 7));
        $dueDate = $invoiceDate->copy()->addDays(30); // 30 days payment terms

        $invoice = Invoice::create([
            'from_model_type' => 'App\Models\SaleOrder',
            'from_model_id' => $saleOrder->id,
            'invoice_number' => 'INV-' . $invoiceDate->format('Ymd') . '-' . str_pad($saleOrder->id, 4, '0', STR_PAD_LEFT),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'total' => $totalAmount,
            'status' => rand(0, 10) > 7 ? 'paid' : 'unpaid', // 30% chance of being paid
            'created_at' => $invoiceDate,
            'updated_at' => $invoiceDate,
        ]);

        // Create Invoice Items
        foreach ($saleOrder->saleOrderItem as $orderItem) {
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

        // Create Account Receivable
        $paidAmount = 0;
        $remainingAmount = $totalAmount;

        // Simulate partial payments for some invoices
        if ($invoice->status === 'paid') {
            $paidAmount = $totalAmount;
            $remainingAmount = 0;
        } elseif (rand(0, 10) > 6) { // 40% chance of partial payment
            $paidAmount = rand(1, 8) * ($totalAmount / 10); // 10%-80% payment
            $remainingAmount = $totalAmount - $paidAmount;
        }

        $accountReceivable = AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
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
                'account_receivable_id' => $accountReceivable->id,
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'amount' => $remainingAmount,
                'due_date' => $dueDate,
                'days_overdue' => max(0, $daysOverdue),
                'ageing_bucket' => $this->getAgeingBucket($daysOverdue),
                'created_at' => $invoiceDate,
                'updated_at' => Carbon::now(),
            ]);
        }

        $this->command->info("Created sales transaction: {$saleOrder->so_number} -> {$invoice->invoice_number} (Customer: {$customer->name})");
    }

    private function updateInventoryStock($productId, $quantity, $date): void
    {
        $stock = InventoryStock::where('product_id', $productId)->first();
        
        if ($stock) {
            $stock->update([
                'quantity' => max(0, $stock->quantity + $quantity),
                'updated_at' => $date,
            ]);
        } else {
            // Create initial stock if doesn't exist
            InventoryStock::create([
                'product_id' => $productId,
                'quantity' => max(0, $quantity + rand(100, 1000)), // Add some initial stock
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
