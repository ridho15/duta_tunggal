<?php

/**
 * Fase 5B audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md), Isu 10:
 *  - satu perhitungan baris (LineAmounts) + kebijakan pembulatan 2 desimal (D6)
 *  - price invoice baku = harga satuan GROSS; rincian gross/diskon/DPP/PPN/total di semua jalur
 *  - tampilan (layar, PDF) identik: Harga × Qty = Jumlah · Diskon · DPP · PPN · Total
 *
 * Contoh UAT: 20 × Rp8.687, diskon 5%, PPN 11% eksklusif
 *   Jumlah 173.740 | Diskon 8.687 | DPP 165.053 | PPN 18.155,83 | Total 183.208,83
 */

use App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice;
use App\Helpers\MoneyHelper;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesInvoiceLineBuilder;
use App\Services\SalesOrderService;
use App\Support\LineAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function p5bContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'P5B-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang 5B', 'status' => 1]);
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo(['view any invoice', 'view invoice', 'update invoice']);
    Auth::login($user);

    $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'kode' => 'G-' . strtoupper(substr(uniqid(), -5)), 'status' => 1]);

    ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);
    $revenue = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue']);
    ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran', 'type' => 'Liability']);
    $cogs = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP', 'type' => 'Expense']);
    $goods = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
    $inventory = ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Persediaan', 'type' => 'Asset']);

    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'P5B-' . strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Medis 5B', 'cost_price' => 5000, 'sell_price' => 8687,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'user', 'currency', 'warehouse', 'customer', 'product');
}

/** SO Approved: 20 × 8.687, diskon 5%, PPN 11% (tipe pajak dapat diubah) — contoh UAT. */
function p5bSaleOrder(array $ctx, string $taxType = 'Eksklusif', array $item = []): array
{
    $so = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-5B-' . strtoupper(substr(uniqid(), -6)),
        'order_date' => now(), 'status' => 'approved', 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['currency']->id,
        'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji 5B',
    ]);

    $soItem = SaleOrderItem::create(array_merge([
        'sale_order_id' => $so->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'delivered_quantity' => 0,
        'unit_price' => 8687, 'discount' => 5, 'tax' => 11, 'tipe_pajak' => $taxType,
        'currency_id' => $ctx['currency']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ], $item));

    app(SalesOrderService::class)->updateTotalAmount($so->fresh());

    return [$so->fresh(), $soItem];
}

/** Invoice otomatis lewat jalur Sales Order (tanpa DO): SO menjadi completed. */
function p5bInvoiceViaSaleOrder(SaleOrder $so): Invoice
{
    $so->update(['status' => 'completed']);

    return Invoice::where('from_model_id', $so->id)->firstOrFail();
}

/** Invoice otomatis lewat jalur Delivery Order (per DO): DO completed. */
function p5bInvoiceViaDeliveryOrder(array $ctx, SaleOrder $so, SaleOrderItem $soItem, float $qty = 20): Invoice
{
    $do = DeliveryOrder::create([
        'do_number' => 'DO-5B-' . strtoupper(substr(uniqid(), -6)), 'delivery_date' => now(), 'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $do->salesOrders()->attach($so->id);
    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id, 'sale_order_item_id' => $soItem->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty,
    ]);
    DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => $qty]);

    foreach (['sent', 'completed'] as $status) {
        $do->update(['status' => $status]);
    }

    return Invoice::whereJsonContains('delivery_orders', $do->id)->firstOrFail();
}

// ───────────────────────────── 10.1 Perhitungan tunggal ─────────────────────────────

it('Isu 10: contoh UAT — 20 × 8.687, diskon 5%, PPN 11% eksklusif = 183.208,83 (bukan 183.209)', function () {
    $line = LineAmounts::calculate(20, 8687, 5, 11, 'Eksklusif');

    expect($line['gross'])->toBe(173740.0)
        ->and($line['discount_amount'])->toBe(8687.0)
        ->and($line['dpp'])->toBe(165053.0)
        ->and($line['ppn'])->toBe(18155.83)
        ->and($line['total'])->toBe(183208.83)
        ->and(round($line['dpp'] + $line['ppn'], 2))->toBe($line['total'])
        ->and(round($line['gross'] - $line['discount_amount'], 2))->toBe($line['dpp']);
});

