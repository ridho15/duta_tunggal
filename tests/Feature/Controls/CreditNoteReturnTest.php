<?php

/**
 * T5.2 — Retur penjualan berjalur uang (keputusan "Refund / Nota Kredit") + laporan penjualan bersih:
 *  - keputusan `credit` memulihkan stok & HPP seperti "Penggantian" (tanpa penggandaan), sisi uang dikerjakan Nota Kredit tipe retur
 *  - draftFromReturn: hanya retur disetujui/selesai, satu Nota Kredit per retur, kuantitas ≤ sisa invoice
 *  - laporan penjualan mengecualikan invoice `cancelled` dan menghitung bersih setelah Nota Kredit (HPP berkurang hanya untuk retur fisik)
 *  - invoice `cancelled` tidak menghalangi Surat Jalan-nya ditagih ulang (invoice pengganti)
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Services\CreditNoteService;
use App\Services\CustomerReturnService;
use App\Services\Reports\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_notes' => true]);
});

function crUser(array $ctx): \App\Models\User
{
    $user = ctlUser($ctx, 'Finance Manager', ['approve credit note', 'create credit note']);
    Auth::login($user);

    return $user;
}

/** Retur atas invoice: satu item berkeputusan $decision, status $status. */
function crReturn(array $ctx, Invoice $invoice, \App\Models\InvoiceItem $item, float $qty, string $decision = CustomerReturnItem::DECISION_CREDIT, string $status = CustomerReturn::STATUS_APPROVED): CustomerReturn
{
    $return = CustomerReturn::create([
        'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now()->toDateString(), 'reason' => 'Unit cacat produksi, customer minta uang kembali', 'status' => $status,
    ]);
    CustomerReturnItem::create([
        'customer_return_id' => $return->id, 'product_id' => $item->product_id, 'invoice_item_id' => $item->id, 'quantity' => $qty,
        'problem_description' => 'Cacat produksi', 'qc_result' => CustomerReturnItem::QC_RESULT_FAIL, 'decision' => $decision,
    ]);

    return $return->fresh();
}

function crSalesReport(): SalesReportService
{
    return new SalesReportService;   // instance baru: cache baris/keberadaan Nota Kredit per instance
}

it('draftFromReturn: retur 3 dari 12 pcs berkeputusan credit → Nota Kredit retur 333.000 (tepat 3/12), tertaut ke retur; tidak ganda', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $return = crReturn($ctx, $invoice, $item, 3);
    $service = app(CreditNoteService::class);

    $cn = $service->draftFromReturn($return, $user);

    expect($cn->type)->toBe(CreditNote::TYPE_RETURN)->and($cn->status)->toBe('draft')->and($cn->customer_return_id)->toBe($return->id)->and($cn->invoice_id)->toBe($invoice->id)
        ->and((float) $cn->subtotal)->toBe(300000.0)->and((float) $cn->tax_amount)->toBe(33000.0)->and((float) $cn->total)->toBe(333000.0)
        ->and($cn->reason)->toContain($return->return_number);

    expect(fn () => $service->draftFromReturn($return, $user))->toThrow(ValidationException::class, 'sudah memiliki Nota Kredit');
});

it('draftFromReturn: ditolak untuk retur belum disetujui, tanpa item credit, atau flag mati; kuantitas melebihi sisa invoice ditolak', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);

    expect(fn () => $service->draftFromReturn(crReturn($ctx, $invoice, $item, 2, status: CustomerReturn::STATUS_RECEIVED), $user))->toThrow(ValidationException::class, 'belum disetujui');
    expect(fn () => $service->draftFromReturn(crReturn($ctx, $invoice, $item, 2, CustomerReturnItem::DECISION_REPLACE), $user))->toThrow(ValidationException::class, 'tidak memiliki item');
    expect(fn () => $service->draftFromReturn(crReturn($ctx, $invoice, $item, 13), $user))->toThrow(ValidationException::class, 'melebihi sisa');

    config(['sales.controls.credit_notes' => false]);
    expect(fn () => $service->draftFromReturn(crReturn($ctx, $invoice, $item, 1), $user))->toThrow(ValidationException::class, 'belum diaktifkan');
});

