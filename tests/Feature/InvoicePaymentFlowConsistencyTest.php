<?php

use App\Filament\Resources\CustomerReceiptResource;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantInvoicePaymentFlowPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any customer receipt',
        'create customer receipt',
        'view any customer',
        'view any invoice',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any customer receipt',
        'create customer receipt',
        'view any customer',
        'view any invoice',
    ]);
}

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantInvoicePaymentFlowPermissions($this->user);
    $this->actingAs($this->user);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111.01',
        'name' => 'Kas Kecil',
        'type' => 'Asset',
        'is_active' => true,
    ]);
});

test('converted invoice total drives receivable and partial receipt balances in idr', function () {
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'completed',
        'cabang_id' => $this->cabang->id,
    ]);

    $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
        return Invoice::create([
            'invoice_number' => 'INV-USD-AR-001',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'total' => 283500,
            'subtotal' => 283500,
            'dpp' => 283500,
            'tax' => 0,
            'ppn_rate' => 0,
            'status' => 'sent',
            'cabang_id' => $this->cabang->id,
        ]);
    });

    $accountReceivable = AccountReceivable::create([
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'total' => 283500,
        'paid' => 0,
        'remaining' => 283500,
        'status' => 'Belum Lunas',
        'created_by' => $this->user->id,
        'cabang_id' => $this->cabang->id,
    ]);

    expect((float) $accountReceivable->total)->toBe(283500.0)
        ->and((float) $accountReceivable->remaining)->toBe(283500.0)
        ->and(CustomerReceiptResource::resolveInvoiceRemainingAmount($invoice->fresh()))->toBe(283500.0);

    $receipt = CustomerReceipt::create([
        'customer_id' => $this->customer->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'coa_id' => $this->cashCoa->id,
        'total_payment' => 141750,
        'invoice_receipts' => [],
        'status' => 'Draft',
        'cabang_id' => $this->cabang->id,
    ]);

    $receipt->selected_invoices = [$invoice->id];
    $receipt->invoice_receipts = [$invoice->id => 141750];
    $receipt->total_payment = 141750;
    $receipt->save();

    // Use the Filament page handler to create items and update AR (mirrors real UI flow)
    $receiptComponent = new \App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt();
    $prop = new \ReflectionProperty(get_class($receiptComponent), 'record');
    $prop->setAccessible(true);
    $prop->setValue($receiptComponent, $receipt);

    $method = new \ReflectionMethod(get_class($receiptComponent), 'afterCreate');
    $method->setAccessible(true);
    $method->invoke($receiptComponent);

    $accountReceivable->refresh();
    $receipt->refresh();

    expect((float) $accountReceivable->paid)->toBe(141750.0)
        ->and((float) $accountReceivable->remaining)->toBe(141750.0)
        ->and(CustomerReceiptResource::resolveInvoiceRemainingAmount($invoice->fresh()))->toBe(141750.0)
        ->and($receipt->status)->toBe('Partial');
});
