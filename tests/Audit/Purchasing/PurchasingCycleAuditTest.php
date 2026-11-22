<?php

/**
 * AUDIT AKUNTANSI SIKLUS PEMBELIAN (PO → GRN → Faktur → Pembayaran Hutang → Retur).
 *
 * Setiap test menegaskan perlakuan yang BENAR menurut PSAK 14 / keputusan bisnis. Test yang GAGAL = temuan audit.
 * Angka aktual dicetak ke STDERR dengan prefiks "[AUDIT]" agar bisa dikutip di laporan.
 *
 * Jalankan: DB_DATABASE=duta_tunggal_test_3 php artisan test tests/Audit/Purchasing/PurchasingCycleAuditTest.php
 */

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\VendorPayment;
use App\Services\PurchaseReceiptService;
use App\Services\Reports\StockReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

require_once __DIR__ . '/../../Support/PurchaseFixtures.php';

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function audLog(string $label, array $data): void
{
    fwrite(STDERR, "\n[AUDIT] {$label}: " . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n");
}

function audCtx(): array
{
    $ctx = purContext();
    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $ctx['variance'] = $coa('5160', 'Selisih Pembelian', 'Expense');
    $ctx['expense'] = $coa('6100', 'Beban Umum', 'Expense');
    $ctx['goodsDelivery'] = $coa('1140.20', 'Barang Terkirim', 'Asset');
    $ctx['bank']->forceFill(['is_cash_bank' => true])->save();
    $ctx['product']->forceFill(['cost_price' => 0, 'goods_delivery_coa_id' => $ctx['goodsDelivery']->id])->saveQuietly();
    $ctx['product'] = $ctx['product']->fresh();

    return $ctx;
}

/** PO satu baris dengan diskon %, tarif PPN, tipe pajak & mata uang bebas. */
function audPo(array $ctx, float $qty, float $price, float $discount = 0, float $tax = 0, string $tipe = 'Non Pajak', ?Currency $currency = null, ?float $rate = null): array
{
    $po = PurchaseOrder::create([
        'po_number' => 'PO-AUD-' . strtoupper(substr(uniqid(), -7)), 'supplier_id' => $ctx['supplier']->id, 'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id, 'order_date' => now()->toDateString(), 'expected_date' => now()->addDays(7)->toDateString(),
        'status' => 'approved', 'total_amount' => $qty * $price, 'top_type' => 'Tempo', 'tempo_hutang' => 30, 'created_by' => $ctx['user']->id,
    ]);
    $cur = $currency ?? $ctx['idr'];
    if ($rate !== null) {
        PurchaseOrderCurrency::create(['purchase_order_id' => $po->id, 'currency_id' => $cur->id, 'nominal' => $rate]);
    }
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty, 'unit_price' => $price,
        'currency_id' => $cur->id, 'discount' => $discount, 'tax' => $tax, 'tipe_pajak' => $tipe,
    ]);

    return [$po->fresh(), $poItem->fresh()];
}

/** GRN + posting persediaan (jalur nyata PenerimaanBarangController::store → postPurchaseReceipt). */
function audReceive(array $ctx, PurchaseOrder $po, float $qty): array
{
    [$receipt, $item] = purReceipt($ctx, $po, $qty);
    $result = app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh());
    expect($result['status'])->toBe('posted');

    return [$receipt->fresh(), $item->fresh()];
}

/** Faktur pembelian lewat route nyata (save_and_post). $price null = harga PO. */
function audInvoice($test, array $ctx, PurchaseReceipt $receipt, ?float $price = null, float $qty = 0): Invoice
{
    $num = 'SUPINV-' . strtoupper(substr(uniqid(), -7));
    $payload = [
        'supplier_id' => $ctx['supplier']->id, 'purchase_receipts' => [$receipt->id], 'supplier_invoice_number' => $num,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'action' => 'save_and_post', 'other_fee' => 0,
    ];
    if ($price !== null) {
        $payload['items'] = [['product_id' => $ctx['product']->id, 'quantity' => $qty, 'price' => $price]];
    }
    $resp = $test->actingAs($ctx['user'])->post(route('pembelian.invoices.store'), $payload);
    $resp->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();

    return Invoice::where('supplier_invoice_number', $num)->firstOrFail();
}

