<?php

/**
 * Fase 1 audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md):
 *  - Isu 1: data quotation (tempo, mata uang, alamat kirim, catatan) harus ikut tersalin ke SO
 *  - Isu 2: angka di modal "Buat Sales Order dari Quotation" tidak boleh melompat 100x
 */

use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Pages\ListQuotations;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Contoh UAT: 20 x Rp8.687, diskon 5%, PPN 11% eksklusif
 * => DPP 165.053 | PPN 18.155,83 | subtotal 183.208,83
 */
function phase1Context(array $customer = [], array $quotation = []): array
{
    $cabang = Cabang::factory()->create(['kode' => 'P1-' . strtoupper(substr(uniqid(), -6)), 'nama' => 'Cabang Fase 1', 'status' => 1]);

    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo([
        'view any quotation', 'view quotation', 'update quotation',
        'create sales order', 'view any sales order', 'view sales order', 'update sales order',
    ]);
    Auth::login($user);

    $idr = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
    );

    $customerModel = Customer::factory()->create(array_merge([
        'cabang_id' => $cabang->id,
        'tempo_kredit' => 30,
        'address' => 'Jl. Master Customer No. 1, Jakarta',
        'tipe_pembayaran' => 'Bebas',
    ], $customer));

    $product = Product::factory()->create(['name' => 'Alat Medis Fase 1', 'sku' => 'AMD-' . strtoupper(substr(uniqid(), -7)), 'sell_price' => 8687]);

    $quotationModel = Quotation::create(array_merge([
        'quotation_number' => 'QO-P1-' . uniqid(),
        'customer_id' => $customerModel->id,
        'cabang_id' => $cabang->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'currency_id' => $idr->id,
        'exchange_rate' => 1,
        'tempo_pembayaran' => 30,
        'notes' => 'Catatan penawaran Fase 1',
        'status' => 'approve',
        'created_by' => $user->id,
        'total_amount' => 183208.83,
    ], $quotation));

    QuotationItem::create([
        'quotation_id' => $quotationModel->id,
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_price' => 8687,
        'discount' => 5,
        'tax' => 11,
        'tax_type' => 'eksklusif',
    ]);

    return ['cabang' => $cabang, 'user' => $user, 'idr' => $idr, 'customer' => $customerModel, 'product' => $product, 'quotation' => $quotationModel];
}

// ───────────────────────────── Isu 1: pemetaan tunggal ─────────────────────────────

it('Isu 1: pemetaan menyalin tempo, mata uang, kurs, alamat, dan catatan dari quotation', function () {
    $ctx = phase1Context(quotation: ['shipped_to' => 'Gudang Klien, Bekasi']);

    $mapped = app(SalesOrderService::class)->headerFromQuotation($ctx['quotation']->fresh());

    expect($mapped['tempo_pembayaran'])->toBe(30)
        ->and($mapped['currency_id'])->toBe($ctx['idr']->id)
        ->and((float) $mapped['exchange_rate'])->toBe(1.0)
        ->and($mapped['shipped_to'])->toBe('Gudang Klien, Bekasi')   // alamat quotation > alamat customer
        ->and($mapped['notes'])->toBe('Catatan penawaran Fase 1')
        ->and($mapped['cabang_id'])->toBe($ctx['cabang']->id);
});

it('Isu 1: tempo 0 (tunai) pada quotation TIDAK dianggap kosong', function () {
    $ctx = phase1Context(quotation: ['tempo_pembayaran' => 0]);   // customer punya tempo 30

    expect(app(SalesOrderService::class)->headerFromQuotation($ctx['quotation']->fresh())['tempo_pembayaran'])->toBe(0);
});

it('Isu 1: tempo kosong jatuh ke tempo customer, lalu ke default 30', function () {
    $service = app(SalesOrderService::class);

    $ctx = phase1Context(customer: ['tempo_kredit' => 45], quotation: ['tempo_pembayaran' => null]);
    expect($service->headerFromQuotation($ctx['quotation']->fresh())['tempo_pembayaran'])->toBe(45);

    expect($service->resolveTempoPembayaran(null, null, null))->toBe(30)
        ->and($service->resolveTempoPembayaran(0, null, null))->toBe(0)
        ->and($service->resolveTempoPembayaran('', null, null))->toBe(30);
});

it('Isu 1: mata uang NULL pada quotation lama jatuh ke IDR, bukan kosong', function () {
    $ctx = phase1Context(quotation: ['currency_id' => null]);

    expect(app(SalesOrderService::class)->headerFromQuotation($ctx['quotation']->fresh())['currency_id'])->toBe($ctx['idr']->id);
});

