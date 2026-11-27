<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCustomerRequest;
use App\Http\Requests\Master\UpdateCustomerRequest;
use App\Models\Cabang;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Support\CustomerDuplicateFinder;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CustomerController extends Controller
{
    protected CustomerService $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Otorisasi akses Master Customer (Role: OWN, SM, SLS, KSR, FIN).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'SM', 'SLS', 'KSR', 'FIN'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Master Customer.');
    }

    /**
     * Tampilkan daftar Master Customer dengan filter & KPI.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any customer');

        $search = trim((string) $request->query('search', ''));
        $tipe = $request->query('tipe', 'all');
        $tipePembayaran = $request->query('tipe_pembayaran', 'all');
        $cabangId = $request->query('cabang_id');

        $user = Auth::user();
        $userCabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $baseQuery = Customer::query()->with('cabang');

        if (! $canAccessAll && $userCabangId) {
            $baseQuery->where(function ($q) use ($userCabangId) {
                $q->where('cabang_id', $userCabangId)
                    ->orWhereNull('cabang_id');
            });
        }

        // KPI Counts
        $kpi = [
            'total_customers' => (clone $baseQuery)->count(),
            'pkp_customers' => (clone $baseQuery)->where('tipe', 'PKP')->count(),
            'pri_customers' => (clone $baseQuery)->where('tipe', 'PRI')->count(),
            'credit_customers' => (clone $baseQuery)->where('tipe_pembayaran', 'Kredit')->count(),
            'total_credit_limit' => (clone $baseQuery)->sum('kredit_limit'),
        ];

        // Apply filters
        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('perusahaan', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('telephone', 'like', "%{$search}%")
                    ->orWhere('nik_npwp', 'like', "%{$search}%");
            });
        }

        if ($tipe !== 'all') {
            $query->where('tipe', $tipe);
        }

        if ($tipePembayaran !== 'all') {
            $query->where('tipe_pembayaran', $tipePembayaran);
        }

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::orderBy('nama')->get();

        return view('master.customer.index', compact('customers', 'cabangs', 'kpi', 'search', 'tipe', 'tipePembayaran', 'cabangId'));
    }

    /**
     * Tampilkan form pembuatan customer baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create customer');

        $cabangs = Cabang::orderBy('nama')->get();
        $generatedCode = $this->customerService->generateCode();

        return view('master.customer.create', compact('cabangs', 'generatedCode'));
    }

    /**
     * Simpan customer baru dengan proteksi duplikasi data.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create customer');

        $validated = $request->validated();

        try {
            $options = [];
            if ($request->filled('allow_duplicate_reason')) {
                $options['allow_duplicate_reason'] = $request->input('allow_duplicate_reason');
            }

            $validated['isSpecial'] = $request->boolean('isSpecial', false);

            $customer = $this->customerService->create($validated, $options);

            return redirect()->route('master.customers.index')
                ->with('success', "Customer '{$customer->name}' ({$customer->code}) berhasil didaftarkan.");
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan customer master: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan customer: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail profile customer, limit kredit & transaksi.
     */
    public function show(Customer $customer): View
    {
        $this->authorizeAccess('view customer');

        $customer->load(['cabang', 'deposit']);

        // Riwayat pesanan penjualan terbaru (10 transaksi)
        $recentSales = $customer->sales()
            ->with(['cabang'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Hitung total piutang berjalan jika relasi invoices / AR tersedia
        $totalAR = 0;
        try {
            $totalAR = (float) $customer->allInvoices()
                ->whereIn('status', ['unpaid', 'partially_paid'])
                ->sum('remaining_amount');
        } catch (Throwable $e) {
            // fallback bila tabel belum memiliki remaining_amount
            $totalAR = 0;
        }

        $remainingLimit = max(0, (float) $customer->kredit_limit - $totalAR);

        return view('master.customer.show', compact('customer', 'recentSales', 'totalAR', 'remainingLimit'));
    }

    /**
     * Tampilkan form edit customer.
     */
    public function edit(Customer $customer): View
    {
        $this->authorizeAccess('update customer');

        $cabangs = Cabang::orderBy('nama')->get();

        return view('master.customer.edit', compact('customer', 'cabangs'));
    }

    /**
     * Update data customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorizeAccess('update customer');

        $validated = $request->validated();
        $validated['isSpecial'] = $request->boolean('isSpecial', false);

        try {
            $customer->update($validated);

            return redirect()->route('master.customers.index')
                ->with('success', "Data customer '{$customer->name}' ({$customer->code}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui customer master: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui customer: ' . $e->getMessage());
        }
    }

    /**
     * Hapus customer (Soft Delete).
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorizeAccess('delete customer');

        try {
            // Cek apakah customer memiliki transaksi sales order aktif, piutang, atau saldo deposit
            $blockers = $customer->getDeletionBlockers();
            if ($blockers !== []) {
                $reason = implode(', ', $blockers);

                return back()->with('error', "Customer '{$customer->name}' tidak dapat dihapus karena {$reason}.");
            }

            $name = $customer->name;
            $code = $customer->code;
            $customer->delete();

            return redirect()->route('master.customers.index')
                ->with('success', "Customer '{$name}' ({$code}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus customer: ' . $e->getMessage());

            return back()->with('error', 'Customer tidak dapat dihapus: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint AJAX: Cek potensi data ganda (Live Deduplication Checker).
     * Memeriksa kesamaan Nama, Perusahaan, Telepon, atau NIK/NPWP.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $name = trim((string) $request->query('name', ''));
        $perusahaan = trim((string) $request->query('perusahaan', ''));
        $phone = trim((string) $request->query('phone', ''));
        $telephone = trim((string) $request->query('telephone', ''));
        $nikNpwp = trim((string) $request->query('nik_npwp', ''));
        $ignoreId = $request->integer('ignore_id') ?: null;

        $matches = collect();

        // 1. Cek Kemiripan Nama Customer
        if (mb_strlen($name) >= 3) {
            $nameMatches = Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('name', 'like', "%{$name}%")
                ->limit(5)
                ->get(['id', 'code', 'name', 'perusahaan', 'phone']);

            foreach ($nameMatches as $c) {
                $matches->put($c->id, [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                    'perusahaan' => $c->perusahaan,
                    'phone' => $c->phone,
                    'reason' => 'Nama customer serupa/mirip',
                ]);
            }
        }

        // 2. Cek Kemiripan Perusahaan
        if (mb_strlen($perusahaan) >= 3) {
            $compMatches = Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('perusahaan', 'like', "%{$perusahaan}%")
                ->limit(5)
                ->get(['id', 'code', 'name', 'perusahaan', 'phone']);

            foreach ($compMatches as $c) {
                if (! $matches->has($c->id)) {
                    $matches->put($c->id, [
                        'id' => $c->id,
                        'code' => $c->code,
                        'name' => $c->name,
                        'perusahaan' => $c->perusahaan,
                        'phone' => $c->phone,
                        'reason' => 'Nama perusahaan serupa/mirip',
                    ]);
                }
            }
        }

        // 3. Cek Nomor Handphone / Telepon
        $phoneClean = preg_replace('/[^0-9]/', '', $phone ?: $telephone);
        if (strlen($phoneClean) >= 7) {
            $suffix = substr($phoneClean, -7);
            $phoneMatches = Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where(function ($q) use ($suffix) {
                    $q->where('phone', 'like', "%{$suffix}")
                        ->orWhere('telephone', 'like', "%{$suffix}");
                })
                ->limit(5)
                ->get(['id', 'code', 'name', 'perusahaan', 'phone']);

            foreach ($phoneMatches as $c) {
                if (! $matches->has($c->id)) {
                    $matches->put($c->id, [
                        'id' => $c->id,
                        'code' => $c->code,
                        'name' => $c->name,
                        'perusahaan' => $c->perusahaan,
                        'phone' => $c->phone,
                        'reason' => 'Nomor kontak telepon/HP sama',
                    ]);
                }
            }
        }

        // 4. Cek NIK / NPWP
        $nikClean = preg_replace('/[^0-9]/', '', $nikNpwp);
        if (strlen($nikClean) >= 10) {
            $nikMatches = Customer::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->whereRaw("REGEXP_REPLACE(nik_npwp, '[^0-9]', '') = ?", [$nikClean])
                ->limit(5)
                ->get(['id', 'code', 'name', 'perusahaan', 'phone']);

            foreach ($nikMatches as $c) {
                if (! $matches->has($c->id)) {
                    $matches->put($c->id, [
                        'id' => $c->id,
                        'code' => $c->code,
                        'name' => $c->name,
                        'perusahaan' => $c->perusahaan,
                        'phone' => $c->phone,
                        'reason' => 'NIK / NPWP identik',
                    ]);
                }
            }
        }

        return response()->json([
            'has_duplicates' => $matches->isNotEmpty(),
            'count' => $matches->count(),
            'duplicates' => $matches->values(),
        ]);
    }

    /**
     * Endpoint AJAX: Generate kode customer baru.
     */
    public function generateCode(): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'code' => $this->customerService->generateCode(),
        ]);
    }
}
