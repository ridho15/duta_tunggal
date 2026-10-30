<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\AgeingSchedule;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Services\CustomerReceiptAllocator;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->cabang = Cabang::factory()->create(['status' => 1]);
    $this->currency = Currency::firstOrCreate(
        ['code' => 'IDR'],
        ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
    );

    $this->user = User::factory()->create([
        'cabang_id' => $this->cabang->id,
        'manage_type' => 'all',
    ]);
    Auth::login($this->user);

    $this->coaKas = ChartOfAccount::firstOrCreate(
        ['code' => '1111.01'],
        ['name' => 'Kas Besar Kantor', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]
    );

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
});

it('Poin 7: CustomerReceiptAllocator accepts payments within Rp 1.00 tolerance for invoices with fractional cents', function () {
    $so = SaleOrder::create([
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'so_number' => 'SO-TEST-TOLERANCE-01',
        'order_date' => now(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Gudang Uji',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-TOL-01',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'sent',
        'total' => 1543210.48,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
    ]);

    $ar = AccountReceivable::firstOrCreate(
        ['invoice_id' => $invoice->id],
        [
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 1543210.48,
            'paid' => 0,
            'remaining' => 1543210.48,
            'status' => PaymentStatus::UNPAID->value,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]
    );

    $allocator = app(CustomerReceiptAllocator::class);

    // Paying rounded integer amount 1.543.210 (diff is 0.48 <= Rp 1.00)
    $plan = $allocator->plan(
        $this->customer->id,
        [$invoice->id => 1543210.00],
        'Cash'
    );

    // Should settle in full (1543210.48) with 0 overpayment!
    expect($plan['applied'][$invoice->id])->toBe(1543210.48)
        ->and($plan['overpayment'])->toBe(0.0);
});

it('Poin 7: CustomerReceiptAllocator correctly rejects genuine overpayments (> Rp 1.00)', function () {
    $so = SaleOrder::create([
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'so_number' => 'SO-TEST-TOLERANCE-02',
        'order_date' => now(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Gudang Uji',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-TOL-02',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'sent',
        'total' => 100000.00,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
    ]);

    AccountReceivable::firstOrCreate(
        ['invoice_id' => $invoice->id],
        [
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 100000.00,
            'paid' => 0,
            'remaining' => 100000.00,
            'status' => PaymentStatus::UNPAID->value,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]
    );

    $allocator = app(CustomerReceiptAllocator::class);

    // Paying 105.000 (exceeds by 5.000 > Rp 1.00) without deposit option
    expect(fn () => $allocator->plan(
        $this->customer->id,
        [$invoice->id => 105000.00],
        'Cash',
        false
    ))->toThrow(ValidationException::class);
});

it('Poin 7: CustomerReceiptObserver auto-closes invoice and AR to paid when remaining is <= Rp 1.00', function () {
    $so = SaleOrder::create([
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'so_number' => 'SO-TEST-TOLERANCE-03',
        'order_date' => now(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Gudang Uji',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-TEST-TOL-03',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'sent',
        'total' => 250000.75,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
    ]);

    $ar = AccountReceivable::firstOrCreate(
        ['invoice_id' => $invoice->id],
        [
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 250000.75,
            'paid' => 0,
            'remaining' => 250000.75,
            'status' => PaymentStatus::UNPAID->value,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]
    );

    $ageing = AgeingSchedule::create([
        'from_model_type' => AccountReceivable::class,
        'from_model_id' => $ar->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'total_amount' => 250000.75,
        'outstanding_amount' => 250000.75,
        'days_outstanding' => 0,
        'cabang_id' => $this->cabang->id,
    ]);

    // Pay 250.000,00 (leaving 0.75 <= Rp 1.00)
    $receipt = CustomerReceipt::create([
        'receipt_number' => 'CR-TEST-TOL-01',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'payment_date' => now(),
        'payment_method' => 'Cash',
        'coa_id' => $this->coaKas->id,
        'total_payment' => 250000.00,
        'status' => 'Draft',
        'selected_invoices' => [$invoice->id],
        'invoice_receipts' => [$invoice->id => 250000.00],
    ]);

    CustomerReceiptItem::create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 250000.00,
        'method' => 'Cash',
    ]);

    $receipt->update(['status' => 'Paid']);

    $ar->refresh();
    $invoice->refresh();

    expect((float) $ar->remaining)->toBe(0.0)
        ->and($ar->status)->toBe(PaymentStatus::PAID->value)
        ->and($invoice->status)->toBe('paid')
        ->and(AgeingSchedule::find($ageing->id))->toBeNull();
});

it('Poin 7: AccountReceivable model hook automatically reconciles remaining <= 1.00 to paid and zeroes balance', function () {
    $so = SaleOrder::create([
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'so_number' => 'SO-TEST-ARHOOK-01',
        'order_date' => now(),
        'status' => 'completed',
        'tipe_pengiriman' => 'Kirim Langsung',
        'shipped_to' => 'Gudang Uji',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-AR-HOOK-01',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'total' => 500000.00,
        'status' => 'sent',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->firstOrFail();

    // Update paid to 499999.50 (difference is 0.50 <= 1.00)
    $ar->update(['paid' => 499999.50]);
    $ar->refresh();

    expect((float) $ar->remaining)->toBe(0.0)
        ->and($ar->status)->toBe(PaymentStatus::PAID->value);
});

it('Poin 7: AccountPayable model hook automatically reconciles remaining <= 1.00 to paid and zeroes balance', function () {
    $po = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'po_number' => 'PO-TEST-APHOOK-01',
        'status' => 'approved',
        'order_date' => now(),
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-AP-HOOK-01',
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'total' => 750000.00,
        'status' => 'sent',
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
    ]);

    $ap = AccountPayable::where('invoice_id', $invoice->id)->firstOrFail();

    // Update paid to 749999.20 (difference is 0.80 <= 1.00)
    $ap->update(['paid' => 749999.20]);
    $ap->refresh();

    expect((float) $ap->remaining)->toBe(0.0)
        ->and($ap->status)->toBe(PaymentStatus::PAID->value);
});

it('Poin 7: VendorPaymentObserver allows payment within Rp 1.00 tolerance and auto-closes AP/invoice to paid', function () {
    $po = PurchaseOrder::create([
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'po_number' => 'PO-TEST-TOL-01',
        'status' => 'approved',
        'order_date' => now(),
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-VEND-TOL-01',
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'sent',
        'total' => 350000.40,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1.0,
    ]);

    $ap = AccountPayable::firstOrCreate(
        ['invoice_id' => $invoice->id],
        [
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'total' => 350000.40,
            'paid' => 0,
            'remaining' => 350000.40,
            'status' => PaymentStatus::UNPAID->value,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]
    );

    // Pay rounded amount 350000.00
    $payment = VendorPayment::create([
        'payment_number' => 'VP-TEST-TOL-01',
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
        'payment_date' => now(),
        'payment_method' => 'Cash',
        'coa_id' => $this->coaKas->id,
        'total_payment' => 350000.00,
        'status' => 'draft',
        'selected_invoices' => [$invoice->id],
    ]);

    VendorPaymentDetail::create([
        'vendor_payment_id' => $payment->id,
        'invoice_id' => $invoice->id,
        'amount' => 350000.00,
        'amount_idr' => 350000.00,
        'method' => 'Cash',
        'payment_date' => now(),
        'coa_id' => $this->coaKas->id,
    ]);

    $ap->refresh();
    $invoice->refresh();

    // With 1.00 tolerance, 0.40 remaining is auto-settled to 0 and PAID
    expect((float) $ap->remaining)->toBe(0.0)
        ->and($ap->status)->toBe(PaymentStatus::PAID->value)
        ->and($invoice->status)->toBe('paid');
});

it('Poin 8: Anti-double-click Blade component exists and is registered in AdminPanelProvider', function () {
    expect(View::exists('filament.hooks.anti-double-click'))->toBeTrue();

    $rendered = View::make('filament.hooks.anti-double-click')->render();
    expect($rendered)->toContain('isRepeaterAddButton')
        ->and($rendered)->toContain("addEventListener('click'")
        ->and($rendered)->toContain('Livewire.hook')
        ->and($rendered)->toContain('pointer-events-none');
});
