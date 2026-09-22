<?php

use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\Reports\CashFlowReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createCashFlowInvoice(array $attributes): Invoice
{
    return Invoice::withoutEvents(fn () => Invoice::create($attributes));
}

function createCashFlowReceipt(array $attributes): CustomerReceipt
{
    return CustomerReceipt::withoutEvents(fn () => CustomerReceipt::create($attributes));
}

function createCashFlowReceiptItem(array $attributes): CustomerReceiptItem
{
    return CustomerReceiptItem::withoutEvents(fn () => CustomerReceiptItem::create($attributes));
}

beforeEach(function () {
    Carbon::setTestNow('2026-03-15 00:00:00');

    ChartOfAccount::firstOrCreate(
        ['code' => '1120'],
        ['name' => 'Piutang Dagang', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::firstOrCreate(
        ['code' => '4000'],
        ['name' => 'Penjualan', 'type' => 'Revenue', 'is_active' => true]
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

dataset('cashFlowSetup', function () {
    return [
        'service' => app(CashFlowReportService::class),
        'cashCoa' => ChartOfAccount::factory()->create([
            'code' => '1111',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_active' => true,
        ]),
        'bankCoa' => ChartOfAccount::factory()->create([
            'code' => '1121',
            'name' => 'Bank',
            'type' => 'Asset',
            'is_active' => true,
        ]),
        'revenueCoa' => ChartOfAccount::factory()->create([
            'code' => '4111',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]),
        'expenseCoa' => ChartOfAccount::factory()->create([
            'code' => '5111',
            'name' => 'Beban Operasional',
            'type' => 'Expense',
            'is_active' => true,
        ]),
        'branch' => Cabang::factory()->create(['nama' => 'Cabang Utama']),
    ];
});

test('service can generate direct method cash flow report', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $revenueCoa = ChartOfAccount::factory()->create([
        'code' => '4111',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Utama']);

    // Create cash inflow from sales (using salesReceipts resolver)
    $customer = \App\Models\Customer::factory()->create();
    $saleOrder = \App\Models\SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-001',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->subDays(2)->toDateString(),
        'subtotal' => 1000000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 1000000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDays(5)->toDateString(),
        'ntpn' => null,
        'total_payment' => 1000000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1000000,
        'payment_date' => now()->subDays(5)->toDateString(),
    ]);

    // Create cash outflow to expense account
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.01',
        'name' => 'Biaya Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    $transaction3 = new CashBankTransaction([
        'number' => 'CB-003-' . now()->format('YmdHis'),
        'date' => now()->subDays(1)->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 500000,
        'description' => 'Test cash out 2',
        'cabang_id' => $branch->id,
    ]);
    $transaction3->save();

    $report = $service->generate(null, null, ['method' => 'direct']);

    expect($report)
        ->toHaveKey('method')
        ->toHaveKey('sections')
        ->toHaveKey('net_change')
        ->toHaveKey('opening_balance')
        ->toHaveKey('closing_balance')
        ->toHaveKey('period');

    expect($report['method'])->toBe('direct');
    expect($report['sections'])->toBeArray();
    expect($report['net_change'])->toBe(500000.0); // 1,000,000 - 500,000
});

test('service includes vendor payment journals in direct cash flow', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Vendor Payment']);

    $apCoa = ChartOfAccount::factory()->create([
        'code' => '2110',
        'name' => 'Hutang Dagang',
        'type' => 'Liability',
        'is_active' => true,
    ]);

    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    JournalEntry::create([
        'coa_id' => $apCoa->id,
        'date' => now()->subDays(1)->toDateString(),
        'reference' => 'PAY-001',
        'description' => 'Vendor payment AP debit',
        'debit' => 100000,
        'credit' => 0,
        'journal_type' => 'payment',
        'cabang_id' => $branch->id,
        'source_type' => VendorPayment::class,
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $cashCoa->id,
        'date' => now()->subDays(1)->toDateString(),
        'reference' => 'PAY-001',
        'description' => 'Vendor payment cash credit',
        'debit' => 0,
        'credit' => 100000,
        'journal_type' => 'payment',
        'cabang_id' => $branch->id,
        'source_type' => VendorPayment::class,
        'source_id' => 1,
    ]);

    $report = $service->generate(null, null, [
        'method' => 'direct',
        'branches' => [$branch->id],
    ]);

    expect($report['net_change'])->toBe(-100000.0);
    $financingSection = collect($report['sections'])->firstWhere('key', 'financing');
    expect($financingSection)->not->toBeNull();
    $liabilityItem = collect($financingSection['items'])->firstWhere('key', 'liability_operations');
    expect($liabilityItem['amount'])->toBe(-100000.0);
});

test('service counts customer deposits once and excludes deposit-funded receipts from sales receipts', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Customer Deposit']);
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $depositLiabilityCoa = ChartOfAccount::factory()->create([
        'code' => '2160',
        'name' => 'Hutang Titipan Customer',
        'type' => 'Liability',
        'is_active' => true,
    ]);

    $deposit = Deposit::factory()->create([
        'from_model_type' => Customer::class,
        'from_model_id' => $customer->id,
        'amount' => 750000,
        'coa_id' => $depositLiabilityCoa->id,
        'created_by' => $user->id,
        'status' => 'active',
    ]);

    JournalEntry::create([
        'coa_id' => $cashCoa->id,
        'date' => now()->subDays(1)->toDateString(),
        'reference' => 'DEP-' . $deposit->id,
        'description' => 'Customer deposit cash debit',
        'debit' => 750000,
        'credit' => 0,
        'journal_type' => 'deposit',
        'cabang_id' => $branch->id,
        'source_type' => Deposit::class,
        'source_id' => $deposit->id,
    ]);

    JournalEntry::create([
        'coa_id' => $depositLiabilityCoa->id,
        'date' => now()->subDays(1)->toDateString(),
        'reference' => 'DEP-' . $deposit->id,
        'description' => 'Customer deposit liability credit',
        'debit' => 0,
        'credit' => 750000,
        'journal_type' => 'deposit',
        'cabang_id' => $branch->id,
        'source_type' => Deposit::class,
        'source_id' => $deposit->id,
    ]);

    $saleOrder = SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-DEP-001',
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(2)->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'subtotal' => 750000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 750000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDay()->toDateString(),
        'ntpn' => null,
        'total_payment' => 750000,
        'notes' => 'Paid from customer deposit',
        'status' => 'Paid',
        'payment_method' => 'Deposit',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Deposit',
        'amount' => 750000,
        'payment_date' => now()->subDay()->toDateString(),
    ]);

    $report = $service->generate(null, null, ['method' => 'direct']);
    $operatingSection = collect($report['sections'])->firstWhere('key', 'operating');

    expect($report['net_change'])->toBe(750000.0);
    expect(collect($operatingSection['items'])->firstWhere('key', 'customer_deposits')['amount'])->toBe(750000.0);
    expect(collect($operatingSection['items'])->firstWhere('key', 'cash_receipts_from_sales')['amount'])->toBe(0.0);
});

