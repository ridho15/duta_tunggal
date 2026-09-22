<?php

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ar-ap sync recalculates receivable paid from receipt items per invoice', function () {
    $branch = Cabang::factory()->create();
    $customer = Customer::factory()->create(['cabang_id' => $branch->id]);

    $saleOrderA = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $branch->id,
        'status' => 'completed',
    ]);

    $saleOrderB = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id' => $branch->id,
        'status' => 'completed',
    ]);

    $invoiceA = Invoice::withoutEvents(fn () => Invoice::factory()->create([
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrderA->id,
        'customer_name' => $customer->name,
        'total' => 100000,
        'status' => 'unpaid',
        'cabang_id' => $branch->id,
    ]));

    $invoiceB = Invoice::withoutEvents(fn () => Invoice::factory()->create([
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $saleOrderB->id,
        'customer_name' => $customer->name,
        'total' => 200000,
        'status' => 'unpaid',
        'cabang_id' => $branch->id,
    ]));

    AccountReceivable::create([
        'invoice_id' => $invoiceA->id,
        'customer_id' => $customer->id,
        'total' => 100000,
        'paid' => 300000,
        'remaining' => 0,
        'status' => 'Lunas',
        'cabang_id' => $branch->id,
    ]);

    AccountReceivable::create([
        'invoice_id' => $invoiceB->id,
        'customer_id' => $customer->id,
        'total' => 200000,
        'paid' => 300000,
        'remaining' => 0,
        'status' => 'Lunas',
        'cabang_id' => $branch->id,
    ]);

    $receipt = CustomerReceipt::withoutEvents(fn () => CustomerReceipt::create([
        'customer_id' => $customer->id,
        'invoice_id' => $invoiceA->id,
        'selected_invoices' => [$invoiceA->id, $invoiceB->id],
        'invoice_receipts' => [$invoiceA->id => 100000, $invoiceB->id => 200000],
        'payment_date' => now()->toDateString(),
        'total_payment' => 300000,
        'payment_method' => 'Transfer',
        'status' => 'Draft',
        'cabang_id' => $branch->id,
    ]));

    CustomerReceiptItem::withoutEvents(function () use ($receipt, $invoiceA, $invoiceB) {
        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoiceA->id,
            'method' => 'Transfer',
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoiceB->id,
            'method' => 'Transfer',
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ]);
    });

    $this->artisan('ar-ap:sync', ['--force' => true])
        ->assertExitCode(0);

    $arA = AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceA->id)->first();
    $arB = AccountReceivable::withoutGlobalScopes()->where('invoice_id', $invoiceB->id)->first();

    expect((float) $arA->paid)->toBe(100000.0)
        ->and((float) $arA->remaining)->toBe(0.0)
        ->and((float) $arB->paid)->toBe(200000.0)
        ->and((float) $arB->remaining)->toBe(0.0);
});

test('ar-ap sync recalculates payable balances from vendor payment details and adjustments', function () {
    $branch = Cabang::factory()->create();
    $supplier = Supplier::factory()->create(['cabang_id' => $branch->id]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'cabang_id' => $branch->id,
    ]);

    $invoice = Invoice::withoutEvents(fn () => Invoice::factory()->create([
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $purchaseOrder->id,
        'supplier_name' => $supplier->perusahaan,
        'total' => 500000,
        'status' => 'unpaid',
        'cabang_id' => $branch->id,
    ]));

    AccountPayable::create([
        'invoice_id' => $invoice->id,
        'supplier_id' => $supplier->id,
        'total' => 500000,
        'paid' => 0,
        'remaining' => 500000,
        'status' => 'Belum Lunas',
        'cabang_id' => $branch->id,
    ]);

    $payment = VendorPayment::withoutEvents(fn () => VendorPayment::create([
        'supplier_id' => $supplier->id,
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [$invoice->id => 300000],
        'payment_date' => now()->toDateString(),
        'total_payment' => 300000,
        'payment_method' => 'Transfer',
        'status' => 'Draft',
    ]));

    VendorPaymentDetail::withoutEvents(function () use ($payment, $invoice) {
        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Transfer',
            'amount' => 300000,
            'adjustment_amount' => 50000,
            'payment_date' => now()->toDateString(),
        ]);
    });

    $this->artisan('ar-ap:sync', ['--force' => true, '--invoice-id' => $invoice->id])
        ->assertExitCode(0);

    $ap = AccountPayable::withoutGlobalScopes()->where('invoice_id', $invoice->id)->first();

    expect((float) $ap->paid)->toBe(300000.0)
        ->and((float) $ap->remaining)->toBe(150000.0)
        ->and($ap->status)->toBe('Belum Lunas');
});