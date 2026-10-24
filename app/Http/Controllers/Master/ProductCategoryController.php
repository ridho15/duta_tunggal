<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductCategoryRequest;
use App\Http\Requests\Master\UpdateProductCategoryRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ProductCategoryController extends Controller
{
    /**
     * Otorisasi hak akses Master Kategori Produk (Role: OWN, PUR, SM, SLS, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'PUR', 'SM', 'SLS', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Kategori Produk.');
    }

    /**
     * Tampilkan daftar kategori produk.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any product category');

        $search = trim((string) $request->query('search', ''));

        $query = ProductCategory::query()->withCount('product');

        // KPI Counts
        $kpi = [
            'total_kategori' => (clone $query)->count(),
            'kategori_aktif' => (clone $query)->has('product')->count(),
            'total_produk' => Product::count(),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            });
        }

        $categories = $query->latest()->paginate(15)->withQueryString();

        return view('master.kategori.index', compact('categories', 'kpi', 'search'));
    }

    /**
     * Form tambah kategori baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create product category');

        return view('master.kategori.create');
    }

    /**
     * Simpan kategori baru.
     */
    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create product category');

        try {
            $data = $request->validated();
            $category = ProductCategory::create($data);

            return redirect()->route('master.kategori.index')
                ->with('success', "Kategori '{$category->name}' ({$category->kode}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan kategori produk: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan kategori: ' . $e->getMessage());
        }
    }

    /**
     * Form edit kategori.
     */
    public function edit(ProductCategory $kategori): View
    {
        $this->authorizeAccess('update product category');

        return view('master.kategori.edit', compact('kategori'));
    }

    /**
     * Perbarui data kategori.
     */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $kategori): RedirectResponse
    {
        $this->authorizeAccess('update product category');

        try {
            $data = $request->validated();
            $kategori->update($data);

            return redirect()->route('master.kategori.index')
                ->with('success', "Data kategori '{$kategori->name}' ({$kategori->kode}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui kategori produk: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui kategori: ' . $e->getMessage());
        }
    }

    /**
     * Hapus kategori dengan proteksi integritas produk.
     */
    public function destroy(ProductCategory $kategori): RedirectResponse
    {
        $this->authorizeAccess('delete product category');

        try {
            $blockers = $kategori->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.kategori.index')
                    ->with('error', "Kategori '{$kategori->name}' ({$kategori->kode}) tidak dapat dihapus karena {$reason}.");
            }

            $nama = $kategori->name;
            $kode = $kategori->kode;
            $kategori->delete();

            return redirect()->route('master.kategori.index')
                ->with('success', "Kategori '{$nama}' ({$kode}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus kategori: ' . $e->getMessage());

            return redirect()->route('master.kategori.index')
                ->with('error', 'Kategori tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
