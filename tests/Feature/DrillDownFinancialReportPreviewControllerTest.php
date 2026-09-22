<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Exports\GenericViewExport;
use App\Services\Reports\DrillDownFinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class DrillDownFinancialReportPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_drill_down_preview_matches_shared_service_with_filters(): void
    {
        [$user, $branch, $revenue, $expense] = $this->seedFixtureData();

        $expected = app(DrillDownFinancialReportService::class)->generate([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'account_type' => 'Revenue',
            'cabang_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.drill-down-financial-report.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'account_type' => 'Revenue',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected, $revenue) {
            return $report['count'] === $expected['count']
                && $report['total_debit'] === $expected['total_debit']
                && $report['total_credit'] === $expected['total_credit']
                && count($report['grouped']) === 1
                && $report['grouped'][0]['coa']?->id === $revenue->id
                && $report['grouped'][0]['balance'] === $expected['grouped'][0]['balance'];
        });

        $response->assertSee('Drill Down Financial Report');
        $response->assertSee($branch->nama);
        $response->assertSee('Revenue');
        $response->assertSee('Rp 5.000.000');
        $response->assertDontSee($expense->name);
    }

    public function test_drill_down_preview_pdf_downloads_a_file(): void
    {
        [$user, $branch] = $this->seedFixtureData();

        $response = $this->actingAs($user)->get(route('reports.drill-down-financial-report.pdf', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('drill-down-financial-report-all-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_drill_down_preview_excel_downloads_a_file(): void
    {
        Excel::fake();

        [$user, $branch] = $this->seedFixtureData();

        $response = $this->actingAs($user)->get(route('reports.drill-down-financial-report.excel', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();

        Excel::assertDownloaded('drill-down-financial-report-all-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            return $export instanceof GenericViewExport;
        });
    }

    private function seedFixtureData(): array
    {
        $user = User::factory()->create();

        $branch = Cabang::factory()->create([
            'kode' => 'DDF-A',
            'nama' => 'Cabang Preview Drill Down',
        ]);

        $revenue = ChartOfAccount::factory()->create([
            'code' => '4-8001',
            'name' => 'Pendapatan Drill Down',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $expense = ChartOfAccount::factory()->create([
            'code' => '6-8001',
            'name' => 'Beban Drill Down',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->createJournal($revenue, $branch->id, '2026-04-10', 'DDF-REV-001', 0, 5000000);
        $this->createJournal($expense, $branch->id, '2026-04-11', 'DDF-EXP-001', 2000000, 0);

        return [$user, $branch, $revenue, $expense];
    }

    private function createJournal(ChartOfAccount $coa, int $branchId, string $date, string $reference, float $debit, float $credit): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'drill down preview fixture',
            'debit' => $debit,
            'credit' => $credit,
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }
}