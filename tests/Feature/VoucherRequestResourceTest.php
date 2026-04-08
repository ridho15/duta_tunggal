<?php

use App\Filament\Resources\VoucherRequestResource;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->manage_type = ['all'];

    $this->actingAs($this->user);
});

test('voucher request list query includes computed totals', function () {
    $cabang = Cabang::factory()->create();
    $voucher = VoucherRequest::factory()->create([
        'status' => 'approved',
        'amount' => 1500000,
        'cabang_id' => $cabang->id,
        'created_by' => $this->user->id,
    ]);

    $coa = ChartOfAccount::factory()->create();
    $offsetCoa = ChartOfAccount::factory()->create();

    CashBankTransaction::create([
        'number' => 'CB-TEST-001',
        'date' => now(),
        'type' => 'cash_out',
        'amount' => 500000,
        'account_coa_id' => $coa->id,
        'offset_coa_id' => $offsetCoa->id,
        'voucher_request_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'voucher_usage_type' => 'multi_use',
        'voucher_amount_used' => 500000,
        'cabang_id' => $cabang->id,
    ]);

    $record = VoucherRequestResource::getEloquentQuery()->firstWhere('id', $voucher->id);

    expect($record)->not->toBeNull()
        ->and((float) $record->total_amount_used)->toBe(500000.0)
        ->and((float) $record->remaining_amount)->toBe(1000000.0);
});
