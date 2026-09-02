<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\CurrencyConversionResolver;
use App\Support\TaxDefaultResolver;
use App\Support\TaxTypeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OrderRequestApiController extends Controller
{
    /**
     * Get raw dependencies array (low memory, no JsonResponse overhead).
     */
    public function getDependenciesData(?Request $request = null): array
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
                ->get(['id', 'code', 'perusahaan', 'kontak_person', 'phone', 'cabang_id']);

            // 4. Resolve default tax rate once before loop (prevents N+1 query memory bloat)
            $activeTaxRate = (float) (\App\Models\TaxSetting::activeRate('PPN') ?? 11.0);

            // 5. Fetch Products with UOM and Supplier pivots
            $products = Product::withoutGlobalScope('product_cabang')
                ->where(function ($q) {
                    $q->whereNull('is_active')->orWhere('is_active', true);
                })
                ->with([
                    'uom:id,name,abbreviation',
                    'suppliers' => function ($query) {
                        $query->select('suppliers.id', 'suppliers.code', 'suppliers.perusahaan')
                            ->withPivot('supplier_price');
                    },
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost_price', 'uom_id', 'cabang_id', 'pajak'])
                ->map(function (Product $product) use ($activeTaxRate) {
                    $productTax = (float) ($product->pajak ?? 0);
                    $defaultTaxRate = $productTax > 0 ? $productTax : $activeTaxRate;
                    
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
                        'default_tax_rate' => $defaultTaxRate,
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

            // 6. Generate fresh request number & default date
            $nextRequestNumber = HelperController::generateRequestNumber();
            $defaultDate = now()->format('Y-m-d');

            return [
                'next_request_number' => $nextRequestNumber,
                'default_request_date' => $defaultDate,
                'default_currency_id' => $defaultCurrencyId,
                'default_cabang_id' => $user?->cabang_id ?? $cabangs->first()?->id ?? null,
                'cabangs' => $cabangs,
                'currencies' => $currencies,
                'suppliers' => $suppliers,
                'products' => $products,
                'tax_types' => [
                    ['value' => 'none', 'label' => 'Non PPN (0%)'],
                    ['value' => 'eklusif', 'label' => 'PPN Excluded (11%)'],
                    ['value' => 'inklusif', 'label' => 'PPN Included (11%)'],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('OrderRequestApiController::getDependenciesData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get dependencies via JsonResponse API
     */
    public function dependencies(Request $request): JsonResponse
    {
        try {
            $data = $this->getDependenciesData($request);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::error('OrderRequestApiController::dependencies failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat master data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a new unique request number on demand.
     */
    public function generateNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'request_number' => HelperController::generateRequestNumber(),
        ]);
    }

    /**
     * Store a newly created Order Request and its items.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_number' => 'required|string|max:255|unique:order_requests,request_number',
            'request_date' => 'required|date',
            'note' => 'nullable|string',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cabang_id' => 'required|integer|exists:cabangs,id',
            'items.*.supplier_id' => 'nullable|integer|exists:suppliers,id',
            'items.*.currency_id' => 'nullable|integer|exists:currencies,id',
            'items.*.original_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'required|string|in:none,eklusif,inklusif',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.note' => 'nullable|string',
            'items.*.unit_price_idr' => 'nullable|numeric|min:0',
            'items.*.original_price_idr' => 'nullable|numeric|min:0',
        ], [
            'request_number.required' => 'Nomor request wajib diisi.',
            'request_number.unique' => 'Nomor request sudah digunakan, silakan klik tombol regenerate.',
            'request_date.required' => 'Tanggal request wajib diisi.',
            'items.required' => 'Harus ada setidaknya satu item produk.',
            'items.min' => 'Harus ada setidaknya satu item produk.',
            'items.*.product_id.required' => 'Produk pada setiap baris wajib dipilih.',
            'items.*.quantity.min' => 'Kuantitas minimal 0.01.',
            'items.*.cabang_id.required' => 'Cabang pada setiap item wajib dipilih.',
            'items.*.tipe_pajak.required' => 'Tipe pajak wajib ditentukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($validated) {
            $currencyId = $validated['currency_id'] ?? CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');
            $userId = Auth::id() ?? 1; // Fallback to user ID 1 if not logged in via session/token

            // 1. Create OrderRequest Header
            $orderRequest = OrderRequest::create([
                'request_number' => $validated['request_number'],
                'request_date' => $validated['request_date'],
                'status' => 'draft',
                'note' => $validated['note'] ?? null,
                'currency_id' => $currencyId,
                'created_by' => $userId,
            ]);

            // 2. Create OrderRequestItem Rows
            foreach ($validated['items'] as $itemData) {
                $itemCurrencyId = $itemData['currency_id'] ?? $currencyId;
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $discountPct = (float) ($itemData['discount'] ?? 0);
                $tipePajak = TaxTypeHelper::normalize($itemData['tipe_pajak'] ?? 'eklusif');
                $taxPct = $tipePajak === 'none'
                    ? 0.0
                    : (isset($itemData['tax']) ? (float) $itemData['tax'] : TaxDefaultResolver::resolveForProductId((int) $itemData['product_id'], TaxTypeHelper::serviceType($tipePajak)));

                // Calculate subtotal
                $base = $quantity * $unitPrice;
                $afterDisc = $base - ($base * ($discountPct / 100));
                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                $subtotal = $tipePajak === 'inklusif'
                    ? round($afterDisc, 2)
                    : round($afterDisc + $taxNominal, 2);

                // IDR Anchors
                $unitPriceIdr = isset($itemData['unit_price_idr']) && (float) $itemData['unit_price_idr'] > 0
                    ? (float) $itemData['unit_price_idr']
                    : (float) CurrencyConversionResolver::convertToIdrHighPrecision((string) $unitPrice, $itemCurrencyId);

                $originalPrice = (float) ($itemData['original_price'] ?? $unitPrice);
                $originalPriceIdr = isset($itemData['original_price_idr']) && (float) $itemData['original_price_idr'] > 0
                    ? (float) $itemData['original_price_idr']
                    : (float) CurrencyConversionResolver::convertToIdrHighPrecision((string) $originalPrice, $itemCurrencyId);

                OrderRequestItem::create([
                    'order_request_id' => $orderRequest->id,
                    'product_id' => $itemData['product_id'],
                    'supplier_id' => $itemData['supplier_id'] ?? null,
                    'cabang_id' => $itemData['cabang_id'],
                    'currency_id' => $itemCurrencyId,
                    'quantity' => $quantity,
                    'fulfilled_quantity' => 0,
                    'status' => OrderRequestItem::STATUS_DRAFT,
                    'unit_price' => $unitPrice,
                    'unit_price_idr' => $unitPriceIdr,
                    'original_price' => $originalPrice,
                    'original_price_idr' => $originalPriceIdr,
                    'discount' => $discountPct,
                    'tax' => $taxPct,
                    'tipe_pajak' => $tipePajak,
                    'subtotal' => $subtotal,
                    'note' => $itemData['note'] ?? null,
                ]);
            }

            Log::info('OrderRequestApiController: Created Order Request', [
                'order_request_id' => $orderRequest->id,
                'request_number' => $orderRequest->request_number,
                'items_count' => count($validated['items']),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Order Request {$orderRequest->request_number} berhasil disimpan dengan status DRAFT.",
                'data' => [
                    'id' => $orderRequest->id,
                    'request_number' => $orderRequest->request_number,
                    'status' => $orderRequest->status,
                    'redirect_url' => url("/admin/order-requests/{$orderRequest->id}"),
                ],
            ], 201);
        });
    }

    /**
     * Update the specified Order Request and its items.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $orderRequest = OrderRequest::with('orderRequestItem')->find($id);
        if (! $orderRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Order Request tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'request_number' => 'required|string|max:255|unique:order_requests,request_number,' . $id,
            'request_date' => 'required|date',
            'note' => 'nullable|string',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cabang_id' => 'required|integer|exists:cabangs,id',
            'items.*.supplier_id' => 'nullable|integer|exists:suppliers,id',
            'items.*.currency_id' => 'nullable|integer|exists:currencies,id',
            'items.*.original_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tipe_pajak' => 'required|string|in:none,eklusif,inklusif',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.note' => 'nullable|string',
            'items.*.status' => 'nullable|string|in:draft,approved,rejected',
            'items.*.unit_price_idr' => 'nullable|numeric|min:0',
            'items.*.original_price_idr' => 'nullable|numeric|min:0',
        ], [
            'request_number.required' => 'Nomor request wajib diisi.',
            'request_number.unique' => 'Nomor request sudah digunakan, silakan gunakan nomor yang berbeda.',
            'request_date.required' => 'Tanggal request wajib diisi.',
            'items.required' => 'Harus ada setidaknya satu item produk.',
            'items.min' => 'Harus ada setidaknya satu item produk.',
            'items.*.product_id.required' => 'Produk pada setiap baris wajib dipilih.',
            'items.*.quantity.min' => 'Kuantitas minimal 0.01.',
            'items.*.cabang_id.required' => 'Cabang pada setiap item wajib dipilih.',
            'items.*.tipe_pajak.required' => 'Tipe pajak wajib ditentukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi data gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        return DB::transaction(function () use ($orderRequest, $validated) {
            $currencyId = $validated['currency_id'] ?? $orderRequest->currency_id;

            // 1. Update Header
            $orderRequest->update([
                'request_number' => $validated['request_number'],
                'request_date' => $validated['request_date'],
                'note' => $validated['note'] ?? null,
                'currency_id' => $currencyId,
            ]);

            // 2. Sync Items
            $submittedItemIds = [];

            foreach ($validated['items'] as $itemData) {
                $itemCurrencyId = $itemData['currency_id'] ?? $currencyId;
                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $discountPct = (float) ($itemData['discount'] ?? 0);
                $tipePajak = TaxTypeHelper::normalize($itemData['tipe_pajak'] ?? 'eklusif');
                $taxPct = $tipePajak === 'none'
                    ? 0.0
                    : (isset($itemData['tax']) ? (float) $itemData['tax'] : TaxDefaultResolver::resolveForProductId((int) $itemData['product_id'], TaxTypeHelper::serviceType($tipePajak)));

                // Calculate subtotal
                $base = $quantity * $unitPrice;
                $afterDisc = $base - ($base * ($discountPct / 100));
                $taxNominal = round($afterDisc * ($taxPct / 100), 2);
                $subtotal = $tipePajak === 'inklusif'
                    ? round($afterDisc, 2)
                    : round($afterDisc + $taxNominal, 2);

                // IDR Anchors
                $unitPriceIdr = isset($itemData['unit_price_idr']) && (float) $itemData['unit_price_idr'] > 0
                    ? (float) $itemData['unit_price_idr']
                    : (float) CurrencyConversionResolver::convertToIdrHighPrecision((string) $unitPrice, $itemCurrencyId);

                $originalPrice = (float) ($itemData['original_price'] ?? $unitPrice);
                $originalPriceIdr = isset($itemData['original_price_idr']) && (float) $itemData['original_price_idr'] > 0
                    ? (float) $itemData['original_price_idr']
                    : (float) CurrencyConversionResolver::convertToIdrHighPrecision((string) $originalPrice, $itemCurrencyId);

                $itemPayload = [
                    'order_request_id' => $orderRequest->id,
                    'product_id' => $itemData['product_id'],
                    'supplier_id' => $itemData['supplier_id'] ?? null,
                    'cabang_id' => $itemData['cabang_id'],
                    'currency_id' => $itemCurrencyId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_price_idr' => $unitPriceIdr,
                    'original_price' => $originalPrice,
                    'original_price_idr' => $originalPriceIdr,
                    'discount' => $discountPct,
                    'tax' => $taxPct,
                    'tipe_pajak' => $tipePajak,
                    'subtotal' => $subtotal,
                    'note' => $itemData['note'] ?? null,
                ];

                $itemStatus = isset($itemData['status'])
                    ? OrderRequestItem::normalizeApprovalStatus($itemData['status'])
                    : null;
                if ($itemStatus) {
                    $itemPayload['status'] = $itemStatus;
                    if ($itemStatus === OrderRequestItem::STATUS_APPROVED) {
                        $itemPayload['approved_by'] = Auth::id() ?? 1;
                        $itemPayload['approved_at'] = now();
                    } elseif ($itemStatus === OrderRequestItem::STATUS_REJECTED) {
                        $itemPayload['rejected_by'] = Auth::id() ?? 1;
                        $itemPayload['rejected_at'] = now();
                    }
                }

                if (! empty($itemData['id'])) {
                    $existingItem = OrderRequestItem::where('id', $itemData['id'])
                        ->where('order_request_id', $orderRequest->id)
                        ->first();

                    if ($existingItem) {
                        $existingItem->update($itemPayload);
                        $submittedItemIds[] = $existingItem->id;
                        continue;
                    }
                }

                // If new item row
                $newItem = OrderRequestItem::create(array_merge($itemPayload, [
                    'fulfilled_quantity' => 0,
                    'status' => $itemPayload['status'] ?? OrderRequestItem::STATUS_DRAFT,
                ]));
                $submittedItemIds[] = $newItem->id;
            }

            // Delete removed items
            OrderRequestItem::where('order_request_id', $orderRequest->id)
                ->whereNotIn('id', $submittedItemIds)
                ->delete();

            $orderRequest->refresh();
            $orderRequest->syncItemApprovalStatus();
            $orderRequest->refresh();

            Log::info('OrderRequestApiController: Updated Order Request', [
                'order_request_id' => $orderRequest->id,
                'request_number' => $orderRequest->request_number,
                'items_count' => count($submittedItemIds),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Order Request {$orderRequest->request_number} berhasil diperbarui.",
                'data' => [
                    'id' => $orderRequest->id,
                    'request_number' => $orderRequest->request_number,
                    'status' => $orderRequest->status,
                    'redirect_url' => url("/admin/order-requests/{$orderRequest->id}"),
                ],
            ], 200);
        });
    }
}

