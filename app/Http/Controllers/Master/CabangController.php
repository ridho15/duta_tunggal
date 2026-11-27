<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCabangRequest;
use App\Http\Requests\Master\UpdateCabangRequest;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CabangController extends Controller
{
    /**
     * Otorisasi akses Master Cabang (Role: OWN, FIN, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (MenuAccess::isSuper($user) || in_array('OWN', MenuAccess::codesFor($user)) || in_array('IT', MenuAccess::codesFor($user))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Master Cabang.');
    }

    /**
     * Tampilkan daftar cabang dengan filter & KPI.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any cabang');

        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        $baseQuery = Cabang::query()->withCount('warehouse');

        // KPI Counts
        $kpi = [
            'total_cabang' => (clone $baseQuery)->count(),
            'active_cabang' => (clone $baseQuery)->where('status', true)->count(),
            'inactive_cabang' => (clone $baseQuery)->where('status', false)->count(),
            'total_warehouses' => Warehouse::count(),
        ];

        // Filter
        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('kode', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all' && $status !== null) {
            $query->where('status', $status === '1');
        }

        $cabangs = $query->orderBy('kode', 'asc')->paginate(20)->withQueryString();

        return view('master.cabang.index', compact('cabangs', 'kpi', 'search', 'status'));
    }

    /**
     * Form pembuatan cabang baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create cabang');

        return view('master.cabang.create');
    }

    /**
     * Simpan cabang baru.
     */
    public function store(StoreCabangRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create cabang');

        $validated = $request->validated();
        $validated['status'] = $request->boolean('status', true);
        $validated['kenaikan_harga'] = $validated['kenaikan_harga'] ?? 0;

        try {
            $cabang = Cabang::create($validated);

            return redirect()->route('master.cabang.index')
                ->with('success', "Cabang '{$cabang->nama}' ({$cabang->kode}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal membuat cabang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan cabang: ' . $e->getMessage());
        }
    }

    /**
     * Form edit cabang.
     */
    public function edit(Cabang $cabang): View
    {
        $this->authorizeAccess('update cabang');

        return view('master.cabang.edit', compact('cabang'));
    }

    /**
     * Perbarui data cabang.
     */
    public function update(UpdateCabangRequest $request, Cabang $cabang): RedirectResponse
    {
        $this->authorizeAccess('update cabang');

        $validated = $request->validated();
        $validated['status'] = $request->boolean('status', true);

        try {
            $cabang->update($validated);

            return redirect()->route('master.cabang.index')
                ->with('success', "Cabang '{$cabang->nama}' ({$cabang->kode}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui cabang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui cabang: ' . $e->getMessage());
        }
    }

    /**
     * Hapus cabang dengan proteksi integritas.
     */
    public function destroy(Cabang $cabang): RedirectResponse
    {
        $this->authorizeAccess('delete cabang');

        try {
            $blockers = $cabang->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.cabang.index')
                    ->with('error', "Cabang '{$cabang->nama}' ({$cabang->kode}) tidak dapat dihapus karena {$reason}.");
            }

            $nama = $cabang->nama;
            $kode = $cabang->kode;
            $cabang->delete();

            return redirect()->route('master.cabang.index')
                ->with('success', "Cabang '{$nama}' ({$kode}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus cabang: ' . $e->getMessage());

            return redirect()->route('master.cabang.index')
                ->with('error', 'Cabang tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
