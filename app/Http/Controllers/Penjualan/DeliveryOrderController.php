<?php

namespace App\Http\Controllers\Penjualan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\Warehouse;
use App\Observers\DeliveryOrderObserver;
use App\Services\DeliveryOrderService;
use App\Services\DeliveryOrderSplitter;
use App\Services\SaleOrderDeliveryProgress;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * Menampilkan daftar Perintah Kirim (Delivery Order / DO).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');
        $warehouseId = $request->query('warehouse_id');

        $kpiBase = DeliveryOrder::query();
        if ($cabangId) {
            $kpiBase->where('cabang_id', $cabangId);
        }
        if ($warehouseId) {
            $kpiBase->where('warehouse_id', $warehouseId);
        }

        // Live counter per status tab
        $counts = [
            'semua' => (clone $kpiBase)->count(),
            'menunggu_gudang' => (clone $kpiBase)->whereIn('status', ['draft', 'request_stock', 'request_approve'])->count(),
            'siap_dicek' => (clone $kpiBase)->where('status', 'approved')->whereNull('returned_at')->count(),
            'siap_sj' => (clone $kpiBase)->where('status', 'confirmed')->whereNull('returned_at')->whereDoesntHave('suratJalan', function ($q) {
                $q->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES);
            })->count(),
            'terkirim' => (clone $kpiBase)->where(function ($q) {
                $q->whereIn('status', DeliveryOrder::DELIVERED_STATUSES)
                  ->orWhereHas('suratJalan', function ($sjQ) {
                      $sjQ->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES);
                  });
            })->count(),
            'diretur' => (clone $kpiBase)->where(function ($q) {
                $q->whereNotNull('returned_at')->orWhereIn('status', ['returned', 'closed', 'reject']);
            })->count(),
        ];

        // Query utama dengan eager loading lengkap
        $query = DeliveryOrder::with([
            'cabang',
            'warehouse',
            'salesOrders.customer',
            'deliveryOrderItem.product.uom',
            'suratJalan',
            'createdBy',
        ])->latest('id');

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        // Filter tab
        if ($tab === 'menunggu_gudang') {
            $query->whereIn('status', ['draft', 'request_stock', 'request_approve']);
        } elseif ($tab === 'siap_dicek') {
            $query->where('status', 'approved')->whereNull('returned_at');
        } elseif ($tab === 'siap_sj') {
            $query->where('status', 'confirmed')->whereNull('returned_at')->whereDoesntHave('suratJalan', function ($q) {
                $q->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES);
            });
        } elseif ($tab === 'terkirim') {
            $query->where(function ($q) {
                $q->whereIn('status', DeliveryOrder::DELIVERED_STATUSES)
                  ->orWhereHas('suratJalan', function ($sjQ) {
                      $sjQ->whereIn('surat_jalans.status', SuratJalan::ACTIVE_STATUSES);
                  });
            });
        } elseif ($tab === 'diretur') {
            $query->where(function ($q) {
                $q->whereNotNull('returned_at')->orWhereIn('status', ['returned', 'closed', 'reject']);
            });
        }

        // Pencarian multi-kolom
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('do_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('salesOrders', function ($soQ) use ($search) {
                      $soQ->where('so_number', 'like', "%{$search}%")
                          ->orWhereHas('customer', function ($cQ) use ($search) {
                              $cQ->where('name', 'like', "%{$search}%")
                                 ->orWhere('code', 'like', "%{$search}%");
                          });
                  })
                  ->orWhereHas('warehouse', function ($wQ) use ($search) {
                      $wQ->where('name', 'like', "%{$search}%")
                         ->orWhere('kode', 'like', "%{$search}%");
                  });
            });
        }

        $deliveryOrders = $query->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->get();
        $warehouses = Warehouse::where('status', true)->get();

        return view('penjualan.do.index', compact(
            'deliveryOrders',
            'counts',
            'tab',
            'search',
            'cabangs',
            'warehouses',
            'cabangId',
            'warehouseId'
        ));
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

    /**
     * Menampilkan detail Perintah Kirim (Delivery Order / DO).
     */
    public function show(DeliveryOrder $deliveryOrder): View
    {
        $deliveryOrder->loadMissing([
            'cabang',
            'warehouse',
            'salesOrders.customer',
            'salesOrders.saleOrderItem',
            'deliveryOrderItem.product.uom',
            'deliveryOrderItem.warehouseSources.warehouse',
            'deliveryOrderItem.itemCheck.checker',
            'suratJalan.cabang',
            'suratJalan.deliverySchedules.driver',
            'suratJalan.deliverySchedules.vehicle',
            'replacementFor',
            'replacements',
            'createdBy',
        ]);

        $breakdown = $deliveryOrder->valueBreakdown();
        $canCreateSj = $deliveryOrder->status === 'confirmed'
            && empty($deliveryOrder->returned_at)
            && ! $deliveryOrder->hasIssuedSuratJalan();

        $canCancel = empty($deliveryOrder->returned_at)
            && ! in_array($deliveryOrder->status, ['closed', 'returned', 'sent', 'received', 'completed'], true)
            && ! $deliveryOrder->hasIssuedSuratJalan();

        return view('penjualan.do.show', compact('deliveryOrder', 'breakdown', 'canCreateSj', 'canCancel'));
    }

    /**
     * Cetak dokumen PDF Delivery Order / Perintah Pengeluaran Barang.
     */
    public function pdf(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->loadMissing([
            'cabang',
            'warehouse',
            'salesOrders.customer',
            'deliveryOrderItem.product.uom',
            'deliveryOrderItem.itemCheck.checker',
            'createdBy',
        ]);

        $pdf = Pdf::loadView('pdf.delivery-order', [
            'deliveryOrder' => $deliveryOrder,
            'doc' => app(\App\Services\DocumentPrintBuilder::class)->deliveryOrder($deliveryOrder),
            'so' => $deliveryOrder->salesOrders->first(),
            'customer' => $deliveryOrder->salesOrders->first()?->customer,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Perintah_Kirim_{$deliveryOrder->do_number}.pdf");
    }

    /**
     * Batalkan Perintah Kirim yang belum diterbitkan Surat Jalan dan kembalikan stok.
     */
    public function cancel(DeliveryOrder $deliveryOrder, Request $request): RedirectResponse
    {
        if ($deliveryOrder->hasIssuedSuratJalan()) {
            return redirect()->back()->with('error', "Perintah Kirim {$deliveryOrder->do_number} tidak dapat dibatalkan karena sudah memiliki Surat Jalan aktif.");
        }

        if (in_array($deliveryOrder->status, ['closed', 'returned', 'sent', 'received', 'completed'], true)) {
            return redirect()->back()->with('error', "Perintah Kirim dengan status '{$deliveryOrder->status}' tidak dapat dibatalkan.");
        }

        $reason = $request->input('reason', 'Dibatalkan oleh staf.');

        DB::transaction(function () use ($deliveryOrder, $reason) {
            // 1. Lepas reservasi stok fisik jika ada
            $this->deliveryOrderService->releaseStockReservations($deliveryOrder);

            // 2. Kembalikan pemotongan stok fisik jika ada
            if (class_exists(\App\Services\DeliveryShipments::class)) {
                app(\App\Services\DeliveryShipments::class)->reverse($deliveryOrder, "DO {$deliveryOrder->do_number} dibatalkan: {$reason}");
            }

            // 3. Update status DO ke closed
            $this->deliveryOrderService->updateStatus(
                deliveryOrder: $deliveryOrder,
                status: 'closed',
                comments: $reason,
                action: 'cancelled'
            );

            // 4. Sinkronisasi reservasi level SO jika service tersedia
            if (class_exists(\App\Services\SaleOrderReservationSynchronizer::class)) {
                app(\App\Services\SaleOrderReservationSynchronizer::class)->syncForDeliveryOrder($deliveryOrder, "DO {$deliveryOrder->do_number} dibatalkan");
            }

            // 5. Update progres pengiriman SO
            foreach ($deliveryOrder->salesOrders as $so) {
                $this->deliveryProgressService->forSaleOrder($so);
            }
        });

        return redirect()->route('penjualan.do.index')
            ->with('success', "Perintah Kirim {$deliveryOrder->do_number} berhasil dibatalkan. Alokasi stok persediaan telah dikembalikan.");
    }
}
