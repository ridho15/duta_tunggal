<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreWarehouseRequest;
use App\Http\Requests\Master\UpdateWarehouseRequest;
use App\Models\Cabang;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class WarehouseController extends Controller
{
    /**
     * Otorisasi hak akses Master Gudang (Role: OWN, GDG, ADP, PUR, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'GDG', 'ADP', 'PUR', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Master Gudang.');
    }

    /**
     * Tampilkan daftar gudang dengan KPI & filter.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any warehouse');

        $search = trim((string) $request->query('search', ''));
        $cabangId = $request->query('cabang_id', 'all');
        $status = $request->query('status', 'all');
        $tipe = $request->query('tipe', 'all');

        $baseQuery = Warehouse::query()->with(['cabang'])->withCount('rak');

        // Filter Cabang
        if ($cabangId !== 'all' && ! empty($cabangId)) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // KPI Counts
        $kpi = [
            'total_gudang' => (clone $baseQuery)->count(),
            'active_gudang' => (clone $baseQuery)->where('status', true)->count(),
            'total_rak' => Rak::count(),
            'quarantine_gudang' => (clone $baseQuery)->where('is_quarantine', true)->count(),
        ];

        // Filter Status
        if ($status !== 'all' && $status !== '') {
            $baseQuery->where('status', $status === '1' || $status === 'active');
        }

        // Filter Tipe
        if ($tipe !== 'all' && ! empty($tipe)) {
            $baseQuery->where('tipe', $tipe);
        }

        // Filter Search
        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('cabang', fn ($cb) => $cb->where('nama', 'like', "%{$search}%"));
            });
        }

        $warehouses = $baseQuery->latest()->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.gudang.index', compact(
            'warehouses',
            'cabangs',
            'kpi',
            'search',
            'cabangId',
            'status',
            'tipe'
        ));
    }

    /**
     * Form penambahan gudang baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create warehouse');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        // Suggest auto-generated code
        $lastWarehouse = Warehouse::latest('id')->first();
        $nextNumber = $lastWarehouse ? ($lastWarehouse->id + 1) : 1;
        $suggestedCode = 'GDG-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

        return view('master.gudang.create', compact('cabangs', 'suggestedCode'));
    }

    /**
     * Simpan gudang baru ke database.
     */
    public function store(StoreWarehouseRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create warehouse');

        try {
            $data = $request->validated();
            $data['status'] = (bool) ($data['status'] ?? true);
            $data['is_quarantine'] = (bool) ($data['is_quarantine'] ?? false);

            $warehouse = Warehouse::create($data);

            return redirect()->route('master.gudang.index')
                ->with('success', "Gudang '{$warehouse->name}' ({$warehouse->kode}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan gudang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan gudang: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail gudang beserta daftar rak di dalamnya.
     */
    public function show(Warehouse $gudang): View
    {
        $this->authorizeAccess('view any warehouse');

        $gudang->load(['cabang', 'rak' => function ($q) {
            $q->withCount('inventoryStock');
        }]);

        return view('master.gudang.show', compact('gudang'));
    }

    /**
     * Form edit gudang.
     */
    public function edit(Warehouse $gudang): View
    {
        $this->authorizeAccess('update warehouse');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.gudang.edit', compact('gudang', 'cabangs'));
    }

    /**
     * Perbarui data gudang.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $gudang): RedirectResponse
    {
        $this->authorizeAccess('update warehouse');

        try {
            $data = $request->validated();
            $data['status'] = (bool) ($data['status'] ?? true);
            $data['is_quarantine'] = (bool) ($data['is_quarantine'] ?? false);

            $gudang->update($data);

            return redirect()->route('master.gudang.index')
                ->with('success', "Data gudang '{$gudang->name}' ({$gudang->kode}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui gudang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui gudang: ' . $e->getMessage());
        }
    }

    /**
     * Hapus gudang dengan proteksi integritas.
     */
    public function destroy(Warehouse $gudang): RedirectResponse
    {
        $this->authorizeAccess('delete warehouse');

        try {
            $blockers = $gudang->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.gudang.index')
                    ->with('error', "Gudang '{$gudang->name}' ({$gudang->kode}) tidak dapat dihapus karena {$reason}.");
            }

            $nama = $gudang->name;
            $kode = $gudang->kode;
            $gudang->delete();

            return redirect()->route('master.gudang.index')
                ->with('success', "Gudang '{$nama}' ({$kode}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus gudang: ' . $e->getMessage());

            return redirect()->route('master.gudang.index')
                ->with('error', 'Gudang tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