test('service includes supplier deposit journals in direct cash flow', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Supplier Deposit']);
    $supplier = Supplier::factory()->create();
    $user = User::factory()->create();

    $depositCoa = ChartOfAccount::factory()->create([
        'code' => '1150.01',
        'name' => 'Uang Muka Pembelian',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $deposit = Deposit::create([
        'from_model_type' => Supplier::class,
        'from_model_id' => $supplier->id,
        'amount' => 250000,
        'remaining_amount' => 250000,
        'used_amount' => 0,
        'coa_id' => $depositCoa->id,
        'status' => 'active',
        'created_by' => $user->id,
    ]);

    JournalEntry::create([
        'coa_id' => $depositCoa->id,
        'date' => now()->subDay()->toDateString(),
        'reference' => 'DEP-' . $deposit->id,
        'description' => 'Deposit to supplier',
        'debit' => 250000,
        'credit' => 0,
        'journal_type' => 'deposit',
        'cabang_id' => $branch->id,
        'source_type' => Deposit::class,
        'source_id' => $deposit->id,
    ]);

    JournalEntry::create([
        'coa_id' => $cashCoa->id,
        'date' => now()->subDay()->toDateString(),
        'reference' => 'DEP-' . $deposit->id,
        'description' => 'Payment for deposit to supplier',
        'debit' => 0,
        'credit' => 250000,
        'journal_type' => 'deposit',
        'cabang_id' => $branch->id,
        'source_type' => Deposit::class,
        'source_id' => $deposit->id,
    ]);

    $report = $service->generate(null, null, [
        'method' => 'direct',
        'branches' => [$branch->id],
    ]);

    expect($report['net_change'])->toBe(-250000.0);
    $operatingSection = collect($report['sections'])->firstWhere('key', 'operating');
    expect($operatingSection)->not->toBeNull();
    $supplierDepositItem = collect($operatingSection['items'])->firstWhere('key', 'supplier_deposits');
    expect($supplierDepositItem['amount'])->toBe(-250000.0);
});

test('service can generate indirect method cash flow report', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $revenueCoa = ChartOfAccount::factory()->create([
        'code' => '4111',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '5111',
        'name' => 'Beban Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    // Create journal entries for income statement
    JournalEntry::factory()->create([
        'coa_id' => $revenueCoa->id,
        'debit' => 0,
        'credit' => 2000000,
        'date' => now()->subDays(5),
        'reference' => 'INV-001',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseCoa->id,
        'debit' => 500000,
        'credit' => 0,
        'date' => now()->subDays(5),
        'reference' => 'EXP-001',
    ]);

    $report = $service->generate(null, null, ['method' => 'indirect']);

    expect($report)
        ->toHaveKey('method')
        ->toHaveKey('sections')
        ->toHaveKey('net_change')
        ->toHaveKey('opening_balance')
        ->toHaveKey('closing_balance');

    expect($report['method'])->toBe('indirect');
    expect($report['sections'])->toBeArray();
    expect(count($report['sections']))->toBeGreaterThan(0);
});

test('service validates net change calculation', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $bankCoa = ChartOfAccount::factory()->create([
        'code' => '1121',
        'name' => 'Bank',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.01',
        'name' => 'Biaya Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Utama']);

    // Create customer receipt for cash inflow
    $customer = \App\Models\Customer::factory()->create();
    $saleOrder = \App\Models\SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-002',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->subDays(2)->toDateString(),
        'subtotal' => 2000000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 2000000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDays(5)->toDateString(),
        'ntpn' => null,
        'total_payment' => 2000000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 2000000,
        'payment_date' => now()->subDays(5)->toDateString(),
    ]);

    // Create cash outflow
    $transaction2 = new CashBankTransaction([
        'number' => 'CB-004-' . now()->format('YmdHis'),
        'date' => now()->subDays(3)->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 800000,
        'description' => 'Test cash out',
        'cabang_id' => $branch->id,
    ]);
    $transaction2->save();

    // Create bank inflow
    $transaction3 = new CashBankTransaction([
        'number' => 'CB-005-' . now()->format('YmdHis'),
        'date' => now()->subDays(2)->toDateString(),
        'type' => 'bank_in',
        'account_coa_id' => $bankCoa->id,
        'offset_coa_id' => $cashCoa->id, // Transfer from cash to bank
        'amount' => 500000,
        'description' => 'Test bank in',
        'cabang_id' => $branch->id,
    ]);
    $transaction3->save();

    $report = $service->generate(null, null, ['method' => 'direct']);

    // Net change should be: +2,000,000 - 800,000 = +1,200,000 (third transaction not counted in cash flow items)
    expect($report['net_change'])->toBe(1200000.0);

    // Closing balance should be opening + net change
    expect($report['closing_balance'])->toBe($report['opening_balance'] + $report['net_change']);
});

