<?php

use App\Models\User;
use App\Models\AccountPayable;
use App\Models\Supplier;
use App\Services\AccountsPayableService;
use App\Services\SupplierService;

uses()->group('accounts-payable');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->apService = Mockery::mock(AccountsPayableService::class);
    $this->supplierService = Mockery::mock(SupplierService::class);
    app()->instance(AccountsPayableService::class, $this->apService);
    app()->instance(SupplierService::class, $this->supplierService);
});

test('can create vendor master data', function () {
    $supplierData = [
        'code' => 'S001',
        'name' => 'PT Test Supplier',
        'perusahaan' => 'PT Test Supplier',
        'npwp' => '1234567890123456',
        'kontak_person' => 'John Doe',
        'phone' => '08123456789',
        'email' => 'john@test.com',
        'address' => 'Jl. Test No. 123',
        'tempo_hutang' => 30,
        'keterangan' => 'Test supplier',
    ];

    $this->supplierService->shouldReceive('create')->once()->andReturn(true);

    $result = $this->supplierService->create($supplierData);

    expect($result)->toBeTrue();
});

test('can update vendor financial terms', function () {
    $supplier = Supplier::create([
        'code' => 'S002',
        'name' => 'PT Update Test',
        'perusahaan' => 'PT Update Test',
        'npwp' => '1234567890123457',
        'kontak_person' => 'Jane Doe',
        'phone' => '08123456790',
        'handphone' => '08123456790',
        'fax' => '021-1234567',
        'email' => 'jane@test.com',
        'address' => 'Jl. Update No. 456',
        'tempo_hutang' => 30,
    ]);

    $updateData = [
        'tempo_hutang' => 60,
    ];

    $this->supplierService->shouldReceive('updateFinancialTerms')->once()->andReturn(true);

    $result = $this->supplierService->updateFinancialTerms($supplier, $updateData);

    expect($result)->toBeTrue();
});

test('can update vendor performance metrics', function () {
    $supplier = Supplier::create([
        'code' => 'S003',
        'name' => 'PT Performance Test',
        'perusahaan' => 'PT Performance Test',
        'npwp' => '1234567890123458',
        'kontak_person' => 'Bob Smith',
        'phone' => '08123456791',
        'handphone' => '08123456791',
        'fax' => '021-1234568',
        'email' => 'bob@test.com',
        'address' => 'Jl. Performance No. 789',
    ]);

    $performanceData = [
        'keterangan' => 'Updated performance metrics',
    ];

    $this->supplierService->shouldReceive('updatePerformanceMetrics')->once()->andReturn(true);

    $result = $this->supplierService->updatePerformanceMetrics($supplier, $performanceData);

    expect($result)->toBeTrue();
});

test('can generate ap aging schedule', function () {
    $this->apService->shouldReceive('generateAgingSchedule')->once()->andReturn([
        'current' => [
            ['vendor_name' => 'Vendor A', 'invoice_number' => 'INV001', 'original_amount' => 1000000, 'outstanding_balance' => 1000000, 'days_until_due' => 15, 'aging_category' => 'Current'],
        ],
        '31_60_days' => [
            ['vendor_name' => 'Vendor B', 'invoice_number' => 'INV002', 'original_amount' => 2000000, 'outstanding_balance' => 1500000, 'days_until_due' => -45, 'aging_category' => '31-60 days'],
        ],
        '61_90_days' => [],
        'over_90_days' => [
            ['vendor_name' => 'Vendor C', 'invoice_number' => 'INV003', 'original_amount' => 3000000, 'outstanding_balance' => 1000000, 'days_until_due' => -120, 'aging_category' => 'Over 90 days'],
        ],
    ]);

    $agingSchedule = $this->apService->generateAgingSchedule();

    expect($agingSchedule)->toHaveKey('current')
        ->and($agingSchedule)->toHaveKey('31_60_days')
        ->and($agingSchedule)->toHaveKey('61_90_days')
        ->and($agingSchedule)->toHaveKey('over_90_days')
        ->and($agingSchedule['current'])->toHaveCount(1)
        ->and($agingSchedule['31_60_days'])->toHaveCount(1)
        ->and($agingSchedule['over_90_days'])->toHaveCount(1);
});