/** Pembayaran lewat route store (save_and_post). Mengembalikan pesan error bila gagal. */
function audPayViaStore($test, array $ctx, Invoice $invoice, float $amount): ?string
{
    $test->actingAs($ctx['user'])->post(route('pembelian.payments.store'), [
        'action' => 'save_and_post', 'supplier_id' => $ctx['supplier']->id, 'cabang_id' => $ctx['cabang']->id, 'payment_date' => now()->toDateString(),
        'payment_method' => 'Bank Transfer', 'coa_id' => $ctx['bank']->id, 'transfer_reference_number' => 'TRF-S-' . uniqid(),
        'selected_invoices' => [$invoice->id], 'allocations' => [$invoice->id => $amount],
    ]);

    return session('error');
}

/**
 * Pembayaran lewat route NYATA `pembelian.payments.post` atas draft (store save_and_post rusak — lihat test E0).
 * Draft dibentuk persis seperti VendorPaymentController::store (header + detail).
 */
function audPay($test, array $ctx, Invoice $invoice, float $amount): VendorPayment
{
    $payment = VendorPayment::withoutEvents(fn () => VendorPayment::create([
        'payment_number' => VendorPayment::generatePaymentNumber(), 'supplier_id' => $ctx['supplier']->id, 'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(), 'payment_method' => 'Bank Transfer', 'coa_id' => $ctx['bank']->id,
        'transfer_reference_number' => 'TRF-' . uniqid(), 'selected_invoices' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
        'invoice_receipts' => [$invoice->id => $amount], 'total_payment' => $amount, 'total_payment_idr' => $amount,
        'currency_id' => $invoice->currency_id, 'exchange_rate' => 1, 'status' => VendorPayment::STATUS_DRAFT,
    ]));
    \App\Models\VendorPaymentDetail::create([
        'vendor_payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'currency_id' => $invoice->currency_id, 'exchange_rate' => 1,
        'method' => 'Bank Transfer', 'amount' => $amount, 'amount_idr' => $amount, 'coa_id' => $ctx['bank']->id, 'payment_date' => now()->toDateString(),
    ]);
    $test->actingAs($ctx['user'])->post(route('pembelian.payments.post', $payment->id));
    expect(session('error'))->toBeNull();

    return $payment->fresh();
}

function audReturn($test, array $ctx, PurchaseReceipt $receipt, PurchaseReceiptItem $item, float $qty): PurchaseReturn
{
    $nota = 'NR-AUD-' . strtoupper(substr(uniqid(), -6));
    $test->actingAs($ctx['user'])->post(route('pembelian.returns.store'), [
        'supplier_id' => $ctx['supplier']->id, 'purchase_receipt_id' => $receipt->id, 'return_date' => now()->toDateString(), 'nota_retur' => $nota,
        'action' => 'save_and_submit', 'items' => [['purchase_receipt_item_id' => $item->id, 'product_id' => $item->product_id, 'qty_returned' => $qty, 'reason' => 'Rusak']],
    ])->assertSessionHasNoErrors();
    $return = PurchaseReturn::where('nota_retur', $nota)->firstOrFail();
    $test->actingAs($ctx['user'])->post(route('pembelian.returns.approve', $return->id));
    expect(session('error'))->toBeNull();

    return $return->fresh();
}

