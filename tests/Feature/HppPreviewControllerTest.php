<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\User;
use App\Services\Reports\HppReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HppPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedHppConfiguration();
    }

    public function test_hpp_preview_matches_hpp_service_values(): void
    {
        Carbon::setTestNow('2025-02-01 00:00:00');

        [$branchA] = $this->seedFixtureJournals();
        $user = User::factory()->create();

        $expected = app(HppReportService::class)->generate('2025-01-01', '2025-01-31');

        $response = $this->actingAs($user)->get(route('reports.hpp.preview', [
            'startDate' => '2025-01-01',
            'endDate' => '2025-01-31',
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['raw_materials'] === $expected['raw_materials']
                && $report['direct_labor'] === $expected['direct_labor']
                && $report['overhead']['total'] === $expected['overhead']['total']
                && $report['production_cost'] === $expected['production_cost']
                && $report['wip'] === $expected['wip']
                && $report['cogm'] === $expected['cogm'];
        });
        $response->assertSee('LAPORAN HARGA POKOK PRODUKSI');
        $response->assertSee(number_format((float) $expected['cogm'], 2, ',', '.'));

        $branchExpected = app(HppReportService::class)->generate('2025-01-01', '2025-01-31', [
            'branches' => [$branchA->id],
        ]);

        $branchResponse = $this->actingAs($user)->get(route('reports.hpp.preview', [
            'startDate' => '2025-01-01',
            'endDate' => '2025-01-31',
            'branchIds' => [$branchA->id],
        ]));

        $branchResponse->assertOk();
        $branchResponse->assertViewHas('report', function (array $report) use ($branchExpected) {
            return $report['raw_materials'] === $branchExpected['raw_materials']
                && $report['direct_labor'] === $branchExpected['direct_labor']
                && $report['overhead']['total'] === $branchExpected['overhead']['total']
                && $report['production_cost'] === $branchExpected['production_cost']
                && $report['wip'] === $branchExpected['wip']
                && $report['cogm'] === $branchExpected['cogm'];
        });
        $branchResponse->assertViewHas('selectedBranches', fn (array $selectedBranches) => $selectedBranches === [$branchA->nama]);
        $branchResponse->assertSee($branchA->nama);
        $branchResponse->assertSee(number_format((float) $branchExpected['cogm'], 2, ',', '.'));
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

    private function seedFixtureJournals(): array
    {
        $branchA = Cabang::factory()->create(['nama' => 'Branch A']);
        $branchB = Cabang::factory()->create(['nama' => 'Branch B']);

        $rawMaterial = $this->createAccount('1140.001', 'Persediaan Bahan Baku', 'Asset');
        $purchases = $this->createAccount('5110.001', 'Pembelian Bahan Baku', 'Expense');
        $directLabor = $this->createAccount('5120.001', 'Biaya Tenaga Kerja Langsung', 'Expense');
        $overheadElectric = $this->createAccount('5130.001', 'Biaya Listrik Pabrik', 'Expense');
        $overheadDepreciation = $this->createAccount('5140.001', 'Biaya Penyusutan Mesin', 'Expense');
        $overheadMaintenance = $this->createAccount('5150.001', 'Biaya Perawatan', 'Expense');
        $wip = $this->createAccount('1150.001', 'Persediaan Barang Dalam Proses', 'Asset');

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

    private function createAccount(string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_active' => true,
            'opening_balance' => 0,
            'debit' => 0,
            'credit' => 0,
            'ending_balance' => 0,
        ]);
    }

    private function createJournal(ChartOfAccount $coa, string $date, float $debit, float $credit, int $branchId): void
    {
        \App\Models\JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => 'TEST',
            'description' => 'hpp preview fixture',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => 'test',
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }
}