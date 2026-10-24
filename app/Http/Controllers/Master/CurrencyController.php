<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCurrencyRequest;
use App\Http\Requests\Master\UpdateCurrencyRequest;
use App\Models\Currency;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CurrencyController extends Controller
{
    /**
     * Otorisasi hak akses Master Mata Uang (Role: OWN, FIN, IT).
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

        abort(403, 'Anda tidak memiliki hak akses untuk modul Mata Uang & Kurs.');
    }

    /**
     * Tampilkan daftar mata uang dan kurs terhadap Rupiah.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any currency');

        $search = trim((string) $request->query('search', ''));

        $query = Currency::query();

        // KPI Counts
        $kpi = [
            'total_currencies' => (clone $query)->count(),
            'foreign_currencies' => (clone $query)->where('code', '!=', 'IDR')->count(),
        ];

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        $currencies = $query->orderByRaw("CASE WHEN code = 'IDR' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('master.mata-uang.index', compact('currencies', 'kpi', 'search'));
    }

    /**
     * Form tambah mata uang baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create currency');

        return view('master.mata-uang.create');
    }

    /**
     * Simpan data mata uang baru.
     */
    public function store(StoreCurrencyRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create currency');

        try {
            $data = $request->validated();
            $data['code'] = strtoupper(trim($data['code']));
            $currency = Currency::create($data);

            return redirect()->route('master.mata-uang.index')
                ->with('success', "Mata uang '{$currency->name}' ({$currency->code}) berhasil ditambahkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan mata uang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal menambahkan mata uang: ' . $e->getMessage());
        }
    }

    /**
     * Form edit mata uang.
     */
    public function edit(Currency $mata_uang): View
    {
        $this->authorizeAccess('update currency');

        return view('master.mata-uang.edit', compact('mata_uang'));
    }

    /**
     * Perbarui data mata uang dan kurs.
     */
    public function update(UpdateCurrencyRequest $request, Currency $mata_uang): RedirectResponse
    {
        $this->authorizeAccess('update currency');

        try {
            $data = $request->validated();
            $data['code'] = strtoupper(trim($data['code']));
            $mata_uang->update($data);

            return redirect()->route('master.mata-uang.index')
                ->with('success', "Data kurs mata uang '{$mata_uang->code}' berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui mata uang: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Gagal memperbarui mata uang: ' . $e->getMessage());
        }
    }

    /**
     * Hapus mata uang dengan proteksi integritas transaksi.
     */
    public function destroy(Currency $mata_uang): RedirectResponse
    {
        $this->authorizeAccess('delete currency');

        try {
            $blockers = $mata_uang->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return redirect()->route('master.mata-uang.index')
                    ->with('error', "Mata uang '{$mata_uang->name}' ({$mata_uang->code}) tidak dapat dihapus karena {$reason}.");
            }

            $code = $mata_uang->code;
            $mata_uang->delete();

            return redirect()->route('master.mata-uang.index')
                ->with('success', "Mata uang '{$code}' berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus mata uang: ' . $e->getMessage());

            return redirect()->route('master.mata-uang.index')
                ->with('error', 'Mata uang tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
