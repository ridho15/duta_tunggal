<?php

/**
 * Fase 6 audit 10 bug sedang penjualan (docs/AUDIT-10-BUG-SEDANG-PENJUALAN.md), Isu 9:
 *  - tiga mode laporan (Invoice default / Pengiriman / Pesanan), tanggal mengikuti dokumen
 *  - filter status dari konstanta model (tidak ada status fiktif)
 *  - snapshot HPP per baris invoice ditulis dari perhitungan yang sama dengan jurnal HPP
 *  - HPP, margin, status pembayaran; rekonsiliasi total HPP laporan = total jurnal HPP
 *  - ekspor Excel/PDF mengikuti kolom baru; widget "SO Belum Selesai" hanya SO yang benar-benar berjalan
 */

use App\Exports\SalesReportExport;
use App\Filament\Pages\SalesReportPage;
use App\Filament\Widgets\SoBelumSelesaiTable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\SalesReportService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

function p6Context(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'P6-' . strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang 6', 'status' => 1]);
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    Auth::login($user);

    $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id, 'kode' => 'G-' . strtoupper(substr(uniqid(), -5)), 'status' => 1]);

    ChartOfAccount::factory()->create(['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset']);
    $revenue = ChartOfAccount::factory()->create(['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue']);
    ChartOfAccount::factory()->create(['code' => '2120.06', 'name' => 'PPn Keluaran', 'type' => 'Liability']);
    $cogs = ChartOfAccount::factory()->create(['code' => '5100.10', 'name' => 'HPP', 'type' => 'Expense']);
    $goods = ChartOfAccount::factory()->create(['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset']);
    $inventory = ChartOfAccount::factory()->create(['code' => '1140.01', 'name' => 'Persediaan', 'type' => 'Asset']);

    $customer = Customer::factory()->create(['cabang_id' => $cabang->id, 'name' => 'PT Laporan Enam', 'tempo_kredit' => 30, 'tipe_pembayaran' => 'Bebas']);
    $product = Product::factory()->create([
        'sku' => 'P6-' . strtoupper(substr(uniqid(), -6)), 'name' => 'Alat Medis 6', 'cost_price' => 5000, 'sell_price' => 8687,
        'sales_coa_id' => $revenue->id, 'cogs_coa_id' => $cogs->id, 'goods_delivery_coa_id' => $goods->id, 'inventory_coa_id' => $inventory->id,
    ]);

    return compact('cabang', 'user', 'currency', 'warehouse', 'customer', 'product');
}