test('service filters by date range correctly', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.02',
        'name' => 'Biaya Penjualan',
        'type' => 'Expense',
        'is_active' => true,
    ]);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Utama']);

    $startDate = now()->subDays(10)->toDateString();
    $endDate = now()->subDays(5)->toDateString();

    // Create customer receipt within the date range
    $customer = \App\Models\Customer::factory()->create();
    $saleOrder = \App\Models\SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-003',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(7)->toDateString(),
        'due_date' => now()->subDays(4)->toDateString(),
        'subtotal' => 1000000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 1000000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDays(7)->toDateString(),
        'ntpn' => null,
        'total_payment' => 1000000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1000000,
        'payment_date' => now()->subDays(7)->toDateString(),
    ]);

    // Create transaction outside the date range
    $transaction2 = new CashBankTransaction([
        'number' => 'CB-007-' . now()->format('YmdHis'),
        'date' => now()->subDays(12)->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 200000,
        'description' => 'Outside range',
        'cabang_id' => $branch->id,
    ]);
    $transaction2->save();

    // Create transaction outside the date range
    $transaction3 = new CashBankTransaction([
        'number' => 'CB-008-' . now()->format('YmdHis'),
        'date' => now()->subDays(3)->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 300000,
        'description' => 'Outside range',
        'cabang_id' => $branch->id,
    ]);
    $transaction3->save();

    $report = $service->generate($startDate, $endDate, ['method' => 'direct']);

    // Should only include the transaction within the date range
    expect($report['net_change'])->toBe(1000000.0);
});

