<?php

/**
 * Feature tests for ProfitLossMultiDivisionService and ProfitLossMultiDivisionPage.
 *
 * Covers:
 * - Service: data structure, per-division balance computation, Vtc%, vector arithmetic
 * - Service: revenue, COGS, gross profit, opex, net profit calculations
 * - Filament page: mount, form defaults, generateReport toggle
 */

use App\Filament\Pages\ProfitLossMultiDivisionPage;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\ProfitLossMultiDivisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Test helpers / fixtures
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a minimal Cabang (division/branch) for testing.
 */
function makeCabang(string $kode, string $nama): Cabang
{
    return Cabang::create([
        'kode'    => $kode,
        'nama'    => $nama,
        'alamat'  => 'Test',
        'telepon' => '021-000',
    ]);
}

/**
 * Create a ChartOfAccount with sensible defaults.
 */
function makeCoa(string $code, string $name, string $type, ?int $parentId = null): ChartOfAccount
{
    return ChartOfAccount::create([
        'code'      => $code,
        'name'      => $name,
        'type'      => $type,
        'parent_id' => $parentId,
        'is_active' => true,
    ]);
}

/**
 * Create a JournalEntry for a specific COA and Cabang.
 */
function makeJe(int $coaId, int $cabangId, float $debit, float $credit, string $date = '2025-06-15'): JournalEntry
{
    return JournalEntry::create([
        'coa_id'      => $coaId,
        'cabang_id'   => $cabangId,
        'date'        => $date,
        'description' => 'Test entry',
        'debit'       => $debit,
        'credit'      => $credit,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// ProfitLossMultiDivisionService tests
// ─────────────────────────────────────────────────────────────────────────────

describe('ProfitLossMultiDivisionService', function () {

    beforeEach(function () {
        $this->service = new ProfitLossMultiDivisionService();

        // Divisions
        $this->divA = makeCabang('ACC', 'Accounting');
        $this->divB = makeCabang('DIR', 'Director');

        // Revenue COA
        $this->revParent = makeCoa('4100', 'PENJUALAN BARANG DAGANGAN', 'Revenue');
        $this->revChild  = makeCoa('4100.10', 'PENJUALAN BARANG DAGANGAN - DEFAULT', 'Revenue', $this->revParent->id);

        // COGS COA
        $this->cogsParent = makeCoa('5000', 'HARGA POKOK PENJUALAN', 'Expense');
        $this->cogsChild  = makeCoa('5000.01', 'HPP BARANG DAGANGAN', 'Expense', $this->cogsParent->id);

        // Expense COA (Operating)
        $this->opexParent = makeCoa('6100', 'BIAYA PENJUALAN', 'Expense');
        $this->opexChild  = makeCoa('6100.01', 'BIAYA PACKING', 'Expense', $this->opexParent->id);
    });

    // ── Structure ──────────────────────────────────────────────────────────────

    it('returns all required keys', function () {
        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result)->toBeArray()
            ->toHaveKey('divisions')
            ->toHaveKey('revenue_rows')
            ->toHaveKey('total_revenue')
            ->toHaveKey('cogs_rows')
            ->toHaveKey('total_cogs')
            ->toHaveKey('gross_profit')
            ->toHaveKey('opex_sections')
            ->toHaveKey('total_opex')
            ->toHaveKey('operating_profit')
            ->toHaveKey('other_rows')
            ->toHaveKey('net_profit')
            ->toHaveKey('vtc')
            ->toHaveKey('period');
    });

    it('includes all divisions when no filter is applied', function () {
        $result = $this->service->generate('2025-01-01', '2025-12-31');

        $ids = array_column($result['divisions'], 'id');
        expect($ids)->toContain($this->divA->id)
                    ->toContain($this->divB->id);
    });

    it('filters divisions when cabangIds are provided', function () {
        $result = $this->service->generate('2025-01-01', '2025-12-31', [$this->divA->id]);

        $ids = array_column($result['divisions'], 'id');
        expect($ids)->toContain($this->divA->id)
                    ->not->toContain($this->divB->id);
    });

    // ── Revenue balance computation ────────────────────────────────────────────

    it('computes revenue balance (credit - debit) per division', function () {
        // Division A: 1,000,000 credit, 0 debit → balance = 1,000,000
        makeJe($this->revChild->id, $this->divA->id, 0, 1_000_000);
        // Division B: 500,000 credit, 100,000 debit → balance = 400,000
        makeJe($this->revChild->id, $this->divB->id, 100_000, 500_000);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_revenue'][$this->divA->id])->toBe(1_000_000.0);
        expect($result['total_revenue'][$this->divB->id])->toBe(400_000.0);
    });

    it('sums multiple journal entries for the same division', function () {
        makeJe($this->revChild->id, $this->divA->id, 0, 600_000);
        makeJe($this->revChild->id, $this->divA->id, 0, 400_000);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_revenue'][$this->divA->id])->toBe(1_000_000.0);
    });

    it('ignores entries outside the date range', function () {
        makeJe($this->revChild->id, $this->divA->id, 0, 1_000_000, '2024-12-31');
        makeJe($this->revChild->id, $this->divA->id, 0, 500_000,   '2025-01-01');

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_revenue'][$this->divA->id])->toBe(500_000.0);
    });

    // ── COGS computation ──────────────────────────────────────────────────────

    it('computes COGS balance (debit - credit) for 5xxx accounts', function () {
        makeJe($this->cogsChild->id, $this->divA->id, 300_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_cogs'][$this->divA->id])->toBe(300_000.0);
    });

    // ── Gross Profit computation ──────────────────────────────────────────────

    it('computes gross profit = revenue - cogs per division', function () {
        makeJe($this->revChild->id,  $this->divA->id, 0,       1_000_000);
        makeJe($this->cogsChild->id, $this->divA->id, 600_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['gross_profit'][$this->divA->id])->toBe(400_000.0);
    });

    it('returns zero gross profit when both revenue and cogs are zero', function () {
        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['gross_profit'][$this->divA->id])->toBe(0.0);
    });

    // ── Operating Expense computation ─────────────────────────────────────────

    it('computes opex balance (debit - credit) for 6xxx accounts', function () {
        makeJe($this->opexChild->id, $this->divA->id, 50_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_opex'][$this->divA->id])->toBe(50_000.0);
    });

    it('segments opex into sections by parent account', function () {
        makeJe($this->opexChild->id, $this->divA->id, 50_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['opex_sections'])->not->toBeEmpty();
        $firstSection = $result['opex_sections'][0];
        expect($firstSection)->toHaveKey('account')
                              ->toHaveKey('rows')
                              ->toHaveKey('total');
    });

    // ── Operating Profit ──────────────────────────────────────────────────────

    it('computes operating profit = gross profit - total opex', function () {
        makeJe($this->revChild->id,  $this->divA->id, 0,       1_000_000);
        makeJe($this->cogsChild->id, $this->divA->id, 600_000, 0);
        makeJe($this->opexChild->id, $this->divA->id, 100_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        // gross_profit = 400,000; opex = 100,000 → operating profit = 300,000
        expect($result['operating_profit'][$this->divA->id])->toBe(300_000.0);
    });

    // ── Net Profit ────────────────────────────────────────────────────────────

    it('net profit equals operating profit when there are no other income/expense items', function () {
        makeJe($this->revChild->id,  $this->divA->id, 0,       1_000_000);
        makeJe($this->cogsChild->id, $this->divA->id, 600_000, 0);
        makeJe($this->opexChild->id, $this->divA->id, 100_000, 0);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['net_profit'][$this->divA->id])
            ->toBe($result['operating_profit'][$this->divA->id]);
    });

    // ── Vtc% computation ──────────────────────────────────────────────────────

    it('computes vtc percent as balance / revenue * 100', function () {
        $service = $this->service;
        $divIds  = [1, 2];

        $values  = [1 => 400_000.0, 2 => 200_000.0];
        $revenue = [1 => 1_000_000.0, 2 => 500_000.0];

        $vtc = $service->computeVtc($values, $revenue, $divIds);

        expect($vtc[1])->toBe(40.0);
        expect($vtc[2])->toBe(40.0);
    });

    it('returns zero vtc when revenue is zero', function () {
        $service = $this->service;
        $divIds  = [1];
        $vtc = $service->computeVtc([1 => 100.0], [1 => 0.0], $divIds);

        expect($vtc[1])->toBe(0.0);
    });

    // ── Vector arithmetic ─────────────────────────────────────────────────────

    it('subtractVectors computes a - b correctly', function () {
        $service = $this->service;
        $divIds  = [1, 2];

        $a = [1 => 1000.0, 2 => 500.0];
        $b = [1 => 300.0,  2 => 200.0];

        $result = $service->subtractVectors($a, $b, $divIds);

        expect($result[1])->toBe(700.0);
        expect($result[2])->toBe(300.0);
    });

    it('addVectors computes a + b correctly', function () {
        $service = $this->service;
        $divIds  = [1, 2];

        $a = [1 => 1000.0, 2 => 500.0];
        $b = [1 => 300.0,  2 => 200.0];

        $result = $service->addVectors($a, $b, $divIds);

        expect($result[1])->toBe(1300.0);
        expect($result[2])->toBe(700.0);
    });

    // ── Revenue rows structure ─────────────────────────────────────────────────

    it('revenue_rows contains section_header, account, subtotal and total_revenue rows', function () {
        makeJe($this->revChild->id, $this->divA->id, 0, 1_000_000);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        $types = array_column($result['revenue_rows'], 'type');
        expect($types)->toContain('section_header')
                      ->toContain('account')
                      ->toContain('subtotal')
                      ->toContain('total_revenue');
    });

    it('account rows in revenue_rows carry per-division balances', function () {
        makeJe($this->revChild->id, $this->divA->id, 0, 750_000);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        $accountRows = array_filter($result['revenue_rows'], fn ($r) => $r['type'] === 'account');
        $accountRow  = array_values($accountRows)[0] ?? null;

        expect($accountRow)->not->toBeNull();
        expect($accountRow['balances'][$this->divA->id])->toBe(750_000.0);
    });

    // ── Period stored in result ────────────────────────────────────────────────

    it('stores the queried period in the result', function () {
        $result = $this->service->generate('2025-03-01', '2025-09-30');

        expect($result['period']['start'])->toBe('2025-03-01');
        expect($result['period']['end'])->toBe('2025-09-30');
    });

    // ── Cross-division isolation ───────────────────────────────────────────────

    it('balances for division A do not leak into division B', function () {
        makeJe($this->revChild->id, $this->divA->id, 0, 1_000_000);

        $result = $this->service->generate('2025-01-01', '2025-12-31');

        expect($result['total_revenue'][$this->divA->id])->toBe(1_000_000.0);
        expect($result['total_revenue'][$this->divB->id])->toBe(0.0);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// ProfitLossMultiDivisionPage Livewire tests
// ─────────────────────────────────────────────────────────────────────────────

describe('ProfitLossMultiDivisionPage', function () {

    beforeEach(function () {
        // Create a user and authenticate for Livewire tests
        $this->user = \App\Models\User::factory()->create();
        $this->actingAs($this->user);
    });

    it('mounts with default date range (current year)', function () {
        $component = Livewire::test(ProfitLossMultiDivisionPage::class);

        $component->assertSet('showReport', false);
        $component->assertSet('cabangIds', []);
        expect($component->get('startDate'))->toBe(now()->startOfYear()->format('Y-m-d'));
        expect($component->get('endDate'))->toBe(now()->endOfYear()->format('Y-m-d'));
    });

    it('sets showReport to true when generateReport is called', function () {
        Livewire::test(ProfitLossMultiDivisionPage::class)
            ->call('generateReport')
            ->assertDispatched('open-report-preview')
            ->assertSet('showReport', true);
    });

    it('sets showReport to false when resetReport is called', function () {
        Livewire::test(ProfitLossMultiDivisionPage::class)
            ->set('showReport', true)
            ->call('resetReport')
            ->assertSet('showReport', false);
    });

    it('returns report data array from getReportData', function () {
        $component = Livewire::test(ProfitLossMultiDivisionPage::class);
        $instance  = $component->instance();

        $data = $instance->getReportData();

        expect($data)->toBeArray()
            ->toHaveKey('divisions')
            ->toHaveKey('total_revenue')
            ->toHaveKey('gross_profit')
            ->toHaveKey('net_profit');
    });

    it('page renders without errors', function () {
        Livewire::test(ProfitLossMultiDivisionPage::class)
            ->assertStatus(200);
    });

    it('shows report table after generateReport', function () {
        // Seed some data so the report is non-trivial
        $div = makeCabang('TST', 'Test Division');
        $rev = makeCoa('4200', 'Revenue Test Parent', 'Revenue');
        $rc  = makeCoa('4200.01', 'Revenue Test Leaf', 'Revenue', $rev->id);
        makeJe($rc->id, $div->id, 0, 1_000_000);

        Livewire::test(ProfitLossMultiDivisionPage::class)
            ->set('startDate', '2025-01-01')
            ->set('endDate', '2025-12-31')
            ->call('generateReport')
            ->assertSet('showReport', true);
    });

    it('builds preview and export urls with selected filters', function () {
        $divA = makeCabang('AA2', 'Alpha 2');
        $divB = makeCabang('BB2', 'Beta 2');

        $component = Livewire::test(ProfitLossMultiDivisionPage::class)
            ->set('startDate', '2025-04-01')
            ->set('endDate', '2025-04-30')
            ->set('cabangIds', [$divA->id, $divB->id]);

        $instance = $component->instance();

        expect($instance->getPreviewUrl())->toContain('reports/profit-loss-multi-division/preview');
        expect($instance->getPreviewUrl())->toContain('startDate=2025-04-01');
        expect($instance->getPreviewUrl())->toContain('endDate=2025-04-30');
        expect($instance->getExportUrl())->toContain('reports/profit-loss-multi-division/download-excel');
    });

    it('accepts specific cabangIds filter', function () {
        $divA = makeCabang('AA1', 'Alpha');
        $divB = makeCabang('BB1', 'Beta');

        $component = Livewire::test(ProfitLossMultiDivisionPage::class)
            ->set('cabangIds', [$divA->id]);

        expect($component->get('cabangIds'))->toEqual([$divA->id]);
    });
});
