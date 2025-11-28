<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Http\Controllers\HelperController;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
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
        // Removed: invoice_id is no longer used, focus on selected_invoices for multiple invoices

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle backward compatibility for single invoice
        // Removed: invoice_id is no longer used, focus on selected_invoices for multiple invoices

        if (!empty($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $decoded = json_decode($data['selected_invoices'], true);
            if (is_array($decoded)) {
                $data['selected_invoices'] = $decoded;
            }
        }

        foreach (['ppn_import_amount', 'pph22_amount', 'bea_masuk_amount'] as $field) {
            $rawValue = $data[$field] ?? 0;
            if (is_string($rawValue)) {
                $rawValue = HelperController::parseIndonesianMoney($rawValue);
            }
            $data[$field] = (float) ($rawValue ?? 0);
        }

        $invoiceCollection = collect();
        if (!empty($data['selected_invoices']) && is_array($data['selected_invoices'])) {
            $invoiceCollection = Invoice::whereIn('id', $data['selected_invoices'])->get();
        }

        $hasImportInvoice = false;
        foreach ($invoiceCollection as $invoice) {
            if ($invoice->from_model_type === PurchaseOrder::class) {
                $purchaseOrder = $invoice->fromModel;
                if ($purchaseOrder && $purchaseOrder->is_import) {
                    $hasImportInvoice = true;
                    break;
                }
            }
        }

        $hasImportAmounts = ($data['ppn_import_amount'] ?? 0) > 0
            || ($data['pph22_amount'] ?? 0) > 0
            || ($data['bea_masuk_amount'] ?? 0) > 0;

        $data['is_import_payment'] = $hasImportInvoice || $hasImportAmounts || !empty($data['is_import_payment']);

        $hasImportInvoice = false;
        foreach ($invoiceCollection as $invoice) {
            if ($invoice->from_model_type === PurchaseOrder::class) {
                $purchaseOrder = $invoice->fromModel;
                if ($purchaseOrder && $purchaseOrder->is_import) {
                    $hasImportInvoice = true;
                    break;
                }
            }
        }

        $hasImportAmounts = ($data['ppn_import_amount'] ?? 0) > 0
            || ($data['pph22_amount'] ?? 0) > 0
            || ($data['bea_masuk_amount'] ?? 0) > 0;

        $data['is_import_payment'] = $hasImportInvoice || $hasImportAmounts || !empty($data['is_import_payment']);

        if (!$data['is_import_payment']) {
            $data['ppn_import_amount'] = 0;
            $data['pph22_amount'] = 0;
            $data['bea_masuk_amount'] = 0;
        }

        return $data;
    }
}