/** Penjualan disimulasikan persis seperti DeliveryOrderObserver (nilai keluar = qty × cost_price, Cr Persediaan). */
function audSell(array $ctx, float $qty): float
{
    $cost = (float) $ctx['product']->fresh()->cost_price;
    $value = round($qty * $cost, 2);
    StockMovement::create(['product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => $qty, 'value' => $value, 'type' => 'sales', 'date' => now(), 'notes' => 'Audit sale', 'meta' => []]);
    foreach ([[$ctx['goodsDelivery']->id, $value, 0], [$ctx['inventory']->id, 0, $value]] as [$coa, $d, $c]) {
        JournalEntry::create(['coa_id' => $coa, 'date' => now()->toDateString(), 'reference' => 'AUD-SALE', 'description' => 'Audit sale', 'debit' => $d, 'credit' => $c, 'journal_type' => 'sales', 'source_type' => 'AUDIT-SALE', 'source_id' => 1]);
    }

    return $value;
}

/** Saldo GL (debit − kredit) untuk kode akun, jurnal aktif saja. */
function audGl(string $code): float
{
    $coa = ChartOfAccount::where('code', $code)->first();
    if (! $coa) {
        return 0.0;
    }
    $q = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('coa_id', $coa->id);

    return round((float) (clone $q)->sum('debit') - (float) (clone $q)->sum('credit'), 2);
}

/** Pasangan [source,tanggal] yang tidak seimbang. */
function audUnbalanced(): array
{
    return JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')
        ->selectRaw('source_type, source_id, DATE(date) d, ROUND(SUM(debit),2) dr, ROUND(SUM(credit),2) cr')
        ->groupBy('source_type', 'source_id', 'd')->get()
        ->filter(fn ($r) => abs((float) $r->dr - (float) $r->cr) > 0.009)->values()->toArray();
}

function audStockValue(array $ctx): float
{
    $r = app(StockReportService::class)->generate(['product_ids' => [$ctx['product']->id]]);

    return round((float) $r['totals']['total_value'], 2);
}

function audJournalCount(): int
{
    return JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->count();
}

// ---------------------------------------------------------------------------------------------------------------
// 1+2. Siklus dasar PPN 11% eksklusif: GRN → faktur → 2100.10 bersih, PPN masukan, hutang = total faktur, idempoten.
// ---------------------------------------------------------------------------------------------------------------
it('A. siklus dasar PPN eksklusif: GRN, faktur, 2100.10 bersih, 2110 = total faktur, seimbang & idempoten', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt, $item] = audReceive($ctx, $po, 10);

    expect(audGl('1140.10'))->toBe(500000.0)->and(audGl('2100.10'))->toBe(-500000.0);

    // idempoten GRN
    $n = audJournalCount();
    app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh());
    app(PurchaseReceiptService::class)->postItemInventoryAfterQC($item->fresh());
    expect(audJournalCount())->toBe($n)
        ->and((float) StockMovement::where('type', 'purchase_in')->sum('quantity'))->toBe(10.0);

    $invoice = audInvoice($this, $ctx, $receipt);
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    audLog('A faktur', ['subtotal' => (float) $invoice->subtotal, 'dpp' => (float) $invoice->dpp, 'ppn_rate' => (float) $invoice->ppn_rate, 'total' => (float) $invoice->total,
        'gl_1140.10' => audGl('1140.10'), 'gl_2100.10' => audGl('2100.10'), 'gl_1170.06' => audGl('1170.06'), 'gl_2110' => audGl('2110'), 'ap_total' => (float) $ap->total]);

    expect(audGl('2100.10'))->toBe(0.0)
        ->and(audGl('1170.06'))->toBe(55000.0)
        ->and(audGl('2110'))->toBe(-555000.0)
        ->and((float) $ap->total)->toBe(555000.0)
        ->and(audUnbalanced())->toBe([]);

    // idempoten posting faktur (klik "Posting" lagi)
    $n = audJournalCount();
    $this->actingAs($ctx['user'])->post(route('pembelian.invoices.post', $invoice));
    expect(audJournalCount())->toBe($n)->and(audGl('2110'))->toBe(-555000.0);
});

it('A2. PPN 11% efektif wajib disajikan sebagai DPP Nilai Lain 11/12 dengan tarif 12% (nilai PPN tetap 11%)', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt);
    $ppn = (float) $invoice->invoiceItem()->sum('tax_amount');
    audLog('A2 DPP', ['dpp' => (float) $invoice->dpp, 'ppn_rate' => (float) $invoice->ppn_rate, 'ppn_amount' => $ppn, 'expected_dpp_nilai_lain' => round(500000 * 11 / 12, 2), 'expected_rate' => 12]);

    expect($ppn)->toBe(55000.0); // nilai uang 11%
    expect(round((float) $invoice->dpp, 2))->toBe(458333.33)
        ->and((float) $invoice->ppn_rate)->toBe(12.0);
});

