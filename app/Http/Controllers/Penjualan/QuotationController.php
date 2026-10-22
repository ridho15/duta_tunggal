<?php

namespace App\Http\Controllers\Penjualan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\ApprovalControlService;
use App\Services\QuotationService;
use App\Support\CurrencyConversionResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    protected QuotationService $quotationService;
    protected ApprovalControlService $approvalControlService;

    public function __construct(
        QuotationService $quotationService,
        ApprovalControlService $approvalControlService
    ) {
        $this->quotationService = $quotationService;
        $this->approvalControlService = $approvalControlService;
    }

    /**
     * Tampilkan daftar Penawaran Harga (Quotation).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);

        $user = Auth::user();
        $cabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('search', ''));
        $customerId = $request->query('customer_id');
        $dateFilter = $request->query('date');
        $cabangFilter = $request->query('cabang_id');

        $baseQuery = Quotation::query()->with(['customer:id,name,perusahaan', 'cabang:id,nama,kode', 'createdBy:id,name']);

        if (! $canAccessAll && $cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        } elseif ($cabangFilter) {
            $baseQuery->where('cabang_id', $cabangFilter);
        }

        // Hitung count untuk tab filter
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'perlu_tindakan' => (clone $baseQuery)->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_REQUEST_APPROVE, Quotation::STATUS_REJECT])->count(),
            'draft' => (clone $baseQuery)->where('status', Quotation::STATUS_DRAFT)->count(),
            'menunggu_approve' => (clone $baseQuery)->where('status', Quotation::STATUS_REQUEST_APPROVE)->count(),
            'disetujui' => (clone $baseQuery)->where('status', Quotation::STATUS_APPROVE)->count(),
            'kedaluwarsa' => (clone $baseQuery)->where(function ($q) {
                $q->where('status', Quotation::STATUS_EXPIRED)
                    ->orWhere(function ($sub) {
                        $sub->where('status', Quotation::STATUS_APPROVE)
                            ->whereNotNull('valid_until')
                            ->whereDate('valid_until', '<', now()->toDateString());
                    });
            })->count(),
        ];

        // KPI cards values
        $kpi = [
            'total' => $counts['semua'],
            'draft' => $counts['draft'],
            'menunggu' => $counts['menunggu_approve'],
            'disetujui' => $counts['disetujui'],
            'kedaluwarsa' => $counts['kedaluwarsa'],
            'total_nilai_disetujui' => (float) (clone $baseQuery)->where('status', Quotation::STATUS_APPROVE)->sum('total_amount'),
        ];

        // Terapkan filter tab
        $query = clone $baseQuery;
        match ($activeTab) {
            'perlu_tindakan' => $query->whereIn('status', [Quotation::STATUS_DRAFT, Quotation::STATUS_REQUEST_APPROVE, Quotation::STATUS_REJECT]),
            'draft' => $query->where('status', Quotation::STATUS_DRAFT),
            'menunggu_approve' => $query->where('status', Quotation::STATUS_REQUEST_APPROVE),
            'disetujui' => $query->where('status', Quotation::STATUS_APPROVE),
            'kedaluwarsa' => $query->where(function ($q) {
                $q->where('status', Quotation::STATUS_EXPIRED)
                    ->orWhere(function ($sub) {
                        $sub->where('status', Quotation::STATUS_APPROVE)
                            ->whereNotNull('valid_until')
                            ->whereDate('valid_until', '<', now()->toDateString());
                    });
            }),
            default => $query,
        };

        // Filter pencarian
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('perusahaan', 'like', "%{$search}%");
                    });
            });
        }

        // Filter customer
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // Filter bulan/tanggal
        if ($dateFilter) {
            $query->where('date', 'like', "{$dateFilter}%");
        }

        $quotations = $query->orderByDesc('id')->paginate(15)->withQueryString();

        // Master data untuk dropdown filter
        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'perusahaan']);
        $cabangs = $canAccessAll ? Cabang::query()->orderBy('nama')->get(['id', 'nama', 'kode']) : collect();
        $cabangNama = (! $canAccessAll && $cabangId) ? (Cabang::find($cabangId)?->nama ?? 'Pusat') : 'Semua Cabang';

        return view('penjualan.penawaran.index', [
            'quotations' => $quotations,
            'counts' => $counts,
            'kpi' => $kpi,
            'activeTab' => $activeTab,
            'search' => $search,
            'customerId' => $customerId,
            'dateFilter' => $dateFilter,
            'cabangFilter' => $cabangFilter,
            'customers' => $customers,
            'cabangs' => $cabangs,
            'cabangNama' => $cabangNama,
            'canAccessAll' => $canAccessAll,
        ]);
    }

    /**
     * Form pembuatan Penawaran Harga baru.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Quotation::class);

        $user = Auth::user();
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);
        $cabangId = $user?->cabang_id;

        $cabangs = Cabang::query()->orderBy('nama')->get(['id', 'nama', 'kode']);
        if (! $canAccessAll && $cabangId) {
            $selectedCabangId = $cabangId;
        } else {
            $selectedCabangId = (int) $request->query('cabang_id', $cabangs->first()?->id ?? 1);
        }

        $nextNumber = $this->quotationService->generateCode($selectedCabangId);

        $customers = Customer::query()->orderBy('name')->get([
            'id', 'name', 'perusahaan', 'address', 'phone', 'telephone', 'tempo_kredit', 'tipe_pembayaran'
        ]);

        $currencies = Currency::query()->orderBy('name')->get(['id', 'name', 'code', 'symbol', 'to_rupiah']);
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR') ?? $currencies->first()?->id ?? 1;

        $products = Product::query()
            ->with('uom:id,name,abbreviation')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'sell_price', 'cost_price', 'harga_batas', 'uom_id'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'sell_price' => (float) MoneyHelper::parseHighPrecision($p->sell_price ?? 0),
                    'cost_price' => (float) MoneyHelper::parseHighPrecision($p->cost_price ?? 0),
                    'uom' => $p->uom?->abbreviation ?? 'PCS',
                ];
            });

        return view('penjualan.penawaran.create', [
            'nextNumber' => $nextNumber,
            'cabangs' => $cabangs,
            'selectedCabangId' => $selectedCabangId,
            'canAccessAll' => $canAccessAll,
            'customers' => $customers,
            'currencies' => $currencies,
            'defaultCurrencyId' => $defaultCurrencyId,
            'products' => $products,
        ]);
    }

    /**
     * Simpan Penawaran Harga Baru.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Quotation::class);

        $validated = $request->validate([
            'quotation_number' => 'nullable|string|max:50',
            'customer_id' => 'required|exists:customers,id',
            'cabang_id' => 'required|exists:cabangs,id',
            'date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:date',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'tempo_pembayaran' => 'nullable|integer|min:0',
            'shipped_to' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'action' => 'required|in:draft,request_approve',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|in:None,PPN Included,PPN Excluded,Inklusif,Eksklusif',
            'items.*.notes' => 'nullable|string|max:255',
        ], [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'cabang_id.required' => 'Cabang wajib dipilih.',
            'date.required' => 'Tanggal penawaran wajib diisi.',
            'valid_until.after_or_equal' => 'Masa berlaku tidak boleh lebih awal dari tanggal penawaran.',
            'items.required' => 'Tambahkan minimal satu item produk penawaran.',
            'items.min' => 'Tambahkan minimal satu item produk penawaran.',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $cabangId = (int) $validated['cabang_id'];
            $number = ! empty($validated['quotation_number'])
                ? $validated['quotation_number']
                : $this->quotationService->generateCode($cabangId);

            $quotation = Quotation::create([
                'quotation_number' => $number,
                'customer_id' => $validated['customer_id'],
                'cabang_id' => $cabangId,
                'date' => $validated['date'],
                'valid_until' => $validated['valid_until'] ?? now()->parse($validated['date'])->addDays(30)->toDateString(),
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'] ?? 1,
                'tempo_pembayaran' => $validated['tempo_pembayaran'] ?? 30,
                'shipped_to' => $validated['shipped_to'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => Quotation::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'tax' => $itemData['tax'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'None',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $this->quotationService->updateTotalAmount($quotation);

            if ($validated['action'] === 'request_approve') {
                $this->quotationService->requestApprove($quotation);
                return redirect()->route('penjualan.quotations.show', $quotation)
                    ->with('success', "Penawaran {$quotation->quotation_number} berhasil dibuat dan diajukan persetujuan.");
            }

            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('success', "Draft Penawaran {$quotation->quotation_number} berhasil disimpan.");
        });
    }

    /**
     * Tampilkan detail Penawaran Harga.
     */
    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        $quotation->load([
            'customer',
            'cabang',
            'currency',
            'createdBy',
            'requestApproveBy',
            'approveBy',
            'rejectBy',
            'quotationItem.product.uom',
            'saleOrders:id,so_number,order_date,status,quotation_id',
            'revisionOf:id,quotation_number,status',
            'revisions:id,quotation_number,status,revision_of_id',
        ]);

        $canApprove = Gate::allows('approve', $quotation);
        $canReject = Gate::allows('reject', $quotation);
        $canRevise = Gate::allows('revise', $quotation);
        $canEdit = $quotation->isEditable() && Gate::allows('update', $quotation);
        $canDelete = $quotation->isEditable() && Gate::allows('delete', $quotation);
        $canRequestApprove = $quotation->status === Quotation::STATUS_DRAFT && Gate::allows('requestApprove', $quotation);

        $unusableReason = $quotation->unusableReasonForSaleOrder();
        $canConvertToSo = is_null($unusableReason) && Gate::allows('create', \App\Models\SaleOrder::class);

        return view('penjualan.penawaran.show', [
            'quotation' => $quotation,
            'canApprove' => $canApprove,
            'canReject' => $canReject,
            'canRevise' => $canRevise,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
            'canRequestApprove' => $canRequestApprove,
            'canConvertToSo' => $canConvertToSo,
            'unusableReason' => $unusableReason,
        ]);
    }

    /**
     * Form edit Penawaran Harga (hanya untuk Draft / Reject).
     */
    public function edit(Quotation $quotation): View|RedirectResponse
    {
        Gate::authorize('update', $quotation);

        if (! $quotation->isEditable()) {
            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('warning', "Penawaran {$quotation->quotation_number} berstatus {$quotation->statusLabel()} sehingga terkunci dari perubahan langsung. Gunakan fitur Buat Revisi untuk menerbitkan versi baru.");
        }

        $quotation->load(['customer', 'quotationItem.product.uom']);

        $user = Auth::user();
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $cabangs = Cabang::query()->orderBy('nama')->get(['id', 'nama', 'kode']);
        $customers = Customer::query()->orderBy('name')->get([
            'id', 'name', 'perusahaan', 'address', 'phone', 'telephone', 'tempo_kredit', 'tipe_pembayaran'
        ]);
        $currencies = Currency::query()->orderBy('name')->get(['id', 'name', 'code', 'symbol', 'to_rupiah']);

        $products = Product::query()
            ->with('uom:id,name,abbreviation')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'sell_price', 'cost_price', 'harga_batas', 'uom_id'])
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'sell_price' => (float) MoneyHelper::parseHighPrecision($p->sell_price ?? 0),
                    'cost_price' => (float) MoneyHelper::parseHighPrecision($p->cost_price ?? 0),
                    'uom' => $p->uom?->abbreviation ?? 'PCS',
                ];
            });

        return view('penjualan.penawaran.edit', [
            'quotation' => $quotation,
            'cabangs' => $cabangs,
            'canAccessAll' => $canAccessAll,
            'customers' => $customers,
            'currencies' => $currencies,
            'products' => $products,
        ]);
    }

    /**
     * Update Penawaran Harga.
     */
    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('update', $quotation);

        if (! $quotation->isEditable()) {
            throw ValidationException::withMessages([
                'status' => "Penawaran {$quotation->quotation_number} tidak dapat diubah karena berstatus {$quotation->statusLabel()}.",
            ]);
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'cabang_id' => 'required|exists:cabangs,id',
            'date' => 'required|date',
            'valid_until' => 'nullable|date|after_or_equal:date',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'tempo_pembayaran' => 'nullable|integer|min:0',
            'shipped_to' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:2000',
            'action' => 'required|in:draft,request_approve',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|in:None,PPN Included,PPN Excluded,Inklusif,Eksklusif',
            'items.*.notes' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated, $quotation) {
            $quotation->update([
                'customer_id' => $validated['customer_id'],
                'cabang_id' => $validated['cabang_id'],
                'date' => $validated['date'],
                'valid_until' => $validated['valid_until'] ?? now()->parse($validated['date'])->addDays(30)->toDateString(),
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'] ?? 1,
                'tempo_pembayaran' => $validated['tempo_pembayaran'] ?? 30,
                'shipped_to' => $validated['shipped_to'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Hapus items lama dan ganti dengan yang baru
            $quotation->quotationItem()->forceDelete();

            foreach ($validated['items'] as $itemData) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'tax' => $itemData['tax'] ?? 0,
                    'tax_type' => $itemData['tax_type'] ?? 'None',
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $this->quotationService->updateTotalAmount($quotation);

            if ($validated['action'] === 'request_approve') {
                $this->quotationService->requestApprove($quotation);
                return redirect()->route('penjualan.quotations.show', $quotation)
                    ->with('success', "Penawaran {$quotation->quotation_number} berhasil diperbarui dan diajukan persetujuan.");
            }

            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('success', "Penawaran {$quotation->quotation_number} berhasil diperbarui.");
        });
    }

    /**
     * Hapus Penawaran Harga (hanya untuk Draft / Reject).
     */
    public function destroy(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('delete', $quotation);

        if (! $quotation->isEditable()) {
            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('error', "Penawaran {$quotation->quotation_number} tidak dapat dihapus karena berstatus {$quotation->statusLabel()}.");
        }

        $number = $quotation->quotation_number;
        $quotation->delete();

        return redirect()->route('penjualan.quotations.index')
            ->with('success', "Penawaran {$number} berhasil dihapus.");
    }

    /**
     * Ajukan persetujuan untuk penawaran draft.
     */
    public function requestApprove(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('requestApprove', $quotation);

        if ($quotation->status !== Quotation::STATUS_DRAFT) {
            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('error', "Hanya draft penawaran yang dapat diajukan persetujuan.");
        }

        $this->quotationService->requestApprove($quotation);

        return redirect()->route('penjualan.quotations.show', $quotation)
            ->with('success', "Penawaran {$quotation->quotation_number} berhasil diajukan untuk persetujuan.");
    }

    /**
     * Setujui Penawaran Harga.
     */
    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('approve', $quotation);

        $options = [];
        if ($request->filled('override_reason')) {
            $options['override_reason'] = $request->input('override_reason');
        }

        $this->quotationService->approve($quotation, $options);

        return redirect()->route('penjualan.quotations.show', $quotation)
            ->with('success', "Penawaran {$quotation->quotation_number} telah disetujui.");
    }

    /**
     * Tolak Penawaran Harga.
     */
    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        Gate::authorize('reject', $quotation);

        $request->validate([
            'reason' => 'required|string|max:1000',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $reason = $request->input('reason');
        $quotation->update([
            'notes' => trim($quotation->notes . "\n[Ditolak: " . now()->format('d/m/Y H:i') . "] " . $reason),
        ]);

        $this->quotationService->reject($quotation);

        return redirect()->route('penjualan.quotations.show', $quotation)
            ->with('info', "Penawaran {$quotation->quotation_number} telah ditolak dengan alasan: {$reason}");
    }

    /**
     * Terbitkan Revisi Baru dari Penawaran Disetujui / Kedaluwarsa.
     */
    public function createRevision(Quotation $quotation): RedirectResponse
    {
        Gate::authorize('revise', $quotation);

        $revision = $this->quotationService->createRevision($quotation);

        return redirect()->route('penjualan.quotations.edit', $revision)
            ->with('success', "Revisi baru {$revision->quotation_number} berhasil dibuat dari penawaran {$quotation->quotation_number}. Silakan sesuaikan data harga atau item.");
    }

    /**
     * Konversi Penawaran Harga yang disetujui menjadi Sales Order.
     */
    public function convertToSaleOrder(Quotation $quotation): RedirectResponse
    {
        $unusableReason = $quotation->unusableReasonForSaleOrder();
        if ($unusableReason) {
            return redirect()->route('penjualan.quotations.show', $quotation)
                ->with('error', $unusableReason);
        }

        return redirect()->route('penjualan.so.create', ['quotation_id' => $quotation->id])
            ->with('info', "Form Sales Order dimuat dari penawaran {$quotation->quotation_number}. Periksa rincian sebelum menyimpan.");
    }

    /**
     * Cetak dokumen resmi Penawaran Harga dalam bentuk PDF.
     */
    public function pdf(Quotation $quotation): Response
    {
        Gate::authorize('view', $quotation);

        $quotation->load([
            'customer',
            'cabang',
            'currency',
            'createdBy',
            'approveBy',
            'quotationItem.product.uom',
        ]);

        $pdf = Pdf::loadView('pdf.quotation', compact('quotation'))
            ->setPaper('a4', 'landscape');

        $filename = 'Quotation-' . str_replace('/', '-', $quotation->quotation_number) . '.pdf';

        return $pdf->stream($filename);
    }
}
