<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Http\Controllers\HelperController;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Quotation;
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateSaleOrder extends CreateRecord
{
    protected static string $resource = SaleOrderResource::class;

    // protected static string $view = 'filament.components.sale-order.form';

    protected static ?string $title = 'Buat Sales Order';

    public function mount(int $record = null): void
    {
        parent::mount($record);

        // Check if quotation_id is passed in the URL
        $quotationId = request()->query('quotation_id');
        if ($quotationId) {
            $this->form->fill([
                'quotation_id' => $quotationId,
                'reference_type' => 2, // Refer Quotation
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = SaleOrderResource::normalizeFormDataForPersist($data);

        // Set created_by to current user
        $data['created_by'] = Auth::id();

        // Enforce branch inheritance from quotation when SO is created from quotation
        if (!empty($data['quotation_id'])) {
            $quotation = Quotation::find($data['quotation_id']);
            if ($quotation && !empty($quotation->cabang_id)) {
                $data['cabang_id'] = $quotation->cabang_id;
            }
        }
        
        // Validate credit limit and overdue credits before creating sale order
        if (isset($data['customer_id']) && isset($data['total_amount'])) {
            $customer = Customer::find($data['customer_id']);
            
            if ($customer) {
                $creditService = app(CreditValidationService::class);
                $totalForCredit = CurrencyConversionResolver::convertToIdr(
                    MoneyHelper::parseHighPrecision(SaleOrderResource::parseCurrencyState($data['total_amount'] ?? 0)),
                    is_numeric($data['currency_id'] ?? null) ? (int) $data['currency_id'] : null,
                    false
                );
                $validation = $creditService->canCustomerMakePurchase($customer, (float) $totalForCredit);
                
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
