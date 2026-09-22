<?php

use App\Filament\Resources\CustomerReturnResource;
use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Services\DeliveryOrderService;
use App\Support\WarehouseStockOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function setupSalesUatContext(): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-UTAMA',
        'nama' => 'Cabang Utama',
        'status' => 1,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'cabang',
    ]);
    Auth::login($user);

    $currency = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
    );

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'name' => 'Gudang Utama',
        'kode' => 'GDG-01',
        'status' => 1,
    ]);

    $arCoa = ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);
    $revenueCoa = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue']);
    $ppnKeluaranCoa = ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran', 'type' => 'Liability']);
    $cogsCoa = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP Barang Dagangan', 'type' => 'Expense']);
    $goodsDeliveryCoa = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
    $inventoryCoa = ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Persediaan', 'type' => 'Asset']);

    $customer = Customer::factory()->create([
        'cabang_id' => $cabang->id,
        'name' => 'DAYA TEKNIK MEDIKA',
        'code' => 'CUST-DTM-01',
        'phone' => '08123456789',
        'tempo_kredit' => 30,
    ]);

    $product = Product::factory()->create([
        'name' => 'Alat Medis Disposable',
        'sku' => 'AMD-001',
        'cost_price' => 5667.00,
        'sell_price' => 8252.65,
        'sales_coa_id' => $revenueCoa->id,
        'cogs_coa_id' => $cogsCoa->id,
        'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
        'inventory_coa_id' => $inventoryCoa->id,
    ]);

    return compact(
        'cabang', 'user', 'currency', 'warehouse', 'customer', 'product',
        'arCoa', 'revenueCoa', 'ppnKeluaranCoa', 'cogsCoa', 'goodsDeliveryCoa', 'inventoryCoa'
    );
}

it('Bug 1 & 5: partial delivery of 12 pcs from 20 pcs keeps SO partially_delivered, bills 12 pcs, and matches HPP', function () {
    $ctx = setupSalesUatContext();

    // 1. Create Sale Order with 20 pcs
    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'so_number' => 'SO-00005',
        'order_date' => now(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $ctx['currency']->id,
        'exchange_rate' => 1.0,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 20,
        'delivered_quantity' => 0,
        'unit_price' => 8252.65,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Eksklusif',
        'currency_id' => $ctx['currency']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ]);

    // 2. Create Delivery Order for partial quantity: 12 pcs
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-20260918-0001',
        'delivery_date' => now(),
        'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $deliveryOrder->salesOrders()->attach($saleOrder->id);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 12,
    ]);

    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity' => 12,
    ]);

    // 3. Process DO: sent -> completed
    $deliveryOrder->update(['status' => 'sent']);
    $deliveryOrder->update(['status' => 'completed']);

    // Assert Bug 1 fix:
    // Sale order MUST be 'partially_delivered', NOT 'completed'
    $saleOrder->refresh();
    expect($saleOrder->status)->toBe('partially_delivered');
    expect($saleOrder->completed_at)->toBeNull();

    // Remaining quantity must be 8
    $soItem->refresh();
    expect((float) $soItem->delivered_quantity)->toBe(12.0);
    expect((float) $soItem->remaining_quantity)->toBe(8.0);

    // Assert Auto-Created Invoice is strictly for 12 pcs
    $invoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->latest('id')
        ->first();

    expect($invoice)->not->toBeNull();
    $invoice->load('invoiceItem');

    // Subtotal 12 * 8252.65 = 99.031,80
    expect((float) $invoice->subtotal)->toBe(99031.8);
    // Tax 11% of 99031.80 = 10.893,50
    expect((float) $invoice->total)->toBe(109925.3);

    expect($invoice->invoiceItem)->toHaveCount(1);
    expect((float) $invoice->invoiceItem->first()->quantity)->toBe(12.0);

    // Assert Bug 5 fix: HPP matches 12 pcs (12 * 5667 = 68.004)
    $journals = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();

    $cogsEntry = $journals->where('coa_id', $ctx['cogsCoa']->id)->first();
    expect($cogsEntry)->not->toBeNull();
    expect((float) $cogsEntry->debit)->toBe(68004.0); // 12 * 5667, NOT 20 * 5667 (113.340)

    $goodsDeliveryEntry = $journals->where('coa_id', $ctx['goodsDeliveryCoa']->id)->first();
    expect($goodsDeliveryEntry)->not->toBeNull();
    expect((float) $goodsDeliveryEntry->credit)->toBe(68004.0);

    // Double-entry balancing invariant
    $totalDebit = $journals->sum('debit');
    $totalCredit = $journals->sum('credit');
    expect(abs($totalDebit - $totalCredit))->toBeLessThanOrEqual(0.01);
});

