<?php

namespace App\Http\Controllers\Penjualan;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Services\ApprovalControlService;
use App\Services\CreditValidationService;
use App\Services\SaleOrderCashierVerification;
use App\Services\SaleOrderDeliveryProgress;
use App\Services\SalesOrderService;
use App\Support\CurrencyConversionResolver;
use App\Support\ProductPriceLimitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SaleOrderController extends Controller
{
    protected SalesOrderService $salesOrderService;
    protected CreditValidationService $creditValidationService;
    protected ApprovalControlService $approvalControlService;
    protected SaleOrderDeliveryProgress $deliveryProgressService;
    protected SaleOrderCashierVerification $cashierVerificationService;

    public function __construct(
        SalesOrderService $salesOrderService,
        CreditValidationService $creditValidationService,
        ApprovalControlService $approvalControlService,
        SaleOrderDeliveryProgress $deliveryProgressService,
        SaleOrderCashierVerification $cashierVerificationService
    ) {
        $this->salesOrderService = $salesOrderService;
        $this->creditValidationService = $creditValidationService;
        $this->approvalControlService = $approvalControlService;
        $this->deliveryProgressService = $deliveryProgressService;
        $this->cashierVerificationService = $cashierVerificationService;
    }

    /**
     * Tampilan Daftar Pesanan Penjualan (Mockup B1_SO-List.html).
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $cabangId = $user?->cabang_id;
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $activeTab = $request->query('tab', 'perlu_tindakan');
        $search = trim((string) $request->query('search', ''));
        $dateFilter = $request->query('date');
        $salesFilter = $request->query('sales');

        // Base query with cabang scoping
        $baseQuery = SaleOrder::query();
        if (!$canAccessAll && $cabangId) {
            $baseQuery->where('cabang_id', $cabangId);
        }

        // Hitung count per tab filter secara efisien
        $counts = [
            'perlu_tindakan' => (clone $baseQuery)->where(function ($q) {
                $q->whereIn('status', ['draft', 'request_approve', 'reject'])
                    ->orWhere(function ($sub) {
                        $sub->whereIn('status', ['approved', 'confirmed', 'partially_delivered'])
                            ->whereHas('saleOrderItem', function ($items) {
                                $items->whereRaw(SaleOrderDeliveryProgress::availableQuantitySql('sale_order_items') . ' > 0');
                            });
                    });
            })->count(),
            'draft' => (clone $baseQuery)->where('status', 'draft')->count(),
            'menunggu_approve' => (clone $baseQuery)->where('status', 'request_approve')->count(),
            'dalam_proses' => (clone $baseQuery)->whereIn('status', ['approved', 'confirmed', 'partially_delivered', 'received'])->count(),
            'selesai' => (clone $baseQuery)->whereIn('status', ['completed', 'closed'])->count(),
            'semua' => (clone $baseQuery)->count(),
        ];

        // Filter query menurut tab yang aktif
        $query = clone $baseQuery;
        match ($activeTab) {
            'draft' => $query->where('status', 'draft'),
            'menunggu_approve' => $query->where('status', 'request_approve'),
            'dalam_proses' => $query->whereIn('status', ['approved', 'confirmed', 'partially_delivered', 'received']),
            'selesai' => $query->whereIn('status', ['completed', 'closed']),
            'semua' => $query,
            default => $query->where(function ($q) {
                $q->whereIn('status', ['draft', 'request_approve', 'reject'])
                    ->orWhere(function ($sub) {
                        $sub->whereIn('status', ['approved', 'confirmed', 'partially_delivered'])
                            ->whereHas('saleOrderItem', function ($items) {
                                $items->whereRaw(SaleOrderDeliveryProgress::availableQuantitySql('sale_order_items') . ' > 0');
                            });
                    });
            }),
        };

        // Pencarian nomor SO, customer, atau nama produk
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('so_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('name', 'like', "%{$search}%")
                            ->orWhere('perusahaan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('saleOrderItem.product', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Filter Sales Creator
        if ($salesFilter && $salesFilter !== 'semua') {
            $query->where('created_by', (int) $salesFilter);
        }

        // Filter Tanggal / Bulan
        if ($dateFilter) {
            $query->whereYear('order_date', substr($dateFilter, 0, 4))
                ->whereMonth('order_date', substr($dateFilter, 5, 2));
        }

        $saleOrders = $query->with(['customer', 'createdBy', 'saleOrderItem.product'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Hitung progres pengiriman secara efisien untuk seluruh SO di halaman ini
        $progressSummary = $this->deliveryProgressService->forSaleOrders($saleOrders->pluck('id'));

        // Ambil daftar sales yang aktif untuk filter dropdown
        $creatorIds = SaleOrder::query()->distinct()->whereNotNull('created_by')->pluck('created_by');
        $salesUsers = User::query()
            ->whereIn('id', $creatorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('penjualan.so.index', [
            'saleOrders' => $saleOrders,
            'counts' => $counts,
            'activeTab' => $activeTab,
            'search' => $search,
            'dateFilter' => $dateFilter,
            'salesFilter' => $salesFilter,
            'salesUsers' => $salesUsers,
            'progressSummary' => $progressSummary,
            'cabangNama' => $user?->cabang?->nama ?? 'Semua Cabang',
        ]);
    }

    /**
     * Form Buat SO Baru (Mockup E3_SO-Form.html).
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $canAccessAll = is_array($user?->manage_type) && in_array('all', $user->manage_type);

        $cabangs = Cabang::query()
            ->when(!$canAccessAll && $user?->cabang_id, fn ($q) => $q->where('id', $user->cabang_id))
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama']);

        $currencies = Currency::query()->orderBy('name')->get();
        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? Currency::query()->orderBy('id')->value('id');

        $customers = Customer::query()
            ->with(['deposit'])
            ->orderBy('name')
            ->get([
                'id', 'code', 'name', 'perusahaan', 'address', 'telephone',
                'tempo_kredit', 'kredit_limit', 'tipe_pembayaran', 'tipe'
            ]);

        // Hitung stok bebas produk
        $freeStockByProduct = InventoryStock::query()
            ->selectRaw('product_id, GREATEST(0, SUM(qty_available - qty_reserved)) as free_stock')
            ->groupBy('product_id')
            ->pluck('free_stock', 'product_id');

        $products = Product::query()
            ->with('uom')
            ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($freeStockByProduct) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'sell_price' => (float) $p->sell_price,
                    'bottom_price' => (float) ($p->bottom_price ?? 0),
                    'free_stock' => max(0.0, (float) ($freeStockByProduct[$p->id] ?? 0)),
                    'uom' => $p->uom?->abbreviation ?? 'PCS',
                ];
            });

        $quotations = Quotation::query()
            ->usable()
            ->with('customer:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'quotation_number', 'customer_id', 'total_amount']);

        $selectedQuotationId = $request->query('quotation_id');
        $selectedQuotation = null;
        if ($selectedQuotationId) {
            $selectedQuotation = Quotation::query()
                ->with(['customer', 'quotationItem.product.uom'])
                ->find($selectedQuotationId);
        }

        $nextSoNumber = $this->salesOrderService->generateSoNumber($canAccessAll ? null : $user?->cabang_id);

        return view('penjualan.so.create', [
            'nextSoNumber' => $nextSoNumber,
            'cabangs' => $cabangs,
            'currencies' => $currencies,
            'defaultCurrencyId' => $defaultCurrencyId,
            'customers' => $customers,
            'products' => $products,
            'quotations' => $quotations,
            'selectedQuotation' => $selectedQuotation,
            'selectedQuotationId' => $selectedQuotationId,
            'user' => $user,
        ]);
    }

    /**
     * Simpan SO Baru ke Database.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'cabang_id' => 'required|exists:cabangs,id',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'tipe_pengiriman' => 'required|in:Ambil Sendiri,Kirim Langsung',
            'shipped_to' => 'nullable|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'exchange_rate' => 'nullable|numeric|min:0',
            'tempo_pembayaran' => 'nullable|integer|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'quotation_id' => 'nullable|exists:quotations,id',
            'notes' => 'nullable|string|max:1000',
            'action' => 'required|in:draft,request_approve',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|in:None,Inklusif,Eksklusif',
        ], [
            'customer_id.required' => 'Customer wajib dipilih.',
            'cabang_id.required' => 'Cabang wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 item barang.',
            'items.min' => 'Minimal harus ada 1 item barang.',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $currencyId = (int) $validated['currency_id'];
        $currency = Currency::find($currencyId);
        $exchangeRate = (float) ($validated['exchange_rate'] ?? ($currency?->to_rupiah ?? 1.0));
        $shippingCost = (float) ($validated['shipping_cost'] ?? 0);

        // Hitung estimasi total & periksa pelanggaran batas harga bawah
        $hasPriceViolation = false;
        $totalItemsAmount = 0;

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            $unitPrice = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);
            $qty = (float) $item['quantity'];

            if ($product && ProductPriceLimitation::validatePrice($product, $unitPrice, $disc)) {
                $hasPriceViolation = true;
            }

            $subtotal = HelperController::hitungSubtotal($qty, $unitPrice, $disc, 0, 'None');
            $totalItemsAmount += (float) $subtotal;
        }

        $grandTotalIdr = CurrencyConversionResolver::convertToIdr(
            MoneyHelper::parseHighPrecision($totalItemsAmount + $shippingCost),
            $currencyId,
            false
        );

        // Validasi Limit Kredit jika Customer bertipe Kredit
        if ($customer->tipe_pembayaran === 'Kredit') {
            $creditCheck = $this->creditValidationService->canCustomerMakePurchase($customer, (float) $grandTotalIdr);
            if (!$creditCheck['can_purchase']) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['customer_id' => implode(' ', $creditCheck['messages'])]);
            }
        }

        // Tentukan status awal: jika minta 'request_approve', status = request_approve; jika draft, status = draft
        $status = $validated['action'] === 'request_approve' ? 'request_approve' : 'draft';

        $soNumber = $this->salesOrderService->generateSoNumber((int) $validated['cabang_id']);

        $saleOrder = DB::transaction(function () use ($validated, $soNumber, $customer, $currencyId, $exchangeRate, $shippingCost, $status) {
            $so = SaleOrder::create([
                'so_number' => $soNumber,
                'customer_id' => $validated['customer_id'],
                'cabang_id' => $validated['cabang_id'],
                'quotation_id' => $validated['quotation_id'] ?? null,
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'tipe_pengiriman' => $validated['tipe_pengiriman'],
                'shipped_to' => $validated['shipped_to'] ?? $customer->address,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'tempo_pembayaran' => (int) ($validated['tempo_pembayaran'] ?? ($customer->tempo_kredit ?? 0)),
                'shipping_cost' => $shippingCost,
                'notes' => $validated['notes'] ?? null,
                'status' => $status,
                'created_by' => Auth::id(),
                'request_approve_at' => $status === 'request_approve' ? now() : null,
                'request_approve_by' => $status === 'request_approve' ? Auth::id() : null,
                'total_amount' => 0,
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $qty = (float) $item['quantity'];
                $price = (float) $item['unit_price'];
                $disc = (float) ($item['discount'] ?? 0);
                $taxType = strtolower($item['tax_type'] ?? 'none');

                $subtotal = HelperController::hitungSubtotal($qty, $price, $disc, 0, $taxType);
                $total += $subtotal;

                SaleOrderItem::create([
                    'sale_order_id' => $so->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $qty,
                    'delivered_quantity' => 0,
                    'unit_price' => $price,
                    'discount' => $disc,
                    'tax' => 0,
                    'tipe_pajak' => $taxType,
                ]);
            }

            $so->update(['total_amount' => $total]);

            return $so;
        });

        $msg = $status === 'request_approve'
            ? "Pesanan Penjualan {$soNumber} berhasil disimpan dan diajukan untuk persetujuan."
            : "Pesanan Penjualan {$soNumber} berhasil disimpan sebagai draft.";

        return redirect()->route('penjualan.so.show', $saleOrder->id)->with('success', $msg);
    }

    /**
     * Tampilan Detail SO & Stepper Progres (Mockup E2_SO-Detail.html).
     */
    public function show(SaleOrder $saleOrder): View
    {
        $saleOrder->loadMissing([
            'customer.deposit',
            'cabang',
            'createdBy',
            'approveBy',
            'cashierApprovedBy',
            'rejectBy',
            'saleOrderItem.product.uom',
            'deliveryOrder.warehouse',
            'deliveryOrder.suratJalan',
        ]);

        $user = Auth::user();

        // Hitung progres riil pengiriman item SO
        $progress = $this->deliveryProgressService->forSaleOrder($saleOrder);
        $totals = $progress['totals'];
        $itemProgress = $progress['items'];

        // Cek wewenang approval user saat ini
        $canApprove = false;
        $approveDenyReason = null;
        if (in_array($saleOrder->status, ['draft', 'request_approve'])) {
            $check = $this->approvalControlService->canApproveSaleOrder($user, $saleOrder);
            $canApprove = $check['allowed'];
            $approveDenyReason = $check['reason'];
        }

        // Stepper state 6 Langkah:
        // 1. SO Dibuat
        // 2. Diapprove Kasir / Atasan
        // 3. DO
        // 4. Dikirim Gudang
        // 5. Surat Jalan
        // 6. Invoice
        $isSoApproved = in_array($saleOrder->status, ['approved', 'confirmed', 'partial_confirmed', 'partially_delivered', 'completed', 'received']);
        $hasDoCreated = $totals['ordered'] > 0 && ($totals['delivered'] > 0 || $totals['in_process'] > 0);
        $isDoAllDone = $totals['ordered'] > 0 && $totals['available'] <= 0;

        $dos = $saleOrder->deliveryOrder;
        $hasDoSent = $dos->whereIn('status', ['sent', 'received', 'completed'])->isNotEmpty();
        $isDoAllSent = $dos->isNotEmpty() && $dos->whereNotIn('status', ['sent', 'received', 'completed'])->isEmpty();

        $hasSj = $dos->flatMap->suratJalan->isNotEmpty();
        $isInvoiceDone = in_array($saleOrder->status, ['completed', 'received']);

        $stepper = [
            'so_created' => [
                'done' => true,
                'active' => false,
                'title' => 'SO dibuat',
                'info' => $saleOrder->order_date ? $saleOrder->order_date->format('d M') . ' · ' . ($saleOrder->createdBy->name ?? 'Sales') : '-',
            ],
            'approved' => [
                'done' => $isSoApproved,
                'active' => $saleOrder->status === 'request_approve',
                'title' => 'Diapprove ' . ($saleOrder->cashier_approved_at ? 'kasir' : 'atasan'),
                'info' => $isSoApproved
                    ? ($saleOrder->approve_at ? $saleOrder->approve_at->format('d M') . ' · ' . ($saleOrder->approveBy->name ?? 'Approver') : 'Disetujui')
                    : ($saleOrder->status === 'request_approve' ? 'Menunggu persetujuan' : 'Belum diapprove'),
            ],
            'do' => [
                'done' => $isDoAllDone,
                'active' => $isSoApproved && !$isDoAllDone,
                'title' => 'DO',
                'info' => round($totals['ordered'] - $totals['available']) . ' dari ' . round($totals['ordered']) . ' pcs',
            ],
            'sent' => [
                'done' => $isDoAllSent,
                'active' => $hasDoSent && !$isDoAllSent,
                'title' => 'Dikirim gudang',
                'info' => $hasDoSent ? 'Dalam proses kirim' : 'Menunggu DO siap',
            ],
            'surat_jalan' => [
                'done' => $hasSj,
                'active' => false,
                'title' => 'Surat Jalan',
                'info' => $hasSj ? 'SJ Terbit' : 'Belum ada SJ',
            ],
            'invoice' => [
                'done' => $isInvoiceDone,
                'active' => false,
                'title' => 'Invoice',
                'info' => $isInvoiceDone ? 'Selesai' : 'Belum diterbitkan',
            ],
        ];

        // Tentukan kartu "Langkah Berikutnya" (Dynamic Action Banner)
        $nextStep = null;
        if ($saleOrder->status === 'request_approve') {
            $nextStep = [
                'title' => 'Persetujuan Pesanan Penjualan',
                'description' => 'SO memerlukan persetujuan sebelum dapat diproses menjadi Delivery Order.',
                'action_label' => $canApprove ? 'Setujui SO' : 'Menunggu Approval Atasan',
                'action_url' => null, // trigger modal
                'can_action' => $canApprove,
                'is_approve' => true,
            ];
        } elseif ($saleOrder->status === 'draft') {
            $nextStep = [
                'title' => 'Lanjutkan Draft SO',
                'description' => 'SO masih berupa draft. Lengkapi barang atau ajukan persetujuan sekarang.',
                'action_label' => 'Ajukan Persetujuan',
                'action_url' => null,
                'can_action' => true,
                'is_submit' => true,
            ];
        } elseif ($isSoApproved && $totals['available'] > 0) {
            $nextStep = [
                'title' => "Buat DO untuk sisa {$totals['available']} pcs barang",
                'description' => 'Barang otomatis diambil dari SO ini, tinggal pilih gudang dan kuantitas.',
                'action_label' => '+ Buat DO dari SO ini',
                'action_url' => route('penjualan.do.create', ['so_id' => $saleOrder->id]),
                'can_action' => true,
            ];
        } elseif ($dos->isNotEmpty() && !$hasSj) {
            $nextStep = [
                'title' => 'Terbitkan Surat Jalan',
                'description' => 'DO sedang diproses oleh gudang. Buat Surat Jalan setelah pengiriman siap.',
                'action_label' => 'Buat Surat Jalan',
                'action_url' => route('penjualan.sj.index'),
                'can_action' => $dos->whereIn('status', ['sent', 'approved'])->isNotEmpty(),
            ];
        }

        return view('penjualan.so.show', [
            'saleOrder' => $saleOrder,
            'stepper' => $stepper,
            'nextStep' => $nextStep,
            'itemProgress' => $itemProgress,
            'totals' => $totals,
            'canApprove' => $canApprove,
            'approveDenyReason' => $approveDenyReason,
        ]);
    }

    /**
     * Setujui Sales Order (Kasir atau Atasan).
     */
    public function approve(Request $request, SaleOrder $saleOrder): RedirectResponse
    {
        $user = Auth::user();

        // Validasi wewenang via ApprovalControlService
        $check = $this->approvalControlService->canApproveSaleOrder($user, $saleOrder);
        if (!$check['allowed']) {
            return redirect()->back()->with('error', $check['reason'] ?? 'Anda tidak berwenang menyetujui SO ini.');
        }

        try {
            DB::transaction(function () use ($saleOrder, $user, $request) {
                // Jika tipe COD, verifikasi Kasir dengan Saldo Titip Customer
                if ($saleOrder->isCod() && !$saleOrder->isCashierApproved()) {
                    $this->cashierVerificationService->verify(
                        $saleOrder,
                        $request->input('notes'),
                        $request->input('override_reason'),
                        $user
                    );
                }

                $isCashier = $user->hasRole('Kasir');

                $updateData = [
                    'status' => 'approved',
                    'approve_at' => now(),
                    'approve_by' => $user->id,
                ];

                if ($isCashier) {
                    $updateData['cashier_approved_at'] = now();
                    $updateData['cashier_approved_by'] = $user->id;
                }

                $saleOrder->update($updateData);
            });

            return redirect()->back()->with('success', "Sales Order {$saleOrder->so_number} berhasil disetujui.");
        } catch (ValidationException $e) {
            $errMsg = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            return redirect()->back()->with('error', $errMsg);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Gagal menyetujui Sales Order: ' . $e->getMessage());
        }
    }

    /**
     * Tolak Sales Order.
     */
    public function reject(Request $request, SaleOrder $saleOrder): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min' => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $saleOrder->update([
            'status' => 'reject',
            'reject_at' => now(),
            'reject_by' => Auth::id(),
            'notes' => ($saleOrder->notes ? $saleOrder->notes . "\n" : '') . "[Ditolak]: " . $request->input('reason'),
        ]);

        return redirect()->back()->with('warning', "Sales Order {$saleOrder->so_number} telah ditolak dengan alasan: " . $request->input('reason'));
    }
}
