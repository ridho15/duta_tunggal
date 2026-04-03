<?php

use App\Filament\Resources\Reports\BalanceSheetResource\Pages\ViewBalanceSheet;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;

/**
 * Tests for the Classic Two-Column Balance Sheet layout feature.
 * Uses direct instantiation following ViewBalanceSheetPrintTest.php pattern.
 */
describe('Classic Balance Sheet Layout', function () {

    beforeEach(function () {
        $this->cabang = Cabang::create([
            'kode' => 'BSC', 'nama' => 'Classic Test Branch',
            'alamat' => 'Jl. Test No. 1', 'telepon' => '0812000001',
        ]);

        $this->kasGroup = ChartOfAccount::create([
            'code' => '100', 'name' => 'KAS DAN BANK',
            'type' => 'Asset', 'is_active' => true, 'is_current' => true,
        ]);
        $this->kasKecil = ChartOfAccount::create([
            'code' => '100.01', 'name' => 'KAS KECIL', 'type' => 'Asset',
            'parent_id' => $this->kasGroup->id, 'is_active' => true, 'is_current' => true,
        ]);
        $this->kasBesar = ChartOfAccount::create([
            'code' => '100.02', 'name' => 'KAS BESAR', 'type' => 'Asset',
            'parent_id' => $this->kasGroup->id, 'is_active' => true, 'is_current' => true,
        ]);
        $this->bankGroup = ChartOfAccount::create([
            'code' => '102', 'name' => 'BANK', 'type' => 'Asset',
            'is_active' => true, 'is_current' => true,
        ]);
        $this->bankBca = ChartOfAccount::create([
            'code' => '102.01', 'name' => 'BANK BCA', 'type' => 'Asset',
            'parent_id' => $this->bankGroup->id, 'is_active' => true, 'is_current' => true,
        ]);
        $this->hutangGroup = ChartOfAccount::create([
            'code' => '201', 'name' => 'HUTANG DAGANG', 'type' => 'Liability',
            'is_active' => true, 'is_current' => true,
        ]);
        $this->hutangIdr = ChartOfAccount::create([
            'code' => '201.01', 'name' => 'HUTANG IDR', 'type' => 'Liability',
            'parent_id' => $this->hutangGroup->id, 'is_active' => true, 'is_current' => true,
        ]);
        $this->modalGroup = ChartOfAccount::create([
            'code' => '301', 'name' => 'MODAL', 'type' => 'Equity', 'is_active' => true,
        ]);
        $this->modalSaham = ChartOfAccount::create([
            'code' => '301.01', 'name' => 'MODAL SAHAM', 'type' => 'Equity',
            'parent_id' => $this->modalGroup->id, 'is_active' => true,
        ]);

        $entries = [
            [$this->kasKecil,   'CL-001', 5_000_000,  0],
            [$this->kasBesar,   'CL-002', 20_000_000, 0],
            [$this->bankBca,    'CL-003', 50_000_000, 0],
            [$this->hutangIdr,  'CL-004', 0, 30_000_000],
            [$this->modalSaham, 'CL-005', 0, 45_000_000],
        ];
        foreach ($entries as [$coa, $ref, $debit, $credit]) {
            JournalEntry::create([
                'coa_id' => $coa->id, 'cabang_id' => $this->cabang->id,
                'date' => '2026-04-30', 'reference' => $ref,
                'source_type' => 'manual', 'source_id' => 1, 'description' => $ref,
                'debit' => $debit, 'credit' => $credit,
            ]);
        }

        /** @return ViewBalanceSheet */
        $this->makePage = function (string $date = '2026-04-30', bool $showZero = false) {
            $page = new ViewBalanceSheet();
            $page->as_of_date = $date;
            $page->include_zero_balances = $showZero;
            return $page;
        };
    });

    // ───────────────────────────── buildClassicGroups() ─────────────────
    describe('buildClassicGroups()', function () {

        it('returns at least 2 asset groups', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect(count($data['asset_groups']))->toBeGreaterThanOrEqual(2);
        });

        it('each group has required keys', function () {
            $data = ($this->makePage)()->getClassicReportData();
            foreach ($data['asset_groups'] as $group) {
                expect($group)->toHaveKeys(['parent_code', 'parent_name', 'children', 'total', 'total_label']);
            }
        });

        it('KAS DAN BANK group total = 25_000_000', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['asset_groups'])->firstWhere('parent_code', '100');
            expect($g['total'])->toBe(25_000_000.0);
        });

        it('children include code/name/balance', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['asset_groups'])->firstWhere('parent_code', '100');
            expect(count($g['children']))->toBe(2);
            foreach ($g['children'] as $c) {
                expect($c)->toHaveKeys(['code', 'name', 'balance']);
            }
        });

        it('children include 100.01 and 100.02', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $codes = array_column(
                collect($data['asset_groups'])->firstWhere('parent_code', '100')['children'],
                'code'
            );
            expect($codes)->toContain('100.01');
            expect($codes)->toContain('100.02');
        });

        it('groups sorted by parent_code ascending', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $codes  = array_column($data['asset_groups'], 'parent_code');
            $sorted = $codes;
            sort($sorted);
            expect($codes)->toBe($sorted);
        });

        it('zero-balance child excluded when include_zero_balances=false', function () {
            ChartOfAccount::create([
                'code' => '100.03', 'name' => 'KAS KOSONG', 'type' => 'Asset',
                'parent_id' => $this->kasGroup->id, 'is_active' => true, 'is_current' => true,
            ]);
            $data  = ($this->makePage)('2026-04-30', false)->getClassicReportData();
            $codes = array_column(
                collect($data['asset_groups'])->firstWhere('parent_code', '100')['children'], 'code'
            );
            expect($codes)->not->toContain('100.03');
        });

        it('zero-balance child included when include_zero_balances=true', function () {
            ChartOfAccount::create([
                'code' => '100.03', 'name' => 'KAS KOSONG', 'type' => 'Asset',
                'parent_id' => $this->kasGroup->id, 'is_active' => true, 'is_current' => true,
            ]);
            $data  = ($this->makePage)('2026-04-30', true)->getClassicReportData();
            $codes = array_column(
                collect($data['asset_groups'])->firstWhere('parent_code', '100')['children'], 'code'
            );
            expect($codes)->toContain('100.03');
        });

        it('total_label = "TOTAL " + uppercase parent name', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['asset_groups'])->firstWhere('parent_code', '100');
            expect($g['total_label'])->toBe('TOTAL KAS DAN BANK');
        });
    });

    // ─────────────────────────── getClassicReportData() ────────────────
    describe('getClassicReportData()', function () {

        it('returns all required top-level keys', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect($data)->toHaveKeys([
                'asset_groups', 'liability_groups', 'equity_groups',
                'retained_earnings', 'current_earnings',
                'total_assets', 'total_liabilities', 'total_equity',
                'total_liabilities_and_equity', 'is_balanced', 'difference',
            ]);
        });

        it('total_assets = 75_000_000', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect($data['total_assets'])->toBe(75_000_000.0);
        });

        it('BANK group total = 50_000_000', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['asset_groups'])->firstWhere('parent_code', '102');
            expect($g['total'])->toBe(50_000_000.0);
        });

        it('HUTANG DAGANG total = 30_000_000', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['liability_groups'])->firstWhere('parent_code', '201');
            expect($g['total'])->toBe(30_000_000.0);
            expect($g['total_label'])->toBe('TOTAL HUTANG DAGANG');
        });

        it('MODAL equity total = 45_000_000', function () {
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['equity_groups'])->firstWhere('parent_code', '301');
            expect($g['total'])->toBe(45_000_000.0);
        });

        it('is_balanced is bool', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect($data['is_balanced'])->toBeIn([true, false]);
        });

        it('liability total negative when debit > credit', function () {
            JournalEntry::create([
                'coa_id' => $this->hutangIdr->id, 'cabang_id' => $this->cabang->id,
                'date' => '2026-04-30', 'reference' => 'NEG-001',
                'source_type' => 'manual', 'source_id' => 1, 'description' => 'Over-payment',
                'debit' => 35_000_000, 'credit' => 0,
            ]);
            $data = ($this->makePage)()->getClassicReportData();
            $g = collect($data['liability_groups'])->firstWhere('parent_code', '201');
            expect($g['total'])->toBeLessThan(0);
        });

        it('total_liabilities_and_equity = total_liabilities + total_equity', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect($data['total_liabilities_and_equity'])
                ->toBe($data['total_liabilities'] + $data['total_equity']);
        });

        it('sum of asset group totals = total_assets when showZero=true', function () {
            $data = ($this->makePage)('2026-04-30', true)->getClassicReportData();
            $groupSum = array_sum(array_column($data['asset_groups'], 'total'));
            expect($data['total_assets'])->toBe($groupSum);
        });
    });

    // ─────────────────────────── classic_view property ─────────────────
    describe('classic_view property', function () {

        it('defaults to false', function () {
            $page = ($this->makePage)();
            expect($page->classic_view)->toBeFalse();
        });

        it('can be set to true', function () {
            $page = ($this->makePage)();
            $page->classic_view = true;
            expect($page->classic_view)->toBeTrue();
        });

        it('asset_groups is an array', function () {
            $data = ($this->makePage)()->getClassicReportData();
            expect($data['asset_groups'])->toBeArray();
        });

        it('all child balances are float', function () {
            $data = ($this->makePage)()->getClassicReportData();
            foreach ($data['asset_groups'] as $group) {
                foreach ($group['children'] as $child) {
                    expect($child['balance'])->toBeFloat();
                }
            }
        });
    });
});
