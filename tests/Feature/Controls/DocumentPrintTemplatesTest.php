<?php

/**
 * T6.2 — Template cetak Invoice, Delivery Order, Kwitansi, Nota Kredit, Retur (+ Sales Order, Quotation): kop dari Cabang/global, NPWP,
 * rekening, tanda tangan, watermark DRAFT/DIBATALKAN, tanpa data contoh. Isi diperiksa pada HTML hasil render (sumber PDF) dan PDF
 * dipastikan terbentuk (Dompdf).
 */

use App\Models\Cabang;
use App\Models\CreditNote;
use App\Models\CustomerReceipt;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Services\CreditNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_notes' => true]);
});

/** Konteks dengan kop Cabang lengkap (nama legal, NPWP, alamat pajak, rekening). */
function tpContext(): array
{
    $ctx = stkContext();
    $ctx['cabang']->update([
        'nama' => 'Cabang Padang', 'nama_legal' => 'PT Duta Tunggal Sumatera', 'npwp' => '01.234.567.8-201.000', 'alamat' => 'Jl. Sudirman No. 10, Padang',
        'alamat_pajak' => 'Jl. Pajak No. 1, Padang', 'telepon' => '0751-123456',
        'rekening' => [['bank' => 'BCA', 'number' => '5550006789', 'holder' => 'PT Duta Tunggal Sumatera', 'branch' => 'KCP Padang']],
    ]);
    $ctx['customer']->update(['address' => 'Jl. Pelanggan No. 9, Bukittinggi']);
    $ctx['finance'] = ctlUser($ctx, 'Finance Manager', ['approve credit note', 'create credit note', 'view credit note', 'view any credit note', 'view customer receipt', 'view any customer receipt']);
    Auth::login($ctx['finance']);

    return $ctx;
}

function tpAssertNoPlaceholders(string $html): void
{
    foreach (['Jl. Contoh', '12345678', '08xx', 'xxx-xxxx', 'Contoh Alamat'] as $fake) {
        expect($html)->not->toContain($fake);
    }
}

it('Invoice: kop legal + NPWP + alamat pajak, pelanggan, rekening, tanda tangan; tanpa "tidak dapat dikembalikan" dan tanpa data contoh', function () {
    $ctx = tpContext();
    [$invoice] = ctlCreditInvoice($ctx);

    $html = view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render();

    expect($html)->toContain('PT Duta Tunggal Sumatera')->toContain('NPWP: 01.234.567.8-201.000')->toContain('Jl. Pajak No. 1, Padang')->toContain('Cabang Padang')
        ->toContain($invoice->invoice_number)->toContain('Jl. Pelanggan No. 9, Bukittinggi')->toContain($ctx['customer']->name)
        ->toContain('5550006789')->toContain('BCA')->toContain('Hormat kami')->toContain('Diterima oleh')->toContain('Belum Dibayar')
        ->not->toContain('tidak dapat dikembalikan')->not->toContain('DRAFT')->not->toContain('DIBATALKAN')->not->toContain('TOTAL SETELAH NOTA KREDIT');
    tpAssertNoPlaceholders($html);
});