/** SO Approved 20 × 8.687, diskon 5%, PPN 11% eksklusif: DPP 165.053 · PPN 18.155,83 · total 183.208,83. */
function p6SaleOrder(array $ctx, array $so = []): array
{
    $order = SaleOrder::create(array_merge([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-6-' . strtoupper(substr(uniqid(), -6)),
        'order_date' => now(), 'status' => 'approved', 'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['currency']->id,
        'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'Jl. Uji 6',
    ], $so));

    $item = SaleOrderItem::create([
        'sale_order_id' => $order->id, 'product_id' => $ctx['product']->id, 'quantity' => 20, 'delivered_quantity' => 0,
        'unit_price' => 8687, 'discount' => 5, 'tax' => 11, 'tipe_pajak' => 'Eksklusif',
        'currency_id' => $ctx['currency']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    app(SalesOrderService::class)->updateTotalAmount($order->fresh());

    return [$order->fresh(), $item];
}

/** DO completed → invoice otomatis + jurnal (termasuk HPP 20 × cost_price). */
function p6Delivery(array $ctx, SaleOrder $so, SaleOrderItem $soItem, string $finalStatus = 'completed'): array
{
    $do = DeliveryOrder::create([
        'do_number' => 'DO-6-' . strtoupper(substr(uniqid(), -6)), 'delivery_date' => now(), 'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    $do->salesOrders()->attach($so->id);
    $doItem = DeliveryOrderItem::create(['delivery_order_id' => $do->id, 'sale_order_item_id' => $soItem->id, 'product_id' => $ctx['product']->id, 'quantity' => 20]);
    DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 20]);

    foreach (match ($finalStatus) { 'sent' => ['sent'], 'completed' => ['sent', 'completed'], default => [] } as $status) {
        $do->update(['status' => $status]);
    }

    $invoice = Invoice::whereJsonContains('delivery_orders', $do->id)->first();

    return [$do->fresh(), $invoice];
}

function p6Invoice(array $ctx): Invoice
{
    [$so, $soItem] = p6SaleOrder($ctx);

    return p6Delivery($ctx, $so, $soItem)[1];
}

function p6Filters(array $extra = []): array
{
    return array_merge(['mode' => 'invoice', 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()], $extra);
}

// ───────────────────────────── 9.2 Snapshot HPP ─────────────────────────────

it('Isu 9: snapshot HPP per baris ditulis saat jurnal HPP diposting, dengan angka yang sama dengan jurnal', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);
    $item = $invoice->invoiceItem()->firstOrFail();

    $journalCogs = (float) JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)
        ->where('description', 'like', SalesReportService::COGS_DESCRIPTION_PREFIX . '%')->sum('debit');

    expect((float) $item->cost_price)->toBe(5000.0)
        ->and((float) $item->cogs_amount)->toBe(100000.0)          // 20 × 5.000
        ->and($item->cogs_source)->toBe(InvoiceItem::COGS_SOURCE_JOURNAL)
        ->and($journalCogs)->toBe(100000.0)
        ->and((float) $item->cogs_amount)->toBe($journalCogs);
});

it('Isu 9: HPP laporan tidak berubah walau cost_price master berubah kemudian', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);

    $ctx['product']->update(['cost_price' => 9000]);
    $row = app(SalesReportService::class)->invoiceRow($invoice->fresh());

    expect($row['hpp'])->toBe(100000.0)
        ->and($row['hpp_estimated'])->toBeFalse()
        ->and($row['dpp'])->toBe(165053.0)
        ->and($row['margin'])->toBe(65053.0)
        ->and($row['margin_pct'])->toBe(39.41);
});

// ───────────────────────────── 9.6 Rekonsiliasi ─────────────────────────────

it('Isu 9: rekonsiliasi — total HPP laporan = total jurnal HPP pada periode yang sama', function () {
    $ctx = p6Context();
    p6Invoice($ctx);
    p6Invoice($ctx);

    $result = app(SalesReportService::class)->cogsReconciliation(now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($result['journal'])->toBe(200000.0)
        ->and($result['report'])->toBe(200000.0)
        ->and($result['difference'])->toBe(0.0)
        ->and($result['reconciled'])->toBeTrue()
        ->and($result['invoices'])->toBe(2)
        ->and($result['estimated_lines'])->toBe(0);

    // dan total HPP pada ringkasan laporan invoice sama dengan itu
    $rows = app(SalesReportService::class)->invoiceQuery(p6Filters())->get()->map(fn ($i) => app(SalesReportService::class)->invoiceRow($i));
    expect(app(SalesReportService::class)->invoiceSummary($rows)['total_hpp'])->toBe(200000.0);
});

it('Isu 9: invoice lama tanpa snapshot dihitung ESTIMASI dan ditandai; selisih terhadap jurnal terlihat lalu hilang setelah backfill', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);

    // keadaan lama: tanpa snapshot, dan cost_price master sudah berubah setelah invoice diposting
    DB::table('invoice_items')->where('invoice_id', $invoice->id)->update(['cost_price' => null, 'cogs_amount' => null, 'cogs_source' => null]);
    $ctx['product']->update(['cost_price' => 9000]);

    $service = app(SalesReportService::class);
    $row = $service->invoiceRow($invoice->fresh());
    $recon = $service->cogsReconciliation(now()->startOfMonth()->toDateString(), now()->toDateString());

    expect($row['hpp'])->toBe(180000.0)                  // 20 × 9.000 (estimasi dari master saat ini)
        ->and($row['hpp_estimated'])->toBeTrue()
        ->and($recon['reconciled'])->toBeFalse()
        ->and($recon['difference'])->toBe(80000.0)
        ->and($recon['estimated_lines'])->toBe(1);

    // dry-run tidak mengubah apa pun
    Artisan::call('invoices:backfill-cogs');
    expect(Artisan::output())->toContain('DRY-RUN')
        ->and(InvoiceItem::whereNull('cogs_amount')->count())->toBe(1);

    Artisan::call('invoices:backfill-cogs', ['--apply' => true]);
    $item = $invoice->invoiceItem()->firstOrFail();

    // satu baris + jurnal HPP → angka jurnal persis (bukan estimasi 180.000)
    expect((float) $item->cogs_amount)->toBe(100000.0)
        ->and($item->cogs_source)->toBe(InvoiceItem::COGS_SOURCE_JOURNAL);

    $service2 = new SalesReportService();
    expect($service2->cogsReconciliation(now()->startOfMonth()->toDateString(), now()->toDateString())['reconciled'])->toBeTrue()
        ->and($service2->invoiceRow($invoice->fresh())['hpp_estimated'])->toBeFalse();

    // idempoten
    Artisan::call('invoices:backfill-cogs', ['--apply' => true]);
    expect(Artisan::output())->toContain('Tidak ada baris yang perlu dilengkapi');

    array_map('unlink', glob(storage_path('app/backfill/invoice-cogs-*.csv')) ?: []);
});

