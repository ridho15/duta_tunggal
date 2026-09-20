<?php

/**
 * Uji ujung-ke-ujung lintas fase 1-6 (audit 10 bug sedang penjualan).
 *
 * Satu alur nyata dengan satu set angka (contoh UAT: 20 × Rp8.687, diskon 5%, PPN 11% eksklusif) dari
 * Quotation → SO → 2 pengiriman parsial (Surat Jalan + Jadwal) → 2 invoice → penerimaan (kelebihan → deposit) → laporan.
 * Tujuannya membuktikan perbaikan setiap fase BEKERJA BERSAMA, bukan hanya sendiri-sendiri.
 */

use App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt;
use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use App\Filament\Resources\SuratJalanResource\Pages\ListSuratJalans;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\DeliverySchedule;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\SalesReportService;
use App\Services\SuratJalanDocumentBuilder;
use App\Services\SuratJalanService;
use App\Support\LineAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function e2eContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'E2E-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang E2E', 'status' => 1, 'alamat' => 'Jl. Kantor E2E No. 1', 'telepon' => '021-000111']);
    $customerCabang = Cabang::factory()->create(['kode' => 'E2X-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang Customer', 'status' => 1]);

    $permissions = [
        'view any quotation', 'view quotation', 'update quotation', 'create quotation', 'create sales order', 'view any sales order', 'view sales order', 'update sales order',
        'view any surat jalan', 'view surat jalan', 'create surat jalan', 'update surat jalan',
        'view any customer receipt', 'view customer receipt', 'create customer receipt', 'update customer receipt', 'view any customer', 'view any invoice',
    ];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions);
    Auth::login($user);

    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'kode' => 'G-' . strtoupper(substr(uniqid(), -5)), 'status' => 1]);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $coa('1120', 'Piutang Dagang', 'Asset');
    $revenue = $coa('4000', 'Penjualan', 'Revenue');
    $coa('2120.06', 'PPn Keluaran', 'Liability');
    $cogs = $coa('5100.10', 'HPP', 'Expense');
    $goods = $coa('1140.20', 'Barang Terkirim', 'Asset');
    $inventory = $coa('1140.01', 'Persediaan', 'Asset');
    $coa('1110', 'KAS DAN SETARA KAS', 'Asset');
    $coa('1112', 'Rekening Bank', 'Asset');
    $bank = $coa('1112.01.01', 'BANK BCA - OPERASIONAL', 'Asset');
    $bankDeposito = $coa('1112.01.02', 'BANK BCA - DEPOSITO', 'Asset');
    $depositLiability = $coa((string) config('coa.customer_deposit'), 'Hutang Titipan Konsumen', 'Liability');

    $uom = UnitOfMeasure::factory()->create(['name' => 'Pieces', 'abbreviation' => 'pcs']);
    // Customer terdaftar di cabang LAIN (Fase 5A: cabang penerimaan harus mengikuti invoice)
    $customer = Customer::factory()->create(['cabang_id' => $customerCabang->id, 'name' => 'PT Alur Lengkap', 'address' => 'Jl. Master Customer No. 5, Jakarta', 'tempo_kredit' => 45, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'E2E-' . strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Medis E2E', 'uom_id' => $uom->id, 'cost_price' => 5000, 'sell_price' => 8687,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'customerCabang', 'user', 'idr', 'warehouse', 'customer', 'product', 'bank', 'bankDeposito', 'depositLiability');
}

function e2eDeliverPartial(array $ctx, SaleOrder $so, $soItem, float $qty, string $scheduleTag): array
{
    // DO Approved → Surat Jalan terbit → Jadwal (ekspedisi) → berangkat → selesai
    $do = DeliveryOrder::create([
        'do_number' => 'DO-E2E-' . $scheduleTag, 'delivery_date' => now(), 'status' => 'approved',
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $do->salesOrders()->attach($so->id);
    $doItem = DeliveryOrderItem::create(['delivery_order_id' => $do->id, 'sale_order_item_id' => $soItem->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty, 'reason' => 'Rak ' . $scheduleTag]);
    DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => $qty]);

    $sjService = app(SuratJalanService::class);
    $sjService->assertDeliveryOrdersUsable(collect([$do->fresh()]));
    $sj = SuratJalan::create([
        'sj_number' => $sjService->generateCode(), 'issued_at' => now(), 'status' => SuratJalan::STATUS_ISSUED,
        'created_by' => $ctx['user']->id, 'cabang_id' => $ctx['cabang']->id,
    ]);
    $sj->deliveryOrder()->sync([$do->id]);

    $schedule = DeliverySchedule::create([
        'schedule_number' => 'SCH-E2E-' . $scheduleTag, 'scheduled_date' => now()->addDay(), 'delivery_method' => 'ekspedisi',
        'driver_name' => 'JNE Cargo', 'tracking_number' => 'RESI-' . $scheduleTag, 'status' => 'pending',
        'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id,
    ]);
    $schedule->suratJalan()->attach($sj->id);

    return [$do, $sj, $schedule];
}

it('Alur lengkap fase 1-6: Quotation → SO → 2 pengiriman → 2 invoice → penerimaan (kelebihan→deposit) → laporan, semua angka konsisten', function () {
    $ctx = e2eContext();

    // ── Fase 1: Quotation Approved → SO (data ikut, satu pemetaan) ──
    $quotation = Quotation::create([
        'quotation_number' => 'QO-E2E-1', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(), 'valid_until' => now()->addDays(30),
        'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'tempo_pembayaran' => 0, 'shipped_to' => 'Gudang Klien, Bekasi', 'notes' => 'Kirim pagi hari',
        'status' => 'approve', 'total_amount' => 183208.83,
    ]);
    QuotationItem::create(['quotation_id' => $quotation->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'unit_price' => 8687, 'discount' => 5, 'tax' => 11, 'tax_type' => 'eksklusif']);
    expect($quotation->fresh()->created_by)->toBe($ctx['user']->id);                       // Fase 3: created_by selalu terisi

    Livewire::actingAs($ctx['user'])
        ->test(ViewQuotation::class, ['record' => $quotation->getKey()])
        ->assertActionHidden('edit')                                                        // Fase 3: Approved terkunci
        ->assertActionVisible('revise')
        ->callAction('create_sale_order', data: [
            'so_number' => 'SO-E2E-1', 'order_date' => now()->format('Y-m-d'), 'tipe_pengiriman' => 'Kirim Langsung',
            'shipped_to' => 'Gudang Klien, Bekasi', 'notes' => '',
        ]);

    $so = SaleOrder::withoutGlobalScopes()->where('so_number', 'SO-E2E-1')->firstOrFail();
    expect($so->status)->toBe('draft')                                                     // D10 (flag mati)
        ->and((int) $so->tempo_pembayaran)->toBe(0)                                         // tempo 0 dihormati, bukan tergeser ke 45
        ->and($so->shipped_to)->toBe('Gudang Klien, Bekasi')
        ->and($so->notes)->toBe('Kirim pagi hari')
        ->and(round((float) $so->total_amount, 2))->toBe(183208.83);                        // Fase 5B: satu perhitungan
    $soItem = $so->saleOrderItem()->firstOrFail();
    $so->update(['status' => 'approved']);

    // ── Fase 3: quotation yang sudah dipakai tetap terkunci; revisi lewat versi baru ──
    $revision = app(\App\Services\QuotationService::class)->createRevision($quotation->fresh());
    expect($revision->quotation_number)->toBe('QO-E2E-1-R1')->and($revision->status)->toBe('draft');

    // ── Fase 2 + 4: pengiriman parsial pertama (12 dari 20) ──
    [$do1, $sj1, $schedule1] = e2eDeliverPartial($ctx, $so, $soItem, 12, 'A');

    // Surat Jalan layak cetak (Fase 4): DO/SO, customer, alamat, ekspedisi + resi, per DO, tanpa harga
    $doc = app(SuratJalanDocumentBuilder::class)->build($sj1->fresh());
    expect($doc['delivery']['sender_name'])->toBe('JNE Cargo')
        ->and($doc['delivery']['tracking_number'])->toBe('RESI-A')
        ->and($doc['customers'])->toBe(['PT Alur Lengkap'])
        ->and($doc['addresses'])->toBe(['Gudang Klien, Bekasi'])
        ->and($doc['groups'][0]['items'][0])->toMatchArray(['quantity' => '12', 'unit' => 'pcs', 'note' => 'Rak A'])
        ->and(strtolower(json_encode($doc)))->not->toContain('unit_price');
    test()->actingAs($ctx['user'])->get(route('pdf-stream', ['type' => 'surat-jalan', 'id' => $sj1->id]))->assertOk();

    // SJ yang dipakai jadwal tidak dapat dibatalkan; DO yang sama tidak dapat dibuatkan SJ kedua
    expect(fn () => app(SuratJalanService::class)->cancel($sj1, 'Coba batalkan saat jadwal aktif'))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => app(SuratJalanService::class)->assertDeliveryOrdersUsable(collect([$do1->fresh()])))->toThrow(\Illuminate\Validation\ValidationException::class);

    $schedule1->update(['status' => 'on_the_way']);
    expect($do1->fresh()->status)->toBe('sent');
    $schedule1->update(['status' => 'delivered']);
    expect($do1->fresh()->status)->toBe('completed')
        ->and($so->fresh()->status)->toBe('partially_delivered');                           // Fase 2: status dari kuantitas terkirim

    $invoice1 = Invoice::whereJsonContains('delivery_orders', $do1->id)->firstOrFail();

    // ── Pengiriman kedua (8 sisanya) → SO selesai ──
    [$do2, $sj2, $schedule2] = e2eDeliverPartial($ctx, $so->fresh(), $soItem->fresh(), 8, 'B');
    $schedule2->update(['status' => 'on_the_way']);
    $schedule2->update(['status' => 'delivered']);
    expect($so->fresh()->status)->toBe('completed');
    $invoice2 = Invoice::whereJsonContains('delivery_orders', $do2->id)->firstOrFail();

    // ── Fase 5B: dua invoice per DO — rincian baku, dan jumlahnya = total SO ──
    $expected1 = LineAmounts::calculate(12, 8687, 5, 11, 'Eksklusif');
    $expected2 = LineAmounts::calculate(8, 8687, 5, 11, 'Eksklusif');
    $line1 = $invoice1->invoiceItem()->firstOrFail();
    expect((float) $line1->price)->toBe(8687.0)                                            // harga satuan GROSS
        ->and((float) $line1->gross_amount)->toBe($expected1['gross'])
        ->and((float) $line1->discount_amount)->toBe($expected1['discount_amount'])
        ->and((float) $line1->subtotal)->toBe($expected1['dpp'])
        ->and((float) $line1->tax_amount)->toBe($expected1['ppn'])
        ->and(round((float) $invoice1->total, 2))->toBe($expected1['total'])
        ->and(round((float) $invoice1->total + (float) $invoice2->total, 2))->toBe(round((float) $so->fresh()->total_amount, 2))   // 183.208,83
        ->and(round($expected1['dpp'] + $expected2['dpp'], 2))->toBe(165053.0);

    // ── Fase 6: snapshot HPP = jurnal HPP ──
    $cogsJournal = (float) JournalEntry::where('source_type', Invoice::class)->whereIn('source_id', [$invoice1->id, $invoice2->id])
        ->where('description', 'like', SalesReportService::COGS_DESCRIPTION_PREFIX . '%')->sum('debit');
    $snapshot = (float) $invoice1->invoiceItem()->sum('cogs_amount') + (float) $invoice2->invoiceItem()->sum('cogs_amount');
    expect($cogsJournal)->toBe(100000.0)->and($snapshot)->toBe(100000.0);                  // 20 × 5.000

    // ── Fase 5A: penerimaan invoice-1 dengan kelebihan → Deposit; cabang = cabang invoice (bukan cabang customer) ──
    $remaining1 = round((float) AccountReceivable::where('invoice_id', $invoice1->id)->value('remaining'), 2);
    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm([
            'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Transfer', 'payment_reference' => 'TRF-E2E-' . strtoupper(uniqid()), 'coa_id' => $ctx['bank']->id,
            'selected_invoices' => json_encode([$invoice1->id]), 'invoice_receipts' => json_encode([$invoice1->id => $remaining1 + 5000]),
            'total_payment' => (string) ($remaining1 + 5000), 'overpayment_as_deposit' => true, 'status' => 'Draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $receipt = CustomerReceipt::withoutGlobalScopes()->latest('id')->firstOrFail();
    $deposit = Deposit::firstOrFail();
    expect($receipt->cabang_id)->toBe($ctx['cabang']->id)                                   // bukan cabang customer
        ->and($ctx['customer']->cabang_id)->toBe($ctx['customerCabang']->id)
        ->and(round((float) $receipt->total_payment, 2))->toBe($remaining1)                 // tidak dipotong senyap: hanya yang teralokasi
        ->and((float) $receipt->overpayment_amount)->toBe(5000.0)
        ->and((float) $deposit->amount)->toBe(5000.0)
        ->and($deposit->id)->toBe($receipt->deposit_id)
        ->and((float) AccountReceivable::where('invoice_id', $invoice1->id)->value('remaining'))->toBe(0.0);

    // akun DEPOSITO tidak dapat dipakai sebagai akun penerima, dan kelebihan tanpa opsi ditolak
    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm([
            'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Transfer', 'payment_reference' => 'TRF-E2E-' . strtoupper(uniqid()), 'coa_id' => $ctx['bankDeposito']->id,
            'selected_invoices' => json_encode([$invoice2->id]), 'invoice_receipts' => json_encode([$invoice2->id => 1000]), 'total_payment' => '1000', 'status' => 'Draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['coa_id']);

    $receiptCount = CustomerReceipt::withoutGlobalScopes()->count();
    $remaining2 = round((float) AccountReceivable::where('invoice_id', $invoice2->id)->value('remaining'), 2);
    Livewire::actingAs($ctx['user'])
        ->test(CreateCustomerReceipt::class)
        ->fillForm([
            'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Transfer', 'payment_reference' => 'TRF-E2E-' . strtoupper(uniqid()), 'coa_id' => $ctx['bank']->id,
            'selected_invoices' => json_encode([$invoice2->id]), 'invoice_receipts' => json_encode([$invoice2->id => $remaining2 + 1]),
            'total_payment' => (string) ($remaining2 + 1), 'status' => 'Draft',
        ])
        ->call('create')
        ->assertHasFormErrors(['total_payment']);   // kelebihan Rp1 tanpa opsi deposit ditolak, tidak dipotong senyap
    expect(CustomerReceipt::withoutGlobalScopes()->count())->toBe($receiptCount)
        ->and(round((float) AccountReceivable::where('invoice_id', $invoice2->id)->value('remaining'), 2))->toBe($remaining2);

    // ── Fase 6: laporan mode Invoice — HPP, margin, status pembayaran, rekonsiliasi ──
    $service = app(SalesReportService::class);
    $filters = ['mode' => 'invoice', 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()];
    $rows = $service->invoiceQuery($filters)->get()->map(fn ($invoice) => $service->invoiceRow($invoice))->keyBy('invoice_number');
    $summary = $service->invoiceSummary($rows->values());

    expect($rows)->toHaveCount(2)
        ->and($rows[$invoice1->invoice_number]['payment_status'])->toBe('lunas')
        ->and($rows[$invoice2->invoice_number]['payment_status'])->toBe('belum')
        ->and($summary['total_dpp'])->toBe(165053.0)
        ->and($summary['total_hpp'])->toBe(100000.0)
        ->and($summary['total_margin'])->toBe(65053.0)
        ->and($summary['margin_pct'])->toBe(39.41)
        ->and(round($summary['total_amount'], 2))->toBe(183208.83)
        ->and($service->cogsReconciliation($filters['start_date'], $filters['end_date'])['reconciled'])->toBeTrue();

    // Surat Jalan: batalkan setelah jadwal selesai/gagal dibatasi; SJ yang dibatalkan dapat diterbitkan ulang bila DO masih approved (di sini DO sudah selesai → ditolak)
    $schedule1->update(['status' => 'cancelled']);
    app(SuratJalanService::class)->cancel($sj1->fresh(), 'Koreksi data setelah jadwal dibatalkan');
    expect($sj1->fresh()->status)->toBe(SuratJalan::STATUS_CANCELLED);
    expect(fn () => app(SuratJalanService::class)->reissue($sj1->fresh()))->toThrow(\Illuminate\Validation\ValidationException::class);
});
