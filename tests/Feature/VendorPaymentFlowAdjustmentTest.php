<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\AccountPayable;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\User;
use App\Models\ChartOfAccount;
use App\Services\LedgerPostingService;
use Carbon\Carbon;

class VendorPaymentFlowAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function manual_mode_creates_details_and_updates_ap_with_adjustment()
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->create();
        $this->actingAs($user);
        // Minimal COA seeding for journal posting
        $utangCoa = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'Accounts Payable',
            'type' => 'Liability',
            'is_active' => true,
        ]);
        $bankCoa = ChartOfAccount::create([
            'code' => '1112.01',
            'name' => 'Bank Utama',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $supplier = Supplier::factory()->create();
        $invoice1 = Invoice::factory()->create(['total' => 200000, 'supplier_name' => $supplier->name]);
        $invoice2 = Invoice::factory()->create(['total' => 150000, 'supplier_name' => $supplier->name]);

        $ap1 = AccountPayable::factory()->create([
            'invoice_id' => $invoice1->id,
            'supplier_id' => $supplier->id,
            'total' => 200000,
            'paid' => 0,
            'remaining' => 200000,
            'status' => 'Belum Lunas'
        ]);
        $ap2 = AccountPayable::factory()->create([
            'invoice_id' => $invoice2->id,
            'supplier_id' => $supplier->id,
            'total' => 150000,
            'paid' => 0,
            'remaining' => 150000,
            'status' => 'Belum Lunas'
        ]);

        // Simulasi data form manual mode
        $invoiceReceipts = [
            [
                'invoice_id' => $invoice1->id,
                'payment_amount' => 120000, // bayar sebagian
                'adjustment_amount' => 5000, // diskon kecil
                'adjustment_description' => 'Diskon promo',
                'balance_amount' => 0, // placeholder, akan dihitung ulang
            ],
            [
                'invoice_id' => $invoice2->id,
                'payment_amount' => 150000, // lunas
                'adjustment_amount' => 0,
                'adjustment_description' => '',
                'balance_amount' => 0,
            ],
        ];

        $vendorPayment = VendorPayment::create([
            'supplier_id' => $supplier->id,
            'payment_date' => Carbon::now()->toDateString(),
            'total_payment' => 270000, // total kas yang user masukkan (120k + 150k)
            'payment_method' => 'Cash',
            'coa_id' => $bankCoa->id,
            'selected_invoices' => [$invoice1->id, $invoice2->id],
            'invoice_receipts' => $invoiceReceipts,
            'status' => 'Draft'
        ]);

        // Re-use logic snippet from afterCreate for manual mode only
        $createdInvoiceIds = [];
        foreach ($invoiceReceipts as $receipt) {
            $invoiceId = (int)$receipt['invoice_id'];
            $paymentAmount = (float)($receipt['payment_amount'] ?? 0);
            $adjustmentAmount = (float)($receipt['adjustment_amount'] ?? 0);
            $adjustmentDesc = (string)($receipt['adjustment_description'] ?? '');
            $ap = AccountPayable::where('invoice_id', $invoiceId)->first();
            $remaining = (float)$ap->remaining;

            $rawTotalReduction = $paymentAmount + $adjustmentAmount;
            $clampedTotalReduction = max(0, min($rawTotalReduction, $remaining));
            $actualPayment = max(0, min($paymentAmount, $clampedTotalReduction));
            $effectiveAdjustment = max(0, $clampedTotalReduction - $actualPayment);
            $actualPayment = round($actualPayment, 2);
            $effectiveAdjustment = round($effectiveAdjustment, 2);
            if ($actualPayment <= 0 && $effectiveAdjustment <= 0) {
                continue;
            }
            $newBalance = max(0, $remaining - ($actualPayment + $effectiveAdjustment));
            $detailNotes = trim('Manual payment' . ($effectiveAdjustment > 0 || $adjustmentDesc !== '' ? (' | Adj: ' . number_format($effectiveAdjustment, 0, ',', '.') . ' ' . $adjustmentDesc) : ''));
            $vendorPayment->vendorPaymentDetail()->create([
                'invoice_id' => $invoiceId,
                'amount' => $actualPayment,
                'adjustment_amount' => $effectiveAdjustment,
                'balance_amount' => $newBalance,
                'notes' => $detailNotes,
                'method' => $vendorPayment->payment_method,
                'payment_date' => $vendorPayment->payment_date,
            ]);
            $createdInvoiceIds[] = $invoiceId;
        }

        // Sinkronisasi AP seperti afterCreate
        $apsSync = AccountPayable::whereIn('invoice_id', $createdInvoiceIds)->get();
        $remainingSum = 0;
        foreach ($apsSync as $ap) {
            $totals = VendorPaymentDetail::selectRaw('COALESCE(SUM(amount),0) as sum_amount, COALESCE(SUM(adjustment_amount),0) as sum_adjustment')
                ->where('invoice_id', $ap->invoice_id)
                ->first();
            $totalPaidForInvoice = (float)$totals->sum_amount;
            $totalAdjustmentForInvoice = (float)$totals->sum_adjustment;
            $newPaid = min($totalPaidForInvoice, $ap->total);
            $totalReduction = min($totalPaidForInvoice + $totalAdjustmentForInvoice, $ap->total);
            $newRemaining = max(0, $ap->total - $totalReduction);
            $remainingSum += $newRemaining;
            $ap->paid = $newPaid;
            $ap->remaining = $newRemaining;
            $ap->status = $newRemaining <= 0.01 ? 'Lunas' : 'Belum Lunas';
            $ap->save();
        }
        $vendorPayment->status = $remainingSum <= 0.01 ? 'Paid' : 'Partial';
        $vendorPayment->save();

        // Post journals
        $ledger = new LedgerPostingService();
        $ledgerResult = $ledger->postVendorPayment($vendorPayment);

        // Assertions
        $this->assertCount(2, $vendorPayment->vendorPaymentDetail, 'Dua detail harus dibuat');

        $detail1 = $vendorPayment->vendorPaymentDetail()->where('invoice_id', $invoice1->id)->first();
        $this->assertEquals(120000.00, $detail1->amount);
        $this->assertEquals(5000.00, $detail1->adjustment_amount);
        $this->assertEquals(75000.00, $detail1->balance_amount); // 200000 - 120000 - 5000 = 75k

        $detail2 = $vendorPayment->vendorPaymentDetail()->where('invoice_id', $invoice2->id)->first();
        $this->assertEquals(150000.00, $detail2->amount);
        $this->assertEquals(0.00, $detail2->adjustment_amount);
        $this->assertEquals(0.00, $detail2->balance_amount);

        $ap1->refresh();
        $ap2->refresh();
        $this->assertEquals(120000.00, $ap1->paid);
        $this->assertEquals(75000.00, $ap1->remaining);
        $this->assertEquals('Belum Lunas', $ap1->status);

        $this->assertEquals(150000.00, $ap2->paid);
        $this->assertEquals(0.00, $ap2->remaining);
        $this->assertEquals('Lunas', $ap2->status);

        $this->assertEquals('Partial', $vendorPayment->status);

        $this->assertGreaterThan(0, $vendorPayment->journalEntries()->count(), 'Journal entries harus dibuat');
        $this->assertEquals('posted', $ledgerResult['status']);
    }
}
