<?php

/**
 * T1.2 — No. Faktur Pajak pada invoice penjualan: aturan format/duplikat, aksi pengisian setelah terbit
 * (invoice otomatis dari DO bertatus `unpaid` tidak bisa diedit lewat form), kolom, filter, View, dan PDF.
 * Keputusan D16: nomor boleh kosong (peringatan), tetapi bila diisi harus sah dan unik.
 */

use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\ListSalesInvoices;
use App\Filament\Resources\SalesInvoiceResource\Pages\ViewSalesInvoice;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Rules\TaxInvoiceNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function tnContext(array $permissions = ['view any invoice', 'view invoice', 'update invoice', 'create invoice', 'view any customer']): array
{
    $cabang = Cabang::factory()->create(['kode' => 'TN-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang TN', 'status' => 1]);

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

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Faktur Pajak', 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'TN-'.strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Faktur', 'uom_id' => $uom->id, 'cost_price' => 5000, 'sell_price' => 8687,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'user', 'idr', 'warehouse', 'customer', 'product');
}

/** Invoice otomatis (status `unpaid`, jurnal + piutang nyata) dari DO yang diselesaikan — persis jalur produksi. */
function tnAutoInvoice(array $ctx, float $tax = 11): Invoice
{
    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-TN-'.strtoupper(substr(uniqid(), -6)), 'order_date' => now(),
        'status' => 'approved', 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji',
    ]);
    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'delivered_quantity' => 0, 'unit_price' => 8687,
        'discount' => 5, 'tax' => $tax, 'tipe_pajak' => $tax > 0 ? 'Eksklusif' : 'none', 'currency_id' => $ctx['idr']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-TN-'.strtoupper(substr(uniqid(), -6)), 'delivery_date' => now(), 'status' => 'sent', 'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $deliveryOrder->salesOrders()->attach($saleOrder->id);
    DeliveryOrderItem::create(['delivery_order_id' => $deliveryOrder->id, 'sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => 12]);

    $deliveryOrder->update(['status' => 'completed']);

    return Invoice::where('from_model_type', SaleOrder::class)->where('from_model_id', $saleOrder->id)->firstOrFail();
}

/** Invoice "kosong" tanpa observer (untuk uji filter/status/duplikat). */
function tnQuietInvoice(array $ctx, array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->make(array_merge([
        'from_model_type' => SaleOrder::class, 'from_model_id' => 1, 'status' => 'unpaid', 'subtotal' => 100000, 'tax' => 11, 'ppn_rate' => 11,
        'total' => 111000, 'invoice_date' => now()->toDateString(), 'cabang_id' => $ctx['cabang']->id, 'tax_invoice_number' => null,
    ], $attributes));
    $invoice->saveQuietly();

    return $invoice;
}

// ───────────────────────────── Aturan: format, normalisasi, duplikat ─────────────────────────────

it('format: 16 digit dengan atau tanpa pemisah lolos dan dinormalkan; selain itu ditolak; kosong lolos', function () {
    $validate = fn (?string $value) => Validator::make(['n' => $value], ['n' => [new TaxInvoiceNumber]])->passes();

    expect($validate('010.000-26.12345678'))->toBeTrue()
        ->and($validate('0100002612345678'))->toBeTrue()
        ->and($validate('010 000-26 12345678'))->toBeTrue()
        ->and($validate(null))->toBeTrue()
        ->and($validate('   '))->toBeTrue()
        ->and($validate('010.000-26.1234567'))->toBeFalse()      // 15 digit
        ->and($validate('010.000-26.123456789'))->toBeFalse()    // 17 digit
        ->and($validate('FP-2026/001'))->toBeFalse()
        ->and($validate('010.000-26.1234567A'))->toBeFalse();

    expect(TaxInvoiceNumber::normalize('0100002612345678'))->toBe('010.000-26.12345678')
        ->and(TaxInvoiceNumber::normalize(' 010 000-26 12345678 '))->toBe('010.000-26.12345678')
        ->and(TaxInvoiceNumber::normalize('  '))->toBeNull()
        ->and(TaxInvoiceNumber::normalize('bukan-nomor'))->toBe('bukan-nomor');   // tidak dirusak; validasi yang menolak
});

it('duplikat ditolak terhadap invoice penjualan lain maupun invoice pembelian; invoice sendiri dikecualikan; soft-delete diabaikan', function () {
    $ctx = tnContext();
    $sales = tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.11112222']);
    tnQuietInvoice($ctx, ['from_model_type' => 'App\\Models\\PurchaseOrder', 'tax_invoice_number' => '010.000-26.33334444']);
    $deleted = tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.55556666']);
    $deleted->delete();

    $passes = fn (string $value, ?int $except = null) => Validator::make(['n' => $value], ['n' => [new TaxInvoiceNumber($except)]])->passes();

    expect($passes('0100002611112222'))->toBeFalse()                 // sama, beda pemisah
        ->and($passes('010.000-26.11112222', $sales->id))->toBeTrue() // mengedit invoice sendiri
        ->and($passes('010.000-26.33334444'))->toBeFalse()            // dipakai invoice pembelian
        ->and($passes('010.000-26.55556666'))->toBeTrue()             // pemilik lama sudah dihapus
        ->and($passes('010.000-26.99990000'))->toBeTrue();
});

// ───────────────────────────── Aksi pengisian setelah invoice terbit ─────────────────────────────

it('invoice otomatis (unpaid) dapat diberi nomor lewat aksi tabel; jurnal dan piutang TIDAK berubah; tercatat di riwayat aktivitas', function () {
    $ctx = tnContext();
    $invoice = tnAutoInvoice($ctx);

    expect($invoice->status)->toBe('unpaid');   // premis: tak bisa diedit lewat form

    $journals = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count();
    $journalDebit = (float) JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->sum('debit');
    $remaining = (float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining');
    $total = (float) $invoice->total;
    $activityBefore = Activity::where('subject_type', Invoice::class)->where('subject_id', $invoice->id)->count();

    expect($journals)->toBeGreaterThan(0);

    Livewire::actingAs($ctx['user'])
        ->test(ListSalesInvoices::class)
        ->assertTableActionVisible('set_tax_invoice_number', $invoice)
        ->callTableAction('set_tax_invoice_number', $invoice, data: ['tax_invoice_number' => '0100002612345678'])
        ->assertHasNoTableActionErrors();

    $invoice->refresh();

    expect($invoice->tax_invoice_number)->toBe('010.000-26.12345678')
        ->and(JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count())->toBe($journals)
        ->and((float) JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->sum('debit'))->toBe($journalDebit)
        ->and((float) AccountReceivable::where('invoice_id', $invoice->id)->value('remaining'))->toBe($remaining)
        ->and((float) $invoice->total)->toBe($total)
        ->and(Activity::where('subject_type', Invoice::class)->where('subject_id', $invoice->id)->count())->toBeGreaterThan($activityBefore);
});

it('aksi menolak format tidak sah dan nomor ganda dengan galat pada isian', function () {
    $ctx = tnContext();
    tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.11112222']);
    $invoice = tnQuietInvoice($ctx);

    Livewire::actingAs($ctx['user'])
        ->test(ListSalesInvoices::class)
        ->callTableAction('set_tax_invoice_number', $invoice, data: ['tax_invoice_number' => '12345'])
        ->assertHasTableActionErrors(['tax_invoice_number'])
        ->callTableAction('set_tax_invoice_number', $invoice, data: ['tax_invoice_number' => '0100002611112222'])
        ->assertHasTableActionErrors(['tax_invoice_number']);

    expect($invoice->fresh()->tax_invoice_number)->toBeNull();
});

it('aksi dapat mengubah dan menghapus nomor yang sudah terisi', function () {
    $ctx = tnContext();
    $invoice = tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.11112222']);

    $page = Livewire::actingAs($ctx['user'])->test(ListSalesInvoices::class);

    $page->callTableAction('set_tax_invoice_number', $invoice, data: ['tax_invoice_number' => '010.000-26.22223333']);
    expect($invoice->fresh()->tax_invoice_number)->toBe('010.000-26.22223333');

    $page->callTableAction('set_tax_invoice_number', $invoice, data: ['tax_invoice_number' => '']);
    expect($invoice->fresh()->tax_invoice_number)->toBeNull();
});

it('aksi tidak tampil untuk invoice draft (dan batal), dan tidak untuk pengguna tanpa izin update invoice', function () {
    $ctx = tnContext();
    $draft = tnQuietInvoice($ctx, ['status' => 'draft']);
    $posted = tnQuietInvoice($ctx);

    Livewire::actingAs($ctx['user'])
        ->test(ListSalesInvoices::class)
        ->assertTableActionHidden('set_tax_invoice_number', $draft)
        ->assertTableActionVisible('set_tax_invoice_number', $posted);

    // Status `canceled` belum ada di enum kolom (T5 menambahkannya) → diuji pada modelnya saja.
    expect(\App\Services\SalesInvoiceTaxNumber::canSetOn(new Invoice(['status' => 'canceled'])))->toBeFalse()
        ->and(\App\Services\SalesInvoiceTaxNumber::canSetOn(new Invoice(['status' => 'unpaid'])))->toBeTrue()
        ->and(\App\Services\SalesInvoiceTaxNumber::canSetOn(new Invoice(['status' => 'draft'])))->toBeFalse();

    $viewer = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $viewer->givePermissionTo(['view any invoice', 'view invoice']);

    Livewire::actingAs($viewer)
        ->test(ListSalesInvoices::class)
        ->assertTableActionHidden('set_tax_invoice_number', $posted);

    // otorisasi juga ditegakkan di server (bukan hanya visibilitas tombol)
    expect(fn () => \App\Filament\Resources\SalesInvoiceResource::saveTaxNumber($posted, ['tax_invoice_number' => '010.000-26.12345678']))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('halaman Lihat: badge "Belum diisi" untuk invoice ber-PPN tanpa nomor, aksi mengisi nomor, dan nomor tampil', function () {
    $ctx = tnContext();
    $invoice = tnAutoInvoice($ctx);

    Livewire::actingAs($ctx['user'])
        ->test(ViewSalesInvoice::class, ['record' => $invoice->getKey()])
        ->assertSee('No. Faktur Pajak')
        ->assertSee('Belum diisi')
        ->callAction('set_tax_invoice_number', data: ['tax_invoice_number' => '010.000-26.12345678'])
        ->assertHasNoActionErrors()
        ->assertSee('010.000-26.12345678')
        ->assertDontSee('Belum diisi');

    expect($invoice->fresh()->tax_invoice_number)->toBe('010.000-26.12345678');
});

// ───────────────────────────── Daftar: kolom & filter ─────────────────────────────

it('filter "Faktur pajak": Belum ada hanya invoice ber-PPN yang sudah terbit tanpa nomor; Sudah ada hanya yang bernomor', function () {
    $ctx = tnContext();
    $missing = tnQuietInvoice($ctx);                                                         // ber-PPN, tanpa nomor
    $filled = tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.11112222']);
    $noVat = tnQuietInvoice($ctx, ['tax' => 0, 'ppn_rate' => 0, 'total' => 100000]);         // tanpa PPN → tak perlu faktur
    $draft = tnQuietInvoice($ctx, ['status' => 'draft']);                                    // belum terbit

    Livewire::actingAs($ctx['user'])
        ->test(ListSalesInvoices::class)
        ->filterTable('tax_invoice_state', 'belum')
        ->assertCanSeeTableRecords([$missing])
        ->assertCanNotSeeTableRecords([$filled, $noVat, $draft])
        ->filterTable('tax_invoice_state', 'ada')
        ->assertCanSeeTableRecords([$filled])
        ->assertCanNotSeeTableRecords([$missing, $noVat, $draft]);
});

it('kolom daftar menampilkan nomor, "Belum diisi" (merah) untuk yang wajib, dan "–" bila tak ber-PPN', function () {
    $ctx = tnContext();
    $filled = tnQuietInvoice($ctx, ['tax_invoice_number' => '010.000-26.11112222']);
    $missing = tnQuietInvoice($ctx);
    $noVat = tnQuietInvoice($ctx, ['tax' => 0, 'ppn_rate' => 0, 'total' => 100000]);

    Livewire::actingAs($ctx['user'])
        ->test(ListSalesInvoices::class)
        ->assertTableColumnFormattedStateSet('tax_invoice_number', '010.000-26.11112222', $filled)
        ->assertTableColumnFormattedStateSet('tax_invoice_number', 'Belum diisi', $missing)
        ->assertTableColumnFormattedStateSet('tax_invoice_number', '–', $noVat)
        ->searchTable('010.000-26.11112222')
        ->assertCanSeeTableRecords([$filled])
        ->assertCanNotSeeTableRecords([$missing]);
});

// ───────────────────────────── Form (draft) & PDF ─────────────────────────────

it('form Edit invoice draft: nomor tidak sah ditolak, nomor sah dinormalkan saat disimpan', function () {
    $ctx = tnContext();
    $draft = tnAutoInvoice($ctx);
    $draft->update(['status' => 'draft']);

    Livewire::actingAs($ctx['user'])
        ->test(EditSalesInvoice::class, ['record' => $draft->getKey()])
        ->fillForm(['tax_invoice_number' => 'salah'])
        ->call('save')
        ->assertHasFormErrors(['tax_invoice_number']);

    Livewire::actingAs($ctx['user'])
        ->test(EditSalesInvoice::class, ['record' => $draft->getKey()])
        ->fillForm(['tax_invoice_number' => '0100002612345678'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($draft->fresh()->tax_invoice_number)->toBe('010.000-26.12345678');
});

it('PDF invoice memuat No. Faktur Pajak bila terisi dan tidak menampilkan barisnya bila kosong', function () {
    $ctx = tnContext();
    $invoice = tnAutoInvoice($ctx);

    $render = fn () => view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product.uom', 'cabang'])])->render();

    expect($render())->not->toContain('No. Faktur Pajak');

    $invoice->update(['tax_invoice_number' => '010.000-26.12345678']);

    expect($render())->toContain('No. Faktur Pajak')->toContain('010.000-26.12345678');
});
