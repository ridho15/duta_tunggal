<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetTransferService
{
    /**
     * Create asset transfer request
     */
    public function createTransferRequest(Asset $asset, int $toCabangId, array $data): AssetTransfer
    {
        return DB::transaction(function () use ($asset, $toCabangId, $data) {
            /** @var Asset $lockedAsset */
            $lockedAsset = Asset::query()
                ->whereKey($asset->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAsset->status !== 'active') {
                throw new \Exception('Hanya aset berstatus aktif yang dapat dimutasi');
            }

            // Validate that asset is not already being transferred
            $pendingTransfer = AssetTransfer::where('asset_id', $lockedAsset->id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($pendingTransfer) {
                throw new \Exception('Asset sedang dalam proses transfer');
            }

            // Create transfer request
            $transfer = AssetTransfer::create([
                'asset_id' => $lockedAsset->id,
                'from_cabang_id' => $lockedAsset->cabang_id,
                'to_cabang_id' => $toCabangId,
                'transfer_date' => $data['transfer_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? null,
                'transfer_document' => $data['transfer_document'] ?? null,
                'requested_by' => Auth::id(),
                'status' => 'pending',
            ]);

            return $transfer;
        });
    }

    /**
     * Approve transfer request
     */
    public function approveTransfer(AssetTransfer $transfer): AssetTransfer
    {
        return DB::transaction(function () use ($transfer) {
            /** @var AssetTransfer $lockedTransfer */
            $lockedTransfer = AssetTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->status !== 'pending') {
                throw new \Exception('Transfer request tidak dalam status pending');
            }

            $lockedTransfer->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $lockedTransfer;
        });
    }

    /**
     * Complete asset transfer
     */
    public function completeTransfer(AssetTransfer $transfer): AssetTransfer
    {
        return DB::transaction(function () use ($transfer) {
            /** @var AssetTransfer $lockedTransfer */
            $lockedTransfer = AssetTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->status !== 'approved') {
                throw new \Exception('Transfer harus disetujui terlebih dahulu');
            }

            // Update asset cabang with row lock
            /** @var Asset $lockedAsset */
            $lockedAsset = Asset::query()
                ->whereKey($lockedTransfer->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedAsset->update([
                'cabang_id' => $lockedTransfer->to_cabang_id,
            ]);

            // Update transfer status
            $lockedTransfer->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            return $lockedTransfer;
        });
    }

    /**
     * Cancel transfer request
     */
    public function cancelTransfer(AssetTransfer $transfer, string $reason = null): AssetTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            /** @var AssetTransfer $lockedTransfer */
            $lockedTransfer = AssetTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedTransfer->status, ['completed', 'cancelled'])) {
                throw new \Exception('Transfer tidak dapat dibatalkan');
            }

            $lockedTransfer->update([
                'status' => 'cancelled',
                'reason' => $transfer->reason . ($reason ? "\n\nDibatalkan: " . $reason : ''),
            ]);

            return $transfer;
        });
    }

    /**
     * Membatalkan / Void Transfer Aset yang sudah Disetujui atau Selesai (Completed).
     * Jika status sudah completed, kepemilikan cabang aset dikembalikan ke cabang asal (from_cabang_id).
     */
    public function voidTransfer(AssetTransfer $transfer, string $reason, ?int $userId = null): AssetTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $userId) {
            /** @var AssetTransfer $lockedTransfer */
            $lockedTransfer = AssetTransfer::query()
                ->whereKey($transfer->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransfer->isCancelled()) {
                throw new \RuntimeException('Transfer aset ini sudah dibatalkan sebelumnya.');
            }

            /** @var Asset $lockedAsset */
            $lockedAsset = Asset::query()
                ->whereKey($lockedTransfer->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Jika transfer sudah selesai (completed), kembalikan aset ke cabang asal
            if ($lockedTransfer->status === 'completed') {
                $lockedAsset->update([
                    'cabang_id' => $lockedTransfer->from_cabang_id,
                ]);
            }

            $lockedTransfer->update([
                'status' => 'cancelled',
                'void_reason' => $reason,
                'voided_by' => $userId ?? Auth::id(),
                'voided_at' => now(),
                'reason' => $lockedTransfer->reason . ($reason ? "\n\n[VOID oleh Super Admin]: " . $reason : ''),
            ]);

            return $lockedTransfer;
        });
    }
}