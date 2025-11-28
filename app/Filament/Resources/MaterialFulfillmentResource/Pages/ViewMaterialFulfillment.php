<?php

namespace App\Filament\Resources\MaterialFulfillmentResource\Pages;

use App\Filament\Resources\MaterialFulfillmentResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewMaterialFulfillment extends ViewRecord
{
    protected static string $resource = MaterialFulfillmentResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informasi Rencana Produksi')
                    ->schema([
                        Infolists\Components\TextEntry::make('plan_number')
                            ->label('Nomor Rencana'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Nama Rencana'),
                        Infolists\Components\TextEntry::make('product.name')
                            ->label('Produk'),
                        Infolists\Components\TextEntry::make('quantity')
                            ->label('Kuantitas'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'scheduled' => 'warning',
                                'in_progress' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label('Tanggal Mulai')
                            ->date(),
                        Infolists\Components\TextEntry::make('end_date')
                            ->label('Tanggal Selesai')
                            ->date(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Informasi Tambahan')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Diupdate Pada')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('Tidak ada catatan'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Detail Pemenuhan Bahan Baku')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('fulfillment_summary_total')
                                    ->label('Total Bahan')
                                    ->getStateUsing(function ($record) {
                                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                                        return $summary['total_materials'] ?? 0;
                                    }),
                                Infolists\Components\TextEntry::make('fulfillment_summary_available')
                                    ->label('Tersedia 100%')
                                    ->getStateUsing(function ($record) {
                                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                                        return ($summary['fully_available'] ?? 0) . '/' . ($summary['total_materials'] ?? 0);
                                    })
                                    ->color(function ($record) {
                                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                                        return (($summary['fully_available'] ?? 0) === ($summary['total_materials'] ?? 0)) ? 'success' : 'warning';
                                    }),
                                Infolists\Components\TextEntry::make('fulfillment_summary_issued')
                                    ->label('Sudah diambil 100%')
                                    ->getStateUsing(function ($record) {
                                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                                        return ($summary['fully_issued'] ?? 0) . '/' . ($summary['total_materials'] ?? 0);
                                    })
                                    ->color(function ($record) {
                                        $summary = \App\Models\MaterialFulfillment::getFulfillmentSummary($record);
                                        return (($summary['fully_issued'] ?? 0) === ($summary['total_materials'] ?? 0)) ? 'success' : 'warning';
                                    }),
                            ]),

                        Infolists\Components\ViewEntry::make('material_fulfillments_table')
                            ->view('filament.infolists.material-fulfillments-table')
                            ->getStateUsing(function ($record) {
                                return \App\Models\MaterialFulfillment::where('production_plan_id', $record->id)
                                    ->with('material')
                                    ->get();
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_material_issue')
                ->label('Ambil Bahan')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn (): string => route('filament.admin.resources.material-issues.create', ['production_plan_id' => $this->getRecord()->id])),
        ];
    }
}