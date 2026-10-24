<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductRequest;
use App\Http\Requests\Master\UpdateProductRequest;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnitConversion;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Services\ProductService;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Otorisasi akses Master Produk (Role: OWN, PUR).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (MenuAccess::isSuper($user) || in_array('PUR', MenuAccess::codesFor($user))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Master Produk.');
    }

    /**
     * Tampilkan daftar Master Produk dengan filter & KPI.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any product');

        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->query('category_id');
        $cabangId = $request->query('cabang_id');
        $status = $request->query('status', 'all');

        $user = Auth::user();
        $userCabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $baseQuery = Product::query()->with(['productCategory', 'uom', 'cabang']);

        if (! $canAccessAll && $userCabangId) {
            $baseQuery->forCabang($userCabangId);
        }

        // KPI Counts
        $kpi = [
            'total_products' => (clone $baseQuery)->count(),
            'active_products' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive_products' => (clone $baseQuery)->where('is_active', false)->count(),
            'total_categories' => ProductCategory::count(),
        ];

        // Apply filters
        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('kode_merk', 'like', "%{$search}%")
                    ->orWhereHas('productCategory', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($categoryId) {
            $query->where('product_category_id', $categoryId);
        }

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $categories = ProductCategory::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('master.produk.index', compact('products', 'categories', 'cabangs', 'kpi', 'search', 'categoryId', 'cabangId', 'status'));
    }

    /**
     * Tampilkan form pembuatan produk baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create product');

        $categories = ProductCategory::orderBy('name')->get();
        $uoms = UnitOfMeasure::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();

        // Ambil pengaturan PPN aktif
        $defaultTax = TaxSetting::where('status', true)
            ->where('effective_date', '<=', now())
            ->where('type', 'PPN')
            ->orderByDesc('effective_date')
            ->value('rate') ?? 11;

        $generatedSku = $this->productService->generateSku();

        // Daftar Akun COA untuk dropdown mapping
        $coas = ChartOfAccount::orderBy('code')->get();

        return view('master.produk.create', compact('categories', 'uoms', 'cabangs', 'defaultTax', 'generatedSku', 'coas'));
    }

    /**
     * Simpan produk baru.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create product');

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $productData = $validated;
            unset($productData['unit_conversions']);

            $productData['is_active'] = $request->boolean('is_active', true);
            $productData['is_manufacture'] = $request->boolean('is_manufacture', false);
            $productData['is_raw_material'] = $request->boolean('is_raw_material', false);
            $productData['biaya'] = $request->input('biaya', 0);
            $productData['harga_batas'] = $request->input('harga_batas', 0);
            $productData['item_value'] = $request->input('item_value', 0);
            $productData['jumlah_kelipatan_gudang_besar'] = $request->input('jumlah_kelipatan_gudang_besar', 0);
            $productData['jumlah_jual_kategori_banyak'] = $request->input('jumlah_jual_kategori_banyak', 0);

            // Simpan Produk
            $product = Product::create($productData);

            // Simpan Konversi Satuan jika ada
            if (! empty($validated['unit_conversions']) && is_array($validated['unit_conversions'])) {
                foreach ($validated['unit_conversions'] as $conv) {
                    if (! empty($conv['uom_id']) && ! empty($conv['nilai_konversi'])) {
                        ProductUnitConversion::create([
                            'product_id' => $product->id,
                            'uom_id' => $conv['uom_id'],
                            'nilai_konversi' => $conv['nilai_konversi'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('master.products.index')
                ->with('success', "Produk '{$product->name}' (SKU: {$product->sku}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal menambahkan produk master: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan produk: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail produk lengkap.
     */
    public function show(Product $product): View
    {
        $this->authorizeAccess('view product');

        $product->load([
            'productCategory',
            'uom',
            'cabang',
            'unitConversions.uom',
            'suppliers',
            'inventoryStock.warehouse',
            'inventoryCoa',
            'salesCoa',
            'salesReturnCoa',
            'cogsCoa',
        ]);

        $totalStock = $product->inventoryStock->sum('qty_available');

        return view('master.produk.show', compact('product', 'totalStock'));
    }

    /**
     * Tampilkan form edit produk.
     */
    public function edit(Product $product): View
    {
        $this->authorizeAccess('update product');

        $product->load('unitConversions.uom');
        $categories = ProductCategory::orderBy('name')->get();
        $uoms = UnitOfMeasure::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $coas = ChartOfAccount::orderBy('code')->get();

        return view('master.produk.edit', compact('product', 'categories', 'uoms', 'cabangs', 'coas'));
    }

    /**
     * Update data produk.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeAccess('update product');

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $productData = $validated;
            unset($productData['unit_conversions']);

            $productData['is_active'] = $request->boolean('is_active', true);
            $productData['is_manufacture'] = $request->boolean('is_manufacture', false);
            $productData['is_raw_material'] = $request->boolean('is_raw_material', false);
            $productData['biaya'] = $request->input('biaya', 0);
            $productData['harga_batas'] = $request->input('harga_batas', 0);
            $productData['item_value'] = $request->input('item_value', 0);
            $productData['jumlah_kelipatan_gudang_besar'] = $request->input('jumlah_kelipatan_gudang_besar', 0);
            $productData['jumlah_jual_kategori_banyak'] = $request->input('jumlah_jual_kategori_banyak', 0);

            $product->update($productData);

            // Sinkronisasi Konversi Satuan
            $incomingConversions = $validated['unit_conversions'] ?? [];
            $existingIds = $product->unitConversions()->pluck('id')->toArray();
            $keptIds = [];

            foreach ($incomingConversions as $conv) {
                if (empty($conv['uom_id']) || empty($conv['nilai_konversi'])) {
                    continue;
                }

                if (! empty($conv['id']) && in_array($conv['id'], $existingIds)) {
                    ProductUnitConversion::where('id', $conv['id'])->update([
                        'uom_id' => $conv['uom_id'],
                        'nilai_konversi' => $conv['nilai_konversi'],
                    ]);
                    $keptIds[] = $conv['id'];
                } else {
                    $newConv = ProductUnitConversion::create([
                        'product_id' => $product->id,
                        'uom_id' => $conv['uom_id'],
                        'nilai_konversi' => $conv['nilai_konversi'],
                    ]);
                    $keptIds[] = $newConv->id;
                }
            }

            // Hapus yang tidak ada di form lagi
            $toDelete = array_diff($existingIds, $keptIds);
            if (! empty($toDelete)) {
                ProductUnitConversion::whereIn('id', $toDelete)->delete();
            }

            DB::commit();

            return redirect()->route('master.products.index')
                ->with('success', "Produk '{$product->name}' (SKU: {$product->sku}) berhasil diperbarui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui produk master: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui produk: ' . $e->getMessage());
        }
    }

    /**
     * Hapus produk (Soft Delete).
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorizeAccess('delete product');

        $blockers = $product->getDeletionBlockers();
        if ($blockers !== []) {
            $reason = implode(', ', $blockers);

            return back()->with('error', "Produk '{$product->name}' (SKU: {$product->sku}) tidak dapat dihapus karena {$reason}.");
        }

        try {
            $name = $product->name;
            $sku = $product->sku;
            $product->delete();

            return redirect()->route('master.products.index')
                ->with('success', "Produk '{$name}' (SKU: {$sku}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus produk: ' . $e->getMessage());

            return back()->with('error', 'Produk tidak dapat dihapus karena masih terkait data transaksi: ' . $e->getMessage());
        }
    }

    /**
     * Quick toggle status aktif / non-aktif via tombol / AJAX switch.
     */
    public function toggleActive(Request $request, Product $product)
    {
        $this->authorizeAccess('update product');

        $product->is_active = ! $product->is_active;
        $product->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $product->is_active,
                'message' => "Status produk '{$product->name}' berhasil diubah menjadi " . ($product->is_active ? 'Aktif' : 'Non-Aktif') . '.',
            ]);
        }

        return back()->with('success', "Status produk '{$product->name}' berhasil diubah.");
    }

    /**
     * Endpoint AJAX: Generate SKU baru yang dijamin unik.
     */
    public function generateSku(): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'sku' => $this->productService->generateSku(),
        ]);
    }
}