it('draftFromReturn: beberapa baris retur atas item invoice yang sama dijumlahkan; baris berkeputusan lain (tolak/ganti) tidak ikut', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    $return = crReturn($ctx, $invoice, $item, 2);   // baris credit pertama (2 pcs)
    foreach ([[1, CustomerReturnItem::DECISION_CREDIT], [4, CustomerReturnItem::DECISION_REJECT], [5, CustomerReturnItem::DECISION_REPLACE]] as [$qty, $decision]) {
        CustomerReturnItem::create(['customer_return_id' => $return->id, 'product_id' => $item->product_id, 'invoice_item_id' => $item->id, 'quantity' => $qty,
            'problem_description' => 'Baris tambahan', 'qc_result' => CustomerReturnItem::QC_RESULT_FAIL, 'decision' => $decision]);
    }

    $cn = app(CreditNoteService::class)->draftFromReturn($return->fresh(), $user);

    expect((float) $cn->items->sum('quantity'))->toBe(3.0)->and((float) $cn->total)->toBe(333000.0);
});

it('keputusan credit tidak menggandakan: stok kembali + jurnal HPP retur (Dr Persediaan/Cr HPP) dari CustomerReturnService; Nota Kredit hanya sisi uang (Dr Retur+PPN/Cr Piutang)', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);
    foreach ([['1101.01', 'Persediaan', 'Asset'], ['5100.10', 'HPP', 'Expense']] as [$code, $name, $type]) {
        ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    }
    $before = (float) InventoryStock::withoutGlobalScopes()->where('product_id', $item->product_id)->where('warehouse_id', $ctx['warehouse']->id)->sum('qty_available');
    $return = crReturn($ctx, $invoice, $item, 3);

    app(CustomerReturnService::class)->processCompletion($return);
    $cn = app(CreditNoteService::class)->draftFromReturn($return->fresh(), $user);
    app(CreditNoteService::class)->issue($cn, $user);

    $after = (float) InventoryStock::withoutGlobalScopes()->where('product_id', $item->product_id)->where('warehouse_id', $ctx['warehouse']->id)->sum('qty_available');
    $returnJournal = JournalEntry::where('source_type', CustomerReturn::class)->where('source_id', $return->id)->get();
    $cnJournal = JournalEntry::where('source_type', CreditNote::class)->where('source_id', $cn->id)->get();
    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();

    expect($after - $before)->toBe(3.0)                                                                     // stok kembali sekali
        ->and(StockMovement::where('from_model_type', CustomerReturn::class)->where('from_model_id', $return->id)->count())->toBe(1)
        ->and((float) $returnJournal->sum('debit'))->toBe((float) $returnJournal->sum('credit'))
        ->and($returnJournal->pluck('coa_id')->intersect($cnJournal->pluck('coa_id')))->toHaveCount(0)     // akun jurnal tidak beririsan (tidak digandakan)
        ->and((float) $cnJournal->sum('debit'))->toBe(333000.0)->and((float) $cnJournal->sum('credit'))->toBe(333000.0)
        ->and((float) $ar->remaining)->toBe(999000.0);
});

