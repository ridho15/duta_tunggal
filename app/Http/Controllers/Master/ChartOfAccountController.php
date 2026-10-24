<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreChartOfAccountRequest;
use App\Http\Requests\Master\UpdateChartOfAccountRequest;
use App\Models\ChartOfAccount;
use App\Support\MenuAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ChartOfAccountController extends Controller
{
    /**
     * Otorisasi akses Master COA (Role: OWN, FIN).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if (MenuAccess::isSuper($user) || in_array('OWN', MenuAccess::codesFor($user)) || in_array('FIN', MenuAccess::codesFor($user))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Bagan Akun (COA).');
    }

    /**
     * Tampilkan daftar akun COA dengan filter & KPI.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any chart of account');

        $search = trim((string) $request->query('search', ''));
        $type = $request->query('type', 'all');
        $cashBank = $request->query('cash_bank', 'all');
        $status = $request->query('status', 'all');

        $baseQuery = ChartOfAccount::query()->with('coaParent');

        // KPI Counts
        $kpi = [
            'total_accounts' => (clone $baseQuery)->count(),
            'total_assets' => (clone $baseQuery)->where('type', 'Asset')->count(),
            'total_liabilities' => (clone $baseQuery)->where('type', 'Liability')->count(),
            'total_cash_bank' => (clone $baseQuery)->where('is_cash_bank', true)->count(),
        ];

        // Filter
        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type !== 'all' && $type !== null) {
            $query->where('type', $type);
        }

        if ($cashBank !== 'all' && $cashBank !== null) {
            $query->where('is_cash_bank', $cashBank === '1');
        }

        if ($status !== 'all' && $status !== null) {
            $query->where('is_active', $status === '1');
        }

        $accounts = $query->orderBy('code', 'asc')->paginate(50)->withQueryString();

        $accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset'];

        return view('master.coa.index', compact('accounts', 'kpi', 'search', 'type', 'cashBank', 'status', 'accountTypes'));
    }

    /**
     * Form pembuatan akun COA baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create chart of account');

        $accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset'];
        $parentAccounts = ChartOfAccount::query()
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        return view('master.coa.create', compact('accountTypes', 'parentAccounts'));
    }

    /**
     * Simpan akun COA baru.
     */
    public function store(StoreChartOfAccountRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create chart of account');

        $validated = $request->validated();
        $validated['is_cash_bank'] = $request->boolean('is_cash_bank');
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;

        try {
            $account = ChartOfAccount::create($validated);

            return redirect()->route('master.coa.index')
                ->with('success', "Akun '{$account->name}' ({$account->code}) berhasil dibuat.");
        } catch (Throwable $e) {
            Log::error('Gagal membuat akun COA: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan akun: ' . $e->getMessage());
        }
    }

    /**
     * Form edit akun COA.
     */
    public function edit(ChartOfAccount $coa): View
    {
        $this->authorizeAccess('update chart of account');

        $accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset'];
        $parentAccounts = ChartOfAccount::query()
            ->where('id', '!=', $coa->id)
            ->where('is_active', true)
            ->orderBy('code', 'asc')
            ->get();

        return view('master.coa.edit', compact('coa', 'accountTypes', 'parentAccounts'));
    }

    /**
     * Perbarui akun COA.
     */
    public function update(UpdateChartOfAccountRequest $request, ChartOfAccount $coa): RedirectResponse
    {
        $this->authorizeAccess('update chart of account');

        $validated = $request->validated();
        $validated['is_cash_bank'] = $request->boolean('is_cash_bank');
        $validated['is_active'] = $request->boolean('is_active', true);

        try {
            $coa->update($validated);

            return redirect()->route('master.coa.index')
                ->with('success', "Akun '{$coa->name}' ({$coa->code}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui akun COA: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui akun: ' . $e->getMessage());
        }
    }

    /**
     * Hapus akun COA dengan proteksi integritas.
     */
    public function destroy(ChartOfAccount $coa): RedirectResponse
    {
        $this->authorizeAccess('delete chart of account');

        try {
            $blockers = $coa->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return back()->with('error', "Akun '{$coa->name}' ({$coa->code}) tidak dapat dihapus karena {$reason}.");
            }

            $name = $coa->name;
            $code = $coa->code;
            $coa->delete();

            return redirect()->route('master.coa.index')
                ->with('success', "Akun '{$name}' ({$code}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus akun COA: ' . $e->getMessage());

            return back()->with('error', 'Akun tidak dapat dihapus: ' . $e->getMessage());
        }
    }
}
