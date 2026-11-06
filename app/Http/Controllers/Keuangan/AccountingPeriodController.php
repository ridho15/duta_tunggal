<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Services\AccountingPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Tutup Buku: periode akuntansi per cabang. Periode yang ditutup mengunci jurnal bertanggal di dalamnya
 * (JournalEntry::ensureDateIsOpen). Otorisasi lewat AccountingPeriodPolicy.
 */
class AccountingPeriodController extends Controller
{
    private const STATUSES = ['open' => 'Terbuka', 'closed' => 'Ditutup'];

    public function __construct(private AccountingPeriodService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AccountingPeriod::class);

        $status = $request->query('status');
        $periods = AccountingPeriod::query()->with(['cabang', 'closedBy'])
            ->when(array_key_exists((string) $status, self::STATUSES), fn ($q) => $q->where('status', $status))
            ->orderByDesc('period_start')->paginate(15)->withQueryString();

        return view('keuangan.period.index', ['periods' => $periods, 'statuses' => self::STATUSES, 'status' => $status]);
    }

    public function create(): View
    {
        Gate::authorize('create', AccountingPeriod::class);

        return view('keuangan.period.create', ['cabangs' => Cabang::query()->orderBy('nama')->get(), 'defaultCabang' => Auth::user()->cabang_id]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', AccountingPeriod::class);

        $data = $request->validate([
            'cabang_id' => ['nullable', 'integer', 'exists:cabangs,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ], ['period_end.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.']);

        abort_unless(Auth::user()->canAccessCabang($data['cabang_id'] ?? null), 403, 'Anda tidak punya akses ke cabang ini.');

        try {
            $this->service->createPeriod($data['period_start'], $data['period_end'], $data['cabang_id'] ?? null);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['period_start' => $e->getMessage()]);
        }

        return redirect()->route('keuangan.period.index')->with('success', 'Periode akuntansi berhasil dibuat.');
    }

    /** Halaman konfirmasi: menutup buku berdampak besar, jadi tidak cukup dialog konfirmasi biasa. */
    public function closeForm(AccountingPeriod $period): View
    {
        Gate::authorize('close', $period);

        return view('keuangan.period.close', compact('period'));
    }

    public function close(Request $request, AccountingPeriod $period): RedirectResponse
    {
        Gate::authorize('close', $period);

        try {
            $this->service->closePeriod($period, Auth::id(), $request->boolean('generate_closing_entries'));
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal menutup periode: '.$e->getMessage());
        }

        return redirect()->route('keuangan.period.index')->with('success', 'Periode berhasil ditutup. Jurnal bertanggal di dalam rentang ini sekarang terkunci.');
    }

    public function reopen(AccountingPeriod $period): RedirectResponse
    {
        Gate::authorize('reopen', $period);

        $this->service->reopenPeriod($period);

        return back()->with('success', 'Periode dibuka kembali. Jurnal penutup yang sudah dibuat tidak dibatalkan otomatis.');
    }

    public function destroy(AccountingPeriod $period): RedirectResponse
    {
        Gate::authorize('delete', $period);

        $period->delete();

        return back()->with('success', 'Periode dihapus.');
    }
}
