<?php

use App\Models\VoucherRequest;
use App\Models\CashBankTransaction;
use App\Models\User;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Services\VoucherRequestService;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('voucher can be selected in cash bank transaction form', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1000000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    // Debug: check voucher properties
    expect($voucher->isApproved())->toBeTrue();
    expect($voucher->getRemainingAmount())->toBe(1000000.0);
    expect($voucher->canBeUsed())->toBeTrue();

    $voucherService = app(VoucherRequestService::class);
    $availableVouchers = $voucherService->getAvailableVouchers();

    // Debug: check what's in available vouchers
    expect($availableVouchers->count())->toBeGreaterThan(0);
    expect($availableVouchers->first()->id)->toBe($voucher->id);

    // Check if voucher is in the collection by ID instead of object reference
    expect($availableVouchers->pluck('id')->contains($voucher->id))->toBeTrue();
});

test('single use voucher validation works', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $coa = ChartOfAccount::factory()->create(['code' => '1111.001']);

    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1000000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    $voucherService = app(VoucherRequestService::class);

    // Should pass validation for single use
    expect(fn() => $voucherService->validateVoucherUsage($voucher, 1000000, 'single_use'))->not->toThrow(Exception::class);

    // Should fail if amount doesn't match for single use
    expect(fn() => $voucherService->validateVoucherUsage($voucher, 500000, 'single_use'))->toThrow(Exception::class);
});

test('multi use voucher validation works', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $coa = ChartOfAccount::factory()->create(['code' => '1111.001']);

    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1000000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    $voucherService = app(VoucherRequestService::class);

    // Should pass validation for partial amount
    expect(fn() => $voucherService->validateVoucherUsage($voucher, 500000, 'multi_use'))->not->toThrow(Exception::class);

    // Should fail if amount exceeds voucher amount
    expect(fn() => $voucherService->validateVoucherUsage($voucher, 1500000, 'multi_use'))->toThrow(Exception::class);
});

test('cash bank transaction can be created with voucher', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $coa = ChartOfAccount::factory()->create(['code' => '1111.001']);
    $offsetCoa = ChartOfAccount::factory()->create(['code' => '5111.001']);

    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1000000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    $transaction = CashBankTransaction::create([
        'number' => 'CB-TEST-001',
        'date' => now(),
        'type' => 'cash_out',
        'amount' => 1000000,
        'account_coa_id' => $coa->id,
        'offset_coa_id' => $offsetCoa->id,
        'voucher_request_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'voucher_usage_type' => 'single_use',
        'voucher_amount_used' => 1000000,
        'cabang_id' => $cabang->id,
    ]);

    expect($transaction->voucher_request_id)->toBe($voucher->id);
    expect($transaction->voucher_number)->toBe($voucher->voucher_number);
    expect($transaction->voucher_usage_type)->toBe('single_use');
    expect($transaction->voucher_amount_used)->toEqual(1000000.0);
});

test('voucher remaining amount calculation works', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $coa = ChartOfAccount::factory()->create(['code' => '1111.001']);
    $offsetCoa = ChartOfAccount::factory()->create(['code' => '5111.001']);

    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1000000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    // Create first transaction using 300000
    CashBankTransaction::create([
        'number' => 'CB-TEST-001',
        'date' => now(),
        'type' => 'cash_out',
        'amount' => 300000,
        'account_coa_id' => $coa->id,
        'offset_coa_id' => $offsetCoa->id,
        'voucher_request_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'voucher_usage_type' => 'multi_use',
        'voucher_amount_used' => 300000,
        'cabang_id' => $cabang->id,
    ]);

    // Create second transaction using 400000
    CashBankTransaction::create([
        'number' => 'CB-TEST-002',
        'date' => now(),
        'type' => 'cash_out',
        'amount' => 400000,
        'account_coa_id' => $coa->id,
        'offset_coa_id' => $offsetCoa->id,
        'voucher_request_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'voucher_usage_type' => 'multi_use',
        'voucher_amount_used' => 400000,
        'cabang_id' => $cabang->id,
    ]);

    // Check remaining amount
    $remainingAmount = $voucher->fresh()->getRemainingAmount();
    expect($remainingAmount)->toBe(300000.0); // 1000000 - 300000 - 400000
});

test('voucher cannot be used if fully consumed', function () {
    $user = User::factory()->create();
    $cabang = Cabang::factory()->create();
    $coa = ChartOfAccount::factory()->create(['code' => '1111.001']);
    $offsetCoa = ChartOfAccount::factory()->create(['code' => '5111.001']);

    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 500000,
        'cabang_id' => $cabang->id,
        'created_by' => $user->id,
    ]);

    // Use entire voucher amount
    CashBankTransaction::create([
        'number' => 'CB-TEST-001',
        'date' => now(),
        'type' => 'cash_out',
        'amount' => 500000,
        'account_coa_id' => $coa->id,
        'offset_coa_id' => $offsetCoa->id,
        'voucher_request_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'voucher_usage_type' => 'single_use',
        'voucher_amount_used' => 500000,
        'cabang_id' => $cabang->id,
    ]);

    $voucherService = app(VoucherRequestService::class);
    $availableVouchers = $voucherService->getAvailableVouchers();

    expect($availableVouchers)->not->toContain($voucher);
    expect($voucher->fresh()->isFullyUsed())->toBeTrue();
});