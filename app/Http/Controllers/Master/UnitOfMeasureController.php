<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreUnitOfMeasureRequest;
use App\Http\Requests\Master\UpdateUnitOfMeasureRequest;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class UnitOfMeasureController extends Controller
{
    /**
     * Otorisasi hak akses Master Satuan (Role: OWN, PUR, GDG, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'PUR', 'GDG', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Satuan Produk.');
    }

    /**
     * Tampilkan daftar satuan barang.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any unit of measure');

        $search = trim((string) $request->query('search', ''));

        $query = UnitOfMeasure::query()->withCount(['product', 'productUnitConversion']);

        // KPI Counts
        $kpi = [
            'total_satuan' => (clone $query)->count(),
            'satuan_aktif_produk' => (clone $query)->has('product')->count(),
            'total_produk' => Product::count(),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('abbreviation', 'like', "%{$search}%");
            });
        }

        $satuanList = $query->latest()->paginate(15)->withQueryString();

        return view('master.satuan.index', compact('satuanList', 'kpi', 'search'));
    }

    /**
     * Form tambah satuan baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create unit of measure');

        return view('master.satuan.create');
    }

    /**
     * Simpan satuan baru.
     */
    public function store(StoreUnitOfMeasureRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create unit of measure');

        try {
            $data = $request->validated();
            $satuan = UnitOfMeasure::create($data);

            return redirect()->route('master.satuan.index')
                ->with('success', "Satuan '{$satuan->name}' ({$satuan->abbreviation}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan satuan: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan satuan: ' . $e->getMessage());
        }
    }

    /**
     * Form edit satuan.
     */
    public function edit(UnitOfMeasure $satuan): View
    {
        $this->authorizeAccess('update unit of measure');

        return view('master.satuan.edit', compact('satuan'));
    }

    /**
     * Perbarui data satuan.
     */
    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $satuan): RedirectResponse
    {
        $this->authorizeAccess('update unit of measure');

        try {
            $data = $request->validated();
            $satuan->update($data);

            return redirect()->route('master.satuan.index')
                ->with('success', "Data satuan '{$satuan->name}' ({$satuan->abbreviation}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui satuan: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui satuan: ' . $e->getMessage());
        }
    }

    /**
     * Hapus satuan dengan proteksi integritas.
     */
    public function destroy(UnitOfMeasure $satuan): RedirectResponse
    {
        $this->authorizeAccess('delete unit of measure');

        try {
            $blockers = $satuan->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.satuan.index')
                    ->with('error', "Satuan '{$satuan->name}' ({$satuan->abbreviation}) tidak dapat dihapus karena {$reason}.");
            }

            $nama = $satuan->name;
            $singkatan = $satuan->abbreviation;
            $satuan->delete();

            return redirect()->route('master.satuan.index')
                ->with('success', "Satuan '{$nama}' ({$singkatan}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus satuan: ' . $e->getMessage());

            return redirect()->route('master.satuan.index')
                ->with('error', 'Satuan tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
