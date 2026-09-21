<?php

/**
 * T3.3 — DocumentLock (flag sales.controls.doc_lock): satu sumber kunci untuk policy/aksi/API, tes berbasis data
 * (dokumen × status × aksi), Batalkan Penerimaan (jurnal balik, piutang kembali), peran fiktif dirapikan (X8).
 */

use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Models\CustomerReturn;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Policies\CustomerReceiptPolicy;
use App\Policies\CustomerReturnPolicy;
use App\Policies\DeliveryOrderPolicy;
use App\Policies\DeliverySchedulePolicy;
use App\Services\CustomerReceiptCancellation;
use App\Services\DocumentLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/** Dokumen tak tersimpan dengan status tertentu (DocumentLock hanya membaca status). */
function lockDoc(string $class, string|int $status): \Illuminate\Database\Eloquent\Model
{
    $model = new $class;
    if ($class === SuratJalan::class) {   // status Surat Jalan bertipe angka
        $status = ['draft' => SuratJalan::STATUS_DRAFT, 'issued' => SuratJalan::STATUS_ISSUED, 'cancelled' => SuratJalan::STATUS_CANCELLED][$status];
    }
    $model->setAttribute('status', $status);

    return $model;
}

// ------------------------------------------------------------------ matriks (data)

$matrix = [
    // [kelas, status, update boleh?, delete boleh?]
    [Quotation::class, 'draft', true, true], [Quotation::class, 'request_approve', false, false], [Quotation::class, 'approve', false, false],
    [Quotation::class, 'reject', true, true], [Quotation::class, 'expired', false, false],
    [SaleOrder::class, 'draft', true, true], [SaleOrder::class, 'request_approve', true, false], [SaleOrder::class, 'approved', false, false],
    [SaleOrder::class, 'partially_delivered', false, false], [SaleOrder::class, 'completed', false, false], [SaleOrder::class, 'canceled', false, false],
    [DeliveryOrder::class, 'draft', true, true], [DeliveryOrder::class, 'request_stock', true, false], [DeliveryOrder::class, 'request_approve', true, false],
    [DeliveryOrder::class, 'request_close', true, false], [DeliveryOrder::class, 'approved', false, false], [DeliveryOrder::class, 'sent', false, false],
    [DeliveryOrder::class, 'received', false, false], [DeliveryOrder::class, 'completed', false, false], [DeliveryOrder::class, 'delivery_failed', false, false],
    [SuratJalan::class, 'draft', true, true], [SuratJalan::class, 'issued', false, false], [SuratJalan::class, 'cancelled', false, false],
    [DeliverySchedule::class, 'pending', true, true], [DeliverySchedule::class, 'on_the_way', false, false], [DeliverySchedule::class, 'delivered', false, false],
    [DeliverySchedule::class, 'failed', false, true], [DeliverySchedule::class, 'cancelled', false, true],
    [Invoice::class, 'draft', true, true], [Invoice::class, 'unpaid', false, false], [Invoice::class, 'sent', false, false], [Invoice::class, 'paid', false, false],
    [CustomerReturn::class, 'pending', true, true], [CustomerReturn::class, 'received', true, false], [CustomerReturn::class, 'qc_inspection', true, false],
    [CustomerReturn::class, 'approved', false, false], [CustomerReturn::class, 'completed', false, false],
    [CustomerReceipt::class, 'Draft', true, true], [CustomerReceipt::class, 'Partial', false, false], [CustomerReceipt::class, 'Paid', false, false],
    [CustomerReceipt::class, 'Cancelled', false, false],
];

it('matriks: setiap dokumen × status × aksi terkunci/terbuka sesuai tabel', function (string $class, string $status, bool $canUpdate, bool $canDelete) {
    $lock = app(DocumentLock::class);
    $document = lockDoc($class, $status);

    expect($lock->isLocked($document, 'update'))->toBe(! $canUpdate)
        ->and($lock->isLocked($document, 'delete'))->toBe(! $canDelete);
})->with($matrix);

it('dokumen terkunci mengembalikan alasan dan jalur koreksi (bukan "buka kunci")', function () {
    $check = app(DocumentLock::class)->check(lockDoc(DeliveryOrder::class, 'sent'), 'update');

    expect($check['locked'])->toBeTrue()
        ->and($check['reason'])->toContain('Delivery Order')->and($check['reason'])->toContain('sent')
        ->and($check['correction'])->toContain('Batalkan DO');
    expect(app(DocumentLock::class)->message(lockDoc(CustomerReceipt::class, 'Paid'), 'update'))->toContain('Batalkan Penerimaan');
});

it('kelas dokumen tak dikenal tidak pernah terkunci; aksi yang tidak diatur dianggap terbuka', function () {
    expect(app(DocumentLock::class)->isLocked(new \App\Models\Customer))->toBeFalse();
});

