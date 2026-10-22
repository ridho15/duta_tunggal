<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\PosOrderService;
use App\Services\PosShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PosController extends Controller
{
    protected PosOrderService $posOrderService;
    protected PosShiftService $posShiftService;

    public function __construct(
        PosOrderService $posOrderService,
        PosShiftService $posShiftService
    ) {
        $this->posOrderService = $posOrderService;
        $this->posShiftService = $posShiftService;
    }

    /**
     * Tampilan utama Terminal Kasir Cepat (Point of Sale).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $cabangId = $user->cabang_id;

        $activeShift = $this->posShiftService->getActiveShift($user, $cabangId);

        $warehouses = Warehouse::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->get();

        $selectedWarehouseId = $activeShift?->warehouse_id ?? $warehouses->first()?->id;

        // Ambil produk populer/katalog awal untuk tampilan cepat kasir
        $initialProducts = Product::withoutGlobalScopes()
            ->active()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id'))
            ->with(['inventoryStocks' => function ($q) use ($selectedWarehouseId) {
                if ($selectedWarehouseId) {
                    $q->where('warehouse_id', $selectedWarehouseId);
                }
            }, 'uom'])
            ->limit(24)
            ->get()
            ->map(function ($product) {
                $stock = (float) $product->inventoryStocks->sum('qty_available');
                $hargaBatas = (float) $product->harga_batas;
                $costPrice = (float) $product->cost_price;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'sell_price' => (float) $product->sell_price,
                    'cost_price' => $costPrice,
                    'harga_batas' => $hargaBatas,
                    'effective_min_price' => $hargaBatas > 0 ? $hargaBatas : $costPrice,
                    'available_stock' => $stock,
                    'uom' => $product->uom?->name ?? 'Pcs',
                ];
            });

        $customers = Customer::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId)->orWhereNull('cabang_id'))
            ->limit(30)
            ->get(['id', 'name', 'phone']);

        return view('kasir.pos.index', compact(
            'activeShift',
            'warehouses',
            'selectedWarehouseId',
            'initialProducts',
            'customers'
        ));
    }

    /**
     * Endpoint API pencarian produk untuk scanner barcode dan autocomplete kasir.
     */
    public function searchProduct(Request $request): JsonResponse
    {
        $keyword = (string) $request->query('keyword', $request->query('q', ''));
        $barcode = (string) $request->query('barcode', '');
        $warehouseId = $request->query('warehouse_id') ? (int) $request->query('warehouse_id') : null;
        $cabangId = Auth::user()->cabang_id;

        if ($barcode !== '') {
            $product = $this->posOrderService->findByBarcode($barcode, $warehouseId, $cabangId);
            return response()->json([
                'found' => (bool) $product,
                'product' => $product,
            ]);
        }

        $results = $this->posOrderService->searchProducts($keyword, $warehouseId, $cabangId);

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Simpan transaksi POS dan jalankan pemotongan stok & penjurnalan otomatis.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'customer_name' => 'nullable|string|max:100',
            'payment_method' => 'required|in:cash,transfer,qris,debit',
            'payment_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:100',
        ]);

        $user = Auth::user();
        $activeShift = $this->posShiftService->getActiveShift($user, $user->cabang_id);

        $order = $this->posOrderService->processOrder($validated, $user, $activeShift);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi POS berhasil diselesaikan!',
                'order' => $order,
                'receipt_url' => route('kasir.pos.receipt', $order),
            ]);
        }

        return redirect()->route('kasir.pos.receipt', $order)
            ->with('success', "Transaksi {$order->order_number} berhasil diselesaikan!");
    }

    /**
     * Tampilan struk belanja termal (58mm / 80mm).
     */
    public function receipt(PosOrder $posOrder): View
    {
        $posOrder->loadMissing([
            'items.product.uom',
            'cashier',
            'warehouse',
            'cabang',
            'posShift',
        ]);

        return view('kasir.pos.receipt', [
            'order' => $posOrder,
        ]);
    }

    /**
     * Riwayat transaksi POS ritel & cetak ulang struk.
     */
    public function history(Request $request): View
    {
        $user = Auth::user();
        $cabangId = $user->cabang_id;
        $search = $request->query('q');
        $shiftId = $request->query('shift_id');
        $date = $request->query('date');

        $query = PosOrder::withoutGlobalScopes()
            ->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))
            ->when($shiftId, fn($q) => $q->where('pos_shift_id', $shiftId))
            ->when($date, fn($q) => $q->whereDate('order_date', $date))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%");
                });
            })
            ->with(['cashier', 'warehouse', 'items'])
            ->latest('order_date');

        $orders = $query->paginate(20)->withQueryString();

        $activeShift = $this->posShiftService->getActiveShift($user, $cabangId);

        return view('kasir.pos.history', compact('orders', 'activeShift', 'search', 'shiftId', 'date'));
    }

    /**
     * Tampilkan detail transaksi POS.
     */
    public function show(PosOrder $posOrder): View
    {
        return $this->receipt($posOrder);
    }
}