test('service filters by branch correctly', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.03',
        'name' => 'Biaya Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
    $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

    // Create cash outflow for branch B
    $transaction2 = new CashBankTransaction([
        'number' => 'CB-010-' . now()->format('YmdHis'),
        'date' => now()->subDays(3)->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 500000,
        'description' => 'Branch B',
        'cabang_id' => $branchB->id,
    ]);
    $transaction2->save();

    // Filter by branch A only (should have no transactions)
    $report = $service->generate(null, null, [
        'method' => 'direct',
        'branches' => [$branchA->id]
    ]);

    expect($report['net_change'])->toBe(0.0);

    // Filter by branch B only
    $report = $service->generate(null, null, [
        'method' => 'direct',
        'branches' => [$branchB->id]
    ]);

    expect($report['net_change'])->toBe(-500000.0);
});

test('service handles empty data gracefully', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);

    $report = $service->generate(null, null, ['method' => 'direct']);

    expect($report['method'])->toBe('direct');
    expect($report['sections'])->toBeArray();
    expect($report['net_change'])->toBe(0.0);
    expect($report['opening_balance'])->toBe(0.0);
    expect($report['closing_balance'])->toBe(0.0);
});

test('service defaults to direct method when no method specified', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);

    $report = $service->generate();

    expect($report['method'])->toBe('direct');
});

test('service calculates opening balance correctly', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.04',
        'name' => 'Biaya Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Utama']);

    // Create an opening balance transaction before the report period
    $openingTransaction = new CashBankTransaction([
        'number' => 'CB-011-' . now()->format('YmdHis'),
        'date' => now()->startOfMonth()->subDays(5)->toDateString(), // Definitely before start of month
        'type' => 'cash_in',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $expenseCoa->id, // Changed to expense account
        'amount' => 5000000,
        'description' => 'Opening balance',
        'cabang_id' => $branch->id,
    ]);
    $openingTransaction->save();

    // Create customer receipt within the report period
    $customer = \App\Models\Customer::factory()->create();
    $saleOrder = \App\Models\SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-005',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->subDays(2)->toDateString(),
        'subtotal' => 1000000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 1000000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDays(5)->toDateString(),
        'ntpn' => null,
        'total_payment' => 1000000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1000000,
        'payment_date' => now()->subDays(5)->toDateString(),
    ]);

    $report = $service->generate(null, null, ['method' => 'direct']);

    // Opening balance should include the transaction before the period
    expect($report['opening_balance'])->toBe(5000000.0);
    // Net change should only include transactions within the period
    expect($report['net_change'])->toBe(1000000.0);
    // Closing balance should be opening + net change
    expect($report['closing_balance'])->toBe(6000000.0);
});

