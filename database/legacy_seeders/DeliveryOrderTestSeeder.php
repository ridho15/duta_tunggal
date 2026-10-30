<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySalesOrder;
use App\Models\StockReservation;

class DeliveryOrderTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test customer
        $customer = Customer::factory()->create([
            'name' => 'Test Customer DO',
            'email' => 'testdo@example.com',
            'phone' => '081234567890',
        ]);

        // Create test product
        $product = Product::factory()->create([
            'name' => 'Test Product DO',
            'sku' => 'TEST-DO-' . time(),
            'sell_price' => 100000,
        ]);

        // Create test warehouse
        $warehouse = Warehouse::factory()->create([
            'name' => 'Test Warehouse DO',
            'kode' => 'TEST-DO',
        ]);

        // Create stock for the product
        $stock = InventoryStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        // Create sale order
        $saleOrder = SaleOrder::factory()->create([
            'so_number' => 'SO-TEST-DO-001',
            'customer_id' => $customer->id,
            'status' => 'confirmed',
            'total_amount' => 500000,
            'created_at' => now(),
        ]);

        // Create sale order item
        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
        ]);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::factory()->create([
            'do_number' => 'DO-TEST-001',
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_at' => now(),
        ]);

        // Create delivery sales order relationship
        \App\Models\DeliverySalesOrder::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $saleOrder->id,
        ]);

        // Create delivery order item
        $deliveryOrderItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Create stock reservation
        $stockReservation = StockReservation::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
        ]);

        $this->command->info('Test Delivery Order data created successfully!');
        $this->command->info('Delivery Order Number: ' . $deliveryOrder->delivery_order_number);
        $this->command->info('Sale Order Number: ' . $saleOrder->order_number);
        $this->command->info('Customer: ' . $customer->name);
        $this->command->info('Product: ' . $product->name . ' (SKU: ' . $product->sku . ')');
        $this->command->info('Warehouse: ' . $warehouse->name);
        $this->command->info('Quantity: 5 units');
        $this->command->info('Total Amount: Rp 500,000');
        $this->command->info('');
        $this->command->info('Next steps:');
        $this->command->info('1. Change DeliveryOrder status to "sent" to create journal entries');
        $this->command->info('2. Test the cascade operations we implemented');
    }
}
