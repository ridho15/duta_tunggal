<?php

use App\Filament\Resources\SaleOrderResource;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrderItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantSaleOrderLivewirePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'create sales order',
        'view sales order',
        'view any sales order',
        'view any customer',
        'view any product',
        'view any warehouse',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'create sales order',
        'view sales order',
        'view any sales order',
        'view any customer',
        'view any product',
        'view any warehouse',
    ]);
}

function seedSaleOrderPostingCoas(): void
{
    ChartOfAccount::firstOrCreate(['code' => '1120'], ['name' => 'Piutang Dagang', 'type' => 'Asset']);
    ChartOfAccount::firstOrCreate(['code' => '4000'], ['name' => 'Penjualan', 'type' => 'Revenue']);
    ChartOfAccount::firstOrCreate(['code' => '4111'], ['name' => 'Penjualan Jasa', 'type' => 'Revenue']);
    ChartOfAccount::firstOrCreate(['code' => '2120.06'], ['name' => 'PPN Keluaran', 'type' => 'Liability']);
    ChartOfAccount::firstOrCreate(['code' => '1140.20'], ['name' => 'Barang Terkirim', 'type' => 'Asset']);
    ChartOfAccount::firstOrCreate(['code' => '5100.10'], ['name' => 'HPP Barang', 'type' => 'Expense']);
    ChartOfAccount::firstOrCreate(['code' => '4100.01'], ['name' => 'Diskon Penjualan', 'type' => 'Expense']);
    ChartOfAccount::firstOrCreate(['code' => '6100.02'], ['name' => 'Biaya Pengiriman', 'type' => 'Expense']);
}

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantSaleOrderLivewirePermissions($this->user);
    $this->actingAs($this->user);

    seedSaleOrderPostingCoas();

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);
    $this->product = Product::factory()->create([
        'cost_price' => 50000,
        'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
        'sales_coa_id' => ChartOfAccount::where('code', '4000')->value('id'),
    ]);

    TaxSetting::factory()->ppn()->create([
        'effective_date' => now()->subDay()->toDateString(),
        'status' => true,
    ]);
});

test('sale order item preserves explicit tax and canonical tipe pajak', function () {
    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-LIVE-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $this->user->id,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 7,
        'tipe_pajak' => 'PPN Included',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    expect($saleOrder)->not->toBeNull();
    expect($item)->not->toBeNull();
    expect((float) $item->tax)->toBe(7.0);
    expect($item->tipe_pajak)->toBe('inklusif');
});

test('sale order currency snapshot converts completed invoice totals to rupiah', function () {
    seedSaleOrderPostingCoas();

    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-LIVE-USD-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $usd->id,
        'exchange_rate' => 16000,
        'created_by' => $this->user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    expect($saleOrder)->not->toBeNull();
    expect((int) $saleOrder->currency_id)->toBe($usd->id);
    expect((float) $saleOrder->exchange_rate)->toBe(16000.0);

    $saleOrder->update(['status' => 'completed']);

    $invoice = \App\Models\Invoice::withoutGlobalScopes()
        ->where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->latest('id')
        ->first();

    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total)->toBe(1600000.0);
    expect((float) $invoice->subtotal)->toBe(1600000.0);
    expect((float) $invoice->dpp)->toBe(1600000.0);
});