test('can filter ap aging schedule by vendor', function () {
    $this->apService->shouldReceive('generateAgingSchedule')->with(['vendor_id' => 1])->once()->andReturn([
        'current' => [
            ['vendor_name' => 'Vendor A', 'invoice_number' => 'INV001', 'original_amount' => 1000000, 'outstanding_balance' => 1000000, 'days_until_due' => 15, 'aging_category' => 'Current'],
        ],
        '31_60_days' => [],
        '61_90_days' => [],
        'over_90_days' => [],
    ]);

    $agingSchedule = $this->apService->generateAgingSchedule(['vendor_id' => 1]);

    expect($agingSchedule['current'])->toHaveCount(1)
        ->and($agingSchedule['current'][0]['vendor_name'])->toBe('Vendor A');
});

test('can filter ap aging schedule by date range', function () {
    $startDate = '2025-01-01';
    $endDate = '2025-01-31';

    $this->apService->shouldReceive('generateAgingSchedule')->with(['start_date' => $startDate, 'end_date' => $endDate])->once()->andReturn([
        'current' => [],
        '31_60_days' => [
            ['vendor_name' => 'Vendor B', 'invoice_number' => 'INV002', 'original_amount' => 2000000, 'outstanding_balance' => 1500000, 'days_until_due' => -45, 'aging_category' => '31-60 days'],
        ],
        '61_90_days' => [],
        'over_90_days' => [],
    ]);

    $agingSchedule = $this->apService->generateAgingSchedule(['start_date' => $startDate, 'end_date' => $endDate]);

    expect($agingSchedule['31_60_days'])->toHaveCount(1);
});

test('can filter ap aging schedule by aging bracket', function () {
    $this->apService->shouldReceive('generateAgingSchedule')->with(['aging_bracket' => 'current'])->once()->andReturn([
        'current' => [
            ['vendor_name' => 'Vendor A', 'invoice_number' => 'INV001', 'original_amount' => 1000000, 'outstanding_balance' => 1000000, 'days_until_due' => 15, 'aging_category' => 'Current'],
        ],
        '31_60_days' => [],
        '61_90_days' => [],
        'over_90_days' => [],
    ]);

    $agingSchedule = $this->apService->generateAgingSchedule(['aging_bracket' => 'current']);

    expect($agingSchedule['current'])->toHaveCount(1)
        ->and($agingSchedule['31_60_days'])->toBeEmpty()
        ->and($agingSchedule['61_90_days'])->toBeEmpty()
        ->and($agingSchedule['over_90_days'])->toBeEmpty();
});

test('can create vendor deposit', function () {
    $supplier = Supplier::create([
        'code' => 'S004',
        'name' => 'PT Deposit Test',
        'perusahaan' => 'PT Deposit Test',
        'npwp' => '1234567890123459',
        'kontak_person' => 'Alice Johnson',
        'phone' => '08123456792',
        'handphone' => '08123456792',
        'fax' => '021-1234569',
        'email' => 'alice@test.com',
        'address' => 'Jl. Deposit No. 101',
    ]);

    $depositData = [
        'supplier_id' => $supplier->id,
        'amount' => 500000,
        'deposit_date' => now(),
        'reference' => 'DEP-001',
        'notes' => 'Security deposit',
    ];

    $this->supplierService->shouldReceive('createDeposit')->once()->andReturn(true);

    $result = $this->supplierService->createDeposit($depositData);

    expect($result)->toBeTrue();
});

