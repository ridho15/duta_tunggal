<?php

use App\Models\CashBankTransaction;
use App\Models\VoucherRequest;
use App\Services\VoucherRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
    $this->voucherService = app(VoucherRequestService::class);
});

test('VCH-CC-01: Unapproved voucher states (draft, pending, rejected, cancelled) reject validation', function () {
    $unapprovedStatuses = ['draft', 'pending', 'rejected', 'cancelled'];

    foreach ($unapprovedStatuses as $status) {
        $voucher = finVoucher($this->ctx, amount: 200000, status: $status);

        expect($voucher->isApproved())->toBeFalse();
        expect($voucher->canBeUsed())->toBeFalse();

        expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 200000, 'single_use'))
            ->toThrow(\Exception::class, 'Voucher belum disetujui');
    }
});

test('VCH-CC-02: Multi-use voucher tracks remaining ceiling and rejects overflow usage', function () {
    $voucher = finVoucher($this->ctx, amount: 500000, status: 'approved');

    expect($voucher->isApproved())->toBeTrue();
    expect($voucher->canBeUsed())->toBeTrue();
    expect($voucher->getRemainingAmount())->toBe(500000.0);
    expect($voucher->isFullyUsed())->toBeFalse();

    // Transaksi 1: Pakai 200.000
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 200000, 'multi_use'))
        ->not->toThrow(\Exception::class);

    $trx1 = finTransaction($this->ctx, 'cash_out', 200000, attributes: [
        'voucher_request_id' => $voucher->id,
        'voucher_usage_type' => 'multi_use',
        'voucher_amount_used' => 200000,
    ]);

    $voucher->refresh();
    expect($voucher->getTotalAmountUsed())->toBe(200000.0);
    expect($voucher->getRemainingAmount())->toBe(300000.0);
    expect($voucher->canBeUsed())->toBeTrue();

    // Transaksi 2: Pakai sisa 300.000
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 300000, 'multi_use'))
        ->not->toThrow(\Exception::class);

    $trx2 = finTransaction($this->ctx, 'cash_out', 300000, attributes: [
        'voucher_request_id' => $voucher->id,
        'voucher_usage_type' => 'multi_use',
        'voucher_amount_used' => 300000,
    ]);

    $voucher->refresh();
    expect($voucher->getTotalAmountUsed())->toBe(500000.0);
    expect($voucher->getRemainingAmount())->toBe(0.0);
    expect($voucher->isFullyUsed())->toBeTrue();
    expect($voucher->canBeUsed())->toBeFalse();

    // Transaksi 3 (Adversarial): Pakai sisa yang sudah 0 -> harus ditolak keras!
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 10000, 'multi_use'))
        ->toThrow(\Exception::class, 'Jumlah transaksi melebihi sisa voucher');
});

test('VCH-CC-03: Single-use voucher enforces exact nominal match and rejects duplicate consumption', function () {
    $voucher = finVoucher($this->ctx, amount: 350000, status: 'approved');

    // 1. Coba gunakan nominal tidak cocok (parsial 200.000)
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 200000, 'single_use'))
        ->toThrow(\Exception::class, 'harus sama dengan nominal voucher');

    // 2. Coba gunakan nominal lebih besar (400.000)
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 400000, 'single_use'))
        ->toThrow(\Exception::class, 'harus sama dengan nominal voucher');

    // 3. Gunakan nominal persis 350.000 -> Sukses validasi
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 350000, 'single_use'))
        ->not->toThrow(\Exception::class);

    $trx = finTransaction($this->ctx, 'cash_out', 350000, attributes: [
        'voucher_request_id' => $voucher->id,
        'voucher_usage_type' => 'single_use',
        'voucher_amount_used' => 350000,
    ]);

    // 4. Coba gunakan kembali voucher single use yang telah terikat transaksi -> harus ditolak
    expect(fn () => $this->voucherService->validateVoucherUsage($voucher, 350000, 'single_use'))
        ->toThrow(\Exception::class, 'sudah digunakan sebelumnya (single use)');
});
