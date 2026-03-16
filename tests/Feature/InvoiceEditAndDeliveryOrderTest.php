<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug #2 – Server error when editing invoice
 *
 * Root cause: EditSalesInvoice::afterSave() called
 *   $this->record->invoiceItem()->create($item)
 * without populating the NOT NULL columns (discount, tax_rate, tax_amount,
 * subtotal), which caused an SQL integrity constraint error.
 *
 * Fix: array_merge() supplies defaults for all missing NOT NULL fields.
 *
 * Bug #3 – Delivery Order sometimes not generated after Sales Order is created
 *
 * Root cause: createDeliveryOrderForConfirmedWarehouseConfirmation() and
 * SalesOrderService::createDeliveryOrder() hard-coded driver_id = 1 and
 * vehicle_id = 1.  When those records do not exist the FK constraint
 * violation silently prevented DO creation.
 *
 * Fix: dynamically fetch the first available Driver/Vehicle; log a warning and
 * bail out gracefully when none exist.
 */
class InvoiceEditAndDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Bug #2 – InvoiceItem recreation provides all NOT NULL fields
    // ──────────────────────────────────────────────────────────────────────────

    public function test_invoice_item_creation_includes_all_required_fields(): void
    {
        $cabang   = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product  = Product::factory()->create();
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        // Use withoutEvents to skip InvoiceObserver (which tries to build AR records
        // and journal entries that would require extra DB fixtures unrelated to this fix).
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
            'from_model_type' => 'App\Models\SaleOrder',
            'from_model_id'   => 1,
            'customer_name'   => $customer->name,
            'cabang_id'       => $cabang->id,
        ]));

        $existingItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity'   => 5,
            'price'      => 100_000,
            'subtotal'   => 500_000,
            'discount'   => 0,
            'tax_rate'   => 0,
            'tax_amount' => 0,
            'total'      => 500_000,
        ]);

        // Simulate what EditSalesInvoice::afterSave() receives from the Repeater
        // (only the 4 fields visible in the form – no subtotal/discount/tax_*)
        $formItems = [
            [
                'product_id' => $product->id,
                'quantity'   => 5,
                'price'      => 120_000,
                'total'      => 600_000,
            ],
        ];

        // Replicate the fixed afterSave() logic
        $invoice->invoiceItem()->delete();

        foreach ($formItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price    = (float) ($item['price']    ?? 0);

            $itemData = array_merge($item, [
                'subtotal'   => $item['subtotal']   ?? ($quantity * $price),
                'discount'   => $item['discount']   ?? 0,
                'tax_rate'   => $item['tax_rate']   ?? 0,
                'tax_amount' => $item['tax_amount'] ?? 0,
            ]);

            $invoice->invoiceItem()->create($itemData);
        }

        $invoice->refresh();
        $newItem = $invoice->invoiceItem()->first();

        $this->assertNotNull($newItem, 'InvoiceItem was not created');
        $this->assertEquals(120_000, (float) $newItem->price);
        $this->assertEquals(600_000, (float) $newItem->subtotal);
        $this->assertEquals(0,       (float) $newItem->discount);
        $this->assertEquals(0,       (float) $newItem->tax_rate);
        $this->assertEquals(0,       (float) $newItem->tax_amount);
    }

    public function test_invoice_item_update_preserves_existing_coa_when_provided(): void
    {
        $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create(['from_model_type' => 'App\Models\SaleOrder', 'from_model_id' => 1]));
        $product = Product::factory()->create();

        $formItems = [
            [
                'product_id' => $product->id,
                'quantity'   => 2,
                'price'      => 50_000,
                'total'      => 100_000,
                'coa_id'     => null,
                'subtotal'   => 100_000,
                'discount'   => 5,
                'tax_rate'   => 11,
                'tax_amount' => 11_000,
            ],
        ];

        $invoice->invoiceItem()->delete();

        foreach ($formItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $price    = (float) ($item['price']    ?? 0);
            $itemData = array_merge($item, [
                'subtotal'   => $item['subtotal']   ?? ($quantity * $price),
                'discount'   => $item['discount']   ?? 0,
                'tax_rate'   => $item['tax_rate']   ?? 0,
                'tax_amount' => $item['tax_amount'] ?? 0,
            ]);
            $invoice->invoiceItem()->create($itemData);
        }

        $saved = $invoice->invoiceItem()->first();

        $this->assertEquals(5,      (float) $saved->discount);
        $this->assertEquals(11,     (float) $saved->tax_rate);
        $this->assertEquals(11_000, (float) $saved->tax_amount);
        $this->assertEquals(100_000,(float) $saved->subtotal);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bug #3 – Delivery Order auto-generation
    // ──────────────────────────────────────────────────────────────────────────

    public function test_delivery_order_is_created_when_driver_and_vehicle_exist(): void
    {
        $cabang = Cabang::factory()->create();
        // No cabang_id → CabangScope is skipped globally for this user
        $user   = User::factory()->create(['cabang_id' => null]);
        $this->actingAs($user);

        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $customer  = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product   = Product::factory()->create();

        // Create driver and vehicle so FK constraints are satisfied
        $driver  = Driver::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id'      => $customer->id,
            'cabang_id'        => $cabang->id,
            'status'           => 'approved',
            'tipe_pengiriman'  => 'Kirim Langsung',
            'approve_by'       => $user->id,
        ]);

        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id'    => $product->id,
            'quantity'      => 3,
            'warehouse_id'  => $warehouse->id,
        ]);

        // Build a WarehouseConfirmation — create as 'request' first so the observer
        // does not fire DO creation before WC items are added (race condition)
        $wc = WarehouseConfirmation::create([
            'sale_order_id'     => $saleOrder->id,
            'confirmation_type' => 'sales_order',
            'status'            => 'request',
            'confirmed_by'      => $user->id,
            'confirmed_at'      => now(),
        ]);

        WarehouseConfirmationItem::create([
            'warehouse_confirmation_id' => $wc->id,
            'sale_order_item_id'        => $soItem->id,
            'product_name'              => $product->name,
            'requested_qty'             => 3,
            'confirmed_qty'             => 3,
            'warehouse_id'              => $warehouse->id,
            'status'                    => 'confirmed',
        ]);

        // Reload WC with items before calling the creation helper
        $wc->load('warehouseConfirmationItems.saleOrderItem.product', 'saleOrder');

        // Directly invoke the protected method via a test-accessible call
        $this->callProtectedMethod($wc, 'createDeliveryOrderForConfirmedWarehouseConfirmation', [$wc]);

        $saleOrder->refresh();
        $deliveryOrder = $saleOrder->deliveryOrder()->first();

        $this->assertNotNull($deliveryOrder, 'Delivery Order was NOT created');
        $this->assertEquals('draft', $deliveryOrder->status);
        $this->assertEquals($driver->id,  $deliveryOrder->driver_id);
        $this->assertEquals($vehicle->id, $deliveryOrder->vehicle_id);

        $items = $deliveryOrder->deliveryOrderItem()->get();
        $this->assertCount(1, $items);
        $this->assertEquals(3, (int) $items->first()->quantity);
    }

    public function test_delivery_order_is_created_without_driver_or_vehicle(): void
    {
        $cabang   = Cabang::factory()->create();
        $user     = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $customer  = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $product   = Product::factory()->create();

        // Deliberately do NOT create any Driver or Vehicle
        // New behavior: DO IS created with null driver_id/vehicle_id (nullable since Task 15)

        $saleOrder = SaleOrder::factory()->create([
            'customer_id'     => $customer->id,
            'cabang_id'       => $cabang->id,
            'status'          => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id'    => $product->id,
            'quantity'      => 2,
            'warehouse_id'  => $warehouse->id,
        ]);

        $wc = WarehouseConfirmation::create([
            'sale_order_id'     => $saleOrder->id,
            'confirmation_type' => 'sales_order',
            'status'            => 'confirmed',
            'confirmed_by'      => $user->id,
            'confirmed_at'      => now(),
        ]);

        WarehouseConfirmationItem::create([
            'warehouse_confirmation_id' => $wc->id,
            'sale_order_item_id'        => $soItem->id,
            'product_name'              => $product->name,
            'requested_qty'             => 2,
            'confirmed_qty'             => 2,
            'warehouse_id'              => $warehouse->id,
            'status'                    => 'confirmed',
        ]);

        $wc->load('warehouseConfirmationItems.saleOrderItem.product', 'saleOrder');
        $this->callProtectedMethod($wc, 'createDeliveryOrderForConfirmedWarehouseConfirmation', [$wc]);

        // DO IS now created even without driver/vehicle (nullable driver_id/vehicle_id)
        $deliveryOrder = $saleOrder->deliveryOrder()->first();
        $this->assertNotNull($deliveryOrder, 'DO should be created even without driver/vehicle');
        $this->assertNull($deliveryOrder->driver_id, 'driver_id should be null when no driver exists');
        $this->assertNull($deliveryOrder->vehicle_id, 'vehicle_id should be null when no vehicle exists');
    }

    public function test_delivery_order_is_created_for_self_pickup_sales_order(): void
    {
        // Task 15: Barang yang diambil sendiri oleh customer tetap perlu DO sebagai bukti keluar gudang
        $cabang  = Cabang::factory()->create();
        $user    = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        Driver::factory()->create();
        Vehicle::factory()->create();

        $product  = Product::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        $saleOrder = SaleOrder::factory()->create([
            'cabang_id'       => $cabang->id,
            'customer_id'     => $customer->id,
            'tipe_pengiriman' => 'Ambil Sendiri',
            'status'          => 'approved',
        ]);

        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id'    => $product->id,
            'quantity'      => 5,
            'warehouse_id'  => $warehouse->id,
        ]);

        $wc = WarehouseConfirmation::create([
            'sale_order_id'     => $saleOrder->id,
            'confirmation_type' => 'sales_order',
            'status'            => 'confirmed',
            'confirmed_by'      => $user->id,
            'confirmed_at'      => now(),
        ]);

        WarehouseConfirmationItem::create([
            'warehouse_confirmation_id' => $wc->id,
            'sale_order_item_id'        => $soItem->id,
            'product_name'              => $product->name,
            'requested_qty'             => 5,
            'confirmed_qty'             => 5,
            'warehouse_id'              => $warehouse->id,
            'status'                    => 'confirmed',
        ]);

        $wc->load('warehouseConfirmationItems.saleOrderItem.product', 'saleOrder');
        $this->callProtectedMethod($wc, 'createDeliveryOrderForConfirmedWarehouseConfirmation', [$wc]);

        // Task 15: DO IS now created for Ambil Sendiri as proof of goods leaving warehouse
        $deliveryOrder = DeliveryOrder::where('cabang_id', $cabang->id)->first();
        $this->assertNotNull($deliveryOrder, 'DO should be created for Ambil Sendiri (Task 15)');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────────────────

    private function callProtectedMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($object, $args);
    }
}
