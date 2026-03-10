<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Guard against other_fee stored as integer 0 in DB (non-JSON value)
        // When DB stores 0, the Repeater must receive [] not int(0)
        $rawOtherFee = $this->record->getAttributes()['other_fee'] ?? null;
        $decoded = ($rawOtherFee !== null && $rawOtherFee !== '') ? @json_decode($rawOtherFee, true) : null;
        $data['other_fee'] = is_array($decoded) ? $decoded : [];

        return $data;
    }
}
