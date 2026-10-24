<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreDriverRequest;
use App\Http\Requests\Master\UpdateDriverRequest;
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

class DriverController extends Controller
{
    /**
     * Otorisasi hak akses Master Driver / Supir (Role: OWN, ADP, GDG, IT).
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

        abort(403, 'Anda tidak memiliki hak akses untuk modul Pengemudi / Driver.');
    }

    /**
     * Tampilkan daftar pengemudi (driver).
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any driver');

        $search = trim((string) $request->query('search', ''));
        $cabangId = $request->query('cabang_id', 'all');

        $query = Driver::query()->with('cabang')->withCount('deliveryOrder');

        if ($cabangId !== 'all' && ! empty($cabangId)) {
            $query->where('cabang_id', $cabangId);
        }

        // KPI Counts
        $kpi = [
            'total_drivers' => (clone $query)->count(),
            'total_vehicles' => Vehicle::count(),
            'drivers_with_deliveries' => (clone $query)->has('deliveryOrder')->count(),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('license', 'like', "%{$search}%")
                    ->orWhereHas('cabang', fn ($cb) => $cb->where('nama', 'like', "%{$search}%"));
            });
        }

        $drivers = $query->latest()->paginate(15)->withQueryString();
        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.driver.index', compact('drivers', 'cabangs', 'kpi', 'search', 'cabangId'));
    }

    /**
     * Form tambah driver baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create driver');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.driver.create', compact('cabangs'));
    }

    /**
     * Simpan driver baru.
     */
    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create driver');

        try {
            $data = $request->validated();
            $driver = Driver::create($data);

            return redirect()->route('master.driver.index')
                ->with('success', "Driver '{$driver->name}' berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan driver: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan driver: ' . $e->getMessage());
        }
    }

    /**
     * Form edit driver.
     */
    public function edit(Driver $driver): View
    {
        $this->authorizeAccess('update driver');

        $cabangs = Cabang::where('status', true)->orderBy('nama')->get(['id', 'nama', 'kode']);

        return view('master.driver.edit', compact('driver', 'cabangs'));
    }

    /**
     * Perbarui data driver.
     */
    public function update(UpdateDriverRequest $request, Driver $driver): RedirectResponse
    {
        $this->authorizeAccess('update driver');

        try {
            $data = $request->validated();
            $driver->update($data);

            return redirect()->route('master.driver.index')
                ->with('success', "Data driver '{$driver->name}' berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui driver: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui driver: ' . $e->getMessage());
        }
    }

    /**
     * Hapus driver dengan proteksi riwayat pengantaran.
     */
    public function destroy(Driver $driver): RedirectResponse
    {
        $this->authorizeAccess('delete driver');

        try {
            $blockers = $driver->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.driver.index')
                    ->with('error', "Driver '{$driver->name}' tidak dapat dihapus karena {$reason}.");
            }

            $name = $driver->name;
            $driver->delete();

            return redirect()->route('master.driver.index')
                ->with('success', "Driver '{$name}' berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus driver: ' . $e->getMessage());

            return redirect()->route('master.driver.index')
                ->with('error', 'Driver tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
