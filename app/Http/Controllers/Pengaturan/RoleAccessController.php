<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Support\Access;
use App\Support\AccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Pengaturan → Peran & Hak Akses (keputusan Ko Handy 27 Nov 2026): hanya Owner / Super Admin.
 * Checklist per halaman (Lihat/Tambah/Ubah/Hapus + tombol khusus), tambah peran dari template, duplikat peran.
 */
class RoleAccessController extends Controller
{
    private function authorizeManage(): void
    {
        abort_unless(Access::canManageRoles(), 403,
            'Anda tidak memiliki akses: Peran & Hak Akses. Solusi: hanya Owner / Super Admin yang dapat mengatur peran.');
    }

    /** Peran bawaan sistem: nama tidak bisa diganti karena dipakai aturan lama (mis. "Sales Manager" boleh override). */
    private function isBuiltIn(Role $role): bool
    {
        return in_array($role->name, config('access.super_roles'), true)
            || array_key_exists($role->name, require database_path('data/access_default_views.php'));
    }

    private function isSuperRole(Role $role): bool
    {
        return in_array($role->name, config('access.super_roles'), true);
    }

    public function index(Request $request): View
    {
        $this->authorizeManage();
        $search = trim((string) $request->query('search', ''));

        $roles = Role::query()->withCount('users')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->paginate(25)->withQueryString();

        $total = count(Access::allKeys());
        $counts = [];
        foreach ($roles as $role) {
            $counts[$role->id] = $this->isSuperRole($role) ? $total : count(AccessManager::roleKeys($role));
        }

        return view('pengaturan.roles.index', [
            'roles' => $roles,
            'counts' => $counts,
            'totalKeys' => $total,
            'search' => $search,
            'superRoles' => config('access.super_roles'),
            'builtIn' => array_keys(require database_path('data/access_default_views.php')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeManage();
        $template = $request->filled('template') ? Role::find($request->integer('template')) : null;

        return view('pengaturan.roles.form', [
            'role' => null,
            'template' => $template,
            'templates' => Role::query()->whereNotIn('name', config('access.super_roles'))->orderBy('name')->get(['id', 'name']),
            'catalog' => AccessManager::catalogForUi(),
            'checked' => $template ? AccessManager::roleKeys($template) : [],
            'history' => collect(),
            'nameLocked' => false,
            'isSuperRole' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'description' => ['nullable', 'string', 'max:500'],
            'template_id' => ['nullable', 'integer', 'exists:roles,id'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string'],
        ], [
            'name.unique' => 'Nama peran ":input" sudah dipakai. Solusi: pakai nama lain, mis. ":input 2".',
            'name.required' => 'Nama peran wajib diisi.',
        ]);

        try {
            $role = DB::transaction(function () use ($data) {
                $template = ! empty($data['template_id']) ? Role::find($data['template_id']) : null;
                $role = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);
                DB::table('roles')->where('id', $role->id)->update([
                    'description' => $data['description'] ?? null,
                    'base_role' => $template ? $this->baseRoleOf($template) : null,
                ]);
                if ($template) {
                    // izin teknis lama ikut disalin agar aturan lama di laporan/proses berjalan sama dengan peran asal
                    $role->givePermissionTo($template->permissions()->where('name', 'not like', Access::PREFIX . '%')->pluck('name')->all());
                }
                AccessManager::saveRoleKeys($role, $data['keys'] ?? [], $template ? 'create_from_template' : 'create',
                    $template ? "Dibuat dari peran {$template->name}" : null);

                return $role;
            });
        } catch (Throwable $e) {
            Log::error('Gagal membuat peran', ['error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Peran gagal dibuat: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('settings.roles.edit', $role)->with('success', "Peran {$role->name} berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        $this->authorizeManage();

        return view('pengaturan.roles.form', [
            'role' => $role,
            'template' => null,
            'templates' => collect(),
            'catalog' => AccessManager::catalogForUi(),
            'checked' => $this->isSuperRole($role) ? Access::allKeys() : AccessManager::roleKeys($role),
            'history' => AccessManager::history('role', $role->id),
            'nameLocked' => $this->isBuiltIn($role),
            'isSuperRole' => $this->isSuperRole($role),
            'usersCount' => $role->users()->count(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManage();
        if ($this->isSuperRole($role)) {
            return back()->with('error', "Peran {$role->name} selalu punya akses penuh dan tidak bisa dikurangi.");
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'keys' => ['nullable', 'array'],
            'keys.*' => ['string'],
        ], [
            'name.unique' => 'Nama peran ":input" sudah dipakai. Solusi: pakai nama lain.',
        ]);

        try {
            [$added, $removed] = DB::transaction(function () use ($role, $data) {
                $update = ['description' => $data['description'] ?? null];
                if (! $this->isBuiltIn($role)) {
                    $update['name'] = trim($data['name']);
                }
                DB::table('roles')->where('id', $role->id)->update($update);
                $role->refresh();

                return AccessManager::saveRoleKeys($role, $data['keys'] ?? []);
            });
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan akses peran', ['role' => $role->id, 'error' => $e->getMessage()]);

            return back()->withInput()->with('error', 'Akses peran gagal disimpan: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('settings.roles.edit', $role)
            ->with('success', "Akses peran {$role->name} tersimpan (+" . count($added) . ' / −' . count($removed) . ' akses). Berlaku langsung untuk semua user peran ini.');
    }

    public function duplicate(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')],
        ], [
            'name.unique' => 'Nama peran ":input" sudah dipakai. Solusi: pakai nama lain.',
            'name.required' => 'Nama peran baru wajib diisi.',
        ]);
        if ($this->isSuperRole($role)) {
            return back()->with('error', "Peran {$role->name} tidak bisa diduplikat (akses penuh khusus pemilik). Solusi: duplikat peran Admin.");
        }

        try {
            $copy = DB::transaction(function () use ($role, $data) {
                $copy = Role::create(['name' => trim($data['name']), 'guard_name' => 'web']);
                DB::table('roles')->where('id', $copy->id)->update([
                    'description' => "Salinan dari {$role->name}",
                    'base_role' => $this->baseRoleOf($role),
                ]);
                $copy->givePermissionTo($role->permissions()->where('name', 'not like', Access::PREFIX . '%')->pluck('name')->all());
                AccessManager::saveRoleKeys($copy, AccessManager::roleKeys($role), 'clone', "Duplikat dari peran {$role->name}");

                return $copy;
            });
        } catch (Throwable $e) {
            Log::error('Gagal duplikat peran', ['role' => $role->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Duplikat peran gagal: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('settings.roles.edit', $copy)
            ->with('success', "Peran {$copy->name} dibuat dari {$role->name}. Silakan sesuaikan centangnya lalu simpan.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorizeManage();
        if ($this->isBuiltIn($role)) {
            return back()->with('error', "Peran bawaan {$role->name} tidak bisa dihapus. Solusi: kosongkan centangnya bila tidak dipakai.");
        }
        $users = $role->users()->count();
        if ($users > 0) {
            return back()->with('error', "Peran {$role->name} masih dipakai {$users} user. Solusi: pindahkan user tersebut ke peran lain dulu.");
        }

        AccessManager::log('role', $role->id, $role->name, 'delete', [], AccessManager::roleKeys($role));
        $name = $role->name;
        $role->delete();
        Access::flush();

        return redirect()->route('settings.roles.index')->with('success', "Peran {$name} dihapus.");
    }

    /** Peran asal untuk aturan lama: peran bawaan → namanya; peran turunan → peran asalnya. Owner/Super Admin tidak diwariskan. */
    private function baseRoleOf(Role $role): ?string
    {
        $base = $role->base_role ?: ($this->isBuiltIn($role) ? $role->name : null);

        return in_array($base, config('access.super_roles'), true) ? null : $base;
    }
}