it('Isu 9: backfill memakai nilai stok DO untuk invoice multi-baris dan tidak menyentuh kolom lain', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);
    $before = DB::table('invoice_items')->where('invoice_id', $invoice->id)->first();

    DB::table('invoice_items')->where('invoice_id', $invoice->id)->update(['cost_price' => null, 'cogs_amount' => null, 'cogs_source' => null]);
    // dua baris agar aturan "satu baris = angka jurnal" tidak berlaku
    InvoiceItem::withoutEvents(fn () => InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 0, 'price' => 0, 'discount' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'subtotal' => 0, 'total' => 0,
    ]));

    Artisan::call('invoices:backfill-cogs', ['--apply' => true]);
    $item = $invoice->invoiceItem()->where('quantity', 20)->firstOrFail();

    expect($item->cogs_source)->toBeIn([InvoiceItem::COGS_SOURCE_STOCK, InvoiceItem::COGS_SOURCE_ESTIMATE])
        ->and((float) $item->cogs_amount)->toBe(100000.0)     // nilai stok DO = 20 × 5.000 (atau estimasi sama karena cost_price belum berubah)
        ->and((float) $item->price)->toBe((float) $before->price)
        ->and((float) $item->total)->toBe((float) $before->total);

    array_map('unlink', glob(storage_path('app/backfill/invoice-cogs-*.csv')) ?: []);
});

// ───────────────────────────── 9.1 Mode & tanggal ─────────────────────────────

