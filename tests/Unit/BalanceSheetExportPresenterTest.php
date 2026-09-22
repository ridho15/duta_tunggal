<?php

namespace Tests\Unit;

use App\Models\ChartOfAccount;
use App\Support\Reports\BalanceSheetExportPresenter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceSheetExportPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shapes_balance_sheet_rows_from_existing_report_payload(): void
    {
        $cash = ChartOfAccount::factory()->create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-1001',
            'name' => 'Utang Usaha',
            'type' => 'Liability',
        ]);

        $capital = ChartOfAccount::factory()->create([
            'code' => '3-1001',
            'name' => 'Modal Disetor',
            'type' => 'Equity',
        ]);

        $payload = [
            'assets' => [[
                'parent' => 'Aset Lancar',
                'items' => [['coa' => $cash, 'balance' => 5000000]],
                'subtotal' => 5000000,
            ]],
            'liabilities' => [[
                'parent' => 'Kewajiban Lancar',
                'items' => [['coa' => $payable, 'balance' => 1500000]],
                'subtotal' => 1500000,
            ]],
            'equity' => [[
                'parent' => 'Ekuitas',
                'items' => [['coa' => $capital, 'balance' => 3000000]],
                'subtotal' => 3000000,
            ]],
            'retained_earnings' => 250000,
            'current_earnings' => 250000,
            'asset_total' => 5000000,
            'liab_total' => 1500000,
            'equity_total' => 3500000,
            'balanced' => true,
        ];

        $rows = app(BalanceSheetExportPresenter::class)->rows($payload, Carbon::parse('2026-04-04'))->values()->all();

        $this->assertSame(['NERACA'], $rows[0]);
        $this->assertSame(['Per Tanggal: 04 Apr 2026'], $rows[1]);
        $this->assertContains(['A. ASET'], $rows);
        $this->assertContains(['1-1001', 'Kas', 5000000], $rows);
        $this->assertContains(['TOTAL KEWAJIBAN', '', 1500000], $rows);
        $this->assertContains(['Laba Ditahan (s/d periode)', '', 250000], $rows);
        $this->assertContains(['Laba Tahun Berjalan', '', 250000], $rows);
        $this->assertSame(['STATUS: BALANCED'], $rows[array_key_last($rows)]);
    }
}