it('B. selisih harga faktur vs PO masuk 5160, 2100.10 tetap bersih', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt, 50500, 10);
    audLog('B variance', ['subtotal' => (float) $invoice->subtotal, 'variance' => (float) $invoice->price_variance_amount, 'gl_5160' => audGl('5160'), 'gl_2100.10' => audGl('2100.10'), 'gl_2110' => audGl('2110'), 'gl_1170.06' => audGl('1170.06'), 'cost_price' => (float) $ctx['product']->fresh()->cost_price]);

    expect(audGl('5160'))->toBe(5000.0)
        ->and(audGl('2100.10'))->toBe(0.0)
        ->and(audGl('1170.06'))->toBe(55550.0)
        ->and(audGl('2110'))->toBe(-560550.0)
        ->and((float) AccountPayable::where('invoice_id', $invoice->id)->value('total'))->toBe(560550.0)
        ->and(audUnbalanced())->toBe([]);
});

it('C. diskon PO 10%: persediaan & HPP harus dinilai net diskon dan 2100.10 bersih setelah faktur', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 10, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $glInvAfterGrn = audGl('1140.10');
    $invoice = audInvoice($this, $ctx, $receipt);
    audLog('C diskon', ['gl_1140.10_grn' => $glInvAfterGrn, 'cost_price' => (float) $ctx['product']->fresh()->cost_price, 'inv_subtotal' => (float) $invoice->subtotal,
        'gl_2100.10' => audGl('2100.10'), 'gl_5160' => audGl('5160'), 'gl_2110' => audGl('2110'), 'gl_1170.06' => audGl('1170.06'), 'stock_value' => audStockValue($ctx)]);

    expect((float) $invoice->subtotal)->toBe(450000.0);
    expect($glInvAfterGrn)->toBe(450000.0)
        ->and((float) $ctx['product']->fresh()->cost_price)->toBe(45000.0)
        ->and(audGl('2100.10'))->toBe(0.0);
});

it('D. PPN inklusif: PPN tidak boleh dikapitalisasi ke persediaan dan 2100.10 bersih', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 55500, 0, 11, 'Inklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $glInvAfterGrn = audGl('1140.10');
    $invoice = audInvoice($this, $ctx, $receipt);
    audLog('D inklusif', ['gl_1140.10_grn' => $glInvAfterGrn, 'cost_price' => (float) $ctx['product']->fresh()->cost_price, 'inv_subtotal' => (float) $invoice->subtotal,
        'gl_2100.10' => audGl('2100.10'), 'gl_1170.06' => audGl('1170.06'), 'gl_2110' => audGl('2110'), 'gl_5160' => audGl('5160')]);

    expect(audGl('1170.06'))->toBe(55000.0)->and(audGl('2110'))->toBe(-555000.0);
    expect($glInvAfterGrn)->toBe(500000.0)
        ->and(audGl('2100.10'))->toBe(0.0);
});

// ---------------------------------------------------------------------------------------------------------------
// 3. Pembayaran hutang parsial + lunas + batal.
// ---------------------------------------------------------------------------------------------------------------
it('E0. form Pembayaran Hutang (store save_and_post) harus berhasil membuat pembayaran', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt);
    $err = audPayViaStore($this, $ctx, $invoice, 300000);
    audLog('E0 store', ['error' => $err, 'payments' => VendorPayment::count(), 'gl_2110' => audGl('2110')]);
    expect($err)->toBeNull()->and(VendorPayment::count())->toBe(1);
});

