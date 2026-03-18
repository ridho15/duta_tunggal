<?php

namespace App\Providers;

use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliverySchedule;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\QualityControl;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\VendorPaymentDetail;
use App\Models\VendorPayment;
use App\Models\VoucherRequest;
use App\Models\Asset;
use App\Models\SaleOrder;
use App\Models\PurchaseReturn;
use App\Models\OtherSale;
use App\Observers\PurchaseReturnObserver;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\JournalEntry;
use App\Models\CashBankTransfer;
use App\Observers\DeliveryOrderObserver;
use App\Observers\DeliveryOrderItemObserver;
use App\Observers\DeliveryScheduleObserver;
use App\Observers\CustomerReceiptObserver;
use App\Observers\DepositLogObserser;
use App\Observers\DepositObserver;
use App\Observers\GlobalActivityObserver;
use App\Observers\InvoiceObserver;
use App\Observers\JournalEntryObserver;
use App\Observers\QualityControlObserver;
use App\Observers\ManufacturingOrder as ObserversManufacturingOrder;
use App\Observers\MaterialIssueObserver;
use App\Observers\MaterialIssueItemObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductionObserver;
use App\Observers\StockMovementObserver;
use App\Observers\StockReservationObserver;
use App\Observers\StockTransferItemObserver;
use App\Observers\VendorPaymentDetailObserver;
use App\Observers\VoucherRequestObserver;
use App\Observers\AssetObserver;
use App\Observers\VendorPaymentObserver;
use App\Observers\SaleOrderObserver;
use App\Observers\PurchaseReceiptObserver;
use App\Observers\PurchaseReceiptItemObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\CashBankTransferObserver;
use App\Observers\OtherSaleObserver;
use App\Helpers\MoneyHelper;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Mask;
use Filament\Tables\Columns\Summarizers\Sum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $loader = AliasLoader::getInstance();
        $loader->alias('Debugbar', Debugbar::class);

        // Ensure Filament classes are loaded before registering macros
        class_exists(\Filament\Forms\Components\TextInput::class);
        class_exists(\Filament\Tables\Table::class);

        // Register Filament macros
        $this->registerFilamentMacros();
    }

    protected function registerFilamentMacros(): void
    {
        // Pastikan tampilan angka uang di Table menggunakan format Indonesia
        Table::$defaultCurrency = 'IDR';
        Table::$defaultNumberLocale = 'id';

        // ---------------------------------------------------------------
        // Rupiah macro for TextColumn (Table columns)
        // Usage: TextColumn::make('price')->rupiah()
        // ---------------------------------------------------------------
        TextColumn::macro('rupiah', function (): TextColumn {
            /** @var TextColumn $this */
            return $this->formatStateUsing(fn($state) => MoneyHelper::rupiah($state));
        });

        // ---------------------------------------------------------------
        // Rupiah macro for TextEntry (Infolist entries)
        // Usage: TextEntry::make('total')->rupiah()
        // ---------------------------------------------------------------
        TextEntry::macro('rupiah', function (): TextEntry {
            /** @var TextEntry $this */
            return $this->formatStateUsing(fn($state) => MoneyHelper::rupiah($state));
        });

        // ---------------------------------------------------------------
        // Rupiah macro for Sum Summarizer (Table column summaries)
        // Usage: TextColumn::make('amount')->summarize(Sum::make()->rupiah())
        // ---------------------------------------------------------------
        \Filament\Tables\Columns\Summarizers\Sum::macro('rupiah', function () {
            /** @var \Filament\Tables\Columns\Summarizers\Sum $this */
            return $this->formatStateUsing(fn($state) => MoneyHelper::rupiah($state));
        });

        // ---------------------------------------------------------------
        // indonesianMoney macro for TextInput (Form inputs)
        // Usage: TextInput::make('amount')->indonesianMoney()
        //
        // Behaviour:
        //  - JS mask: real-time thousand-dot formatting while user types
        //  - formatStateUsing: loads DB numeric value as formatted string
        //  - dehydrateStateUsing: parses user input back to float for DB storage
        // ---------------------------------------------------------------
        TextInput::macro('indonesianMoney', function (): TextInput {
            /** @var TextInput $this */

            return $this
                ->prefix('Rp')
                ->placeholder('500.000')
                ->mask(\Filament\Support\RawJs::make(<<<'JS'
            $money($input, ',', '.', 0)
        JS))
                ->formatStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return '';
                    }

                    return number_format(\App\Helpers\MoneyHelper::parse($state), 0, ',', '.');
                })
                ->rules([function () {
                    return function ($attribute, $value, $fail) {
                        if ($value === null || $value === '') {
                            return;
                        }
                        $parsed = \App\Helpers\MoneyHelper::parse($value);
                        if (! is_numeric($parsed)) {
                            $fail('Nilai nominal tidak valid. Contoh format: 1.000.000');
                        }
                    };
                }])
                ->dehydrateStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return null;
                    }

                    return \App\Helpers\MoneyHelper::parse($state);
                });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        StockMovement::observe(StockMovementObserver::class);
        StockReservation::observe(StockReservationObserver::class);
        ManufacturingOrder::observe(ObserversManufacturingOrder::class);
        MaterialIssue::observe(MaterialIssueObserver::class);
        MaterialIssueItem::observe(MaterialIssueItemObserver::class);
        Production::observe(ProductionObserver::class);
        Invoice::observe(InvoiceObserver::class);
        VendorPayment::observe(VendorPaymentObserver::class);
        VendorPaymentDetail::observe(VendorPaymentDetailObserver::class);
        CustomerReceipt::observe(CustomerReceiptObserver::class);
        // CustomerReceiptItem::observe(CustomerReceiptItemObserver::class);
        DeliveryOrder::observe(DeliveryOrderObserver::class);
        DeliveryOrderItem::observe(DeliveryOrderItemObserver::class);
        DeliverySchedule::observe(DeliveryScheduleObserver::class);
        Deposit::observe(DepositObserver::class);
        DepositLog::observe(DepositLogObserser::class);
        VoucherRequest::observe(VoucherRequestObserver::class);
        Asset::observe(AssetObserver::class);
        PurchaseReceipt::observe(PurchaseReceiptObserver::class);
        PurchaseReceiptItem::observe(PurchaseReceiptItemObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);
        PurchaseReturn::observe(PurchaseReturnObserver::class);
        SaleOrder::observe(SaleOrderObserver::class);
        Product::observe(ProductObserver::class);
        JournalEntry::observe(JournalEntryObserver::class);
        QualityControl::observe(QualityControlObserver::class);
        CashBankTransfer::observe(CashBankTransferObserver::class);
        OtherSale::observe(OtherSaleObserver::class);
    }
}
