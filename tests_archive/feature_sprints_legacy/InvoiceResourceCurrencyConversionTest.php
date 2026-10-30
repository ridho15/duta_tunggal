<?php

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantInvoiceResourcePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'create invoice',
        'view invoice',
        'view any invoice',
        'update invoice',
        'view any customer',
        'view any product',
        'view any sales order',
        'view sale order',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'create invoice',
        'view invoice',
        'view any invoice',
        'update invoice',
        'view any customer',
        'view any product',
        'view any sales order',
        'view sale order',
    ]);
}

function seedInvoiceResourceCoas(): void
{
    foreach ([
        ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
        ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
        ['code' => '4111', 'name' => 'Penjualan Jasa', 'type' => 'Revenue'],
        ['code' => '2120.06', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
        ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
        ['code' => '5100.10', 'name' => 'HPP Barang', 'type' => 'Expense'],
        ['code' => '4100.01', 'name' => 'Diskon Penjualan', 'type' => 'Expense'],
        ['code' => '6100.02', 'name' => 'Biaya Pengiriman', 'type' => 'Expense'],
    ] as $coa) {
        ChartOfAccount::firstOrCreate(['code' => $coa['code']], $coa + ['is_active' => true]);
    }
}

beforeEach(function () {
    seedInvoiceResourceCoas();

    $this->user = User::factory()->create();
    grantInvoiceResourcePermissions($this->user);
    $this->actingAs($this->user);

    $this->cabang = Cabang::factory()->create();
    $this->customer = Customer::factory()->create([
        'cabang_id' => $this->cabang->id,
        'tempo_kredit' => 30,
    ]);
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);

    $this->idr = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $this->usd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'to_rupiah' => 15000,
    ]);

    TaxSetting::factory()->ppn()->create([
        'effective_date' => now()->subDay()->toDateString(),
        'status' => true,
    ]);

    $this->product = Product::factory()->create([
        'cost_price' => 75000,
        'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
        'sales_coa_id' => ChartOfAccount::where('code', '4000')->value('id'),
    ]);
});

test('invoice create form shows converted sale order values and stores them in idr', function () {
    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-INVOICE-USD-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->usd->id,
        'exchange_rate' => 15000,
        'created_by' => $this->user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 10.5,
        'discount' => 10,
        'tax' => TaxSetting::activeRate('PPN'),
        'tipe_pajak' => 'eksklusif',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    $component = Livewire::test(CreateInvoice::class)
        ->fillForm([
            'invoice_number' => 'INV-SO-USD-001',
            'invoice_date' => now()->format('Y-m-d'),
            'from_model_type' => SaleOrder::class,
        ])
        ->set('data.from_model_id', $saleOrder->id);

    $expectedPrice = 141750.0; // 10.5 - 10% = 9.45; 9.45 * 15000
    $expectedTotal = 283500.0; // 2 * 9.45 * 15000

    $component
        ->assertSet('data.subtotal', $expectedTotal)
        ->assertSet('data.dpp', $expectedTotal)
        ->assertSet('data.total', $expectedTotal)
        ->assertSet('data.invoiceItem.0.price', $expectedPrice)
        ->assertSet('data.invoiceItem.0.total', $expectedTotal)
        ->call('create');

    $invoice = Invoice::where('invoice_number', 'INV-SO-USD-001')->with('invoiceItem')->firstOrFail();
    $item = $invoice->invoiceItem->firstOrFail();

    expect((float) $invoice->subtotal)->toBe($expectedTotal)
        ->and((float) $invoice->dpp)->toBe($expectedTotal)
        ->and((float) $invoice->total)->toBe($expectedTotal)
        ->and((float) $item->price)->toBe($expectedPrice)
        ->and((float) $item->total)->toBe($expectedTotal);
});

test('invoice edit keeps decimal item values when saved', function () {
    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-INVOICE-EDIT-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->idr->id,
        'exchange_rate' => 1,
        'created_by' => $this->user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 1000.25,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
        return Invoice::create([
            'invoice_number' => 'INV-EDIT-DECIMAL-001',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 2000.50,
            'dpp' => 2000.50,
            'tax' => 0,
            'ppn_rate' => 0,
            'total' => 2000.50,
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $invoice->invoiceItem()->create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'price' => 1000.25,
        'subtotal' => 2000.50,
        'total' => 2000.50,
    ]);

    $invoiceItem = $invoice->invoiceItem->firstOrFail();

    InvoiceItem::whereKey($invoiceItem->id)->update([
        'price' => 1234.56,
        'total' => 2469.12,
    ]);

    Invoice::whereKey($invoice->id)->update([
        'subtotal' => 2469.12,
        'dpp' => 2469.12,
        'total' => 2469.12,
    ]);

    $savedInvoice = Invoice::where('invoice_number', 'INV-EDIT-DECIMAL-001')->with('invoiceItem')->firstOrFail();
    $savedItem = $savedInvoice->invoiceItem->firstOrFail();

    expect((float) $savedItem->price)->toBe(1234.56)
        ->and((float) $savedItem->total)->toBe(2469.12)
        ->and((float) $savedInvoice->subtotal)->toBe(2469.12)
        ->and((float) $savedInvoice->dpp)->toBe(2469.12)
        ->and((float) $savedInvoice->total)->toBe(2469.12);
});

test('invoice list page shows converted rupiah values', function () {
    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-INVOICE-VIEW-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $this->usd->id,
        'exchange_rate' => 15000,
        'created_by' => $this->user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 10.5,
        'discount' => 10,
        'tax' => TaxSetting::activeRate('PPN'),
        'tipe_pajak' => 'eksklusif',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'invoice_number' => 'INV-SO-VIEW-001',
            'invoice_date' => now()->format('Y-m-d'),
            'from_model_type' => SaleOrder::class,
        ])
        ->set('data.from_model_id', $saleOrder->id)
        ->call('create');

    $invoice = Invoice::where('invoice_number', 'INV-SO-VIEW-001')->firstOrFail();

    Livewire::test(ListInvoices::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$invoice]);
});