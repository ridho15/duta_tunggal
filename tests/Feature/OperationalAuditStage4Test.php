<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\CashBankAccount;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAuditStage4Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $superAdmin;
    protected ChartOfAccount $kasCoa;
    protected ChartOfAccount $bankCoa;
    protected ChartOfAccount $arCoa;
    protected ChartOfAccount $salesCoa;
    protected ChartOfAccount $expenseCoa;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sales.controls.credit_notes' => true]);

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama Keuangan',
            'kode' => 'CU-KEU',
            'alamat' => 'Jl. Jenderal Sudirman No. 1 Jakarta',
            'status' => 1,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Administrator Keuangan',
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->kasCoa = ChartOfAccount::create([
            'code' => '1111-001',
            'name' => 'Kas Operasional',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->bankCoa = ChartOfAccount::create([
            'code' => '1112-001',
            'name' => 'Bank BCA Operasional',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->arCoa = ChartOfAccount::create([
            'code' => '1120-001',
            'name' => 'Piutang Usaha',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->salesCoa = ChartOfAccount::create([
            'code' => '4100-001',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $this->expenseCoa = ChartOfAccount::create([
            'code' => '5100-001',
            'name' => 'Beban Operasional',
            'type' => 'Expense',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_void_issued_credit_note_and_restore_ar_and_clean_gl(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Customer PT Mitra Sentosa',
            'code' => 'CUST-MS-01',
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-CN-01',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang->id,
            'total' => 1000000,
            'status' => Invoice::STATUS_SENT,
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
        ]);

        $ar = AccountReceivable::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'total' => 800000, // sudah dikurangi CN sebelumnya 200rb
            'paid' => 0,
            'remaining' => 800000,
            'status' => 'Belum Lunas',
        ]);

        $creditNote = CreditNote::create([
            'credit_note_number' => 'CN-TEST-001',
            'type' => CreditNote::TYPE_CORRECTION,
            'status' => CreditNote::STATUS_ISSUED,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'credit_date' => now()->toDateString(),
            'reason' => 'Koreksi potongan harga retur barang cacat',
            'subtotal' => 200000,
            'tax_amount' => 0,
            'total' => 200000,
            'applied_to_ar' => 200000,
            'applied_to_deposit' => 0,
            'created_by' => $this->superAdmin->id,
            'issued_by' => $this->superAdmin->id,
            'issued_at' => now(),
        ]);

        JournalEntry::create([
            'coa_id' => $this->arCoa->id,
            'date' => now()->toDateString(),
            'reference' => $creditNote->credit_note_number,
            'description' => 'Credit Note GL',
            'debit' => 0,
            'credit' => 200000,
            'journal_type' => 'credit_note',
            'cabang_id' => $this->cabang->id,
            'source_type' => CreditNote::class,
            'source_id' => $creditNote->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('penjualan.credit-notes.void', $creditNote->id), [
                'reason' => 'Salah input invoice tujuan saat penerbitan nota kredit',
            ]);

        $response->assertRedirect(route('penjualan.credit-notes.show', $creditNote->id));
        $response->assertSessionHas('success');

        $creditNote->refresh();
        $this->assertEquals(CreditNote::STATUS_VOID, $creditNote->status);
        $this->assertEquals('Salah input invoice tujuan saat penerbitan nota kredit', $creditNote->void_reason);
        $this->assertEquals($this->superAdmin->id, $creditNote->voided_by);

        $ar->refresh();
        $this->assertEquals(1000000.0, (float) $ar->total);
        $this->assertEquals(1000000.0, (float) $ar->remaining);

        // GL Journal entries cleaned up
        $this->assertEquals(0, JournalEntry::where('source_type', CreditNote::class)->where('source_id', $creditNote->id)->count());
    }

    public function test_void_credit_note_requires_at_least_10_chars_reason(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Cust A',
            'code' => 'CA-01',
            'cabang_id' => $this->cabang->id,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-02',
            'cabang_id' => $this->cabang->id,
            'total' => 500000,
            'status' => 'sent',
            'invoice_date' => now(),
            'due_date' => now(),
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
        ]);
        AccountReceivable::create(['invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'cabang_id' => $this->cabang->id, 'total' => 500000, 'paid' => 0, 'remaining' => 500000, 'status' => 'Belum Lunas']);

        $cn = CreditNote::create([
            'credit_note_number' => 'CN-TEST-002',
            'type' => CreditNote::TYPE_CORRECTION,
            'status' => CreditNote::STATUS_ISSUED,
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'credit_date' => now()->toDateString(),
            'reason' => 'Alasan pembuatan nota kredit',
            'total' => 100000,
            'applied_to_ar' => 100000,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('penjualan.credit-notes.void', $cn->id), [
                'reason' => 'Pendek',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals(CreditNote::STATUS_ISSUED, $cn->fresh()->status);
    }

    public function test_super_admin_can_cancel_customer_receipt_with_deposit_overpayment(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Customer Deposit Batal',
            'code' => 'CUST-DB',
            'cabang_id' => $this->cabang->id,
        ]);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-DEP-01',
            'cabang_id' => $this->cabang->id,
            'total' => 1000000,
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now(),
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
        ]);
        $ar = AccountReceivable::create(['invoice_id' => $invoice->id, 'customer_id' => $customer->id, 'cabang_id' => $this->cabang->id, 'total' => 1000000, 'paid' => 1000000, 'remaining' => 0, 'status' => 'Lunas']);

        $receipt = CustomerReceipt::create([
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'Transfer',
            'coa_id' => $this->bankCoa->id,
            'total_payment' => 1000000,
            'total_payment_idr' => 1000000,
            'overpayment_amount' => 150000,
            'status' => 'Paid',
            'created_by' => $this->superAdmin->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'amount_idr' => 1000000,
            'method' => 'Transfer',
            'coa_id' => $this->bankCoa->id,
            'payment_date' => now()->toDateString(),
        ]);

        $deposit = Deposit::create([
            'deposit_number' => 'DEP-TEST-001',
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 150000,
            'used_amount' => 0,
            'remaining_amount' => 150000,
            'coa_id' => $this->bankCoa->id,
            'note' => 'Kelebihan bayar dari Penerimaan Customer #' . $receipt->id,
            'status' => 'active',
            'created_by' => $this->superAdmin->id,
        ]);

        $receipt->update(['deposit_id' => $deposit->id]);

        JournalEntry::create([
            'coa_id' => $this->bankCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'REC-' . $receipt->id,
            'description' => 'Penerimaan Bank',
            'debit' => 1000000,
            'credit' => 0,
            'journal_type' => 'customer_receipt',
            'source_type' => CustomerReceipt::class,
            'source_id' => $receipt->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('penjualan.receipts.cancel', $receipt->id), [
                'reason' => 'Transfer bank ternyata gagal kliring, kwitansi harus dibatalkan resmi',
            ]);

        $response->assertRedirect(route('penjualan.receipts.show', $receipt->id));
        $response->assertSessionHas('success');

        $receipt->refresh();
        $this->assertEquals('Cancelled', $receipt->status);
        $this->assertEquals('Transfer bank ternyata gagal kliring, kwitansi harus dibatalkan resmi', $receipt->cancel_reason);

        // AR restored
        $ar->refresh();
        $this->assertEquals(1000000.0, (float) $ar->remaining);
        $this->assertEquals(0.0, (float) $ar->paid);

        // Overpayment deposit deleted
        $this->assertNull(Deposit::find($deposit->id));
    }

    public function test_super_admin_or_finance_can_create_reversal_journal_voucher(): void
    {
        $ref = 'JV-MANUAL-001';
        JournalEntry::create([
            'coa_id' => $this->expenseCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Beban Operasional Perlengkapan',
            'debit' => 750000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->superAdmin->id,
        ]);

        JournalEntry::create([
            'coa_id' => $this->kasCoa->id,
            'date' => now()->toDateString(),
            'reference' => $ref,
            'description' => 'Kas Operasional Keluar',
            'debit' => 0,
            'credit' => 750000,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('keuangan.journal.reverse', $ref), [
                'reverse_date' => now()->toDateString(),
                'reason' => 'Salah alokasi akun beban, koreksi jurnal pembalik',
            ]);

        $response->assertSessionHas('success');

        // Check that reversal entries exist with flipped Debit & Credit
        $revEntries = JournalEntry::where('reversal_of_transaction_id', $ref)
            ->where('is_reversal', true)
            ->get();

        $this->assertCount(2, $revEntries);

        $expenseRev = $revEntries->firstWhere('coa_id', $this->expenseCoa->id);
        $this->assertEquals(0.0, (float) $expenseRev->debit);
        $this->assertEquals(750000.0, (float) $expenseRev->credit);

        $kasRev = $revEntries->firstWhere('coa_id', $this->kasCoa->id);
        $this->assertEquals(750000.0, (float) $kasRev->debit);
        $this->assertEquals(0.0, (float) $kasRev->credit);
    }

    public function test_super_admin_or_finance_can_cancel_cash_bank_transfer_officially(): void
    {
        $transfer = CashBankTransfer::create([
            'number' => 'TRF-TEST-001',
            'date' => now()->toDateString(),
            'from_coa_id' => $this->kasCoa->id,
            'to_coa_id' => $this->bankCoa->id,
            'amount' => 5000000,
            'other_costs' => 0,
            'description' => 'Setoran kas ke bank BCA',
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        JournalEntry::create([
            'coa_id' => $this->bankCoa->id,
            'date' => now()->toDateString(),
            'reference' => $transfer->number,
            'description' => 'Debit Bank BCA',
            'debit' => 5000000,
            'credit' => 0,
            'journal_type' => 'transfer',
            'source_type' => CashBankTransfer::class,
            'source_id' => $transfer->id,
            'cabang_id' => $this->cabang->id,
        ]);

        JournalEntry::create([
            'coa_id' => $this->kasCoa->id,
            'date' => now()->toDateString(),
            'reference' => $transfer->number,
            'description' => 'Kredit Kas',
            'debit' => 0,
            'credit' => 5000000,
            'journal_type' => 'transfer',
            'source_type' => CashBankTransfer::class,
            'source_id' => $transfer->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->from(route('keuangan.cashbank.transfer.show', $transfer->id))
            ->post(route('keuangan.cashbank.transfer.cancel', $transfer->id), [
                'cancel_reason' => 'Teller bank tutup sebelum setoran masuk, uang kas kembali ke brankas',
            ]);

        if ($response->getSession()->has('error')) {
            $this->fail('Cancel transfer failed with error: ' . $response->getSession()->get('error'));
        }

        $response->assertRedirect(route('keuangan.cashbank.transfer.show', $transfer->id));
        $response->assertSessionHas('success');

        $transfer->refresh();
        $this->assertEquals('cancelled', $transfer->status);
        $this->assertEquals('Teller bank tutup sebelum setoran masuk, uang kas kembali ke brankas', $transfer->cancel_reason);
        $this->assertEquals($this->superAdmin->id, $transfer->cancelled_by);

        // Journal entries cleanly removed
        $this->assertEquals(0, $transfer->journalEntries()->count());
    }

    public function test_accounting_period_reopen_cleans_closing_entries_and_shows_accurate_message(): void
    {
        $period = AccountingPeriod::create([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'open',
            'cabang_id' => $this->cabang->id,
        ]);

        JournalEntry::create([
            'coa_id' => $this->salesCoa->id,
            'date' => $period->period_end,
            'reference' => 'CLOSE-' . $period->id,
            'description' => 'Closing Entry - Tutup akun pendapatan',
            'debit' => 1000000,
            'credit' => 0,
            'journal_type' => 'closing',
            'cabang_id' => $this->cabang->id,
            'source_type' => AccountingPeriod::class,
            'source_id' => $period->id,
        ]);

        $period->update([
            'status' => 'closed',
            'closed_by' => $this->superAdmin->id,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('keuangan.period.reopen', $period->id));

        $response->assertSessionHas('success', 'Periode akuntansi berhasil dibuka kembali. Jurnal penutup (closing entries) periode ini telah dibersihkan secara otomatis.');

        $period->refresh();
        $this->assertEquals('open', $period->status);
        $this->assertNull($period->closed_by);

        // Closing entries deleted
        $this->assertEquals(0, JournalEntry::where('source_type', AccountingPeriod::class)->where('source_id', $period->id)->count());
    }
}
