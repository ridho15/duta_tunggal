<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorPayment extends EditRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Handle backward compatibility - if no selected_invoices but has invoice_id
        if (empty($data['selected_invoices']) && !empty($data['invoice_id'])) {
            $data['selected_invoices'] = [$data['invoice_id']];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle backward compatibility for single invoice
        if (!empty($data['selected_invoices']) && empty($data['invoice_id'])) {
            // If multiple invoices selected, set invoice_id to first one for compatibility
            $data['invoice_id'] = $data['selected_invoices'][0] ?? null;
        }

        return $data;
    }
}
