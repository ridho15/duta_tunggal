<?php

use App\Models\User;
use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('Super Admin');
    $this->actingAs($this->user);
    $this->service = app(VoucherRequestService::class);
});

test('it generates unique voucher number with correct format', function () {
    $number1 = $this->service->generateVoucherNumber();

    // Create a voucher request to increment the counter
    VoucherRequest::factory()->create(['status' => 'draft']);

    $number2 = $this->service->generateVoucherNumber();

    expect($number1)->toMatch('/^VR-\d{8}-\d{4}$/')
        ->and($number2)->toMatch('/^VR-\d{8}-\d{4}$/')
        ->and($number1)->not->toBe($number2);
});

test('it submits voucher for approval', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'draft']);

    $result = $this->service->submitForApproval($voucher);

    expect($result)->toBeInstanceOf(VoucherRequest::class)
        ->and($voucher->fresh()->status)->toBe('pending');
});

test('it throws exception when submitting non-draft voucher', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'pending']);

    $this->service->submitForApproval($voucher);
})->throws(\Exception::class, 'Voucher request tidak dapat diajukan. Status saat ini: Menunggu Persetujuan');

test('it approves voucher successfully', function () {
    $voucher = VoucherRequest::factory()->pending()->create();
    
    $result = $this->service->approve($voucher, [
        'approval_notes' => 'Approved by test',
    ]);

    expect($result)->toBeInstanceOf(VoucherRequest::class)
        ->and($voucher->fresh()->status)->toBe('approved')
        ->and($voucher->fresh()->approved_by)->toBe($this->user->id)
        ->and($voucher->fresh()->approved_at)->not->toBeNull()
        ->and($voucher->fresh()->approval_notes)->toBe('Approved by test');
});

test('it throws exception when approving non-pending voucher', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'draft']);

    $this->service->approve($voucher);
})->throws(\Exception::class, 'Voucher request tidak dapat disetujui. Status saat ini: Draft');

test('it rejects voucher successfully', function () {
    $voucher = VoucherRequest::factory()->pending()->create();
    
    $result = $this->service->reject($voucher, 'Not enough budget');

    expect($result)->toBeInstanceOf(VoucherRequest::class)
        ->and($voucher->fresh()->status)->toBe('rejected')
        ->and($voucher->fresh()->approved_by)->toBe($this->user->id)
        ->and($voucher->fresh()->approved_at)->not->toBeNull()
        ->and($voucher->fresh()->approval_notes)->toContain('Not enough budget');
});

test('it requires reason when rejecting voucher', function () {
    $voucher = VoucherRequest::factory()->pending()->create();

    $this->service->reject($voucher, '');
})->throws(\Exception::class, 'Alasan penolakan harus diisi');

test('it throws exception when rejecting non-pending voucher', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'approved']);

    $this->service->reject($voucher, 'Some reason');
})->throws(\Exception::class, 'Voucher request tidak dapat ditolak. Status saat ini: Disetujui');

test('it cancels voucher successfully', function () {
    $voucher = VoucherRequest::factory()->create(['status' => 'draft']);
    
    $result = $this->service->cancel($voucher);

    expect($result)->toBeInstanceOf(VoucherRequest::class)
        ->and($voucher->fresh()->status)->toBe('cancelled');
});

test('it throws exception when cancelling approved voucher', function () {
    $voucher = VoucherRequest::factory()->approved()->create();

    $this->service->cancel($voucher);
})->throws(\Exception::class, 'Voucher request tidak dapat dibatalkan. Status saat ini: Disetujui');

test('it gets statistics correctly', function () {
    VoucherRequest::factory()->count(2)->create(['status' => 'draft']);
    VoucherRequest::factory()->count(3)->create(['status' => 'pending']);
    VoucherRequest::factory()->count(4)->create(['status' => 'approved']);
    VoucherRequest::factory()->count(1)->create(['status' => 'rejected']);

    $stats = $this->service->getStatistics();

    expect($stats)->toHaveKeys(['total', 'draft', 'pending', 'approved', 'rejected', 'cancelled'])
        ->and($stats['total'])->toBe(10)
        ->and($stats['draft'])->toBe(2)
        ->and($stats['pending'])->toBe(3)
        ->and($stats['approved'])->toBe(4)
        ->and($stats['rejected'])->toBe(1)
        ->and($stats['cancelled'])->toBe(0);
});

test('it generates sequential voucher numbers for same date', function () {
    $date = now()->format('Ymd');
    
    $number1 = $this->service->generateVoucherNumber();
    $number2 = $this->service->generateVoucherNumber();
    $number3 = $this->service->generateVoucherNumber();

    expect($number1)->toBe("VR-{$date}-0001")
        ->and($number2)->toBe("VR-{$date}-0002")
        ->and($number3)->toBe("VR-{$date}-0003");
});
