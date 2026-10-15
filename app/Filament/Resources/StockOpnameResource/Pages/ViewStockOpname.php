<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\StockOpnameResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewStockOpname extends ViewRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('start_physical_count')
                ->label('Mulai Hitung Fisik')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, ['draft', 'in_progress']))
                ->requiresConfirmation()
                ->modalHeading('Mulai Hitung Fisik Stock Opname')
                ->modalDescription('Sistem akan memuat seluruh daftar stok produk di gudang ini agar dapat dilakukan pencatatan hitung fisik.')
                ->modalSubmitActionLabel('Ya, Mulai Hitung')
                ->action(function () {
                    try {
                        $count = app(\App\Services\StockOpnameService::class)->startPhysicalCount($this->record);

                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Hitung Fisik Dimulai',
                            message: "Berhasil memuat {$count} produk ke daftar hitung fisik opname."
                        );
                        $this->refreshFormData(['status']);
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Gagal Memulai Hitung Fisik',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),

            Actions\Action::make('complete_counting')
                ->label('Tandai Selesai Hitung')
                ->icon('heroicon-o-check')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'in_progress')
                ->requiresConfirmation()
                ->modalHeading('Selesaikan Hitung Fisik')
                ->modalDescription('Apakah seluruh pencatatan hitung fisik telah selesai dan siap disetujui?')
                ->modalSubmitActionLabel('Ya, Tandai Selesai')
                ->action(function () {
                    try {
                        app(\App\Services\StockOpnameService::class)->completePhysicalCount($this->record);

                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Hitung Fisik Selesai',
                            message: 'Stock opname telah ditandai selesai dan siap disetujui.'
                        );
                        $this->refreshFormData(['status']);
                    } catch (\Illuminate\Validation\ValidationException $exception) {
                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Stock Opname',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),

            Actions\EditAction::make()
                ->color('warning')
                ->visible(fn () => $this->record->status !== 'approved'),
        ];
    }
}
