<?php

use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it can create a voucher request', function () {
    $voucher = VoucherRequest::factory()->create([
        'amount' => 1000000,
        'related_party' => 'PT Test',
        'status' => 'draft',
    ]);

    expect($voucher)->toBeInstanceOf(VoucherRequest::class)
        ->and($voucher->amount)->toBe(1000000.00)
        ->and($voucher->related_party)->toBe('PT Test')
        ->and($voucher->status)->toBe('draft');
});

test('it auto generates voucher number on creation', function () {
    $voucher = VoucherRequest::factory()->create();

    expect($voucher->voucher_number)->not->toBeNull()
        ->and($voucher->voucher_number)->toStartWith('VR-')
        ->and($voucher->voucher_number)->toMatch('/^VR-\d{8}-\d{4}$/');
});

test('it sets default status to draft', function () {
    $voucher = VoucherRequest::factory()->create();

    expect($voucher->status)->toBe('draft');
});

test('it can check if voucher can be submitted', function () {
    $draftVoucher = VoucherRequest::factory()->create(['status' => 'draft']);
    $pendingVoucher = VoucherRequest::factory()->create(['status' => 'pending']);

    expect($draftVoucher->canBeSubmitted())->toBeTrue()
        ->and($pendingVoucher->canBeSubmitted())->toBeFalse();
});

test('it can check if voucher can be approved', function () {
    $draftVoucher = VoucherRequest::factory()->create(['status' => 'draft']);
    $pendingVoucher = VoucherRequest::factory()->create(['status' => 'pending']);
    $approvedVoucher = VoucherRequest::factory()->create(['status' => 'approved']);

    expect($draftVoucher->canBeApproved())->toBeFalse()
        ->and($pendingVoucher->canBeApproved())->toBeTrue()
        ->and($approvedVoucher->canBeApproved())->toBeFalse();
});

test('it can check if voucher can be rejected', function () {
    $pendingVoucher = VoucherRequest::factory()->create(['status' => 'pending']);
    $approvedVoucher = VoucherRequest::factory()->create(['status' => 'approved']);

    expect($pendingVoucher->canBeRejected())->toBeTrue()
        ->and($approvedVoucher->canBeRejected())->toBeFalse();
});

test('it can check if voucher can be cancelled', function () {
    $draftVoucher = VoucherRequest::factory()->create(['status' => 'draft']);
    $pendingVoucher = VoucherRequest::factory()->create(['status' => 'pending']);
    $approvedVoucher = VoucherRequest::factory()->create(['status' => 'approved']);

    expect($draftVoucher->canBeCancelled())->toBeTrue()
        ->and($pendingVoucher->canBeCancelled())->toBeTrue()
        ->and($approvedVoucher->canBeCancelled())->toBeFalse();
});

test('it can check if voucher can be edited', function () {
    $draftVoucher = VoucherRequest::factory()->create(['status' => 'draft']);
    $pendingVoucher = VoucherRequest::factory()->create(['status' => 'pending']);

    expect($draftVoucher->canBeEdited())->toBeTrue()
        ->and($pendingVoucher->canBeEdited())->toBeFalse();
});

test('it has correct status colors', function () {
    $voucher = VoucherRequest::factory()->create();

    $voucher->status = 'draft';
    expect($voucher->getStatusColor())->toBe('gray');

    $voucher->status = 'pending';
    expect($voucher->getStatusColor())->toBe('warning');

    $voucher->status = 'approved';
    expect($voucher->getStatusColor())->toBe('success');

    $voucher->status = 'rejected';
    expect($voucher->getStatusColor())->toBe('danger');

    $voucher->status = 'cancelled';
    expect($voucher->getStatusColor())->toBe('secondary');
});

test('it has correct status labels', function () {
    $voucher = VoucherRequest::factory()->create();

    $voucher->status = 'draft';
    expect($voucher->getStatusLabel())->toBe('Draft');

    $voucher->status = 'pending';
    expect($voucher->getStatusLabel())->toBe('Menunggu Persetujuan');

    $voucher->status = 'approved';
    expect($voucher->getStatusLabel())->toBe('Disetujui');
});

test('it belongs to creator', function () {
    $voucher = VoucherRequest::factory()->create(['created_by' => $this->user->id]);

    expect($voucher->creator)->toBeInstanceOf(User::class)
        ->and($voucher->creator->id)->toBe($this->user->id);
});

test('it belongs to approver', function () {
    $approver = User::factory()->create();
    $voucher = VoucherRequest::factory()->create([
        'approved_by' => $approver->id,
        'status' => 'approved',
    ]);

    expect($voucher->approver)->toBeInstanceOf(User::class)
        ->and($voucher->approver->id)->toBe($approver->id);
});

test('it has scope for draft status', function () {
    VoucherRequest::factory()->create(['status' => 'draft']);
    VoucherRequest::factory()->create(['status' => 'pending']);
    VoucherRequest::factory()->create(['status' => 'draft']);

    $draftVouchers = VoucherRequest::draft()->get();

    expect($draftVouchers)->toHaveCount(2)
        ->and($draftVouchers->every(fn($v) => $v->status === 'draft'))->toBeTrue();
});

test('it has scope for pending status', function () {
    VoucherRequest::factory()->create(['status' => 'draft']);
    VoucherRequest::factory()->create(['status' => 'pending']);
    VoucherRequest::factory()->create(['status' => 'pending']);

    $pendingVouchers = VoucherRequest::pending()->get();

    expect($pendingVouchers)->toHaveCount(2)
        ->and($pendingVouchers->every(fn($v) => $v->status === 'pending'))->toBeTrue();
});

test('it has scope for approved status', function () {
    VoucherRequest::factory()->create(['status' => 'approved']);
    VoucherRequest::factory()->create(['status' => 'pending']);
    VoucherRequest::factory()->create(['status' => 'approved']);

    $approvedVouchers = VoucherRequest::approved()->get();

    expect($approvedVouchers)->toHaveCount(2)
        ->and($approvedVouchers->every(fn($v) => $v->status === 'approved'))->toBeTrue();
});

test('it casts amount to decimal', function () {
    $voucher = VoucherRequest::factory()->create(['amount' => 1234567.89]);

    expect($voucher->amount)->toBeFloat()
        ->and($voucher->amount)->toBe(1234567.89);
});

test('it soft deletes', function () {
    $voucher = VoucherRequest::factory()->create();
    $voucherId = $voucher->id;

    $voucher->delete();

    expect($voucher->fresh()->deleted_at)->not->toBeNull();
    $this->assertSoftDeleted('voucher_requests', ['id' => $voucherId]);
});
