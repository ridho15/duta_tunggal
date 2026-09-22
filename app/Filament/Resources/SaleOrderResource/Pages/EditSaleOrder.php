<?php

namespace App\Filament\Resources\SaleOrderResource\Pages;

use App\Filament\Resources\SaleOrderResource;
use App\Services\SalesOrderService;
use App\Services\CreditValidationService;
use App\Models\Customer;
use App\Support\CurrencyConversionResolver;
use App\Helpers\MoneyHelper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class EditSaleOrder extends EditRecord
{
    protected static string $resource = SaleOrderResource::class;

    protected static string $view = 'filament.resources.sale-order-resource.pages.edit-sale-order';

    protected static ?string $title = 'Ubah Sales Order';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (! in_array($this->record->status, ['draft', 'request_approve'])) {
            Notification::make()
                ->title('Sales Order Terkunci')
                ->body('Sales Order dengan status ' . ucfirst(str_replace('_', ' ', $this->record->status)) . ' sudah tidak dapat diubah.')
                ->warning()
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_sale_order')
                ->label('Lihat Sales Order')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->url(fn () => route('filament.admin.resources.sale-orders.view', $this->getRecord())),
            DeleteAction::make()
                ->visible(fn ($record) => $record->status === 'draft')
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = SaleOrderResource::normalizeFormDataForPersist($data);

        // Validate credit limit and overdue credits before saving sale order
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Set shipped_to to customer address if it's empty
        if (empty($data['shipped_to']) && isset($data['customer_id'])) {
            $customer = Customer::find($data['customer_id']);
            if ($customer && $customer->address) {
                $data['shipped_to'] = $customer->address;
            }
        }

        return $data;
    }

    protected function afterSave()
    {
        $salesOrderService = new SalesOrderService;
        $salesOrderService->updateTotalAmount($this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