it('Isu 9: default laporan = Invoice; tanggal mengikuti invoice_date (bukan created_at); draft tidak dihitung', function () {
    $ctx = p6Context();
    $inRange = p6Invoice($ctx);
    $old = p6Invoice($ctx);
    $draft = p6Invoice($ctx);

    Invoice::withoutEvents(function () use ($old, $draft) {
        $old->update(['invoice_date' => now()->subMonths(2)->toDateString(), 'created_at' => now()]);   // dibuat hari ini, tetapi tanggal dokumen 2 bulan lalu
        $draft->update(['status' => 'draft']);
    });

    expect(SalesReportService::resolveMode([]))->toBe('invoice');

    $numbers = app(SalesReportService::class)->query(['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()])->pluck('invoice_number')->all();
    expect($numbers)->toBe([$inRange->invoice_number]);
});

it('Isu 9: mode Pesanan memakai order_date; mode Pengiriman memakai delivery_date dan hanya DO yang sudah keluar gudang', function () {
    $ctx = p6Context();
    [$soIn] = p6SaleOrder($ctx, ['so_number' => 'SO-6-IN', 'order_date' => now()]);
    [$soOut] = p6SaleOrder($ctx, ['so_number' => 'SO-6-OUT', 'order_date' => now()->subMonths(2)]);
    SaleOrder::withoutEvents(fn () => $soOut->update(['created_at' => now()]));     // dicatat hari ini, tanggal SO 2 bulan lalu

    $service = app(SalesReportService::class);
    $orders = $service->query(p6Filters(['mode' => 'order']))->pluck('so_number')->all();
    expect($orders)->toContain('SO-6-IN')->not->toContain('SO-6-OUT');

    [$soDel, $soDelItem] = p6SaleOrder($ctx);
    [$sentDo] = p6Delivery($ctx, $soDel, $soDelItem, 'sent');
    [$draftSo, $draftSoItem] = p6SaleOrder($ctx);
    [$draftDo] = p6Delivery($ctx, $draftSo, $draftSoItem, 'draft');

    $deliveries = $service->query(p6Filters(['mode' => 'delivery']))->pluck('do_number')->all();
    expect($deliveries)->toContain($sentDo->do_number)->not->toContain($draftDo->do_number);

    // dengan filter status eksplisit, DO draft dapat dilihat
    expect($service->query(p6Filters(['mode' => 'delivery', 'status' => 'draft']))->pluck('do_number')->all())->toBe([$draftDo->do_number]);
});

// ───────────────────────────── 9.4 Status dari konstanta ─────────────────────────────

it('Isu 9: opsi status dari konstanta model — semua status nyata SO tersedia, tidak ada "processing"/"cancelled" fiktif', function () {
    $orderOptions = SalesReportService::statusOptions('order');

    expect(array_keys($orderOptions))->toBe(array_keys(SaleOrder::STATUS_LABELS))
        ->and($orderOptions)->toHaveKeys(['approved', 'partially_delivered', 'request_approve', 'closed', 'reject', 'canceled'])
        ->and($orderOptions)->not->toHaveKeys(['processing', 'cancelled'])
        ->and($orderOptions['approved'])->toBe('Disetujui');

    expect(SalesReportService::statusOptions('delivery'))->toBe(DeliveryOrder::STATUS_LABELS)
        ->and(array_keys(SalesReportService::statusOptions('invoice')))->toBe(['belum', 'sebagian', 'lunas', 'jatuh_tempo']);
});

it('Isu 9: ringkasan status SO menghitung status nyata (SO Disetujui tidak lagi hilang dari kartu status)', function () {
    $ctx = p6Context();
    p6SaleOrder($ctx, ['status' => 'approved']);
    p6SaleOrder($ctx, ['status' => 'approved']);
    p6SaleOrder($ctx, ['status' => 'partially_delivered']);
    p6SaleOrder($ctx, ['status' => 'canceled']);

    $orders = app(SalesReportService::class)->query(p6Filters(['mode' => 'order']))->get();
    $summary = app(SalesReportService::class)->summary($orders);

    expect($summary['status_counts']['approved'])->toBe(2)
        ->and($summary['status_counts']['partially_delivered'])->toBe(1)
        ->and($summary['status_counts']['canceled'])->toBe(1)
        ->and($summary['status_counts']['cancelled'])->toBe(1);      // alias kunci lama
});

// ───────────────────────────── 9.3 Status pembayaran ─────────────────────────────

it('Isu 9: status pembayaran turunan dari AR — Belum Bayar / Sebagian / Lunas / Jatuh Tempo, dan filternya sesuai', function () {
    $ctx = p6Context();
    $unpaid = p6Invoice($ctx);
    $partial = p6Invoice($ctx);
    $paid = p6Invoice($ctx);
    $overdue = p6Invoice($ctx);

    AccountReceivable::where('invoice_id', $partial->id)->update(['paid' => 50000, 'remaining' => 133208.83]);
    AccountReceivable::where('invoice_id', $paid->id)->update(['paid' => 183208.83, 'remaining' => 0]);
    Invoice::withoutEvents(fn () => $overdue->update(['due_date' => now()->subDays(5)->toDateString()]));

    $service = app(SalesReportService::class);
    expect($service->paymentStatus($unpaid->fresh()))->toBe('belum')
        ->and($service->paymentStatus($partial->fresh()))->toBe('sebagian')
        ->and($service->paymentStatus($paid->fresh()))->toBe('lunas')
        ->and($service->paymentStatus($overdue->fresh()))->toBe('jatuh_tempo');

    foreach ([['belum', $unpaid], ['sebagian', $partial], ['lunas', $paid], ['jatuh_tempo', $overdue]] as [$status, $expected]) {
        expect($service->query(p6Filters(['status' => $status]))->pluck('invoice_number')->all())
            ->toBe([$expected->invoice_number], "filter {$status}");
    }

    $rows = $service->invoiceQuery(p6Filters())->get()->map(fn ($i) => $service->invoiceRow($i));
    $summary = $service->invoiceSummary($rows);
    expect($summary['payment_counts'])->toBe(['belum' => 1, 'sebagian' => 1, 'lunas' => 1, 'jatuh_tempo' => 1])
        ->and(round($summary['outstanding'], 2))->toBe(round(183208.83 + 133208.83 + 0 + 183208.83, 2));
});

// ───────────────────────────── 9.3 Kolom & ringkasan ─────────────────────────────

it('Isu 9: baris laporan invoice memuat DPP, PPN, total, HPP, margin, margin %, SO, DO, customer, cabang', function () {
    $ctx = p6Context();
    [$so, $soItem] = p6SaleOrder($ctx, ['so_number' => 'SO-6-KOLOM']);
    [$do, $invoice] = p6Delivery($ctx, $so, $soItem);

    $row = app(SalesReportService::class)->invoiceRow($invoice->fresh());

    expect($row)->toMatchArray([
        'invoice_number' => $invoice->invoice_number, 'customer_name' => 'PT Laporan Enam', 'so_number' => 'SO-6-KOLOM',
        'dpp' => 165053.0, 'total' => 183208.83, 'hpp' => 100000.0, 'margin' => 65053.0, 'margin_pct' => 39.41,
        'payment_status' => 'belum', 'branch' => 'Cabang 6', 'currency' => 'IDR',
    ])->and($row['ppn'])->toBe(18155.83)
        ->and($row['do_numbers'])->toBe([$do->do_number])
        ->and($row['lines'])->toHaveCount(1)
        ->and($row['lines'][0])->toMatchArray(['quantity' => 20.0, 'unit_price' => 8687.0, 'discount_pct' => 5.0, 'discount_amount' => 8687.0, 'hpp' => 100000.0]);
});

it('Isu 9: ringkasan invoice — total DPP/PPN/HPP/margin dan rincian per mata uang', function () {
    $ctx = p6Context();
    p6Invoice($ctx);
    p6Invoice($ctx);

    $service = app(SalesReportService::class);
    $rows = $service->invoiceQuery(p6Filters())->get()->map(fn ($i) => $service->invoiceRow($i));
    $summary = $service->invoiceSummary($rows);

    expect($summary['total_invoices'])->toBe(2)
        ->and($summary['total_dpp'])->toBe(330106.0)
        ->and($summary['total_hpp'])->toBe(200000.0)
        ->and($summary['total_margin'])->toBe(130106.0)
        ->and($summary['margin_pct'])->toBe(39.41)
        ->and(round($summary['total_amount'], 2))->toBe(366417.66)
        ->and($summary['per_currency']['IDR']['invoices'])->toBe(2)
        ->and($summary['estimated_invoices'])->toBe(0);
});

// ───────────────────────────── 9.7 Ekspor ─────────────────────────────

it('Isu 9: ekspor Excel mode invoice — kolom sesuai rencana, satu baris per baris invoice, TOTAL = ringkasan', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);
    $service = app(SalesReportService::class);

    $export = new SalesReportExport($service->query(p6Filters()));
    $headings = $export->headings();

    expect($headings)->toBe([
        'No. Invoice', 'Tanggal', 'Kode Customer', 'Nama Customer', 'No. SO', 'No. DO', 'Produk', 'Qty', 'Harga Satuan',
        'Diskon (%)', 'Diskon (Rp)', 'DPP', 'PPN', 'Total', 'HPP', 'Basis HPP', 'Margin (Rp)', 'Margin (%)',
        'Status Pembayaran', 'Cabang', 'Mata Uang',
    ]);

    $collection = $export->collection();
    $line = $collection->first();
    $total = $collection->last();

    expect($collection)->toHaveCount(2)
        ->and($line[0])->toBe($invoice->invoice_number)
        ->and($line[3])->toBe('PT Laporan Enam')
        ->and($line[7])->toBe(20.0)->and($line[8])->toBe(8687.0)->and($line[9])->toBe(5.0)->and($line[10])->toBe(8687.0)
        ->and($line[11])->toBe(165053.0)->and($line[12])->toBe(18155.83)->and($line[13])->toBe(183208.83)
        ->and($line[14])->toBe(100000.0)->and($line[15])->toBe('Snapshot jurnal')
        ->and($line[16])->toBe(65053.0)->and($line[17])->toBe(39.41)->and($line[18])->toBe('Belum Bayar')
        ->and($total[0])->toBe('TOTAL')->and($total[11])->toBe(165053.0)->and($total[14])->toBe(100000.0)->and($total[16])->toBe(65053.0);

    // berkas .xlsx benar-benar dapat dibuat (gaya, lebar kolom)
    expect(strlen(Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX)))->toBeGreaterThan(1000);
});

