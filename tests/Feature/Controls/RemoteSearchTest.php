<?php

/**
 * T7.2 — Pencarian dropdown sisi-server (usulan 17): customer/produk/dokumen ke-500 dapat ditemukan; hasil ≤ 50 dengan petunjuk;
 * label nilai terpilih selalu pulih; NIK/NPWP dicari tetapi tidak ditampilkan; form Invoice tidak memuat seluruh tabel; pemindai pola.
 */

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Customer;
use App\Models\Product;
use App\Services\RemoteSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function rsCustomers(int $count, array $ctx, string $prefix = 'Pelanggan'): void
{
    for ($i = 1; $i <= $count; $i++) {
        Customer::factory()->create([
            'name' => sprintf('%s %03d', $prefix, $i), 'code' => sprintf('C-%04d', $i), 'perusahaan' => $i === 500 ? 'PT Anggrek Khusus' : '',
            'nik_npwp' => $i === 500 ? '3201010101010001' : '', 'cabang_id' => $ctx['cabang']->id,
        ]);
    }
}

it('pelanggan ke-500 ditemukan lewat nama, kode, perusahaan, dan NIK/NPWP; NIK tidak muncul pada label', function () {
    $ctx = stkContext();
    rsCustomers(520, $ctx);
    $service = new RemoteSearch;

    foreach (['Pelanggan 500', 'C-0500', 'Anggrek', '3201010101010001'] as $term) {
        $found = $service->options('customers', $term);
        $labels = array_values($found);
        expect($labels)->toContain('(C-0500) Pelanggan 500');
    }
    expect(implode(' ', $service->options('customers', 'Anggrek')))->not->toContain('3201010101010001');

    // beberapa kata: setiap kata harus cocok (AND)
    expect($service->options('customers', 'Pelanggan Anggrek'))->toHaveCount(1)->and($service->options('customers', 'Pelanggan 999999'))->toBe([]);
});

it('hasil dibatasi 50; endpoint melaporkan terpotong + petunjuk; kueri sempit tidak terpotong', function () {
    $ctx = stkContext();
    rsCustomers(120, $ctx);
    $service = new RemoteSearch;

    expect($service->options('customers'))->toHaveCount(50)->and($service->options('customers', 'Pelanggan'))->toHaveCount(50);

    $wide = $service->search('customers', 'Pelanggan');
    expect($wide['results'])->toHaveCount(50)->and($wide['truncated'])->toBeTrue()->and($wide['hint'])->toBe(RemoteSearch::HINT)->and($wide['limit'])->toBe(50);

    $narrow = $service->search('customers', 'Pelanggan 07');
    expect($narrow['truncated'])->toBeFalse()->and($narrow['hint'])->toBeNull()->and(count($narrow['results']))->toBeGreaterThan(0)->toBeLessThan(50);
});

it('karakter wildcard LIKE (%, _) dicari apa adanya, tidak menjadi "cocok semua"', function () {
    $ctx = stkContext();
    Customer::factory()->create(['name' => 'Toko Diskon 50% Off', 'code' => 'C-PCT', 'cabang_id' => $ctx['cabang']->id]);
    Customer::factory()->create(['name' => 'Toko Biasa', 'code' => 'C-BIASA', 'cabang_id' => $ctx['cabang']->id]);
    $service = new RemoteSearch;

    expect(array_values($service->options('customers', '%')))->toBe(['(C-PCT) Toko Diskon 50% Off'])
        ->and($service->options('customers', '_'))->toBe([]);
});

it('label nilai terpilih pulih walau di luar 50 teratas; customer yang sudah digabung tidak ditawarkan tetapi labelnya tetap ada', function () {
    $ctx = stkContext();
    rsCustomers(80, $ctx);
    $service = new RemoteSearch;
    $last = Customer::where('code', 'C-0080')->first();
    $merged = Customer::factory()->create(['name' => 'Pelanggan Lama', 'code' => 'C-OLD', 'cabang_id' => $ctx['cabang']->id, 'merged_into' => $last->id]);

    expect($service->options('customers'))->not->toHaveKey($last->id)                         // di luar 50 teratas
        ->and($service->label('customers', $last->id))->toBe('(C-0080) Pelanggan 080')
        ->and($service->options('customers', 'Pelanggan Lama'))->toBe([])                     // digabung: tidak ditawarkan
        ->and($service->label('customers', $merged->id))->toBe('(C-OLD) Pelanggan Lama')      // tetapi label pulih
        ->and($service->label('customers', null))->toBeNull()->and($service->label('customers', 999999))->toBeNull();
});

