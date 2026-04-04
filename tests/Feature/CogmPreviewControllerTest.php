<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\User;
use App\Services\Reports\HppReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CogmPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedHppConfiguration();
    }

    public function test_preview_report_matches_hpp_service_values(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        [$branchA] = $this->seedCogmJournals();
        $user = User::factory()->create();

        $expected = app(HppReportService::class)->generate('2025-01-01', '2025-01-31');

        $response = $this->actingAs($user)->get(route('reports.cogm.preview', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['opening_wip'] === $expected['wip']['opening']
                && $report['raw_material_used'] === $expected['raw_materials']['used']
                && $report['labor_cost'] === $expected['direct_labor']
                && $report['overhead'] === $expected['overhead']['total']
                && $report['total_cost_added'] === $expected['production_cost']
                && $report['closing_wip'] === $expected['wip']['closing']
                && $report['cogm'] === $expected['cogm'];
        });
        $response->assertSee('Rp 6.120');

        $branchExpected = app(HppReportService::class)->generate('2025-01-01', '2025-01-31', [
            'branches' => [$branchA->id],
        ]);

        $branchResponse = $this->actingAs($user)->get(route('reports.cogm.preview', [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'cabang_id' => $branchA->id,
        ]));

        $branchResponse->assertOk();
        $branchResponse->assertViewHas('report', function (array $report) use ($branchExpected) {
            return $report['opening_wip'] === $branchExpected['wip']['opening']
                && $report['raw_material_used'] === $branchExpected['raw_materials']['used']
                && $report['labor_cost'] === $branchExpected['direct_labor']
                && $report['overhead'] === $branchExpected['overhead']['total']
                && $report['total_cost_added'] === $branchExpected['production_cost']
                && $report['closing_wip'] === $branchExpected['wip']['closing']
                && $report['cogm'] === $branchExpected['cogm'];
        });
        $branchResponse->assertSee('Rp 5.150');
    }

    private function seedHppConfiguration(): void
    {
        $prefixGroups = [
            'raw_material_inventory' => ['1140.001'],
            'raw_material_purchase' => ['5110'],
            'direct_labor' => ['5120'],
            'wip_inventory' => ['1150.001'],
        ];

        foreach ($prefixGroups as $category => $prefixes) {
            $order = 1;
            foreach ($prefixes as $prefix) {
                HppPrefix::create([
                    'category' => $category,
                    'prefix' => $prefix,
                    'sort_order' => $order++,
                ]);
            }
        }

        $overheadItems = [
            [
                'key' => 'factory_electricity',
                'label' => 'Biaya Listrik Pabrik',
                'sort_order' => 1,
                'prefixes' => ['5130'],
            ],
            [
                'key' => 'machine_depreciation',
                'label' => 'Biaya Penyusutan Mesin',
                'sort_order' => 2,
                'prefixes' => ['5140'],
            ],
            [
                'key' => 'maintenance',
                'label' => 'Biaya Perawatan',
                'sort_order' => 3,
                'prefixes' => ['5150'],
            ],
        ];

        foreach ($overheadItems as $itemData) {
            $item = HppOverheadItem::create([
                'key' => $itemData['key'],
                'label' => $itemData['label'],
                'sort_order' => $itemData['sort_order'],
            ]);

            foreach ($itemData['prefixes'] as $prefix) {
                $item->prefixes()->create(['prefix' => $prefix]);
            }
        }
    }

    private function seedCogmJournals(): array
    {
        $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
        $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

        $rawMaterial = ChartOfAccount::create([
            'code' => '1140.001',
            'name' => 'Persediaan Bahan Baku',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $purchases = ChartOfAccount::create([
            'code' => '5110.001',
            'name' => 'Pembelian Bahan Baku',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $directLabor = ChartOfAccount::create([
            'code' => '5120.001',
            'name' => 'Biaya Tenaga Kerja Langsung',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadElectric = ChartOfAccount::create([
            'code' => '5130.001',
            'name' => 'Biaya Listrik Pabrik',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadDepreciation = ChartOfAccount::create([
            'code' => '5140.001',
            'name' => 'Biaya Penyusutan Mesin',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $overheadMaintenance = ChartOfAccount::create([
            'code' => '5150.001',
            'name' => 'Biaya Perawatan',
            'type' => 'Expense',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $wip = ChartOfAccount::create([
            'code' => '1150.001',
            'name' => 'Persediaan Barang Dalam Proses',
            'type' => 'Asset',
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);

        $this->createJournal($rawMaterial, '2024-12-31', 1000, 0, $branchA->id);
        $this->createJournal($wip, '2024-12-31', 400, 0, $branchA->id);
        $this->createJournal($rawMaterial, '2025-01-05', 0, 300, $branchA->id);
        $this->createJournal($rawMaterial, '2025-01-20', 0, 200, $branchA->id);
        $this->createJournal($purchases, '2025-01-10', 2000, 0, $branchA->id);
        $this->createJournal($directLabor, '2025-01-12', 1500, 0, $branchA->id);
        $this->createJournal($overheadElectric, '2025-01-18', 300, 0, $branchA->id);
        $this->createJournal($overheadDepreciation, '2025-01-25', 500, 0, $branchA->id);
        $this->createJournal($overheadMaintenance, '2025-01-28', 200, 0, $branchA->id);
        $this->createJournal($wip, '2025-01-10', 200, 0, $branchA->id);
        $this->createJournal($wip, '2025-01-25', 0, 350, $branchA->id);

        $this->createJournal($rawMaterial, '2024-12-31', 500, 0, $branchB->id);
        $this->createJournal($wip, '2024-12-31', 200, 0, $branchB->id);
        $this->createJournal($rawMaterial, '2025-01-07', 0, 100, $branchB->id);
        $this->createJournal($purchases, '2025-01-15', 400, 0, $branchB->id);
        $this->createJournal($directLabor, '2025-01-18', 300, 0, $branchB->id);
        $this->createJournal($overheadElectric, '2025-01-22', 100, 0, $branchB->id);
        $this->createJournal($overheadMaintenance, '2025-01-29', 50, 0, $branchB->id);
        $this->createJournal($wip, '2025-01-11', 100, 0, $branchB->id);
        $this->createJournal($wip, '2025-01-27', 0, 120, $branchB->id);

        return [$branchA, $branchB];
    }

    private function createJournal(ChartOfAccount $coa, string $date, float $debit, float $credit, int $branchId): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => 'TEST',
            'description' => 'test entry',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => 'test',
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }
}