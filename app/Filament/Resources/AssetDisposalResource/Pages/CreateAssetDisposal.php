<?php

namespace App\Filament\Resources\AssetDisposalResource\Pages;

use App\Filament\Resources\AssetDisposalResource;
use App\Models\Asset;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetDisposal extends CreateRecord
{
    protected static string $resource = AssetDisposalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the asset to calculate book value at disposal
        $asset = Asset::find($data['asset_id']);
        $bookValueAtDisposal = $asset->book_value;

        // Calculate gain/loss amount
        $gainLossAmount = null;
        if (isset($data['sale_price']) && $data['sale_price'] !== null) {
            $gainLossAmount = $data['sale_price'] - $bookValueAtDisposal;
        } elseif ($data['disposal_type'] !== 'sale') {
            // For non-sale disposals (scrap, donation, theft, other), it's always a loss of book value
            $gainLossAmount = -$bookValueAtDisposal;
        }

        $data['book_value_at_disposal'] = $bookValueAtDisposal;
        $data['gain_loss_amount'] = $gainLossAmount;
        $data['approved_by'] = \Illuminate\Support\Facades\Auth::id();
        $data['approved_at'] = now();
        $data['status'] = 'completed';

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update asset status
        $this->record->asset->update(['status' => 'disposed']);

        // Post journal entries using the service
        $disposalService = app(\App\Services\AssetDisposalService::class);
        $disposalService->postDisposalJournalEntries($this->record->asset, $this->record);
    }
}