it('Isu 10: inklusif — total = nilai setelah diskon, DPP diekstrak, PPN = total − DPP; non pajak tanpa PPN', function () {
    $inclusive = LineAmounts::calculate(20, 8687, 5, 11, 'Inklusif');
    expect($inclusive['total'])->toBe(165053.0)
        ->and($inclusive['dpp'])->toBe(148696.4)
        ->and($inclusive['ppn'])->toBe(16356.6)
        ->and(round($inclusive['dpp'] + $inclusive['ppn'], 2))->toBe($inclusive['total'])
        ->and(round($inclusive['gross'] - $inclusive['discount_amount'], 2))->toBe($inclusive['total']);

    $none = LineAmounts::calculate(3, 1000, 0, 11, 'Non Pajak');
    expect($none['ppn'])->toBe(0.0)->and($none['total'])->toBe(3000.0)->and($none['tax_rate'])->toBe(0.0);
});

it('Isu 10: kebijakan pembulatan dapat dikembalikan ke rupiah bulat lewat config (rollback tanpa kode)', function () {
    config(['sales.line_rounding_decimals' => 0]);
    $line = LineAmounts::calculate(20, 8687, 5, 11, 'Eksklusif');

    expect($line['ppn'])->toBe(18156.0)->and($line['total'])->toBe(183209.0);

    config(['sales.line_rounding_decimals' => 2]);
    expect(LineAmounts::calculate(20, 8687, 5, 11, 'Eksklusif')['total'])->toBe(183208.83);
});

it('Isu 10: SO, quotation, dan invoice memakai perhitungan yang sama (hitungSubtotal → LineAmounts)', function () {
    expect(HelperController::hitungSubtotal(20, 8687, 5, 11, 'Eksklusif'))->toBe(183208.83)
        ->and(HelperController::hitungSubtotal(20, 8687, 5, 11, 'Eksklusif'))->toBe(LineAmounts::calculate(20, 8687, 5, 11, 'Eksklusif')['total'])
        ->and(HelperController::hitungSubtotal(20, 8687, 5, 11, 'Inklusif'))->toBe(165053.0)
        ->and(HelperController::hitungSubtotal(20, 8687, 5, 11, 'Non Pajak'))->toBe(165053.0)
        ->and(HelperController::hitungSubtotal(20, 8687, 5, 11, 'included'))->toBe(165053.0);   // varian penulisan lama tetap dikenal
});

it('Isu 10: format uang baku selalu 2 desimal sehingga sen tidak tersembunyi', function () {
    expect(LineAmounts::money(183208.83))->toBe('Rp 183.208,83')
        ->and(LineAmounts::money(173740))->toBe('Rp 173.740,00')
        ->and(LineAmounts::money(8687))->toBe('Rp 8.687,00');
});

// ───────────────────────────── 10.2 Semua jalur pembuatan invoice ─────────────────────────────

it('Isu 10: invoice dari jalur Sales Order — price GROSS + rincian; total invoice = total SO (183.208,83)', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    expect(round((float) $so->total_amount, 2))->toBe(183208.83);

    $invoice = p5bInvoiceViaSaleOrder($so);
    $item = $invoice->invoiceItem()->firstOrFail();

    expect((float) $item->price)->toBe(8687.0)                    // harga satuan gross, bukan net
        ->and((float) $item->discount)->toBe(5.0)
        ->and((float) $item->gross_amount)->toBe(173740.0)
        ->and((float) $item->discount_amount)->toBe(8687.0)
        ->and((float) $item->subtotal)->toBe(165053.0)             // DPP
        ->and((float) $item->tax_rate)->toBe(11.0)
        ->and((float) $item->tax_amount)->toBe(18155.83)
        ->and((float) $item->total)->toBe(183208.83)
        ->and(round((float) $invoice->total, 2))->toBe(round((float) $so->total_amount, 2))
        ->and(round((float) $invoice->subtotal, 2))->toBe(165053.0);
});

