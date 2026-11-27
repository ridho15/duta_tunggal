<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreRakRequest;
use App\Http\Requests\Master\UpdateRakRequest;
use App\Models\Rak;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class RakController extends Controller
{
    /**
     * Otorisasi hak akses Master Rak (Role: OWN, GDG, ADP, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'GDG', 'ADP', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk pengelolaan Rak Gudang.');
    }

    /**
     * Simpan rak baru.
     */
    public function store(StoreRakRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create warehouse');

        try {
            $data = $request->validated();
            $rak = Rak::create($data);

            return back()->with('success', "Rak '{$rak->name}' ({$rak->code}) berhasil ditambahkan ke gudang.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan rak: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan rak: ' . $e->getMessage());
        }
    }

    /**
     * Perbarui data rak.
     */
    public function update(UpdateRakRequest $request, Rak $rak): RedirectResponse
    {
        $this->authorizeAccess('update warehouse');

        try {
            $data = $request->validated();
            $rak->update($data);

            return back()->with('success', "Data rak '{$rak->name}' ({$rak->code}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui rak: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui rak: ' . $e->getMessage());
        }
    }

    /**
     * Hapus rak dengan proteksi integritas stok.
     */
    public function destroy(Rak $rak): RedirectResponse
    {
        $this->authorizeAccess('delete warehouse');

        try {
            $blockers = $rak->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return back()->with('error', "Rak '{$rak->name}' ({$rak->code}) tidak dapat dihapus karena {$reason}.");
            }

            $nama = $rak->name;
            $kode = $rak->code;
            $rak->delete();

            return back()->with('success', "Rak '{$nama}' ({$kode}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus rak: ' . $e->getMessage());

            return back()->with('error', 'Rak tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
