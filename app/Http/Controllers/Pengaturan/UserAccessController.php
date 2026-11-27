<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access;
use App\Support\AccessManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Akses khusus per user (keputusan Ko Handy 27 Nov 2026): menambah / mencabut akses di luar perannya,
 * salin peran & akses dari user lain, kembalikan ke akses peran. Hanya Owner / Super Admin.
 */
class UserAccessController extends Controller
{
    private function authorizeManage(): void
    {
        abort_unless(Access::canManageRoles(), 403,
            'Anda tidak memiliki akses: Akses User. Solusi: hanya Owner / Super Admin yang dapat mengatur akses user.');
    }

    public function edit(User $user): View
    {
        $this->authorizeManage();
        $user->load('roles');
        $isSuper = Access::isSuper($user);

        return view('pengaturan.users.access', [
            'user' => $user,
            'isSuper' => $isSuper,
            'catalog' => AccessManager::catalogForUi(),
            'roleKeys' => $isSuper ? Access::allKeys() : Access::roleKeysForUser($user),
            'overrides' => Access::overrides($user),
            'history' => AccessManager::history('user', $user->id),
            'sourceUsers' => User::query()->whereKeyNot($user->id)->where('status', 1)->orderBy('name')
                ->with('roles:id,name')->get(['id', 'name', 'username']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage();
        if (Access::isSuper($user)) {
            return back()->with('error', "{$user->name} adalah Owner / Super Admin: aksesnya selalu penuh dan tidak bisa dikurangi.");
        }
        $data = $request->validate([
            'add' => ['nullable', 'array'], 'add.*' => ['string'],
            'revoke' => ['nullable', 'array'], 'revoke.*' => ['string'],
        ]);

        try {
            [$add, $revoke] = AccessManager::saveUserOverrides($user, $data['add'] ?? [], $data['revoke'] ?? []);
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan akses user', ['user' => $user->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Akses user gagal disimpan: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('settings.users.access.edit', $user)
            ->with('success', "Akses khusus {$user->name} tersimpan: +" . count($add) . ' ditambah, −' . count($revoke) . ' dicabut dari peran.');
    }

    public function copyFrom(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate(['source_user_id' => ['required', 'integer', 'exists:users,id']],
            ['source_user_id.required' => 'Pilih user sumber yang aksesnya mau disalin.']);
        $source = User::with('roles')->findOrFail($data['source_user_id']);

        if ($source->id === $user->id) {
            return back()->with('error', 'User sumber sama dengan user ini. Solusi: pilih user lain.');
        }
        if (Access::isSuper($source) && ! Access::isSuper($user)) {
            return back()->with('error', "{$source->name} adalah Owner / Super Admin. Solusi: berikan peran itu lewat menu Ubah Pengguna bila memang dimaksud.");
        }

        try {
            DB::transaction(function () use ($user, $source) {
                $user->syncRoles($source->roles->pluck('name')->all());
                $user->unsetRelation('roles');
                Access::flush();
                $over = Access::overrides($source);
                AccessManager::saveUserOverrides($user,
                    array_keys(array_filter($over)), array_keys(array_filter($over, fn ($v) => ! $v)),
                    'copy_from_user', "Peran & akses disalin dari {$source->name}: " . $source->roles->pluck('name')->implode(', '));
            });
        } catch (Throwable $e) {
            Log::error('Gagal menyalin akses user', ['user' => $user->id, 'source' => $source->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'Salin akses gagal: ' . $e->getMessage() . '. Solusi: coba lagi; bila berulang hubungi tim IT.');
        }

        return redirect()->route('settings.users.access.edit', $user)
            ->with('success', "Peran & akses {$user->name} kini sama dengan {$source->name}.");
    }

    public function reset(User $user): RedirectResponse
    {
        $this->authorizeManage();
        AccessManager::saveUserOverrides($user, [], [], 'reset', 'Dikembalikan ke akses peran');

        return redirect()->route('settings.users.access.edit', $user)
            ->with('success', "Akses {$user->name} dikembalikan sepenuhnya ke akses perannya.");
    }
}