it('Isu 10: invoice dari jalur Delivery Order — price GROSS (dulu NET), rincian identik dengan jalur Sales Order', function () {
    $ctx = p5bContext();
    [$soA] = p5bSaleOrder($ctx);
    [$soB, $soBItem] = p5bSaleOrder($ctx);

    $viaSo = p5bInvoiceViaSaleOrder($soA)->invoiceItem()->firstOrFail();
    $viaDo = p5bInvoiceViaDeliveryOrder($ctx, $soB, $soBItem);
    $doItem = $viaDo->invoiceItem()->firstOrFail();

    foreach (['price', 'discount', 'gross_amount', 'discount_amount', 'subtotal', 'tax_rate', 'tax_amount', 'total'] as $column) {
        expect((float) $doItem->{$column})->toBe((float) $viaSo->{$column}, "kolom {$column}");
    }
    expect((float) $doItem->price)->toBe(8687.0)
        ->and(round((float) $viaDo->total, 2))->toBe(183208.83)
        ->and(round((float) $viaDo->dpp, 2))->toBe(165053.0);
});

it('Isu 10: invoice per DO untuk pengiriman sebagian — baris memakai kuantitas DO dengan rincian yang sama', function () {
    $ctx = p5bContext();
    [$so, $soItem] = p5bSaleOrder($ctx);

    $invoice = p5bInvoiceViaDeliveryOrder($ctx, $so, $soItem, 12);
    $item = $invoice->invoiceItem()->firstOrFail();
    $expected = LineAmounts::calculate(12, 8687, 5, 11, 'Eksklusif');

    expect((float) $item->quantity)->toBe(12.0)
        ->and((float) $item->gross_amount)->toBe($expected['gross'])
        ->and((float) $item->discount_amount)->toBe($expected['discount_amount'])
        ->and((float) $item->subtotal)->toBe($expected['dpp'])
        ->and((float) $item->tax_amount)->toBe($expected['ppn'])
        ->and(round((float) $invoice->total, 2))->toBe($expected['total']);
});

it('Isu 10: SO inklusif pada jalur Delivery Order tidak lagi ditambah PPN kedua kali', function () {
    $ctx = p5bContext();
    [$so, $soItem] = p5bSaleOrder($ctx, 'Inklusif');

    $invoice = p5bInvoiceViaDeliveryOrder($ctx, $so, $soItem);
    $item = $invoice->invoiceItem()->firstOrFail();

    expect((float) $item->total)->toBe(165053.0)          // sudah termasuk PPN
        ->and((float) $item->subtotal)->toBe(148696.4)    // DPP diekstrak
        ->and((float) $item->tax_amount)->toBe(16356.6)
        ->and(round((float) $invoice->total, 2))->toBe(165053.0)
        ->and(round((float) $so->total_amount, 2))->toBe(165053.0);
});

