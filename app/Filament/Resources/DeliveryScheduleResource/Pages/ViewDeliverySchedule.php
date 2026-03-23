<?php

namespace App\Filament\Resources\DeliveryScheduleResource\Pages;

use App\Filament\Resources\DeliveryScheduleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliverySchedule extends ViewRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print_surat_kerja')
                ->label('Print Surat Kerja')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->visible(fn () => in_array($this->record->delivery_method, ['internal', 'kurir_internal']))
                ->action(fn () => DeliveryScheduleResource::streamWorkOrderPdf($this->record)),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
