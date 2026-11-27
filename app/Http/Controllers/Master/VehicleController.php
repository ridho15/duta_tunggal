<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreVehicleRequest;
use App\Http\Requests\Master\UpdateVehicleRequest;
use App\Models\Cabang;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class VehicleController extends Controller
{
    /**
     * Otorisasi hak akses Master Armada Kendaraan (Role: OWN, ADP, GDG, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'ADP', 'GDG', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Kendaraan Armada.');
    }

    /**
     * Tampilkan daftar kendaraan armada logistik.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any vehicle');

        $search = trim((string) $request->query('search', ''));
        $cabangId = $request->query('cabang_id', 'all');

        $query = Vehicle::query()->with('cabang')->withCount('deliveryOrder');

        if ($cabangId !== 'all' && ! empty($cabangId)) {
            $query->where('cabang_id', $cabangId);
        }

        // KPI Counts
        $kpi = [
            'total_vehicles' => (clone $query)->count(),
            'total_drivers' => Driver::count(),
            'vehicles_with_deliveries' => (clone $query)->has('deliveryOrder')->count(),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('plate', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('capacity', 'like', "%{$search}%")
                    ->orWhereHas('cabang', fn ($cb) => $cb->where('nama', 'like', "%{$search}%"));
            });
        }

        $vehicles = $query->latest()->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.kendaraan.index', compact('vehicles', 'cabangs', 'kpi', 'search', 'cabangId'));
    }

    /**
     * Form tambah kendaraan.
     */
    public function create(): View
    {
        $this->authorizeAccess('create vehicle');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.kendaraan.create', compact('cabangs'));
    }

    /**
     * Simpan data kendaraan baru.
     */
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create vehicle');

        try {
            $data = $request->validated();
            $data['plate'] = strtoupper(trim($data['plate']));
            $vehicle = Vehicle::create($data);

            return redirect()->route('master.kendaraan.index')
                ->with('success', "Kendaraan '{$vehicle->plate}' ({$vehicle->type}) berhasil didaftarkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan kendaraan: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan kendaraan: ' . $e->getMessage());
        }
    }

    /**
     * Form edit kendaraan.
     */
    public function edit(Vehicle $kendaraan): View
    {
        $this->authorizeAccess('update vehicle');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.kendaraan.edit', compact('kendaraan', 'cabangs'));
    }

    /**
     * Perbarui data kendaraan.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $kendaraan): RedirectResponse
    {
        $this->authorizeAccess('update vehicle');

        try {
            $data = $request->validated();
            $data['plate'] = strtoupper(trim($data['plate']));
            $kendaraan->update($data);

            return redirect()->route('master.kendaraan.index')
                ->with('success', "Data armada '{$kendaraan->plate}' berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui kendaraan: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui kendaraan: ' . $e->getMessage());
        }
    }

    /**
     * Hapus kendaraan dengan proteksi integritas pengiriman.
     */
    public function destroy(Vehicle $kendaraan): RedirectResponse
    {
        $this->authorizeAccess('delete vehicle');

        try {
            $blockers = $kendaraan->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.kendaraan.index')
                    ->with('error', "Kendaraan '{$kendaraan->plate}' tidak dapat dihapus karena {$reason}.");
            }

            $plate = $kendaraan->plate;
            $kendaraan->delete();

            return redirect()->route('master.kendaraan.index')
                ->with('success', "Kendaraan '{$plate}' berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus kendaraan: ' . $e->getMessage());

            return redirect()->route('master.kendaraan.index')
                ->with('error', 'Kendaraan tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
