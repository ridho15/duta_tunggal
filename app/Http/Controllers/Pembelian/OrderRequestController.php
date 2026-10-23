<?php

namespace App\Http\Controllers\Pembelian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Http\Requests\Pembelian\StoreOrderRequestRequest;
use App\Http\Requests\Pembelian\UpdateOrderRequestRequest;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\TaxTypeHelper;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class OrderRequestController extends Controller
{
    /**
     * Tampilkan daftar Permintaan Pembelian (Order Request / OR)
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $cabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('search', $request->query('q', '')));
        $cabangFilter = $request->query('cabang_id');
        $supplierFilter = $request->query('supplier_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Query dasar dengan pembatasan cabang jika bukan pengguna berkewenangan penuh
        $baseQuery = OrderRequest::query();
        if (!$canAccessAll && $cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Hitung count per status untuk tab filter
        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'menunggu_approve' => (clone $baseQuery)->where('status', 'request_approve')->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'diproses' => (clone $baseQuery)->whereIn('status', ['partial', 'complete'])->count(),
            'ditolak' => (clone $baseQuery)->where('status', 'rejected')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        // 4 KPI Utama
        $kpi = [
            'total_requests' => $counts['semua'],
            'waiting_approval_count' => $counts['menunggu_approve'],
            'approved_count' => $counts['approved'],
            'rejected_count' => $counts['ditolak'],
        ];

        // Filter query menurut tab yang aktif
        $query = clone $baseQuery;
        match ($activeTab) {
            'draft' => $query->where('status', 'draft'),
            'menunggu_approve' => $query->where('status', 'request_approve'),
            'approved' => $query->where('status', 'approved'),
            'diproses' => $query->whereIn('status', ['partial', 'complete']),
            'ditolak' => $query->where('status', 'rejected'),
            'closed' => $query->where('status', 'closed'),
            default => $query,
        };

        // Filter Cabang
        if (!empty($cabangFilter) && $canAccessAll) {
            $query->where('cabang_id', $cabangFilter);
        }

        // Filter Supplier (berdasarkan supplier pada baris item)
        if (!empty($supplierFilter)) {
            $query->whereHas('orderRequestItem', function ($iq) use ($supplierFilter) {
                $iq->where('supplier_id', $supplierFilter);
            });
        }

        // Filter Tanggal Permintaan
        if (!empty($dateFrom)) {
            $query->whereDate('request_date', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $query->whereDate('request_date', '<=', $dateTo);
        }

        // Pencarian teks: nomor OR, keperluan, catatan, atau nama produk yang diminta
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('orderRequestItem.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Paginasi dengan mempertahankan parameter query
        $orderRequests = $query
            ->with([
                'createdBy',
                'currency',
                'orderRequestItem.product.uom',
                'orderRequestItem.supplier',
                'purchaseOrders',
            ])
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Data master untuk dropdown filter
        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama']);
        $suppliers = Supplier::orderBy('perusahaan')->get(['id', 'perusahaan', 'code']);

        return view('pembelian.order-request.index', compact(
            'orderRequests',
            'counts',
            'kpi',
            'activeTab',
            'search',
            'cabangs',
            'suppliers',
            'cabangFilter',
            'supplierFilter',
            'dateFrom',
            'dateTo'
        ));
    }

    /**
     * Tampilkan formulir pembuatan Permintaan Pembelian baru
     */
    public function create(): View
    {
        $user = Auth::user();
        $defaultCabangId = $user?->cabang_id ?? Cabang::first()?->id ?? 1;

        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama', 'kode']);
        $currencies = Currency::orderBy('code')->get();

        $defaultRequestNumber = HelperController::generateRequestNumber();
        $defaultDate = Carbon::now()->format('Y-m-d');
        $defaultCurrency = Currency::where('code', 'IDR')->first() ?? $currencies->first();

        return view('pembelian.order-request.create', compact(
            'cabangs',
            'currencies',
            'defaultRequestNumber',
            'defaultDate',
            'defaultCabangId',
            'defaultCurrency'
        ));
    }

    /**
     * Simpan Permintaan Pembelian baru ke basis data
     */
    public function store(StoreOrderRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $cabangId = (int) $validated['cabang_id'];
        $reqNumber = !empty($validated['request_number']) ? trim($validated['request_number']) : HelperController::generateRequestNumber();

        // Antisipasi nomor duplikat
        if (OrderRequest::where('request_number', $reqNumber)->exists()) {
            $reqNumber = HelperController::generateRequestNumber();
        }

        $action = $validated['action'] ?? 'draft';
        $status = $action === 'request_approve' ? 'request_approve' : 'draft';

        try {
            $orderRequest = DB::transaction(function () use ($validated, $cabangId, $reqNumber, $status, $user) {
                $record = OrderRequest::create([
                    'request_number' => $reqNumber,
                    'request_date' => $validated['request_date'],
                    'required_date' => $validated['required_date'] ?? null,
                    'purpose' => $validated['purpose'] ?? null,
                    'cabang_id' => $cabangId,
                    'currency_id' => $validated['currency_id'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'status' => $status,
                    'created_by' => $user?->id ?? 1,
                ]);

                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $originalPrice = (float) (!empty($item['original_price']) ? $item['original_price'] : $unitPrice);
                    $discount = (float) ($item['discount'] ?? 0);
                    $tax = (float) ($item['tax'] ?? 0);
                    $tipePajak = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'none');
                    $itemCabangId = !empty($item['cabang_id']) ? (int) $item['cabang_id'] : $cabangId;
                    $itemCurrencyId = !empty($item['currency_id']) ? (int) $item['currency_id'] : ($record->currency_id ?? null);

                    OrderRequestItem::create([
                        'order_request_id' => $record->id,
                        'product_id' => (int) $item['product_id'],
                        'supplier_id' => !empty($item['supplier_id']) ? (int) $item['supplier_id'] : null,
                        'cabang_id' => $itemCabangId,
                        'currency_id' => $itemCurrencyId,
                        'quantity' => $qty,
                        'fulfilled_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'original_price' => $originalPrice,
                        'discount' => $discount,
                        'tax' => $tax,
                        'tipe_pajak' => $tipePajak,
                        'note' => $item['note'] ?? null,
                        'status' => OrderRequestItem::STATUS_DRAFT,
                    ]);
                }

                return $record;
            });

            $msg = $status === 'request_approve'
                ? "Permintaan Pembelian {$orderRequest->request_number} berhasil dibuat dan diajukan untuk persetujuan."
                : "Permintaan Pembelian {$orderRequest->request_number} berhasil disimpan sebagai draf.";

            return redirect()->route('pembelian.or.show', $orderRequest)->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('Gagal menyimpan Order Request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat menyimpan permintaan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Tampilkan rincian detail Permintaan Pembelian
     */
    public function show(OrderRequest $orderRequest): View
    {
        $orderRequest->load([
            'createdBy',
            'currency',
            'orderRequestItem.product.uom',
            'orderRequestItem.supplier',
            'orderRequestItem.cabang',
            'orderRequestItem.approvedBy',
            'orderRequestItem.rejectedBy',
            'purchaseOrders.supplier',
        ]);

        $totalItems = $orderRequest->orderRequestItem->count();
        $totalQuantity = (float) $orderRequest->orderRequestItem->sum('quantity');
        $totalFulfilled = (float) $orderRequest->orderRequestItem->sum('fulfilled_quantity');
        $estimatedSubtotal = (float) $orderRequest->orderRequestItem->sum('subtotal');

        // Nama cabang (resolusi melalui cabang_id)
        $cabang = Cabang::find($orderRequest->cabang_id);

        return view('pembelian.order-request.show', compact(
            'orderRequest',
            'cabang',
            'totalItems',
            'totalQuantity',
            'totalFulfilled',
            'estimatedSubtotal'
        ));
    }

    /**
     * Tampilkan formulir edit untuk Permintaan Pembelian berstatus draf atau ditolak
     */
    public function edit(OrderRequest $orderRequest): View|RedirectResponse
    {
        if (!in_array($orderRequest->status, ['draft', 'rejected'], true)) {
            return redirect()->route('pembelian.or.show', $orderRequest)
                ->with('error', 'Hanya permintaan pembelian berstatus Draf atau Ditolak yang dapat diubah.');
        }

        $orderRequest->load([
            'orderRequestItem.product.uom',
            'orderRequestItem.supplier',
            'orderRequestItem.cabang',
            'orderRequestItem.currency'
        ]);

        $cabangs = Cabang::orderBy('nama')->get(['id', 'nama', 'kode']);
        $currencies = Currency::orderBy('code')->get();

        // Preload ONLY specific products linked to this order request's items
        $existingProductIds = $orderRequest->orderRequestItem->pluck('product_id')->filter()->unique();
        $preloadedProducts = Product::withoutGlobalScope('product_cabang')
            ->whereIn('id', $existingProductIds)
            ->with([
                'uom:id,name,abbreviation',
                'suppliers' => function ($q) {
                    $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')
                      ->withPivot('supplier_price');
                }
            ])
            ->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id', 'pajak'])
            ->map(function ($p) {
                $cheapest = $p->suppliers
                    ->filter(fn($s) => $s->pivot && $s->pivot->supplier_price !== null)
                    ->sortBy(fn($s) => (float) $s->pivot->supplier_price)
                    ->first();

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku ?? '-',
                    'label' => $p->sku && $p->sku !== '-' ? "({$p->sku}) {$p->name}" : $p->name,
                    'cost_price' => (float) ($p->cost_price ?? 0),
                    'uom' => $p->uom?->abbreviation ?? $p->uom?->name ?? 'Pcs',
                    'cabang_id' => $p->cabang_id,
                    'default_tax_rate' => (float) ($p->pajak ?? 11.0),
                    'recommended_supplier' => $cheapest ? [
                        'id' => $cheapest->id,
                        'perusahaan' => $cheapest->perusahaan,
                        'price' => (float) $cheapest->pivot->supplier_price,
                        'label' => "{$cheapest->perusahaan} (Rp " . number_format((float) $cheapest->pivot->supplier_price, 0, ',', '.') . ")"
                    ] : null,
                ];
            })
            ->keyBy('id');

        return view('pembelian.order-request.edit', compact(
            'orderRequest',
            'cabangs',
            'currencies',
            'preloadedProducts'
        ));
    }

    /**
     * Perbarui data Permintaan Pembelian
     */
    public function update(UpdateOrderRequestRequest $request, OrderRequest $orderRequest): RedirectResponse
    {
        if (!in_array($orderRequest->status, ['draft', 'rejected'], true)) {
            return redirect()->route('pembelian.or.show', $orderRequest)
                ->with('error', 'Permintaan pembelian tidak dapat diubah karena statusnya saat ini bukan draf/ditolak.');
        }

        $validated = $request->validated();
        $cabangId = (int) $validated['cabang_id'];

        $action = $validated['action'] ?? 'draft';
        $status = $action === 'request_approve' ? 'request_approve' : 'draft';

        try {
            DB::transaction(function () use ($orderRequest, $validated, $cabangId, $status) {
                $orderRequest->update([
                    'request_date' => $validated['request_date'],
                    'required_date' => $validated['required_date'] ?? null,
                    'purpose' => $validated['purpose'] ?? null,
                    'cabang_id' => $cabangId,
                    'currency_id' => $validated['currency_id'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'status' => $status,
                ]);

                // Hapus item lama dan buat item baru
                $orderRequest->orderRequestItem()->delete();

                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                    $originalPrice = (float) (!empty($item['original_price']) ? $item['original_price'] : $unitPrice);
                    $discount = (float) ($item['discount'] ?? 0);
                    $tax = (float) ($item['tax'] ?? 0);
                    $tipePajak = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'none');
                    $itemCabangId = !empty($item['cabang_id']) ? (int) $item['cabang_id'] : $cabangId;
                    $itemCurrencyId = !empty($item['currency_id']) ? (int) $item['currency_id'] : ($orderRequest->currency_id ?? null);

                    OrderRequestItem::create([
                        'order_request_id' => $orderRequest->id,
                        'product_id' => (int) $item['product_id'],
                        'supplier_id' => !empty($item['supplier_id']) ? (int) $item['supplier_id'] : null,
                        'cabang_id' => $itemCabangId,
                        'currency_id' => $itemCurrencyId,
                        'quantity' => $qty,
                        'fulfilled_quantity' => 0,
                        'unit_price' => $unitPrice,
                        'original_price' => $originalPrice,
                        'discount' => $discount,
                        'tax' => $tax,
                        'tipe_pajak' => $tipePajak,
                        'note' => $item['note'] ?? null,
                        'status' => OrderRequestItem::STATUS_DRAFT,
                    ]);
                }
            });

            $msg = $status === 'request_approve'
                ? "Permintaan Pembelian {$orderRequest->request_number} berhasil diperbarui dan diajukan untuk persetujuan."
                : "Permintaan Pembelian {$orderRequest->request_number} berhasil diperbarui.";

            return redirect()->route('pembelian.or.show', $orderRequest)->with('success', $msg);
        } catch (Throwable $e) {
            Log::error('Gagal memperbarui Order Request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan saat memperbarui permintaan: ' . $e->getMessage());
        }
    }

    /**
     * Hapus Permintaan Pembelian (hanya draf)
     */
    public function destroy(OrderRequest $orderRequest): RedirectResponse
    {
        if ($orderRequest->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya permintaan pembelian berstatus draf yang dapat dihapus.');
        }

        try {
            DB::transaction(function () use ($orderRequest) {
                $orderRequest->orderRequestItem()->delete();
                $orderRequest->delete();
            });

            return redirect()->route('pembelian.or.index')
                ->with('success', "Permintaan Pembelian {$orderRequest->request_number} berhasil dihapus.");
        } catch (Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menghapus permintaan pembelian: ' . $e->getMessage());
        }
    }

    /**
     * Ajukan persetujuan (Draft -> Request Approve)
     */
    public function requestApprove(OrderRequest $orderRequest): RedirectResponse
    {
        if (!in_array($orderRequest->status, ['draft', 'rejected'], true)) {
            return redirect()->back()->with('error', 'Hanya permintaan berstatus draf atau ditolak yang dapat diajukan persetujuannya.');
        }

        $orderRequest->update(['status' => 'request_approve']);

        return redirect()->route('pembelian.or.show', $orderRequest)
            ->with('success', "Permintaan Pembelian {$orderRequest->request_number} berhasil diajukan untuk persetujuan.");
    }

    /**
     * Setujui Permintaan Pembelian (Approved)
     */
    public function approve(OrderRequest $orderRequest): RedirectResponse
    {
        if (!in_array($orderRequest->status, ['draft', 'request_approve'], true)) {
            return redirect()->back()->with('error', 'Permintaan ini tidak dapat disetujui pada status saat ini.');
        }

        $user = Auth::user();

        DB::transaction(function () use ($orderRequest, $user) {
            $orderRequest->update(['status' => 'approved']);

            // Setujui seluruh baris item
            $orderRequest->orderRequestItem()->update([
                'status' => OrderRequestItem::STATUS_APPROVED,
                'approved_by' => $user?->id ?? 1,
                'approved_at' => Carbon::now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);
        });

        return redirect()->route('pembelian.or.show', $orderRequest)
            ->with('success', "Permintaan Pembelian {$orderRequest->request_number} telah disetujui dan siap diterbitkan PO.");
    }

    /**
     * Tolak Permintaan Pembelian (Rejected)
     */
    public function reject(Request $request, OrderRequest $orderRequest): RedirectResponse
    {
        if (!in_array($orderRequest->status, ['draft', 'request_approve'], true)) {
            return redirect()->back()->with('error', 'Permintaan ini tidak dapat ditolak pada status saat ini.');
        }

        $validated = $request->validate([
            'rejection_note' => 'required|string|max:1000',
        ], [
            'rejection_note.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $user = Auth::user();
        $reason = trim($validated['rejection_note']);

        DB::transaction(function () use ($orderRequest, $user, $reason) {
            $orderRequest->update([
                'status' => 'rejected',
                'note' => $orderRequest->note
                    ? $orderRequest->note . "\n[Ditolak oleh " . ($user?->name ?? 'Supervisor') . " pada " . Carbon::now()->format('d/m/Y H:i') . "]: " . $reason
                    : "[Ditolak oleh " . ($user?->name ?? 'Supervisor') . " pada " . Carbon::now()->format('d/m/Y H:i') . "]: " . $reason,
            ]);

            $orderRequest->orderRequestItem()->update([
                'status' => OrderRequestItem::STATUS_REJECTED,
                'rejected_by' => $user?->id ?? 1,
                'rejected_at' => Carbon::now(),
                'rejection_note' => $reason,
            ]);
        });

        return redirect()->route('pembelian.or.show', $orderRequest)
            ->with('success', "Permintaan Pembelian {$orderRequest->request_number} telah ditolak.");
    }

    /**
     * Tutup Permintaan Pembelian (Closed)
     */
    public function close(OrderRequest $orderRequest): RedirectResponse
    {
        if (in_array($orderRequest->status, ['closed'], true)) {
            return redirect()->back()->with('error', 'Permintaan sudah berstatus ditutup.');
        }

        $orderRequest->update(['status' => 'closed']);

        return redirect()->route('pembelian.or.show', $orderRequest)
            ->with('success', "Permintaan Pembelian {$orderRequest->request_number} berhasil ditutup.");
    }

    /**
     * Cetak PDF Dokumen Permintaan Pembelian
     */
    public function pdf(OrderRequest $orderRequest)
    {
        $orderRequest->load([
            'orderRequestItem.product.uom',
            'orderRequestItem.supplier',
            'createdBy',
            'currency',
        ]);

        $pdf = Pdf::loadView('pdf.order-request', [
            'orderRequest' => $orderRequest,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("Order_Request_{$orderRequest->request_number}.pdf");
    }

    /**
     * Pencarian produk secara server-side via AJAX (Limit 25-50 item, cepat dan hemat memori)
     */
    public function searchProducts(Request $request): \Illuminate\Http\JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $cabangId = $request->query('cabang_id');
        $supplierId = $request->query('supplier_id');
        $limit = min(max((int) $request->query('limit', 25), 5), 50);

        $query = Product::withoutGlobalScope('product_cabang')
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', true);
            });

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($cabangId) {
            $query->where(function ($q) use ($cabangId) {
                $q->whereNull('cabang_id')->orWhere('cabang_id', $cabangId);
            });
        }

        $activeTaxRate = (float) (\App\Models\TaxSetting::activeRate('PPN') ?? 11.0);

        $products = $query->with([
                'uom:id,name,abbreviation',
                'suppliers' => function ($q) {
                    $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')
                      ->withPivot('supplier_price');
                }
            ])
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id', 'pajak', 'tipe_pajak'])
            ->map(function ($p) use ($activeTaxRate, $supplierId) {
                $productTax = (float) ($p->pajak ?? 0);
                $defaultTax = $productTax > 0 ? $productTax : $activeTaxRate;

                // Cari supplier termurah dari pivot product_supplier
                $cheapestSupplier = $p->suppliers
                    ->filter(fn($s) => $s->pivot && $s->pivot->supplier_price !== null)
                    ->sortBy(fn($s) => (float) $s->pivot->supplier_price)
                    ->first();

                $recommended = null;
                if ($cheapestSupplier) {
                    $price = (float) $cheapestSupplier->pivot->supplier_price;
                    $recommended = [
                        'id' => $cheapestSupplier->id,
                        'perusahaan' => $cheapestSupplier->perusahaan,
                        'price' => $price,
                        'label' => "{$cheapestSupplier->perusahaan} (Rp " . number_format($price, 0, ',', '.') . ")"
                    ];
                } elseif ($p->suppliers->isNotEmpty()) {
                    $first = $p->suppliers->first();
                    $recommended = [
                        'id' => $first->id,
                        'perusahaan' => $first->perusahaan,
                        'price' => (float) ($p->cost_price ?? 0),
                        'label' => $first->perusahaan
                    ];
                }

                // Cek harga dari supplier spesifik jika diminta (untuk PO)
                $supplierPrice = null;
                if ($supplierId) {
                    $matched = $p->suppliers->firstWhere('id', (int) $supplierId);
                    if ($matched && $matched->pivot && $matched->pivot->supplier_price !== null) {
                        $supplierPrice = (float) $matched->pivot->supplier_price;
                    }
                }

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'sku' => $p->sku ?? '-',
                    'label' => $p->sku && $p->sku !== '-' ? "({$p->sku}) {$p->name}" : $p->name,
                    'cost_price' => (float) ($p->cost_price ?? 0),
                    'supplier_price' => $supplierPrice,
                    'tipe_pajak' => $p->tipe_pajak ?? 'inklusif',
                    'uom' => $p->uom?->abbreviation ?? $p->uom?->name ?? 'Pcs',
                    'cabang_id' => $p->cabang_id,
                    'default_tax_rate' => $defaultTax,
                    'recommended_supplier' => $recommended,
                    'suppliers' => $p->suppliers->map(fn($s) => [
                        'id' => $s->id,
                        'perusahaan' => $s->perusahaan,
                        'price' => $s->pivot?->supplier_price !== null ? (float) $s->pivot->supplier_price : null
                    ])->values()->all(),
                ];
            });

        return response()->json([
            'success' => true,
            'products' => $products
        ]);
    }

    /**
     * Pencarian supplier yang diprioritaskan berdasarkan relasi pivot produk terkait
     */
    public function searchSuppliers(Request $request): \Illuminate\Http\JsonResponse
    {
        $productId = $request->query('product_id');
        $search = trim((string) $request->query('q', ''));
        $limit = min(max((int) $request->query('limit', 25), 5), 50);

        $linkedSupplierIds = [];
        $linkedPriceMap = [];

        if ($productId) {
            $linkedRows = DB::table('product_supplier')
                ->where('product_id', $productId)
                ->get(['supplier_id', 'supplier_price']);

            foreach ($linkedRows as $row) {
                $sId = (int) $row->supplier_id;
                $linkedSupplierIds[] = $sId;
                $linkedPriceMap[$sId] = $row->supplier_price !== null ? (float) $row->supplier_price : null;
            }
        }

        $query = Supplier::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('perusahaan', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (!empty($linkedSupplierIds)) {
            $placeholders = implode(',', array_fill(0, count($linkedSupplierIds), '?'));
            $query->orderByRaw("CASE WHEN id IN ({$placeholders}) THEN 0 ELSE 1 END", $linkedSupplierIds);
        }

        $query->orderBy('perusahaan');

        $suppliers = $query->limit($limit)->get(['id', 'code', 'perusahaan'])
            ->map(function ($s) use ($linkedPriceMap) {
                $isLinked = array_key_exists($s->id, $linkedPriceMap);
                $price = $isLinked ? $linkedPriceMap[$s->id] : null;

                $label = "({$s->code}) {$s->perusahaan}";
                if ($price !== null) {
                    $label .= " - Rp " . number_format($price, 0, ',', '.');
                }

                return [
                    'id' => $s->id,
                    'code' => $s->code,
                    'perusahaan' => $s->perusahaan,
                    'supplier_price' => $price,
                    'label' => $label,
                    'is_linked' => $isLinked
                ];
            });

        return response()->json([
            'success' => true,
            'suppliers' => $suppliers
        ]);
    }
}
