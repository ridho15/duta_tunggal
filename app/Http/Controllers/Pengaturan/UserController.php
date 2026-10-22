<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\StoreUserRequest;
use App\Http\Requests\Pengaturan\UpdateUserRequest;
use App\Models\Cabang;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Throwable;

class UserController extends Controller
{
    /**
     * Otorisasi akses modul Pengaturan Pengguna & Peran (Role: OWN, IT).
     */
    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Pengaturan Pengguna & Peran.');
    }

    /**
     * Tampilkan daftar Pengguna dengan KPI & filter dinamis.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $search = trim((string) $request->query('search', ''));
        $roleName = $request->query('role', 'all');
        $cabangId = $request->query('cabang_id', 'all');
        $status = $request->query('status', 'all');

        $query = User::query()->with(['cabang', 'warehouse', 'roles']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('telepon', 'like', "%{$search}%")
                    ->orWhere('kode_user', 'like', "%{$search}%")
                    ->orWhere('posisi', 'like', "%{$search}%");
            });
        }

        if ($roleName !== 'all' && ! empty($roleName)) {
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        if ($cabangId !== 'all' && ! empty($cabangId)) {
            $query->where('cabang_id', $cabangId);
        }

        if ($status !== 'all' && $status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $users = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        $kpi = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 1)->count(),
            'inactive_users' => User::where('status', 0)->count(),
            'total_roles' => Role::count(),
        ];

        $roles = Role::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('pengaturan.users.index', compact(
            'users',
            'kpi',
            'roles',
            'cabangs',
            'warehouses',
            'search',
            'roleName',
            'cabangId',
            'status'
        ));
    }

    /**
     * Tampilkan form pembuatan pengguna baru.
     */
    public function create(): View
    {
        $this->authorizeAccess();

        $roles = Role::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('pengaturan.users.create', compact('roles', 'cabangs', 'warehouses'));
    }

    /**
     * Simpan pengguna baru ke database beserta sinkronisasi role Spatie.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $fullName = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

            $user = new User();
            $user->first_name = $validated['first_name'];
            $user->last_name = $validated['last_name'] ?? null;
            $user->name = $fullName;
            $user->username = $validated['username'];
            $user->email = $validated['email'];
            $user->telepon = $validated['telepon'] ?? null;
            $user->posisi = $validated['posisi'];
            $user->password = Hash::make($validated['password']);
            $user->status = $request->boolean('status', true) ? 1 : 0;
            $user->manage_type = $validated['manage_type'];
            $user->cabang_id = $validated['cabang_id'] ?? null;
            $user->warehouse_id = $validated['warehouse_id'] ?? null;
            $user->save();

            if (! empty($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            DB::commit();

            return redirect()->route('settings.users.index')
                ->with('success', "Pengguna '{$user->name}' ({$user->kode_user}) berhasil didaftarkan.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal menambahkan pengguna baru: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan pengguna: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan profil detail pengguna, peran, dan matriks hak akses.
     */
    public function show(User $user): View
    {
        $this->authorizeAccess();

        $user->load(['cabang', 'warehouse', 'roles.permissions', 'permissions']);
        $allPermissions = $user->getAllPermissions()->sortBy('name');

        return view('pengaturan.users.show', compact('user', 'allPermissions'));
    }

    /**
     * Tampilkan form edit pengguna.
     */
    public function edit(User $user): View
    {
        $this->authorizeAccess();

        $user->load(['roles', 'cabang', 'warehouse']);
        $roles = Role::orderBy('name')->get();
        $cabangs = Cabang::orderBy('nama')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $userRoles = $user->roles->pluck('name')->toArray();

        return view('pengaturan.users.edit', compact('user', 'roles', 'cabangs', 'warehouses', 'userRoles'));
    }

    /**
     * Perbarui data pengguna & sinkronisasi role.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $fullName = trim($validated['first_name'] . ' ' . ($validated['last_name'] ?? ''));

            $user->first_name = $validated['first_name'];
            $user->last_name = $validated['last_name'] ?? null;
            $user->name = $fullName;
            $user->username = $validated['username'];
            $user->email = $validated['email'];
            $user->telepon = $validated['telepon'] ?? null;
            $user->posisi = $validated['posisi'];
            $user->status = $request->boolean('status', true) ? 1 : 0;
            $user->manage_type = $validated['manage_type'];
            $user->cabang_id = $validated['cabang_id'] ?? null;
            $user->warehouse_id = $validated['warehouse_id'] ?? null;

            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();

            if (isset($validated['roles'])) {
                $user->syncRoles($validated['roles']);
            }

            DB::commit();

            return redirect()->route('settings.users.index')
                ->with('success', "Data pengguna '{$user->name}' berhasil diperbarui.");
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Gagal memperbarui pengguna: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui pengguna: ' . $e->getMessage());
        }
    }

    /**
     * Hapus akun pengguna dengan proteksi akun aktif & Super Admin.
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeAccess();

        // 1. Proteksi diri sendiri
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan saat ini.');
        }

        // 2. Proteksi Super Admin / Owner terakhir
        if ($user->hasRole(['Super Admin', 'Owner'])) {
            $superAdminCount = User::role(['Super Admin', 'Owner'])->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Tidak dapat menghapus Super Admin / Owner terakhir dalam sistem.');
            }
        }

        try {
            $userName = $user->name;
            $user->delete();

            return redirect()->route('settings.users.index')
                ->with('success', "Pengguna '{$userName}' berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus pengguna: ' . $e->getMessage());

            return back()->with('error', 'Gagal menghapus pengguna: ' . $e->getMessage());
        }
    }

    /**
     * Toggle status aktif / nonaktif pengguna.
     */
    public function toggleActive(Request $request, User $user)
    {
        $this->authorizeAccess();

        if ($user->id === Auth::id()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang digunakan.',
                ], 422);
            }

            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang digunakan.');
        }

        $newStatus = $user->status ? 0 : 1;
        $user->update(['status' => $newStatus]);

        $statusLabel = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => "Akun '{$user->name}' berhasil {$statusLabel}.",
            ]);
        }

        return back()->with('success', "Akun '{$user->name}' berhasil {$statusLabel}.");
    }

    /**
     * Reset password akun pengguna oleh Admin/Owner.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password minimal terdiri dari 6 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password untuk akun '{$user->name}' ({$user->username}) berhasil diubah.");
    }
}
