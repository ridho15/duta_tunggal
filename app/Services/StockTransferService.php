<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function generateTransferNumber(): string
    {
        return StockTransfer::generateTransferNumber();
    }

    public function requestTransfer(StockTransfer $stockTransfer): StockTransfer
    {
        $stockTransfer->loadMissing('stockTransferItem');

        if ($stockTransfer->status !== 'Draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer stok berstatus draft yang dapat diajukan.',
            ]);
        }

        $items = $stockTransfer->stockTransferItem;

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Tambahkan minimal satu item sebelum request transfer stok.',
            ]);
        }

        foreach ($items as $item) {
            $this->validateTransferItemShape($item);
        }

        $stockTransfer->update([
            'status' => 'Request',
        ]);

        return $stockTransfer->fresh();
    }

    public function rejectTransfer(StockTransfer $stockTransfer, ?string $reason = null): StockTransfer
    {
        if ($stockTransfer->status !== 'Request') {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer stok berstatus Request yang dapat ditolak.',
            ]);
        }

        $notes = trim(($stockTransfer->notes ? $stockTransfer->notes . "\n\n" : '') . ($reason ? "DITOLAK: {$reason}" : 'DITOLAK OLEH SUPERVISOR'));

        $stockTransfer->update([
            'status' => 'Reject',
            'notes' => $notes,
        ]);

        return $stockTransfer->fresh();
    }

    public function revertToDraft(StockTransfer $stockTransfer): StockTransfer
    {
        if (!in_array(strtolower((string) $stockTransfer->status), ['reject', 'request'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya transfer stok berstatus Reject atau Request yang dapat dikembalikan ke Draft.',
            ]);
        }

        $stockTransfer->update([
            'status' => 'Draft',
        ]);

        return $stockTransfer->fresh();
    }

    public function voidStockTransfer(StockTransfer $stockTransfer, string $reason): StockTransfer
    {
        return DB::transaction(function () use ($stockTransfer, $reason) {
            /** @var StockTransfer $lockedTransfer */
            $lockedTransfer = StockTransfer::query()
                ->whereKey($stockTransfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (strtolower((string) $lockedTransfer->status) !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya transfer stok berstatus Approved yang dapat di-void.',
                ]);
            }

            // Hapus pergerakan stok mutasi
            $movements = StockMovement::query()
                ->where('from_model_type', StockTransfer::class)
                ->where('from_model_id', $lockedTransfer->id)
                ->get();

            foreach ($movements as $movement) {
                $movement->delete();
            }

            // Jurnal antar cabang dibalik (bukan dihapus) agar periode tertutup & jejak audit tetap utuh
            app(JournalReversalService::class)->reverseSource($lockedTransfer, "Mutasi stok di-void: {$reason}");

            $notes = trim(($lockedTransfer->notes ? $lockedTransfer->notes . "\n\n" : '') . "DIVOID OLEH SUPER ADMIN: {$reason} (" . now()->format('d/m/Y H:i') . ')');

            $lockedTransfer->update([
                'status' => 'Cancelled',
                'notes' => $notes,
            ]);

            return $lockedTransfer->fresh();
        });
    }

    public function approveStockTransfer(StockTransfer $stockTransfer): StockTransfer
    {
        return DB::transaction(function () use ($stockTransfer) {
            /** @var StockTransfer $lockedTransfer */
            $lockedTransfer = StockTransfer::query()
                ->whereKey($stockTransfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTransfer->load([
                'stockTransferItem.product',
                'stockTransferItem.fromWarehouse',
                'stockTransferItem.fromRak',
                'stockTransferItem.toWarehouse',
                'stockTransferItem.toRak',
            ]);

            if ($lockedTransfer->status !== 'Request') {
                throw ValidationException::withMessages([
                    'status' => 'Hanya transfer stok berstatus request yang dapat disetujui.',
                ]);
            }

            if ($lockedTransfer->stockTransferItem->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Transfer stok tidak memiliki item untuk diproses.',
                ]);
            }

            foreach ($lockedTransfer->stockTransferItem as $item) {
                $this->validateTransferItemShape($item);

                $sourceStockQuery = InventoryStock::query()
                    ->where('product_id', $item->product_id)
                    ->where('warehouse_id', $item->from_warehouse_id);

                if ($item->from_rak_id) {
                    $sourceStockQuery->where('rak_id', $item->from_rak_id);
                } else {
                    $sourceStockQuery->whereNull('rak_id');
                }

                $sourceStock = $sourceStockQuery->lockForUpdate()->first();

                // Fallback jika tidak ditemukan spesifik null rak, cari stok apa pun produk tersebut di gudang asal
                if (! $sourceStock && ! $item->from_rak_id) {
                    $sourceStock = InventoryStock::query()
                        ->where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->from_warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if ($sourceStock && $sourceStock->rak_id) {
                        $item->update(['from_rak_id' => $sourceStock->rak_id]);
                    }
                }

                if (! $sourceStock) {
                    throw ValidationException::withMessages([
                        'stock' => sprintf(
                            'Stok sumber untuk produk %s di gudang %s / rak %s tidak ditemukan.',
                            $item->product?->name ?? $item->product_id,
                            $item->fromWarehouse?->name ?? '-',
                            $item->fromRak?->name ?? '-'
                        ),
                    ]);
                }

                $physical = (float) $sourceStock->qty_available;
                $reserved = (float) $sourceStock->qty_reserved;
                $freeStock = max(0, $physical - $reserved);

                if ($freeStock < (float) $item->quantity) {
                    $productCode = $item->product?->sku ?: ($item->product?->name ?? $item->product_id);
                    $qtyTransfer = rtrim(rtrim((string) $item->quantity, '0'), '.');
                    $qtyFree = rtrim(rtrim((string) $freeStock, '0'), '.');
                    $whName = $item->fromWarehouse?->name ?? 'Gudang Asal';
                    throw ValidationException::withMessages([
                        'stock' => "Kuantitas transfer untuk {$productCode} ({$qtyTransfer} pcs) melebihi stok bebas yang tersedia di {$whName} ({$qtyFree} pcs).",
                    ]);
                }

                $this->syncApprovedItemMovements($lockedTransfer, $item);
            }

            $lockedTransfer->update([
                'status' => 'Approved',
            ]);

            $this->syncInterBranchJournal($lockedTransfer->fresh());

            return $lockedTransfer->fresh();
        });
    }

    public function syncApprovedItemMovements(StockTransfer $stockTransfer, StockTransferItem $stockTransferItem): void
    {
        $this->upsertItemMovement($stockTransfer, $stockTransferItem, 'transfer_out', $stockTransferItem->from_warehouse_id, $stockTransferItem->from_rak_id, [
            'notes' => 'Transfer ke gudang ' . ($stockTransferItem->toWarehouse?->name ?? '-'),
        ]);

        $this->upsertItemMovement($stockTransfer, $stockTransferItem, 'transfer_in', $stockTransferItem->to_warehouse_id, $stockTransferItem->to_rak_id, [
            'notes' => 'Transfer dari gudang ' . ($stockTransferItem->fromWarehouse?->name ?? '-'),
        ]);

        // Saat approve, jurnal diposting sekali setelah semua item diproses; di luar itu (edit item mutasi yang sudah Approved)
        // jurnal antar cabang diselaraskan ulang.
        if (strtolower((string) $stockTransfer->status) === 'approved') {
            $this->syncInterBranchJournal($stockTransfer->fresh());
        }
    }

    public function deleteApprovedItemMovements(StockTransfer $stockTransfer, StockTransferItem $stockTransferItem, bool $forceDelete = false): void
    {
        $movements = StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('meta->stock_transfer_item_id', $stockTransferItem->id)
            ->get();

        foreach ($movements as $movement) {
            if ($forceDelete) {
                $movement->forceDelete();

                continue;
            }

            $movement->delete();
        }

        if (strtolower((string) $stockTransfer->status) === 'approved') {
            $this->syncInterBranchJournal($stockTransfer->fresh());
        }
    }

    /**
     * Jurnal mutasi ANTAR CABANG (keputusan 22 Nov 2026: per cabang, nilai HPP, dijurnal saat disetujui, lewat akun perantara
     * Rekening Antar Cabang). Mutasi dalam satu cabang tidak dijurnal (persediaan cabang tidak berubah).
     *
     *   Cabang asal  : Dr Rekening Antar Cabang   / Cr Persediaan   (qty × HPP rata-rata)
     *   Cabang tujuan: Dr Persediaan              / Cr Rekening Antar Cabang
     *
     * Saldo Rekening Antar Cabang kedua cabang saling meniadakan di laporan gabungan. Idempoten: bila jurnal yang ada sudah
     * sama dengan yang seharusnya, tidak ada yang diposting; bila berbeda (item diubah), jurnal lama dibalik lalu diposting ulang.
     */
    public function syncInterBranchJournal(StockTransfer $stockTransfer): void
    {
        $stockTransfer->loadMissing(['stockTransferItem.product', 'stockTransferItem.fromWarehouse', 'stockTransferItem.toWarehouse']);

        $lines = [];   // "coaId|cabangId" => net (debit − credit)
        $add = function (int $coaId, int $cabangId, float $net) use (&$lines) {
            $key = $coaId . '|' . $cabangId;
            $lines[$key] = round(($lines[$key] ?? 0.0) + $net, 2);
        };

        $interBranchCoa = null;
        foreach ($stockTransfer->stockTransferItem as $item) {
            $fromCabang = (int) ($item->fromWarehouse?->cabang_id ?? 0);
            $toCabang = (int) ($item->toWarehouse?->cabang_id ?? 0);
            if (! $fromCabang || ! $toCabang || $fromCabang === $toCabang) {
                continue;
            }

            $value = $this->transferValue($stockTransfer, $item);
            if ($value <= 0) {
                continue;
            }

            $inventoryCoa = $item->product?->resolveInventoryCoaOrDefault();
            $interBranchCoa ??= ChartOfAccount::where('code', config('coa.inter_branch', '1180.02'))->where('is_active', true)->first();
            if (! $inventoryCoa || ! $interBranchCoa) {
                throw ValidationException::withMessages([
                    'journal' => sprintf(
                        'Mutasi antar cabang %s tidak dapat dijurnal: akun %s tidak ditemukan / tidak aktif. Solusi: tambahkan akun tersebut di Bagan Akun (COA), lalu setujui ulang mutasi.',
                        $stockTransfer->transfer_number,
                        ! $interBranchCoa ? 'Rekening Antar Cabang (kode ' . config('coa.inter_branch', '1180.02') . ')' : 'Persediaan produk ' . ($item->product?->name ?? $item->product_id)
                    ),
                ]);
            }

            $add($interBranchCoa->id, $fromCabang, $value);
            $add($inventoryCoa->id, $fromCabang, -$value);
            $add($inventoryCoa->id, $toCabang, $value);
            $add($interBranchCoa->id, $toCabang, -$value);
        }
        $lines = array_filter($lines, fn (float $net) => abs($net) >= 0.005);

        $existing = JournalEntry::withoutGlobalScopes()
            ->where('source_type', $stockTransfer->getMorphClass())
            ->where('source_id', $stockTransfer->id)
            ->get()
            ->groupBy(fn (JournalEntry $e) => $e->coa_id . '|' . (int) $e->cabang_id)
            ->map(fn ($g) => round((float) $g->sum('debit') - (float) $g->sum('credit'), 2))
            ->filter(fn (float $net) => abs($net) >= 0.005)
            ->all();

        ksort($lines);
        ksort($existing);
        if ($lines == $existing) {
            return;
        }

        app(JournalReversalService::class)->reverseSource($stockTransfer, 'Mutasi antar cabang diubah, jurnal diposting ulang');

        $date = $stockTransfer->transfer_date
            ? \Illuminate\Support\Carbon::parse($stockTransfer->transfer_date)->toDateString()
            : now()->toDateString();
        foreach ($lines as $key => $net) {
            [$coaId, $cabangId] = array_map('intval', explode('|', $key));
            JournalEntry::create([
                'coa_id' => $coaId,
                'date' => $date,
                'reference' => $stockTransfer->transfer_number,
                'description' => "Mutasi stok antar cabang {$stockTransfer->transfer_number} (nilai HPP)",
                'debit' => $net > 0 ? $net : 0,
                'credit' => $net < 0 ? abs($net) : 0,
                'journal_type' => 'stock_transfer',
                'cabang_id' => $cabangId,
                'source_type' => $stockTransfer->getMorphClass(),
                'source_id' => $stockTransfer->id,
            ]);
        }
    }

    /** Nilai mutasi = nilai kartu stok transfer_out (qty × HPP saat disetujui) agar jurnal = Laporan Stok. */
    private function transferValue(StockTransfer $stockTransfer, StockTransferItem $item): float
    {
        $movementValue = StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('type', 'transfer_out')
            ->where('meta->stock_transfer_item_id', $item->id)
            ->value('value');

        return round(abs((float) ($movementValue ?? ((float) $item->quantity * (float) ($item->product?->cost_price ?? 0)))), 2);
    }

    private function validateTransferItemShape(StockTransferItem $item): void
    {
        if ((float) $item->quantity <= 0) {
            throw ValidationException::withMessages([
                'items' => 'Qty transfer harus lebih besar dari 0.',
            ]);
        }

        if (! $item->product_id || ! $item->from_warehouse_id || ! $item->to_warehouse_id) {
            throw ValidationException::withMessages([
                'items' => 'Setiap item transfer harus memiliki produk, gudang asal, dan gudang tujuan yang lengkap.',
            ]);
        }

        if (
            (int) $item->from_warehouse_id === (int) $item->to_warehouse_id
            && (int) ($item->from_rak_id ?? 0) === (int) ($item->to_rak_id ?? 0)
        ) {
            throw ValidationException::withMessages([
                'items' => 'Gudang dan rak tujuan harus berbeda dari gudang dan rak asal.',
            ]);
        }
    }

    private function upsertItemMovement(
        StockTransfer $stockTransfer,
        StockTransferItem $stockTransferItem,
        string $type,
        int $warehouseId,
        ?int $rakId,
        array $attributes = []
    ): void {
        $existingMovement = StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('type', $type)
            ->where('meta->stock_transfer_item_id', $stockTransferItem->id)
            ->first();

        $payload = array_merge([
            'product_id' => $stockTransferItem->product_id,
            'warehouse_id' => $warehouseId,
            'rak_id' => $rakId,
            'quantity' => $stockTransferItem->quantity,
            // Nilai kartu stok mutasi = qty × HPP rata-rata produk (keluar & masuk bernilai sama, tanpa dampak GL)
            'value' => round((float) $stockTransferItem->quantity * (float) ($stockTransferItem->product?->cost_price ?? 0), 2),
            'type' => $type,
            'reference_id' => $stockTransfer->transfer_number,
            'date' => $stockTransfer->transfer_date,
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $stockTransfer->id,
            'meta' => [
                'stock_transfer_item_id' => $stockTransferItem->id,
            ],
        ], $attributes);

        if ($existingMovement) {
            $existingMovement->update($payload);

            return;
        }

        StockMovement::create($payload);
    }
}
