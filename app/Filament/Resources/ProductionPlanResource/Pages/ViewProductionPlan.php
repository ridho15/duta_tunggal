<?php

namespace App\Filament\Resources\ProductionPlanResource\Pages;

use App\Filament\Resources\ProductionPlanResource;
use App\Http\Controllers\HelperController;
use App\Models\ProductionPlan;
use App\Services\ManufacturingService;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class ViewProductionPlan extends ViewRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Kebutuhan Bahan Produksi')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('plan_number')
                                    ->label('Nomor Rencana'),
                                TextEntry::make('name')
                                    ->label('Nama Pekerjaan'),
                                TextEntry::make('product_label')
                                    ->label('Produk')
                                    ->getStateUsing(function ($record) {
                                        if (! $record instanceof ProductionPlan) {
                                            return '-';
                                        }

                                        $product = $record->product;

                                        return $product?->exists
                                            ? sprintf('(%s) %s', $product->sku ?? '-', $product->name ?? '-')
                                            : '-';
                                    }),
                                TextEntry::make('bom_label')
                                    ->label('BOM')
                                    ->getStateUsing(function ($record) {
                                        if (! $record instanceof ProductionPlan) {
                                            return '-';
                                        }

                                        $bom = $record->billOfMaterial;

                                        return $bom?->exists
                                            ? sprintf('(%s) %s', $bom->code ?? '-', $bom->nama_bom ?? '-')
                                            : '-';
                                    }),
                                TextEntry::make('quantity')
                                    ->label('Quantity Produksi'),
                                TextEntry::make('uom.name')
                                    ->label('Satuan'),
                            ]),

                        TextEntry::make('material_requirement_summary')
                            ->label('Ringkasan Kebutuhan')
                            ->getStateUsing(function ($record) {
                                if (! $record instanceof ProductionPlan || ! $record->exists) {
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

                        ViewEntry::make('material_requirement_details')
                            ->label('Daftar Kebutuhan Bahan')
                            ->view('filament.infolists.production-plan-material-requirements-table')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')
            ->visible(function () {
                return in_array($this->getRecord()->status, ['draft', 'scheduled']);
            }),
            Actions\DeleteAction::make()->icon('heroicon-o-trash')
            ->visible(function () {
                return $this->getRecord()->status === 'draft';
            }),
            Actions\Action::make('schedule')
                ->label('Jadwalkan')
                ->icon('heroicon-o-calendar-days')
                ->color('warning')
                ->visible(function () {
                    return $this->getRecord()->status === 'draft';
                })
                ->requiresConfirmation()
                ->modalHeading('Jadwalkan Rencana Produksi')
                ->modalDescription('Apakah Anda yakin ingin menjadwalkan rencana produksi ini? Status akan berubah menjadi SCHEDULED dan MaterialIssue akan dibuat otomatis.')
                ->modalSubmitActionLabel('Jadwalkan')
                ->action(function () {
                    $record = $this->getRecord();

                    if ($record->status !== 'draft') {
                        Notification::make()
                            ->title('Rencana sudah dijadwalkan')
                            ->info()
                            ->body('Rencana produksi ini tidak berada pada status draft.')
                            ->send();

                        return;
                    }

                    try {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'scheduled']);

                            HelperController::setLog(
                                message: 'Production plan dijadwalkan dan MaterialIssue dibuat otomatis.',
                                model: $record
                            );
                        });

                        HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Berhasil',
                            message: 'Rencana produksi berhasil dijadwalkan dan MaterialIssue telah dibuat otomatis. Proses selanjutnya: Kepala Produksi perlu memulai Manufacturing Order dan memastikan bahan baku siap diproduksi.'
                        );
                    } catch (\Throwable $exception) {
                        report($exception);

                        HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Gagal menjadwalkan',
                            message: 'Terjadi kesalahan saat menjadwalkan rencana produksi: ' . $exception->getMessage()
                        );
                    }
                }),
        ];
    }
}