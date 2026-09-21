<?php

/**
 * T3.1 — aturan persetujuan (flag sales.controls.approval_rules): ambang & peran dari tabel, penegakan di service,
 * pemisahan tugas Quotation, override Owner/Super Admin wajib alasan + tercatat.
 */

use App\Models\ApprovalOverride;
use App\Models\ApprovalRule;
use App\Services\ApprovalControlService;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function ctlSo(array $ctx, float $amount, $creator = null): \App\Models\SaleOrder
{
    [$so] = stkSaleOrder($ctx, 1, ['status' => 'request_approve', 'total_amount' => $amount, 'created_by' => ($creator ?? $ctx['user'])->id]);

    return $so;
}

beforeEach(function () {
    config(['sales.controls.approval_rules' => true]);
});

it('nilai awal tabel = konstanta lama (dua aturan per dokumen)', function () {
    $rules = ApprovalRule::orderBy('document_type')->orderBy('above_amount')->get();

    expect($rules)->toHaveCount(4)
        ->and($rules->pluck('document_type')->unique()->sort()->values()->all())->toBe(['quotation', 'sale_order'])
        ->and(ApprovalRule::forDocument('sale_order', 10000000)->approver_label)->toBe('Sales Manager / Direktur')      // batas atas inklusif
        ->and(ApprovalRule::forDocument('sale_order', 10000000.01)->approver_label)->toBe('Direktur / Owner / Finance Manager');
});

it('matriks ekuivalen: hasil aturan tabel = hasil konstanta lama untuk peran × nominal × pembuat (SO dan Quotation)', function (string $role, float $amount, bool $creator, bool $expected) {
    $ctx = stkContext();
    $user = ctlUser($ctx, $role);
    $so = ctlSo($ctx, $amount, $creator ? $user : $ctx['user']);
    $quotation = ctlQuotation($ctx, $amount, $creator ? $user : $ctx['user']);
    $service = app(ApprovalControlService::class);

    expect($service->canApprove($user, $so)['allowed'])->toBe($expected)
        ->and($service->canApprove($user, $quotation)['allowed'])->toBe($expected)
        ->and($service->canApproveSaleOrder($user, $so)['allowed'])->toBe($expected);
})->with([
    // [peran, nilai, penyetuju = pembuat?, diizinkan]
    ['Sales Manager', 5000000, false, true],
    ['Sales Manager', 10000000, false, true],
    ['Sales Manager', 10000001, false, false],   // di atas ambang: hanya peran puncak
    ['Sales Manager', 5000000, true, false],     // pembuat sendiri
    ['Finance Manager', 50000000, false, true],
    ['Admin', 50000000, false, true],
    ['Sales', 1000, false, false],               // bukan peran penyetuju
    ['Owner', 50000000, true, true],             // override darurat (butuh alasan saat enforce)
    ['Super Admin', 5000000, true, true],
]);

it('pesan penolakan identik dengan teks lama', function () {
    $ctx = stkContext();
    $service = app(ApprovalControlService::class);

    $big = $service->canApprove(ctlUser($ctx, 'Sales Manager'), ctlSo($ctx, 25000000));
    $regular = $service->canApprove(ctlUser($ctx, 'Sales'), ctlSo($ctx, 1000));
    $creator = ctlUser($ctx, 'Sales Manager');
    $self = $service->canApprove($creator, ctlSo($ctx, 1000, $creator));

    expect($big['reason'])->toBe('Persetujuan bertingkat: Nilai Sales Order di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager.')
        ->and($regular['reason'])->toBe('Persetujuan Sales Order membutuhkan wewenang Sales Manager / Direktur.')
        ->and($self['reason'])->toContain('Segregation of Duties');
});

it('ubah ambang di tabel → berlaku tanpa deploy', function () {
    $ctx = stkContext();
    $manager = ctlUser($ctx, 'Sales Manager');
    $so = ctlSo($ctx, 15000000);
    $service = app(ApprovalControlService::class);

    expect($service->canApprove($manager, $so)['allowed'])->toBeFalse();

    ApprovalRule::where('document_type', 'sale_order')->whereNull('above_amount')->update(['up_to_amount' => 20000000]);
    ApprovalRule::where('document_type', 'sale_order')->where('above_amount', 10000000)->update(['above_amount' => 20000000]);

    expect($service->canApprove($manager, $so)['allowed'])->toBeTrue()
        ->and($service->canApprove($manager, ctlSo($ctx, 20000001))['allowed'])->toBeFalse();
});

it('ubah peran di tabel → berlaku (peran baru boleh, peran lama tidak)', function () {
    $ctx = stkContext();
    $accounting = ctlUser($ctx, 'Accounting');
    $manager = ctlUser($ctx, 'Sales Manager');
    $so = ctlSo($ctx, 1000);

    ApprovalRule::where('document_type', 'sale_order')->whereNull('above_amount')->update(['roles' => ['Accounting']]);
    $service = app(ApprovalControlService::class);

    expect($service->canApprove($accounting, $so)['allowed'])->toBeTrue()
        ->and($service->canApprove($manager, $so)['allowed'])->toBeFalse();
});

