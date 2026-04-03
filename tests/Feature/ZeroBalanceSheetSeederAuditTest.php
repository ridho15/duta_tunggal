<?php

use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\DeliverySchedule;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationWarehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
});

test('zero balance sheet seeder clears transactional tables and preserves master data', function () {
    $this->seed(\Database\Seeders\ZeroBalanceSheetSeeder::class);

    $branch = Cabang::firstOrFail();
    $user = User::firstOrFail();
    $warehouse = Warehouse::firstOrFail();
    $rak = Rak::where('warehouse_id', $warehouse->id)->first() ?? Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $product = Product::firstOrFail();
    $customer = Customer::firstOrFail();
    $supplier = Supplier::firstOrFail();

    Model::withoutEvents(function () use ($branch, $user, $warehouse, $rak, $product, $customer, $supplier) {
        $invoice = Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => 1,
        ]);

        $customerReturn = CustomerReturn::create([
            'return_number' => 'CR-AUDIT-' . now()->format('His') . random_int(1000, 9999),
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Audit seeder cleanup',
            'status' => CustomerReturn::STATUS_PENDING,
            'notes' => 'Created for zero balance seeder audit',
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'problem_description' => 'Audit item',
            'decision' => 'reject',
        ]);

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'status' => 'approved',
        ]);

        $saleOrderItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'quantity' => 4,
        ]);

        SaleOrderItemWarehouseAllocation::create([
            'sale_order_item_id' => $saleOrderItem->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 4,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $branch->id,
            'status' => 'draft',
        ]);

        $deliveryOrderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'reason' => 'Audit delivery item',
            'status' => 'draft',
        ]);

        DeliveryOrderItemWarehouseSource::create([
            'delivery_order_item_id' => $deliveryOrderItem->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'quantity' => 2,
        ]);

        $deliverySchedule = DeliverySchedule::create([
            'schedule_number' => 'DS-AUDIT-' . now()->format('His') . random_int(1000, 9999),
            'scheduled_date' => now()->addDay(),
            'driver_id' => null,
            'vehicle_id' => null,
            'status' => 'pending',
            'notes' => 'Audit delivery schedule',
            'created_by' => $user->id,
            'cabang_id' => $branch->id,
        ]);

        $suratJalan = SuratJalan::factory()->create([
            'created_by' => $user->id,
            'signed_by' => $user->id,
        ]);

        DB::table('delivery_schedule_delivery_orders')->insert([
            'delivery_schedule_id' => $deliverySchedule->id,
            'delivery_order_id' => $deliveryOrder->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('delivery_schedule_surat_jalans')->insert([
            'delivery_schedule_id' => $deliverySchedule->id,
            'surat_jalan_id' => $suratJalan->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PaymentRequest::factory()->create([
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'requested_by' => $user->id,
            'approved_by' => null,
        ]);

        $stockAdjustment = StockAdjustment::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'adjustment_type' => 'increase',
            'status' => 'approved',
        ]);

        StockAdjustmentItem::factory()->create([
            'stock_adjustment_id' => $stockAdjustment->id,
            'product_id' => $product->id,
            'rak_id' => $rak->id,
        ]);

        $warehouseConfirmation = WarehouseConfirmation::factory()->create([
            'confirmable_type' => SaleOrder::class,
            'confirmable_id' => $saleOrder->id,
            'confirmed_by' => $user->id,
        ]);

        WarehouseConfirmationWarehouse::create([
            'warehouse_confirmation_id' => $warehouseConfirmation->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'request',
            'confirmed_by' => null,
            'confirmed_at' => null,
            'notes' => 'Audit warehouse confirmation row',
        ]);

        DB::table('accounting_periods')->insert([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
            'cabang_id' => $branch->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->seed(\Database\Seeders\ZeroBalanceSheetSeeder::class);

    expect(PaymentRequest::count())->toBe(0)
        ->and(StockAdjustment::count())->toBe(0)
        ->and(StockAdjustmentItem::count())->toBe(0)
        ->and(CustomerReturn::count())->toBe(0)
        ->and(CustomerReturnItem::count())->toBe(0)
        ->and(DeliverySchedule::count())->toBe(0)
        ->and(DeliveryOrder::count())->toBe(0)
        ->and(DeliveryOrderItem::count())->toBe(0)
        ->and(WarehouseConfirmation::count())->toBe(0)
        ->and(WarehouseConfirmationWarehouse::count())->toBe(0)
        ->and(InventoryStock::count())->toBe(0)
        ->and(DB::table('delivery_schedule_delivery_orders')->count())->toBe(0)
        ->and(DB::table('delivery_schedule_surat_jalans')->count())->toBe(0)
        ->and(DB::table('sale_order_item_warehouse_allocations')->count())->toBe(0)
        ->and(DB::table('delivery_order_item_warehouse_sources')->count())->toBe(0)
        ->and(DB::table('accounting_periods')->count())->toBe(0);

    expect(Product::count())->toBeGreaterThan(0)
        ->and(Customer::count())->toBeGreaterThan(0)
        ->and(Supplier::count())->toBeGreaterThan(0)
        ->and(ChartOfAccount::count())->toBeGreaterThan(0);
});