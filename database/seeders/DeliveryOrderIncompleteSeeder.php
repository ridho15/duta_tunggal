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

class DeliveryOrderIncompleteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating incomplete delivery order test data...');

        // Create test customer
        $customer = Customer::factory()->create([
            'name' => 'Test Customer Incomplete DO',
            'email' => 'testincomplete@example.com',
            'phone' => '081234567891',
        ]);

        // Create test products
        $product1 = Product::factory()->create([
            'name' => 'Test Product Incomplete 1',
            'sku' => 'TEST-INCOMPLETE-1-' . time(),
            'sell_price' => 150000,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Test Product Incomplete 2',
            'sku' => 'TEST-INCOMPLETE-2-' . time(),
            'sell_price' => 200000,
        ]);

        // Create test warehouse
        $warehouse = Warehouse::factory()->create([
            'name' => 'Test Warehouse Incomplete',
            'kode' => 'TEST-INCOMPLETE',
        ]);

        // Create stocks for the products
        InventoryStock::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $warehouse->id,
            'qty_available' => 50,
            'qty_reserved' => 0,
        ]);

        InventoryStock::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $warehouse->id,
            'qty_available' => 30,
            'qty_reserved' => 0,
        ]);

        // Create multiple sale orders and delivery orders with different incomplete statuses
        $incompleteStatuses = [
            ['status' => 'draft', 'description' => 'Draft - belum diajukan'],
            ['status' => 'request_approve', 'description' => 'Request Approve - menunggu approval'],
            ['status' => 'approved', 'description' => 'Approved - sudah diapprove tapi belum dikirim'],
            ['status' => 'sent', 'description' => 'Sent - sudah dikirim tapi belum diterima'],
            ['status' => 'supplier', 'description' => 'Supplier - dalam proses supplier'],
            ['status' => 'request_close', 'description' => 'Request Close - menunggu penutupan'],
        ];

        foreach ($incompleteStatuses as $index => $statusInfo) {
            $orderNumber = $index + 1;

            // Create sale order
            $saleOrder = SaleOrder::factory()->create([
                'so_number' => 'SO-INCOMPLETE-' . $orderNumber . '-' . time(),
                'customer_id' => $customer->id,
                'status' => 'confirmed',
                'total_amount' => 350000 * $orderNumber,
                'created_at' => now(),
            ]);

            // Create sale order items (alternating products)
            $product = $index % 2 == 0 ? $product1 : $product2;
            $quantity = 5 + $index; // 5, 6, 7, 8, 9, 10

            $saleOrderItem = SaleOrderItem::factory()->create([
                'sale_order_id' => $saleOrder->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->sell_price,
            ]);

            // Create delivery order with incomplete status
            $deliveryOrder = DeliveryOrder::factory()->create([
                'do_number' => 'DO-INCOMPLETE-' . $orderNumber . '-' . time(),
                'warehouse_id' => $warehouse->id,
                'status' => $statusInfo['status'],
                'created_at' => now(),
            ]);

            // Create delivery sales order relationship
            DeliverySalesOrder::create([
                'delivery_order_id' => $deliveryOrder->id,
                'sales_order_id' => $saleOrder->id,
            ]);

            // Create delivery order item
            DeliveryOrderItem::factory()->create([
                'delivery_order_id' => $deliveryOrder->id,
                'sale_order_item_id' => $saleOrderItem->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
            ]);

            // Create stock reservation for orders that should have reservations
            if (in_array($statusInfo['status'], ['approved', 'sent', 'supplier'])) {
                StockReservation::factory()->create([
                    'sale_order_id' => $saleOrder->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => $quantity,
                ]);
            }

            $this->command->info("Created: {$deliveryOrder->do_number} - Status: {$statusInfo['status']} ({$statusInfo['description']})");
        }

        $this->command->info('');
        $this->command->info('Incomplete Delivery Order data created successfully!');
        $this->command->info('Created 6 delivery orders with different incomplete statuses:');
        $this->command->info('');
        $this->command->info('Available Delivery Orders:');

        $deliveryOrders = DeliveryOrder::where('do_number', 'like', 'DO-INCOMPLETE-%')->get();
        foreach ($deliveryOrders as $do) {
            $statusLabel = match($do->status) {
                'draft' => 'Draft',
                'request_approve' => 'Request Approve',
                'approved' => 'Approved',
                'sent' => 'Sent',
                'supplier' => 'Supplier',
                'request_close' => 'Request Close',
                default => ucfirst($do->status)
            };

            $this->command->info("- {$do->do_number}: {$statusLabel}");

            // Show related sale order
            $saleOrder = $do->salesOrders()->first();
            if ($saleOrder) {
                $this->command->info("  └─ Sale Order: {$saleOrder->so_number}");
                $this->command->info("  └─ Customer: {$saleOrder->customer->name}");
                $this->command->info("  └─ Items: {$do->deliveryOrderItem->count()} item(s)");
            }
        }

        $this->command->info('');
        $this->command->info('Next steps:');
        $this->command->info('1. Test delivery order workflow dari status draft → completed');
        $this->command->info('2. Test journal entries creation untuk status sent');
        $this->command->info('3. Test cascade operations saat delete delivery order');
    }
}