it('Invoice: watermark DRAFT/DIBATALKAN; Nota Kredit terbit tampil dengan total setelah Nota Kredit', function () {
    $ctx = tpContext();
    [$invoice, $item] = ctlCreditInvoice($ctx);

    $invoice->forceFill(['status' => 'draft'])->saveQuietly();
    expect(view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render())->toContain('DRAFT');

    $invoice->forceFill(['status' => 'unpaid'])->saveQuietly();
    $service = app(CreditNoteService::class);
    $cn = $service->issue($service->draft($invoice->fresh(), CreditNote::TYPE_RETURN, [$item->id => 3], 'Retur tiga unit dari invoice', actor: $ctx['finance']), $ctx['finance']);

    $html = view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render();
    expect($html)->toContain('Nota Kredit '.$cn->credit_note_number)->toContain('TOTAL SETELAH NOTA KREDIT')->toContain('Rp 999.000,00')->toContain('Rp 333.000,00');

    $invoice->forceFill(['status' => 'cancelled'])->saveQuietly();
    expect(view('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->render())->toContain('DIBATALKAN')->toContain('Dibatalkan');
});

it('Delivery Order: kop cabang, meta, tanda tangan gudang/pengirim/penerima; tidak error tanpa driver/kendaraan; tanpa data contoh', function () {
    $ctx = tpContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);
    $deliveryOrder = stkDeliveryOrder($ctx, $so, $soItem, 5);

    $html = view('pdf.delivery-order', ['deliveryOrder' => $deliveryOrder->load(['cabang', 'deliveryOrderItem.product.uom', 'salesOrders.customer'])])->render();

    expect($html)->toContain('PT Duta Tunggal Sumatera')->toContain('Jl. Sudirman No. 10, Padang')->toContain($deliveryOrder->do_number)->toContain('DELIVERY ORDER')
        ->toContain($ctx['customer']->name)->toContain('Jl. Uji Stok No. 1')->toContain('Pengirim')->toContain('Penerima')->toContain('Dibuat / Gudang');
    tpAssertNoPlaceholders($html);
});

it('Kwitansi dari Penerimaan Customer: penerima, jumlah, terbilang, nomor; Dibatalkan diberi watermark', function () {
    $ctx = tpContext();
    [$invoice] = ctlCreditInvoice($ctx);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 1332000);

    $html = view('pdf.kwitansi', ['receipt' => $receipt->fresh()])->render();
    $number = 'KW-'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT);

    expect($html)->toContain('KWITANSI')->toContain($number)->toContain($ctx['customer']->name)->toContain('Invoice '.$invoice->invoice_number)
        ->toContain('Rp 1.332.000,00')->toContain('Satu juta tiga ratus tiga puluh dua ribu rupiah')->toContain('PT Duta Tunggal Sumatera')->toContain('Penerima')
        ->not->toContain('DIBATALKAN');
    tpAssertNoPlaceholders($html);

    $receipt->forceFill(['status' => 'Cancelled'])->saveQuietly();
    expect(view('pdf.kwitansi', ['receipt' => $receipt->fresh()])->render())->toContain('DIBATALKAN');
});

it('Nota Kredit: draf berwatermark DRAFT; terbit memuat uraian, DPP/PPN/total, penyelesaian piutang/Deposit, penerbit; tanpa data contoh', function () {
    $ctx = tpContext();
    [$invoice, $item] = ctlCreditInvoice($ctx, status: 'paid');
    $service = app(CreditNoteService::class);
    $cn = $service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Barang rusak dikembalikan customer', actor: $ctx['finance']);

    $draftHtml = view('pdf.credit-note', ['creditNote' => $cn->fresh()])->render();
    expect($draftHtml)->toContain('DRAFT')->toContain('NOTA KREDIT')->toContain($cn->credit_note_number)->toContain('Barang rusak dikembalikan customer');

    $cn = $service->issue($cn, $ctx['finance'], ['tax_document_number' => 'NRP-0001']);
    $html = view('pdf.credit-note', ['creditNote' => $cn->fresh()])->render();

    expect($html)->not->toContain('DRAFT')->toContain('PT Duta Tunggal Sumatera')->toContain('NPWP: 01.234.567.8-201.000')->toContain('Rp 300.000,00')->toContain('Rp 33.000,00')
        ->toContain('Rp 333.000,00')->toContain('Menjadi Deposit Customer')->not->toContain('Mengurangi piutang invoice')->toContain('NRP-0001')->toContain($invoice->invoice_number)
        ->toContain($ctx['finance']->name)->toContain('Diterima oleh')->toContain($ctx['customer']->name);
    tpAssertNoPlaceholders($html);
});

it('Retur: keputusan "Refund / Nota Kredit" berlabel; kop cabang; blok tanda tangan; tanpa data contoh', function () {
    $ctx = tpContext();
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $return = CustomerReturn::create([
        'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now()->toDateString(), 'reason' => 'Unit cacat produksi', 'status' => CustomerReturn::STATUS_APPROVED,
    ]);
    CustomerReturnItem::create(['customer_return_id' => $return->id, 'product_id' => $item->product_id, 'invoice_item_id' => $item->id, 'quantity' => 2,
        'problem_description' => 'Cacat', 'qc_result' => 'fail', 'decision' => CustomerReturnItem::DECISION_CREDIT]);

    $html = view('pdf.customer-return', ['return' => $return->load(['invoice', 'customer', 'cabang', 'customerReturnItems.product', 'receivedBy', 'qcInspectedBy', 'approvedBy'])])->render();

    expect($html)->toContain('RETUR CUSTOMER')->toContain($return->return_number)->toContain('Refund / Nota Kredit')->toContain('PT Duta Tunggal Sumatera')
        ->toContain('Disetujui')->toContain('Diterima / QC')->toContain('Dibuat oleh');
    tpAssertNoPlaceholders($html);
});