it('Isu 9: ekspor Excel mode pengiriman dan pesanan tetap dapat dibuat dan memakai kolomnya sendiri', function () {
    $ctx = p6Context();
    [$so, $soItem] = p6SaleOrder($ctx);
    [$do] = p6Delivery($ctx, $so, $soItem, 'sent');
    $service = app(SalesReportService::class);

    $delivery = new SalesReportExport($service->query(p6Filters(['mode' => 'delivery'])));
    expect($delivery->headings())->toContain('No. DO', 'HPP (Stok)', 'Margin (%)')
        ->and($delivery->collection()->first()[0])->toBe($do->do_number)
        ->and(strlen(Excel::raw($delivery, \Maatwebsite\Excel\Excel::XLSX)))->toBeGreaterThan(1000);

    $order = new SalesReportExport($service->query(p6Filters(['mode' => 'order'])));
    expect($order->headings())->toContain('No. SO', 'Total SO', 'Status')
        ->and($order->collection()->pluck('Status')->filter()->first())->toBe(SaleOrder::statusLabel($so->fresh()->status))
        ->and(strlen(Excel::raw($order, \Maatwebsite\Excel\Excel::XLSX)))->toBeGreaterThan(1000);
});

it('Isu 9: PDF laporan invoice memuat kolom baru, ringkasan HPP/margin, rekonsiliasi, dan catatan estimasi', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);
    $service = app(SalesReportService::class);

    $payload = $service->pdfPayload(p6Filters());
    $html = view('reports.sales_report_modes', array_merge($payload, ['start_date' => p6Filters()['start_date'], 'end_date' => p6Filters()['end_date']]))->render();

    expect($payload['columns'])->toBe(['No. Invoice', 'Tanggal', 'Customer', 'No. SO', 'DPP', 'PPN', 'Total', 'HPP', 'Margin', 'Margin %', 'Pembayaran'])
        ->and($html)->toContain($invoice->invoice_number)->toContain('Rp 165.053,00')->toContain('Rp 100.000,00')->toContain('39,41%')
        ->toContain('Total HPP')->toContain('Rekonsiliasi HPP dengan Jurnal')->toContain('Sesuai')
        ->not->toContain('*');

    // invoice tanpa snapshot → HPP ditandai estimasi + catatan kaki
    DB::table('invoice_items')->where('invoice_id', $invoice->id)->update(['cogs_amount' => null, 'cogs_source' => null]);
    $estimated = (new SalesReportService())->pdfPayload(p6Filters());
    expect($estimated['rows'][0][7])->toContain('*')->and($estimated['footnote'])->toContain('estimasi');
});

