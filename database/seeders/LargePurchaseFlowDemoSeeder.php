<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LargePurchaseFlowDemoSeeder extends Seeder
{
    private const OR_NUMBER = 'OR-LARGE-DEMO-001';
    private const PO_A_NUMBER = 'PO-LARGE-DEMO-A';
    private const PO_B_NUMBER = 'PO-LARGE-DEMO-B';
    private const SUPPLIER_CODE = 'SUPP-LARGE-DEMO';
    private const PRODUCT_PREFIX = 'LARGE-DEMO-';
    private const ITEM_COUNT = 120;

    public function run(): void
    {
        DB::transaction(function () {
            $currency = Currency::firstOrCreate(
                ['code' => 'IDR'],
                ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
            );

            $uom = UnitOfMeasure::firstOrCreate(
                ['abbreviation' => 'PCS'],
                ['name' => 'Pieces']
            );

            $category = ProductCategory::firstOrCreate(
                ['kode' => 'LARGE-DEMO'],
                ['name' => 'Large Demo Products', 'kenaikan_harga' => 0]
            );

            $branchA = Cabang::firstOrCreate(
                ['kode' => 'LDEMO-A'],
                ['nama' => 'Large Demo Cabang A', 'alamat' => 'Demo Address A', 'status' => 1]
            );

            $branchB = Cabang::firstOrCreate(
                ['kode' => 'LDEMO-B'],
                ['nama' => 'Large Demo Cabang B', 'alamat' => 'Demo Address B', 'status' => 1]
            );

            $supplier = Supplier::firstOrCreate(
                ['code' => self::SUPPLIER_CODE],
                [
                    'perusahaan' => 'PT Supplier Large Demo',
                    'address' => 'Demo Supplier Address',
                    'phone' => '021-000000',
                    'email' => 'large-demo@supplier.test',
                    'handphone' => '081200000000',
                    'fax' => '021-000001',
                    'npwp' => '00.000.000.0-000.000',
                    'tempo_hutang' => 30,
                    'kontak_person' => 'Large Demo Contact',
                    'keterangan' => 'Supplier demo untuk OR/PO besar.',
                    'cabang_id' => $branchA->id,
                ]
            );

            $userId = User::query()->value('id') ?? User::factory()->create()->id;

            $orderRequest = OrderRequest::updateOrCreate(
                ['request_number' => self::OR_NUMBER],
                [
                    'request_date' => now()->toDateString(),
                    'status' => 'approved',
                    'note' => 'Demo OR besar untuk review performa item 100+.',
                    'created_by' => $userId,
                    'currency_id' => $currency->id,
                ]
            );

            $taxTypes = ['inklusif', 'eklusif', 'none'];
            $orderRequestItems = [];

            for ($index = 1; $index <= self::ITEM_COUNT; $index++) {
                $sku = self::PRODUCT_PREFIX . str_pad((string) $index, 3, '0', STR_PAD_LEFT);
                $branch = $index <= 60 ? $branchA : $branchB;
                $taxType = $taxTypes[($index - 1) % count($taxTypes)];
                $productTaxType = match ($taxType) {
                    'inklusif' => 'Inklusif',
                    'none' => 'Non Pajak',
                    default => 'Eksklusif',
                };
                $unitPrice = 10000 + ($index * 125);

                $product = Product::withoutGlobalScopes()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => 'Large Demo Product ' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                        'product_category_id' => $category->id,
                        'cabang_id' => $branch->id,
                        'cost_price' => $unitPrice,
                        'sell_price' => round($unitPrice * 1.2, 2),
                        'biaya' => 0,
                        'harga_batas' => 0,
                        'tipe_pajak' => $productTaxType,
                        'pajak' => $taxType === 'none' ? 0 : 11,
                        'jumlah_kelipatan_gudang_besar' => 1,
                        'jumlah_jual_kategori_banyak' => 1,
                        'kode_merk' => 'LDEMO',
                        'description' => 'Produk demo untuk OR/PO besar.',
                        'uom_id' => $uom->id,
                        'is_active' => true,
                    ]
                );

                $product->suppliers()->syncWithoutDetaching([
                    $supplier->id => ['supplier_price' => $unitPrice],
                ]);

                $quantity = ($index % 5) + 1;
                $base = $quantity * $unitPrice;
                $taxNominal = $taxType === 'none' ? 0 : round($base * 0.11, 2);
                $subtotal = $taxType === 'eklusif' ? $base + $taxNominal : $base;

                $orderRequestItem = OrderRequestItem::updateOrCreate(
                    [
                        'order_request_id' => $orderRequest->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'cabang_id' => $branch->id,
                        'quantity' => $quantity,
                        'fulfilled_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'unit_price_idr' => $unitPrice,
                        'original_price' => $unitPrice,
                        'original_price_idr' => $unitPrice,
                        'discount' => 0,
                        'tax' => $taxType === 'none' ? 0 : 11,
                        'tipe_pajak' => $taxType,
                        'subtotal' => $subtotal,
                        'note' => 'Large demo item #' . $index,
                        'currency_id' => $currency->id,
                    ]
                );

                $orderRequestItems[] = $orderRequestItem->fresh();
            }

            $poA = $this->upsertPurchaseOrder(self::PO_A_NUMBER, $supplier, $orderRequest, $branchA, $userId);
            $poB = $this->upsertPurchaseOrder(self::PO_B_NUMBER, $supplier, $orderRequest, $branchB, $userId);

            collect($orderRequestItems)->each(function (OrderRequestItem $item, int $index) use ($poA, $poB, $currency) {
                $purchaseOrder = $index < 60 ? $poA : $poB;

                PurchaseOrderItem::updateOrCreate(
                    [
                        'purchase_order_id' => $purchaseOrder->id,
                        'refer_item_model_type' => OrderRequestItem::class,
                        'refer_item_model_id' => $item->id,
                    ],
                    [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $item->discount ?? 0,
                        'tax' => $item->tax ?? 0,
                        'tipe_pajak' => $item->tipe_pajak,
                        'currency_id' => $item->currency_id ?? $currency->id,
                    ]
                );
            });

            $this->syncPurchaseOrderTotal($poA);
            $this->syncPurchaseOrderTotal($poB);
        });

        $this->command?->info('Large purchase flow demo ready: OR-LARGE-DEMO-001 with 120 items and 2 PO demo records.');
    }

    private function upsertPurchaseOrder(
        string $poNumber,
        Supplier $supplier,
        OrderRequest $orderRequest,
        Cabang $branch,
        int $userId
    ): PurchaseOrder {
        return PurchaseOrder::updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'cabang_id' => $branch->id,
                'order_date' => now()->toDateString(),
                'status' => 'approved',
                'expected_date' => now()->addDays(7)->toDateString(),
                'total_amount' => 0,
                'is_asset' => false,
                'date_approved' => now()->toDateString(),
                'approved_by' => $userId,
                'top_type' => 'credit_days',
                'tempo_hutang' => $supplier->tempo_hutang ?? 30,
                'note' => 'Demo PO besar dari OR-LARGE-DEMO-001.',
                'created_by' => $userId,
                'refer_model_type' => OrderRequest::class,
                'refer_model_id' => $orderRequest->id,
                'is_import' => false,
            ]
        );
    }

    private function syncPurchaseOrderTotal(PurchaseOrder $purchaseOrder): void
    {
        $total = $purchaseOrder->purchaseOrderItem()->get()->sum(function (PurchaseOrderItem $item) {
            $base = (float) $item->quantity * (float) $item->unit_price;
            $afterDiscount = $base - ($base * ((float) $item->discount / 100));
            $taxNominal = strtolower((string) $item->tipe_pajak) === 'none'
                ? 0
                : round($afterDiscount * ((float) $item->tax / 100), 2);

            return strtolower((string) $item->tipe_pajak) === 'eklusif'
                ? $afterDiscount + $taxNominal
                : $afterDiscount;
        });

        $purchaseOrder->update(['total_amount' => $total]);
    }
}