test('sale order resource exposes quotation locked layout fields', function () {
    $resource = file_get_contents(base_path('app/Filament/Resources/SaleOrderResource.php'));

    expect($resource)->toContain("->label('Mata Uang')")
        ->and($resource)->toContain("TextInput::make('discount_nominal')")
        ->and($resource)->toContain("->label('Discount (Nominal)')")
        ->and($resource)->toContain("->addable(fn(Get \$get) => ! static::isReferQuotationForm(\$get))")
        ->and($resource)->toContain("->deletable(fn(Get \$get) => ! static::isReferQuotationForm(\$get))")
        ->and($resource)->toContain("->reorderable(fn(Get \$get) => ! static::isReferQuotationForm(\$get))")
        ->and($resource)->toContain("->collapsible()")
        ->and($resource)->toContain('->addAction(function (ActionsAction $action)')
        ->and($resource)->toContain("'repeater-collapse'")
        ->and($resource)->toContain('$component->generateUuid()')
        ->and($resource)->toContain('protected static function readOnlyGrayInputAttributes()')
        ->and($resource)->toContain('background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;');

    expect(strpos($resource, "Select::make('quotation_id')"))->toBeLessThan(strpos($resource, "TextInput::make('so_number')"));
    expect(strpos($resource, "Select::make('customer_id')"))->toBeLessThan(strpos($resource, "Select::make('cabang_id')"));
    expect(strpos($resource, "DatePicker::make('order_date')"))->toBeLessThan(strpos($resource, "DatePicker::make('delivery_date')"));
    expect(strpos($resource, "Radio::make('tipe_pengiriman')"))->toBeLessThan(strpos($resource, "TextInput::make('shipped_to')"));
    expect(strpos($resource, "TextInput::make('tempo_pembayaran')"))->toBeLessThan(strpos($resource, "TextInput::make('total_amount')"));

    expect(strpos($resource, "TextInput::make('quantity')"))->toBeLessThan(strpos($resource, "Select::make('currency_id')"));
    expect(strpos($resource, "Select::make('currency_id')"))->toBeLessThan(strpos($resource, "TextInput::make('unit_price')"));
    expect(strpos($resource, "TextInput::make('unit_price')"))->toBeLessThan(strpos($resource, "TextInput::make('total')"));
    expect(strpos($resource, "TextInput::make('total')"))->toBeLessThan(strpos($resource, "TextInput::make('discount')"));
    expect(strpos($resource, "TextInput::make('discount')"))->toBeLessThan(strpos($resource, "TextInput::make('discount_nominal')"));
    expect(strpos($resource, "\\Filament\\Forms\\Components\\Select::make('tipe_pajak')"))->toBeLessThan(strpos($resource, "TextInput::make('tax')"));
    expect(strpos($resource, "TextInput::make('tax')"))->toBeLessThan(strpos($resource, "TextInput::make('tax_nominal')"));
    expect(strpos($resource, "TextInput::make('tax_nominal')"))->toBeLessThan(strpos($resource, "TextInput::make('subtotal')"));
    expect($resource)->toContain('$quotationCurrencyId')
        ->and($resource)->toContain("\$set('currency_id', \$quotationCurrencyId)")
        ->and($resource)->toContain("\$set('exchange_rate', static::resolveExchangeRate(\$quotationCurrencyId))")
        ->and($resource)->toContain('CurrencyConversionResolver::convertToIdr(MoneyHelper::parseHighPrecision($quotation->total_amount ?? 0), $quotationCurrencyId, false)');

    foreach ([
        "Select::make('customer_id')",
        "Select::make('cabang_id')",
        "TextInput::make('tempo_pembayaran')",
        "Select::make('product_id')",
        "Select::make('currency_id')",
        "TextInput::make('unit_price')",
        "TextInput::make('discount')",
        "\\Filament\\Forms\\Components\\Select::make('tipe_pajak')",
    ] as $field) {
        $offset = strpos($resource, $field);
        $window = substr($resource, $offset, 1200);

        expect($window)->toContain('static::isReferQuotationForm($get)')
            ->and($window)->toContain('static::lockedInputAttributes($get)');
    }

    foreach ([
        "TextInput::make('total_amount')",
        "TextInput::make('unit')",
        "TextInput::make('total')",
        "TextInput::make('discount_nominal')",
        "TextInput::make('tax')",
        "TextInput::make('tax_nominal')",
        "TextInput::make('subtotal')",
    ] as $field) {
        $offset = strpos($resource, $field);
        $window = substr($resource, $offset, 1000);

        expect($window)->toContain('static::readOnlyGrayInputAttributes()');
    }
});

