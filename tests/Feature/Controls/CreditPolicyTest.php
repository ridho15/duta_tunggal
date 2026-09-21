<?php

/**
 * T3.2 — kebijakan kredit (flag sales.controls.credit_policy): paparan = piutang + SO belum ditagih (D27), Kredit = blokir,
 * COD/Bebas = informasi, limit 0 tidak diizinkan (D28), pengecualian beralasan oleh peran tinggi (D24), Info Customer semua tipe.
 */

use App\Models\ApprovalOverride;
use App\Models\Customer;
use App\Services\CreditExposure;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.controls.credit_policy' => true]);
});

/** Customer Kredit dengan limit; SO baru bernilai $amount menunggu persetujuan. */
function crdCustomer(array &$ctx, string $type = 'Kredit', float $limit = 100000000): Customer
{
    $ctx['customer']->update(['tipe_pembayaran' => $type, 'kredit_limit' => $limit, 'tempo_kredit' => 30]);

    return $ctx['customer']->fresh();
}

function crdSo(array $ctx, float $amount, string $status = 'request_approve'): \App\Models\SaleOrder
{
    [$so] = stkSaleOrder($ctx, 1, ['status' => $status, 'total_amount' => $amount]);

    return $so;
}

function crdApproverContext(array &$ctx, string $role = 'Sales Manager'): \App\Models\User
{
    $user = ctlUser($ctx, $role);
    Auth::login($user);

    return $user;
}

it('paparan = piutang berjalan + SO aktif yang belum ditagih (invoice parsial mengurangi sisa SO)', function () {
    $ctx = stkContext();
    $customer = crdCustomer($ctx);
    $exposure = app(CreditExposure::class);

    ctlInvoiceWithAr($ctx, crdSo($ctx, 40000000, 'completed'), 20000000);        // piutang 20 jt (SO selesai: tidak dihitung sebagai SO terbuka)
    $open = crdSo($ctx, 30000000, 'approved');                                    // SO terbuka 30 jt
    $partial = crdSo($ctx, 10000000, 'partially_delivered');
    ctlInvoiceWithAr($ctx, $partial, 4000000);                                    // 4 jt sudah ditagih → sisa 6 jt; AR +4 jt

    expect($exposure->receivables($customer))->toBe(24000000.0)
        ->and($exposure->openSalesOrders($customer))->toBe(['total' => 36000000.0, 'count' => 2])
        ->and($exposure->exposure($customer))->toBe(60000000.0);
});

it('SO yang sudah seluruhnya ditagih tidak lagi dihitung sebagai SO terbuka (hanya piutangnya)', function () {
    $ctx = stkContext();
    $customer = crdCustomer($ctx);
    $so = crdSo($ctx, 10000000, 'partially_delivered');
    ctlInvoiceWithAr($ctx, $so, 10000000);

    expect(app(CreditExposure::class)->openSalesOrders($customer))->toBe(['total' => 0.0, 'count' => 0])
        ->and(app(CreditExposure::class)->exposure($customer))->toBe(10000000.0);
});

it('D27: SO yang membuat paparan melebihi limit tidak dapat disetujui; tanpa flag hanya piutang yang dihitung (perilaku lama)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    crdCustomer($ctx);
    crdApproverContext($ctx);
    ctlInvoiceWithAr($ctx, crdSo($ctx, 20000000, 'completed'), 20000000);    // piutang 20 jt
    crdSo($ctx, 30000000, 'approved');                                        // SO terbuka 30 jt → paparan 50 jt
    $new = crdSo($ctx, 60000000);                                             // 50 + 60 = 110 jt > 100 jt

    expect(fn () => app(SalesOrderService::class)->approve($new))->toThrow(ValidationException::class, 'Kredit limit tidak mencukupi');
    expect($new->fresh()->status)->toBe('request_approve');

    config(['sales.controls.credit_policy' => false]);   // perilaku lama: 20 + 60 = 80 jt ≤ 100 jt lolos
    app(SalesOrderService::class)->approve($new->fresh());
    expect($new->fresh()->status)->toBe('approved');
});

it('paparan memasukkan SO yang baru disetujui: SO kedua ditolak setelah SO pertama menghabiskan limit (uji berurutan, penjaga balapan)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    crdCustomer($ctx, 'Kredit', 100000000);
    crdApproverContext($ctx);
    $first = crdSo($ctx, 60000000);
    $second = crdSo($ctx, 60000000);
    $service = app(SalesOrderService::class);

    $service->approve($first);
    expect(fn () => $service->approve($second))->toThrow(ValidationException::class, 'Kredit limit tidak mencukupi');

    expect($first->fresh()->status)->toBe('approved')->and($second->fresh()->status)->toBe('request_approve');
});

it('D28: limit 0 pada tipe Kredit tidak diizinkan', function () {
    $ctx = stkContext();
    crdCustomer($ctx, 'Kredit', 0);
    crdApproverContext($ctx);
    $so = crdSo($ctx, 1000);

    expect(fn () => app(SalesOrderService::class)->approve($so))->toThrow(ValidationException::class, 'kredit limit yang valid');
});

