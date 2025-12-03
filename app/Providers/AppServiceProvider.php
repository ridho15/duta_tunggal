<?php

namespace App\Providers;

use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\FinishedGoodsCompletion;
use App\Models\Invoice;
use App\Models\ManufacturingOrder;
use App\Models\MaterialFulfillment;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\VendorPaymentDetail;
use App\Models\VendorPayment;
use App\Models\VoucherRequest;
use App\Models\Asset;
use App\Models\SaleOrder;
use App\Models\PurchaseReceipt;
use App\Models\JournalEntry;
use App\Observers\DeliveryOrderObserver;
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
use App\Observers\VendorPaymentDetailObserver;
use App\Observers\VoucherRequestObserver;
use App\Observers\AssetObserver;
use App\Observers\VendorPaymentObserver;
use App\Observers\SaleOrderObserver;
use App\Observers\PurchaseReceiptObserver;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TextInput\Mask;
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
        // Log that we're registering macros
        \Illuminate\Support\Facades\Log::info('Registering Filament macros in AppServiceProvider');

        // Pastikan tampilan angka uang di Table menggunakan format Indonesia
        Table::$defaultCurrency = 'IDR';
        Table::$defaultNumberLocale = 'id';

        // Deklarasi mask global untuk input uang menggunakan format Indonesia
        TextInput::macro('indonesianMoney', function (): TextInput {
            /** @var TextInput $this */
            return $this
                ->prefix('Rp')
                ->placeholder('500.000')
                ->formatStateUsing(function ($state) {
                    // Return the numeric value as-is, let Filament handle display formatting
                    if ($state === null || $state === '') {
                        return 0;
                    }
                    return number_format(round($state, 2), 0, ',', '.');
                })
                ->dehydrateStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return 0;
                    }

                    // Parse Indonesian money format input from user
                    $str = (string) $state;
                    // Keep only digits, dots and commas
                    $clean = preg_replace('/[^\d\.,]/', '', $str);
                    if ($clean === '') {
                        return 0;
                    }

                    // Simple approach: remove all separators and treat as integer, then divide by 100 if there are decimal places
                    // This handles both Indonesian (1.000.000) and American (1,000,000.00) formats
                    $hasComma = strpos($clean, ',') !== false;
                    $hasDot = strpos($clean, '.') !== false;

                    if ($hasComma && $hasDot) {
                        // Both present - determine which is decimal separator
                        // If dot is followed by 1-2 digits and preceded by 3 digits, it's likely decimal
                        $dotPos = strrpos($clean, '.');
                        $afterDot = strlen($clean) - $dotPos - 1;
                        if ($afterDot >= 1 && $afterDot <= 2) {
                            // Dot is decimal separator, comma is thousands
                            $clean = str_replace(',', '', $clean);
                        } else {
                            // Assume Indonesian format: dot is thousands, comma is decimal
                            $clean = str_replace('.', '', $clean);
                            $clean = str_replace(',', '.', $clean);
                        }
                    } elseif ($hasDot) {
                        // Only dots
                        if (preg_match('/\.\d{1,2}$/', $clean)) {
                            // Ends with dot followed by 1-3 digits - dot is decimal
                            // Remove commas if any (shouldn't be there)
                            $clean = str_replace(',', '', $clean);
                        } else {
                            // Dots are thousands separators
                            $clean = str_replace('.', '', $clean);
                        }
                    } elseif ($hasComma) {
                        // Only commas
                        if (preg_match('/,\d{1,2}$/', $clean)) {
                            // Ends with comma followed by 1-3 digits - comma is decimal
                            $clean = str_replace(',', '.', $clean);
                        } else {
                            // Commas are thousands separators
                            $clean = str_replace(',', '', $clean);
                        }
                    }

                    // Return the cleaned float value
                    $value = (float) $clean;
                    return $value;
                });
                // ->helperText('Format: 500.000 (gunakan titik sebagai pemisah ribuan)');
        });

        \Illuminate\Support\Facades\Log::info('indonesianMoney macro registered in AppServiceProvider');
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
        Deposit::observe(DepositObserver::class);
        DepositLog::observe(DepositLogObserser::class);
        VoucherRequest::observe(VoucherRequestObserver::class);
        Asset::observe(AssetObserver::class);
        PurchaseReceipt::observe(PurchaseReceiptObserver::class);
        // PurchaseOrder::observe(PurchaseOrderObserver::class);
        SaleOrder::observe(SaleOrderObserver::class);
        Product::observe(ProductObserver::class);
        JournalEntry::observe(JournalEntryObserver::class);
        QualityControl::observe(QualityControlObserver::class);
    }
}
