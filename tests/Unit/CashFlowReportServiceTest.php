<?php

namespace Tests\Unit;

use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Services\Reports\CashFlowReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the finance report configuration
        $this->seed(\Database\Seeders\Finance\FinanceReportConfigSeeder::class);
    }

    public function test_branch_filter_limits_cash_bank_outflows(): void
    {
        Carbon::setTestNow('2025-10-15 00:00:00');

        $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
        $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

        $cashCoa = ChartOfAccount::create([
            'code' => '1111',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $sellingCoa = ChartOfAccount::create([
            'code' => '6100.01',
            'name' => 'Biaya Penjualan',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $customer = Customer::factory()->create();

        $saleOrder = SaleOrder::factory()->state([
            'customer_id' => $customer->id,
            'status' => 'approved',
        ])->create();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => '2025-10-05',
            'due_date' => '2025-10-20',
            'subtotal' => 1000,
            'tax' => 0,
            'other_fee' => 0,
            'total' => 1000,
            'status' => 'paid',
        ]);

        $receipt = CustomerReceipt::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'payment_date' => '2025-10-10',
            'ntpn' => null,
            'total_payment' => 1000,
            'notes' => null,
            'status' => 'Paid',
            'payment_method' => 'Cash',
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [],
            'diskon' => 0,
            'payment_adjustment' => 0,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'amount' => 1000,
            'payment_date' => '2025-10-10',
        ]);

        CashBankTransaction::create([
            'number' => 'CB-001',
            'date' => '2025-10-12',
            'type' => 'cash_out',
            'account_coa_id' => $cashCoa->id,
            'offset_coa_id' => $sellingCoa->id,
            'amount' => 500,
            'counterparty' => 'Biaya Penjualan A',
            'description' => 'Pengeluaran cabang A',
            'cabang_id' => $branchA->id,
        ]);

        CashBankTransaction::create([
            'number' => 'CB-002',
            'date' => '2025-10-12',
            'type' => 'cash_out',
            'account_coa_id' => $cashCoa->id,
            'offset_coa_id' => $sellingCoa->id,
            'amount' => 200,
            'counterparty' => 'Biaya Penjualan B',
            'description' => 'Pengeluaran cabang B',
            'cabang_id' => $branchB->id,
        ]);

        $service = app(CashFlowReportService::class);

        $allBranches = $service->generate('2025-10-01', '2025-10-31');
        $sellingAll = $this->findSectionItem($allBranches, 'operating', 'selling_expenses');
        $this->assertEquals(-700.0, $sellingAll['amount']);

        $filtered = $service->generate('2025-10-01', '2025-10-31', [
            'branches' => [$branchA->id],
        ]);
        $sellingFiltered = $this->findSectionItem($filtered, 'operating', 'selling_expenses');
        $this->assertEquals(-500.0, $sellingFiltered['amount']);

        $this->assertEquals(300.0, $allBranches['net_change']);
        $this->assertEquals(500.0, $filtered['net_change']);
    }

    private function findSectionItem(array $report, string $sectionKey, string $itemKey): array
    {
        $section = collect($report['sections'])->firstWhere('key', $sectionKey);
        $this->assertNotNull($section, "Section {$sectionKey} not found in report");

        $item = collect($section['items'])->firstWhere('key', $itemKey);
        $this->assertNotNull($item, "Item {$itemKey} not found in section {$sectionKey}");

        return $item;
    }
}
