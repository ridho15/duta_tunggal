<?php

namespace App\Filament\Resources\DeliveryScheduleResource\Pages;

use App\Filament\Resources\DeliveryScheduleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliverySchedule extends ViewRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('print_surat_kerja')
                    ->label('Print Surat Kerja')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->visible(fn() => in_array($this->record->delivery_method, ['internal', 'kurir_internal']))
                    ->action(fn() => DeliveryScheduleResource::streamWorkOrderPdf($this->record)),
                Action::make('set_on_the_way')
                    ->label('Mulai Pengiriman')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Mulai Pengiriman')
                    ->modalDescription('Ubah status jadwal ini menjadi "Sedang Berjalan"?')
                    ->visible(fn() => in_array($this->record->status, ['pending']))
                    ->action(fn() => $this->record->update(['status' => 'on_the_way'])),
                Action::make('set_delivered')
                    ->label('Tandai Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Selesai')
                    ->modalDescription('Tandai jadwal pengiriman ini sebagai selesai/terkirim?')
                    ->visible(fn() => in_array($this->record->status, ['on_the_way', 'pending']))
                    ->action(fn() => $this->record->update(['status' => 'delivered'])),
                Action::make('set_failed')
                    ->label('Tandai Gagal')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Pengiriman Gagal')
                    ->modalDescription('Tandai jadwal pengiriman ini sebagai gagal?')
                    ->visible(fn() => in_array($this->record->status, ['on_the_way', 'pending']))
                    ->action(fn() => $this->record->update(['status' => 'failed'])),
                Action::make('set_cancelled')
                    ->label('Batalkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn() => in_array($this->record->status, ['pending']))
                    ->action(fn() => $this->record->update(['status' => 'cancelled'])),
                EditAction::make()->icon('heroicon-o-pencil')->label('Edit Jadwal'),
                DeleteAction::make()->icon('heroicon-o-trash')->label('Hapus Jadwal'),
            ])->button()->label('Aksi'),
        ];
    }
}
