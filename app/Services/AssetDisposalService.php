<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetDisposalService
{
    /**
     * Create asset disposal and post journal entries
     */
    public function createDisposal(Asset $asset, array $data): AssetDisposal
    {
        return DB::transaction(function () use ($asset, $data) {
            /** @var Asset $lockedAsset */
            $lockedAsset = Asset::query()
                ->whereKey($asset->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAsset->status === 'disposed') {
                throw new \RuntimeException('Aset ini sudah pernah dihapusbukukan (disposed).');
            }

            if ($lockedAsset->disposals()->whereIn('status', ['pending', 'completed'])->exists()) {
                throw new \RuntimeException('Aset ini sudah memiliki catatan pelepasan (disposal).');
            }

            // Calculate book value at disposal
            $bookValueAtDisposal = $lockedAsset->book_value;

            // Calculate gain/loss
            $gainLossAmount = null;
            if (isset($data['sale_price'])) {
                $gainLossAmount = $data['sale_price'] - $bookValueAtDisposal;
            } elseif ($data['disposal_type'] !== 'sale') {
                // For non-sale disposals (scrap, donation, theft, other), it's always a loss of book value
                $gainLossAmount = -$bookValueAtDisposal;
            }

            // Create disposal record
            $disposal = AssetDisposal::create([
                'asset_id' => $lockedAsset->id,
                'disposal_date' => $data['disposal_date'],
                'disposal_type' => $data['disposal_type'],
                'sale_price' => $data['sale_price'] ?? null,
                'book_value_at_disposal' => $bookValueAtDisposal,
                'gain_loss_amount' => $gainLossAmount,
                'notes' => $data['notes'] ?? null,
                'disposal_document' => $data['disposal_document'] ?? null,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'status' => 'completed',
            ]);

            // Update asset status
            $lockedAsset->update(['status' => 'disposed']);

            // Post journal entries
            $this->postDisposalJournalEntries($lockedAsset, $disposal);

            return $disposal;
        });
    }

    /**
     * Post journal entries for asset disposal
     */
    public function postDisposalJournalEntries(Asset $asset, AssetDisposal $disposal): void
    {
        // Remove asset from balance sheet
        // Debit: Accumulated Depreciation
        // Credit: Fixed Asset
        JournalEntry::create([
            'date' => $disposal->disposal_date,
            'coa_id' => $asset->accumulated_depreciation_coa_id,
            'debit' => $asset->accumulated_depreciation,
            'credit' => 0,
            'description' => 'Asset disposal - remove accumulated depreciation: ' . $asset->name,
            'source_type' => AssetDisposal::class,
            'source_id' => $disposal->id,
            'cabang_id' => $asset->cabang_id,
            'created_by' => Auth::id(),
        ]);

        JournalEntry::create([
            'date' => $disposal->disposal_date,
            'coa_id' => $asset->asset_coa_id,
            'debit' => 0,
            'credit' => $asset->purchase_cost,
            'description' => 'Asset disposal - remove asset cost: ' . $asset->name,
            'source_type' => AssetDisposal::class,
            'source_id' => $disposal->id,
            'cabang_id' => $asset->cabang_id,
            'created_by' => Auth::id(),
        ]);

        // If there's a sale price, record cash received
        if ($disposal->sale_price > 0) {
            $cashCoa = ChartOfAccount::where('code', '1101')->first()
                ?? ChartOfAccount::where('code', '1111.01')->first()
                ?? ChartOfAccount::where('code', '1111')->first()
                ?? ChartOfAccount::where('code', '1110')->first()
                ?? ChartOfAccount::where('type', 'Asset')->where('name', 'like', '%Kas%')->first();

            if (! $cashCoa) {
                throw new \RuntimeException('COA Kas tidak ditemukan untuk pencatatan penjualan aset.');
            }

            JournalEntry::create([
                'date' => $disposal->disposal_date,
                'coa_id' => $cashCoa->id,
                'debit' => $disposal->sale_price,
                'credit' => 0,
                'description' => 'Asset disposal - cash received: ' . $asset->name,
                'source_type' => AssetDisposal::class,
                'source_id' => $disposal->id,
                'cabang_id' => $asset->cabang_id,
                'created_by' => Auth::id(),
            ]);
        }

        // Record gain/loss on disposal
        if (abs((float) $disposal->gain_loss_amount) > 0.001) {
            $gainLossCoa = null;
            $description = '';

            if ($disposal->gain_loss_amount > 0) {
                // Gain on disposal - prioritize dedicated Asset Disposal Gain COA before generic 41%
                $gainLossCoa = ChartOfAccount::where('code', '7000.03')->first()
                    ?? ChartOfAccount::where('code', '4190')->first()
                    ?? ChartOfAccount::where('type', 'Revenue')->where('code', 'like', '41%')->first();
                $description = 'Gain on asset disposal: ' . $asset->name;
            } else {
                // Loss on disposal - prioritize dedicated Loss COA before generic 52%
                $gainLossCoa = ChartOfAccount::where('code', '5290')->first()
                    ?? ChartOfAccount::where('code', '8000.05')->first()
                    ?? ChartOfAccount::where('type', 'Expense')->where('code', 'like', '52%')->first();
                $description = 'Loss on asset disposal: ' . $asset->name;
            }

            if (! $gainLossCoa) {
                throw new \RuntimeException('COA Laba/Rugi pelepasan aset tidak ditemukan.');
            }

            JournalEntry::create([
                'date' => $disposal->disposal_date,
                'coa_id' => $gainLossCoa->id,
                'debit' => $disposal->gain_loss_amount > 0 ? 0 : abs($disposal->gain_loss_amount),
                'credit' => $disposal->gain_loss_amount > 0 ? $disposal->gain_loss_amount : 0,
                'description' => $description,
                'source_type' => AssetDisposal::class,
                'source_id' => $disposal->id,
                'cabang_id' => $asset->cabang_id,
                'created_by' => Auth::id(),
            ]);
        }
    }
}