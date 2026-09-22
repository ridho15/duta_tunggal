<?php

namespace Tests\Feature;

use App\Exports\AlkGrafikExport;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Reports\AlkGrafikReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AlkGrafikPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_alk_grafik_preview_matches_shared_service_payload(): void
    {
        [$user, $branch] = $this->seedFixtureData();

        $expected = app(AlkGrafikReportService::class)->generate([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cabang_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.alk-grafik.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected, $branch) {
            return $report['branch_name'] === $branch->nama
                && (float) data_get($report, 'summary.revenue', 0) === (float) data_get($expected, 'summary.revenue', 0)
                && (float) data_get($report, 'summary.net_profit', 0) === (float) data_get($expected, 'summary.net_profit', 0)
                && (float) data_get($report, 'summary.total_assets', 0) === (float) data_get($expected, 'summary.total_assets', 0)
                && (float) data_get($report, 'summary.total_liabilities', 0) === (float) data_get($expected, 'summary.total_liabilities', 0)
                && (float) data_get($report, 'ratios.current_ratio', 0) === (float) data_get($expected, 'ratios.current_ratio', 0)
                && $report['trend'] === $expected['trend'];
        });

        $response->assertSee('Laporan ALK Grafik');
        $response->assertSee($branch->nama);
        $response->assertSee('Download Excel');
        $response->assertSee('Workbook Excel berisi summary, rasio, komposisi, dan tren');

        $response->assertSee(number_format((float) data_get($expected, 'ratios.current_ratio'), 2) . 'x');
        $response->assertSee(number_format((float) data_get($expected, 'ratios.debt_to_equity'), 2) . 'x');
        $response->assertSee(number_format((float) data_get($expected, 'ratios.roa'), 2) . '%');
        $response->assertSee(number_format((float) data_get($expected, 'ratios.roe'), 2) . '%');
        $response->assertSee(number_format((float) data_get($expected, 'ratios.profit_margin'), 2) . '%');
        $response->assertDontSee('N/A');
    }

    public function test_alk_grafik_embedded_preview_hides_toolbar_actions(): void
    {
        [$user] = $this->seedFixtureData();

        $response = $this->actingAs($user)->get(route('reports.alk-grafik.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'embedded' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Laporan ALK Grafik');
        $response->assertDontSee('Download Excel');
        $response->assertDontSee('Cetak / PDF');
    }

    public function test_alk_grafik_excel_export_downloads_expected_workbook(): void
    {
        Excel::fake();

        [$user] = $this->seedFixtureData();

        $response = $this->actingAs($user)->get(route('reports.alk-grafik.excel', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('alk-grafik-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            if (! $export instanceof AlkGrafikExport) {
                return false;
            }

            $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

            return $titles === ['Summary', 'Ratios', 'Composition', 'Trend'];
        });
    }

    public function test_alk_grafik_pdf_export_downloads_pdf(): void
    {
        [$user] = $this->seedFixtureData();

        $response = $this->actingAs($user)->get(route('reports.alk-grafik.pdf', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('alk-grafik-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    private function seedFixtureData(): array
    {
        $user = User::factory()->create();
        $user->forceFill(['manage_type' => 'all'])->save();

        $branch = Cabang::factory()->create([
            'kode' => 'ALK-A',
            'nama' => 'Cabang Preview ALK',
        ]);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-9101',
            'name' => 'Kas Preview ALK',
            'type' => 'Asset',
            'is_current' => true,
            'is_active' => true,
        ]);

        $inventory = ChartOfAccount::factory()->create([
            'code' => '1-9102',
            'name' => 'Persediaan Preview ALK',
            'type' => 'Asset',
            'is_current' => true,
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-9101',
            'name' => 'Utang Preview ALK',
            'type' => 'Liability',
            'is_current' => true,
            'is_active' => true,
        ]);

        $capital = ChartOfAccount::factory()->create([
            'code' => '3-9101',
            'name' => 'Modal Preview ALK',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $sales = ChartOfAccount::factory()->create([
            'code' => '4-9101',
            'name' => 'Pendapatan Preview ALK',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $expense = ChartOfAccount::factory()->create([
            'code' => '6-9101',
            'name' => 'Beban Preview ALK',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->createJournal($cash, $branch->id, '2026-04-01', 'ALK-CAP-001', 800000, 0);
        $this->createJournal($capital, $branch->id, '2026-04-01', 'ALK-CAP-001', 0, 800000);
        $this->createJournal($cash, $branch->id, '2026-04-10', 'ALK-SAL-001', 1000000, 0);
        $this->createJournal($sales, $branch->id, '2026-04-10', 'ALK-SAL-001', 0, 1000000);
        $this->createJournal($inventory, $branch->id, '2026-04-12', 'ALK-PUR-001', 250000, 0);
        $this->createJournal($payable, $branch->id, '2026-04-12', 'ALK-PUR-001', 0, 250000);
        $this->createJournal($expense, $branch->id, '2026-04-15', 'ALK-EXP-001', 180000, 0);
        $this->createJournal($cash, $branch->id, '2026-04-15', 'ALK-EXP-001', 0, 180000);

        return [$user, $branch];
    }

    private function createJournal(ChartOfAccount $coa, int $branchId, string $date, string $reference, float $debit, float $credit): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'alk preview test entry',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => 'manual',
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }
}