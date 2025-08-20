<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;

    // protected static string $view = 'filament.components.sale-order.form';

    protected static ?string $title = 'Buat Penjualan';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set created_by to current user
        $data['created_by'] = Auth::id();
        
        // Validate credit limit and overdue credits before creating sale order
        if (isset($data['customer_id']) && isset($data['total_amount'])) {
            $customer = Customer::find($data['customer_id']);
            
            if ($customer) {
                $creditService = app(CreditValidationService::class);
                $validation = $creditService->canCustomerMakePurchase($customer, (float)$data['total_amount']);
                
                if (!$validation['can_purchase']) {
                    Notification::make()
                        ->title('Transaksi Tidak Dapat Dilanjutkan')
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

    protected function afterCreate()
    {
        $salesOrderService = app(SalesOrderService::class);
        $salesOrderService->updateTotalAmount($this->getRecord());
    }
}
