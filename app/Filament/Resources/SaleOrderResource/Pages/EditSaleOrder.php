<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class EditSaleOrder extends EditRecord
{
    protected static string $resource = SaleOrderResource::class;

    // protected static string $view = 'filament.components.sale-order.form';

    protected static ?string $title = 'Ubah Penjualan';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_sale_order')
                ->label('Lihat Penjualan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->url(fn () => route('filament.admin.resources.sale-orders.view', $this->getRecord())),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate credit limit and overdue credits before saving sale order
        if (isset($data['customer_id']) && isset($data['total_amount'])) {
            $customer = Customer::find($data['customer_id']);

            if ($customer) {
                $creditService = app(CreditValidationService::class);
                $validation = $creditService->canCustomerMakePurchase($customer, (float)$data['total_amount']);

                if (!$validation['can_purchase']) {
                    Notification::make()
                        ->title('Transaksi Tidak Dapat Disimpan')
                        ->body(implode('<br>', $validation['messages']))
                        ->danger()
                        ->persistent()
                        ->send();

                    throw ValidationException::withMessages([
                        'customer_id' => implode(' ', $validation['messages'])
                    ]);
                }

                // Show warnings if any
                if (!empty($validation['warnings'])) {
                    Notification::make()
                        ->title('Peringatan Kredit')
                        ->body(implode('<br>', $validation['warnings']))
                        ->warning()
                        ->send();
                }
            }
        }


        return $data;
    }

    protected function afterSave()
    {
        $salesOrderService = new SalesOrderService;
        $salesOrderService->updateTotalAmount($this->getRecord());
    }
}
