<?php

namespace Database\Seeders\Finance;

use App\Models\Reports\CashFlowCashAccount;
use App\Models\Reports\CashFlowItem;
use App\Models\Reports\CashFlowItemPrefix;
use App\Models\Reports\CashFlowItemSource;
use App\Models\Reports\CashFlowSection;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppOverheadItemPrefix;
use App\Models\Reports\HppPrefix;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceReportConfigSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedCashFlow();
            $this->seedHpp();
        });
    }

    private function seedCashFlow(): void
    {
        $sections = [
            [
                'key' => 'operating',
                'label' => 'Aktivitas Operasi',
                'sort_order' => 1,
                'items' => [
                    [
                        'key' => 'cash_receipts_from_sales',
                        'label' => 'Penerimaan Kas dari Penjualan',
                        'type' => 'inflow',
                        'resolver' => 'salesReceipts',
                        'include_assets' => false,
                        'sort_order' => 1,
                        'prefixes' => [],
                        'asset_prefixes' => [],
                        'sources' => ['Penjualan', 'Customer Receipt'],
                    ],
                    [
                        'key' => 'non_operating_income',
                        'label' => 'Pendapatan Luar Usaha & Beban atas Pendapatan',
                        'type' => 'net',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 2,
                        'prefixes' => ['7000'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'selling_expenses',
                        'label' => 'Biaya Penjualan',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 3,
                        'prefixes' => ['6100'],
                        'asset_prefixes' => [],
                        'sources' => ['Biaya Penjualan', 'Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'insurance_expenses',
                        'label' => 'Biaya Asuransi',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 4,
                        'prefixes' => ['6220'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'rent_expenses',
                        'label' => 'Biaya Sewa',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 5,
                        'prefixes' => ['6230'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'lgat_expenses',
                        'label' => 'Biaya LGAT',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 6,
                        'prefixes' => ['6240'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'office_supplies_expenses',
                        'label' => 'Biaya Perlengkapan Kantor',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => true,
                        'sort_order' => 7,
                        'prefixes' => ['6250'],
                        'asset_prefixes' => ['1210.01', '1210.02'],
                        'sources' => ['Buku Kas & Bank', 'Aset'],
                    ],
                    [
                        'key' => 'office_needs_expenses',
                        'label' => 'Biaya Keperluan Kantor',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 8,
                        'prefixes' => ['6260'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'special_services_expenses',
                        'label' => 'Biaya Jasa Khusus',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 9,
                        'prefixes' => ['6270'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'general_admin_expenses',
                        'label' => 'Biaya Administrasi Umum',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 10,
                        'prefixes' => ['6280'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                    [
                        'key' => 'non_operating_expenses',
                        'label' => 'Biaya Diluar Usaha',
                        'type' => 'outflow',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 11,
                        'prefixes' => ['8000'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank'],
                    ],
                ],
            ],
            [
                'key' => 'investing',
                'label' => 'Aktivitas Investasi',
                'sort_order' => 2,
                'items' => [
                    [
                        'key' => 'asset_operations',
                        'label' => 'Operasi Aset',
                        'type' => 'net',
                        'resolver' => null,
                        'include_assets' => true,
                        'sort_order' => 1,
                        'prefixes' => ['1210', '1220', '1230', '1240', '1250'],
                        'asset_prefixes' => ['1210', '1220', '1230', '1240', '1250'],
                        'sources' => ['Buku Kas & Bank', 'Aset'],
                    ],
                ],
            ],
            [
                'key' => 'financing',
                'label' => 'Aktivitas Pendanaan',
                'sort_order' => 3,
                'items' => [
                    [
                        'key' => 'liability_operations',
                        'label' => 'Operasi Liabilitas',
                        'type' => 'net',
                        'resolver' => null,
                        'include_assets' => false,
                        'sort_order' => 1,
                        'prefixes' => ['2100', '2110', '2120', '2130', '2140', '2150', '2160', '2170', '2200', '2210', '2220'],
                        'asset_prefixes' => [],
                        'sources' => ['Buku Kas & Bank', 'Penjualan', 'Biaya Lain-lain Belum Terbayar'],
                    ],
                ],
            ],
        ];

        foreach ($sections as $sectionData) {
            $section = CashFlowSection::updateOrCreate(
                ['key' => $sectionData['key']],
                ['label' => $sectionData['label'], 'sort_order' => $sectionData['sort_order']]
            );

            $existingItemIds = [];

            foreach ($sectionData['items'] as $itemData) {
                $item = CashFlowItem::updateOrCreate(
                    ['key' => $itemData['key']],
                    [
                        'section_id' => $section->id,
                        'label' => $itemData['label'],
                        'type' => $itemData['type'],
                        'resolver' => $itemData['resolver'],
                        'include_assets' => $itemData['include_assets'],
                        'sort_order' => $itemData['sort_order'],
                    ]
                );

                $existingItemIds[] = $item->id;

                $item->prefixes()->delete();
                $item->sources()->delete();

                $prefixPayload = [];
                foreach ($itemData['prefixes'] as $prefix) {
                    $prefixPayload[] = ['prefix' => $prefix, 'is_asset' => false];
                }
                foreach ($itemData['asset_prefixes'] as $prefix) {
                    $prefixPayload[] = ['prefix' => $prefix, 'is_asset' => true];
                }

                if (!empty($prefixPayload)) {
                    $item->prefixes()->createMany($prefixPayload);
                }

                if (!empty($itemData['sources'])) {
                    $item->sources()->createMany(
                        collect($itemData['sources'])->values()->map(function (string $label, int $index) {
                            return [
                                'label' => $label,
                                'sort_order' => $index + 1,
                            ];
                        })->toArray()
                    );
                }
            }

            CashFlowItem::where('section_id', $section->id)
                ->whereNotIn('id', $existingItemIds)
                ->delete();
        }

        $cashAccounts = [
            ['prefix' => '1111', 'label' => 'Kas', 'sort_order' => 1],
            ['prefix' => '1112', 'label' => 'Bank', 'sort_order' => 2],
            ['prefix' => '1113', 'label' => 'Deposito', 'sort_order' => 3],
        ];

        foreach ($cashAccounts as $account) {
            CashFlowCashAccount::updateOrCreate(
                ['prefix' => $account['prefix']],
                ['label' => $account['label'], 'sort_order' => $account['sort_order']]
            );
        }
    }

    private function seedHpp(): void
    {
        $prefixGroups = [
            'raw_material_inventory' => ['1140.01'],
            'raw_material_purchase' => ['5110'],
            'direct_labor' => ['5120'],
            'wip_inventory' => ['1140.02'],
            'cogs_code' => ['5000', '5-1000'],
            'cogs_prefix' => ['5000', '5100'],
        ];

        foreach ($prefixGroups as $category => $prefixes) {
            $order = 1;
            foreach ($prefixes as $prefix) {
                HppPrefix::updateOrCreate(
                    ['category' => $category, 'prefix' => $prefix],
                    ['sort_order' => $order++]
                );
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
            $item = HppOverheadItem::updateOrCreate(
                ['key' => $itemData['key']],
                ['label' => $itemData['label'], 'sort_order' => $itemData['sort_order']]
            );

            $item->prefixes()->delete();
            $item->prefixes()->createMany(
                collect($itemData['prefixes'])->map(fn (string $prefix) => ['prefix' => $prefix])->toArray()
            );
        }
    }
}
