<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use App\Support\MenuAccess;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    protected StockTransferService $stockTransferService;

    public function __construct(StockTransferService $stockTransferService)
    {
        $this->stockTransferService = $stockTransferService;
    }

    /**
     * Menampilkan daftar Transfer Stok Antar Gudang.
     */
    public function index(Request $request)
    {
        $this->authorizeAccess('view any stock transfer');

        $search = trim($request->input('search', ''));
        $fromWarehouseId = $request->integer('from_warehouse_id') ?: null;
        $toWarehouseId = $request->integer('to_warehouse_id') ?: null;
        $status = $request->input('status', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Query dasar
        $query = StockTransfer::query()
            ->with([
                'fromWarehouse',
                'toWarehouse',
                'stockTransferItem.product.uom',
            ]);

        // Filter pencarian no transfer atau nama item
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                    ->orWhereHas('stockTransferItem.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Filter gudang asal
        if ($fromWarehouseId) {
            $query->where('from_warehouse_id', $fromWarehouseId);
        }

        // Filter gudang tujuan
        if ($toWarehouseId) {
            $query->where('to_warehouse_id', $toWarehouseId);
        }

        // Filter status
        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        // Filter rentang tanggal
        if ($startDate && $endDate) {
            $query->whereBetween('transfer_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        } elseif ($startDate) {
            $query->where('transfer_date', '>=', Carbon::parse($startDate)->startOfDay());
        } elseif ($endDate) {
            $query->where('transfer_date', '<=', Carbon::parse($endDate)->endOfDay());
        }

        // KPI Metrik
        $kpi = [
            'total' => StockTransfer::count(),
            'draft' => StockTransfer::where('status', 'Draft')->count(),
            'request' => StockTransfer::whereIn('status', ['Request', 'Pending'])->count(),
            'approved' => StockTransfer::whereIn('status', ['Approved', 'completed'])->count(),
            'rejected' => StockTransfer::whereIn('status', ['Reject', 'cancelled'])->count(),
        ];

        $transfers = $query->orderByDesc('transfer_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        return view('gudang.transfer.index', compact(
            'transfers',
            'warehouses',
            'kpi',
            'search',
            'fromWarehouseId',
            'toWarehouseId',
            'status',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Form pembuatan Transfer Stok Baru.
     */
    public function create()
    {
        $this->authorizeAccess('create stock transfer');

        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();
        $suggestedNumber = $this->stockTransferService->generateTransferNumber();

        return view('gudang.transfer.create', compact('warehouses', 'suggestedNumber'));
    }

    /**
     * Menyimpan data Transfer Stok Baru.
     */
    public function store(Request $request)
    {
        $this->authorizeAccess('create stock transfer');

        $validated = $request->validate([
            'transfer_number' => 'nullable|string|max:50',
            'transfer_date' => 'required|date',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.from_rak_id' => 'nullable|exists:raks,id',
            'items.*.to_rak_id' => 'nullable|exists:raks,id',
        ], [
            'to_warehouse_id.different' => 'Gudang tujuan harus berbeda dari gudang asal.',
            'items.required' => 'Harap tambahkan minimal satu barang untuk dipindahkan.',
            'items.*.quantity.min' => 'Kuantitas transfer minimal 0.01.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $fromWarehouseId = $validated['from_warehouse_id'];
            $toWarehouseId = $validated['to_warehouse_id'];

            // Validasi ketersediaan stok sumber di gudang asal
            foreach ($validated['items'] as $index => $itemData) {
                $stockQuery = InventoryStock::where('warehouse_id', $fromWarehouseId)
                    ->where('product_id', $itemData['product_id']);

                if (!empty($itemData['from_rak_id'])) {
                    $stockQuery->where('rak_id', $itemData['from_rak_id']);
                }

                $available = (float) ($stockQuery->sum('qty_available') ?? 0);
                if ($available < (float) $itemData['quantity']) {
                    $product = Product::find($itemData['product_id']);
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Stok untuk {$product->name} di gudang asal hanya tersedia {$available}. Diminta: {$itemData['quantity']}.",
                    ]);
                }
            }

            $transferNumber = $validated['transfer_number'] ?: $this->stockTransferService->generateTransferNumber();

            $transfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'transfer_date' => $validated['transfer_date'],
                'status' => 'Draft',
            ]);

            foreach ($validated['items'] as $itemData) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'from_warehouse_id' => $fromWarehouseId,
                    'from_rak_id' => $itemData['from_rak_id'] ?? null,
                    'to_warehouse_id' => $toWarehouseId,
                    'to_rak_id' => $itemData['to_rak_id'] ?? null,
                ]);
            }

            // Jika user memilih tombol "Simpan & Ajukan"
            if ($request->input('action') === 'submit_request') {
                $this->stockTransferService->requestTransfer($transfer);
                return redirect()->route('gudang.transfer.show', $transfer)
                    ->with('success', "Transfer {$transfer->transfer_number} berhasil disimpan dan diajukan untuk persetujuan.");
            }

            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('success', "Draft transfer {$transfer->transfer_number} berhasil dibuat.");
        });
    }

    /**
     * Menampilkan detail berkas Transfer Stok.
     */
    public function show(StockTransfer $transfer)
    {
        $this->authorizeAccess('view stock transfer');

        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItem.product.uom',
            'stockTransferItem.fromRak',
            'stockTransferItem.toRak',
        ]);

        return view('gudang.transfer.show', compact('transfer'));
    }

    /**
     * Form edit berkas Transfer Stok (hanya saat Draft).
     */
    public function edit(StockTransfer $transfer)
    {
        $this->authorizeAccess('update stock transfer');

        if ($transfer->status !== 'Draft') {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', 'Hanya transfer berstatus Draft yang dapat diubah.');
        }

        $transfer->load(['stockTransferItem.product.uom', 'stockTransferItem.fromRak', 'stockTransferItem.toRak']);
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        return view('gudang.transfer.edit', compact('transfer', 'warehouses'));
    }

    /**
     * Memperbarui data Transfer Stok (hanya saat Draft).
     */
    public function update(Request $request, StockTransfer $transfer)
    {
        $this->authorizeAccess('update stock transfer');

        if ($transfer->status !== 'Draft') {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', 'Hanya transfer berstatus Draft yang dapat diperbarui.');
        }

        $validated = $request->validate([
            'transfer_date' => 'required|date',
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.from_rak_id' => 'nullable|exists:raks,id',
            'items.*.to_rak_id' => 'nullable|exists:raks,id',
        ]);

        return DB::transaction(function () use ($validated, $transfer, $request) {
            $fromWarehouseId = $validated['from_warehouse_id'];
            $toWarehouseId = $validated['to_warehouse_id'];

            // Validasi ketersediaan stok sumber di gudang asal
            foreach ($validated['items'] as $index => $itemData) {
                $stockQuery = InventoryStock::where('warehouse_id', $fromWarehouseId)
                    ->where('product_id', $itemData['product_id']);

                if (!empty($itemData['from_rak_id'])) {
                    $stockQuery->where('rak_id', $itemData['from_rak_id']);
                }

                $available = (float) ($stockQuery->sum('qty_available') ?? 0);
                if ($available < (float) $itemData['quantity']) {
                    $product = Product::find($itemData['product_id']);
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Stok untuk {$product->name} di gudang asal hanya tersedia {$available}. Diminta: {$itemData['quantity']}.",
                    ]);
                }
            }

            $transfer->update([
                'transfer_date' => $validated['transfer_date'],
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
            ]);

            // Hapus item lama dan buat ulang
            $transfer->stockTransferItem()->forceDelete();

            foreach ($validated['items'] as $itemData) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'from_warehouse_id' => $fromWarehouseId,
                    'from_rak_id' => $itemData['from_rak_id'] ?? null,
                    'to_warehouse_id' => $toWarehouseId,
                    'to_rak_id' => $itemData['to_rak_id'] ?? null,
                ]);
            }

            if ($request->input('action') === 'submit_request') {
                $this->stockTransferService->requestTransfer($transfer);
                return redirect()->route('gudang.transfer.show', $transfer)
                    ->with('success', "Transfer {$transfer->transfer_number} berhasil diperbarui dan diajukan.");
            }

            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} berhasil diperbarui.");
        });
    }

    /**
     * Menghapus berkas Transfer Stok (hanya saat Draft).
     */
    public function destroy(StockTransfer $transfer)
    {
        $this->authorizeAccess('delete stock transfer');

        if ($transfer->status !== 'Draft' && !MenuAccess::isSuper(Auth::user())) {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', 'Hanya transfer berstatus Draft yang dapat dihapus.');
        }

        $transferNumber = $transfer->transfer_number;
        $transfer->delete();

        return redirect()->route('gudang.transfer.index')
            ->with('success', "Transfer {$transferNumber} berhasil dihapus.");
    }

    /**
     * Mengajukan transfer stok dari Draft ke Request.
     */
    public function requestTransfer(StockTransfer $transfer)
    {
        $this->authorizeAccess('request stock transfer');

        try {
            $this->stockTransferService->requestTransfer($transfer);
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} berhasil diajukan untuk persetujuan supervisor/kepala gudang.");
        } catch (ValidationException $e) {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Menyetujui transfer stok dan memotong stok asal serta menambah stok tujuan.
     */
    public function approve(StockTransfer $transfer)
    {
        $this->authorizeAccess('response stock transfer');

        try {
            $this->stockTransferService->approveStockTransfer($transfer);
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} berhasil disetujui! Mutasi stok fisik telah diproses.");
        } catch (ValidationException $e) {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Menolak transfer stok.
     */
    public function reject(Request $request, StockTransfer $transfer)
    {
        $this->authorizeAccess('response stock transfer');

        try {
            $this->stockTransferService->rejectTransfer($transfer, $request->input('reason'));
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} telah ditolak.");
        } catch (ValidationException $e) {
            return redirect()->route('gudang.transfer.show', $transfer)
                ->with('error', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Cetak Surat Jalan Transfer Stok Antar Gudang (PDF).
     */
    public function pdf(StockTransfer $transfer)
    {
        $this->authorizeAccess('view stock transfer');

        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItem.product.uom',
            'stockTransferItem.fromRak',
            'stockTransferItem.toRak',
        ]);

        $pdf = Pdf::loadView('pdf.gudang.surat-jalan-transfer', compact('transfer'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream("Surat-Jalan-Transfer-{$transfer->transfer_number}.pdf");
    }

    /**
     * Helper AJAX: Mengambil produk yang memiliki stok tersedia di gudang terpilih.
     */
    public function availableProducts(Warehouse $warehouse)
    {
        $this->authorizeAccess('view any stock transfer');

        $stocks = InventoryStock::where('warehouse_id', $warehouse->id)
            ->where('qty_available', '>', 0)
            ->with(['product.uom', 'rak'])
            ->get();

        $data = $stocks->map(function ($stock) {
            return [
                'product_id' => $stock->product_id,
                'name' => $stock->product?->name ?? '-',
                'sku' => $stock->product?->sku ?? '-',
                'barcode' => $stock->product?->barcode ?? '',
                'uom_name' => $stock->product?->uom?->name ?? 'Pcs',
                'rak_id' => $stock->rak_id,
                'rak_name' => $stock->rak?->name ?? '-',
                'qty_available' => (float) $stock->qty_available,
            ];
        });

        return response()->json($data);
    }

    /**
     * Helper AJAX: Mengambil daftar rak pada gudang terpilih.
     */
    public function warehouseRaks(Warehouse $warehouse)
    {
        $this->authorizeAccess('view any stock transfer');

        $raks = Rak::where('warehouse_id', $warehouse->id)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($raks);
    }

    /**
     * Helper otorisasi peran / permission.
     */
    protected function authorizeAccess(string $permission): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (MenuAccess::isSuper($user) || in_array('GDG', MenuAccess::codesFor($user))) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk fitur ini.');
    }
}
