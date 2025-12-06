<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Cabang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CompleteSalesFlowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createCompleteSalesFlow();
        });
    }

    private function createCompleteSalesFlow(): void
    {
        // Get existing data
        $customers = Customer::take(5)->get();
        $products = Product::take(10)->get();
        $user = User::first();
        $warehouse = Warehouse::first();

        if ($customers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('No customers or products found. Please run CustomerSeeder and ProductSeeder first.');
            return;
        }

        $this->command->info('Creating complete sales flow: Quotation -> Approved Sale Order -> Approved Delivery Order...');

        foreach ($customers as $customer) {
            $this->createCompleteFlowForCustomer($customer, $products, $user, $warehouse);
        }

        $this->command->info('Complete sales flow created successfully!');
    }

    private function createCompleteFlowForCustomer($customer, $products, $user, $warehouse): void
    {
        // 1. Create Approved Quotation
        $quotationDate = Carbon::now()->subDays(rand(10, 30));
        $quotation = Quotation::create([
            'quotation_number' => 'QT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'date' => $quotationDate,
            'valid_until' => $quotationDate->copy()->addDays(30),
            'total_amount' => 0,
            'status' => 'approve',
            'created_by' => $user->id ?? 1,
            'approve_by' => $user->id ?? 1,
            'approve_at' => $quotationDate->copy()->addDays(1),
            'notes' => 'Approved quotation for complete sales flow',
        ]);

        // Add Quotation Items
        $itemCount = rand(2, 4);
        $selectedProducts = $products->random($itemCount);
        $totalAmount = 0;

        foreach ($selectedProducts as $product) {
            $quantity = rand(1, 10);
            $unitPrice = $product->price ?? rand(10000, 500000);
            $totalPrice = $quantity * $unitPrice;
            $totalAmount += $totalPrice;

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ]);
        }

        // Update quotation total
        $quotation->update(['total_amount' => $totalAmount]);

        // 2. Create Approved Sale Order based on Quotation
        $orderDate = $quotationDate->copy()->addDays(rand(2, 5));
        $saleOrder = SaleOrder::create([
            'customer_id' => $customer->id,
            'quotation_id' => $quotation->id,
            'so_number' => 'SO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'order_date' => $orderDate,
            'status' => 'approved',
            'delivery_date' => $orderDate->copy()->addDays(rand(3, 7)),
            'total_amount' => $totalAmount,
            'approve_by' => $user->id ?? 1,
            'approve_at' => $orderDate->copy()->addDays(1),
            'shipped_to' => $customer->address ?? 'Customer Address',
            'tipe_pengiriman' => 'Kirim Langsung',
            'created_by' => $user->id ?? 1,
            'cabang_id' => Cabang::inRandomOrder()->first()->id ?? 1,
        ]);

        // Add Sale Order Items (copy from quotation)
        foreach ($quotation->quotationItem as $quoteItem) {
            SaleOrderItem::create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $quoteItem->product_id,
                'quantity' => $quoteItem->quantity,
                'unit_price' => $quoteItem->unit_price,
                'warehouse_id' => $warehouse->id ?? 1,
            ]);
        }

        // 3. Create Approved Delivery Order
        $deliveryDate = $orderDate->copy()->addDays(rand(2, 5));
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'delivery_date' => $deliveryDate,
            'driver_id' => 1, // Assuming driver exists
            'vehicle_id' => 1, // Assuming vehicle exists
            'warehouse_id' => $warehouse->id ?? 1,
            'status' => 'approved',
            'notes' => 'Approved delivery order for sale order ' . $saleOrder->so_number,
            'created_by' => $user->id ?? 1,
            'cabang_id' => $saleOrder->cabang_id, // Use same cabang as sale order
        ]);

        // Add Delivery Order Items (from sale order items)
        foreach ($saleOrder->saleOrderItem as $soItem) {
            DeliveryOrderItem::create([
                'delivery_order_id' => $deliveryOrder->id,
                'sale_order_item_id' => $soItem->id,
                'product_id' => $soItem->product_id,
                'quantity' => $soItem->quantity,
            ]);
        }

        // Link Delivery Order to Sale Order
        DeliverySalesOrder::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $saleOrder->id,
        ]);

        $this->command->info("Created: Quotation {$quotation->quotation_number} -> SO {$saleOrder->so_number} -> DO {$deliveryOrder->do_number}");
    }
}