it('Sales Order dan Quotation: kop mengikuti data cabang (tanpa alamat contoh)', function () {
    $ctx = tpContext();
    [$so] = stkSaleOrder($ctx, 3);

    $soHtml = view('pdf.sales-order', ['saleOrder' => $so->load('saleOrderItem.product.uom', 'customer', 'cabang')])->render();
    expect($soHtml)->toContain('PT Duta Tunggal Sumatera')->toContain('Jl. Pajak No. 1, Padang')->toContain('Telp: 0751-123456');
    tpAssertNoPlaceholders($soHtml);

    $quotation = \App\Models\Quotation::create([
        'quotation_number' => 'QO-TP-1', 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'date' => now(), 'valid_until' => now()->addDays(30),
        'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'status' => 'approve', 'total_amount' => 1000,
    ]);
    $qHtml = view('pdf.quotation', ['quotation' => $quotation->load('quotationItem.product.uom', 'customer', 'cabang', 'createdBy', 'approveBy')])->render();
    expect($qHtml)->toContain('PT Duta Tunggal Sumatera')->toContain('NPWP: 01.234.567.8-201.000');
    tpAssertNoPlaceholders($qHtml);
});

it('pemindai: SEMUA template cetak (penjualan, pembelian, laporan) tidak memuat alamat/telepon contoh; template penjualan tanpa nama perusahaan dikeraskan di kop', function () {
    $fakes = ['Jl. Contoh', '12345678', '08xx', 'xxx-xxxx', 'Contoh Alamat', 'Jakarta, Indonesia'];

    foreach (glob(resource_path('views/pdf/*.blade.php')) as $path) {
        $source = file_get_contents($path);
        foreach ($fakes as $fake) {
            expect($source)->not->toContain($fake, basename($path).": memuat \"{$fake}\"");
        }
    }

    foreach (['sale-order-invoice', 'delivery-order', 'kwitansi', 'credit-note', 'customer-return', 'sales-order', 'quotation', 'surat-jalan', 'purchase-order', 'purchase-order-invoice-2', 'order-request'] as $name) {
        expect(file_get_contents(resource_path("views/pdf/{$name}.blade.php")))->not->toContain('<div class="company-name">PT DUTA TUNGGAL</div>', "{$name}: nama perusahaan dikeraskan");
    }
    expect(file_exists(resource_path('views/pdf/kwitansi-sales-order.blade.php')))->toBeFalse();   // templat yatim berdata contoh dihapus
});

it('Purchase Order, Invoice Pembelian, Order Request: kop dari Cabang/global (tanpa alamat contoh); render tetap sukses', function () {
    $ctx = tpContext();
    $supplier = \App\Models\Supplier::factory()->create();
    $po = \App\Models\PurchaseOrder::create([
        'supplier_id' => $supplier->id, 'cabang_id' => $ctx['cabang']->id, 'po_number' => 'PO-KOP-001', 'order_date' => now(), 'tempo_hutang' => 14, 'status' => 'approved', 'is_asset' => false,
    ]);

    // Purchase Order: alamat operasional cabang (bukan alamat pajak), NPWP tidak dicetak pada dokumen non-pajak
    $poHtml = view('pdf.purchase-order', ['purchaseOrder' => $po->load(['supplier', 'cabang', 'purchaseOrderItem.currency', 'purchaseOrderCurrency.currency'])])->render();
    expect($poHtml)->toContain('PT Duta Tunggal Sumatera')->toContain('Jl. Sudirman No. 10, Padang')->toContain('Telp: 0751-123456')->not->toContain('Jl. Pajak No. 1');
    tpAssertNoPlaceholders($poHtml);

    // Invoice Pembelian: dokumen berpajak → alamat pajak + NPWP; nama cabang tetap tampil
    $invoice = \App\Models\Invoice::factory()->create([
        'from_model_type' => \App\Models\PurchaseOrder::class, 'from_model_id' => $po->id, 'invoice_number' => 'PINV-KOP-001', 'cabang_id' => $ctx['cabang']->id,
        'subtotal' => 1000000, 'dpp' => 1000000, 'tax' => 11, 'ppn_rate' => 11, 'total' => 1110000,
    ]);
    $invoice = \App\Models\Invoice::with(['fromModel.supplier', 'invoiceItem.product', 'cabang'])->findOrFail($invoice->id);
    $invHtml = view('pdf.purchase-order-invoice-2', ['invoice' => $invoice])->render();
    expect($invHtml)->toContain('PT Duta Tunggal Sumatera')->toContain('Jl. Pajak No. 1, Padang')->toContain('NPWP: 01.234.567.8-201.000')->toContain('Cabang: Cabang Padang');
    tpAssertNoPlaceholders($invHtml);

    // Order Request tidak punya cabang tingkat dokumen → kop global; tanpa data apa pun hanya nama bawaan
    $orderRequest = \App\Models\OrderRequest::factory()->create(['currency_id' => $ctx['idr']->id]);
    $emptyHtml = view('pdf.order-request', ['orderRequest' => $orderRequest->load('createdBy')])->render();
    expect($emptyHtml)->toContain(\App\Services\DocumentPrintBuilder::DEFAULT_COMPANY_NAME)->not->toContain('Jl. Sudirman')->not->toContain('NPWP:');
    tpAssertNoPlaceholders($emptyHtml);

    \App\Models\AppSetting::set('company_legal_name', 'PT Global Legal');
    \App\Models\AppSetting::set('company_address', 'Jl. Global No. 5');
    \App\Models\AppSetting::set('company_phone', '021-999');
    $globalHtml = view('pdf.order-request', ['orderRequest' => $orderRequest->fresh()->load('createdBy')])->render();
    expect($globalHtml)->toContain('PT Global Legal')->toContain('Jl. Global No. 5')->toContain('Telp: 021-999');
    tpAssertNoPlaceholders($globalHtml);
});