// ───────────────────────────── Halaman laporan ─────────────────────────────

it('Isu 9: halaman laporan — default Invoice, menampilkan HPP/margin/pembayaran, dan ringkasan periode', function () {
    $ctx = p6Context();
    $invoice = p6Invoice($ctx);

    Livewire::actingAs($ctx['user'])
        ->test(SalesReportPage::class)
        ->assertSet('mode', 'invoice')
        ->assertSee($invoice->invoice_number)
        ->assertSee('PT Laporan Enam')
        ->assertSee('Rp 100.000,00')          // HPP
        ->assertSee('Rp 65.053,00')           // margin
        ->assertSee('39,41%')
        ->assertSee('Belum Bayar')
        ->assertSee('Total HPP')
        ->assertSee('Rekonsiliasi HPP dengan Jurnal');
});

it('Isu 9: satu instance SalesReportService per request — cache baris tidak dibuat ulang tiap sel', function () {
    $ctx = p6Context();

    $page = Livewire::actingAs($ctx['user'])->test(SalesReportPage::class)->instance();
    $method = new ReflectionMethod($page, 'salesReportService');

    expect($method->invoke($page))->toBe($method->invoke($page));
});

it('Isu 9: mengganti mode mereset filter status; opsi status berasal dari konstanta mode aktif', function () {
    $ctx = p6Context();
    p6SaleOrder($ctx, ['so_number' => 'SO-6-MODE', 'status' => 'approved']);

    Livewire::actingAs($ctx['user'])
        ->test(SalesReportPage::class)
        ->set('mode', 'order')
        ->set('status', 'approved')
        ->assertSee('SO-6-MODE')
        ->set('mode', 'delivery')
        ->assertSet('status', null)
        ->assertDontSee('SO-6-MODE');
});

