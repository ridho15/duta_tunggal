<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ViewVendorPayment extends ViewRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->color('warning'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
            Action::make('view_journal_entries')
                ->label('Lihat Journal Entries')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(fn () => route('filament.admin.resources.journal-entries.index', [
                    'tableFilters[source_type][value]' => 'App\Models\VendorPayment',
                    'tableFilters[source_id][value]' => $this->record->id
                ]))
                ->openUrlInNewTab(),
        ];
    }
}