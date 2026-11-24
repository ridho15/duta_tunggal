<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\StockTransferShipment;
use App\Models\StockTransferShipmentItem;
use App\Models\Warehouse;
use App\Models\Cabang;
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

            if (! in_array(strtolower((string) $lockedTransfer->status), ['approved', 'shipped', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya mutasi yang sudah disetujui / dikirim / diterima yang dapat di-void.',
                ]);
            }

            // Lepas cadangan stok asal yang belum dikirim
            $this->releaseReservations($lockedTransfer);

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

            StockTransferShipmentItem::query()->whereIn('stock_transfer_shipment_id', $lockedTransfer->shipments()->pluck('id'))->delete();
            $lockedTransfer->shipments()->delete();

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
                    $qtyTransfer = $this->qty((float) $item->quantity);   // rtrim('10', '0') dulu menampilkan "1"
                    $qtyFree = $this->qty((float) $freeStock);
                    $whName = $item->fromWarehouse?->name ?? 'Gudang Asal';
                    throw ValidationException::withMessages([
                        'stock' => "Kuantitas transfer untuk {$productCode} ({$qtyTransfer} pcs) melebihi stok bebas yang tersedia di {$whName} ({$qtyFree} pcs).",
                    ]);
                }

                // Disetujui = stok asal DICADANGKAN (belum pindah). Stok baru keluar saat gudang asal menekan Kirim.
                StockReservation::create([
                    'stock_transfer_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $item->from_warehouse_id,
                    'rak_id' => $item->from_rak_id ?? $sourceStock->rak_id,
                    'quantity' => (float) $item->quantity,
                ]);
            }

            $lockedTransfer->update([
                'status' => 'Approved',
            ]);

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
     * Jurnal mutasi ANTAR CABANG (keputusan 22 Nov 2026: per cabang, nilai HPP, lewat akun perantara
     * Rekening Antar Cabang; sejak 24 Nov dijurnal saat DIKIRIM sebesar barang yang keluar dari gudang asal —
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
                        'Mutasi antar cabang %s tidak dapat dijurnal: akun %s tidak ditemukan / tidak aktif. Solusi: tambahkan akun tersebut di Bagan Akun (COA), lalu ulangi Kirim Barang.',
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

    /** Nilai mutasi = Σ nilai kartu stok yang keluar dari gudang asal (qty dikirim × HPP) agar jurnal = Laporan Stok. */
    private function transferValue(StockTransfer $stockTransfer, StockTransferItem $item): float
    {
        // Nilai = barang yang SUDAH keluar dari gudang asal (dikirim); sebelum dikirim belum ada perpindahan persediaan
        return round(abs((float) StockMovement::query()
            ->where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $stockTransfer->id)
            ->where('type', 'transfer_out')
            ->where('warehouse_id', $item->from_warehouse_id)
            ->where('meta->stock_transfer_item_id', $item->id)
            ->sum('value')), 2);
    }

    /**
     * KIRIM (gudang asal): boleh sebagian item & sebagian qty, boleh beberapa kali. Stok keluar dari gudang asal dan masuk
     * gudang transit cabang tujuan (barang dalam perjalanan); cadangan stok asal dilepas sebesar qty yang dikirim.
     *
     * @param  array<int|string, float|int|string|null>  $quantities  [stock_transfer_item_id => qty dikirim]
     */
    public function shipTransfer(StockTransfer $stockTransfer, array $quantities, ?string $notes = null, ?int $userId = null): StockTransferShipment
    {
        return DB::transaction(function () use ($stockTransfer, $quantities, $notes, $userId) {
            $transfer = StockTransfer::query()->whereKey($stockTransfer->getKey())->lockForUpdate()->firstOrFail();
            $transfer->load(['stockTransferItem.product', 'stockTransferItem.toWarehouse', 'stockTransferItem.fromWarehouse']);

            if (! in_array($transfer->status, ['Approved', 'Shipped'], true) || $transfer->remaining_closed_at !== null) {
                throw ValidationException::withMessages(['status' => 'Mutasi ini tidak bisa dikirim: harus berstatus Disetujui / Dikirim Sebagian dan sisanya belum ditutup.']);
            }

            $lines = [];
            foreach ($transfer->stockTransferItem as $item) {
                $qty = round((float) ($quantities[$item->id] ?? 0), 2);
                if ($qty <= 0) {
                    continue;
                }
                $remaining = $item->remainingToShip();
                if ($qty > $remaining + 0.0001) {
                    throw ValidationException::withMessages(['quantities' => sprintf(
                        'Qty kirim %s (%s) melebihi sisa yang belum dikirim (%s). Solusi: isi maksimal %s.',
                        $item->product?->name ?? $item->product_id, $this->qty($qty), $this->qty($remaining), $this->qty($remaining)
                    )]);
                }
                $physical = (float) InventoryStock::query()->where('product_id', $item->product_id)->where('warehouse_id', $item->from_warehouse_id)->lockForUpdate()->sum('qty_available');
                if ($physical + 0.0001 < $qty) {
                    throw ValidationException::withMessages(['quantities' => sprintf(
                        'Stok fisik %s di %s hanya %s, kurang dari qty kirim %s. Solusi: cek fisik gudang / stock opname, lalu kirim sesuai stok yang ada.',
                        $item->product?->name ?? $item->product_id, $item->fromWarehouse?->name ?? 'gudang asal', $this->qty($physical), $this->qty($qty)
                    )]);
                }
                $lines[] = [$item, $qty];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['quantities' => 'Isi qty kirim minimal satu barang (lebih dari 0).']);
            }

            $toCabangId = $lines[0][0]->toWarehouse?->cabang_id;
            $transit = $this->transitWarehouse($toCabangId);
            $shipment = StockTransferShipment::create([
                'stock_transfer_id' => $transfer->id,
                'shipment_number' => $transfer->transfer_number . '-K' . ($transfer->shipments()->count() + 1),
                'transit_warehouse_id' => $transit->id,
                'shipped_at' => now(),
                'shipped_by' => $userId,
                'notes' => $notes,
            ]);

            foreach ($lines as [$item, $qty]) {
                $shipmentItem = $shipment->items()->create(['stock_transfer_item_id' => $item->id, 'quantity' => $qty]);
                $this->consumeReservation($item, $qty);
                $meta = ['stock_transfer_item_id' => $item->id, 'shipment_id' => $shipment->id, 'shipment_item_id' => $shipmentItem->id];
                $this->createMovement($transfer, $item, 'transfer_out', $item->from_warehouse_id, $item->from_rak_id, $qty, $meta + ['leg' => 'kirim'],
                    'Dikirim ke ' . ($item->toWarehouse?->name ?? '-') . ' (' . $shipment->shipment_number . ')');
                $this->createMovement($transfer, $item, 'transfer_in', $transit->id, null, $qty, $meta + ['leg' => 'transit'],
                    'Barang dalam perjalanan ' . $shipment->shipment_number);
            }

            $transfer->update(['status' => 'Shipped']);
            $this->syncInterBranchJournal($transfer->fresh());

            return $shipment->fresh('items');
        });
    }

    /** TERIMA (gudang tujuan): seluruh isi satu pengiriman pindah dari gudang transit ke gudang/rak tujuan. */
    public function receiveShipment(StockTransferShipment $shipment, ?int $userId = null): StockTransferShipment
    {
        return DB::transaction(function () use ($shipment, $userId) {
            $locked = StockTransferShipment::query()->whereKey($shipment->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->received_at !== null) {
                throw ValidationException::withMessages(['shipment' => "Pengiriman {$locked->shipment_number} sudah diterima sebelumnya."]);
            }
            $transfer = $locked->stockTransfer()->with('stockTransferItem')->firstOrFail();
            if ($transfer->status !== 'Shipped') {
                throw ValidationException::withMessages(['shipment' => 'Mutasi ini tidak dalam status Dikirim, sehingga tidak bisa diterima.']);
            }

            foreach ($locked->items()->with('transferItem.fromWarehouse')->get() as $line) {
                $item = $line->transferItem;
                $meta = ['stock_transfer_item_id' => $item->id, 'shipment_id' => $locked->id, 'shipment_item_id' => $line->id];
                $this->createMovement($transfer, $item, 'transfer_out', $locked->transit_warehouse_id, null, (float) $line->quantity, $meta + ['leg' => 'transit'],
                    'Diterima gudang tujuan ' . $locked->shipment_number);
                $this->createMovement($transfer, $item, 'transfer_in', $item->to_warehouse_id, $item->to_rak_id, (float) $line->quantity, $meta + ['leg' => 'terima'],
                    'Diterima dari ' . ($item->fromWarehouse?->name ?? '-') . ' (' . $locked->shipment_number . ')');
            }

            $locked->update(['received_at' => now(), 'received_by' => $userId]);
            $this->completeIfDone($transfer->fresh('stockTransferItem'));

            return $locked->fresh();
        });
    }

    /** TUTUP SISA: qty yang belum dikirim batal dikirim; cadangan stok asal dilepas. */
    public function closeRemaining(StockTransfer $stockTransfer, string $reason, ?int $userId = null): StockTransfer
    {
        return DB::transaction(function () use ($stockTransfer, $reason, $userId) {
            $transfer = StockTransfer::query()->whereKey($stockTransfer->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($transfer->status, ['Approved', 'Shipped'], true) || $transfer->remaining_closed_at !== null) {
                throw ValidationException::withMessages(['status' => 'Sisa mutasi ini sudah ditutup atau mutasi belum disetujui.']);
            }

            $this->releaseReservations($transfer);
            $transfer->update(['remaining_closed_at' => now(), 'remaining_closed_by' => $userId, 'remaining_close_reason' => $reason]);

            if (! $transfer->shipments()->exists()) {
                $transfer->update(['status' => 'Cancelled']);   // belum ada yang dikirim: mutasi batal seluruhnya
            } else {
                $this->completeIfDone($transfer->fresh('stockTransferItem'));
            }

            return $transfer->fresh();
        });
    }

    private function completeIfDone(StockTransfer $transfer): void
    {
        $allReceived = ! $transfer->shipments()->whereNull('received_at')->exists();
        if ($allReceived && ! $transfer->hasRemainingToShip() && $transfer->shipments()->exists()) {
            $transfer->update(['status' => 'Completed']);
        }
    }

    /** Kurangi cadangan stok asal item sebesar qty yang dikirim. */
    private function consumeReservation(StockTransferItem $item, float $qty): void
    {
        $left = $qty;
        foreach (StockReservation::query()->where('stock_transfer_item_id', $item->id)->orderBy('id')->lockForUpdate()->get() as $reservation) {
            if ($left <= 0.0001) {
                break;
            }
            $take = min($left, (float) $reservation->quantity);
            $rest = round((float) $reservation->quantity - $take, 2);
            $rest > 0.0001 ? $reservation->update(['quantity' => $rest]) : $reservation->delete();
            $left -= $take;
        }
    }

    private function releaseReservations(StockTransfer $transfer): void
    {
        StockReservation::query()->whereIn('stock_transfer_item_id', $transfer->stockTransferItem()->withTrashed()->pluck('id'))
            ->get()->each->delete();   // observer menurunkan qty_reserved
    }

    private function createMovement(StockTransfer $transfer, StockTransferItem $item, string $type, int $warehouseId, ?int $rakId, float $qty, array $meta, string $notes): void
    {
        $item->loadMissing('product');
        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $warehouseId,
            'rak_id' => $rakId,
            'quantity' => $qty,
            // Nilai kartu stok mutasi = qty × HPP rata-rata produk
            'value' => round($qty * (float) ($item->product?->cost_price ?? 0), 2),
            'type' => $type,
            'reference_id' => $transfer->transfer_number,
            'date' => now()->toDateString(),
            'notes' => $notes,
            'from_model_type' => StockTransfer::class,
            'from_model_id' => $transfer->id,
            'meta' => $meta,
        ]);
    }

    /** Gudang transit per cabang (dibuat otomatis): barang mutasi dalam perjalanan, bukan stok jual. */
    public function transitWarehouse(?int $cabangId): Warehouse
    {
        $existing = Warehouse::query()->where('is_transit', true)->where('cabang_id', $cabangId)->first();
        if ($existing) {
            return $existing;
        }
        $cabang = $cabangId ? Cabang::query()->find($cabangId) : null;

        return Warehouse::create([
            'kode' => 'TRS-' . strtoupper((string) ($cabang?->kode ?: ($cabangId ?: 'ALL'))),
            'name' => 'Transit / Barang Dalam Perjalanan' . ($cabang?->nama ? ' - ' . $cabang->nama : ''),
            'cabang_id' => $cabangId,
            'location' => 'Barang mutasi yang sedang dikirim',
            'tipe' => 'Kecil',
            'status' => 1,
            'is_transit' => true,
        ]);
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
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
