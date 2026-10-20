<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemCheck;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Support\HpWarehouse;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KirimanController extends Controller
{
    /**
     * Menampilkan daftar kiriman checker gudang (Mockup F1_Kiriman-HP.html).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $warehouses = HpWarehouse::options($user);
        $currentWarehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : HpWarehouse::currentId($user);

        if ($currentWarehouseId) {
            HpWarehouse::select($user, $currentWarehouseId);
        } else if ($warehouses->isNotEmpty()) {
            $currentWarehouseId = $warehouses->first()->id;
            HpWarehouse::select($user, $currentWarehouseId);
        }

        $activeWarehouse = Warehouse::find($currentWarehouseId);
        $tab = $request->query('tab', 'siap_dicek');
        $search = $request->query('q');

        // Query dasar DO untuk gudang ini
        $baseQuery = DeliveryOrder::with([
            'salesOrders.customer',
            'warehouse',
            'deliveryOrderItem.product',
            'suratJalan',
        ])
        ->where('warehouse_id', $currentWarehouseId)
        ->whereNull('returned_at');

        // Hitung counter tab
        $counts = [
            'siap_dicek' => (clone $baseQuery)->whereIn('status', ['approved', 'confirmed'])->whereDoesntHave('suratJalan')->count(),
            'menunggu_sj' => (clone $baseQuery)->where('status', 'confirmed')->whereDoesntHave('suratJalan')->count(),
            'terkirim' => (clone $baseQuery)->whereHas('suratJalan')->whereDate('updated_at', today())->count(),
        ];

        $query = clone $baseQuery;

        if ($tab === 'menunggu_sj') {
            $query->where('status', 'confirmed')->whereDoesntHave('suratJalan');
        } elseif ($tab === 'terkirim') {
            $query->whereHas('suratJalan');
        } else {
            // siap_dicek (default)
            $query->whereIn('status', ['approved', 'confirmed'])->whereDoesntHave('suratJalan');
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('do_number', 'like', "%{$search}%")
                  ->orWhereHas('salesOrders.customer', function ($cQ) use ($search) {
                      $cQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $deliveryOrders = $query->orderBy('id', 'desc')->get();

        return view('gudang.kiriman.index', compact(
            'deliveryOrders',
            'counts',
            'tab',
            'search',
            'warehouses',
            'currentWarehouseId',
            'activeWarehouse'
        ));
    }

    /**
     * Layar Checklist Pengecekan Barang DO di HP (Mockup E5_Gudang-HP.html).
     */
    public function check(DeliveryOrder $deliveryOrder): View
    {
        $deliveryOrder->loadMissing([
            'salesOrders.customer',
            'warehouse',
            'deliveryOrderItem.product.uom',
        ]);

        return view('gudang.kiriman.check', compact('deliveryOrder'));
    }

    /**
     * Konfirmasi hasil cek barang (Semua sesuai – KIRIM).
     */
    public function submitCheck(DeliveryOrder $deliveryOrder, Request $request): RedirectResponse
    {
        $checkedItemIds = $request->input('checked_items', []);

        DB::transaction(function () use ($deliveryOrder, $checkedItemIds) {
            foreach ($deliveryOrder->deliveryOrderItem as $item) {
                if (in_array((string)$item->id, $checkedItemIds, true)) {
                    DeliveryOrderItemCheck::updateOrCreate(
                        ['delivery_order_item_id' => $item->id],
                        ['checked_by' => Auth::id(), 'checked_at' => now()]
                    );
                }
            }

            // Tandai DO sebagai confirmed (siap diterbitkan Surat Jalan)
            $deliveryOrder->update(['status' => 'confirmed']);
        });

        return redirect()->route('gudang.kiriman.index')
            ->with('success', "Pengecekan {$deliveryOrder->do_number} selesai. Barang siap dikirim.");
    }

    /**
     * Layar Catat Barang Kurang / Rusak (Mockup F2_ReturDO-HP.html).
     */
    public function retur(DeliveryOrder $deliveryOrder): View
    {
        $deliveryOrder->loadMissing([
            'salesOrders.customer',
            'warehouse',
            'deliveryOrderItem.product.uom',
        ]);

        return view('gudang.kiriman.retur', compact('deliveryOrder'));
    }

    /**
     * Simpan & Ajukan Retur DO (Alur Ko Handy §5: DO asal -> DO.000xR & DO.000xC).
     */
    public function submitRetur(DeliveryOrder $deliveryOrder, Request $request): RedirectResponse
    {
        $itemsData = $request->input('items', []);

        DB::transaction(function () use ($deliveryOrder, $itemsData) {
            $now = now();
            $originalDoNumber = $deliveryOrder->do_number;

            // 1. Tandai DO Asal sebagai diretur
            $deliveryOrder->update([
                'returned_at' => $now,
                'status' => 'returned',
            ]);

            // 2. Buat DO Pengganti (...C) untuk barang yang sesuai dan siap dijadikan Surat Jalan
            $replacementDo = DeliveryOrder::create([
                'do_number' => "{$originalDoNumber}C",
                'status' => 'approved',
                'warehouse_id' => $deliveryOrder->warehouse_id,
                'cabang_id' => $deliveryOrder->cabang_id,
                'created_by' => Auth::id(),
                'original_delivery_order_id' => $deliveryOrder->id,
            ]);

            // Hubungkan relasi SO ke DO pengganti
            $replacementDo->salesOrders()->attach($deliveryOrder->salesOrders->pluck('id'));

            // Buat baris barang pengganti
            foreach ($deliveryOrder->deliveryOrderItem as $item) {
                $kurang = (int) ($itemsData[$item->id]['kurang'] ?? 0);
                $reject = (int) ($itemsData[$item->id]['reject'] ?? 0);
                $acceptedQty = max(0, (int)$item->quantity - $kurang - $reject);

                if ($acceptedQty > 0) {
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $replacementDo->id,
                        'product_id' => $item->product_id,
                        'quantity' => $acceptedQty,
                        'sale_order_item_id' => $item->sale_order_item_id,
                    ]);
                }
            }
        });

        return redirect()->route('gudang.kiriman.index')
            ->with('success', "Retur berhasil dicatat. DO pengganti {$deliveryOrder->do_number}C dibuat untuk pengiriman Surat Jalan.");
    }

    /**
     * Cetak A4 Picking List Gudang (Mockup F3_PickingList.html).
     */
    public function pickingList(Request $request): View
    {
        $warehouseId = $request->query('warehouse_id') ?? HpWarehouse::currentId(Auth::user());
        $warehouse = Warehouse::findOrFail($warehouseId);

        // Ambil DO yang siap dicek di gudang ini
        $deliveryOrders = DeliveryOrder::with([
            'salesOrders.customer',
            'deliveryOrderItem.product.uom',
        ])
        ->where('warehouse_id', $warehouse->id)
        ->whereIn('status', ['approved', 'confirmed'])
        ->whereNull('returned_at')
        ->orderBy('id', 'desc')
        ->get();

        // Agregasikan item urut produk
        $aggregatedItems = [];
        foreach ($deliveryOrders as $do) {
            foreach ($do->deliveryOrderItem as $item) {
                $pId = $item->product_id;
                if (!isset($aggregatedItems[$pId])) {
                    $aggregatedItems[$pId] = [
                        'product' => $item->product,
                        'sku' => $item->product->sku ?? '-',
                        'name' => $item->product->name ?? '-',
                        'unit' => $item->product->uom->abbreviation ?? 'pcs',
                        'total_quantity' => 0,
                        'rak' => 'A-01', // Lokasi rak default
                        'for_dos' => [],
                    ];
                }
                $aggregatedItems[$pId]['total_quantity'] += (float)$item->quantity;
                $aggregatedItems[$pId]['for_dos'][] = "{$do->do_number} (" . (int)$item->quantity . ")";
            }
        }

        return view('gudang.kiriman.picking-list', compact('warehouse', 'deliveryOrders', 'aggregatedItems'));
    }
}