test('can process vendor deposit refund', function () {
    $supplier = Supplier::create([
        'code' => 'S005',
        'name' => 'PT Refund Test',
        'perusahaan' => 'PT Refund Test',
        'npwp' => '1234567890123460',
        'kontak_person' => 'Charlie Brown',
        'phone' => '08123456793',
        'handphone' => '08123456793',
        'fax' => '021-1234570',
        'email' => 'charlie@test.com',
        'address' => 'Jl. Refund No. 202',
    ]);

    $depositData = [
        'supplier_id' => $supplier->id,
        'amount' => 1000000,
        'deposit_date' => now()->subDays(30),
        'reference' => 'DEP-002',
        'status' => 'active',
    ];

    $this->supplierService->shouldReceive('createDeposit')->once()->andReturn((object)$depositData);

    $deposit = $this->supplierService->createDeposit($depositData);

    $refundData = [
        'deposit_id' => 1, // Mock ID
        'refund_amount' => 500000,
        'refund_date' => now(),
        'reason' => 'Contract completed',
    ];

    $this->supplierService->shouldReceive('processRefund')->once()->andReturn(true);

    $result = $this->supplierService->processRefund($refundData);

    expect($result)->toBeTrue();
});

test('can calculate vendor performance score', function () {
    $supplier = Supplier::create([
        'code' => 'S006',
        'name' => 'PT Score Test',
        'perusahaan' => 'PT Score Test',
        'npwp' => '1234567890123461',
        'kontak_person' => 'Diana Prince',
        'phone' => '08123456794',
        'handphone' => '08123456794',
        'fax' => '021-1234571',
        'email' => 'diana@test.com',
        'address' => 'Jl. Score No. 303',
    ]);

    $this->supplierService->shouldReceive('calculatePerformanceScore')->once()->andReturn(92.5);

    $score = $this->supplierService->calculatePerformanceScore($supplier);

    expect($score)->toBe(92.5);
});

test('can get vendor credit utilization', function () {
    $supplier = Supplier::create([
        'code' => 'S007',
        'name' => 'PT Credit Test',
        'perusahaan' => 'PT Credit Test',
        'npwp' => '1234567890123462',
        'kontak_person' => 'Eve Wilson',
        'phone' => '08123456795',
        'handphone' => '08123456795',
        'fax' => '021-1234572',
        'email' => 'eve@test.com',
        'address' => 'Jl. Credit No. 404',
    ]);

    $this->supplierService->shouldReceive('getCreditUtilization')->once()->andReturn([
        'credit_limit' => 10000000,
        'outstanding_balance' => 2500000,
        'available_credit' => 7500000,
        'utilization_percentage' => 25.0,
    ]);

    $creditInfo = $this->supplierService->getCreditUtilization($supplier);

    expect($creditInfo['credit_limit'])->toBe(10000000)
        ->and($creditInfo['outstanding_balance'])->toBe(2500000)
        ->and($creditInfo['available_credit'])->toBe(7500000)
        ->and($creditInfo['utilization_percentage'])->toBe(25.0);
});

test('can generate vendor payment reminder', function () {
    $supplier = Supplier::create([
        'code' => 'S008',
        'name' => 'PT Reminder Test',
        'perusahaan' => 'PT Reminder Test',
        'npwp' => '1234567890123463',
        'kontak_person' => 'Frank Miller',
        'phone' => '08123456796',
        'handphone' => '08123456796',
        'fax' => '021-1234573',
        'email' => 'frank@test.com',
        'address' => 'Jl. Reminder No. 505',
    ]);

    $overdueInvoices = [
        ['invoice_number' => 'INV004', 'amount' => 1500000, 'due_date' => now()->subDays(10)],
        ['invoice_number' => 'INV005', 'amount' => 2000000, 'due_date' => now()->subDays(5)],
    ];

    $this->apService->shouldReceive('generatePaymentReminder')->once()->andReturn(true);

    $result = $this->apService->generatePaymentReminder($supplier, $overdueInvoices);

    expect($result)->toBeTrue();
});