it('penerimaan berjurnal terkunci walau statusnya Draft (jurnal ada)', function () {
    $ctx = stkContext();
    $invoice = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 5000000);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 5000000);
    $receipt->forceFill(['status' => 'Draft'])->saveQuietly();

    expect(app(DocumentLock::class)->isLocked($receipt->fresh(), 'update'))->toBeTrue();
});

function ctlSoForLock(array $ctx): SaleOrder
{
    [$so] = stkSaleOrder($ctx, 1, ['status' => 'completed', 'total_amount' => 5000000]);

    return $so;
}

// ------------------------------------------------------------------ policy mengikuti matriks (satu sumber)

it('policy DO/Jadwal/Penerimaan/Retur mengikuti DocumentLock bila flag hidup — untuk setiap status', function () {
    config(['sales.controls.doc_lock' => true]);
    $ctx = stkContext();
    $user = ctlUser($ctx, 'Admin', [
        'update delivery order', 'delete delivery order', 'update delivery schedule', 'delete delivery schedule',
        'update customer receipt', 'delete customer receipt', 'update customer return', 'delete customer return',
    ]);
    $lock = app(DocumentLock::class);

    $cases = [
        [new DeliveryOrderPolicy, DeliveryOrder::class, ['draft', 'request_stock', 'request_approve', 'request_close', 'approved', 'sent', 'received', 'completed', 'delivery_failed', 'closed', 'reject']],
        [new DeliverySchedulePolicy, DeliverySchedule::class, ['pending', 'on_the_way', 'partial_delivered', 'delivered', 'failed', 'cancelled']],
        [new CustomerReceiptPolicy, CustomerReceipt::class, ['Draft', 'Partial', 'Paid', 'Cancelled']],
        [new CustomerReturnPolicy, CustomerReturn::class, ['pending', 'received', 'qc_inspection', 'approved', 'rejected', 'completed']],
    ];

    foreach ($cases as [$policy, $class, $statuses]) {
        foreach ($statuses as $status) {
            $document = lockDoc($class, $status);
            expect($policy->update($user, $document))->toBe(! $lock->isLocked($document, 'update'), "{$class} update @ {$status}")
                ->and($policy->delete($user, $document))->toBe(! $lock->isLocked($document, 'delete'), "{$class} delete @ {$status}");
        }
    }
});

it('celah G4 tertutup: DO Dikirim/Selesai tidak dapat di-Edit oleh pemegang izin update (tabel & halaman)', function () {
    config(['sales.controls.doc_lock' => true]);
    $ctx = stkContext();
    $user = ctlUser($ctx, 'Sales Manager', ['update delivery order']);
    $policy = new DeliveryOrderPolicy;

    expect($policy->update($user, lockDoc(DeliveryOrder::class, 'sent')))->toBeFalse()
        ->and($policy->update($user, lockDoc(DeliveryOrder::class, 'completed')))->toBeFalse()
        ->and($policy->update($user, lockDoc(DeliveryOrder::class, 'request_stock')))->toBeTrue();
});

it('X8: peran fiktif "Super Sales" tidak lagi dirujuk policy DO', function () {
    expect(file_get_contents(app_path('Policies/DeliveryOrderPolicy.php')))->not->toContain('Super Sales');
});

it('[flag mati] policy berperilaku lama: hanya izin (DO Dikirim masih dapat diubah oleh pemegang izin)', function () {
    config(['sales.controls.doc_lock' => false]);
    $ctx = stkContext();
    $user = ctlUser($ctx, 'Sales Manager', ['update delivery order', 'update customer receipt', 'update delivery schedule']);

    expect((new DeliveryOrderPolicy)->update($user, lockDoc(DeliveryOrder::class, 'sent')))->toBeTrue()
        ->and((new CustomerReceiptPolicy)->update($user, lockDoc(CustomerReceipt::class, 'Paid')))->toBeTrue()
        ->and((new DeliverySchedulePolicy)->update($user, lockDoc(DeliverySchedule::class, 'delivered')))->toBeTrue();
});

// ------------------------------------------------------------------ Batalkan Penerimaan

