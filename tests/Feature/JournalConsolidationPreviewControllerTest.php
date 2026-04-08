<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Reports\JournalConsolidationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalConsolidationPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_consolidation_preview_matches_shared_service_when_grouped_by_branch(): void
    {
        [$user, $branchA, $branchB] = $this->seedFixtureData();

        $this->actingAs($user);

        $expected = app(JournalConsolidationReportService::class)->generate([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'group_by_branch' => true,
        ]);

        $response = $this->get(route('reports.journal-consolidation.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'group_by_branch' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['count'] === $expected['count']
                && $report['total_debit'] === $expected['total_debit']
                && $report['total_credit'] === $expected['total_credit']
                && $report['difference'] === $expected['difference']
                && $report['balanced'] === $expected['balanced']
                && $this->normalizeGroups($report['grouped']) === $this->normalizeGroups($expected['grouped'])
                && $this->normalizeCoaSummary($report['coa_summary']) === $this->normalizeCoaSummary($expected['coa_summary']);
        });

        $response->assertSee('Journal Consolidation');
        $response->assertSee($branchA->nama);
        $response->assertSee($branchB->nama);
        $response->assertSee('Rp 1.000.000');
    }

    public function test_journal_consolidation_preview_matches_shared_service_in_consolidated_mode_with_filters(): void
    {
        [$user] = $this->seedFixtureData();

        $this->actingAs($user);

        $expected = app(JournalConsolidationReportService::class)->generate([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'journal_type' => 'manual',
            'group_by_branch' => false,
        ]);

        $response = $this->get(route('reports.journal-consolidation.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'journal_type' => 'manual',
            'group_by_branch' => 0,
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['filters']['group_by_branch'] === false
                && $report['filters']['journal_type'] === 'manual'
                && $report['count'] === $expected['count']
                && $report['total_debit'] === $expected['total_debit']
                && $report['total_credit'] === $expected['total_credit']
                && count($report['grouped']) === 1
                && $report['grouped'][0]['cabang_name'] === 'Semua Cabang (Konsolidasi)'
                && $report['grouped'][0]['count'] === $expected['grouped'][0]['count']
                && $report['grouped'][0]['total_debit'] === $expected['grouped'][0]['total_debit']
                && $report['grouped'][0]['total_credit'] === $expected['grouped'][0]['total_credit'];
        });

        $response->assertSee('Semua Cabang (Konsolidasi)');
        $response->assertSee('manual');
        $response->assertSee('Rp 800.000');
    }

    private function seedFixtureData(): array
    {
        $user = User::factory()->create();
        $user->forceFill([
            'manage_type' => 'all',
        ])->save();

        $branchA = Cabang::factory()->create([
            'kode' => 'JC-A',
            'nama' => 'Cabang Preview A',
        ]);

        $branchB = Cabang::factory()->create([
            'kode' => 'JC-B',
            'nama' => 'Cabang Preview B',
        ]);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-9001',
            'name' => 'Kas Preview',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $revenue = ChartOfAccount::factory()->create([
            'code' => '4-9001',
            'name' => 'Pendapatan Preview',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $inventory = ChartOfAccount::factory()->create([
            'code' => '1-9002',
            'name' => 'Persediaan Preview',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-9001',
            'name' => 'Hutang Preview',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $expense = ChartOfAccount::factory()->create([
            'code' => '6-9001',
            'name' => 'Beban Preview',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->createJournal($cash, $branchA->id, '2026-04-05', 'JC-A-MAN-001', 500000, 0, 'manual');
        $this->createJournal($revenue, $branchA->id, '2026-04-05', 'JC-A-MAN-001', 0, 500000, 'manual');
        $this->createJournal($inventory, $branchB->id, '2026-04-06', 'JC-B-MAN-001', 300000, 0, 'manual');
        $this->createJournal($payable, $branchB->id, '2026-04-06', 'JC-B-MAN-001', 0, 300000, 'manual');
        $this->createJournal($expense, $branchA->id, '2026-04-07', 'JC-A-SAL-001', 200000, 0, 'sales');
        $this->createJournal($cash, $branchA->id, '2026-04-07', 'JC-A-SAL-001', 0, 200000, 'sales');

        return [$user, $branchA, $branchB];
    }

    private function createJournal(ChartOfAccount $coa, int $branchId, string $date, string $reference, float $debit, float $credit, string $journalType): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'journal consolidation preview fixture',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => $journalType,
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }

    private function normalizeGroups(array $groups): array
    {
        return collect($groups)
            ->map(fn (array $group) => [
                'cabang_name' => $group['cabang_name'],
                'count' => $group['count'],
                'total_debit' => $group['total_debit'],
                'total_credit' => $group['total_credit'],
                'balance' => $group['balance'],
            ])
            ->values()
            ->all();
    }

    private function normalizeCoaSummary(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row) => [
                'code' => $row['coa']?->code,
                'total_debit' => $row['total_debit'],
                'total_credit' => $row['total_credit'],
                'balance' => $row['balance'],
            ])
            ->values()
            ->all();
    }
}