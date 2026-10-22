<?php

namespace App\Http\Controllers\Manufaktur;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manufaktur\StoreBillOfMaterialRequest;
use App\Http\Requests\Manufaktur\UpdateBillOfMaterialRequest;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\BillOfMaterialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BillOfMaterialController extends Controller
{
    protected BillOfMaterialService $bomService;

    public function __construct(BillOfMaterialService $bomService)
    {
        $this->bomService = $bomService;
    }

    /**
     * Menampilkan daftar Resep Produksi (Bill of Materials).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'semua');
        $search = $request->query('q');
        $cabangId = $request->query('cabang_id');

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $baseQuery = BillOfMaterial::with(['product.uom', 'cabang', 'uom', 'items.product']);

        if (!$canManageAll && $user?->cabang_id) {
            $baseQuery->where('cabang_id', $user->cabang_id);
        } elseif ($cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Hitung KPIs
        $totalBoms = (clone $baseQuery)->count();
        $activeBoms = (clone $baseQuery)->where('is_active', true)->count();
        $inactiveBoms = (clone $baseQuery)->where('is_active', false)->count();
        $avgTotalCost = (clone $baseQuery)->where('is_active', true)->avg('total_cost') ?? 0;

        // Filter tab
        $query = clone $baseQuery;
        if ($tab === 'aktif') {
            $query->where('is_active', true);
        } elseif ($tab === 'nonaktif') {
            $query->where('is_active', false);
        }

        // Filter pencarian
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('nama_bom', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $boms = $query->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $cabangs = Cabang::orderBy('nama')->get();

        return view('manufaktur.bom.index', compact(
            'boms',
            'tab',
            'search',
            'cabangId',
            'cabangs',
            'totalBoms',
            'activeBoms',
            'inactiveBoms',
            'avgTotalCost'
        ));
    }

    /**
     * Form tambah Resep Produksi baru.
     */
    public function create(): View
    {
        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        // Produk jadi yang boleh dirakit/diproduksi
        $manufacturedProducts = Product::where('is_manufacture', true)
            ->orderBy('name')
            ->get();

        // Bahan baku
        $rawMaterials = Product::where('is_raw_material', true)
            ->with('uom')
            ->orderBy('name')
            ->get();

        $uoms = UnitOfMeasure::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $defaultCode = $this->bomService->generateCode();

        return view('manufaktur.bom.create', compact(
            'manufacturedProducts',
            'rawMaterials',
            'uoms',
            'cabangs',
            'defaultCode',
            'canManageAll'
        ));
    }

    /**
     * Menyimpan Resep Produksi baru ke database.
     */
    public function store(StoreBillOfMaterialRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        // Pastikan cabang terisi
        $cabangId = $validated['cabang_id'] ?? $user?->cabang_id;
        if (!$cabangId) {
            $firstCabang = Cabang::first();
            $cabangId = $firstCabang?->id;
        }

        DB::beginTransaction();
        try {
            $laborCost = (float) ($validated['labor_cost'] ?? 0);
            $overheadCost = (float) ($validated['overhead_cost'] ?? 0);

            $bom = BillOfMaterial::create([
                'code' => $validated['code'],
                'nama_bom' => $validated['nama_bom'],
                'product_id' => $validated['product_id'],
                'quantity' => (float) $validated['quantity'],
                'uom_id' => $validated['uom_id'],
                'cabang_id' => $cabangId,
                'note' => $validated['note'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'labor_cost' => $laborCost,
                'overhead_cost' => $overheadCost,
                'work_in_progress_coa_id' => $validated['work_in_progress_coa_id'] ?? null,
                'labor_coa_id' => $validated['labor_coa_id'] ?? null,
                'overhead_coa_id' => $validated['overhead_coa_id'] ?? null,
                'total_cost' => 0,
            ]);

            $totalMaterialCost = 0;
            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];
                
                // Ambil harga satuan default dari master produk jika kosong
                $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                    ? (float) $item['unit_price']
                    : (float) (Product::find($item['product_id'])?->cost_price ?? 0);

                $subtotal = $qty * $unitPrice;
                $totalMaterialCost += $subtotal;

                $rawProduct = Product::find($item['product_id']);
                $itemUomId = $item['uom_id'] ?? $rawProduct?->uom_id;

                BillOfMaterialItem::create([
                    'bill_of_material_id' => $bom->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'uom_id' => $itemUomId,
                    'unit_price' => (string) $unitPrice,
                    'subtotal' => (string) $subtotal,
                    'note' => $item['note'] ?? null,
                ]);
            }

            // Hitung total biaya BOM
            $bom->total_cost = $totalMaterialCost + $laborCost + $overheadCost;
            $bom->save();

            DB::commit();

            return redirect()
                ->route('manufaktur.bom.show', $bom)
                ->with('success', "Resep BOM {$bom->code} ({$bom->nama_bom}) berhasil disimpan!");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal menyimpan resep BOM: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail Resep Produksi.
     */
    public function show(BillOfMaterial $billOfMaterial): View
    {
        $billOfMaterial->load([
            'product.uom',
            'uom',
            'cabang',
            'items.product.uom',
            'items.uom',
            'productionPlans.manufacturingOrders',
        ]);

        $totalMaterialCost = $billOfMaterial->items->sum(function ($item) {
            return (float) ($item->subtotal ?? ((float) $item->quantity * (float) $item->unit_price));
        });

        $costPerUnit = $billOfMaterial->quantity > 0
            ? (float) $billOfMaterial->total_cost / (float) $billOfMaterial->quantity
            : (float) $billOfMaterial->total_cost;

        $isInUse = $billOfMaterial->isInUse();

        return view('manufaktur.bom.show', compact(
            'billOfMaterial',
            'totalMaterialCost',
            'costPerUnit',
            'isInUse'
        ));
    }

    /**
     * Form edit Resep Produksi.
     */
    public function edit(BillOfMaterial $billOfMaterial): View
    {
        $billOfMaterial->load(['items.product.uom', 'items.uom', 'product']);

        $user = Auth::user();
        $canManageAll = $user && method_exists($user, 'canManageAllCabang') ? $user->canManageAllCabang() : true;

        $manufacturedProducts = Product::where('is_manufacture', true)
            ->orWhere('id', $billOfMaterial->product_id)
            ->orderBy('name')
            ->get();

        $rawMaterials = Product::where('is_raw_material', true)
            ->with('uom')
            ->orderBy('name')
            ->get();

        $uoms = UnitOfMeasure::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('manufaktur.bom.edit', compact(
            'billOfMaterial',
            'manufacturedProducts',
            'rawMaterials',
            'uoms',
            'cabangs',
            'canManageAll'
        ));
    }

    /**
     * Memperbarui data Resep Produksi di database.
     */
    public function update(UpdateBillOfMaterialRequest $request, BillOfMaterial $billOfMaterial): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $cabangId = $validated['cabang_id'] ?? $billOfMaterial->cabang_id ?? $user?->cabang_id;

        DB::beginTransaction();
        try {
            $laborCost = (float) ($validated['labor_cost'] ?? 0);
            $overheadCost = (float) ($validated['overhead_cost'] ?? 0);

            $billOfMaterial->update([
                'code' => $validated['code'],
                'nama_bom' => $validated['nama_bom'],
                'product_id' => $validated['product_id'],
                'quantity' => (float) $validated['quantity'],
                'uom_id' => $validated['uom_id'],
                'cabang_id' => $cabangId,
                'note' => $validated['note'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'labor_cost' => $laborCost,
                'overhead_cost' => $overheadCost,
                'work_in_progress_coa_id' => $validated['work_in_progress_coa_id'] ?? null,
                'labor_coa_id' => $validated['labor_coa_id'] ?? null,
                'overhead_coa_id' => $validated['overhead_coa_id'] ?? null,
            ]);

            // Hapus items lama dan ganti dengan yang baru
            $billOfMaterial->items()->delete();

            $totalMaterialCost = 0;
            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];
                $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                    ? (float) $item['unit_price']
                    : (float) (Product::find($item['product_id'])?->cost_price ?? 0);

                $subtotal = $qty * $unitPrice;
                $totalMaterialCost += $subtotal;

                $rawProduct = Product::find($item['product_id']);
                $itemUomId = $item['uom_id'] ?? $rawProduct?->uom_id;

                BillOfMaterialItem::create([
                    'bill_of_material_id' => $billOfMaterial->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'uom_id' => $itemUomId,
                    'unit_price' => (string) $unitPrice,
                    'subtotal' => (string) $subtotal,
                    'note' => $item['note'] ?? null,
                ]);
            }

            // Hitung ulang total biaya
            $billOfMaterial->total_cost = $totalMaterialCost + $laborCost + $overheadCost;
            $billOfMaterial->save();

            DB::commit();

            return redirect()
                ->route('manufaktur.bom.show', $billOfMaterial)
                ->with('success', "Resep BOM {$billOfMaterial->code} berhasil diperbarui!");
        } catch (Throwable $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Gagal memperbarui resep BOM: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus Resep Produksi (Soft Delete) jika tidak sedang digunakan.
     */
    public function destroy(BillOfMaterial $billOfMaterial): RedirectResponse
    {
        if ($billOfMaterial->isInUse()) {
            return redirect()
                ->back()
                ->with('error', 'Resep BOM tidak dapat dihapus karena masih digunakan dalam rencana produksi / SPK yang aktif!');
        }

        try {
            $code = $billOfMaterial->code;
            $billOfMaterial->delete();

            return redirect()
                ->route('manufaktur.bom.index')
                ->with('success', "Resep BOM {$code} berhasil dihapus.");
        } catch (Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Gagal menghapus resep BOM: ' . $e->getMessage());
        }
    }

    /**
     * Toggle status aktif / nonaktif resep BOM.
     */
    public function toggleActive(BillOfMaterial $billOfMaterial): RedirectResponse
    {
        $billOfMaterial->is_active = !$billOfMaterial->is_active;
        $billOfMaterial->save();

        $statusStr = $billOfMaterial->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->back()
            ->with('success', "Status Resep BOM {$billOfMaterial->code} berhasil {$statusStr}.");
    }

    /**
     * Cetak Lembar Resep Formula BOM (PDF).
     */
    public function pdf(BillOfMaterial $billOfMaterial): Response
    {
        $billOfMaterial->load(['product.uom', 'uom', 'cabang', 'items.product.uom', 'items.uom']);

        $pdf = Pdf::loadView('manufaktur.bom.pdf', compact('billOfMaterial'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream("BOM_{$billOfMaterial->code}.pdf");
    }

    /**
     * API: Generate kode BOM baru.
     */
    public function generateCode(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'code' => $this->bomService->generateCode(),
        ]);
    }

    /**
     * API: Ambil info detail produk untuk form BOM.
     */
    public function productDetails(Product $product): JsonResponse
    {
        $product->load('uom');

        return response()->json([
            'status' => true,
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'uom_id' => $product->uom_id,
            'uom_name' => $product->uom?->name ?? 'Pcs',
            'uom_abbr' => $product->uom?->abbreviation ?? 'pcs',
            'cost_price' => (float) ($product->cost_price ?? 0),
        ]);
    }
}
