<?php

use App\Models\User;
use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Spatie\Permission\Models\Role;

uses()->group('voucher');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    $this->actingAs($this->user);
    $this->service = app(VoucherRequestService::class);
});

test('can create voucher request with auto generated number', function () {
    $voucher = VoucherRequest::factory()->create([
        'amount' => 1000000,
        'related_party' => 'PT Test',
    ]);

    expect($voucher->voucher_number)->toMatch('/^VR-\d{8}-\d{4}$/')
        ->and((float)$voucher->amount)->toBe(1000000.00)
        ->and($voucher->status)->toBe('draft');
});

test('can submit voucher for approval', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'draft']);

    $this->service->submitForApproval($voucher);

    expect($voucher->fresh()->status)->toBe('pending');
});

test('cannot submit non-draft voucher', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'pending']);

    $this->service->submitForApproval($voucher);
})->throws(\Exception::class);

test('can approve pending voucher', function () {
    $voucher = VoucherRequest::factory()->pending()->create();
    
    $this->service->approve($voucher, ['approval_notes' => 'Approved']);

    $fresh = $voucher->fresh();
    expect($fresh->status)->toBe('approved')
        ->and($fresh->approved_by)->toBe($this->user->id)
        ->and($fresh->approved_at)->not->toBeNull();
});

test('can reject pending voucher', function () {
    $voucher = VoucherRequest::factory()->pending()->create();
    
    $this->service->reject($voucher, 'Not enough budget');

    $fresh = $voucher->fresh();
    expect($fresh->status)->toBe('rejected')
        ->and($fresh->approval_notes)->toContain('Not enough budget');
});

test('can cancel draft voucher', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'draft']);
    
    $this->service->cancel($voucher);

    expect($voucher->fresh()->status)->toBe('cancelled');
});

test('cannot cancel approved voucher', function () {
    $voucher = VoucherRequest::factory()->approved()->create();

    $this->service->cancel($voucher);
})->throws(\Exception::class);

test('generates statistics correctly', function () {
    VoucherRequest::factory()->count(2)->create(['status' => 'draft']);
    VoucherRequest::factory()->count(3)->create(['status' => 'pending']);
    VoucherRequest::factory()->count(1)->approved()->create();

    $stats = $this->service->getStatistics();

    expect($stats['total_requests'])->toBe(6)
        ->and($stats['draft'])->toBe(2)
        ->and($stats['pending'])->toBe(3)
        ->and($stats['approved'])->toBe(1);
});

test('model scopes work correctly', function () {
    VoucherRequest::factory()->create(['status' => 'draft']);
    VoucherRequest::factory()->create(['status' => 'pending']);
    VoucherRequest::factory()->approved()->create();

    expect(VoucherRequest::draft()->count())->toBe(1)
        ->and(VoucherRequest::pending()->count())->toBe(1)
        ->and(VoucherRequest::approved()->count())->toBe(1);
});

test('helper methods return correct values', function () {
    $draft = VoucherRequest::factory()->create(['status' => 'draft']);
    $pending = VoucherRequest::factory()->pending()->create();
    $approved = VoucherRequest::factory()->approved()->create();

    expect($draft->canBeSubmitted())->toBeTrue()
        ->and($draft->canBeApproved())->toBeFalse()
        ->and($pending->canBeApproved())->toBeTrue()
        ->and($approved->canBeCancelled())->toBeFalse();
});