it('Isu 1: alamat kirim = alamat quotation, lalu alamat customer, lalu null (bukan placeholder "-")', function () {
    $service = app(SalesOrderService::class);

    $ctx = phase1Context();
    expect($service->headerFromQuotation($ctx['quotation']->fresh())['shipped_to'])->toBe('Jl. Master Customer No. 1, Jakarta');

    $ctx2 = phase1Context(customer: ['address' => '-'], quotation: ['quotation_number' => 'QO-P1-NOADDR']);
    expect($service->headerFromQuotation($ctx2['quotation']->fresh())['shipped_to'])->toBeNull();
});

it('Isu 1: API getQuotation memakai pemetaan yang sama', function () {
    $ctx = phase1Context(quotation: ['tempo_pembayaran' => 0, 'currency_id' => null]);

    $this->actingAs($ctx['user'])
        ->getJson("/api/v1/sales-orders/quotation/{$ctx['quotation']->id}")
        ->assertOk()
        ->assertJsonPath('data.tempo_pembayaran', 0)
        ->assertJsonPath('data.currency_id', $ctx['idr']->id)
        ->assertJsonPath('data.shipped_to', 'Jl. Master Customer No. 1, Jakarta')
        ->assertJsonPath('data.notes', 'Catatan penawaran Fase 1');
});

function phase1SoPayload(array $ctx, array $header = []): array
{
    return [
        'header' => array_merge([
            'so_number' => 'SO-P1-' . uniqid(),
            'customer_id' => $ctx['customer']->id,
            'cabang_id' => $ctx['cabang']->id,
            'quotation_id' => $ctx['quotation']->id,
            'order_date' => now()->format('Y-m-d'),
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $ctx['idr']->id,
            'exchange_rate' => 1,
        ], $header),
        'items' => [[
            'product_id' => $ctx['product']->id,
            'quantity' => 20,
            'unit_price' => 8687,
            'discount' => 5,
            'tax_type' => 'eksklusif',
            'tax' => 11,
        ]],
    ];
}

it('Isu 1: API store menyimpan catatan dan menghormati tempo 0 eksplisit', function () {
    $ctx = phase1Context();

    $payload = phase1SoPayload($ctx, ['tempo_pembayaran' => 0, 'notes' => 'Kirim pagi hari', 'shipped_to' => 'Alamat SO']);
    $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload)->assertOk()->assertJsonPath('success', true);

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', $payload['header']['so_number'])->firstOrFail();
    expect($so->notes)->toBe('Kirim pagi hari')
        ->and((int) $so->tempo_pembayaran)->toBe(0)
        ->and($so->shipped_to)->toBe('Alamat SO');
});

it('Isu 1: API store tanpa tempo memakai tempo quotation dan menyimpannya sebagai angka', function () {
    $ctx = phase1Context(quotation: ['tempo_pembayaran' => 60]);

    $payload = phase1SoPayload($ctx);   // tempo_pembayaran tidak dikirim
    $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload)->assertOk();

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', $payload['header']['so_number'])->firstOrFail();
    expect($so->tempo_pembayaran)->not->toBeNull()
        ->and((int) $so->tempo_pembayaran)->toBe(60);
});

it('Isu 1: API show untuk SO lama tanpa mata uang/tempo tidak mengembalikan kosong dan mengirim catatan', function () {
    $ctx = phase1Context(customer: ['tempo_kredit' => 45]);

    $so = SaleOrder::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'so_number' => 'SO-LEGACY-P1',
        'order_date' => now(),
        'tipe_pengiriman' => 'Kirim Langsung',
        'status' => 'draft',
        'notes' => 'Catatan lama',
    ]);
    expect($so->fresh()->currency_id)->toBeNull();

    $this->actingAs($ctx['user'])
        ->getJson("/api/v1/sales-orders/{$so->id}")
        ->assertOk()
        ->assertJsonPath('data.header.currency_id', $ctx['idr']->id)
        ->assertJsonPath('data.header.tempo_pembayaran', 45)
        ->assertJsonPath('data.header.notes', 'Catatan lama');
});

it('Isu 1: API update tidak menghapus catatan bila klien tidak mengirim notes', function () {
    $ctx = phase1Context();
    $so = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-UPD-P1',
        'order_date' => now(), 'tipe_pengiriman' => 'Kirim Langsung', 'status' => 'draft',
        'currency_id' => $ctx['idr']->id, 'tempo_pembayaran' => 30, 'notes' => 'Jangan hilang',
    ]);

    $payload = phase1SoPayload($ctx, ['so_number' => 'SO-UPD-P1', 'tempo_pembayaran' => 30]);
    $this->actingAs($ctx['user'])->putJson("/api/v1/sales-orders/{$so->id}", $payload)->assertOk();

    expect($so->fresh()->notes)->toBe('Jangan hilang');
});

