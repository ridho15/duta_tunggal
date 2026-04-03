<?php

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountPayableQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->supplier = Supplier::factory()->create();
    $this->purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplier->id,
        'status' => 'approved',
    ]);
});

test('account payable summary query excludes paid and soft deleted records', function () {
    $activeUnpaidInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'total' => 150000,
        ]);
    });

    $paidInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'total' => 250000,
        ]);
    });

    $deletedInvoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'total' => 350000,
        ]);
    });

    AccountPayable::create([
        'invoice_id' => $activeUnpaidInvoice->id,
        'supplier_id' => $this->supplier->id,
        'total' => 150000,
        'paid' => 0,
        'remaining' => 150000,
        'status' => PaymentStatus::UNPAID->value,
        'created_by' => $this->user->id,
    ]);

    AccountPayable::create([
        'invoice_id' => $paidInvoice->id,
        'supplier_id' => $this->supplier->id,
        'total' => 250000,
        'paid' => 250000,
        'remaining' => 0,
        'status' => PaymentStatus::PAID->value,
        'created_by' => $this->user->id,
    ]);

    $softDeleted = AccountPayable::create([
        'invoice_id' => $deletedInvoice->id,
        'supplier_id' => $this->supplier->id,
        'total' => 350000,
        'paid' => 0,
        'remaining' => 350000,
        'status' => PaymentStatus::UNPAID->value,
        'created_by' => $this->user->id,
    ]);
    $softDeleted->delete();

    $query = AccountPayableQuery::filtered();

    expect((float) $query->sum('remaining'))->toBe(150000.0)
        ->and($query->count())->toBe(1);
});

test('overdue filter works on a joined account payable query without ambiguous status columns', function () {
    $invoice = Invoice::withoutEvents(function () {
        return Invoice::factory()->create([
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $this->purchaseOrder->id,
            'total' => 200000,
            'due_date' => now()->subDays(10),
        ]);
    });

    AccountPayable::create([
        'invoice_id' => $invoice->id,
        'supplier_id' => $this->supplier->id,
        'total' => 200000,
        'paid' => 0,
        'remaining' => 200000,
        'status' => PaymentStatus::UNPAID->value,
        'created_by' => $this->user->id,
    ]);

    $query = AccountPayable::query()
        ->leftJoin('invoices', 'account_payables.invoice_id', '=', 'invoices.id')
        ->select('account_payables.*');

    $filtered = AccountPayableQuery::applyTableFilters($query, [
        'overdue' => ['isActive' => true],
    ]);

    expect($filtered->count())->toBe(1)
        ->and($filtered->sum('remaining'))->toBe(200000);
});