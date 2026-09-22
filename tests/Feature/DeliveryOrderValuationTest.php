<?php

/**
 * T1.4 — satu sumber nilai Delivery Order (DeliveryOrderValuation) + label pilihan yang informatif.
 * Nilai DO harus sama dengan invoice yang terbit dari DO itu (LineAmounts), bukan lagi `harga − diskon + pajak`.
 */

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderValuation;
use App\Support\DocumentLabels;
use App\Support\LineAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function dovContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'DOV-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang DOV', 'status' => 1]);

    $permissions = ['view any invoice', 'create invoice', 'view invoice', 'view any customer'];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions);
    Auth::login($user);

    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'kode' => 'G-'.strtoupper(substr(uniqid(), -5)), 'status' => 1]);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $coa('1120', 'Piutang Dagang', 'Asset');
    $revenue = $coa('4000', 'Penjualan', 'Revenue');
    $coa('2120.06', 'PPn Keluaran', 'Liability');
    $cogs = $coa('5100.10', 'HPP', 'Expense');
    $goods = $coa('1140.20', 'Barang Terkirim', 'Asset');
    $inventory = $coa('1140.10', 'Persediaan', 'Asset');
    $coa('6100.02', 'Biaya Pengiriman', 'Expense');

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Nilai DO', 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'DOV-'.strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Nilai DO', 'uom_id' => $uom->id, 'cost_price' => 5000, 'sell_price' => 9000,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'user', 'idr', 'warehouse', 'customer', 'product');
}

/** SO 20 pcs × Rp8.687, diskon 5%, PPN 11% (tipe & tarif dapat diganti). */
function dovSaleOrder(array $ctx, array $item = []): array
{
    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-DOV-'.strtoupper(substr(uniqid(), -6)),
        'order_date' => '2026-09-12', 'status' => 'completed', 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id,
        'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji No. 1', 'total_amount' => 183208.83,
    ]);
    $saleOrderItem = SaleOrderItem::create(array_merge([
        'sale_order_id' => $saleOrder->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'delivered_quantity' => 0,
        'unit_price' => 8687, 'discount' => 5, 'tax' => 11, 'tipe_pajak' => 'Eksklusif', 'currency_id' => $ctx['idr']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ], $item));

    return [$saleOrder, $saleOrderItem];
}

/** DO berstatus 'sent' (dibuat langsung, tanpa memicu observer) dengan satu item $qty, terhubung ke SO. */
function dovDeliveryOrder(array $ctx, SaleOrder $saleOrder, ?SaleOrderItem $saleOrderItem, float $qty, array $do = []): DeliveryOrder
{
    $deliveryOrder = DeliveryOrder::create(array_merge([
        'do_number' => 'DO-DOV-'.strtoupper(substr(uniqid(), -6)), 'delivery_date' => '2026-09-14', 'status' => 'sent',
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ], $do));
    $deliveryOrder->salesOrders()->attach($saleOrder->id);

    DeliveryOrderItem::create([
        'delivery_order_id' => $deliveryOrder->id, 'sale_order_item_id' => $saleOrderItem?->id,
        'product_id' => $ctx['product']->id, 'quantity' => $qty, 'reason' => 'Uji nilai DO',
    ]);

    return $deliveryOrder->fresh();
}

it('nilai DO memakai LineAmounts (harga gross, diskon %, PPN eksklusif) — bukan harga − diskon + pajak', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);
    $do = dovDeliveryOrder($ctx, $so, $soItem, 12);

    $expected = LineAmounts::calculate(12, 8687, 5, 11, 'Eksklusif');
    $valuation = app(DeliveryOrderValuation::class)->forDeliveryOrder($do);

    expect($valuation['total'])->toBe(round($expected['total'], 2))
        ->and($valuation['dpp'])->toBe((float) $expected['dpp'])
        ->and($valuation['tax'])->toBe((float) $expected['ppn'])
        ->and($valuation['tipe_pajak'])->toBe('Eksklusif')
        ->and((float) $do->total)->toBe($valuation['total']);

    // rumus lama (harga − diskon + pajak) × qty = (8687 − 5 + 11) × 12 = 104.316 — salah
    expect((float) $do->total)->not->toBe(104316.0);
});

it('paritas: nilai DO sama dengan total invoice otomatis yang terbit saat DO selesai', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);
    $do = dovDeliveryOrder($ctx, $so, $soItem, 12, ['additional_cost' => 50000, 'additional_cost_description' => 'Ongkir']);

    $before = app(DeliveryOrderValuation::class)->forDeliveryOrder($do);

    $do->update(['status' => 'completed']);   // observer: jurnal DO + invoice otomatis

    $invoice = Invoice::where('from_model_type', SaleOrder::class)->where('from_model_id', $so->id)->firstOrFail();

    expect(round((float) $invoice->total, 2))->toBe($before['total'])
        ->and(round((float) $invoice->subtotal, 2))->toBe(round($before['dpp'], 2))
        ->and($before['additional_cost'])->toBe(50000.0);
});

