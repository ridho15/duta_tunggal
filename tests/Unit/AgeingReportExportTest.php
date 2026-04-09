<?php

namespace Tests\Unit;

use App\Exports\AgeingReportExport;
use App\Exports\AgeingReportPdfExport;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Reports\AgeingReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AgeingReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_export_uses_service_backed_summary_and_detail_sheets(): void
    {
        Carbon::setTestNow('2025-03-27 10:30:00');

        $fixture = $this->createAgeingFixture();

        $export = new AgeingReportExport('2025-03-27', $fixture['branch']->id, 'both');
        $sheets = $export->sheets();

        $this->assertCount(3, $sheets);
        $this->assertSame('Summary', $sheets[0]->title());
        $this->assertSame('Account Receivables Aging', $sheets[1]->title());
        $this->assertSame('Account Payables Aging', $sheets[2]->title());

        $summaryRows = $sheets[0]->collection()->values()->all();
        $receivableRows = $sheets[1]->collection()->values()->all();
        $payableRows = $sheets[2]->collection()->values()->all();

        $expectedReceivables = app(AgeingReportService::class)->summarizeBuckets(
            app(AgeingReportService::class)->getReceivableRecords([
                'as_of_date' => '2025-03-27',
                'cabang_id' => $fixture['branch']->id,
            ]),
            true
        );

        $expectedPayables = app(AgeingReportService::class)->summarizeBuckets(
            app(AgeingReportService::class)->getPayableRecords([
                'as_of_date' => '2025-03-27',
                'cabang_id' => $fixture['branch']->id,
            ]),
            true
        );

        $this->assertSame('Report Date', $summaryRows[0][0]);
        $this->assertSame('27/03/2025', $summaryRows[0][1]);
        $this->assertSame('Ageing Export Branch', $summaryRows[1][1]);
        $this->assertSame('Account Receivables Summary', $summaryRows[4][0]);
        $this->assertSame($expectedReceivables['total']['count'], $summaryRows[4][8]);
        $this->assertSame('Rp 600.000', $summaryRows[5][4]);
        $this->assertSame('Rp 0', $summaryRows[5][5]);
        $this->assertSame('Rp 0', $summaryRows[5][6]);
        $this->assertSame('Rp 0', $summaryRows[5][7]);
        $this->assertSame('Rp 600.000', $summaryRows[5][8]);
        $this->assertSame('Account Payables Summary', $summaryRows[7][0]);
        $this->assertSame($expectedPayables['total']['count'], $summaryRows[7][8]);
        $this->assertSame('Rp 500.000', $summaryRows[8][4]);
        $this->assertSame('Rp 0', $summaryRows[8][5]);
        $this->assertSame('Rp 0', $summaryRows[8][6]);
        $this->assertSame('Rp 0', $summaryRows[8][7]);
        $this->assertSame('Rp 500.000', $summaryRows[8][8]);
        $this->assertSame('Cash Flow Projection (Next 30 Days)', $summaryRows[10][0]);
        $this->assertSame('Rp 600.000', $summaryRows[11][1]);
        $this->assertSame('Rp 500.000', $summaryRows[11][4]);
        $this->assertSame('Rp 100.000', $summaryRows[11][7]);

        $this->assertCount(1, $receivableRows);
        $this->assertSame('Export Customer', $receivableRows[0]['Customer Name']);
        $this->assertSame(-8, $receivableRows[0]['Days Outstanding']);
        $this->assertSame('Current', $receivableRows[0]['Aging Bucket']);
        $this->assertSame('Rp 600.000', $receivableRows[0]['Remaining Amount']);
        $this->assertSame('Ageing Export Branch', $receivableRows[0]['Branch']);

        $this->assertCount(1, $payableRows);
        $this->assertSame('Export Supplier', $payableRows[0]['Supplier Name']);
        $this->assertSame(-13, $payableRows[0]['Days Outstanding']);
        $this->assertSame('Current', $payableRows[0]['Aging Bucket']);
        $this->assertSame('Rp 500.000', $payableRows[0]['Remaining Amount']);

        Carbon::setTestNow();
    }

    public function test_pdf_export_prepares_service_backed_receivables_payload(): void
    {
        Carbon::setTestNow('2025-03-27 10:30:00');

        $fixture = $this->createAgeingFixture();
        $export = new AgeingReportPdfExport('2025-03-27', $fixture['branch']->id, 'receivables');

        $prepareData = new ReflectionMethod(AgeingReportPdfExport::class, 'prepareData');
        $prepareData->setAccessible(true);
        $payload = $prepareData->invoke($export);

        $expected = app(AgeingReportService::class)->generate([
            'as_of_date' => '2025-03-27',
            'cabang_id' => $fixture['branch']->id,
            'report_type' => 'receivables',
        ]);

        $expectedReceivablesSummary = app(AgeingReportService::class)->summarizeBuckets($expected['arRecords'], true);
        $expectedPayablesSummary = app(AgeingReportService::class)->summarizeBuckets($expected['apRecords'], true);

        $this->assertSame('Ageing Report - Receivables', $payload['reportTitle']);
        $this->assertSame('27 March 2025', $payload['asOfDate']);
        $this->assertSame('Ageing Export Branch', $payload['cabangName']);
        $this->assertSame('receivables', $payload['type']);
        $this->assertCount(1, $payload['receivables']);
        $this->assertSame([], $payload['payables']);
        $this->assertSame($expectedReceivablesSummary, $payload['summary']['receivables']);
        $this->assertSame($expectedPayablesSummary, $payload['summary']['payables']);
        $this->assertSame(600000.0, $payload['cashFlowProjection'][30]['receivables']);
        $this->assertSame(500000.0, $payload['cashFlowProjection'][30]['payables']);
        $this->assertSame(100000.0, $payload['cashFlowProjection'][30]['net_cash_flow']);
        $this->assertSame('Export Customer', $payload['receivables'][0]['customer_name']);
        $this->assertSame(-8, $payload['receivables'][0]['days_outstanding']);
        $this->assertSame('Current', $payload['receivables'][0]['aging_bucket']);
        $this->assertSame(600000.0, (float) $payload['receivables'][0]['remaining_amount']);

        Carbon::setTestNow();
    }

    private function createAgeingFixture(): array
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Ageing Export Branch']);
        $customer = Customer::factory()->create([
            'name' => 'Export Customer',
            'cabang_id' => $branch->id,
        ]);
        $supplier = Supplier::factory()->create([
            'perusahaan' => 'Export Supplier',
            'cabang_id' => $branch->id,
        ]);

        $receivableInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-AR-EXPORT',
            'invoice_date' => '2025-02-10',
            'due_date' => '2025-04-05',
            'customer_name' => $customer->name,
            'cabang_id' => $branch->id,
        ]);

        AccountReceivable::factory()->create([
            'invoice_id' => $receivableInvoice->id,
            'customer_id' => $customer->id,
            'cabang_id' => $branch->id,
            'total' => 700000,
            'paid' => 100000,
            'remaining' => 600000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        $payableInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-AP-EXPORT',
            'invoice_date' => '2024-12-20',
            'due_date' => '2025-04-10',
            'supplier_name' => $supplier->perusahaan,
            'cabang_id' => $branch->id,
        ]);

        AccountPayable::factory()->create([
            'invoice_id' => $payableInvoice->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $branch->id,
            'total' => 500000,
            'paid' => 0,
            'remaining' => 500000,
            'status' => 'Belum Lunas',
            'created_by' => $user->id,
        ]);

        return compact('branch');
    }
}