it('Isu 9: mode Pengiriman di halaman menampilkan DO, HPP stok, dan margin', function () {
    $ctx = p6Context();
    [$so, $soItem] = p6SaleOrder($ctx);
    [$do] = p6Delivery($ctx, $so, $soItem, 'sent');

    Livewire::actingAs($ctx['user'])
        ->test(SalesReportPage::class)
        ->set('mode', 'delivery')
        ->assertSee($do->do_number)
        ->assertSee('HPP (Stok)')
        ->assertSee('Sedang Dikirim');
});

// ───────────────────────────── Widget SO Belum Selesai ─────────────────────────────

it('Isu 9: widget "SO Belum Selesai" hanya menampilkan SO yang masih berjalan', function () {
    $ctx = p6Context();
    [$approved] = p6SaleOrder($ctx, ['status' => 'approved']);
    [$partial] = p6SaleOrder($ctx, ['status' => 'partially_delivered']);
    [$draft] = p6SaleOrder($ctx, ['status' => 'draft']);
    [$waiting] = p6SaleOrder($ctx, ['status' => 'request_approve']);
    [$completed] = p6SaleOrder($ctx, ['status' => 'completed']);
    [$closed] = p6SaleOrder($ctx, ['status' => 'closed']);
    [$canceled] = p6SaleOrder($ctx, ['status' => 'canceled']);
    [$rejected] = p6SaleOrder($ctx, ['status' => 'reject']);

    Livewire::actingAs($ctx['user'])
        ->test(SoBelumSelesaiTable::class)
        ->assertCanSeeTableRecords([$approved, $partial])
        ->assertCanNotSeeTableRecords([$draft, $waiting, $completed, $closed, $canceled, $rejected]);
});