it('laporan: invoice cancelled tidak dihitung; Nota Kredit sebagian mengurangi DPP/PPN/total dan HPP hanya untuk retur fisik', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice, $item] = ctlCreditInvoice($ctx);   // 12 × 100.000, PPN 11% → 1.332.000; HPP estimasi = 12 × cost_price
    $item->forceFill(['cogs_amount' => 600000, 'cogs_source' => 'journal'])->save();   // HPP snapshot 600.000 (50.000/unit)
    $service = app(CreditNoteService::class);

    $before = crSalesReport()->invoiceRow($invoice->fresh());
    expect($before['dpp'])->toBe(1200000.0)->and($before['total'])->toBe(1332000.0)->and($before['hpp'])->toBe(600000.0)->and($before['credit_note_total'])->toBe(0.0);

    $service->issue($service->draft($invoice, CreditNote::TYPE_RETURN, [$item->id => 3], 'Retur fisik tiga unit dari invoice', actor: $user), $user);
    $service->issue($service->draft($invoice, CreditNote::TYPE_CORRECTION, [$item->id => 1], 'Koreksi harga satu unit saja sesuai nota', actor: $user), $user);

    $row = crSalesReport()->invoiceRow($invoice->fresh());
    // Kredit: 4 unit → DPP −400.000, PPN −44.000; HPP berkurang hanya 3 unit retur fisik (600.000 × 9/12 = 450.000)
    expect($row['dpp'])->toBe(800000.0)->and($row['ppn'])->toBe(88000.0)->and($row['total'])->toBe(888000.0)->and($row['credit_note_total'])->toBe(444000.0)
        ->and($row['hpp'])->toBe(450000.0)->and($row['other_fees'])->toBe(0.0)
        ->and($row['lines']->first()['quantity'])->toBe(8.0)->and($row['lines']->first()['total'])->toBe(888000.0);

    $rows = crSalesReport()->invoiceQuery(['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()], $user)->get();
    expect($rows->pluck('id')->all())->toContain($invoice->id);
    $summary = crSalesReport()->invoiceSummary($rows->map(fn ($i) => crSalesReport()->invoiceRow($i)));
    expect($summary['total_amount'])->toBe(888000.0)->and($summary['total_dpp'])->toBe(800000.0);
});

it('laporan: pembatalan penuh → invoice cancelled keluar dari laporan (penjualan bersih nol), invoice lain tetap', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$cancelled] = ctlCreditInvoice($ctx);
    [$kept] = ctlCreditInvoice($ctx);
    $service = app(CreditNoteService::class);

    $service->issue($service->draft($cancelled, CreditNote::TYPE_CANCELLATION, [], 'Invoice salah nominal, dibatalkan penuh', actor: $user), $user);

    $filters = ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()];
    $ids = crSalesReport()->invoiceQuery($filters, $user)->pluck('id')->all();

    expect($cancelled->fresh()->status)->toBe('cancelled')->and($ids)->not->toContain($cancelled->id)->and($ids)->toContain($kept->id);
});

it('laporan: tanpa Nota Kredit terbit angkanya identik dengan perilaku lama dan tidak menambah kueri credit_notes per baris', function () {
    $ctx = stkContext();
    $user = crUser($ctx);
    [$invoice] = ctlCreditInvoice($ctx);
    [$invoice2] = ctlCreditInvoice($ctx);

    $queries = [];
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'credit_note')) {
            $queries[] = $query->sql;
        }
    });
    $service = crSalesReport();
    $rows = collect([$invoice, $invoice2])->map(fn ($i) => $service->invoiceRow($i->fresh()));

    expect($rows->pluck('total')->all())->toBe([1332000.0, 1332000.0])->and($rows->pluck('credit_note_total')->all())->toBe([0.0, 0.0])
        ->and($queries)->toHaveCount(2)                                              // cek tabel + keberadaan NK terbit: satu kali per instance, bukan per baris
        ->and(collect($queries)->filter(fn ($sql) => str_contains($sql, 'invoice_id'))->all())->toBe([]);
});

it('invoice cancelled tidak menghalangi Surat Jalannya ditagih ulang (filter status canceled/cancelled)', function () {
    $needle = "whereNotIn('status', ['canceled', 'cancelled'])";
    foreach (['app/Filament/Resources/SalesInvoiceResource.php', 'app/Filament/Resources/SalesInvoiceResource/Pages/CreateSalesInvoice.php', 'app/Observers/DeliveryOrderObserver.php'] as $file) {
        expect(file_get_contents(base_path($file)))->toContain($needle);
    }
});