it('D7: COD dan Bebas tidak diblokir; ringkasan menandai kebijakan informasi', function (string $type, string $policy) {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $customer = crdCustomer($ctx, $type, 0);
    crdApproverContext($ctx);
    ctlInvoiceWithAr($ctx, crdSo($ctx, 5000000, 'approved'), 5000000, 5000000, 'unpaid', now()->subDays(10)->toDateString());   // jatuh tempo
    $so = crdSo($ctx, 90000000);

    app(SalesOrderService::class)->approve($so);

    $summary = app(CreditExposure::class)->summary($customer);
    expect($so->fresh()->status)->toBe('approved')
        ->and($summary['policy'])->toBe($policy)
        ->and($summary['receivables'])->toBe(5000000.0)
        ->and($summary['overdue_count'])->toBe(1)
        ->and($summary['oldest_overdue_days'])->toBeGreaterThanOrEqual(10);
})->with([['COD (Bayar Lunas)', 'cash'], ['Bebas', 'info']]);

it('pengecualian beralasan: hanya Owner/Super Admin/Finance Manager, alasan ≥ 10 karakter, tercatat di audit', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    crdCustomer($ctx, 'Kredit', 10000000);
    $service = app(SalesOrderService::class);
    $so = crdSo($ctx, 50000000);
    $reason = 'Customer strategis, pembayaran DP sudah masuk';

    // Sales Manager berwenang menyetujui SO tetapi BUKAN peran pengecualian kredit
    crdApproverContext($ctx, 'Sales Manager');
    expect(fn () => $service->approve($so, ['credit_override_reason' => $reason]))->toThrow(ValidationException::class, 'Kredit limit tidak mencukupi');

    // Peran tinggi tanpa alasan / alasan pendek: ditolak
    crdApproverContext($ctx, 'Finance Manager');
    expect(fn () => $service->approve($so))->toThrow(ValidationException::class);
    expect(fn () => $service->approve($so, ['credit_override_reason' => 'singkat']))->toThrow(ValidationException::class);
    expect(ApprovalOverride::count())->toBe(0);

    $service->approve($so->fresh(), ['credit_override_reason' => $reason]);

    $override = ApprovalOverride::firstOrFail();
    expect($so->fresh()->status)->toBe('approved')
        ->and($override->context['kind'])->toBe('credit_limit')
        ->and($override->reason)->toBe($reason)
        ->and((float) $override->amount)->toBe(50000000.0);
});

it('Info Customer: endpoint ringkas mengembalikan paparan untuk SEMUA tipe pembayaran', function () {
    $ctx = stkContext();
    $ctx['user']->givePermissionTo(['create sales order', 'view any sales order', 'view sales order']);
    $customer = crdCustomer($ctx, 'Bebas', 0);
    ctlInvoiceWithAr($ctx, crdSo($ctx, 7000000, 'completed'), 7000000);
    crdSo($ctx, 3000000, 'approved');

    $response = $this->actingAs($ctx['user'])->getJson("/api/v1/sales-orders/customer-credit/{$customer->id}")->assertOk();

    expect($response->json('data.payment_type'))->toBe('Bebas')
        ->and($response->json('data.receivables'))->toEqual(7000000)
        ->and($response->json('data.open_sales_orders_total'))->toEqual(3000000)
        ->and($response->json('data.exposure'))->toEqual(10000000)
        ->and($response->json('data.policy'))->toBe('info')
        ->and($response->json('data'))->toHaveKeys(['credit_limit', 'available_credit', 'usage_percentage', 'overdue_count', 'overdue_total', 'deposit_balance']);   // kunci lama tetap ada
});

it('[flag mati] perilaku lama: pemakaian = piutang saja dan tidak ada pengecualian kredit', function () {
    config(['sales.controls.credit_policy' => false]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    crdCustomer($ctx, 'Kredit', 100000000);
    crdApproverContext($ctx, 'Finance Manager');
    crdSo($ctx, 90000000, 'approved');                 // SO terbuka 90 jt tidak dihitung pada perilaku lama
    $so = crdSo($ctx, 50000000);

    app(SalesOrderService::class)->approve($so);

    expect($so->fresh()->status)->toBe('approved')->and(ApprovalOverride::count())->toBe(0);
});

it('penjaga balapan: baris customer dikunci (SELECT … FOR UPDATE) selama persetujuan SO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    crdCustomer($ctx);
    crdApproverContext($ctx);
    $so = crdSo($ctx, 1000);

    $queries = [];
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });
    app(SalesOrderService::class)->approve($so);

    $locks = array_filter($queries, fn ($sql) => stripos($sql, 'from `customers`') !== false && stripos($sql, 'for update') !== false);
    // dua kunci: penguncian awal persetujuan (menahan sampai SO tersimpan) + pemeriksaan limit; tanpa kunci awal hanya tersisa satu
    expect(count($locks))->toBeGreaterThanOrEqual(2);
});