// ───────────────────────────── Isu 2: angka modal ─────────────────────────────

it('Isu 2: format angka preview memakai 2 desimal dan bukan mask (contoh UAT)', function () {
    expect(QuotationResource::formatCurrencyPreviewState(18155.83, 1))->toBe('18.155,83')
        ->and(QuotationResource::formatCurrencyPreviewState(183208.83, 1))->toBe('183.208,83')
        ->and(QuotationResource::formatCurrencyPreviewState('183208.83', 1))->toBe('183.208,83')
        ->and(QuotationResource::formatCurrencyPreviewState(160000, 1))->toBe('160.000,00');
});

it('Isu 2: state awal modal di halaman View menampilkan PPN 18.155,83 dan subtotal 183.208,83', function () {
    $ctx = phase1Context();

    $page = Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->mountAction('create_sale_order');

    $actions = $page->instance()->mountedActionsData;
    $data = $actions[array_key_last($actions)];
    $item = collect($data['saleOrderItems'] ?? [])->first();

    expect($item)->not->toBeNull()
        ->and($item['tax_nominal'])->toBe('18.155,83')
        ->and($item['subtotal'])->toBe('183.208,83')
        ->and($item['unit_price'])->toBe('8.687,00')
        ->and($data['shipped_to'])->toBe('Jl. Master Customer No. 1, Jakarta');
});

it('Isu 2: state awal modal aksi tabel sama persis dengan halaman View (satu definisi)', function () {
    $ctx = phase1Context();

    $table = Livewire::actingAs($ctx['user'])
        ->test(ListQuotations::class)
        ->mountTableAction('create_sale_order', $ctx['quotation']);

    $actions = $table->instance()->mountedTableActionsData;
    $data = $actions[array_key_last($actions)];
    $item = collect($data['saleOrderItems'] ?? [])->first();

    expect($item)->not->toBeNull()
        ->and($item['tax_nominal'])->toBe('18.155,83')
        ->and($item['subtotal'])->toBe('183.208,83');
});

// ───────────────────── Pembuatan SO dari kedua jalur: data identik ─────────────────────

/**
 * Data isian modal. Item SO TIDAK dikirim: modal sudah mengisinya otomatis dari item
 * quotation (default repeater) — persis seperti yang dialami user.
 */
function phase1ModalData(array $extra = []): array
{
    return array_merge([
        'so_number' => 'SO-MODAL-' . uniqid(),
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Jl. Master Customer No. 1, Jakarta',
        'notes' => '',
    ], $extra);
}

it('Isu 1: SO dari halaman View — tempo, mata uang, alamat, catatan tersalin; status Draft', function () {
    $ctx = phase1Context(quotation: ['tempo_pembayaran' => 0]);
    $data = phase1ModalData();

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->callAction('create_sale_order', data: $data)
        ->assertHasNoActionErrors();

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', $data['so_number'])->firstOrFail();
    expect($so->status)->toBe('draft')
        ->and((int) $so->tempo_pembayaran)->toBe(0)
        ->and($so->currency_id)->toBe($ctx['idr']->id)
        ->and($so->shipped_to)->toBe('Jl. Master Customer No. 1, Jakarta')
        ->and($so->notes)->toBe('Catatan penawaran Fase 1')
        ->and($so->quotation_id)->toBe($ctx['quotation']->id)
        ->and($so->saleOrderItem()->count())->toBe(1)
        ->and(round((float) $so->total_amount, 2))->toBe(183208.83);
});

it('Isu 1: SO dari aksi tabel — data identik dengan halaman View; status Draft (D10, flag mati)', function () {
    $ctx = phase1Context();
    $data = phase1ModalData();

    Livewire::actingAs($ctx['user'])
        ->test(ListQuotations::class)
        ->callTableAction('create_sale_order', $ctx['quotation'], data: $data)
        ->assertHasNoTableActionErrors();

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', $data['so_number'])->firstOrFail();
    expect($so->status)->toBe('draft')
        ->and((int) $so->tempo_pembayaran)->toBe(30)
        ->and($so->currency_id)->toBe($ctx['idr']->id)
        ->and($so->shipped_to)->toBe('Jl. Master Customer No. 1, Jakarta')
        ->and($so->notes)->toBe('Catatan penawaran Fase 1')
        ->and(round((float) $so->total_amount, 2))->toBe(183208.83);
});

it('Isu 1: SO dari aksi tabel langsung Approved bila flag config sales.so_from_quotation_auto_approve menyala', function () {
    config(['sales.so_from_quotation_auto_approve' => true]);
    $ctx = phase1Context();
    $data = phase1ModalData();

    Livewire::actingAs($ctx['user'])
        ->test(ListQuotations::class)
        ->callTableAction('create_sale_order', $ctx['quotation'], data: $data)
        ->assertHasNoTableActionErrors();

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', $data['so_number'])->firstOrFail();
    expect($so->status)->toBe('approved');
});