it('Isu 10: SalesInvoiceLineBuilder — baris form dicocokkan ke item SO, pajak mengikuti header invoice yang diubah user', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $builder = app(SalesInvoiceLineBuilder::class);

    // Invoice header: Eksklusif 11% (default dari SO)
    $invoice = new Invoice(['from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'tipe_pajak' => 'Eksklusif', 'ppn_rate' => 11]);
    $formItems = [['product_id' => $ctx['product']->id, 'quantity' => 20, 'price' => 8252.65, 'total' => 165053]];   // form: harga NET

    $built = $builder->fromFormItems($invoice, $formItems);
    expect($built['matched'])->toBeTrue()
        ->and($built['items'][0])->toMatchArray(['price' => 8687.0, 'discount' => 5.0, 'gross_amount' => 173740.0, 'discount_amount' => 8687.0, 'subtotal' => 165053.0, 'tax_amount' => 18155.83, 'total' => 183208.83]);

    // User mengubah tarif/tipe di form: baris mengikuti header
    $override = new Invoice(['from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'tipe_pajak' => 'None', 'ppn_rate' => 0]);
    $none = $builder->fromFormItems($override, $formItems)['items'][0];
    expect($none['tax_amount'])->toBe(0.0)->and($none['total'])->toBe(165053.0);

    // Produk yang tidak ada di SO: dilaporkan tidak cocok; harga form dipakai sebagai gross tanpa diskon
    $unmatched = $builder->fromFormItems($invoice, [['product_id' => 999999, 'quantity' => 2, 'price' => 1000]]);
    expect($unmatched['matched'])->toBeFalse()->and($unmatched['items'][0]['gross_amount'])->toBe(2000.0);
});

// ───────────────────────────── 10.3 Rincian baris untuk invoice lama ─────────────────────────────

it('Isu 10: breakdown() — invoice lama dengan price GROSS dan price NET sama-sama menampilkan Harga × Qty = Jumlah yang benar', function () {
    $ctx = p5bContext();
    $mk = fn (array $attrs) => (function () use ($ctx, $attrs) {
        $invoice = Invoice::withoutEvents(fn () => Invoice::create([
            'invoice_number' => 'INV-5B-' . strtoupper(substr(uniqid(), -6)), 'from_model_type' => SaleOrder::class, 'from_model_id' => p5bSaleOrder($ctx)[0]->id,
            'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 165053, 'total' => 183208.83,
            'tipe_pajak' => 'Eksklusif', 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
        ]));

        return InvoiceItem::create(array_merge(['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'discount' => 5,
            'tax_rate' => 11, 'tax_amount' => 18155.83, 'subtotal' => 165053, 'total' => 183208.83], $attrs));
    })();

    $gross = $mk(['price' => 8687])->breakdown();
    expect($gross)->toMatchArray(['basis' => 'gross', 'gross' => 173740.0, 'discount_amount' => 8687.0, 'unit_price' => 8687.0, 'dpp' => 165053.0]);

    $net = $mk(['price' => 8252.65])->breakdown();   // jalur DO lama: price sudah dipotong diskon
    expect($net)->toMatchArray(['basis' => 'net', 'gross' => 173740.0, 'discount_amount' => 8687.0, 'unit_price' => 8687.0]);

    // nilai yang tidak dapat dijelaskan: tidak menebak
    $odd = $mk(['price' => 1234])->breakdown();
    expect($odd['basis'])->toBe('tidak-cocok');
});

it('Isu 10: net_unit_price (dipakai retur) tidak bergantung pada apakah price gross atau net; data tak cocok memakai price apa adanya', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = Invoice::withoutEvents(fn () => Invoice::create([
        'invoice_number' => 'INV-5B-NET', 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 165053, 'total' => 183208.83, 'tipe_pajak' => 'Eksklusif', 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
    ]));
    $base = ['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'discount' => 5, 'tax_rate' => 11, 'tax_amount' => 18155.83, 'subtotal' => 165053, 'total' => 183208.83];

    expect(round(InvoiceItem::create($base + ['price' => 8687])->net_unit_price, 2))->toBe(8252.65)
        ->and(round(InvoiceItem::create($base + ['price' => 8252.65])->net_unit_price, 2))->toBe(8252.65)
        ->and(InvoiceItem::create($base + ['price' => 1234])->net_unit_price)->toBe(1234.0);
});

it('Isu 10: data lama tanpa subtotal (DPP) diturunkan dari total − PPN', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = Invoice::withoutEvents(fn () => Invoice::create([
        'invoice_number' => 'INV-5B-LEGACY', 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 0, 'total' => 125000000, 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
    ]));
    $item = InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 10, 'price' => 12500000, 'discount' => 0,
        'tax_rate' => 0, 'tax_amount' => 0, 'subtotal' => 0, 'total' => 125000000]);

    expect($item->breakdown())->toMatchArray(['basis' => 'gross', 'gross' => 125000000.0, 'dpp' => 125000000.0, 'discount_amount' => 0.0]);
});

// ───────────────────────────── 10.5 Backfill ─────────────────────────────

