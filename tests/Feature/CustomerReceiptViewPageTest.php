<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::firstOrCreate([
        'name' => 'view any customer receipt',
        'guard_name' => 'web',
    ]);

    Permission::firstOrCreate([
        'name' => 'view customer receipt',
        'guard_name' => 'web',
    ]);

    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->user->givePermissionTo(['view any customer receipt', 'view customer receipt']);
    $this->actingAs($this->user);

    $this->cashCoa = ChartOfAccount::factory()->create([
        'code' => '1111.01',
        'name' => 'Kas Operasional',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->arCoa = ChartOfAccount::factory()->create([
        'code' => '1120.01',
        'name' => 'Piutang Dagang',
        'type' => 'Asset',
        'is_active' => true,
    ]);
});

test('customer receipt view page renders payment history and journal entries', function () {
    $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $invoice = Invoice::factory()->create([
        'customer_name' => $customer->name,
        'total' => 1500000,
        'status' => 'paid',
        'cabang_id' => $this->cabang->id,
    ]);

    $receipt = CustomerReceipt::factory()->create([
        'customer_id' => $customer->id,
        'invoice_id' => $invoice->id,
        'payment_date' => now()->toDateString(),
        'total_payment' => 1500000,
        'payment_method' => 'Cash',
        'coa_id' => $this->cashCoa->id,
        'status' => 'Paid',
        'cabang_id' => $this->cabang->id,
    ]);

    CustomerReceiptItem::factory()->create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'method' => 'Cash',
        'amount' => 1500000,
        'coa_id' => $this->cashCoa->id,
        'payment_date' => now()->toDateString(),
    ]);

    JournalEntry::create([
        'coa_id' => $this->cashCoa->id,
        'date' => now()->toDateString(),
        'reference' => 'REC-' . $receipt->id,
        'description' => 'Customer receipt for receipt id ' . $receipt->id,
        'debit' => 1500000,
        'credit' => 0,
        'journal_type' => 'receipt',
        'source_type' => CustomerReceipt::class,
        'source_id' => $receipt->id,
        'cabang_id' => $this->cabang->id,
    ]);

    JournalEntry::create([
        'coa_id' => $this->arCoa->id,
        'date' => now()->toDateString(),
        'reference' => 'REC-' . $receipt->id,
        'description' => 'Customer receipt for receipt id ' . $receipt->id,
        'debit' => 0,
        'credit' => 1500000,
        'journal_type' => 'receipt',
        'source_type' => CustomerReceipt::class,
        'source_id' => $receipt->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $response = $this->get(route('filament.admin.resources.customer-receipts.view', ['record' => $receipt->id]));

    $response->assertOk();
    $response->assertSee('Detail Pembayaran per Invoice');
    $response->assertSee('Status Account Receivable');
    $response->assertSee('History Pembayaran Invoice');
    $response->assertSee('Journal Entries');
    $response->assertSee($invoice->invoice_number);
    $response->assertSee('Lunas');
    $response->assertSee('Receipt #' . $receipt->id, false);
    $response->assertSee('Rp 1.500.000');
    $response->assertSee('Kas Operasional');
    $response->assertSee('Piutang Dagang');
});