it('E. pembayaran hutang parsial & lunas: Dr 2110 / Cr Bank; subledger AP = GL 2110', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt);

    $p1 = audPay($this, $ctx, $invoice, 300000);
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    audLog('E parsial', ['status' => $p1->status, 'ap_remaining' => (float) $ap->remaining, 'gl_2110' => audGl('2110'), 'gl_bank' => audGl('1112.01'), 'inv_status' => $invoice->fresh()->status]);
    expect(audGl('2110'))->toBe(-255000.0)->and(audGl('1112.01'))->toBe(-300000.0)
        ->and((float) $ap->remaining)->toBe(-audGl('2110'))
        ->and(audUnbalanced())->toBe([]);

    // idempoten: posting ulang pembayaran yang sudah terposting
    $n = audJournalCount();
    app(\App\Services\LedgerPostingService::class)->postVendorPayment($p1->fresh());
    expect(audJournalCount())->toBe($n);

    $p2 = audPay($this, $ctx, $invoice, 255000);
    $ap->refresh();
    audLog('E lunas', ['status' => $p2->status, 'ap_remaining' => (float) $ap->remaining, 'ap_status' => $ap->status, 'gl_2110' => audGl('2110'), 'inv_status' => $invoice->fresh()->status]);
    expect(audGl('2110'))->toBe(0.0)->and((float) $ap->remaining)->toBe(0.0)->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);

    // batal pembayaran kedua → hutang & GL pulih, dengan jejak jurnal pembalik (bukan hapus)
    $this->actingAs($ctx['user'])->post(route('pembelian.payments.cancel', $p2->id), ['cancel_reason' => 'Salah transfer bank']);
    $ap->refresh();
    $trashed = JournalEntry::withoutGlobalScopes()->where('source_type', VendorPayment::class)->where('source_id', $p2->id)->whereNotNull('deleted_at')->count();
    $reversals = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('source_type', VendorPayment::class)->where('source_id', $p2->id)->count();
    audLog('E batal', ['ap_remaining' => (float) $ap->remaining, 'gl_2110' => audGl('2110'), 'soft_deleted_lines' => $trashed, 'active_reversal_lines' => $reversals]);
    expect(audGl('2110'))->toBe(-255000.0)->and((float) $ap->remaining)->toBe(255000.0);
    expect($trashed)->toBe(0); // jurnal terposting seharusnya dibalik, bukan dihapus
});

it('E2. sisa hutang ≤ Rp1 dihapus dari subledger tanpa jurnal → subledger ≠ GL', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt);
    audPay($this, $ctx, $invoice, 554999.5);
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    audLog('E2 toleransi', ['ap_remaining' => (float) $ap->remaining, 'ap_status' => $ap->status, 'gl_2110' => audGl('2110')]);
    expect((float) $ap->remaining)->toBe(-audGl('2110'));
});

// ---------------------------------------------------------------------------------------------------------------
// 4. HPP rata-rata bergerak: 2× pembelian beda harga + penjualan di tengah; laporan stok vs GL.
// ---------------------------------------------------------------------------------------------------------------
it('F. HPP averaging: pembelian 10@10.000, jual 4, pembelian 10@13.000 → HPP 11.875, laporan stok = GL', function () {
    $ctx = audCtx();
    [$po1] = audPo($ctx, 10, 10000);
    [$r1] = audReceive($ctx, $po1, 10);
    $hpp1 = (float) $ctx['product']->fresh()->cost_price;
    $sold = audSell($ctx, 4);
    [$po2] = audPo($ctx, 10, 13000);
    [$r2] = audReceive($ctx, $po2, 10);
    $p = $ctx['product']->fresh();
    audInvoice($this, $ctx, $r1);
    audInvoice($this, $ctx, $r2);
    audLog('F averaging', ['hpp1' => $hpp1, 'sold_value' => $sold, 'cost_price' => (float) $p->cost_price, 'last_purchase_price' => (float) $p->last_purchase_price,
        'stock_qty' => (float) \App\Models\InventoryStock::where('product_id', $p->id)->sum('qty_available'), 'stock_value' => audStockValue($ctx), 'gl_1140.10' => audGl('1140.10'), 'gl_2100.10' => audGl('2100.10')]);

    expect($hpp1)->toBe(10000.0)
        ->and((float) $p->cost_price)->toBe(11875.0)
        ->and((float) $p->last_purchase_price)->toBe(13000.0)
        ->and(audStockValue($ctx))->toBe(190000.0)
        ->and(audGl('1140.10'))->toBe(190000.0)
        ->and(audGl('2100.10'))->toBe(0.0)
        ->and(audUnbalanced())->toBe([]);
});

