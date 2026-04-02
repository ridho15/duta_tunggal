<?php

namespace App\Filament\Resources\ProductionResource\Pages;

use App\Filament\Resources\ProductionResource;
use App\Http\Controllers\HelperController;
use App\Models\Production;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewProduction extends ViewRecord
{
    protected static string $resource = ProductionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Produksi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('production_number')
                                    ->label('Nomor Produksi'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn ($state) => strtoupper((string) $state)),
                                TextEntry::make('production_date')
                                    ->label('Tanggal Produksi')
                                    ->date(),
                                TextEntry::make('quantity_produced')
                                    ->label('Quantity Diproduksi')
                                    ->getStateUsing(fn ($record) => $record?->quantity_produced ?? $record?->manufacturingOrder?->productionPlan?->quantity ?? 0)
                                    ->numeric(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('manufacturing_order_number')
                                    ->label('Manufacturing Order')
                                    ->getStateUsing(fn ($record) => $record?->manufacturingOrder?->mo_number ?? '-'),
                                TextEntry::make('production_plan_number')
                                    ->label('Production Plan')
                                    ->getStateUsing(fn ($record) => $record?->manufacturingOrder?->productionPlan?->plan_number ?? '-'),
                                TextEntry::make('product_label')
                                    ->label('Produk')
                                    ->getStateUsing(function ($record) {
                                        $product = $record?->manufacturingOrder?->productionPlan?->product;

                                        return $product?->exists
                                            ? sprintf('(%s) %s', $product->sku ?? '-', $product->name ?? '-')
                                            : '-';
                                    }),
                                TextEntry::make('bom_label')
                                    ->label('BOM')
                                    ->getStateUsing(function ($record) {
                                        $bom = $record?->manufacturingOrder?->productionPlan?->billOfMaterial;

                                        return $bom?->exists
                                            ? sprintf('(%s) %s', $bom->code ?? '-', $bom->nama_bom ?? '-')
                                            : '-';
                                    }),
                            ]),
                    ]),

                Section::make('Informasi Kebutuhan Bahan Produksi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('material_requirement_summary')
                                    ->label('Ringkasan Kebutuhan')
                                    ->getStateUsing(function ($record) {
                                        if (! $record instanceof Production || ! $record->exists) {
                                            return '-';
                                        }

                                        $summary = $record->getFulfillmentSummary();

                                        return sprintf(
                                            'Total bahan %d | Available %d | Partial %d | Unavailable %d | Issued %d | Ready %s',
                                            $summary['total_materials'] ?? 0,
                                            $summary['fully_available'] ?? 0,
                                            $summary['partially_available'] ?? 0,
                                            $summary['not_available'] ?? 0,
                                            $summary['fully_issued'] ?? 0,
                                            ($summary['can_start_production'] ?? false) ? 'Yes' : 'No'
                                        );
                                    })
                                    ->columnSpanFull(),

                                TextEntry::make('production_plan_source')
                                    ->label('Sumber Kebutuhan')
                                    ->getStateUsing(fn ($record) => $record?->resolveMaterialRequirementsSourceLabel() ?? '-')
                                    ->columnSpanFull(),

                                ViewEntry::make('material_requirement_details')
                                    ->label('Daftar Kebutuhan Bahan')
                                    ->view('filament.infolists.production-plan-material-requirements-table')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $routeName = (string) request()->route()?->getName();

        if (str_ends_with($routeName, '.edit')) {
            return [];
        }

        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Actions\Action::make('finished')
                ->label('Finished')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(function (Production $record) {
                    return $record->status === 'draft';
                })
                ->requiresConfirmation()
                ->action(function (Production $record) {
                    $plannedQuantity = $record->manufacturingOrder?->productionPlan?->quantity;

                    $record->update([
                        'status' => 'finished',
                        'quantity_produced' => $record->quantity_produced ?? $plannedQuantity,
                    ]);

                    HelperController::sendNotification(
                        isSuccess: true,
                        title: 'Information',
                        message: 'Production Finished. Quality Control manufacture dibuat otomatis dan Manufacturing Order akan diselesaikan setelah QC diproses.'
                    );
                }),
        ];
    }
}