test('service handles multiple cash accounts correctly', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $bankCoa = ChartOfAccount::factory()->create([
        'code' => '1121',
        'name' => 'Bank',
        'type' => 'Asset',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6100.05',
        'name' => 'Biaya Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);
    $branch = Cabang::factory()->create(['nama' => 'Cabang Utama']);

    // Create customer receipt for cash inflow
    $customer = \App\Models\Customer::factory()->create();
    $saleOrder = \App\Models\SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-006',
        'from_model_type' => \App\Models\SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => now()->subDays(5)->toDateString(),
        'due_date' => now()->subDays(2)->toDateString(),
        'subtotal' => 1000000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 1000000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => now()->subDays(5)->toDateString(),
        'ntpn' => null,
        'total_payment' => 1000000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1000000,
        'payment_date' => now()->subDays(5)->toDateString(),
    ]);

    // Create bank inflow (transfer from cash to bank)
    $transaction2 = new CashBankTransaction([
        'number' => 'CB-014-' . now()->format('YmdHis'),
        'date' => now()->subDays(3)->toDateString(),
        'type' => 'bank_in',
        'account_coa_id' => $bankCoa->id,
        'offset_coa_id' => $cashCoa->id,
        'amount' => 2000000,
        'description' => 'Bank in',
        'cabang_id' => $branch->id,
    ]);
    $transaction2->save();

    // Create bank outflow
    $transaction3 = new CashBankTransaction([
        'number' => 'CB-015-' . now()->format('YmdHis'),
        'date' => now()->subDays(1)->toDateString(),
        'type' => 'bank_out',
        'account_coa_id' => $bankCoa->id,
        'offset_coa_id' => $expenseCoa->id,
        'amount' => 500000,
        'description' => 'Bank out',
        'cabang_id' => $branch->id,
    ]);
    $transaction3->save();

    $report = $service->generate(null, null, ['method' => 'direct']);

    // Net change should be: +1,000,000 - 500,000 = +500,000 (transfers between cash accounts don't affect net change)
    expect($report['net_change'])->toBe(500000.0);
});

test('indirect method includes net income from income statement', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);
    $revenueCoa = ChartOfAccount::factory()->create([
        'code' => '4111',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);
    $expenseCoa = ChartOfAccount::factory()->create([
        'code' => '5111',
        'name' => 'Beban Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    // Create journal entries for revenue and expenses
    JournalEntry::factory()->create([
        'coa_id' => $revenueCoa->id,
        'debit' => 0,
        'credit' => 3000000,
        'date' => now()->subDays(5),
        'reference' => 'REV-001',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseCoa->id,
        'debit' => 1000000,
        'credit' => 0,
        'date' => now()->subDays(5),
        'reference' => 'EXP-001',
    ]);

    $report = $service->generate(null, null, ['method' => 'indirect']);

    expect($report['method'])->toBe('indirect');

    // Find the operating activities section
    $operatingSection = collect($report['sections'])->firstWhere('key', 'operating');
    expect($operatingSection)->not->toBeNull();

    // Should include net income (revenue - expenses = 3,000,000 - 1,000,000 = 2,000,000)
    $netIncomeItem = collect($operatingSection['items'])->firstWhere('key', 'net_income');
    expect($netIncomeItem)->not->toBeNull();
    expect($netIncomeItem['amount'])->toBe(2000000.0);
});

test('service handles invalid method parameter gracefully', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);

    $report = $service->generate(null, null, ['method' => 'invalid_method']);

    // Should default to direct method
    expect($report['method'])->toBe('direct');
});

test('service returns correct period information', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);

    $startDate = '2025-01-01';
    $endDate = '2025-01-31';

    $report = $service->generate($startDate, $endDate, ['method' => 'direct']);

    expect($report['period']['start'])->toBe($startDate);
    expect($report['period']['end'])->toBe($endDate);
});

test('service handles null dates correctly', function () {
    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    $service = app(CashFlowReportService::class);

    $report = $service->generate(null, null, ['method' => 'direct']);

    expect($report['period']['start'])->toBe(now()->startOfMonth()->toDateString());
    expect($report['period']['end'])->toBe(now()->endOfMonth()->toDateString());
});

