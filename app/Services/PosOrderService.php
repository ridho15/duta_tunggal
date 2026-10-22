<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosOrderService
{
    /**
     * Cari produk aktif untuk scanner barcode dan pencarian cepat kasir.
     */
    public function searchProducts(string $keyword, ?int $warehouseId = null, ?int $cabangId = null): Collection
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return collect();
        }

        $query = Product::withoutGlobalScopes()
            ->active()
            ->when($cabangId, function ($q) use ($cabangId) {
                $q->where(function ($sub) use ($cabangId) {
                    $sub->where('cabang_id', $cabangId)->orWhereNull('cabang_id');
                });
            })
            ->where(function ($q) use ($keyword) {
                $q->where('sku', 'like', "{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            })
            ->with(['inventoryStocks' => function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            }])
            ->limit(15);

        return $query->get()->map(function (Product $product) {
            $stockAvailable = (float) $product->inventoryStocks->sum('qty_available');
            $hargaBatas = (float) $product->harga_batas;
            $costPrice = (float) $product->cost_price;
            $effectiveMinPrice = $hargaBatas > 0 ? $hargaBatas : $costPrice;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'sell_price' => (float) $product->sell_price,
                'cost_price' => $costPrice,
                'harga_batas' => $hargaBatas,
                'effective_min_price' => $effectiveMinPrice,
                'available_stock' => $stockAvailable,
                'category' => $product->category?->name ?? '-',
                'uom' => $product->uom?->name ?? 'Pcs',
            ];
        });
    }

    /**
     * Cari produk tunggal secara presisi berdasarkan Barcode / SKU (digunakan oleh barcode scanner).
     */
    public function findByBarcode(string $barcode, ?int $warehouseId = null, ?int $cabangId = null): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $product = Product::withoutGlobalScopes()
            ->active()
            ->when($cabangId, function ($q) use ($cabangId) {
                $q->where(function ($sub) use ($cabangId) {
                    $sub->where('cabang_id', $cabangId)->orWhereNull('cabang_id');
                });
            })
            ->where('sku', $barcode)
            ->with(['inventoryStocks' => function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            }])
            ->first();

        if (!$product) {
            return null;
        }

        $stockAvailable = (float) $product->inventoryStocks->sum('qty_available');
        $hargaBatas = (float) $product->harga_batas;
        $costPrice = (float) $product->cost_price;
        $effectiveMinPrice = $hargaBatas > 0 ? $hargaBatas : $costPrice;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'sell_price' => (float) $product->sell_price,
            'cost_price' => $costPrice,
            'harga_batas' => $hargaBatas,
            'effective_min_price' => $effectiveMinPrice,
            'available_stock' => $stockAvailable,
            'category' => $product->category?->name ?? '-',
            'uom' => $product->uom?->name ?? 'Pcs',
        ];
    }

    /**
     * Simpan transaksi POS penjualan ritel dengan validasi harga_batas, potong stok & jurnal otomatis.
     */
    public function processOrder(array $data, User $cashier, ?PosShift $shift = null): PosOrder
    {
        return DB::transaction(function () use ($data, $cashier, $shift) {
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => 'Keranjang belanja tidak boleh kosong.',
                ]);
            }

            $cabangId = $shift?->cabang_id ?? $cashier->cabang_id;
            $warehouseId = $shift?->warehouse_id ?? $data['warehouse_id'] ?? null;

            if (!$warehouseId) {
                $warehouseId = InventoryStock::whereIn('product_id', collect($itemsData)->pluck('product_id'))
                    ->value('warehouse_id') ?? \App\Models\Warehouse::first()?->id;
            }

            // 1. Validasi setiap item: batas harga (harga_batas) dan ketersediaan stok
            $productIds = collect($itemsData)->pluck('product_id')->unique()->all();
            $products = Product::withoutGlobalScopes()
                ->whereIn('id', $productIds)
                ->with(['inventoryStocks' => function ($q) use ($warehouseId) {
                    if ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId);
                    }
                }])
                ->get()
                ->keyBy('id');

            $preparedItems = [];
            $subtotalTotal = 0.0;
            $itemDiscountTotal = 0.0;
            $totalCogs = 0.0;

            foreach ($itemsData as $idx => $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.product_id" => "Produk #{$productId} tidak ditemukan di sistem.",
                    ]);
                }

                $qty = max(0.01, (float) ($item['quantity'] ?? 1));
                $unitPrice = (float) ($item['unit_price'] ?? $product->sell_price);
                $discountAmount = max(0.0, (float) ($item['discount_amount'] ?? 0));
                $itemSubtotal = max(0.0, ($unitPrice * $qty) - $discountAmount);

                $costPrice = (float) $product->cost_price;
                $hargaBatas = (float) $product->harga_batas;
                $effectiveMinPrice = $hargaBatas > 0 ? $hargaBatas : $costPrice;

                // Proteksi batas harga minimum
                $effectiveUnitPrice = $qty > 0 ? round($itemSubtotal / $qty, 2) : $unitPrice;
                if ($effectiveUnitPrice < ($effectiveMinPrice - 0.01)) {
                    $formattedMin = number_format($effectiveMinPrice, 0, ',', '.');
                    $formattedPrice = number_format($effectiveUnitPrice, 0, ',', '.');
                    throw ValidationException::withMessages([
                        "items.{$idx}.unit_price" => "Harga jual {$product->name} (Rp {$formattedPrice}/unit) di bawah batas minimum (Rp {$formattedMin}). Berikan harga minimal sesuai batas harga_batas/HPP.",
                    ]);
                }

                // Cek ketersediaan stok di gudang yang dipilih
                $currentStock = (float) $product->inventoryStocks->sum('qty_available');
                if ($currentStock < $qty) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.quantity" => "Stok persediaan tidak mencukupi untuk {$product->name}. Stok saat ini: {$currentStock}, diminta: {$qty}.",
                    ]);
                }

                $subtotalTotal += ($unitPrice * $qty);
                $itemDiscountTotal += $discountAmount;
                $totalCogs += ($costPrice * $qty);

                $preparedItems[] = [
                    'product' => $product,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'subtotal' => $itemSubtotal,
                    'harga_batas' => $hargaBatas,
                    'cost_price' => $costPrice,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // 2. Kalkulasi grand total & pembayaran
            $globalDiscount = max(0.0, (float) ($data['discount_amount'] ?? 0));
            $totalDiscount = $itemDiscountTotal + $globalDiscount;
            $taxAmount = max(0.0, (float) ($data['tax_amount'] ?? 0));
            $grandTotal = max(0.0, $subtotalTotal - $totalDiscount + $taxAmount);

            $paymentMethod = $data['payment_method'] ?? 'cash';
            $paymentAmount = (float) ($data['payment_amount'] ?? $grandTotal);

            if ($paymentMethod === 'cash') {
                if ($paymentAmount < ($grandTotal - 0.01)) {
                    $formattedShort = number_format($grandTotal - $paymentAmount, 0, ',', '.');
                    throw ValidationException::withMessages([
                        'payment_amount' => "Nominal uang yang diterima kurang Rp {$formattedShort}.",
                    ]);
                }
                $changeAmount = round($paymentAmount - $grandTotal, 2);
            } else {
                $paymentAmount = $grandTotal;
                $changeAmount = 0.0;
            }

            // 3. Tentukan Akun Kas / Bank Penerimaan
            $coaId = $data['coa_id'] ?? null;
            if (!$coaId) {
                if ($paymentMethod === 'cash') {
                    $coaId = ChartOfAccount::where('code', '1111.03')->value('id') // KAS PENJUALAN
                        ?? ChartOfAccount::where('code', '1111.01')->value('id')   // KAS BESAR
                        ?? ChartOfAccount::where('code', config('coa.cash_and_bank'))->value('id');
                } else {
                    $coaId = ChartOfAccount::where('code', config('coa.cash_and_bank', '1112.01'))->value('id')
                        ?? ChartOfAccount::where('code', '1111.03')->value('id');
                }
            }

            $orderNumber = SequentialNumberGenerator::generate('pos_orders', 'order_number', 'POS-');

            // 4. Buat PosOrder Header
            $order = PosOrder::create([
                'order_number' => $orderNumber,
                'pos_shift_id' => $shift?->id,
                'cabang_id' => $cabangId,
                'warehouse_id' => $warehouseId,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? 'Pelanggan Umum',
                'user_id' => $cashier->id,
                'order_date' => Carbon::now(),
                'subtotal' => $subtotalTotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'payment_method' => $paymentMethod,
                'payment_amount' => $paymentAmount,
                'change_amount' => $changeAmount,
                'coa_id' => $coaId,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // 5. Buat Item & Gerakan Stok (StockMovement)
            foreach ($preparedItems as $pItem) {
                /** @var Product $product */
                $product = $pItem['product'];

                PosOrderItem::create([
                    'pos_order_id' => $order->id,
                    'product_id' => $pItem['product_id'],
                    'quantity' => $pItem['quantity'],
                    'unit_price' => $pItem['unit_price'],
                    'discount_amount' => $pItem['discount_amount'],
                    'subtotal' => $pItem['subtotal'],
                    'harga_batas' => $pItem['harga_batas'],
                    'cost_price' => $pItem['cost_price'],
                    'notes' => $pItem['notes'],
                ]);

                // StockMovement keluar untuk penjualan POS (observer akan memotong InventoryStock)
                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $pItem['quantity'],
                    'value' => $pItem['cost_price'] * $pItem['quantity'],
                    'type' => 'sales',
                    'reference_id' => $order->id,
                    'date' => Carbon::now()->toDateString(),
                    'notes' => "Penjualan POS #{$order->order_number} ({$product->name})",
                    'from_model_type' => PosOrder::class,
                    'from_model_id' => $order->id,
                ]);
            }

            // 6. Penjurnalan Akuntansi Otomatis
            $this->postJournalEntries($order, $preparedItems, $grandTotal, $totalCogs, $coaId);

            // 7. Akumulasikan ke Shift Aktif (jika ada)
            if ($shift) {
                if ($paymentMethod === 'cash') {
                    $shift->increment('cash_sales_total', $grandTotal);
                } else {
                    $shift->increment('non_cash_sales_total', $grandTotal);
                }
                $shift->increment('total_sales', $grandTotal);
                $shift->increment('total_transactions', 1);
            }

            return $order->fresh(['items.product', 'cashier', 'warehouse', 'coa']);
        });
    }

    /**
     * Buat ayat jurnal akuntansi seimbang untuk transaksi POS ritel:
     * - Debit Kas/Bank Penerimaan
     * - Kredit Pendapatan Penjualan
     * - Debit HPP
     * - Kredit Persediaan Barang Dagangan
     */
    protected function postJournalEntries(
        PosOrder $order,
        array $preparedItems,
        float $grandTotal,
        float $totalCogs,
        ?int $cashCoaId
    ): void {
        $date = Carbon::now()->toDateString();
        $ref = $order->order_number;

        // 1. DEBIT: Kas Penjualan / Bank
        if ($grandTotal > 0 && $cashCoaId) {
            JournalEntry::create([
                'coa_id' => $cashCoaId,
                'date' => $date,
                'reference' => $ref,
                'description' => "Kasir POS #{$ref} - Penerimaan Kas/Pembayaran",
                'debit' => $grandTotal,
                'credit' => 0,
                'journal_type' => 'sales',
                'source_type' => PosOrder::class,
                'source_id' => $order->id,
                'cabang_id' => $order->cabang_id,
            ]);
        }

        // 2. KREDIT: Pendapatan Penjualan per item
        $defaultSalesCoaId = ChartOfAccount::where('code', '4100.10')->value('id')
            ?? ChartOfAccount::where('code', config('coa.sales_revenue', '4000'))->value('id')
            ?? ChartOfAccount::where('type', 'Revenue')->value('id')
            ?? ChartOfAccount::first()?->id;

        $totalCreditedRevenue = 0.0;
        foreach ($preparedItems as $pItem) {
            /** @var Product $product */
            $product = $pItem['product'];
            $lineRevenue = round((float) $pItem['subtotal'], 2);

            if ($lineRevenue <= 0) {
                continue;
            }

            $revenueCoaId = $product->sales_coa_id ?? $defaultSalesCoaId;
            if ($revenueCoaId) {
                JournalEntry::create([
                    'coa_id' => $revenueCoaId,
                    'date' => $date,
                    'reference' => $ref,
                    'description' => "Kasir POS #{$ref} - Penjualan: {$product->name}",
                    'debit' => 0,
                    'credit' => $lineRevenue,
                    'journal_type' => 'sales',
                    'source_type' => PosOrder::class,
                    'source_id' => $order->id,
                    'cabang_id' => $order->cabang_id,
                ]);
                $totalCreditedRevenue += $lineRevenue;
            }
        }

        // Penyesuaian selisih pembulatan revenue vs grand total jika ada
        $revenueDiff = round($grandTotal - $totalCreditedRevenue, 2);
        if (abs($revenueDiff) > 0 && $defaultSalesCoaId) {
            JournalEntry::create([
                'coa_id' => $defaultSalesCoaId,
                'date' => $date,
                'reference' => $ref,
                'description' => "Kasir POS #{$ref} - Selisih Penjualan/Pajak",
                'debit' => $revenueDiff < 0 ? abs($revenueDiff) : 0,
                'credit' => $revenueDiff > 0 ? $revenueDiff : 0,
                'journal_type' => 'sales',
                'source_type' => PosOrder::class,
                'source_id' => $order->id,
                'cabang_id' => $order->cabang_id,
            ]);
        }

        // 3. HPP & PERSEDIAAN
        if ($totalCogs > 0) {
            $defaultCogsCoaId = ChartOfAccount::where('code', '5100.10')->value('id')
                ?? ChartOfAccount::where('type', 'Expense')->value('id')
                ?? ChartOfAccount::first()?->id;

            $defaultInvCoaId = ChartOfAccount::where('code', '1140.10')->value('id')
                ?? ChartOfAccount::where('code', config('coa.inventory', '1140.10'))->value('id')
                ?? ChartOfAccount::where('type', 'Asset')->value('id')
                ?? ChartOfAccount::first()?->id;

            if ($defaultCogsCoaId && $defaultInvCoaId) {
                // DEBIT HPP
                JournalEntry::create([
                    'coa_id' => $defaultCogsCoaId,
                    'date' => $date,
                    'reference' => $ref,
                    'description' => "Kasir POS #{$ref} - Beban Pokok Penjualan (HPP)",
                    'debit' => round($totalCogs, 2),
                    'credit' => 0,
                    'journal_type' => 'general',
                    'source_type' => PosOrder::class,
                    'source_id' => $order->id,
                    'cabang_id' => $order->cabang_id,
                ]);

                // KREDIT PERSEDIAAN
                JournalEntry::create([
                    'coa_id' => $defaultInvCoaId,
                    'date' => $date,
                    'reference' => $ref,
                    'description' => "Kasir POS #{$ref} - Pengeluaran Persediaan",
                    'debit' => 0,
                    'credit' => round($totalCogs, 2),
                    'journal_type' => 'general',
                    'source_type' => PosOrder::class,
                    'source_id' => $order->id,
                    'cabang_id' => $order->cabang_id,
                ]);
            }
        }
    }
}