it('Batalkan Penerimaan: jurnal dibalik (cermin, asli tetap), piutang & status invoice kembali, status Cancelled + alasan tercatat', function () {
    $ctx = stkContext();
    $so = ctlSoForLock($ctx);
    $invoice = ctlInvoiceWithAr($ctx, $so, 5000000);
    [$receipt, $bank, $piutang] = ctlPaidReceipt($ctx, $invoice, 5000000);
    expect(AccountReceivable::where('invoice_id', $invoice->id)->first()->remaining)->toEqual(0);

    app(CustomerReceiptCancellation::class)->cancel($receipt, 'Transfer ternyata salah rekening, dana dikembalikan', $ctx['user']);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    $entries = JournalEntry::where('source_type', CustomerReceipt::class)->where('source_id', $receipt->id)->get();
    $bankNet = $entries->where('coa_id', $bank->id)->sum('debit') - $entries->where('coa_id', $bank->id)->sum('credit');
    $arNet = $entries->where('coa_id', $piutang->id)->sum('debit') - $entries->where('coa_id', $piutang->id)->sum('credit');

    expect($receipt->fresh()->status)->toBe('Cancelled')
        ->and($receipt->fresh()->cancel_reason)->toContain('salah rekening')
        ->and((int) $receipt->fresh()->cancelled_by)->toBe($ctx['user']->id)
        ->and($receipt->fresh()->cancelled_at)->not->toBeNull()
        ->and((float) $ar->remaining)->toBe(5000000.0)->and((float) $ar->paid)->toBe(0.0)
        ->and($ar->status)->toBe('Belum Lunas')
        ->and($invoice->fresh()->status)->toBe('unpaid')
        ->and($entries)->toHaveCount(4)                               // 2 asli + 2 cermin
        ->and($entries->where('is_reversal', true))->toHaveCount(2)
        ->and((float) $entries->sum('debit'))->toBe((float) $entries->sum('credit'))
        ->and((float) $bankNet)->toBe(0.0)->and((float) $arNet)->toBe(0.0);   // saldo bersih nol
});

it('Batalkan Penerimaan parsial: piutang kembali hanya sebesar penerimaan itu; status invoice partially_paid → unpaid sesuai sisa', function () {
    $ctx = stkContext();
    $invoice = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 10000000);
    [$first] = ctlPaidReceipt($ctx, $invoice, 4000000);
    expect($invoice->fresh()->status)->toBe('partially_paid');

    app(CustomerReceiptCancellation::class)->cancel($first, 'Pembayaran pertama dibatalkan oleh customer', $ctx['user']);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->remaining)->toBe(10000000.0)->and((float) $ar->paid)->toBe(0.0)->and($invoice->fresh()->status)->toBe('unpaid');
});

it('Batalkan Penerimaan: alasan wajib, tidak dapat dua kali, hanya penerimaan berjurnal; deposit ditolak', function () {
    $ctx = stkContext();
    $invoice = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 5000000);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 5000000);
    $service = app(CustomerReceiptCancellation::class);

    expect(fn () => $service->cancel($receipt, 'singkat'))->toThrow(ValidationException::class, 'minimal 10');
    expect($receipt->fresh()->status)->toBe('Paid');

    $service->cancel($receipt, 'Alasan pembatalan yang cukup panjang', $ctx['user']);
    expect(fn () => $service->cancel($receipt->fresh(), 'Alasan pembatalan yang cukup panjang'))->toThrow(ValidationException::class, 'sudah dibatalkan');
    expect(JournalEntry::where('source_id', $receipt->id)->where('is_reversal', true)->count())->toBe(2);   // tidak digandakan

    // deposit: ditolak dengan pesan jelas
    $invoice2 = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 3000000);
    [$depositReceipt] = ctlPaidReceipt($ctx, $invoice2, 3000000, 'Deposit');
    expect(fn () => $service->cancel($depositReceipt, 'Alasan pembatalan yang cukup panjang'))->toThrow(ValidationException::class, 'deposit');
    expect($depositReceipt->fresh()->status)->toBe('Paid');

    // Draft: bukan untuk dibatalkan
    $draft = CustomerReceipt::withoutEvents(fn () => CustomerReceipt::create([
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'total_payment' => 1000, 'payment_method' => 'Transfer', 'status' => 'Draft',
        'created_by' => $ctx['user']->id, 'cabang_id' => $ctx['cabang']->id,
    ]));
    expect(fn () => $service->cancel($draft, 'Alasan pembatalan yang cukup panjang'))->toThrow(ValidationException::class, 'Draft');
});

it('penerimaan yang dibatalkan tidak memicu posting ulang jurnal saat disimpan lagi', function () {
    $ctx = stkContext();
    $invoice = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 5000000);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 5000000);
    app(CustomerReceiptCancellation::class)->cancel($receipt, 'Alasan pembatalan yang cukup panjang', $ctx['user']);
    $count = JournalEntry::where('source_id', $receipt->id)->where('source_type', CustomerReceipt::class)->count();

    $receipt->fresh()->update(['notes' => 'catatan setelah batal']);

    expect(JournalEntry::where('source_id', $receipt->id)->where('source_type', CustomerReceipt::class)->count())->toBe($count);
});

it('penerimaan dengan kelebihan bayar (menghasilkan deposit) tidak dapat dibatalkan otomatis', function () {
    $ctx = stkContext();
    $invoice = ctlInvoiceWithAr($ctx, ctlSoForLock($ctx), 5000000);
    [$receipt] = ctlPaidReceipt($ctx, $invoice, 5000000);
    $receipt->forceFill(['overpayment_amount' => 250000])->saveQuietly();

    expect(app(CustomerReceiptCancellation::class)->blocker($receipt->fresh()))->toContain('deposit');
    expect(fn () => app(CustomerReceiptCancellation::class)->cancel($receipt->fresh(), 'Alasan pembatalan yang cukup panjang'))
        ->toThrow(ValidationException::class, 'deposit');
});