it('Isu 10: invoices:backfill-line-breakdown — dry-run tidak mengubah; --apply hanya mengisi dua kolom baru; nilai terposting tak berubah', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = Invoice::withoutEvents(fn () => Invoice::create([
        'invoice_number' => 'INV-5B-BF', 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 165053, 'total' => 183208.83, 'tipe_pajak' => 'Eksklusif', 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
    ]));
    $base = ['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'discount' => 5, 'tax_rate' => 11, 'tax_amount' => 18155.83, 'subtotal' => 165053, 'total' => 183208.83];
    $grossItem = InvoiceItem::create($base + ['price' => 8687]);
    $netItem = InvoiceItem::create($base + ['price' => 8252.65]);
    $oddItem = InvoiceItem::create($base + ['price' => 1234]);
    $before = DB::table('invoice_items')->whereIn('id', [$grossItem->id, $netItem->id, $oddItem->id])->get()->keyBy('id');

    Artisan::call('invoices:backfill-line-breakdown');
    expect(Artisan::output())->toContain('DRY-RUN')->toContain('net')
        ->and(InvoiceItem::whereNull('gross_amount')->count())->toBe(3);

    Artisan::call('invoices:backfill-line-breakdown', ['--apply' => true]);
    $after = DB::table('invoice_items')->whereIn('id', [$grossItem->id, $netItem->id, $oddItem->id])->get()->keyBy('id');

    expect((float) $after[$grossItem->id]->gross_amount)->toBe(173740.0)
        ->and((float) $after[$grossItem->id]->discount_amount)->toBe(8687.0)
        ->and((float) $after[$netItem->id]->gross_amount)->toBe(173740.0)       // dihitung mundur dari price NET
        ->and((float) $after[$netItem->id]->discount_amount)->toBe(8687.0)
        ->and($after[$oddItem->id]->gross_amount)->toBeNull();                  // tidak cocok: tidak diisi

    // Nilai yang sudah diposting tidak berubah sama sekali
    foreach ([$grossItem, $netItem, $oddItem] as $item) {
        foreach (['price', 'discount', 'tax_rate', 'tax_amount', 'subtotal', 'total'] as $column) {
            expect($after[$item->id]->{$column})->toEqual($before[$item->id]->{$column});
        }
    }

    // Idempoten
    Artisan::call('invoices:backfill-line-breakdown', ['--apply' => true]);
    expect(Artisan::output())->toContain('Tidak ada baris yang perlu dilengkapi');

    array_map('unlink', glob(storage_path('app/backfill/invoice-line-breakdown-*.csv')) ?: []);
});

// ───────────────────────────── 10.3 & 10.4 Tampilan: PDF = layar ─────────────────────────────

it('Isu 10: PDF invoice memuat Harga × Qty = Jumlah, Diskon (% dan Rp), DPP, PPN (% dan Rp), Total — 2 desimal', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = p5bInvoiceViaSaleOrder($so);

    $html = view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render();

    expect($html)
        ->toContain('Harga Satuan')->toContain('Jumlah')->toContain('Diskon (%)')->toContain('Diskon (Rp)')
        ->toContain('DPP')->toContain('PPN (%)')->toContain('PPN (Rp)')
        ->toContain('Rp 8.687,00')          // harga satuan gross
        ->toContain('Rp 173.740,00')        // jumlah
        ->toContain('5,00%')->toContain('Rp 8.687,00')
        ->toContain('Rp 165.053,00')        // DPP
        ->toContain('11,00%')->toContain('Rp 18.155,83')
        ->toContain('Rp 183.208,83')        // total baris & TOTAL
        ->not->toContain('Rp 183.209');
});

it('Isu 10: PDF Sales Order dan Quotation menampilkan 183.208,83 (bukan pembulatan rupiah yang menyembunyikan sen)', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);

    $soHtml = view('pdf.sales-order', ['saleOrder' => $so->load('saleOrderItem.product.uom', 'customer', 'cabang')])->render();
    expect($soHtml)->toContain('Rp 183.208,83')->toContain('Rp 18.155,83')->toContain('Rp 165.053,00')->not->toContain('Rp 183.209');

    $quotation = \App\Models\Quotation::create([
        'quotation_number' => 'QO-5B-1', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(), 'valid_until' => now()->addDays(30),
        'currency_id' => $ctx['currency']->id, 'exchange_rate' => 1, 'status' => 'approve', 'total_amount' => 183208.83,
    ]);
    \App\Models\QuotationItem::create(['quotation_id' => $quotation->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'unit_price' => 8687, 'discount' => 5, 'tax' => 11, 'tax_type' => 'eksklusif']);

    $qHtml = view('pdf.quotation', ['quotation' => $quotation->load('quotationItem.product.uom', 'customer', 'cabang', 'createdBy', 'approveBy')])->render();
    expect($qHtml)->toContain('Rp 183.208,83')->not->toContain('Rp 183.209');
});