test('branch filter limits cash bank outflows', function () {
    Carbon::setTestNow('2025-10-15 00:00:00');

    app(\Database\Seeders\Finance\FinanceReportConfigSeeder::class)->run();

    // Check if seeder ran
    expect(\App\Models\Reports\CashFlowSection::count())->toBeGreaterThan(0);

    $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
    $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

    $cashCoa = ChartOfAccount::create([
        'code' => '1112',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
        'opening_balance' => 0,
        'debit' => 0,
        'credit' => 0,
        'ending_balance' => 0,
    ]);

    $sellingCoa = ChartOfAccount::create([
        'code' => '6100.02',
        'name' => 'Biaya Penjualan',
        'type' => 'Expense',
        'is_active' => true,
        'opening_balance' => 0,
        'debit' => 0,
        'credit' => 0,
        'ending_balance' => 0,
    ]);

    $customer = Customer::factory()->create();

    $saleOrder = SaleOrder::factory()->state([
        'customer_id' => $customer->id,
        'status' => 'approved',
    ])->create();

    $invoice = createCashFlowInvoice([
        'invoice_number' => 'INV-001',
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrder->id,
        'invoice_date' => '2025-10-05',
        'due_date' => '2025-10-20',
        'subtotal' => 1000,
        'tax' => 0,
        'other_fee' => 0,
        'total' => 1000,
        'status' => 'paid',
    ]);

    $receipt = createCashFlowReceipt([
        'invoice_id' => $invoice->id,
        'customer_id' => $customer->id,
        'payment_date' => '2025-10-10',
        'ntpn' => null,
        'total_payment' => 1000,
        'notes' => null,
        'status' => 'Paid',
        'payment_method' => 'Cash',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [],
        'diskon' => 0,
        'payment_adjustment' => 0,
    ]);

    createCashFlowReceiptItem([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1000,
        'payment_date' => '2025-10-10',
    ]);

    CashBankTransaction::create([
        'number' => 'CB-016-' . now()->format('YmdHis'),
        'date' => '2025-10-12',
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $sellingCoa->id,
        'amount' => 500,
        'counterparty' => 'Biaya Penjualan A',
        'description' => 'Pengeluaran cabang A',
        'cabang_id' => $branchA->id,
    ]);

    CashBankTransaction::create([
        'number' => 'CB-017-' . now()->format('YmdHis'),
        'date' => '2025-10-12',
        'type' => 'cash_out',
        'account_coa_id' => $cashCoa->id,
        'offset_coa_id' => $sellingCoa->id,
        'amount' => 200,
        'counterparty' => 'Biaya Penjualan B',
        'description' => 'Pengeluaran cabang B',
        'cabang_id' => $branchB->id,
    ]);

    $service = app(CashFlowReportService::class);

    $allBranches = $service->generate(null, null);
    expect($allBranches['sections'])->toBeArray();
    expect(count($allBranches['sections']))->toBeGreaterThan(0);
    $sectionKeys = collect($allBranches['sections'])->pluck('key')->toArray();
    expect($sectionKeys)->toContain('operating');

    $sellingAll = findSectionItem($allBranches, 'operating', 'selling_expenses');
    expect($sellingAll['amount'])->toBe(-700.0);

    $filtered = $service->generate(null, null, [
        'branches' => [$branchA->id],
    ]);
    $sellingFiltered = findSectionItem($filtered, 'operating', 'selling_expenses');
    expect($sellingFiltered['amount'])->toBe(-500.0);

    expect($allBranches['net_change'])->toBe(300.0);
    expect($filtered['net_change'])->toBe(500.0);
});

function findSectionItem(array $report, string $sectionKey, string $itemKey): array
{
    $section = collect($report['sections'])->firstWhere('key', $sectionKey);
    expect($section)->not->toBeNull("Section {$sectionKey} not found in report");

    $item = collect($section['items'])->firstWhere('key', $itemKey);
    expect($item)->not->toBeNull("Item {$itemKey} not found in section {$sectionKey}");

    return $item;
}
