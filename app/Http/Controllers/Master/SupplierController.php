<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Models\Cabang;
use App\Models\Supplier;
use App\Services\SupplierService;
use App\Support\MenuAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class SupplierController extends Controller
{
    protected SupplierService $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    /**
     * Otorisasi akses Master Supplier (Role: OWN, PUR, FIN).
     */
    protected function authorizeAccess(?string $permission = null): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $allowedRoles = ['OWN', 'PUR', 'FIN'];
        if (MenuAccess::isSuper($user) || ! empty(array_intersect($allowedRoles, MenuAccess::codesFor($user)))) {
            return;
        }

        if ($permission && $user->can($permission)) {
            return;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk modul Master Supplier.');
    }

    /**
     * Tampilkan daftar Master Supplier dengan filter & KPI.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess('view any supplier');

        $search = trim((string) $request->query('search', ''));
        $cabangId = $request->query('cabang_id');
        $bank = $request->query('bank', 'all');

        $user = Auth::user();
        $userCabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $baseQuery = Supplier::query()->with('cabang');

        if (! $canAccessAll && $userCabangId) {
            $baseQuery->where('cabang_id', $userCabangId);
        }

        // KPI Counts
        $kpi = [
            'total_suppliers' => (clone $baseQuery)->count(),
            'avg_top' => round((clone $baseQuery)->avg('tempo_hutang') ?? 0),
            'valid_bank_suppliers' => (clone $baseQuery)->whereNotNull('nomor_rekening')->where('nomor_rekening', '!=', '')->count(),
            'total_branches' => Cabang::count(),
        ];

        // Apply filters
        $query = clone $baseQuery;

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('perusahaan', 'like', "%{$search}%")
                    ->orWhere('kontak_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('handphone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('npwp', 'like', "%{$search}%")
                    ->orWhere('nomor_rekening', 'like', "%{$search}%");
            });
        }

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        if ($bank !== 'all' && $bank !== '') {
            $query->where('nama_bank', $bank);
        }

        $suppliers = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
        $cabangs = Cabang::orderBy('nama')->get();

        // Bank options untuk filter
        $availableBanks = Supplier::query()
            ->whereNotNull('nama_bank')
            ->where('nama_bank', '!=', '')
            ->distinct()
            ->pluck('nama_bank')
            ->sort()
            ->values();

        return view('master.supplier.index', compact('suppliers', 'cabangs', 'kpi', 'search', 'cabangId', 'bank', 'availableBanks'));
    }

    /**
     * Tampilkan form pembuatan supplier baru.
     */
    public function create(): View
    {
        $this->authorizeAccess('create supplier');

        $cabangs = Cabang::orderBy('nama')->get();
        $generatedCode = $this->supplierService->generateCode();

        $bankSuggestions = [
            'BCA',
            'Mandiri',
            'BNI',
            'BRI',
            'BSI (Bank Syariah Indonesia)',
            'CIMB Niaga',
            'Permata',
            'Danamon',
            'Panin',
            'OCBC NISP',
            'Bank Mega',
            'Bank Jago',
        ];

        return view('master.supplier.create', compact('cabangs', 'generatedCode', 'bankSuggestions'));
    }

    /**
     * Simpan data supplier baru.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorizeAccess('create supplier');

        $validated = $request->validated();

        try {
            $supplier = Supplier::create($validated);

            return redirect()->route('master.suppliers.index')
                ->with('success', "Supplier '{$supplier->perusahaan}' ({$supplier->code}) berhasil didaftarkan.");
        } catch (Throwable $e) {
            Log::error('Gagal menambahkan supplier master: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return back()->withInput()->with('error', 'Terjadi kesalahan saat menyimpan supplier: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan detail supplier, info rekening, & riwayat PO.
     */
    public function show(Supplier $supplier): View
    {
        $this->authorizeAccess('view supplier');

        $supplier->load(['cabang', 'productSuppliers.uom']);

        // Riwayat Purchase Order (10 pesanan terakhir)
        $recentPOs = $supplier->purchaseOrder()
            ->with('cabang')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $totalPOs = $supplier->purchaseOrder()->count();
        $totalSuppliedProducts = $supplier->productSuppliers()->count();

        return view('master.supplier.show', compact('supplier', 'recentPOs', 'totalPOs', 'totalSuppliedProducts'));
    }

    /**
     * Tampilkan form edit supplier.
     */
    public function edit(Supplier $supplier): View
    {
        $this->authorizeAccess('update supplier');

        $cabangs = Cabang::orderBy('nama')->get();

        $bankSuggestions = [
            'BCA',
            'Mandiri',
            'BNI',
            'BRI',
            'BSI (Bank Syariah Indonesia)',
            'CIMB Niaga',
            'Permata',
            'Danamon',
            'Panin',
            'OCBC NISP',
            'Bank Mega',
            'Bank Jago',
        ];

        return view('master.supplier.edit', compact('supplier', 'cabangs', 'bankSuggestions'));
    }

    /**
     * Update data supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorizeAccess('update supplier');

        $validated = $request->validated();

        try {
            $supplier->update($validated);

            return redirect()->route('master.suppliers.index')
                ->with('success', "Data supplier '{$supplier->perusahaan}' ({$supplier->code}) berhasil diperbarui.");
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui supplier master: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Terjadi kesalahan saat memperbarui supplier: ' . $e->getMessage());
        }
    }

    /**
     * Hapus supplier (Soft Delete).
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorizeAccess('delete supplier');

        try {
            // Cek apakah supplier memiliki PO aktif
            $hasActiveOrders = $supplier->purchaseOrder()->whereIn('status', ['draft', 'approved', 'process'])->exists();
            if ($hasActiveOrders) {
                return back()->with('error', "Supplier '{$supplier->perusahaan}' tidak dapat dihapus karena masih memiliki pesanan pembelian aktif.");
            }

            $name = $supplier->perusahaan;
            $code = $supplier->code;
            $supplier->delete();

            return redirect()->route('master.suppliers.index')
                ->with('success', "Supplier '{$name}' ({$code}) berhasil dihapus.");
        } catch (Throwable $e) {
            Log::error('Gagal menghapus supplier: ' . $e->getMessage());

            return back()->with('error', 'Supplier tidak dapat dihapus: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint AJAX: Cek potensi duplikasi nama supplier/perusahaan atau kontak.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $perusahaan = trim((string) $request->query('perusahaan', ''));
        $phone = trim((string) $request->query('phone', ''));
        $npwp = trim((string) $request->query('npwp', ''));
        $ignoreId = $request->integer('ignore_id') ?: null;

        $matches = collect();

        // 1. Cek Nama Perusahaan Supplier
        if (mb_strlen($perusahaan) >= 3) {
            $compMatches = Supplier::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->where('perusahaan', 'like', "%{$perusahaan}%")
                ->limit(5)
                ->get(['id', 'code', 'perusahaan', 'kontak_person', 'phone', 'nama_bank', 'nomor_rekening']);

            foreach ($compMatches as $s) {
                $matches->put($s->id, [
                    'id' => $s->id,
                    'code' => $s->code,
                    'perusahaan' => $s->perusahaan,
                    'kontak_person' => $s->kontak_person,
                    'phone' => $s->phone,
                    'bank_info' => $s->nama_bank ? "{$s->nama_bank} - {$s->nomor_rekening}" : 'Belum ada rekening',
                    'reason' => 'Nama perusahaan supplier serupa/mirip',
                ]);
            }
        }

        // 2. Cek NPWP
        $npwpClean = preg_replace('/[^0-9]/', '', $npwp);
        if (strlen($npwpClean) >= 10) {
            $npwpMatches = Supplier::query()
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->whereRaw("REGEXP_REPLACE(npwp, '[^0-9]', '') = ?", [$npwpClean])
                ->limit(5)
                ->get(['id', 'code', 'perusahaan', 'kontak_person', 'phone', 'nama_bank', 'nomor_rekening']);

            foreach ($npwpMatches as $s) {
                if (! $matches->has($s->id)) {
                    $matches->put($s->id, [
                        'id' => $s->id,
                        'code' => $s->code,
                        'perusahaan' => $s->perusahaan,
                        'kontak_person' => $s->kontak_person,
                        'phone' => $s->phone,
                        'bank_info' => $s->nama_bank ? "{$s->nama_bank} - {$s->nomor_rekening}" : 'Belum ada rekening',
                        'reason' => 'NPWP identik',
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
     * Endpoint AJAX: Generate kode supplier baru.
     */
    public function generateCode(): JsonResponse
    {
        $this->authorizeAccess();

        return response()->json([
            'code' => $this->supplierService->generateCode(),
        ]);
    }
}