it('penegakan DI SERVICE: SalesOrderService::approve dan QuotationService::approve menolak penyetuju yang tidak berwenang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = ctlSo($ctx, 1000);
    $quotation = ctlQuotation($ctx, 1000);

    Auth::login(ctlUser($ctx, 'Sales'));   // punya izin, bukan peran penyetuju
    expect(fn () => app(SalesOrderService::class)->approve($so))->toThrow(ValidationException::class, 'wewenang');
    expect(fn () => app(QuotationService::class)->approve($quotation))->toThrow(ValidationException::class, 'wewenang');
    expect($so->fresh()->status)->toBe('request_approve')->and($quotation->fresh()->status)->toBe('request_approve');

    Auth::login(ctlUser($ctx, 'Sales Manager'));
    app(SalesOrderService::class)->approve($so->fresh());
    app(QuotationService::class)->approve($quotation->fresh());
    expect($so->fresh()->status)->toBe('approved')->and($quotation->fresh()->status)->toBe('approve');
});

it('Quotation: pembuat tidak dapat menyetujui sendiri (D26)', function () {
    $ctx = stkContext();
    $creator = ctlUser($ctx, 'Sales Manager');
    $quotation = ctlQuotation($ctx, 1000, $creator);
    Auth::login($creator);

    expect(fn () => app(QuotationService::class)->approve($quotation))->toThrow(ValidationException::class, 'Segregation of Duties');
    expect($quotation->fresh()->status)->toBe('request_approve');
});

it('override Owner/Super Admin (menyetujui dokumen sendiri): alasan wajib ≥ 10 karakter dan tercatat di audit (D24)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $owner = ctlUser($ctx, 'Owner');
    $so = ctlSo($ctx, 1000, $owner);
    Auth::login($owner);
    $service = app(SalesOrderService::class);

    expect(app(ApprovalControlService::class)->requiresOverrideReason($owner, $so))->toBeTrue();
    expect(fn () => $service->approve($so))->toThrow(ValidationException::class, 'Alasan wajib');
    expect(fn () => $service->approve($so, ['override_reason' => 'singkat']))->toThrow(ValidationException::class, 'minimal 10');
    expect($so->fresh()->status)->toBe('request_approve')->and(ApprovalOverride::count())->toBe(0);

    $service->approve($so->fresh(), ['override_reason' => 'Direksi meminta pengiriman segera']);

    $override = ApprovalOverride::firstOrFail();
    expect($so->fresh()->status)->toBe('approved')
        ->and($override->document_type)->toBe('sale_order')->and($override->document_id)->toBe($so->id)
        ->and($override->user_id)->toBe($owner->id)->and($override->reason)->toContain('Direksi')
        ->and($override->context['kind'])->toBe('self_approval');
});

it('penyetuju lain (bukan pembuat) tidak butuh alasan override', function () {
    $ctx = stkContext();
    $owner = ctlUser($ctx, 'Owner');
    $so = ctlSo($ctx, 1000);   // dibuat pengguna lain

    expect(app(ApprovalControlService::class)->requiresOverrideReason($owner, $so))->toBeFalse();
});

it('QuotationPolicy::approve mengikuti aturan bila flag hidup dan izin saja bila mati', function () {
    $ctx = stkContext();
    $creator = ctlUser($ctx, 'Sales Manager');
    $quotation = ctlQuotation($ctx, 1000, $creator);
    $policy = new \App\Policies\QuotationPolicy;

    expect($policy->approve($creator, $quotation))->toBeFalse();   // pembuat sendiri

    config(['sales.controls.approval_rules' => false]);
    expect($policy->approve($creator, $quotation))->toBeTrue();    // perilaku lama: cukup izin
});

it('[flag mati] service tidak menegakkan aturan dan tidak butuh alasan override (perilaku lama)', function () {
    config(['sales.controls.approval_rules' => false]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = ctlSo($ctx, 1000);
    $quotation = ctlQuotation($ctx, 1000);
    Auth::login(ctlUser($ctx, 'Sales'));   // bukan peran penyetuju

    app(SalesOrderService::class)->approve($so);
    app(QuotationService::class)->approve($quotation);

    expect($so->fresh()->status)->toBe('approved')->and($quotation->fresh()->status)->toBe('approve')->and(ApprovalOverride::count())->toBe(0);
});

it('halaman Aturan Persetujuan hanya untuk Super Admin dan Finance Manager', function () {
    $ctx = stkContext();

    Auth::login(ctlUser($ctx, 'Sales Manager'));
    expect(\App\Filament\Resources\ApprovalRuleResource::canViewAny())->toBeFalse();

    Auth::login(ctlUser($ctx, 'Finance Manager'));
    expect(\App\Filament\Resources\ApprovalRuleResource::canViewAny())->toBeTrue();

    Auth::login(ctlUser($ctx, 'Super Admin'));
    expect(\App\Filament\Resources\ApprovalRuleResource::canViewAny())->toBeTrue();
});

it('perubahan aturan tercatat di log aktivitas', function () {
    $ctx = stkContext();
    $before = \Spatie\Activitylog\Models\Activity::count();

    ApprovalRule::where('document_type', 'sale_order')->whereNull('above_amount')->first()->update(['up_to_amount' => 12000000]);

    expect(\Spatie\Activitylog\Models\Activity::count())->toBeGreaterThan($before);
});