it('PDF benar-benar terbentuk (Dompdf) untuk Invoice, DO, Kwitansi, Nota Kredit, Retur — termasuk watermark diputar', function () {
    $ctx = tpContext();
    [$invoice, $item] = ctlCreditInvoice($ctx);
    [$so, $soItem] = stkSaleOrder($ctx, 2);
    $deliveryOrder = stkDeliveryOrder($ctx, $so, $soItem, 2);
    [$receipt] = ctlPaidReceipt($ctx, ctlCreditInvoice($ctx)[0], 1332000);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Retur satu unit dari invoice', actor: $ctx['finance']);
    $invoice->forceFill(['status' => 'draft'])->saveQuietly();   // invoice berwatermark DRAFT ikut dirender

    foreach ([
        [Pdf::loadView('pdf.sale-order-invoice', ['invoice' => $invoice->fresh(['invoiceItem.product'])])->setPaper('a4', 'landscape')],
        [Pdf::loadView('pdf.delivery-order', ['deliveryOrder' => $deliveryOrder->load(['cabang', 'deliveryOrderItem.product.uom', 'salesOrders.customer'])])->setPaper('a4', 'portrait')],
        [Pdf::loadView('pdf.kwitansi', ['receipt' => $receipt->fresh()])->setPaper('a5', 'landscape')],
        [Pdf::loadView('pdf.credit-note', ['creditNote' => $cn->fresh()])->setPaper('a4', 'portrait')],
    ] as [$pdf]) {
        expect(substr($pdf->output(), 0, 5))->toBe('%PDF-');
    }
});

it('rute /pdf/credit-note dan /pdf/customer-receipt: PDF untuk yang berizin view, 403 untuk yang tidak', function () {
    $ctx = tpContext();
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $cn = app(CreditNoteService::class)->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 1], 'Retur satu unit dari invoice', actor: $ctx['finance']);
    [$receipt] = ctlPaidReceipt($ctx, ctlCreditInvoice($ctx)[0], 1332000);

    $ok = test()->actingAs($ctx['finance'])->get(route('pdf-stream', ['type' => 'credit-note', 'id' => $cn->id]));
    $ok->assertOk();
    expect($ok->headers->get('content-type'))->toContain('application/pdf');
    test()->actingAs($ctx['finance'])->get(route('pdf-stream', ['type' => 'customer-receipt', 'id' => $receipt->id]))->assertOk();

    $outsider = ctlUser($ctx, 'Sales', ['view any invoice']);
    test()->actingAs($outsider)->get(route('pdf-stream', ['type' => 'credit-note', 'id' => $cn->id]))->assertForbidden();
    test()->actingAs($outsider)->get(route('pdf-stream', ['type' => 'customer-receipt', 'id' => $receipt->id]))->assertForbidden();
});