it('F2. HPP averaging dengan pembulatan: 3@100 lalu 4@101 → selisih pembulatan laporan stok vs GL', function () {
    $ctx = audCtx();
    [$po1] = audPo($ctx, 3, 100);
    audReceive($ctx, $po1, 3);
    [$po2] = audPo($ctx, 4, 101);
    audReceive($ctx, $po2, 4);
    $cost = (float) $ctx['product']->fresh()->cost_price;
    $before = ['cost_price' => $cost, 'stock_value' => audStockValue($ctx), 'gl' => audGl('1140.10')];
    audSell($ctx, 7);
    audLog('F2 pembulatan', ['before_sale' => $before, 'after_sale_qty0_gl_1140.10' => audGl('1140.10'), 'after_sale_stock_value' => audStockValue($ctx)]);
    expect(audGl('1140.10'))->toBe(audStockValue($ctx));
});

it('G. retur pembelian di tengah averaging: GL persediaan vs laporan stok harus tetap sama', function () {
    $ctx = audCtx();
    [$po1] = audPo($ctx, 10, 10000);
    [$r1] = audReceive($ctx, $po1, 10);
    audSell($ctx, 4);
    [$po2] = audPo($ctx, 10, 13000);
    [$r2, $i2] = audReceive($ctx, $po2, 10);
    audInvoice($this, $ctx, $r2);
    $ret = audReturn($this, $ctx, $r2, $i2, 2);
    $p = $ctx['product']->fresh();
    audLog('G retur+averaging', ['return_status' => $ret->status, 'cost_price' => (float) $p->cost_price, 'stock_qty' => (float) \App\Models\InventoryStock::where('product_id', $p->id)->sum('qty_available'),
        'stock_value' => audStockValue($ctx), 'gl_1140.10' => audGl('1140.10')]);
    expect($ret->status)->toBe('approved');
    expect(audStockValue($ctx))->toBe(audGl('1140.10'));
});

// ---------------------------------------------------------------------------------------------------------------
// 4b. Mata uang asing.
// ---------------------------------------------------------------------------------------------------------------
it('H. PO USD (kurs 15.000): GRN, faktur & hutang dalam IDR konsisten; 2100.10 bersih; AP = GL', function () {
    $ctx = audCtx();
    $usd = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'to_rupiah' => 15000]);
    [$po] = audPo($ctx, 10, 10, 0, 11, 'Eklusif', $usd, 15000);
    [$receipt] = audReceive($ctx, $po, 10);
    $glGrn = audGl('1140.10');
    $invoice = audInvoice($this, $ctx, $receipt);
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    audLog('H USD', ['gl_1140.10' => $glGrn, 'inv_currency_id' => $invoice->currency_id, 'inv_rate' => (float) $invoice->exchange_rate, 'inv_total' => (float) $invoice->total,
        'gl_2100.10' => audGl('2100.10'), 'gl_1170.06' => audGl('1170.06'), 'gl_2110' => audGl('2110'), 'ap_total_idr' => (float) $ap->total, 'ap_total_original' => (float) $ap->total_original, 'ap_currency' => $ap->currency_id, 'ap_rate' => (float) $ap->exchange_rate]);

    expect($glGrn)->toBe(1500000.0)
        ->and(audGl('2100.10'))->toBe(0.0)
        ->and(audGl('1170.06'))->toBe(165000.0)
        ->and(audGl('2110'))->toBe(-1665000.0);
    expect((float) $ap->total)->toBe(1665000.0);
});