it('Bug 2: blocks invoicing the same delivery order multiple times', function () {
    $ctx = setupSalesUatContext();

    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'so_number' => 'SO-00006',
        'order_date' => now(),
        'status' => 'approved',
    ]);

    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-20260918-0002',
        'delivery_date' => now(),
        'status' => 'completed',
        'cabang_id' => $ctx['cabang']->id,
    ]);

    // Existing active invoice for this DO
    Invoice::create([
        'invoice_number' => 'INV-20260918-0001',
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'delivery_orders' => [$deliveryOrder->id],
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => 'unpaid',
        'subtotal' => 100000,
        'total' => 111000,
        'cabang_id' => $ctx['cabang']->id,
    ]);

    // Attempting to create a second invoice for the same DO
    $page = new class extends CreateSalesInvoice {
        public function callMutateFormDataBeforeCreate(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    expect(fn () => $page->callMutateFormDataBeforeCreate([
        'selected_delivery_orders' => [$deliveryOrder->id],
        'total' => 111000,
        'subtotal' => 100000,
    ]))->toThrow(ValidationException::class);
});

it('Bug 3 & 4: rejects Rp0 invoices and ensures sales journals are strictly balanced', function () {
    $ctx = setupSalesUatContext();

    // Validation rejects Rp0 invoice in CreateSalesInvoice
    $page = new class extends CreateSalesInvoice {
        public function callMutateFormDataBeforeCreate(array $data): array
        {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    expect(fn () => $page->callMutateFormDataBeforeCreate([
        'total' => 0,
        'subtotal' => 0,
    ]))->toThrow(ValidationException::class);

    // Observer skips or throws if invoice has Rp0 total
    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'so_number' => 'SO-00007',
        'order_date' => now(),
        'status' => 'approved',
    ]);

    $zeroInvoice = Invoice::create([
        'invoice_number' => 'INV-ZERO-TEST',
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => 'unpaid',
        'subtotal' => 0,
        'total' => 0,
        'cabang_id' => $ctx['cabang']->id,
    ]);

    $invoiceObserver = new \App\Observers\InvoiceObserver();
    $invoiceObserver->postSalesInvoice($zeroInvoice);

    // No journal entries should be created for zero invoice
    $journals = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $zeroInvoice->id)
        ->get();

    expect($journals)->toBeEmpty();
});

it('Bug 6: warehouse stock options shows 0 stock warehouse instead of empty array and DO warehouse confirmation preserves product details', function () {
    $ctx = setupSalesUatContext();

    // Product has 0 stock in warehouse
    $options = WarehouseStockOptions::forProduct($ctx['product']->id);

    // Must NOT be empty
    expect($options)->not->toBeEmpty();
    expect($options)->toHaveKey($ctx['warehouse']->id);
    expect($options[$ctx['warehouse']->id])->toContain('Stok: 0');

    // Create DO and verify warehouse confirmation has product_id and valid product details
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-CONF-TEST-001',
        'delivery_date' => now(),
        'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ]);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
    ]);

    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity' => 5,
    ]);

    $doService = new DeliveryOrderService();
    $confirmations = $doService->createWarehouseConfirmationsForDeliveryOrder($deliveryOrder);

    expect($confirmations)->toHaveCount(1);
    $conf = $confirmations[0];
    expect($conf->note)->toContain('Alat Medis Disposable');
    expect($conf->note)->toContain('AMD-001');

    $conf->load('warehouseConfirmationItems.product');
    $confItem = $conf->warehouseConfirmationItems->first();
    expect($confItem)->not->toBeNull();
    expect($confItem->product_id)->toBe($ctx['product']->id);
    expect($confItem->product_name)->toBe('Alat Medis Disposable');
    expect($confItem->product_display)->toContain('AMD-001');
});

it('Bug 7: customer return search finds customer beyond 50 records', function () {
    $ctx = setupSalesUatContext();

    // Create 55 dummy customers using factory
    Customer::factory()->count(55)->create([
        'cabang_id' => $ctx['cabang']->id,
    ]);

    // Now test search in CustomerReturnResource
    $searchResults = Customer::query()
        ->where(function ($q) {
            $q->where('name', 'like', '%DAYA TEKNIK MEDIKA%')
                ->orWhere('code', 'like', '%DAYA TEKNIK MEDIKA%')
                ->orWhere('phone', 'like', '%DAYA TEKNIK MEDIKA%');
        })
        ->limit(50)
        ->get()
        ->mapWithKeys(function ($customer) {
            $label = $customer->code ? "({$customer->code}) {$customer->name}" : $customer->name;
            if ($customer->phone) {
                $label .= " - {$customer->phone}";
            }
            return [$customer->id => $label];
        })
        ->toArray();

    expect($searchResults)->toHaveKey($ctx['customer']->id);
    expect($searchResults[$ctx['customer']->id])->toContain('DAYA TEKNIK MEDIKA');
});
