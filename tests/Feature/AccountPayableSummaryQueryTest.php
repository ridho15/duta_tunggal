<?php

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AccountPayableQuery;
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