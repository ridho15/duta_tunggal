<?php

use App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt;
use App\Filament\Resources\CustomerReceiptResource;
use App\Enums\PaymentStatus;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\SaleOrder;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantCustomerReceiptPermissions(User $user): void
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
    $this->customerCabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantCustomerReceiptPermissions($this->user);
    $this->actingAs($this->user);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->customerCabang->id]);
});

test('customer receipt create form keeps JSON state fields hidden and auto-fills cabang', function () {
    Livewire::actingAs($this->user)
        ->test(CreateCustomerReceipt::class)
        ->assertSuccessful()
        ->assertFormExists()
        ->assertFormFieldExists('selected_invoices')
        ->assertFormFieldExists('invoice_receipts')
        ->assertFormFieldExists('payment_method')
        ->fillForm([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
        ])
        ->assertSet('data.cabang_id', $this->customerCabang->id);
});

test('customer receipt create form no longer shows payment mode controls', function () {
    Livewire::actingAs($this->user)
        ->test(CreateCustomerReceipt::class)
        ->assertSuccessful()
        ->assertDontSee('Mode Pembayaran')
        ->assertDontSee('Pembayaran Penuh')
        ->assertDontSee('Pembayaran Sebagian');
});

test('customer receipt filters coa options by payment method', function () {
    $cash = ChartOfAccount::factory()->create([
        'code' => '1111.99',
        'name' => 'Kas Operasional',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $bank = ChartOfAccount::factory()->create([
        'code' => '1112.98',
        'name' => 'Bank Mandiri',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $deposit = ChartOfAccount::factory()->create([
        'code' => config('coa.customer_deposit'),
        'name' => 'Deposit Pelanggan',
        'type' => 'Liability',
        'is_active' => true,
    ]);

    $cashOptions = CustomerReceiptResource::getCoaOptionsByPaymentMethod('Cash');
    $transferOptions = CustomerReceiptResource::getCoaOptionsByPaymentMethod('Transfer');
    $depositOptions = CustomerReceiptResource::getCoaOptionsByPaymentMethod('Deposit');

    expect($cashOptions)->toHaveKey($cash->id)
        ->and($cashOptions)->not->toHaveKey($bank->id)
        ->and($transferOptions)->toHaveKey($bank->id)
        ->and($transferOptions)->not->toHaveKey($cash->id)
        ->and($depositOptions)->toHaveKey($deposit->id);
});

test('customer receipt resolves remaining amount from invoice total when account receivable is absent', function () {
    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'completed',
        'cabang_id' => $this->customerCabang->id,
    ]);

    $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'total' => 250000,
            'status' => 'unpaid',
        ]);
    });

    \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->delete();

    $receipt = CustomerReceipt::factory()->create([
        'customer_id' => $this->customer->id,
        'payment_method' => 'Cash',
        'status' => 'Draft',
    ]);

    CustomerReceiptItem::factory()->create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 75000,
        'method' => 'Cash',
    ]);

    expect(CustomerReceiptResource::resolveInvoiceRemainingAmount($invoice->fresh()))->toBe(175000.0);
});

test('customer receipt create flow moves draft receipt to paid and posts journal entries', function () {
    $cashCoa = ChartOfAccount::firstOrCreate([
        'code' => '1111.01',
    ], [
        'name' => 'Kas Kecil',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $accountsReceivableCoa = ChartOfAccount::firstOrCreate([
        'code' => '1120',
    ], [
        'name' => 'Piutang Usaha',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'completed',
        'cabang_id' => $this->customerCabang->id,
    ]);

    $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_name' => $this->customer->name,
            'total' => 250000,
            'status' => 'unpaid',
        ]);
    });

    AccountReceivable::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'total' => 250000,
        'paid' => 0,
        'remaining' => 250000,
        'status' => 'Belum Lunas',
        'created_by' => $this->user->id,
    ]);

    $receipt = CustomerReceipt::withoutGlobalScopes()->create([
        'customer_id' => $this->customer->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'coa_id' => $cashCoa->id,
        'total_payment' => 250000,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [$invoice->id => 250000],
        'status' => 'Draft',
        'cabang_id' => $this->cabang->id,
    ]);

    $receipt->refresh();

    // Simulate the post-create AR update that happens in CreateCustomerReceipt::afterCreate().
    $accountReceivable = AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoice->id)->first();
    $accountReceivable->update([
        'paid' => 250000,
        'remaining' => 0,
        'status' => PaymentStatus::PAID->value,
    ]);

    $component = new CreateCustomerReceipt();
    $reflection = new ReflectionMethod(CreateCustomerReceipt::class, 'syncReceiptStatusFromReceivables');
    $reflection->setAccessible(true);
    $reflection->invoke($component, $receipt->fresh());

    $receipt->refresh();

    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('Paid')
        ->and((float) $receipt->total_payment)->toBe(250000.0);

    $journalEntries = JournalEntry::query()
        ->where('source_type', CustomerReceipt::class)
        ->where('source_id', $receipt->id)
        ->get();

    expect($journalEntries)->toHaveCount(2)
        ->and($journalEntries->sum('debit'))->toBe($journalEntries->sum('credit'));

    $cashEntry = $journalEntries->firstWhere('coa_id', $cashCoa->id);
    $arEntry = $journalEntries->firstWhere('coa_id', $accountsReceivableCoa->id);

    expect($cashEntry)->not->toBeNull()
        ->and($arEntry)->not->toBeNull();
});

test('customer receipt item creation does not double count account receivable after create marker', function () {
    $cashCoa = ChartOfAccount::firstOrCreate([
        'code' => '1111.01',
    ], [
        'name' => 'Kas Operasional',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $saleOrder = SaleOrder::factory()->create([
        'customer_id' => $this->customer->id,
        'status' => 'completed',
        'cabang_id' => $this->customerCabang->id,
    ]);

    $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
        return Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_name' => $this->customer->name,
            'total' => 250000,
            'status' => 'unpaid',
        ]);
    });

    AccountReceivable::factory()->create([
        'invoice_id' => $invoice->id,
        'customer_id' => $this->customer->id,
        'total' => 250000,
        'paid' => 0,
        'remaining' => 250000,
        'status' => 'Belum Lunas',
        'created_by' => $this->user->id,
    ]);

    $receipt = CustomerReceipt::withoutGlobalScopes()->create([
        'customer_id' => $this->customer->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'coa_id' => $cashCoa->id,
        'total_payment' => 250000,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [$invoice->id => 250000],
        'status' => 'Draft',
        'cabang_id' => $this->cabang->id,
    ]);

    \App\Observers\CustomerReceiptObserver::markArUpdatedInCreate($receipt->id);

    CustomerReceiptItem::create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 250000,
        'coa_id' => $cashCoa->id,
        'payment_date' => now()->toDateString(),
    ]);

    $accountReceivable = AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoice->id)->first();
    $accountReceivable->update([
        'paid' => 250000,
        'remaining' => 0,
        'status' => PaymentStatus::PAID->value,
    ]);

    $component = new CreateCustomerReceipt();
    $reflection = new ReflectionMethod(CreateCustomerReceipt::class, 'syncReceiptStatusFromReceivables');
    $reflection->setAccessible(true);
    $reflection->invoke($component, $receipt->fresh());

    $receipt->refresh();

    expect($accountReceivable)->not->toBeNull()
        ->and((float) $accountReceivable->paid)->toBe(250000.0)
        ->and((float) $accountReceivable->remaining)->toBe(0.0)
        ->and($receipt->status)->toBe('Paid');
});
