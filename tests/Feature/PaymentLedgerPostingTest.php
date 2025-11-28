<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\ChartOfAccount;
use App\Models\Supplier;
use App\Models\AccountPayable;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Services\LedgerPostingService;

class PaymentLedgerPostingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure base seeders not interfering
        self::disableBaseSeeding();
        // Create a user and authenticate so observers using Auth::id() have a value
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test.user@example.test',
            'username' => 'testuser',
            'password' => 'password',
            'first_name' => 'Test',
            'last_name' => 'User',
            'kode_user' => 'TU001',
        ]);
        $this->actingAs($user);
        $this->user = $user;

        // Disable observers that interfere with test data creation
        \App\Models\VendorPayment::observe(\App\Observers\VendorPaymentObserver::class);
        \App\Models\VendorPaymentDetail::observe(\App\Observers\VendorPaymentDetailObserver::class);
    }

    public function test_post_vendor_payment_creates_debit_ap_and_credit_bank()
    {
        // Create required COAs
        $apCoa = ChartOfAccount::create(['code' => '2110', 'name' => 'Accounts Payable']);
        $bankCoa = ChartOfAccount::create(['code' => '1112.01', 'name' => 'Bank Account']);

        $supplier = Supplier::create([
            'code' => 'SUP-A',
            'name' => 'Supplier A',
            'perusahaan' => 'PT Testindo',
            'address' => 'Jalan Test No.1',
            'phone' => '08123456789',
            'handphone' => '08123456789',
            'email' => 'supplier.a@example.test',
            'fax' => '',
            'npwp' => '',
            'tempo_hutang' => 0,
            'kontak_person' => '',
            'keterangan' => ''
        ]);

        $payment = VendorPayment::create([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 1000000,
            'coa_id' => $bankCoa->id,
            'payment_method' => 'cash',
            'status' => 'Paid'
        ]);

        // Create an account payable record to satisfy VendorPaymentDetail observer logic
        $ap = AccountPayable::create([
            'invoice_id' => 9999,
            'supplier_id' => $supplier->id,
            'total' => 2000000,
            'remaining' => 2000000,
            'paid' => 0,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'method' => 'cash',
            'amount' => 1000000,
            'coa_id' => $bankCoa->id,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $ap->invoice_id,
        ]);

        $service = app(LedgerPostingService::class);
        $result = $service->postVendorPayment($payment);

        $this->assertTrue(in_array($result['status'], ['posted', 'skipped']), 'Deposit posting should be posted or skipped');

        // There should be journal entries with source_type VendorPayment and source_id
        $journals = JournalEntry::where('source_type', VendorPayment::class)->where('source_id', $payment->id)->get();
        $this->assertNotEmpty($journals);

        // Check a debit on AP and credit on bank
        $debitAp = $journals->firstWhere('coa_id', $apCoa->id);
        $this->assertNotNull($debitAp);
        $this->assertGreaterThan(0, (float)$debitAp->debit);

        $creditBank = $journals->firstWhere('coa_id', $bankCoa->id);
        $this->assertNotNull($creditBank);
        $this->assertGreaterThan(0, (float)$creditBank->credit);
    }

    public function test_post_vendor_payment_using_deposit_credits_deposit_coa()
    {
        $apCoa = ChartOfAccount::create(['code' => '2110', 'name' => 'Accounts Payable']);
        $depositCoa = ChartOfAccount::create(['code' => '1150.01', 'name' => 'Uang Muka Supplier']);

        $supplier = Supplier::create([
            'code' => 'SUP-B',
            'name' => 'Supplier B',
            'perusahaan' => 'PT Testindo',
            'address' => 'Jalan Test No.2',
            'phone' => '08123456780',
            'handphone' => '08123456780',
            'email' => 'supplier.b@example.test',
            'fax' => '',
            'npwp' => '',
            'tempo_hutang' => 0,
            'kontak_person' => '',
            'keterangan' => ''
        ]);

        // Create active deposit for supplier
        // Create an account payable for deposit-path payment to satisfy observer
        $ap2 = AccountPayable::create([
            'invoice_id' => 9998,
            'supplier_id' => $supplier->id,
            'total' => 1000000,
            'remaining' => 1000000,
            'paid' => 0,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        $deposit = Deposit::create([
            'from_model_type' => Supplier::class,
            'from_model_id' => $supplier->id,
            'coa_id' => $depositCoa->id,
            'amount' => 500000,
            'remaining_amount' => 500000,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $payment = VendorPayment::create([
            'supplier_id' => $supplier->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 500000,
            'payment_method' => 'deposit',
            'status' => 'Paid'
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'method' => 'deposit',
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $ap2->invoice_id,
        ]);

        $service = app(LedgerPostingService::class);
        $result = $service->postVendorPayment($payment);

        $this->assertTrue(in_array($result['status'], ['posted', 'skipped']), 'VendorPayment posting should be posted or skipped');

        $journals = JournalEntry::where('source_type', VendorPayment::class)->where('source_id', $payment->id)->get();
        $this->assertNotEmpty($journals);

        $creditDeposit = $journals->firstWhere('coa_id', $depositCoa->id);
        $this->assertNotNull($creditDeposit);
        $this->assertGreaterThan(0, (float)$creditDeposit->credit);
    }

    public function test_post_deposit_creates_journal_entries()
    {
        $bankCoa = ChartOfAccount::create(['code' => '1112.01', 'name' => 'Bank Account']);
        $depositCoa = ChartOfAccount::create(['code' => '1150.01', 'name' => 'Uang Muka Supplier']);

        $supplier = Supplier::create([
            'code' => 'SUP-C',
            'name' => 'Supplier C',
            'perusahaan' => 'PT Testindo',
            'address' => 'Jalan Test No.3',
            'phone' => '08123456781',
            'handphone' => '08123456781',
            'email' => 'supplier.c@example.test',
            'fax' => '',
            'npwp' => '',
            'tempo_hutang' => 0,
            'kontak_person' => '',
            'keterangan' => ''
        ]);

        $deposit = Deposit::create([
            'from_model_type' => Supplier::class,
            'from_model_id' => $supplier->id,
            'coa_id' => $depositCoa->id,
            'amount' => 250000,
            'remaining_amount' => 250000,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $service = app(LedgerPostingService::class);
        $result = $service->postDeposit($deposit);

        $this->assertTrue(in_array($result['status'], ['posted', 'skipped']), 'Deposit posting should be posted or skipped');

        $journals = JournalEntry::where('source_type', Deposit::class)->where('source_id', $deposit->id)->get();
        $this->assertNotEmpty($journals);

        $debitDeposit = $journals->firstWhere('coa_id', $depositCoa->id);
        $this->assertNotNull($debitDeposit);
        $this->assertGreaterThan(0, (float)$debitDeposit->debit);
    }

    public function test_import_purchase_invoice_does_not_post_ppn_masukan()
    {
        // Create required COAs
        $apCoa = ChartOfAccount::create(['code' => '2110', 'name' => 'Accounts Payable']);
        $bankCoa = ChartOfAccount::create(['code' => '1112.01', 'name' => 'Bank Account']);
        $ppnMasukanCoa = ChartOfAccount::create(['code' => '1170.06', 'name' => 'PPN Masukan']);

        $unbilledPurchaseCoa = ChartOfAccount::create(['code' => '2100.10', 'name' => 'Penerimaan Barang Belum Tertagih']);

        $supplier = Supplier::create([
            'code' => 'SUP-IMP',
            'name' => 'Import Supplier',
            'perusahaan' => 'PT Importindo',
            'address' => 'Jalan Import No.1',
            'phone' => '08123456789',
            'handphone' => '08123456789',
            'email' => 'supplier.imp@example.test',
            'fax' => '',
            'npwp' => '',
            'tempo_hutang' => 0,
            'kontak_person' => '',
            'keterangan' => ''
        ]);

        // Create import purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'is_import' => true,
            'ppn_option' => 'standard',
            'status' => 'approved',
        ]);

        // Create invoice for import purchase
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-IMP-001',
            'from_model_type' => \App\Models\PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'supplier_name' => $supplier->name,
            'subtotal' => 100000,
            'tax' => 11000, // 11% PPN
            'total' => 111000,
            'status' => 'draft',
        ]);

        // Post invoice to ledger
        $service = app(LedgerPostingService::class);
        $result = $service->postInvoice($invoice);

        $this->assertTrue(in_array($result['status'], ['posted', 'skipped']), 'Invoice posting should be posted or skipped');

        // Check journal entries - should NOT have PPN Masukan debit for import purchases
        $journals = JournalEntry::where('source_type', \App\Models\Invoice::class)->where('source_id', $invoice->id)->get();
        $ppnMasukanEntry = $journals->firstWhere('coa_id', $ppnMasukanCoa->id);
        $this->assertNull($ppnMasukanEntry, 'Import purchase invoice should not post PPN Masukan');
    }

    public function test_import_payment_posts_ppn_masukan_and_other_import_taxes()
    {
        // Create required COAs
        $apCoa = ChartOfAccount::create(['code' => '2110', 'name' => 'Accounts Payable']);
        $bankCoa = ChartOfAccount::create(['code' => '1112.01', 'name' => 'Bank Account']);
        $ppnMasukanCoa = ChartOfAccount::create(['code' => '1170.06', 'name' => 'PPN Masukan']);
        $pph22Coa = ChartOfAccount::create(['code' => '1170.02', 'name' => 'PPh 22']);
        $beaMasukCoa = ChartOfAccount::create(['code' => '5130', 'name' => 'Bea Masuk']);

        $supplier = Supplier::create([
            'code' => 'SUP-IMP-PAY',
            'name' => 'Import Payment Supplier',
            'perusahaan' => 'PT ImportPay',
            'address' => 'Jalan Import Pay No.1',
            'phone' => '08123456789',
            'handphone' => '08123456789',
            'email' => 'supplier.imppay@example.test',
            'fax' => '',
            'npwp' => '',
            'tempo_hutang' => 0,
            'kontak_person' => '',
            'keterangan' => ''
        ]);

        // Create import purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'is_import' => true,
            'ppn_option' => 'standard',
            'status' => 'approved',
        ]);

        // Create invoice for import purchase
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-IMP-PAY-001',
            'from_model_type' => \App\Models\PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'supplier_name' => $supplier->name,
            'subtotal' => 100000,
            'tax' => 11000, // 11% PPN
            'total' => 111000,
            'status' => 'draft',
        ]);

        // Create account payable
        $accountPayable = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'total' => 111000,
            'paid' => 0,
            'remaining' => 111000,
            'status' => 'Belum Lunas',
        ]);

        // Create import payment
        $payment = VendorPayment::withoutEvents(function () use ($supplier, $bankCoa) {
            return VendorPayment::factory()->create([
                'supplier_id' => $supplier->id,
                'payment_date' => now(),
                'payment_method' => 'bank_transfer',
                'coa_id' => $bankCoa->id,
                'total_payment' => 100000,
                'is_import_payment' => true,
                'ppn_import_amount' => 11000,
                'pph22_amount' => 2500,
                'bea_masuk_amount' => 5000,
                'notes' => 'Import payment test',
            ]);
        });

        // Create payment detail
        VendorPaymentDetail::withoutEvents(function () use ($payment, $invoice, $bankCoa) {
            return VendorPaymentDetail::create([
                'vendor_payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => 100000,
                'method' => 'bank_transfer',
                'coa_id' => $bankCoa->id,
                'payment_date' => now(),
            ]);
        });

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment->id,
            'account_payable_id' => $accountPayable->id,
            'amount' => 100000,
        ]);

        // Post payment to ledger
        $service = app(LedgerPostingService::class);
        $result = $service->postVendorPayment($payment);

        $this->assertTrue(in_array($result['status'], ['posted', 'skipped']), 'Payment posting should be posted or skipped');

        // Check journal entries
        $journals = JournalEntry::where('source_type', VendorPayment::class)->where('source_id', $payment->id)->get();
        $this->assertNotEmpty($journals);

        // Should have debit AP
        $apDebit = $journals->firstWhere('coa_id', $apCoa->id);
        $this->assertNotNull($apDebit);
        $this->assertEquals(100000, (float)$apDebit->debit);

        // Should have credit bank
        $bankCredit = $journals->firstWhere('coa_id', $bankCoa->id);
        $this->assertNotNull($bankCredit);
        $this->assertEquals(100000, (float)$bankCredit->credit);

        // Should have debit PPN Masukan for import taxes
        $ppnDebit = $journals->firstWhere('coa_id', $ppnMasukanCoa->id);
        $this->assertNotNull($ppnDebit);
        $this->assertEquals(11000, (float)$ppnDebit->debit);
        $this->assertTrue(strpos($ppnDebit->description, 'PPN Impor') !== false, 'Description should contain PPN Impor');
    }
}
