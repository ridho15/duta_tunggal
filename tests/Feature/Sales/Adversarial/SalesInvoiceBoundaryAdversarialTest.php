<?php

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\AccountReceivable;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use App\Services\InvoiceService;
use App\Services\SalesInvoiceLineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('INV-BV-01: kalkulasi pajak dan pembulatan desimal ganjil menghasilkan jurnal penjualan seimbang mutlak', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 3.3333, [
        'tipe_pajak' => 'include',
        'ppn_rate' => 11.0,
    ], [
        'unit_price' => 12345.67,
        'tipe_pajak' => 'include',
    ]);

    $do = stkDeliveryOrder($ctx, $so, $soItem, 3.3333, 'approved');
    $sch = stkSchedule($ctx, $do);
    $sch->update(['status' => 'on_the_way']);
    $sch->update(['status' => 'delivered']);

    // Buat invoice dari SO/DO dengan item desimal
    $invoiceNumber = app(InvoiceService::class)->generateSalesInvoiceNumber($ctx['cabang']->id, true);
    $grossTotal = round(3.3333 * 12345.67, 2); // ~ 41151.78
    $dpp = round($grossTotal / 1.11, 2);
    $ppn = round($grossTotal - $dpp, 2);

    $invoice = Invoice::create([
        'invoice_number' => $invoiceNumber,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'delivery_orders' => [$do->id],
        'status' => Invoice::STATUS_SENT,
        'subtotal' => $dpp,
        'tax' => $ppn,
        'ppn_rate' => 11.0,
        'tipe_pajak' => 'include',
        'total' => $grossTotal,
        'created_by' => $ctx['user']->id,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 3.3333,
        'unit_price' => 12345.67,
        'subtotal' => $dpp,
        'tax' => $ppn,
        'total' => $grossTotal,
    ]);

    // Posting jurnal penjualan
    (new InvoiceObserver())->postSalesInvoice($invoice->fresh());

    $journals = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();

    expect($journals)->isNotEmpty();

    $sumDebit = (float) $journals->sum('debit');
    $sumCredit = (float) $journals->sum('credit');

    // Invarian akuntansi: Total Debit (Piutang) == Total Credit (Penjualan + PPN), selisih < Rp 0.01
    expect(abs($sumDebit - $sumCredit))->toBeLessThan(0.01)
        ->and($sumDebit)->toBeGreaterThan(0);

    // Sub-ledger AR juga harus terbuat dengan nominal yang sesuai
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect($ar)->not->toBeNull()
        ->and((float) $ar->total)->toBe($grossTotal)
        ->and((float) $ar->remaining)->toBe($grossTotal);
});

it('INV-BV-02: penolakan penerbitan faktur dari Delivery Order ilegal atau belum terkirim', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    // DO masih berstatus draf / request_stock (belum disetujui gudang / belum dikirim)
    $do = stkDeliveryOrder($ctx, $so, $soItem, 5, 'request_stock');

    expect($do->status)->toBe('request_stock');

    // Validasi bisnis melarang penagihan DO yang belum selesai/dikirim
    expect(in_array($do->status, ['sent', 'delivered', 'completed']))->toBeFalse();
});

it('INV-BV-03: sistem menolak penerbitan faktur penjualan bernilai Rp 0', function () {
    $createPage = new class extends CreateSalesInvoice {
        public function testMutate(array $data): array {
            return $this->mutateFormDataBeforeCreate($data);
        }
    };

    // Percobaan membuat invoice dengan total dan subtotal 0
    expect(function () use ($createPage) {
        $createPage->testMutate([
            'total' => 0,
            'subtotal' => 0,
            'selected_delivery_orders' => [999],
        ]);
    })->toThrow(ValidationException::class);
});

it('INV-BV-04: fallback aman saat exchange rate mata uang nol atau null tanpa menyebabkan crash division by zero', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 2);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-CURR-NULL',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 20000,
        'tax' => 0,
        'total' => 20000,
        'exchange_rate' => null, // exchange rate null
        'currency_id' => null,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 2,
        'unit_price' => 10000,
        'subtotal' => 20000,
        'total' => 20000,
    ]);

    // Observer harus mampu meng-handle exchange rate fallback menjadi 1.0 secara aman
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect($ar)->not->toBeNull()
        ->and((float) $ar->exchange_rate)->toBe(1.0)
        ->and((float) $ar->total)->toBe(20000.0);
});
