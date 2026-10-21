<?php

namespace App\Http\Controllers\Gudang;

use App\Exports\InventoryCardExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Reports\InventoryCardReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class StockController extends Controller
{
    /**
     * Tampilan Cek Stok Real-Time (Overview Persediaan & Stok per Gudang).
     */
    public function index(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $search = trim($request->input('search', ''));
        $categoryId = $request->integer('category_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $status = $request->input('status', 'all');

        // Query dasar produk dengan stok
        $query = Product::query()
            ->with([
                'productCategory',
                'uom',
                'inventoryStock' => function ($q) use ($warehouseId) {
                    if ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }
                    $q->with(['warehouse', 'rak']);
                },
            ]);

        // Filter pencarian
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter kategori
        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        // Filter jika spesifik gudang yang memiliki stok
        if ($warehouseId) {
            $query->whereHas('inventoryStock', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        // Ambil koleksi untuk menghitung KPI & filter status sebelum paginasi
        // Hitung total KPI seluruh produk
        $allStocksQuery = InventoryStock::query();
        if ($warehouseId) {
            $allStocksQuery->where('warehouse_id', $warehouseId);
        }

        $totalActiveProducts = Product::where('is_active', true)->count();
        $totalPhysicalQty = (float) $allStocksQuery->sum('qty_available');
        $totalReservedQty = (float) (clone $allStocksQuery)->sum('qty_reserved');
        $totalFreeQty = $totalPhysicalQty - $totalReservedQty;

        // Estimasi nilai aset persediaan: join products and inventory_stocks
        $totalAssetValue = (float) DB::table('inventory_stocks')
            ->join('products', 'inventory_stocks.product_id', '=', 'products.id')
            ->when($warehouseId, fn($q) => $q->where('inventory_stocks.warehouse_id', $warehouseId))
            ->whereNull('inventory_stocks.deleted_at')
            ->whereNull('products.deleted_at')
            ->sum(DB::raw('inventory_stocks.qty_available * products.cost_price'));

        // Hitung produk menipis / kritis
        $criticalCount = Product::whereHas('inventoryStock', function ($q) {
            $q->whereRaw('qty_available <= qty_min');
        })->count();

        // Filter Status Stok (Aman, Menipis, Habis)
        if ($status === 'out_of_stock') {
            $query->whereDoesntHave('inventoryStock', function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
                $q->where('qty_available', '>', 0);
            });
        } elseif ($status === 'low_stock') {
            $query->whereHas('inventoryStock', function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
                $q->whereRaw('qty_available <= qty_min')->where('qty_available', '>', 0);
            });
        } elseif ($status === 'in_stock') {
            $query->whereHas('inventoryStock', function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
                $q->where('qty_available', '>', 0);
            });
        }

        $products = $query->orderBy('name', 'asc')->paginate(20)->withQueryString();

        // Data master untuk opsi filter
        $categories = ProductCategory::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('gudang.stok.index', [
            'products' => $products,
            'categories' => $categories,
            'warehouses' => $warehouses,
            'kpi' => [
                'total_products' => $totalActiveProducts,
                'total_physical_qty' => $totalPhysicalQty,
                'total_free_qty' => $totalFreeQty,
                'total_asset_value' => $totalAssetValue,
                'critical_count' => $criticalCount,
            ],
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'warehouse_id' => $warehouseId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Tampilan Kartu Persediaan (Stock Card) individual per produk.
     */
    public function stockCard(Product $product, Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon = Carbon::parse($endDate)->endOfDay();

        $cardData = $this->buildSingleProductStockCard($product, $startCarbon, $endCarbon, $warehouseId);
        $warehouses = Warehouse::orderBy('name')->get();

        return view('gudang.stok.kartu', [
            'product' => $product->load(['productCategory', 'uom', 'cabang']),
            'warehouses' => $warehouses,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'warehouse_id' => $warehouseId,
            ],
            'openingQty' => $cardData['opening_qty'],
            'movements' => $cardData['movements'],
            'totalIn' => $cardData['total_in'],
            'totalOut' => $cardData['total_out'],
            'closingQty' => $cardData['closing_qty'],
        ]);
    }

    /**
     * Cetak PDF Kartu Persediaan Produk Tunggal (A4 Landscape).
     */
    public function stockCardPdf(Product $product, Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $startCarbon = Carbon::parse($startDate)->startOfDay();
        $endCarbon = Carbon::parse($endDate)->endOfDay();

        $cardData = $this->buildSingleProductStockCard($product, $startCarbon, $endCarbon, $warehouseId);
        $warehouse = $warehouseId ? Warehouse::find($warehouseId) : null;

        $pdf = Pdf::loadView('pdf.gudang.kartu-stok', [
            'product' => $product->load(['productCategory', 'uom', 'cabang']),
            'warehouse' => $warehouse,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'warehouse_id' => $warehouseId,
            ],
            'openingQty' => $cardData['opening_qty'],
            'movements' => $cardData['movements'],
            'totalIn' => $cardData['total_in'],
            'totalOut' => $cardData['total_out'],
            'closingQty' => $cardData['closing_qty'],
        ])->setPaper('a4', 'landscape');

        $safeSku = preg_replace('/[^A-Za-z0-9_-]/', '_', $product->sku ?: 'PROD');
        $filename = "Kartu_Stok_{$safeSku}_{$startDate}_{$endDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Unduh Excel Kartu Persediaan Produk.
     */
    public function stockCardExcel(Product $product, Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $export = new InventoryCardExport(
            startDate: $startDate,
            endDate: $endDate,
            productId: $product->id,
            warehouseId: $warehouseId,
        );

        $safeSku = preg_replace('/[^A-Za-z0-9_-]/', '_', $product->sku ?: 'PROD');
        $filename = "Kartu_Stok_{$safeSku}_{$startDate}_{$endDate}.xlsx";

        return Excel::download($export, $filename);
    }

    /**
     * Tampilan Laporan Kartu Persediaan Konsolidasi (Semua Produk).
     */
    public function inventoryCardReport(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        $reportData = app(InventoryCardReportService::class)->reportData([
            'start' => $startDate,
            'end' => $endDate,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
        ]);

        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::orderBy('name')->get();

        return view('gudang.stok.inventory-card', [
            'reportData' => $reportData,
            'warehouses' => $warehouses,
            'products' => $products,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ],
        ]);
    }

    /**
     * Cetak PDF Laporan Kartu Persediaan Konsolidasi.
     */
    public function inventoryCardReportPdf(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        $data = app(InventoryCardReportService::class)->reportData([
            'start' => $startDate,
            'end' => $endDate,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
        ]);
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('reports.inventory-card-print', compact('data'))
            ->setPaper('a4', 'landscape');

        $filename = "Laporan_Kartu_Persediaan_{$startDate}_{$endDate}.pdf";

        return $pdf->stream($filename);
    }

    /**
     * Kalkulasi Saldo Awal, Mutasi Kronologis, dan Running Balance untuk 1 Produk.
     */
    protected function buildSingleProductStockCard(Product $product, Carbon $start, Carbon $end, ?int $warehouseId): array
    {
        $inTypes = StockMovement::IN_TYPES;
        $outTypes = StockMovement::OUT_TYPES;

        // 1. Hitung Saldo Awal (Mutasi sebelum tanggal mulai)
        $openingQuery = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('date', '<', $start->toDateTimeString());

        if ($warehouseId) {
            $openingQuery->where('warehouse_id', $warehouseId);
        }

        $openingIn = (clone $openingQuery)->whereIn('type', $inTypes)->sum('quantity');
        $openingOut = (clone $openingQuery)->whereIn('type', $outTypes)->sum('quantity');
        $openingQty = (float) $openingIn - (float) $openingOut;

        // 2. Ambil Mutasi dalam Periode (diurutkan kronologis ASC)
        $movementQuery = StockMovement::query()
            ->with(['warehouse', 'rak', 'fromModel'])
            ->where('product_id', $product->id)
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()]);

        if ($warehouseId) {
            $movementQuery->where('warehouse_id', $warehouseId);
        }

        $rawMovements = $movementQuery->orderBy('date', 'asc')->orderBy('id', 'asc')->get();

        // 3. Hitung Running Balance per mutasi
        $runningBalance = $openingQty;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $calculatedMovements = [];

        foreach ($rawMovements as $m) {
            $isPositive = in_array($m->type, $inTypes);
            $qty = (float) $m->quantity;

            if ($isPositive) {
                $qtyIn = $qty;
                $qtyOut = 0.0;
                $runningBalance += $qtyIn;
                $totalIn += $qtyIn;
            } else {
                $qtyIn = 0.0;
                $qtyOut = $qty;
                $runningBalance -= $qtyOut;
                $totalOut += $qtyOut;
            }

            // Tentukan label dan link rujukan
            $referenceNumber = $m->source_number !== 'N/A' ? $m->source_number : ($m->reference_id ?: '-');
            $referenceUrl = $this->resolveFriendlyBladeUrl($m);

            $calculatedMovements[] = [
                'id' => $m->id,
                'date' => $m->date,
                'type' => $m->type,
                'type_label' => $this->formatMovementTypeLabel($m->type),
                'reference_number' => $referenceNumber,
                'reference_url' => $referenceUrl,
                'warehouse_name' => $m->warehouse?->name ?? 'Semua Gudang',
                'rak_name' => $m->rak?->name ?? '-',
                'notes' => $m->notes ?: '-',
                'qty_in' => $qtyIn,
                'qty_out' => $qtyOut,
                'running_balance' => $runningBalance,
            ];
        }

        return [
            'opening_qty' => $openingQty,
            'movements' => $calculatedMovements,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'closing_qty' => $runningBalance,
        ];
    }

    /**
     * Resolusi URL Blade transaksi yang ramah pengguna.
     */
    protected function resolveFriendlyBladeUrl(StockMovement $movement): ?string
    {
        $source = $movement->fromModel;
        if (! $source) {
            return null;
        }

        try {
            return match ($movement->from_model_type) {
                \App\Models\PurchaseReceipt::class => route('gudang.penerimaan.show', $source->id),
                \App\Models\PurchaseReceiptItem::class => $source->purchase_receipt_id
                    ? route('gudang.penerimaan.show', $source->purchase_receipt_id)
                    : null,
                \App\Models\PurchaseOrder::class => route('pembelian.po.show', $source->id),
                \App\Models\PurchaseReturn::class => route('pembelian.returns.show', $source->id),
                \App\Models\CustomerReturn::class => route('penjualan.returns.show', $source->id),
                \App\Models\SaleOrder::class => route('penjualan.so.show', $source->id),
                default => null,
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Format label tipe mutasi stok dalam Bahasa Indonesia.
     */
    protected function formatMovementTypeLabel(string $type): string
    {
        return match ($type) {
            'purchase_in' => 'Penerimaan Pembelian',
            'sales' => 'Pengiriman Penjualan',
            'customer_return', 'sales_return_in' => 'Retur Penjualan (Masuk)',
            'purchase_return', 'purchase_return_out' => 'Retur Pembelian (Keluar)',
            'transfer_in' => 'Transfer Masuk',
            'transfer_out' => 'Transfer Keluar',
            'manufacture_in' => 'Hasil Produksi',
            'manufacture_out' => 'Bahan Baku Produksi',
            'adjustment_in' => 'Penyesuaian Masuk',
            'adjustment_out' => 'Penyesuaian Keluar',
            'beginning' => 'Saldo Awal',
            'do_return' => 'Retur Surat Jalan',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}
