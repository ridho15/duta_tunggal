<?php

namespace App\Http\Controllers\Gudang;

use App\Exceptions\DeliveryOrderTransitionException;
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
        $tab = $request->query('tab');
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
            'menunggu_stok' => (clone $baseQuery)->whereIn('status', ['request_stock', 'request_approve'])->count(),
            'siap_dicek' => (clone $baseQuery)->whereIn('status', ['approved', 'confirmed'])->whereDoesntHave('suratJalan')->count(),
            'menunggu_sj' => (clone $baseQuery)->where('status', 'confirmed')->whereDoesntHave('suratJalan')->count(),
            'terkirim' => (clone $baseQuery)->whereHas('suratJalan')->whereDate('updated_at', today())->count(),
        ];

        // Tanpa tab eksplisit: buka antrean yang berisi pekerjaan (konfirmasi stok dulu, karena DO baru mulai dari sana).
        $tab ??= $counts['siap_dicek'] === 0 && $counts['menunggu_stok'] > 0 ? 'menunggu_stok' : 'siap_dicek';

        $query = clone $baseQuery;

        if ($tab === 'menunggu_stok') {
            $query->whereIn('status', ['request_stock', 'request_approve']);
        } elseif ($tab === 'menunggu_sj') {
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

        $deliveryOrders = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

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
    public function check(DeliveryOrder $deliveryOrder): View|RedirectResponse
    {
        if (in_array($deliveryOrder->status, ['request_stock', 'request_approve'], true)) {
            return redirect()->route('gudang.kiriman.index', ['tab' => 'menunggu_stok', 'warehouse_id' => $deliveryOrder->warehouse_id])
                ->with('error', "Konfirmasi stok {$deliveryOrder->do_number} dulu sebelum barangnya dicek.");
        }

        $deliveryOrder->loadMissing([
            'salesOrders.customer',
            'warehouse',
            'deliveryOrderItem.product.uom',
            'deliveryOrderItem.itemCheck',
        ]);

        return view('gudang.kiriman.check', compact('deliveryOrder'));
    }

    /**
     * Konfirmasi hasil cek barang (Semua sesuai – KIRIM).
     */
    public function submitCheck(DeliveryOrder $deliveryOrder, Request $request): RedirectResponse
    {
        if (! in_array($deliveryOrder->status, ['approved', 'confirmed'], true)) {
            return back()->with('error', "{$deliveryOrder->do_number} berstatus \"".DeliveryOrder::statusLabel($deliveryOrder->status).'", belum bisa dicek.');
        }

        $checkedItemIds = $request->input('checked_items', []);
        $totalItems = $deliveryOrder->deliveryOrderItem()->count();

        if (count($checkedItemIds) < $totalItems) {
            return back()->with('error', "Periksa dan centang semua barang terlebih dahulu sebelum mengirim, atau pilih tombol 'Ada Kurang / Rusak' jika ada selisih.");
        }

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
     * Checker Edit Qty: turunkan kuantitas item DO yang kurang di rak sebelum dicek dan dikirim.
     */
    public function editQty(DeliveryOrder $deliveryOrder, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['required', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:500'],
        ], [
            'notes.required' => 'Alasan perubahan qty wajib diisi.',
        ]);

        try {
            $changed = app(\App\Services\DeliveryOrderService::class)
                ->updateCheckerQuantities($deliveryOrder, $data['quantities'], $data['notes']);
        } catch (DeliveryOrderTransitionException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $changed > 0
            ? "Qty {$deliveryOrder->do_number} diperbarui ({$changed} barang). Cek ulang barangnya sebelum mengirim."
            : 'Tidak ada qty yang berubah.');
    }

    /**
     * Layar Catat Barang Kurang / Rusak (Mockup F2_ReturDO-HP.html).
     */
    public function retur(DeliveryOrder $deliveryOrder): View|RedirectResponse
    {
        if ($deliveryOrder->hasIssuedSuratJalan()) {
            return redirect()->route('gudang.kiriman.check', $deliveryOrder->id)
                ->with('error', "{$deliveryOrder->do_number} sudah punya Surat Jalan aktif, jadi tidak bisa diretur dari sini.");
        }

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
        if ($deliveryOrder->hasIssuedSuratJalan()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => "{$deliveryOrder->do_number} sudah punya Surat Jalan aktif, jadi tidak bisa diretur dari sini.",
            ]);
        }

        $itemsData = $request->input('items', []);
        $lines = [];
        foreach ($itemsData as $itemId => $row) {
            $lines[$itemId] = [
                'short' => (int) ($row['kurang'] ?? 0),
                'reject' => (int) ($row['reject'] ?? 0),
            ];
        }

        $service = app(\App\Services\Hp\DeliveryOrderShortageReturn::class);
        $return = $service->create($deliveryOrder, $lines, $request->input('notes'));

        return redirect()->route('gudang.kiriman.index')
            ->with('success', "Retur {$return->return_number} berhasil dicatat. Menunggu persetujuan kepala gudang.");
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