it('Isu 1: alamat kirim wajib diisi di modal (tidak boleh kosong / placeholder)', function () {
    $ctx = phase1Context(customer: ['address' => ''], quotation: ['shipped_to' => null]);

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $ctx['quotation']->getKey()])
        ->callAction('create_sale_order', data: phase1ModalData(['shipped_to' => '']))
        ->assertHasActionErrors(['shipped_to' => 'required']);
});

// ───────────────────────────── Backfill data lama ─────────────────────────────

it('Backfill: dry-run tidak mengubah apa pun; --apply hanya mengisi kolom kosong dan tidak menyentuh invoice', function () {
    $ctx = phase1Context(quotation: ['tempo_pembayaran' => 45]);

    // SO lama dari quotation: semua kolom header kosong
    $legacy = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'quotation_id' => $ctx['quotation']->id,
        'so_number' => 'SO-BF-1', 'order_date' => now(), 'tipe_pengiriman' => 'Kirim Langsung', 'status' => 'draft',
    ]);
    // SO yang sudah punya nilai: tidak boleh ditimpa
    $filled = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'quotation_id' => $ctx['quotation']->id,
        'so_number' => 'SO-BF-2', 'order_date' => now(), 'tipe_pengiriman' => 'Kirim Langsung', 'status' => 'draft',
        'currency_id' => $ctx['idr']->id, 'tempo_pembayaran' => 7, 'shipped_to' => 'Alamat Asli', 'notes' => 'Catatan Asli',
    ]);
    $invoice = Invoice::create([
        'invoice_number' => 'INV-BF-1', 'from_model_type' => SaleOrder::class, 'from_model_id' => $legacy->id,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 100, 'total' => 100, 'status' => 'draft', 'cabang_id' => $ctx['cabang']->id,
    ]);
    $dueBefore = $invoice->fresh()->due_date?->toDateString();

    // dry-run
    Artisan::call('sales:backfill-so-from-quotation');
    expect($legacy->fresh()->currency_id)->toBeNull()
        ->and($legacy->fresh()->tempo_pembayaran)->toBeNull();

    // apply
    Artisan::call('sales:backfill-so-from-quotation', ['--apply' => true]);

    $legacy = $legacy->fresh();
    expect($legacy->currency_id)->toBe($ctx['idr']->id)
        ->and((int) $legacy->tempo_pembayaran)->toBe(45)
        ->and($legacy->shipped_to)->toBe('Jl. Master Customer No. 1, Jakarta')
        ->and($legacy->notes)->toBe('Catatan penawaran Fase 1');

    $filled = $filled->fresh();
    expect((int) $filled->tempo_pembayaran)->toBe(7)
        ->and($filled->shipped_to)->toBe('Alamat Asli')
        ->and($filled->notes)->toBe('Catatan Asli');

    expect($invoice->fresh()->due_date?->toDateString())->toBe($dueBefore);   // invoice tidak diubah

    foreach (glob(storage_path('app/backfill/so-from-quotation-*.csv')) ?: [] as $csv) {
        @unlink($csv);
    }
});

it('Isu 1: handler bersama menyalin item dari quotation bila data repeater tidak tersedia', function () {
    $ctx = phase1Context();

    $so = QuotationResource::createSaleOrderFromQuotation($ctx['quotation'], [
        'so_number' => 'SO-FALLBACK-P1',
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Ambil Sendiri',
    ], autoApprove: false);

    expect($so->saleOrderItem()->count())->toBe(1)
        ->and((int) $so->tempo_pembayaran)->toBe(30)
        ->and($so->shipped_to)->toBe('Jl. Master Customer No. 1, Jakarta')   // fallback dari mapping
        ->and($so->notes)->toBe('Catatan penawaran Fase 1')
        ->and($so->status)->toBe('draft');
});

it('Isu 1: pembuatan SO atomik — bila item gagal, SO tidak tersisa setengah jadi', function () {
    $ctx = phase1Context();

    expect(fn () => QuotationResource::createSaleOrderFromQuotation($ctx['quotation'], [
        'so_number' => 'SO-ATOMIC-P1',
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'saleOrderItems' => [['quantity' => 1]],   // product_id hilang -> gagal
    ]))->toThrow(\ErrorException::class);   // dilempar SETELAH SO dibuat -> transaksi harus rollback

    expect(SaleOrder::withoutGlobalScopes()->where('so_number', 'SO-ATOMIC-P1')->exists())->toBeFalse();
});
