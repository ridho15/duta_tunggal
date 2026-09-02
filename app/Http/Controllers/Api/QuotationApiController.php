<?php

namespace App\Http\Controllers\Api;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Services\QuotationService;
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

class QuotationApiController extends Controller
{
    /**
     * Get all dependencies needed to populate the Quotation form.
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

            // 3. Fetch Customers (Sorted Alphabetically by name / code)
            $customers = Customer::query()
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'perusahaan', 'nik_npwp', 'address',
                    'telephone', 'phone', 'email', 'tempo_kredit', 'kredit_limit',
                    'tipe_pembayaran', 'tipe'
                ]);

            // 4. Fetch Products with UOM (Sorted Alphabetically by name)
            $products = Product::withoutGlobalScope('product_cabang')
                ->with(['uom:id,name,abbreviation'])
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'sell_price', 'uom_id'])
                ->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'sku' => $p->sku,
                        'name' => $p->name,
                        'sell_price' => (float) MoneyHelper::parseHighPrecision($p->sell_price ?? 0),
                        'uom' => $p->uom ? [
                            'id' => $p->uom->id,
                            'name' => $p->uom->name,
                            'abbreviation' => $p->uom->abbreviation,
                        ] : null,
                    ];
                });

            // 5. Generate Next Quotation Number
            $quotationService = app(QuotationService::class);
            $nextQuotationNumber = $quotationService->generateCode();

            // 6. Tax Types standard
            $taxTypes = [
                ['value' => 'None', 'label' => 'None (0%)', 'rate' => 0],
                ['value' => 'Inklusif', 'label' => 'PPN Inklusif (11%)', 'rate' => 11],
                ['value' => 'Eksklusif', 'label' => 'PPN Eksklusif (11%)', 'rate' => 11],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'next_quotation_number' => $nextQuotationNumber,
                    'default_date' => now()->format('Y-m-d'),
                    'default_valid_until' => now()->addDays(30)->format('Y-m-d'),
                    'default_currency_id' => $defaultCurrencyId,
                    'default_cabang_id' => $user?->cabang_id,
                    'can_access_all_cabang' => $canAccessAllCabang,
                    'cabangs' => $cabangs,
                    'currencies' => $currencies,
                    'customers' => $customers,
                    'products' => $products,
                    'tax_types' => $taxTypes,
                    'user' => [
                        'id' => $user?->id,
                        'name' => $user?->name,
                        'cabang_id' => $user?->cabang_id,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('QuotationApiController::dependencies failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data dependensi: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a new unique quotation code.
     */
    public function generateNumber(): JsonResponse
    {
        try {
            $quotationService = app(QuotationService::class);
            $nextNumber = $quotationService->generateCode();

            return response()->json([
                'success' => true,
                'data' => [
                    'quotation_number' => $nextNumber,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat nomor quotation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created Quotation.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'header.quotation_number' => 'required|string|max:255|unique:quotations,quotation_number',
            'header.customer_id' => 'required|integer|exists:customers,id',
            'header.cabang_id' => 'nullable|integer|exists:cabangs,id',
            'header.date' => 'required|date',
            'header.valid_until' => 'nullable|date',
            'header.currency_id' => 'required|integer|exists:currencies,id',
            'header.tempo_pembayaran' => 'nullable|numeric|min:0',
            'header.notes' => 'nullable|string',
            'header.status' => 'nullable|string|in:draft,request_approve,approve,reject',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ], [
            'header.quotation_number.required' => 'Nomor quotation wajib diisi.',
            'header.quotation_number.unique' => 'Nomor quotation sudah digunakan.',
            'header.customer_id.required' => 'Customer wajib dipilih.',
            'header.date.required' => 'Tanggal quotation wajib diisi.',
            'header.currency_id.required' => 'Mata uang wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 item.',
            'items.min' => 'Minimal harus ada 1 item.',
            'items.*.product_id.required' => 'Produk pada baris item wajib dipilih.',
            'items.*.quantity.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Periksa kembali form input Anda.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $headerData = $request->input('header');
            $itemsData = $request->input('items', []);

            $currencyId = (int) $headerData['currency_id'];
            $exchangeRate = CurrencyConversionResolver::resolveRate($currencyId);

            // Create Quotation Header
            $quotation = Quotation::create([
                'quotation_number' => $headerData['quotation_number'],
                'customer_id' => (int) $headerData['customer_id'],
                'cabang_id' => ! empty($headerData['cabang_id']) ? (int) $headerData['cabang_id'] : Auth::user()?->cabang_id,
                'date' => $headerData['date'],
                'valid_until' => $headerData['valid_until'] ?? null,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'tempo_pembayaran' => isset($headerData['tempo_pembayaran']) ? (int) $headerData['tempo_pembayaran'] : 0,
                'notes' => $headerData['notes'] ?? null,
                'status' => $headerData['status'] ?? 'draft',
                'created_by' => Auth::id(),
                'total_amount' => 0,
            ]);

            // Create Items
            foreach ($itemsData as $item) {
                $unitPrice = (float) $item['unit_price'];
                $unitPriceIdr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
                    (string) $unitPrice,
                    $currencyId
                );

                $qty = (float) $item['quantity'];
                $disc = (float) ($item['discount'] ?? 0);
                $taxType = TaxTypeHelper::normalize($item['tax_type'] ?? null, TaxTypeHelper::NONE);
                $tax = $taxType === TaxTypeHelper::NONE ? 0.0 : (float) ($item['tax'] ?? 0);

                $subtotal = HelperController::hitungSubtotal($qty, $unitPrice, $disc, $tax, $taxType);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_price_idr' => $unitPriceIdr,
                    'discount' => $disc,
                    'tax_type' => $taxType,
                    'tax' => $tax,
                    'total_price' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Sync total_amount via QuotationService
            $quotationService = app(QuotationService::class);
            $quotationService->updateTotalAmount($quotation);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil dibuat!',
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'redirect_url' => "/admin/quotations/{$quotation->id}",
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('QuotationApiController::store failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyimpan Quotation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show quotation data for edit mode.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $quotation = Quotation::with([
                'customer',
                'cabang',
                'currency',
                'quotationItem.product.uom',
            ])->find($id);

            if (! $quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation tidak ditemukan.',
                ], 404);
            }

            $items = $quotation->quotationItem->map(function ($item, $index) {
                return [
                    'id' => $item->id,
                    'row_id' => 'row_' . $item->id,
                    'product_id' => $item->product_id,
                    'product_sku' => $item->product?->sku ?? '',
                    'product_name' => $item->product?->name ?? '',
                    'unit' => $item->product?->uom?->abbreviation ?? $item->product?->uom?->name ?? 'PCS',
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'unit_price_idr' => (float) $item->unit_price_idr,
                    'discount' => (float) $item->discount,
                    'tax_type' => TaxTypeHelper::normalize($item->tax_type, TaxTypeHelper::NONE),
                    'tax' => (float) $item->tax,
                    'notes' => $item->notes ?? '',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'header' => [
                        'id' => $quotation->id,
                        'quotation_number' => $quotation->quotation_number,
                        'customer_id' => $quotation->customer_id,
                        'cabang_id' => $quotation->cabang_id,
                        'date' => $quotation->date ? $quotation->date->format('Y-m-d') : now()->format('Y-m-d'),
                        'valid_until' => $quotation->valid_until ? $quotation->valid_until->format('Y-m-d') : null,
                        'currency_id' => $quotation->currency_id,
                        'exchange_rate' => (float) ($quotation->exchange_rate ?? 1.0),
                        'tempo_pembayaran' => (int) ($quotation->tempo_pembayaran ?? 0),
                        'notes' => $quotation->notes ?? '',
                        'status' => $quotation->status ?? 'draft',
                        'total_amount' => (float) $quotation->total_amount,
                    ],
                    'items' => $items,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('QuotationApiController::show failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data Quotation: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing Quotation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $quotation = Quotation::find($id);
        if (! $quotation) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'header.quotation_number' => 'required|string|max:255|unique:quotations,quotation_number,' . $id,
            'header.customer_id' => 'required|integer|exists:customers,id',
            'header.cabang_id' => 'nullable|integer|exists:cabangs,id',
            'header.date' => 'required|date',
            'header.valid_until' => 'nullable|date',
            'header.currency_id' => 'required|integer|exists:currencies,id',
            'header.tempo_pembayaran' => 'nullable|numeric|min:0',
            'header.notes' => 'nullable|string',
            'header.status' => 'nullable|string|in:draft,request_approve,approve,reject',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string',
        ], [
            'header.quotation_number.required' => 'Nomor quotation wajib diisi.',
            'header.quotation_number.unique' => 'Nomor quotation sudah digunakan.',
            'header.customer_id.required' => 'Customer wajib dipilih.',
            'header.date.required' => 'Tanggal quotation wajib diisi.',
            'header.currency_id.required' => 'Mata uang wajib dipilih.',
            'items.required' => 'Minimal harus ada 1 item.',
            'items.min' => 'Minimal harus ada 1 item.',
            'items.*.product_id.required' => 'Produk pada baris item wajib dipilih.',
            'items.*.quantity.required' => 'Jumlah (Qty) wajib diisi.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal. Periksa kembali form input Anda.',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $headerData = $request->input('header');
            $itemsData = $request->input('items', []);

            $currencyId = (int) $headerData['currency_id'];
            $exchangeRate = CurrencyConversionResolver::resolveRate($currencyId);

            // Update Quotation Header
            $quotation->update([
                'quotation_number' => $headerData['quotation_number'],
                'customer_id' => (int) $headerData['customer_id'],
                'cabang_id' => ! empty($headerData['cabang_id']) ? (int) $headerData['cabang_id'] : $quotation->cabang_id,
                'date' => $headerData['date'],
                'valid_until' => $headerData['valid_until'] ?? null,
                'currency_id' => $currencyId,
                'exchange_rate' => $exchangeRate,
                'tempo_pembayaran' => isset($headerData['tempo_pembayaran']) ? (int) $headerData['tempo_pembayaran'] : 0,
                'notes' => $headerData['notes'] ?? null,
                'status' => $headerData['status'] ?? $quotation->status,
            ]);

            // Sync Quotation Items
            // Delete removed items
            $quotation->quotationItem()->delete();

            // Re-insert current items
            foreach ($itemsData as $item) {
                $unitPrice = (float) $item['unit_price'];
                $unitPriceIdr = (float) CurrencyConversionResolver::convertToIdrHighPrecision(
                    (string) $unitPrice,
                    $currencyId
                );

                $qty = (float) $item['quantity'];
                $disc = (float) ($item['discount'] ?? 0);
                $taxType = TaxTypeHelper::normalize($item['tax_type'] ?? null, TaxTypeHelper::NONE);
                $tax = $taxType === TaxTypeHelper::NONE ? 0.0 : (float) ($item['tax'] ?? 0);

                $subtotal = HelperController::hitungSubtotal($qty, $unitPrice, $disc, $tax, $taxType);

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_price_idr' => $unitPriceIdr,
                    'discount' => $disc,
                    'tax_type' => $taxType,
                    'tax' => $tax,
                    'total_price' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // Sync total_amount via QuotationService
            $quotationService = app(QuotationService::class);
            $quotationService->updateTotalAmount($quotation);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation berhasil diperbarui!',
                'data' => [
                    'id' => $quotation->id,
                    'quotation_number' => $quotation->quotation_number,
                    'redirect_url' => "/admin/quotations/{$quotation->id}",
                ],
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('QuotationApiController::update failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui Quotation: ' . $e->getMessage(),
            ], 500);
        }
    }
}
