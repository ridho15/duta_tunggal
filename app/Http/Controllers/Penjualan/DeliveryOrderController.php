<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Observers\DeliveryOrderObserver;
use App\Services\DeliveryOrderService;
use App\Services\DeliveryOrderSplitter;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryOrderController extends Controller
{
    protected DeliveryOrderService $deliveryOrderService;
    protected DeliveryOrderSplitter $deliveryOrderSplitter;
    protected SaleOrderDeliveryProgress $deliveryProgressService;

    public function __construct(
        DeliveryOrderService $deliveryOrderService,
        DeliveryOrderSplitter $deliveryOrderSplitter,
        SaleOrderDeliveryProgress $deliveryProgressService
    ) {
        $this->deliveryOrderService = $deliveryOrderService;
        $this->deliveryOrderSplitter = $deliveryOrderSplitter;
        $this->deliveryProgressService = $deliveryProgressService;
    }

    /**
     * Tampilan Form Buat DO Multi-Gudang (Mockup E4_DO-Gudang.html).
     */
    public function create(Request $request): View
    {
        $soId = $request->query('so_id');
        $saleOrder = SaleOrder::with(['customer', 'cabang', 'saleOrderItem.product.uom'])
            ->findOrFail($soId);

        // Ambil data progres pengiriman per item SO
        $progress = $this->deliveryProgressService->forSaleOrder($saleOrder);
        $itemProgress = $progress['items'];

        $pendingItems = [];
        $completedItems = [];

        foreach ($saleOrder->saleOrderItem as $item) {
            $itemId = $item->id;
            $prog = $itemProgress[$itemId] ?? null;
            $available = $prog ? (float) $prog['available'] : (float) $item->quantity;
            $ordered = $prog ? (float) $prog['ordered'] : (float) $item->quantity;

            if ($available <= 0) {
                $completedItems[] = [
                    'item' => $item,
                    'ordered' => $ordered,
                ];
                continue;
            }

            // Cari seluruh gudang di cabang ini (atau seluruh gudang) yang memiliki stok bebas > 0
            $warehouseStocks = InventoryStock::query()
                ->with('warehouse')
                ->where('product_id', $item->product_id)
                ->whereRaw('(qty_available - qty_reserved) > 0')
                ->when($saleOrder->cabang_id, function ($q) use ($saleOrder) {
                    $q->whereHas('warehouse', fn ($w) => $w->where('cabang_id', $saleOrder->cabang_id));
                })
                ->get()
                ->map(function ($stock) {
                    return [
                        'warehouse_id' => $stock->warehouse_id,
                        'warehouse_name' => $stock->warehouse?->name ?? 'Gudang #' . $stock->warehouse_id,
                        'warehouse_code' => $stock->warehouse?->code ?? '-',
                        'free_stock' => max(0, (float) ($stock->qty_available - $stock->qty_reserved)),
                    ];
                });

            // Fallback jika tidak ada di cabang sama, tampilkan gudang mana pun yang berstok
            if ($warehouseStocks->isEmpty()) {
                $warehouseStocks = InventoryStock::query()
                    ->with('warehouse')
                    ->where('product_id', $item->product_id)
                    ->whereRaw('(qty_available - qty_reserved) > 0')
                    ->get()
                    ->map(function ($stock) {
                        return [
                            'warehouse_id' => $stock->warehouse_id,
                            'warehouse_name' => $stock->warehouse?->name ?? 'Gudang #' . $stock->warehouse_id,
                            'warehouse_code' => $stock->warehouse?->code ?? '-',
                            'free_stock' => max(0, (float) ($stock->qty_available - $stock->qty_reserved)),
                        ];
                    });
            }

            $pendingItems[] = [
                'item' => $item,
                'available' => $available,
                'ordered' => $ordered,
                'warehouses' => $warehouseStocks,
            ];
        }

        return view('penjualan.do.create', [
            'saleOrder' => $saleOrder,
            'pendingItems' => $pendingItems,
            'completedItems' => $completedItems,
        ]);
    }

    /**
     * Simpan DO Multi-Gudang & Jalankan Pemisahan Otomatis (Splitter).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'sale_order_id' => 'required|exists:sale_orders,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:sale_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.warehouses' => 'required|array',
        ]);

        $saleOrder = SaleOrder::findOrFail($request->input('sale_order_id'));

        // Hitung total kuantitas yang dialokasikan ke gudang
        $totalAllocatedQty = 0;
        $itemsPayload = $request->input('items');

        foreach ($itemsPayload as $itemData) {
            foreach ($itemData['warehouses'] as $whId => $qty) {
                $totalAllocatedQty += (float) $qty;
            }
        }

        if ($totalAllocatedQty <= 0) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Kuantitas kirim harus lebih besar dari 0 pada minimal satu gudang.');
        }

        // Tunda pemotongan stok otomatis di observer selama pembuatan & splitting
        DeliveryOrderObserver::$deferStockDeduction = true;

        try {
            $createdOrders = DB::transaction(function () use ($saleOrder, $itemsPayload) {
                // Temukan gudang pertama yang memiliki alokasi
                $firstWarehouseId = null;
                foreach ($itemsPayload as $itemData) {
                    foreach ($itemData['warehouses'] as $whId => $qty) {
                        if ((float) $qty > 0) {
                            $firstWarehouseId = (int) $whId;
                            break 2;
                        }
                    }
                }

                $doNumber = $this->deliveryOrderService->generateDoNumber($firstWarehouseId);

                $deliveryOrder = DeliveryOrder::create([
                    'do_number' => $doNumber,
                    'delivery_date' => now(),
                    'warehouse_id' => $firstWarehouseId,
                    'cabang_id' => $saleOrder->cabang_id,
                    'status' => 'draft',
                    'created_by' => Auth::id(),
                ]);

                // Hubungkan relasi pivot SO
                $deliveryOrder->salesOrders()->sync([$saleOrder->id]);

                // Simpan items dan sumber gudang
                foreach ($itemsPayload as $itemData) {
                    $totalItemQty = 0;
                    foreach ($itemData['warehouses'] as $qty) {
                        $totalItemQty += (float) $qty;
                    }

                    if ($totalItemQty <= 0) {
                        continue;
                    }

                    $doi = DeliveryOrderItem::create([
                        'delivery_order_id' => $deliveryOrder->id,
                        'sale_order_item_id' => $itemData['item_id'],
                        'product_id' => $itemData['product_id'],
                        'quantity' => $totalItemQty,
                    ]);

                    foreach ($itemData['warehouses'] as $whId => $qty) {
                        $qtyFloat = (float) $qty;
                        if ($qtyFloat > 0) {
                            DeliveryOrderItemWarehouseSource::create([
                                'delivery_order_item_id' => $doi->id,
                                'warehouse_id' => (int) $whId,
                                'quantity' => $qtyFloat,
                            ]);
                        }
                    }
                }

                // Bersihkan baris sumber gudang bernilai kosong jika ada
                DeliveryOrderItemWarehouseSource::query()
                    ->whereIn('delivery_order_item_id', $deliveryOrder->deliveryOrderItem()->pluck('id'))
                    ->where(fn ($q) => $q->whereNull('quantity')->orWhere('quantity', '<=', 0))
                    ->delete();

                // Jalankan pemecahan DO otomatis per gudang
                $orders = $this->deliveryOrderSplitter->splitByWarehouse($deliveryOrder);

                // Finalisasi tiap DO (potong stok, buat Warehouse Confirmation, dan majukan status)
                foreach ($orders as $order) {
                    app(DeliveryOrderObserver::class)->createStockMovementsForDeliveryOrder($order, force: true);
                    $this->deliveryOrderService->createWarehouseConfirmationsForDeliveryOrder($order);
                    $this->deliveryOrderService->updateStatus(
                        deliveryOrder: $order,
                        status: 'request_stock',
                        comments: 'DO otomatis dibuat per gudang dari SO ' . $saleOrder->so_number
                    );
                }

                return $orders;
            });
        } finally {
            DeliveryOrderObserver::$deferStockDeduction = false;
        }

        $orderNumbers = collect($createdOrders)->pluck('do_number')->implode(', ');
        $msg = count($createdOrders) > 1
            ? "Pengiriman berhasil dipisah ke " . count($createdOrders) . " gudang berbeda: {$orderNumbers}."
            : "Perintah Kirim {$orderNumbers} berhasil diterbitkan.";

        return redirect()->route('penjualan.so.show', $saleOrder->id)->with('success', $msg);
    }
}
