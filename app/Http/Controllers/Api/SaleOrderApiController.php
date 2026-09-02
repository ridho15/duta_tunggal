<?php

namespace App\Http\Controllers\Api;

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
use App\Services\CreditValidationService;
use App\Services\SalesOrderService;
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SaleOrderApiController extends Controller
{
    protected SalesOrderService $salesOrderService;
    protected CreditValidationService $creditValidationService;

    public function __construct(
        SalesOrderService $salesOrderService,
        CreditValidationService $creditValidationService
    ) {
        $this->salesOrderService = $salesOrderService;
        $this->creditValidationService = $creditValidationService;
    }

    /**
     * Get master dependencies for Sales Order Form.
     */
    public function dependencies(Request $request): JsonResponse
    {
        $user = Auth::user();
        $manageType = $user?->manage_type ?? [];
        $canAccessAllCabang = is_array($manageType) && in_array('all', $manageType);

        // Cabang query
        $cabangsQuery = Cabang::query()->orderBy('kode');
        if (!$canAccessAllCabang && $user?->cabang_id) {
            $cabangsQuery->where('id', $user->cabang_id);
        }
        $cabangs = $cabangsQuery->get(['id', 'kode', 'nama', 'alamat']);

        // Currency query
        $currencies = Currency::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'symbol', 'to_rupiah']);

        $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR')
            ?? Currency::query()->orderBy('id')->value('id');

        // Customer query with credit & deposit information
        $customers = Customer::query()
            ->with(['deposit'])
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'perusahaan',
                'nik_npwp',
                'address',
                'telephone',
                'phone',
                'email',
                'tempo_kredit',
                'kredit_limit',
                'tipe_pembayaran',
                'tipe',
            ])
            ->map(function ($cust) {
                $creditSummary = $this->creditValidationService->getCreditSummary($cust);
                return [
                    'id' => $cust->id,
                    'code' => $cust->code,
                    'name' => $cust->name,
                    'perusahaan' => $cust->perusahaan,
                    'nik_npwp' => $cust->nik_npwp,
                    'address' => $cust->address,
                    'telephone' => $cust->telephone,
                    'phone' => $cust->phone,
                    'email' => $cust->email,
                    'tempo_kredit' => $cust->tempo_kredit ?? 0,
                    'kredit_limit' => (float) ($cust->kredit_limit ?? 0),
                    'tipe_pembayaran' => $cust->tipe_pembayaran,
                    'tipe' => $cust->tipe,
                    'deposit_balance' => (float) ($cust->deposit?->remaining_amount ?? 0),
                    'credit_summary' => $creditSummary,
                ];
            });

        // Approved Quotations query for Refer Quotation option
        $approvedQuotations = Quotation::query()
            ->where('status', 'approve')
            ->with(['customer', 'quotationItem.product.uom'])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function ($q) {
                return [
                    'id' => $q->id,
                    'quotation_number' => $q->quotation_number,
                    'customer_id' => $q->customer_id,
                    'customer_name' => $q->customer?->name,
                    'customer_code' => $q->customer?->code,
                    'cabang_id' => $q->cabang_id,
                    'currency_id' => $q->currency_id,
                    'exchange_rate' => (float) ($q->exchange_rate ?? 1.0),
                    'tempo_pembayaran' => $q->tempo_pembayaran ?? 0,
                    'shipped_to' => $q->customer?->address,
                    'total_amount' => (float) ($q->total_amount ?? 0),
                    'notes' => $q->notes,
                    'items' => $q->quotationItem->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'product_sku' => $item->product?->sku,
                            'product_name' => $item->product?->name,
                            'unit' => $item->product?->uom?->abbreviation ?? 'PCS',
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'discount' => (float) ($item->discount ?? 0),
                            'tax_type' => $item->tax_type ?? 'None',
                            'tax' => (float) ($item->tax ?? 0),
                            'notes' => $item->notes ?? '',
                        ];
                    }),
                ];
            });

        // Products query with free inventory stock aggregation
        $freeStockByProduct = InventoryStock::query()
            ->selectRaw('product_id, SUM(qty_available - qty_reserved) as free_stock')
            ->where('qty_available', '>', 0)
            ->groupBy('product_id')
            ->pluck('free_stock', 'product_id');

        $products = Product::withoutGlobalScope('product_cabang')
            ->with('uom')
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'sell_price', 'uom_id'])
            ->map(function ($p) use ($freeStockByProduct) {
                return [
                    'id' => $p->id,
                    'sku' => $p->sku,
                    'name' => $p->name,
                    'sell_price' => (float) $p->sell_price,
                    'free_stock' => (float) ($freeStockByProduct[$p->id] ?? 0),
                    'uom' => $p->uom ? [
                        'id' => $p->uom->id,
                        'name' => $p->uom->name,
                        'abbreviation' => $p->uom->abbreviation,
                    ] : null,
                ];
            });

        $taxTypes = [
            ['value' => 'None', 'label' => 'None (0%)', 'rate' => 0],
            ['value' => 'Inklusif', 'label' => 'PPN Inklusif (11%)', 'rate' => 11],
            ['value' => 'Eksklusif', 'label' => 'PPN Eksklusif (11%)', 'rate' => 11],
        ];

        $nextSoNumber = $this->salesOrderService->generateSoNumber();

        return response()->json([
            'success' => true,
            'data' => [
                'next_so_number' => $nextSoNumber,
                'default_order_date' => now()->format('Y-m-d'),
                'default_delivery_date' => now()->addDays(7)->format('Y-m-d'),
                'default_currency_id' => $defaultCurrencyId,
                'default_cabang_id' => $canAccessAllCabang ? null : $user?->cabang_id,
                'can_access_all_cabang' => $canAccessAllCabang,
                'cabangs' => $cabangs,
                'currencies' => $currencies,
                'customers' => $customers,
                'approved_quotations' => $approvedQuotations,
                'products' => $products,
                'tax_types' => $taxTypes,
                'user' => [
                    'id' => $user?->id,
                    'name' => $user?->name,
                    'cabang_id' => $user?->cabang_id,
                ],
            ],
        ]);
    }

    /**
     * Generate fresh SO Number.
     */
    public function generateNumber(): JsonResponse
    {
        $soNumber = $this->salesOrderService->generateSoNumber();
        return response()->json([
            'success' => true,
            'data' => [
                'so_number' => $soNumber,
            ],
        ]);
    }

    /**
     * Get single Quotation details for Refer Quotation mode.
     */
    public function getQuotation(int $id): JsonResponse
    {
        $quotation = Quotation::query()
            ->with(['customer', 'quotationItem.product.uom'])
            ->find($id);

        if (!$quotation) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'customer_id' => $quotation->customer_id,
                'cabang_id' => $quotation->cabang_id,
                'currency_id' => $quotation->currency_id,
                'exchange_rate' => (float) ($quotation->exchange_rate ?? 1.0),
                'tempo_pembayaran' => $quotation->tempo_pembayaran ?? 0,
                'shipped_to' => $quotation->customer?->address,
                'total_amount' => (float) ($quotation->total_amount ?? 0),
                'notes' => $quotation->notes,
                'items' => $quotation->quotationItem->map(function ($item) {
                    return [
                        'product_id' => $item->product_id,
                        'product_sku' => $item->product?->sku,
                        'product_name' => $item->product?->name,
                        'unit' => $item->product?->uom?->abbreviation ?? 'PCS',
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'discount' => (float) ($item->discount ?? 0),
                        'tax_type' => $item->tax_type ?? 'None',
                        'tax' => (float) ($item->tax ?? 0),
                        'notes' => $item->notes ?? '',
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get customer credit validation details.
     */
    public function getCustomerCredit(int $id): JsonResponse
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer tidak ditemukan.',
            ], 404);
        }

        $creditSummary = $this->creditValidationService->getCreditSummary($customer);

        return response()->json([
            'success' => true,
            'data' => $creditSummary,
        ]);
    }

    /**
     * Store new Sales Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'header.so_number' => 'required|string|max:255|unique:sale_orders,so_number',
            'header.customer_id' => 'required|exists:customers,id',
            'header.cabang_id' => 'required|exists:cabangs,id',
            'header.order_date' => 'required|date',
            'header.delivery_date' => 'nullable|date',
            'header.tipe_pengiriman' => 'required|in:Ambil Sendiri,Kirim Langsung',
            'header.shipped_to' => 'nullable|string|max:255',
            'header.currency_id' => 'required|exists:currencies,id',
            'header.exchange_rate' => 'nullable|numeric|min:0',
            'header.tempo_pembayaran' => 'nullable|integer|min:0',
            'header.quotation_id' => 'nullable|exists:quotations,id',
            'header.notes' => 'nullable|string',
            'header.status' => 'nullable|string|in:draft,request_approve,approved,canceled,reject',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ], [
            'header.so_number.required' => 'Nomor SO wajib diisi.',
            'header.so_number.unique' => 'Nomor SO sudah digunakan.',
            'header.customer_id.required' => 'Customer wajib dipilih.',
            'header.cabang_id.required' => 'Cabang wajib dipilih.',
            'header.order_date.required' => 'Tanggal order wajib diisi.',
            'header.tipe_pengiriman.required' => 'Tipe pengiriman wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 item.',
            'items.min' => 'Minimal harus ada 1 item.',
            'items.*.product_id.required' => 'Produk pada setiap baris wajib dipilih.',
            'items.*.quantity.required' => 'Kuantitas wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        $headerData = $validated['header'];
        $itemsData = $validated['items'];

        $customer = Customer::find($headerData['customer_id']);
        $currencyId = (int) $headerData['currency_id'];
        $currency = Currency::find($currencyId);
        $exchangeRate = $headerData['exchange_rate'] ?? ($currency?->to_rupiah ?? 1.0);

        // Precalculate estimated total for credit validation check
        $estimatedTotalIdr = 0;
        foreach ($itemsData as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);
            $taxRate = (float) ($item['tax'] ?? 0);
            $taxType = $item['tax_type'] ?? 'None';

            $subtotal = HelperController::hitungSubtotal($qty, $price, $disc, $taxRate, $taxType);
            $subtotalIdr = CurrencyConversionResolver::convertToIdr(
                MoneyHelper::parseHighPrecision($subtotal),
                $currencyId,
                false
            );
            $estimatedTotalIdr += $subtotalIdr;
        }

        // Validate Customer Credit Limit
        if ($customer && $customer->tipe_pembayaran === 'Kredit') {
            $validation = $this->creditValidationService->canCustomerMakePurchase($customer, (float) $estimatedTotalIdr);
            if (!$validation['can_purchase']) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $validation['messages']),
                    'errors' => [
                        'header.customer_id' => $validation['messages'],
                    ],
                ], 422);
            }
        }

        try {
            $saleOrder = DB::transaction(function () use ($headerData, $itemsData, $exchangeRate, $currencyId) {
                $so = SaleOrder::create([
                    'so_number' => $headerData['so_number'],
                    'customer_id' => $headerData['customer_id'],
                    'cabang_id' => $headerData['cabang_id'],
                    'quotation_id' => $headerData['quotation_id'] ?? null,
                    'order_date' => $headerData['order_date'],
                    'delivery_date' => $headerData['delivery_date'] ?? null,
                    'tipe_pengiriman' => $headerData['tipe_pengiriman'],
                    'shipped_to' => $headerData['shipped_to'] ?? null,
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate,
                    'tempo_pembayaran' => $headerData['tempo_pembayaran'] ?? 0,
                    'status' => $headerData['status'] ?? 'draft',
                    'created_by' => Auth::id(),
                    'total_amount' => 0,
                ]);

                foreach ($itemsData as $item) {
                    $taxType = $item['tax_type'] ?? 'None';
                    $taxRate = (float) ($item['tax'] ?? 0);
                    if ($taxType === 'None') {
                        $taxRate = 0;
                    }

                    SaleOrderItem::create([
                        'sale_order_id' => $so->id,
                        'product_id' => $item['product_id'],
                        'quantity' => (float) $item['quantity'],
                        'delivered_quantity' => 0,
                        'unit_price' => (float) $item['unit_price'],
                        'discount' => (float) ($item['discount'] ?? 0),
                        'tax' => $taxRate,
                        'tipe_pajak' => $taxType,
                        'currency_id' => $currencyId,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                $this->salesOrderService->updateTotalAmount($so);

                return $so;
            });

            return response()->json([
                'success' => true,
                'message' => 'Sales Order berhasil dibuat.',
                'data' => [
                    'id' => $saleOrder->id,
                    'so_number' => $saleOrder->so_number,
                    'redirect_url' => route('filament.admin.resources.sale-orders.index'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating sales order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat Sales Order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single Sales Order for edit view.
     */
    public function show(int $id): JsonResponse
    {
        $saleOrder = SaleOrder::with([
            'customer',
            'quotation',
            'currency',
            'saleOrderItem.product.uom',
        ])->find($id);

        if (!$saleOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales Order tidak ditemukan.',
            ], 404);
        }

        $items = $saleOrder->saleOrderItem->map(function ($item) {
            return [
                'id' => $item->id,
                'row_id' => 'row_' . $item->id,
                'product_id' => $item->product_id,
                'product_sku' => $item->product?->sku,
                'product_name' => $item->product?->name,
                'unit' => $item->product?->uom?->abbreviation ?? 'PCS',
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) ($item->discount ?? 0),
                'tax_type' => $item->tipe_pajak ?? 'None',
                'tax' => (float) ($item->tax ?? 0),
                'notes' => $item->notes ?? '',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'header' => [
                    'id' => $saleOrder->id,
                    'so_number' => $saleOrder->so_number,
                    'customer_id' => $saleOrder->customer_id,
                    'cabang_id' => $saleOrder->cabang_id,
                    'quotation_id' => $saleOrder->quotation_id,
                    'order_date' => $saleOrder->order_date ? $saleOrder->order_date->format('Y-m-d') : null,
                    'delivery_date' => $saleOrder->delivery_date ? $saleOrder->delivery_date->format('Y-m-d') : null,
                    'tipe_pengiriman' => $saleOrder->tipe_pengiriman ?? 'Kirim Langsung',
                    'shipped_to' => $saleOrder->shipped_to,
                    'currency_id' => $saleOrder->currency_id,
                    'exchange_rate' => (float) ($saleOrder->exchange_rate ?? 1.0),
                    'tempo_pembayaran' => $saleOrder->tempo_pembayaran ?? 0,
                    'status' => $saleOrder->status,
                    'total_amount' => (float) ($saleOrder->total_amount ?? 0),
                ],
                'items' => $items,
            ],
        ]);
    }

    /**
     * Update existing Sales Order.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $saleOrder = SaleOrder::find($id);
        if (!$saleOrder) {
            return response()->json([
                'success' => false,
                'message' => 'Sales Order tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'header.so_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sale_orders', 'so_number')->ignore($saleOrder->id),
            ],
            'header.customer_id' => 'required|exists:customers,id',
            'header.cabang_id' => 'required|exists:cabangs,id',
            'header.order_date' => 'required|date',
            'header.delivery_date' => 'nullable|date',
            'header.tipe_pengiriman' => 'required|in:Ambil Sendiri,Kirim Langsung',
            'header.shipped_to' => 'nullable|string|max:255',
            'header.currency_id' => 'required|exists:currencies,id',
            'header.exchange_rate' => 'nullable|numeric|min:0',
            'header.tempo_pembayaran' => 'nullable|integer|min:0',
            'header.quotation_id' => 'nullable|exists:quotations,id',
            'header.status' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        $headerData = $validated['header'];
        $itemsData = $validated['items'];

        $customer = Customer::find($headerData['customer_id']);
        $currencyId = (int) $headerData['currency_id'];
        $currency = Currency::find($currencyId);
        $exchangeRate = $headerData['exchange_rate'] ?? ($currency?->to_rupiah ?? 1.0);

        // Precalculate estimated total for credit validation check
        $estimatedTotalIdr = 0;
        foreach ($itemsData as $item) {
            $qty = (float) $item['quantity'];
            $price = (float) $item['unit_price'];
            $disc = (float) ($item['discount'] ?? 0);
            $taxRate = (float) ($item['tax'] ?? 0);
            $taxType = $item['tax_type'] ?? 'None';

            $subtotal = HelperController::hitungSubtotal($qty, $price, $disc, $taxRate, $taxType);
            $subtotalIdr = CurrencyConversionResolver::convertToIdr(
                MoneyHelper::parseHighPrecision($subtotal),
                $currencyId,
                false
            );
            $estimatedTotalIdr += $subtotalIdr;
        }

        // Validate Customer Credit Limit
        if ($customer && $customer->tipe_pembayaran === 'Kredit') {
            $validation = $this->creditValidationService->canCustomerMakePurchase($customer, (float) $estimatedTotalIdr);
            if (!$validation['can_purchase']) {
                return response()->json([
                    'success' => false,
                    'message' => implode(' ', $validation['messages']),
                    'errors' => [
                        'header.customer_id' => $validation['messages'],
                    ],
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($saleOrder, $headerData, $itemsData, $exchangeRate, $currencyId) {
                $saleOrder->update([
                    'so_number' => $headerData['so_number'],
                    'customer_id' => $headerData['customer_id'],
                    'cabang_id' => $headerData['cabang_id'],
                    'quotation_id' => $headerData['quotation_id'] ?? null,
                    'order_date' => $headerData['order_date'],
                    'delivery_date' => $headerData['delivery_date'] ?? null,
                    'tipe_pengiriman' => $headerData['tipe_pengiriman'],
                    'shipped_to' => $headerData['shipped_to'] ?? null,
                    'currency_id' => $currencyId,
                    'exchange_rate' => $exchangeRate,
                    'tempo_pembayaran' => $headerData['tempo_pembayaran'] ?? 0,
                    'status' => $headerData['status'] ?? $saleOrder->status,
                ]);

                // Sync items
                SaleOrderItem::where('sale_order_id', $saleOrder->id)->delete();

                foreach ($itemsData as $item) {
                    $taxType = $item['tax_type'] ?? 'None';
                    $taxRate = (float) ($item['tax'] ?? 0);
                    if ($taxType === 'None') {
                        $taxRate = 0;
                    }

                    SaleOrderItem::create([
                        'sale_order_id' => $saleOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => (float) $item['quantity'],
                        'delivered_quantity' => 0,
                        'unit_price' => (float) $item['unit_price'],
                        'discount' => (float) ($item['discount'] ?? 0),
                        'tax' => $taxRate,
                        'tipe_pajak' => $taxType,
                        'currency_id' => $currencyId,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                $this->salesOrderService->updateTotalAmount($saleOrder);
            });

            return response()->json([
                'success' => true,
                'message' => 'Sales Order berhasil diperbarui.',
                'data' => [
                    'id' => $saleOrder->id,
                    'so_number' => $saleOrder->so_number,
                    'redirect_url' => route('filament.admin.resources.sale-orders.index'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating sales order: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Sales Order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