it('produk dicari lewat SKU/nama; dokumen (SO, Quotation, Invoice penjualan, DO) dicari lewat nomor atau nama customer dengan label informatif', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 3);
    [$invoice] = ctlCreditInvoice($ctx);
    $deliveryOrder = stkDeliveryOrder($ctx, $so, $soItem, 3);
    $quotation = ctlQuotation($ctx, 500000);
    $product = Product::factory()->create(['sku' => 'SKU-KHUSUS-77', 'name' => 'Alat Ukur Presisi']);
    $service = new RemoteSearch;

    expect(array_values($service->options('products', 'KHUSUS-77')))->toBe(['(SKU-KHUSUS-77) Alat Ukur Presisi'])
        ->and(array_values($service->options('products', 'presisi')))->toBe(['(SKU-KHUSUS-77) Alat Ukur Presisi']);

    $soLabel = $service->options('sale-orders', $so->so_number)[$so->id] ?? null;
    expect($soLabel)->toContain($so->so_number)->toContain($ctx['customer']->name);
    expect($service->options('sale-orders', $ctx['customer']->name))->toHaveKey($so->id);
    expect($service->options('sale-orders', $ctx['customer']->name, ['customer_id' => 999999]))->toBe([]);

    expect($service->options('quotations', $quotation->quotation_number))->toHaveKey($quotation->id)
        ->and($service->options('invoices', $invoice->invoice_number)[$invoice->id])->toContain($invoice->invoice_number)->toContain('Rp 1.332.000,00')
        ->and($service->options('delivery-orders', $ctx['customer']->name))->toHaveKey($deliveryOrder->id);

    // invoice pembelian tidak ikut (hanya invoice penjualan)
    $purchaseInvoice = \App\Models\Invoice::withoutEvents(fn () => \App\Models\Invoice::factory()->create([
        'from_model_type' => \App\Models\PurchaseOrder::class, 'from_model_id' => 1, 'invoice_number' => 'INV-BELI-XYZ', 'customer_name' => 'Pemasok X',
    ]));
    expect($service->options('invoices', 'INV-BELI-XYZ'))->not->toHaveKey($purchaseInvoice->id)->and($service->options('invoices', 'INV-BELI-XYZ'))->toBe([]);

    expect(fn () => $service->options('tidak-ada'))->toThrow(InvalidArgumentException::class);
});

it('endpoint /api/v1/search/{type}: hasil JSON untuk yang berizin; 403 tanpa izin; 404 jenis tak dikenal; 401 tanpa login', function () {
    $ctx = stkContext();
    rsCustomers(60, $ctx);
    $allowed = ctlUser($ctx, 'Sales', ['view any customer']);
    $denied = ctlUser($ctx, 'Gudang', ['view any invoice']);

    $ok = test()->actingAs($allowed)->getJson('/api/v1/search/customers?q=Pelanggan');
    $ok->assertOk()->assertJsonCount(50, 'results')->assertJsonPath('truncated', true)->assertJsonPath('limit', 50);
    test()->actingAs($allowed)->getJson('/api/v1/search/customers?q=Pelanggan%20005')->assertOk()->assertJsonPath('results.0.label', '(C-0005) Pelanggan 005');

    test()->actingAs($denied)->getJson('/api/v1/search/customers?q=Pelanggan')->assertForbidden();
    test()->actingAs($allowed)->getJson('/api/v1/search/tidak-ada')->assertNotFound();
    \Illuminate\Support\Facades\Auth::guard('web')->logout();
    test()->getJson('/api/v1/search/customers?q=Pelanggan')->assertUnauthorized();
});

it('form Invoice tidak memuat seluruh tabel customer/produk (kueri berbatas) dan customer ke-500 dapat dipilih lewat pencarian', function () {
    $ctx = stkContext();
    rsCustomers(520, $ctx);
    $user = ctlUser($ctx, 'Finance Manager', ['view any invoice', 'create invoice', 'view invoice']);

    $unbounded = [];
    DB::listen(function ($query) use (&$unbounded) {
        if (preg_match('/from `(customers|products)`/i', $query->sql) && ! preg_match('/limit|`id` = \?|`id` in|count\(|exists/i', $query->sql)) {
            $unbounded[] = $query->sql;
        }
    });

    $component = Livewire::actingAs($user)->test(CreateSalesInvoice::class)->assertSuccessful();
    expect($unbounded)->toBe([]);

    $field = $component->instance()->getForm('form')->getFlatFields()['selected_customer'];
    $results = $field->getSearchResults('Pelanggan 500');
    expect(array_values($results))->toContain('(C-0500) Pelanggan 500')->and(count($field->getSearchResults('Pelanggan')))->toBe(50);
    $selected = Customer::where('code', 'C-0500')->first();
    expect(app(RemoteSearch::class)->label('customers', $selected->id))->toBe('(C-0500) Pelanggan 500');
});

it('pemindai: resource penjualan tidak memakai pola "Model::all() + preload" dan tidak menambah limit(50) statis baru', function () {
    $files = ['SalesInvoiceResource', 'SaleOrderResource', 'QuotationResource', 'DeliveryOrderResource', 'CustomerReceiptResource', 'CustomerReturnResource'];
    // utang lama yang diketahui: pilihan Cabang/Supplier/Gudang (bukan customer/produk/dokumen) — hanya boleh menyusut
    $allowedLimit50 = ['SaleOrderResource' => 4, 'QuotationResource' => 3, 'DeliveryOrderResource' => 1, 'CustomerReturnResource' => 1];

    foreach ($files as $name) {
        $source = file_get_contents(app_path("Filament/Resources/{$name}.php"));
        expect(preg_match('/(Customer|Product|SaleOrder|Quotation|Invoice)::all\(\)->mapWithKeys/', $source))->toBe(0, "{$name}: Model::all()->mapWithKeys");
        expect(substr_count($source, '->limit(50)'))->toBeLessThanOrEqual($allowedLimit50[$name] ?? 0, "{$name}: limit(50) statis baru");
    }
});