// ---------------------------------------------------------------------------------------------------------------
// 5. Retur pembelian.
// ---------------------------------------------------------------------------------------------------------------
it('I. retur gudang setelah ditagih: Dr 2110 (DPP+PPN) / Cr Persediaan / Cr PPN Masukan; stok & AP turun; AP = GL', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt, $item] = audReceive($ctx, $po, 10);
    $invoice = audInvoice($this, $ctx, $receipt);
    $ret = audReturn($this, $ctx, $receipt, $item, 2);
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();
    $lines = JournalEntry::where('source_type', PurchaseReturn::class)->where('source_id', $ret->id)->get(['coa_id', 'debit', 'credit'])->toArray();
    audLog('I retur', ['status' => $ret->status, 'lines' => $lines, 'gl_1140.10' => audGl('1140.10'), 'gl_1170.06' => audGl('1170.06'), 'gl_2110' => audGl('2110'),
        'ap_remaining' => (float) $ap->remaining, 'stock' => (float) \App\Models\InventoryStock::where('product_id', $ctx['product']->id)->sum('qty_available'), 'stock_value' => audStockValue($ctx)]);

    expect($ret->status)->toBe('approved')
        ->and(audGl('1140.10'))->toBe(400000.0)
        ->and(audGl('1170.06'))->toBe(44000.0)
        ->and(audGl('2110'))->toBe(-444000.0)
        ->and((float) $ap->remaining)->toBe(444000.0)
        ->and((float) \App\Models\InventoryStock::where('product_id', $ctx['product']->id)->sum('qty_available'))->toBe(8.0)
        ->and(audUnbalanced())->toBe([]);

    // idempoten: approve ulang ditolak, tidak ada jurnal ganda
    $n = audJournalCount();
    $this->actingAs($ctx['user'])->post(route('pembelian.returns.approve', $ret->id));
    expect(audJournalCount())->toBe($n);
});

it('J. retur gudang SEBELUM ditagih: Dr 2100.10; faktur berikutnya hanya untuk qty bersih; 2100.10 = 0', function () {
    $ctx = audCtx();
    [$po] = audPo($ctx, 10, 50000, 0, 11, 'Eklusif');
    [$receipt, $item] = audReceive($ctx, $po, 10);
    audReturn($this, $ctx, $receipt, $item, 2);
    $glUnbilledAfterReturn = audGl('2100.10');
    $invoice = audInvoice($this, $ctx, $receipt);
    audLog('J retur pra-faktur', ['gl_2100.10_after_return' => $glUnbilledAfterReturn, 'invoice_qty' => (float) $invoice->invoiceItem()->sum('quantity'), 'invoice_total' => (float) $invoice->total,
        'gl_2100.10' => audGl('2100.10'), 'gl_2110' => audGl('2110'), 'gl_1140.10' => audGl('1140.10'), 'ap_total' => (float) AccountPayable::where('invoice_id', $invoice->id)->value('total'),
        'ap_remaining' => (float) AccountPayable::where('invoice_id', $invoice->id)->value('remaining')]);

    expect($glUnbilledAfterReturn)->toBe(-400000.0);
    expect((float) $invoice->invoiceItem()->sum('quantity'))->toBe(8.0)
        ->and(audGl('2100.10'))->toBe(0.0)
        ->and(audGl('2110'))->toBe(-444000.0);
});

// ---------------------------------------------------------------------------------------------------------------
// 6. Rekonsiliasi akhir.
// ---------------------------------------------------------------------------------------------------------------
it('K. rekonsiliasi akhir multi-dokumen: GL 1140.10 = nilai stok, GL 2110 = Σ AP remaining, GL 2100.10 = 0', function () {
    $ctx = audCtx();
    [$po1] = audPo($ctx, 10, 20000, 0, 11, 'Eklusif');
    [$r1] = audReceive($ctx, $po1, 10);
    [$po2] = audPo($ctx, 5, 26000, 0, 11, 'Eklusif');
    [$r2, $i2] = audReceive($ctx, $po2, 5);
    $inv1 = audInvoice($this, $ctx, $r1);
    $inv2 = audInvoice($this, $ctx, $r2, 26100, 5);
    audPay($this, $ctx, $inv1, 100000);
    audSell($ctx, 3);
    audReturn($this, $ctx, $r2, $i2, 1);

    $apRemaining = round((float) AccountPayable::sum('remaining'), 2);
    $res = ['cost_price' => (float) $ctx['product']->fresh()->cost_price, 'stock_value' => audStockValue($ctx), 'gl_1140.10' => audGl('1140.10'),
        'gl_2110' => audGl('2110'), 'ap_remaining' => $apRemaining, 'gl_2100.10' => audGl('2100.10'), 'gl_5160' => audGl('5160'), 'unbalanced' => audUnbalanced()];
    audLog('K rekonsiliasi', $res);

    expect(audGl('2100.10'))->toBe(0.0)
        ->and($apRemaining)->toBe(-audGl('2110'))
        ->and(audUnbalanced())->toBe([]);
    expect(audStockValue($ctx))->toBe(audGl('1140.10'));
});
