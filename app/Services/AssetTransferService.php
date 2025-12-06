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
            // Validate that asset is not already being transferred
            $pendingTransfer = AssetTransfer::where('asset_id', $asset->id)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($pendingTransfer) {
                throw new \Exception('Asset sedang dalam proses transfer');
            }

            // Create transfer request
            $transfer = AssetTransfer::create([
                'asset_id' => $asset->id,
                'from_cabang_id' => $asset->cabang_id,
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
            if ($transfer->status !== 'pending') {
                throw new \Exception('Transfer request tidak dalam status pending');
            }

            $transfer->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Complete asset transfer
     */
    public function completeTransfer(AssetTransfer $transfer): AssetTransfer
    {
        return DB::transaction(function () use ($transfer) {
            if ($transfer->status !== 'approved') {
                throw new \Exception('Transfer harus disetujui terlebih dahulu');
            }

            // Update asset cabang
            $transfer->asset->update([
                'cabang_id' => $transfer->to_cabang_id,
            ]);

            // Update transfer status
            $transfer->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Cancel transfer request
     */
    public function cancelTransfer(AssetTransfer $transfer, string $reason = null): AssetTransfer
    {
        return DB::transaction(function () use ($transfer, $reason) {
            if (in_array($transfer->status, ['completed', 'cancelled'])) {
                throw new \Exception('Transfer tidak dapat dibatalkan');
            }

            $transfer->update([
                'status' => 'cancelled',
                'reason' => $transfer->reason . ($reason ? "\n\nDibatalkan: " . $reason : ''),
            ]);

            return $transfer;
        });
    }
}