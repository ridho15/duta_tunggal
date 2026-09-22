<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\TrialBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalancePreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_balance_preview_matches_trial_balance_service(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Trial Balance Preview Branch']);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-0001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 100000,
        ]);

        $revenue = ChartOfAccount::factory()->create([
            'code' => '4-0001',
            'name' => 'Pendapatan',
            'type' => 'Revenue',
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        JournalEntry::factory()->create([
            'coa_id' => $cash->id,
            'date' => '2025-01-15',
            'debit' => 250000,
            'credit' => 0,
            'cabang_id' => $branch->id,
            'journal_type' => 'manual',
        ]);

        JournalEntry::factory()->create([
            'coa_id' => $revenue->id,
            'date' => '2025-01-15',
            'debit' => 0,
            'credit' => 250000,
            'cabang_id' => $branch->id,
            'journal_type' => 'manual',
        ]);

        $expected = app(TrialBalanceService::class)->generate([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'cabang_id' => $branch->id,
            'show_zero_balance' => false,
        ]);

        $response = $this->actingAs($user)->get(route('reports.trial-balance.preview', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['period']['start_date'] === $expected['period']['start_date']
                && $report['period']['end_date'] === $expected['period']['end_date']
                && $report['grand_totals']['beginning_balance'] === $expected['grand_totals']['beginning_balance']
                && $report['grand_totals']['period_debit'] === $expected['grand_totals']['period_debit']
                && $report['grand_totals']['period_credit'] === $expected['grand_totals']['period_credit']
                && $report['grand_totals']['ending_balance'] === $expected['grand_totals']['ending_balance'];
        });
        $response->assertSee('350.000');
    }
}