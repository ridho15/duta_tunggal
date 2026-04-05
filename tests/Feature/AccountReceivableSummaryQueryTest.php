<?php

use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountReceivableQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->cabang = Cabang::factory()->create();
    $this->user->update(['cabang_id' => $this->cabang->id]);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->saleOrder = SaleOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'status' => 'approved',
    ]);
});

test('account receivable summary query excludes paid and soft deleted records', function () {
    $activeUnpaidInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 150000,
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $paidInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 250000,
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $deletedInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 350000,
            'cabang_id' => $this->cabang->id,
        ]);
    });

    AccountReceivable::create([
        'invoice_id' => $activeUnpaidInvoice->id,
        'customer_id' => $this->customer->id,
        'total' => 150000,
        'paid' => 0,
        'remaining' => 150000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    AccountReceivable::create([
        'invoice_id' => $paidInvoice->id,
        'customer_id' => $this->customer->id,
        'total' => 250000,
        'paid' => 250000,
        'remaining' => 0,
        'status' => PaymentStatus::PAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    $softDeleted = AccountReceivable::create([
        'invoice_id' => $deletedInvoice->id,
        'customer_id' => $this->customer->id,
        'total' => 350000,
        'paid' => 0,
        'remaining' => 350000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);
    $softDeleted->delete();

    $query = AccountReceivableQuery::filtered();

    expect((float) $query->sum('remaining'))->toBe(150000.0)
        ->and($query->count())->toBe(1);
});

test('overdue filter works on a joined account receivable query without ambiguous status columns', function () {
    $invoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 200000,
            'due_date' => now()->subDays(10),
            'cabang_id' => $this->cabang->id,
        ]);
    });

    AccountReceivable::create([
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'total' => 200000,
        'paid' => 0,
        'remaining' => 200000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    $query = AccountReceivable::query()
        ->leftJoin('invoices', 'account_receivables.invoice_id', '=', 'invoices.id')
        ->select('account_receivables.*');

    $filtered = AccountReceivableQuery::applyTableFilters($query, [
        'overdue' => ['isActive' => true],
    ]);

    expect($filtered->count())->toBe(1)
        ->and((float) $filtered->sum('remaining'))->toBe(200000.0);
});

test('overdue grouping marks deleted invoices before overdue buckets for receivables', function () {
    $overdueInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 300000,
            'due_date' => now()->subDays(65),
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $deletedInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 400000,
            'due_date' => now()->subDays(65),
            'cabang_id' => $this->cabang->id,
        ]);
    });
    $deletedInvoice->delete();

    $overdueReceivable = AccountReceivable::create([
        'invoice_id' => $overdueInvoice->id,
        'customer_id' => $this->customer->id,
        'total' => 300000,
        'paid' => 0,
        'remaining' => 300000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    $deletedReceivable = AccountReceivable::create([
        'invoice_id' => $deletedInvoice->id,
        'customer_id' => $this->customer->id,
        'total' => 400000,
        'paid' => 0,
        'remaining' => 400000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    $grouped = AccountReceivableQuery::withOverdueGrouping(AccountReceivableQuery::base())
        ->orderBy('account_receivables.id')
        ->pluck('overdue_group', 'account_receivables.id');

    expect($grouped[$overdueReceivable->id])->toBe('OVERDUE 60+ Days')
        ->and($grouped[$deletedReceivable->id])->toBe('DELETED INVOICE');
});

test('overdue days filter reuses shared date buckets for receivables', function () {
    $invoice31to60 = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 275000,
            'due_date' => now()->subDays(45),
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $invoiceCurrent = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'total' => 180000,
            'due_date' => now()->subDays(10),
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $matchingReceivable = AccountReceivable::create([
        'invoice_id' => $invoice31to60->id,
        'customer_id' => $this->customer->id,
        'total' => 275000,
        'paid' => 0,
        'remaining' => 275000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    AccountReceivable::create([
        'invoice_id' => $invoiceCurrent->id,
        'customer_id' => $this->customer->id,
        'total' => 180000,
        'paid' => 0,
        'remaining' => 180000,
        'status' => PaymentStatus::UNPAID->value,
        'cabang_id' => $this->cabang->id,
        'created_by' => $this->user->id,
    ]);

    $filtered = AccountReceivableQuery::applyOverdueDaysFilter(AccountReceivableQuery::base(), '31-60')
        ->pluck('account_receivables.id');

    expect($filtered->all())->toBe([$matchingReceivable->id]);
});
