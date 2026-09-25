<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\StockOpnameResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockOpname extends EditRecord
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
                    } catch (ValidationException $exception) {
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
                    } catch (ValidationException $exception) {
                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Stock Opname',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),

            Actions\Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'completed')
                ->requiresConfirmation()
                ->modalHeading('Setujui Stock Opname')
                ->modalDescription('Apakah Anda yakin ingin menyetujui stock opname ini? Mutasi stok dan jurnal akuntansi penyesuaian akan dibuat.')
                ->modalSubmitActionLabel('Ya, Setujui')
                ->action(function () {
                    try {
                        app(\App\Services\StockOpnameService::class)->approveStockOpname($this->record, \Illuminate\Support\Facades\Auth::id());

                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: true,
                            title: 'Information',
                            message: 'Stock opname berhasil disetujui, mutasi stok fisik dan jurnal penyesuaian sudah dicatat.'
                        );
                        $this->refreshFormData(['status', 'approved_by', 'approved_at']);
                    } catch (ValidationException $exception) {
                        \App\Http\Controllers\HelperController::sendNotification(
                            isSuccess: false,
                            title: 'Validasi Stock Opname',
                            message: collect($exception->errors())->flatten()->implode("\n")
                        );
                    }
                }),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn () => $this->record->status !== 'approved'),
        ];
    }

    protected function beforeSave(): void
    {
        if ($this->record->status === 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Stock opname yang sudah disetujui tidak dapat diubah.',
            ]);
        }
    }
}
