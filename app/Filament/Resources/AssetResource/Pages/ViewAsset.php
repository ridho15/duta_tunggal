<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Http\Controllers\HelperController;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\Console\Helper\Helper;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            Actions\Action::make('calculate_depreciation')
                ->color('warning')
                ->label('Hitung Penyusutan')
                ->icon('heroicon-o-calculator')
                ->action(function () {
                    $this->record->calculateDepreciation();
                    \Filament\Notifications\Notification::make()
                        ->title('Penyusutan berhasil dihitung')
                        ->success()
                        ->persistent()
                        ->sendToDatabase(\Filament\Facades\Filament::auth()->user())
                        ->send();
                }),
            Actions\Action::make('post_asset_journal')
                ->color('info')
                ->label('Post Jurnal Akuisisi')
                ->icon('heroicon-o-document-plus')
                ->visible(fn() => !$this->record->hasPostedJournals())
                ->action(function () {
                    $assetService = new \App\Services\AssetService();
                    $assetService->postAssetAcquisitionJournal($this->record);
                    \Filament\Notifications\Notification::make()
                        ->title('Jurnal akuisisi aset berhasil dipost')
                        ->success()
                        ->persistent()
                        ->sendToDatabase(\Filament\Facades\Filament::auth()->user())
                        ->send();
                }),
            Actions\Action::make('post_depreciation_journal')
                ->color('primary')
                ->label('Post Jurnal Penyusutan')
                ->visible(fn() => $this->record->status !== 'fully_depreciated' && $this->record->monthly_depreciation > 0)
                ->action(function () {
                    $currentMonth = now()->format('Y-m');
                    $depreciationAmount = $this->record->monthly_depreciation ?? 0;

                    if ($depreciationAmount <= 0) {
                        \Filament\Notifications\Notification::make()
                            ->title('Tidak ada penyusutan untuk dipost')
                            ->body('Nilai penyusutan bulanan asset ini adalah 0 atau negatif. Pastikan asset belum fully depreciated dan nilai penyusutan sudah dihitung dengan benar.')
                            ->warning()
                            ->persistent()
                            ->sendToDatabase(\Filament\Facades\Filament::auth()->user())
                            ->send();
                        return;
                    }

                    // Check if depreciation journal already exists for this month
                    $existingDepreciation = \App\Models\JournalEntry::where('source_type', 'App\Models\Asset')
                        ->where('source_id', $this->record->id)
                        ->where('description', 'like', '%Depreciation expense%')
                        ->where('date', '>=', now()->startOfMonth())
                        ->where('date', '<=', now()->endOfMonth())
                        ->exists();

                    if ($existingDepreciation) {
                        \Filament\Notifications\Notification::make()
                            ->title('Jurnal penyusutan bulan ini sudah ada')
                            ->body('Jurnal penyusutan untuk bulan ' . now()->format('F Y') . ' sudah pernah dipost.')
                            ->warning()
                            ->persistent()
                            ->sendToDatabase(\Filament\Facades\Filament::auth()->user())
                            ->send();
                        return;
                    }

                    $assetService = new \App\Services\AssetService();
                    $assetService->postAssetDepreciationJournal($this->record, $depreciationAmount, $currentMonth);
                    \Filament\Notifications\Notification::make()
                        ->title('Jurnal penyusutan berhasil dipost')
                        ->body('Jurnal penyusutan untuk bulan ' . now()->format('F Y') . ' telah berhasil dibuat.')
                        ->success()
                        ->persistent()
                        ->sendToDatabase(\Filament\Facades\Filament::auth()->user())
                        ->send();
                }),
            Actions\Action::make('view_asset_journals')
                ->color('gray')
                ->label('Lihat Jurnal')
                ->icon('heroicon-o-eye')
                ->url(fn() => '/admin/journal-entries?tableFilters[source_type][value]=App%5CModels%5CAsset&tableFilters[source_id][value]=' . $this->record->id)
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['purchase_cost'] = HelperController::parseIndonesianMoney($data['purchase_cost']);
        $data['salvage_value'] = HelperController::parseIndonesianMoney($data['salvage_value']);
        $data['annual_depreciation'] = HelperController::parseIndonesianMoney($data['annual_depreciation']);
        $data['monthly_depreciation'] = HelperController::parseIndonesianMoney($data['monthly_depreciation']);
        $data['accumulated_depreciation'] = HelperController::parseIndonesianMoney($data['accumulated_depreciation']);
        $data['book_value'] = HelperController::parseIndonesianMoney($data['book_value']);
        return $data;
    }
}
