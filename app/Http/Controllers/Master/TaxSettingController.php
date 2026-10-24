<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreTaxSettingRequest;
use App\Http\Requests\Master\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class TaxSettingController extends Controller
{
    /**
     * Otorisasi hak akses Master Pengaturan Pajak (Role: OWN, FIN, IT).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'FIN', 'IT'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Pengaturan Pajak.');
    }

    /**
     * Tampilkan daftar tarif dan pengaturan pajak (PPN, PPh, dll).
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any tax setting');

        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type', 'all');

        $query = TaxSetting::query();

        if ($type !== 'all' && ! empty($type)) {
            $query->where('type', $type);
        }

        // KPI Counts
        $kpi = [
            'total_tax' => (clone $query)->count(),
            'active_ppn' => TaxSetting::activeRate('PPN'),
            'active_pph' => TaxSetting::activeRate('PPH'),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $taxes = $query->latest('effective_date')->paginate(15)->withQueryString();

        return view('master.pajak.index', compact('taxes', 'kpi', 'search', 'type'));
    }

    /**
     * Form tambah tarif pajak baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create tax setting');

        return view('master.pajak.create');
    }

    /**
     * Simpan pengaturan pajak baru.
     */
    public function store(StoreTaxSettingRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create tax setting');

        try {
            $data = $request->validated();
            $data['status'] = (bool) ($data['status'] ?? true);
            $tax = TaxSetting::create($data);

            return redirect()->route('master.pajak.index')
                ->with('success', "Tarif pajak '{$tax->name}' ({$tax->rate}%) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan pajak: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan pajak: ' . $e->getMessage());
        }
    }

    /**
     * Form edit pajak.
     */
    public function edit(TaxSetting $pajak): View
    {
        $this->authorizeAccess('update tax setting');

        return view('master.pajak.edit', compact('pajak'));
    }

    /**
     * Perbarui data pengaturan pajak.
     */
    public function update(UpdateTaxSettingRequest $request, TaxSetting $pajak): RedirectResponse
    {
        $this->authorizeAccess('update tax setting');

        try {
            $data = $request->validated();
            $data['status'] = (bool) ($data['status'] ?? true);
            $pajak->update($data);

            return redirect()->route('master.pajak.index')
                ->with('success', "Pengaturan pajak '{$pajak->name}' berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui pajak: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui pajak: ' . $e->getMessage());
        }
    }

    /**
     * Hapus pengaturan pajak dengan proteksi integritas.
     */
    public function destroy(TaxSetting $pajak): RedirectResponse
    {
        $this->authorizeAccess('delete tax setting');

        try {
            $blockers = $pajak->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.pajak.index')
                    ->with('error', "Tarif pajak '{$pajak->name}' tidak dapat dihapus karena {$reason}.");
            }

            $name = $pajak->name;
            $pajak->delete();

            return redirect()->route('master.pajak.index')
                ->with('success', "Tarif pajak '{$name}' berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus pajak: ' . $e->getMessage());

            return redirect()->route('master.pajak.index')
                ->with('error', 'Pajak tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
