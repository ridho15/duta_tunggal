<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use App\Http\Controllers\HelperController;
use App\Models\Production;
use App\Services\QualityControlService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewQualityControlManufacture extends ViewRecord
{
    protected static string $resource = QualityControlManufactureResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('From Production')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('qc_number')
                                    ->label('QC Number'),
                                TextEntry::make('status_formatted')
                                    ->label('Status'),
                                TextEntry::make('production_reference')
                                    ->label('From Production')
                                    ->getStateUsing(function ($record) {
                                        $production = $record?->fromModel;
                                        $manufacturingOrder = $production?->manufacturingOrder;

                                        if (! $production instanceof Production || ! $production->exists) {
                                            return '-';
                                        }

                                        return sprintf(
                                            '%s / %s',
                                            $production->production_number ?? '-',
                                            $manufacturingOrder?->mo_number ?? '-'
                                        );
                                    }),
                                TextEntry::make('product_label')
                                    ->label('Product')
                                    ->getStateUsing(function ($record) {
                                        $product = $record?->product;

                                        return $product?->exists
                                            ? sprintf('(%s) %s', $product->sku ?? '-', $product->name ?? '-')
                                            : '-';
                                    }),
                                TextEntry::make('production_date')
                                    ->label('Tanggal Produksi')
                                    ->getStateUsing(fn ($record) => $record?->fromModel?->production_date)
                                    ->date(),
                                TextEntry::make('target_quantity')
                                    ->label('Total Produksi')
                                    ->getStateUsing(function ($record) {
                                        $production = $record?->fromModel;
                                        $productionPlan = $production?->manufacturingOrder?->productionPlan;

                                        return $production?->quantity_produced ?? $productionPlan?->quantity ?? 0;
                                    })
                                    ->numeric(),
                            ]),
                    ]),
                Section::make('Hasil Quality Control')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('passed_quantity')
                                    ->label('Passed Quantity')
                                    ->numeric(),
                                TextEntry::make('rejected_quantity')
                                    ->label('Rejected Quantity')
                                    ->numeric(),
                                TextEntry::make('warehouse_label')
                                    ->label('Gudang')
                                    ->getStateUsing(fn ($record) => $record?->warehouse?->exists ? sprintf('(%s) %s', $record->warehouse->kode ?? '-', $record->warehouse->name ?? '-') : '-'),
                                TextEntry::make('rak_label')
                                    ->label('Rak')
                                    ->getStateUsing(fn ($record) => $record?->rak?->exists ? sprintf('(%s) %s', $record->rak->code ?? '-', $record->rak->name ?? '-') : '-'),
                                TextEntry::make('reason_reject')
                                    ->label('Reason Reject')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Additional Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('inspectedBy.name')
                                    ->label('Inspected By')
                                    ->placeholder('-'),
                                TextEntry::make('date_send_stock')
                                    ->label('Date Send to Stock')
                                    ->date(),
                            ]),
                    ]),
            ]);
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            Action::make('Complete')
                ->color('success')
                ->label('Complete')
                ->requiresConfirmation()
                ->hidden(function ($record) {
                    return $record->status == 1;
                })
                ->icon('heroicon-o-check-badge')
                ->form(fn ($record) => QualityControlManufactureResource::buildProcessingFormSchema($record))
                ->action(function (array $data, $record) {
                    $qualityControlService = app(QualityControlService::class);
                    $qualityControlService->completeQualityControl($record, $data);
                    HelperController::sendNotification(isSuccess: true, title: "Information", message: "Quality Control Manufacture Completed. Proses selanjutnya: Tim Gudang perlu memindahkan barang hasil produksi ke lokasi penyimpanan dan memperbarui stok inventori.");
                    $qualityControlService->checkPenerimaanBarang($record);
                })
        ];
    }
}