it('Isu 10: halaman Lihat invoice menampilkan rincian baris dan total yang sama dengan PDF', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = p5bInvoiceViaSaleOrder($so);

    Livewire::actingAs($ctx['user'])
        ->test(ViewSalesInvoice::class, ['record' => $invoice->getKey()])
        ->assertSuccessful()
        ->assertSee('Rp 8.687,00')
        ->assertSee('Rp 173.740,00')
        ->assertSee('5,00% = Rp 8.687,00')
        ->assertSee('Rp 165.053,00')
        ->assertSee('11,00% = Rp 18.155,83')
        ->assertSee('Rp 183.208,83');
});

it('Isu 10: invoice PDF tampil untuk invoice lama (rincian dihitung dari price/diskon/DPP tanpa kolom baru)', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = Invoice::withoutEvents(fn () => Invoice::create([
        'invoice_number' => 'INV-5B-OLD', 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(), 'subtotal' => 165053, 'total' => 183208.83, 'tipe_pajak' => 'Eksklusif', 'ppn_rate' => 11, 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id,
    ]));
    InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'price' => 8252.65, 'discount' => 5,
        'tax_rate' => 11, 'tax_amount' => 18155.83, 'subtotal' => 165053, 'total' => 183208.83]);   // price NET (jalur DO lama)

    $html = view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render();

    // tidak lagi tampak "harga 8.252,65 × 20 dengan diskon 5%" (seolah diskon dua kali)
    expect($html)->toContain('Rp 8.687,00')->toContain('Rp 173.740,00')->not->toContain('Rp 8.252,65');
});

it('Isu 10: endpoint PDF invoice berhasil (landscape, 12 kolom)', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = p5bInvoiceViaSaleOrder($so);

    $response = test()->actingAs($ctx['user'])->get(route('pdf-stream', ['type' => 'sales-invoice', 'id' => $invoice->id]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('Isu 10: retur customer memakai harga bersih (setelah diskon), bukan harga gross', function () {
    // net_unit_price adalah satu-satunya sumber nilai retur; jalur SO menyimpan price gross sehingga sebelumnya
    // retur invoice berdiskon dikreditkan terlalu besar.
    $source = file_get_contents(app_path('Services/CustomerReturnService.php'));

    expect($source)->toContain('invoiceItem?->net_unit_price')->not->toContain('invoiceItem?->price');
});

it('Isu 10: halaman Ubah invoice menampilkan rincian baris dan menyimpan ulang dengan rincian baku (header = Σ baris)', function () {
    $ctx = p5bContext();
    [$so] = p5bSaleOrder($ctx);
    $invoice = p5bInvoiceViaSaleOrder($so);
    DB::table('invoices')->where('id', $invoice->id)->update(['status' => 'draft']);   // hanya invoice Draft yang dapat diubah (InvoicePolicy)

    $component = Livewire::actingAs($ctx['user'])
        ->test(\App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice::class, ['record' => $invoice->getKey()])
        ->assertSuccessful();

    $state = $component->get('data.invoiceItem');
    $first = collect($state)->first();
    expect($first['bd_gross'])->toBe('Rp 173.740,00')
        ->and($first['bd_discount'])->toBe('5,00% = Rp 8.687,00')
        ->and($first['bd_dpp'])->toBe('Rp 165.053,00')
        ->and($first['bd_ppn'])->toBe('11,00% = Rp 18.155,83');

    $component->call('save')->assertHasNoFormErrors();

    $invoice->refresh();
    $item = $invoice->invoiceItem()->firstOrFail();
    expect((float) $item->gross_amount)->toBe(173740.0)
        ->and((float) $item->price)->toBe(8687.0)                    // tetap gross setelah disimpan dari form
        ->and((float) $item->subtotal)->toBe(165053.0)
        ->and((float) $item->tax_amount)->toBe(18155.83)
        ->and(round((float) $invoice->subtotal, 2))->toBe(165053.0)
        ->and(round((float) $invoice->total, 2))->toBe(183208.83);
});