it('SO inklusif dan SO tanpa pajak dinilai sesuai tipenya', function () {
    $ctx = dovContext();

    [$soInc, $itemInc] = dovSaleOrder($ctx, ['unit_price' => 11100, 'discount' => 0, 'tax' => 11, 'tipe_pajak' => 'Inklusif']);
    $doInc = dovDeliveryOrder($ctx, $soInc, $itemInc, 10);

    [$soNo, $itemNo] = dovSaleOrder($ctx, ['unit_price' => 5000, 'discount' => 10, 'tax' => 0, 'tipe_pajak' => 'none']);
    $doNo = dovDeliveryOrder($ctx, $soNo, $itemNo, 10);

    // Inklusif: total = 10 × 11.100 (PPN sudah termasuk, tidak ditambah dua kali)
    expect(app(DeliveryOrderValuation::class)->forDeliveryOrder($doInc)['total'])->toBe(111000.0)
        // Tanpa pajak: 10 × 5.000 − 10% = 45.000
        ->and(app(DeliveryOrderValuation::class)->forDeliveryOrder($doNo)['total'])->toBe(45000.0);
});

it('item DO tanpa tautan item SO bernilai 0 (tidak menebak harga), ditandai, dan dicatat peringatan', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);

    Log::spy();
    $do = dovDeliveryOrder($ctx, $so, null, 4);   // sell_price produk 9.000 TIDAK dipakai — sama dengan invoice otomatis
    $valuation = app(DeliveryOrderValuation::class)->forDeliveryOrder($do);

    expect($valuation['total'])->toBe(0.0)
        ->and($valuation['unlinked_items'])->toHaveCount(1);
    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'DeliveryOrderValuation'))->atLeast()->once();

    // DO yang tertaut penuh: tanpa penanda
    $linked = dovDeliveryOrder($ctx, $so, $soItem, 4);
    expect(app(DeliveryOrderValuation::class)->forDeliveryOrder($linked)['unlinked_items'])->toBe([]);

    // label memperingatkan pengguna, bukan sekadar "Rp0"
    expect(DocumentLabels::deliveryOrder($do, 0.0, true))->toContain('⚠ ada item tanpa tautan SO');
});

it('nilai banyak DO dihitung dengan jumlah query tetap (bukan per DO)', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);

    $makeOrders = function (int $count) use ($ctx, $so, $soItem) {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = dovDeliveryOrder($ctx, $so, $soItem, 1)->id;
        }

        return DeliveryOrder::whereIn('id', $ids)->get();
    };

    $countQueries = function (iterable $orders) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(DeliveryOrderValuation::class)->forDeliveryOrders($orders);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $few = $countQueries($makeOrders(2));
    $many = $countQueries($makeOrders(12));

    expect($many)->toBe($few);
});

it('label pilihan SO, DO, dan Quotation memuat customer, tanggal, dan nilai', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);
    $do = dovDeliveryOrder($ctx, $so, $soItem, 12);
    $total = app(DeliveryOrderValuation::class)->forDeliveryOrder($do)['total'];

    $soLabel = DocumentLabels::saleOrder($so->fresh('customer'));
    $doLabel = DocumentLabels::deliveryOrder($do, $total);

    expect($soLabel)->toContain($so->so_number)->toContain('PT Nilai DO')->toContain('12 Sep 2026')->toContain('Rp 183.208,83')
        ->and($doLabel)->toContain($do->do_number)->toContain('14 Sep 2026')->toContain('Rp '.number_format($total, 2, ',', '.'))
        ->and(DocumentLabels::deliveryOrder($do))->toBe($do->do_number.' · 14 Sep 2026');
});

it('form Invoice: pilihan SO memuat nama customer & nilai, pilihan DO memuat nilai yang benar (bukan Rp0)', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);
    // 20 dari 20: SO menjadi Selesai (form Invoice manual hanya memuat SO Selesai — lihat teks bantu T1.8)
    $do = dovDeliveryOrder($ctx, $so, $soItem, 20, ['status' => 'completed']);
    $expectedTotal = app(DeliveryOrderValuation::class)->forDeliveryOrder($do)['total'];

    Livewire::actingAs($ctx['user'])
        ->test(CreateSalesInvoice::class)
        ->fillForm(['selected_customer' => $ctx['customer']->id])
        ->assertFormFieldExists('selected_sale_order', function ($field) use ($so) {
            $label = $field->getOptions()[$so->id] ?? '';

            return str_contains($label, $so->so_number) && str_contains($label, 'PT Nilai DO') && str_contains($label, 'Rp 183.208,83');
        })
        ->fillForm(['selected_customer' => $ctx['customer']->id, 'selected_sale_order' => $so->id])
        ->assertFormFieldExists('selected_delivery_orders', function ($field) use ($do, $expectedTotal) {
            $label = $field->getOptions()[$do->id] ?? '';

            return str_contains($label, $do->do_number)
                && str_contains($label, 'Rp '.number_format($expectedTotal, 2, ',', '.'))
                && ! str_contains($label, 'Rp 0,00');
        });
});

it('PDF DO menampilkan total yang sama dengan valuation (2 desimal)', function () {
    $ctx = dovContext();
    [$so, $soItem] = dovSaleOrder($ctx);
    $do = dovDeliveryOrder($ctx, $so, $soItem, 12, ['additional_cost' => 50000]);
    $total = app(DeliveryOrderValuation::class)->forDeliveryOrder($do)['total'];

    $html = view('pdf.delivery-order', ['deliveryOrder' => $do->load('cabang', 'deliveryOrderItem.product.uom', 'salesOrders.customer')])->render();

    expect($html)->toContain(number_format($total, 2, ',', '.'))
        ->and($html)->toContain('Rp '.number_format(50000, 2, ',', '.'));
});