test('sale order created from quotation currency stores item currency and idr total', function () {
    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-SO-USD-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'date' => now()->toDateString(),
        'valid_until' => now()->addDays(14)->toDateString(),
        'currency_id' => $usd->id,
        'exchange_rate' => 16000,
        'total_amount' => 10,
        'status' => 'approve',
        'created_by' => $this->user->id,
    ]);

    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'discount' => 0,
        'tax' => 0,
        'tax_type' => 'none',
    ]);

    $saleOrder = SaleOrder::create([
        'so_number' => 'SO-FROM-QO-USD-001',
        'customer_id' => $quotation->customer_id,
        'quotation_id' => $quotation->id,
        'cabang_id' => $quotation->cabang_id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $quotation->currency_id,
        'exchange_rate' => $quotation->exchange_rate,
        'created_by' => $this->user->id,
    ]);

    $saleOrder->saleOrderItem()->create([
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'currency_id' => $quotation->currency_id,
        'warehouse_id' => $this->warehouse->id,
    ]);

    app(\App\Services\SalesOrderService::class)->updateTotalAmount($saleOrder);

    $saleOrder->refresh();
    $item = $saleOrder->saleOrderItem()->first();

    expect((int) $saleOrder->currency_id)->toBe($usd->id)
        ->and((float) $saleOrder->exchange_rate)->toBe(16000.0)
        ->and((int) $item->currency_id)->toBe($usd->id)
        ->and((float) $saleOrder->total_amount)->toBe(160000.0);
});

test('sale order currency preview calculates discount nominal', function () {
    $method = new ReflectionMethod(SaleOrderResource::class, 'calculateCurrencyPreview');
    $method->setAccessible(true);

    $preview = $method->invoke(null, 10, 100000, 10, 11, 'eklusif', null);

    expect($preview['total'])->toBe(1000000.0)
        ->and($preview['discount_nominal'])->toBe(100000.0)
        ->and($preview['tax_nominal'])->toBe(99000.0)
        ->and($preview['subtotal'])->toBe(999000.0);
});

test('view sale order renders expandable item infolist', function () {
    $currency = Currency::factory()->create([
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'code' => 'IDR',
        'to_rupiah' => 1,
    ]);
    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Piece',
        'abbreviation' => 'pcs',
    ]);

    $this->product->update([
        'sku' => 'SKU-SO-001',
        'name' => 'Produk Sales Test',
        'uom_id' => $uom->id,
    ]);

    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-VIEW-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Gudang Customer',
        'tempo_pembayaran' => 14,
        'currency_id' => $currency->id,
        'total_amount' => 999000,
        'created_by' => $this->user->id,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 10,
        'delivered_quantity' => 4,
        'unit_price' => 100000,
        'discount' => 10,
        'tax' => 11,
        'tipe_pajak' => 'eklusif',
        'currency_id' => $currency->id,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    SaleOrderItemWarehouseAllocation::create([
        'sale_order_item_id' => $item->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
    ]);

    InventoryStock::create([
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
        'qty_available' => 20,
        'qty_reserved' => 5,
        'qty_min' => 0,
    ]);

    $response = $this->get(SaleOrderResource::getUrl('view', ['record' => $saleOrder]));

    $response->assertOk()
        ->assertSee('Informasi Sales Order')
        ->assertSee('Ringkasan Sales Order')
        ->assertSee('Detail Item Sales Order')
        ->assertSee('Product:', false)
        ->assertSee('Qty:', false)
        ->assertSee('Subtotal:', false)
        ->assertSee('Produk Sales Test')
        ->assertSee('Product :', false)
        ->assertSee('Satuan :', false)
        ->assertSee('Qty :', false)
        ->assertSee('Qty Delivered :', false)
        ->assertSee('Sisa Qty Belum Dikirim :', false)
        ->assertSee('Mode Gudang :', false)
        ->assertSee('Alokasi Order :', false)
        ->assertSee('Stok Bebas :', false)
        ->assertSee('Mata Uang :', false)
        ->assertSee('Unit Price :', false)
        ->assertSee('Discount (Nominal) :', false)
        ->assertSee('Nominal Pajak :', false)
        ->assertSee('Subtotal :', false)
        ->assertSee('pcs')
        ->assertSee('Multi-Gudang (1 gudang)')
        ->assertSee($this->warehouse->name)
        ->assertSee('15');
});
