<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use App\Support\TaxDefaultResolver;
use App\Support\TaxTypeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PurchaseOrderApiController extends Controller
{
    /**
     * Get all dependencies needed to populate the Purchase Order form.
     */
    public function dependencies(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $manageType = $user?->manage_type ?? [];
            $canAccessAllCabang = is_array($manageType) && in_array('all', $manageType);

            // 1. Fetch Cabangs
            $cabangsQuery = Cabang::query()->orderBy('kode');
            if (! $canAccessAllCabang && $user?->cabang_id) {
                $cabangsQuery->where('id', $user->cabang_id);
            }
            $cabangs = $cabangsQuery->get(['id', 'kode', 'nama', 'alamat']);

            // 2. Fetch Currencies
            $currencies = Currency::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'symbol', 'to_rupiah']);

            $defaultCurrencyId = CurrencyConversionResolver::resolveCurrencyIdByCode('IDR') ?? $currencies->first()?->id ?? 1;

            // 3. Fetch Suppliers
            $suppliers = Supplier::query()
                ->orderBy('perusahaan')
                ->get(['id', 'code', 'perusahaan', 'kontak_person', 'phone', 'cabang_id', 'tempo_hutang']);

            // 4. Fetch Products with UOM and Supplier pivots
            $products = Product::withoutGlobalScope('product_cabang')
                ->with([
                    'uom:id,name,abbreviation',
                    'suppliers' => function ($query) {
                        $query->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')
                            ->withPivot('supplier_price');
                    },
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id'])
                ->map(function (Product $product) {
                    $defaultTaxRate = TaxDefaultResolver::resolveForProductId((int) $product->id, 'PPN Excluded');

                    // Recommended supplier with lowest price
                    $recommendedSupplier = $product->suppliers
                        ->sortBy(fn ($s) => (float) ($s->pivot->supplier_price ?? PHP_FLOAT_MAX))
                        ->first();

                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'cost_price' => (float) ($product->cost_price ?? 0),
                        'uom_id' => $product->uom_id,
                        'uom' => $product->uom?->abbreviation ?? $product->uom?->name ?? 'PCS',
                        'cabang_id' => $product->cabang_id,
                        'default_tax_rate' => (float) $defaultTaxRate,
                        'suppliers' => $product->suppliers->map(function ($s) {
                            return [
                                'id' => $s->id,
                                'code' => $s->code,
                                'perusahaan' => $s->perusahaan,
                                'supplier_price' => $s->pivot->supplier_price !== null ? (float) $s->pivot->supplier_price : null,
                            ];
                        })->values(),
                        'recommended_supplier' => $recommendedSupplier ? [
                            'id' => $recommendedSupplier->id,
                            'code' => $recommendedSupplier->code,
                            'perusahaan' => $recommendedSupplier->perusahaan,
                            'price' => (float) ($recommendedSupplier->pivot->supplier_price ?? $product->cost_price ?? 0),
                        ] : null,
                    ];
                });

            // 5. Fetch Available Order Requests with remaining approved items for PO
            $orderRequests = OrderRequest::query()
                ->whereIn('status', ['approved', 'partial', 'approve'])
                ->with(['orderRequestItem.product', 'orderRequestItem.supplier'])
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(function (OrderRequest $or) {
                    $remainingCount = 0;
                    $supplierIds = [];
                    $supplierDetails = [];
                    $cabangId = null;

                    foreach ($or->orderRequestItem as $item) {
                        $isApproved = OrderRequestItem::normalizeApprovalStatus($item->status ?? null) === OrderRequestItem::STATUS_APPROVED;
                        if (! $isApproved) {
                            continue;
                        }

                        $lock = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id);
                        if (($lock['remaining_for_po'] ?? 0) > 0) {
                            $remainingCount++;
                            if ($item->supplier_id) {
                                $supplierIds[] = (int) $item->supplier_id;
                                if (! isset($supplierDetails[$item->supplier_id]) && $item->supplier) {
                                    $supplierDetails[$item->supplier_id] = [
                                        'id' => $item->supplier->id,
                                        'code' => $item->supplier->code,
                                        'perusahaan' => $item->supplier->perusahaan,
                                        'tempo_hutang' => (int) ($item->supplier->tempo_hutang ?? 0),
                                    ];
                                }
                            }
                            if ($item->cabang_id) {
                                $cabangId = (int) $item->cabang_id;
                            }
                        }
                    }

                    return [
                        'id' => $or->id,
                        'request_number' => $or->request_number,
                        'request_date' => $or->request_date ? (string) $or->request_date : '',
                        'currency_id' => (int) ($or->currency_id ?? 1),
                        'total_items' => $or->orderRequestItem->count(),
                        'remaining_items' => $remainingCount,
                        'cabang_id' => $cabangId,
                        'supplier_ids' => array_values(array_unique($supplierIds)),
                        'suppliers' => array_values($supplierDetails),
                    ];
                })
                ->filter(fn ($or) => $or['remaining_items'] > 0)
                ->values();

            // 6. Fetch Available Sales Orders
            $salesOrders = SaleOrder::query()
                ->whereIn('status', ['approved', 'confirmed', 'draft'])
                ->with(['customer:id,code,name', 'saleOrderItem'])
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(function (SaleOrder $so) {
                    return [
                        'id' => $so->id,
                        'so_number' => $so->so_number,
                        'order_date' => $so->order_date ? $so->order_date->format('Y-m-d') : '',
                        'customer_name' => $so->customer?->name ?? '-',
                        'customer_code' => $so->customer?->code ?? '-',
                        'cabang_id' => (int) ($so->cabang_id ?? 0),
                        'currency_id' => (int) ($so->currency_id ?? 1),
                        'total_items' => $so->saleOrderItem->count(),
                    ];
                });

            // 7. Generate fresh PO Number & default dates
            $nextPoNumber = HelperController::generatePoNumber();
            $defaultOrderDate = now()->format('Y-m-d');
            $defaultExpectedDate = now()->addDays(7)->format('Y-m-d');

            return response()->json([
                'success' => true,
                'data' => [
                    'next_po_number' => $nextPoNumber,
                    'default_order_date' => $defaultOrderDate,
                    'default_expected_date' => $defaultExpectedDate,
                    'default_currency_id' => $defaultCurrencyId,
                    'default_cabang_id' => $user?->cabang_id,
                    'cabangs' => $cabangs,
                    'currencies' => $currencies,
                    'suppliers' => $suppliers,
                    'products' => $products,
                    'tax_types' => [
                        ['value' => 'eklusif', 'label' => 'PPN Excluded (Eksklusif)'],
                        ['value' => 'inklusif', 'label' => 'PPN Included (Inklusif)'],
                        ['value' => 'none', 'label' => 'Non PPN (0%)'],
                    ],
                    'top_types' => [
                        ['value' => 'cod', 'label' => 'COD (Cash On Delivery)'],
                        ['value' => 'advance_before_delivery', 'label' => 'Advance Before Delivery'],
                        ['value' => 'deposit_balance', 'label' => 'Deposit + Balance'],
                        ['value' => 'credit_days', 'label' => 'Credit (Tempo Hari)'],
                    ],
                    'available_order_requests' => $orderRequests,
                    'available_sales_orders' => $salesOrders,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('PurchaseOrderApiController dependencies error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data dependencies Purchase Order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reference items when an Order Request or Sales Order is selected.
     */
    public function referenceItems(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type'); // 'App\Models\OrderRequest' or 'App\Models\SaleOrder' or simple 'OrderRequest' / 'SaleOrder'
            $id = (int) $request->query('id');
            $supplierFilter = $request->query('supplier_id') ? (int) $request->query('supplier_id') : null;

            if (! $id) {
                return response()->json(['success' => true, 'items' => []]);
            }

            $items = [];
            $headerDefaults = [];

            if ($type === 'App\Models\OrderRequest' || $type === 'OrderRequest') {
                $orderRequest = OrderRequest::with([
                    'orderRequestItem.product.uom',
                    'orderRequestItem.supplier',
                    'orderRequestItem.product.suppliers' => function ($q) {
                        $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')->withPivot('supplier_price');
                    },
                ])->find($id);

                if ($orderRequest) {
                    $matchedSupplier = $supplierFilter ? Supplier::find($supplierFilter) : null;
                    if (! $matchedSupplier) {
                        $firstEligibleItem = $orderRequest->orderRequestItem
                            ->filter(fn ($item) => OrderRequestItem::normalizeApprovalStatus($item->status ?? null) === OrderRequestItem::STATUS_APPROVED)
                            ->first();
                        if ($firstEligibleItem?->supplier) {
                            $matchedSupplier = $firstEligibleItem->supplier;
                        }
                    }

                    $headerDefaults = [
                        'cabang_id' => $orderRequest->orderRequestItem->first()?->cabang_id,
                        'currency_id' => $orderRequest->currency_id ?? 1,
                        'note' => $orderRequest->note ?? '',
                        'supplier_id' => $matchedSupplier?->id ?? null,
                        'tempo_hutang' => (int) ($matchedSupplier?->tempo_hutang ?? 0),
                        'top_type' => ($matchedSupplier && ($matchedSupplier->tempo_hutang ?? 0) > 0) ? 'credit_days' : 'cod',
                    ];

                    foreach ($orderRequest->orderRequestItem as $item) {
                        // Only approved items
                        if (OrderRequestItem::normalizeApprovalStatus($item->status ?? null) !== OrderRequestItem::STATUS_APPROVED) {
                            continue;
                        }

                        // Check remaining quota for PO
                        $limit = OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id);
                        $remaining = (float) ($limit['remaining_for_po'] ?? $item->quantity);

                        if ($remaining <= 0) {
                            continue;
                        }

                        // If supplier filter provided, match supplier
                        if ($supplierFilter && $item->supplier_id && (int) $item->supplier_id !== $supplierFilter) {
                            continue;
                        }

                        $product = $item->product;
                        $taxType = TaxTypeHelper::normalize($item->tipe_pajak);
                        $unitPrice = (float) ($item->unit_price ?? $product?->cost_price ?? 0);

                        $items[] = [
                            'product_id' => $item->product_id,
                            'unit' => $product?->uom?->abbreviation ?? $product?->uom?->name ?? 'PCS',
                            'quantity' => $remaining,
                            'max_quantity' => $remaining,
                            'cabang_id' => $item->cabang_id,
                            'supplier_id' => $item->supplier_id,
                            'currency_id' => (int) ($item->currency_id ?? $orderRequest->currency_id ?? 1),
                            'unit_price' => $unitPrice,
                            'discount' => (float) ($item->discount ?? 0),
                            'tipe_pajak' => $taxType,
                            'tax' => (float) ($item->tax ?? 11),
                            'note' => $item->note ?? '',
                            'refer_item_model_type' => OrderRequestItem::class,
                            'refer_item_model_id' => $item->id,
                        ];
                    }
                }
            } elseif ($type === 'App\Models\SaleOrder' || $type === 'SaleOrder') {
                $saleOrder = SaleOrder::with([
                    'saleOrderItem.product.uom',
                    'saleOrderItem.product.suppliers' => function ($q) {
                        $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')->withPivot('supplier_price');
                    },
                ])->find($id);

                if ($saleOrder) {
                    $headerDefaults = [
                        'cabang_id' => $saleOrder->cabang_id,
                        'currency_id' => $saleOrder->currency_id ?? 1,
                    ];

                    foreach ($saleOrder->saleOrderItem as $soItem) {
                        $product = $soItem->product;
                        $unitPrice = (float) ($product?->cost_price ?? 0);
                        if ($supplierFilter && $product) {
                            $sp = $product->suppliers->firstWhere('id', $supplierFilter);
                            if ($sp && $sp->pivot->supplier_price !== null) {
                                $unitPrice = (float) $sp->pivot->supplier_price;
                            }
                        }

                        $items[] = [
                            'product_id' => $soItem->product_id,
                            'unit' => $product?->uom?->abbreviation ?? $product?->uom?->name ?? 'PCS',
                            'quantity' => (float) $soItem->quantity,
                            'cabang_id' => $saleOrder->cabang_id,
                            'supplier_id' => $supplierFilter,
                            'currency_id' => (int) ($saleOrder->currency_id ?? 1),
                            'unit_price' => $unitPrice,
                            'discount' => 0,
                            'tipe_pajak' => 'eklusif',
                            'tax' => 11,
                            'note' => '',
                            'refer_item_model_type' => null,
                            'refer_item_model_id' => null,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'header_defaults' => $headerDefaults,
                'items' => $items,
            ]);
        } catch (Throwable $e) {
            Log::error('PurchaseOrderApiController referenceItems error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil item referensi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate fresh PO Number.
     */
    public function generateNumber(): JsonResponse
    {
        try {
            $number = HelperController::generatePoNumber();

            return response()->json([
                'success' => true,
                'po_number' => $number,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate nomor PO: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store new Purchase Order.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'header.po_number' => 'required|string|max:255|unique:purchase_orders,po_number',
            'header.supplier_id' => 'required|integer|exists:suppliers,id',
            'header.cabang_id' => 'nullable|integer|exists:cabangs,id',
            'header.order_date' => 'required|date',
            'header.expected_date' => 'nullable|date',
            'header.top_type' => 'nullable|string',
            'header.tempo_hutang' => 'nullable|integer|min:0',
            'header.is_asset' => 'nullable|boolean',
            'header.is_import' => 'nullable|boolean',
            'header.refer_model_type' => 'nullable|string',
            'header.refer_model_id' => 'nullable|integer',
            'header.note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'required|string|in:none,eklusif,inklusif',
            'items.*.currency_id' => 'nullable|integer|exists:currencies,id',
        ], [
            'header.po_number.required' => 'Nomor PO tidak boleh kosong',
            'header.po_number.unique' => 'Nomor PO sudah digunakan',
            'header.supplier_id.required' => 'Supplier wajib dipilih',
            'header.order_date.required' => 'Tanggal PO wajib diisi',
            'items.required' => 'Minimal harus menambahkan 1 item barang',
            'items.min' => 'Minimal harus menambahkan 1 item barang',
            'items.*.product_id.required' => 'Produk pada baris item wajib dipilih',
            'items.*.quantity.min' => 'Kuantitas minimal 0.0001',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Silakan periksa kolom yang ditandai.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $headerData = $request->input('header');
        $itemsData = $request->input('items', []);

        // Validate item quantities against Order Request limits
        foreach ($itemsData as $idx => $item) {
            if (! empty($item['refer_item_model_id'])) {
                $limit = OrderRequestQuantityLock::orderRequestItemLimit((int) $item['refer_item_model_id']);
                $maxAllowed = (float) ($limit['remaining_for_po'] ?? 0);
                $qty = (float) ($item['quantity'] ?? 0);
                if ($qty > $maxAllowed + 0.0001) {
                    return response()->json([
                        'success' => false,
                        'message' => "Kuantitas baris #" . ($idx + 1) . " ({$qty}) melebihi sisa kuota Order Request ({$maxAllowed}).",
                        'errors' => [
                            "items.{$idx}.quantity" => ["Kuantitas tidak boleh melebihi sisa Order Request ({$maxAllowed})."],
                        ],
                    ], 422);
                }
            }
        }

        // Determine status: Auto-approve if created from approved Order Request (matching legacy Filament logic)
        $status = 'draft';
        $dateApproved = null;
        $approvedBy = null;

        $referModelType = ! empty($headerData['refer_model_type']) ? $headerData['refer_model_type'] : null;
        $referModelId = ! empty($headerData['refer_model_id']) ? (int) $headerData['refer_model_id'] : null;

        if ($referModelType && in_array($referModelType, [OrderRequest::class, 'OrderRequest', 'App\\Models\\OrderRequest']) && $referModelId) {
            $referOr = OrderRequest::find($referModelId);
            if ($referOr && in_array($referOr->status, ['approved', 'partial', 'approve'])) {
                $status = 'approved';
                $dateApproved = now()->toDateString();
                $approvedBy = Auth::id();
            }
        }

        DB::beginTransaction();
        try {
            // 1. Create Header
            $po = PurchaseOrder::create([
                'po_number' => $headerData['po_number'],
                'supplier_id' => (int) $headerData['supplier_id'],
                'cabang_id' => ! empty($headerData['cabang_id']) ? (int) $headerData['cabang_id'] : Auth::user()?->cabang_id,
                'order_date' => $headerData['order_date'],
                'expected_date' => $headerData['expected_date'] ?? null,
                'status' => $status,
                'date_approved' => $dateApproved,
                'approved_by' => $approvedBy,
                'top_type' => $headerData['top_type'] ?? 'cod',
                'tempo_hutang' => (int) ($headerData['tempo_hutang'] ?? 0),
                'is_asset' => (bool) ($headerData['is_asset'] ?? false),
                'is_import' => (bool) ($headerData['is_import'] ?? false),
                'refer_model_type' => $referModelType === 'OrderRequest' ? OrderRequest::class : ($referModelType === 'SaleOrder' ? SaleOrder::class : $referModelType),
                'refer_model_id' => $referModelId,
                'note' => $headerData['note'] ?? null,
                'created_by' => Auth::id(),
                'total_amount' => 0,
            ]);

            // 2. Create Items
            $currencyMap = [];
            foreach ($itemsData as $item) {
                $taxType = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'eklusif');
                $currencyId = ! empty($item['currency_id']) ? (int) $item['currency_id'] : 1;
                $currencyMap[$currencyId] = true;

                $poItem = PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 11),
                    'tipe_pajak' => $taxType,
                    'currency_id' => $currencyId,
                    'refer_item_model_type' => ! empty($item['refer_item_model_type']) ? $item['refer_item_model_type'] : null,
                    'refer_item_model_id' => ! empty($item['refer_item_model_id']) ? (int) $item['refer_item_model_id'] : null,
                ]);
            }

            // 3. Create PurchaseOrderCurrency for each distinct currency
            foreach (array_keys($currencyMap) as $currId) {
                $curr = Currency::find($currId);
                $rate = (float) ($curr?->to_rupiah ?? 1);
                PurchaseOrderCurrency::create([
                    'purchase_order_id' => $po->id,
                    'currency_id' => $currId,
                    'to_rupiah' => $rate,
                ]);
            }

            // 4. Update Total Amount using PurchaseOrderService
            $purchaseOrderService = app(PurchaseOrderService::class);
            $purchaseOrderService->updateTotalAmount($po);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Purchase Order {$po->po_number} berhasil dibuat.",
                'data' => [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'redirect_url' => "/admin/purchase-orders/{$po->id}",
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('PurchaseOrderApiController store error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan Purchase Order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show PO data for Edit mode.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $po = PurchaseOrder::with([
                'purchaseOrderItem.product.uom',
                'purchaseOrderItem.product.suppliers' => function ($q) {
                    $q->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')->withPivot('supplier_price');
                },
                'supplier',
                'cabang',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'supplier_id' => $po->supplier_id,
                    'cabang_id' => $po->cabang_id,
                    'order_date' => $po->order_date ? $po->order_date->format('Y-m-d') : '',
                    'expected_date' => $po->expected_date ? $po->expected_date->format('Y-m-d') : '',
                    'status' => $po->status,
                    'top_type' => $po->top_type ?? 'cod',
                    'tempo_hutang' => (int) ($po->tempo_hutang ?? 0),
                    'is_asset' => (bool) $po->is_asset,
                    'is_import' => (bool) $po->is_import,
                    'refer_model_type' => $po->refer_model_type,
                    'refer_model_id' => $po->refer_model_id,
                    'note' => $po->note ?? '',
                    'total_amount' => (float) $po->total_amount,
                    'purchase_order_item' => $po->purchaseOrderItem->map(function (PurchaseOrderItem $item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product' => [
                                'id' => $item->product?->id,
                                'name' => $item->product?->name,
                                'sku' => $item->product?->sku,
                                'uom' => [
                                    'abbreviation' => $item->product?->uom?->abbreviation,
                                    'name' => $item->product?->uom?->name,
                                ],
                            ],
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'discount' => (float) ($item->discount ?? 0),
                            'tax' => (float) ($item->tax ?? 11),
                            'tipe_pajak' => TaxTypeHelper::normalize($item->tipe_pajak),
                            'currency_id' => (int) ($item->currency_id ?? 1),
                            'refer_item_model_type' => $item->refer_item_model_type,
                            'refer_item_model_id' => $item->refer_item_model_id,
                        ];
                    }),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data PO: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update existing Purchase Order.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'header.po_number' => "required|string|max:255|unique:purchase_orders,po_number,{$id}",
            'header.supplier_id' => 'required|integer|exists:suppliers,id',
            'header.cabang_id' => 'nullable|integer|exists:cabangs,id',
            'header.order_date' => 'required|date',
            'header.expected_date' => 'nullable|date',
            'header.top_type' => 'nullable|string',
            'header.tempo_hutang' => 'nullable|integer|min:0',
            'header.is_asset' => 'nullable|boolean',
            'header.is_import' => 'nullable|boolean',
            'header.note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'required|string|in:none,eklusif,inklusif',
            'items.*.currency_id' => 'nullable|integer|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Silakan periksa kolom yang ditandai.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $headerData = $request->input('header');
        $itemsData = $request->input('items', []);

        // Validate item quantities against Order Request limits
        foreach ($itemsData as $idx => $item) {
            if (! empty($item['refer_item_model_id'])) {
                $currentPoItemId = ! empty($item['id']) ? (int) $item['id'] : null;
                $limit = OrderRequestQuantityLock::orderRequestItemLimit((int) $item['refer_item_model_id'], $currentPoItemId);
                $maxAllowed = (float) ($limit['remaining_for_po'] ?? 0);
                $qty = (float) ($item['quantity'] ?? 0);
                if ($qty > $maxAllowed + 0.0001) {
                    return response()->json([
                        'success' => false,
                        'message' => "Kuantitas baris #" . ($idx + 1) . " ({$qty}) melebihi sisa kuota Order Request ({$maxAllowed}).",
                        'errors' => [
                            "items.{$idx}.quantity" => ["Kuantitas tidak boleh melebihi sisa Order Request ({$maxAllowed})."],
                        ],
                    ], 422);
                }
            }
        }

        DB::beginTransaction();
        try {
            // 1. Update Header
            $po->update([
                'po_number' => $headerData['po_number'],
                'supplier_id' => (int) $headerData['supplier_id'],
                'cabang_id' => ! empty($headerData['cabang_id']) ? (int) $headerData['cabang_id'] : $po->cabang_id,
                'order_date' => $headerData['order_date'],
                'expected_date' => $headerData['expected_date'] ?? null,
                'top_type' => $headerData['top_type'] ?? $po->top_type,
                'tempo_hutang' => (int) ($headerData['tempo_hutang'] ?? $po->tempo_hutang),
                'is_asset' => (bool) ($headerData['is_asset'] ?? false),
                'is_import' => (bool) ($headerData['is_import'] ?? false),
                'note' => $headerData['note'] ?? null,
            ]);

            // 2. Sync Items: delete removed, update existing, insert new
            $existingItemIds = $po->purchaseOrderItem()->pluck('id')->toArray();
            $incomingItemIds = [];
            $currencyMap = [];

            foreach ($itemsData as $item) {
                $currencyId = ! empty($item['currency_id']) ? (int) $item['currency_id'] : 1;
                $currencyMap[$currencyId] = true;
                $taxType = TaxTypeHelper::normalize($item['tipe_pajak'] ?? 'eklusif');

                $payload = [
                    'purchase_order_id' => $po->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (float) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'discount' => (float) ($item['discount'] ?? 0),
                    'tax' => (float) ($item['tax'] ?? 11),
                    'tipe_pajak' => $taxType,
                    'currency_id' => $currencyId,
                    'refer_item_model_type' => ! empty($item['refer_item_model_type']) ? $item['refer_item_model_type'] : null,
                    'refer_item_model_id' => ! empty($item['refer_item_model_id']) ? (int) $item['refer_item_model_id'] : null,
                ];

                if (! empty($item['id']) && in_array($item['id'], $existingItemIds)) {
                    $incomingItemIds[] = (int) $item['id'];
                    PurchaseOrderItem::where('id', $item['id'])->update($payload);
                } else {
                    $newItem = PurchaseOrderItem::create($payload);
                    $incomingItemIds[] = $newItem->id;
                }
            }

            // Remove items not present in request
            $toDelete = array_diff($existingItemIds, $incomingItemIds);
            if (! empty($toDelete)) {
                PurchaseOrderItem::whereIn('id', $toDelete)->delete();
            }

            // 3. Sync Currencies
            PurchaseOrderCurrency::where('purchase_order_id', $po->id)->delete();
            foreach (array_keys($currencyMap) as $currId) {
                $curr = Currency::find($currId);
                $rate = (float) ($curr?->to_rupiah ?? 1);
                PurchaseOrderCurrency::create([
                    'purchase_order_id' => $po->id,
                    'currency_id' => $currId,
                    'to_rupiah' => $rate,
                ]);
            }

            // 4. Update Total Amount
            $purchaseOrderService = app(PurchaseOrderService::class);
            $purchaseOrderService->updateTotalAmount($po);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Purchase Order {$po->po_number} berhasil diperbarui.",
                'data' => [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'redirect_url' => "/admin/purchase-orders/{$po->id}",
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('PurchaseOrderApiController update error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui Purchase Order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
