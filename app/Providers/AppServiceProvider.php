<?php

namespace App\Providers;

use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
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
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\VendorPaymentDetail;
use App\Models\VendorPayment;
use App\Models\VoucherRequest;
use App\Models\Asset;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\JournalEntry;
use App\Observers\CustomerReceiptItemObserver;
use App\Observers\CustomerReceiptObserver;
use App\Observers\DepositLogObserser;
use App\Observers\DepositObserver;
use App\Observers\GlobalActivityObserver;
use App\Observers\InvoiceObserver;
use App\Observers\JournalEntryObserver;
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
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseReceiptObserver;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
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
                    if ($state === null || $state === '') {
                        return '0';
                    }

                    // Normalize and parse different possible input formats:
                    // - "100.000" (thousand separator)
                    // - "100000.00" (dot as decimal)
                    // - "100,000.00" or "100.000,00" (mixed separators)
                    $str = (string) $state;
                    // Keep only digits, dots and commas
                    $clean = preg_replace('/[^\d\.,]/', '', $str);
                    if ($clean === '') {
                        return '0';
                    }

                    // If both separators present, assume '.' thousands and ',' decimal
                    if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
                        $clean = str_replace('.', '', $clean); // remove thousands
                        $clean = str_replace(',', '.', $clean); // make decimal dot
                    } elseif (substr_count($clean, '.') > 1) {
                        // multiple dots -> dots are thousands separators
                        $clean = str_replace('.', '', $clean);
                    } elseif (strpos($clean, '.') !== false) {
                        // single dot: decide if it's thousands (last group length 3)
                        $parts = explode('.', $clean);
                        $last = end($parts);
                        if (strlen($last) === 3) {
                            // treat dot as thousands separator
                            $clean = str_replace('.', '', $clean);
                        }
                        // otherwise keep dot as decimal separator
                    } elseif (strpos($clean, ',') !== false) {
                        // only comma present: decide if it's thousands (group length 3)
                        $parts = explode(',', $clean);
                        $last = end($parts);
                        if (strlen($last) === 3) {
                            // treat comma as thousands
                            $clean = str_replace(',', '', $clean);
                        } else {
                            // treat comma as decimal separator
                            $clean = str_replace(',', '.', $clean);
                        }
                    }

                    // Parse to float then format as integer rupiah string (no decimals)
                    $value = (float) $clean;
                    return number_format((float)$value, 0, ',', '.');
                })
                ->dehydrateStateUsing(function ($state) {
                    if ($state === null || $state === '') {
                        return 0;
                    }

                    $str = (string) $state;
                    $clean = preg_replace('/[^\d\.,]/', '', $str);
                    if ($clean === '') {
                        return 0;
                    }

                    if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
                        $clean = str_replace('.', '', $clean);
                        $clean = str_replace(',', '.', $clean);
                    } elseif (substr_count($clean, '.') > 1) {
                        $clean = str_replace('.', '', $clean);
                    } elseif (strpos($clean, '.') !== false) {
                        $parts = explode('.', $clean);
                        $last = end($parts);
                        if (strlen($last) === 3) {
                            $clean = str_replace('.', '', $clean);
                        }
                    } elseif (strpos($clean, ',') !== false) {
                        $parts = explode(',', $clean);
                        $last = end($parts);
                        if (strlen($last) === 3) {
                            $clean = str_replace(',', '', $clean);
                        } else {
                            $clean = str_replace(',', '.', $clean);
                        }
                    }

                    // Convert to float then round to nearest whole rupiah and return int
                    $value = (float) $clean;
                    return (int) round($value);
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
        CustomerReceiptItem::observe(CustomerReceiptItemObserver::class);
        Deposit::observe(DepositObserver::class);
        DepositLog::observe(DepositLogObserser::class);
        VoucherRequest::observe(VoucherRequestObserver::class);
        Asset::observe(AssetObserver::class);
        PurchaseReceipt::observe(PurchaseReceiptObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        Product::observe(ProductObserver::class);
        JournalEntry::observe(JournalEntryObserver::class);
        Livewire::component('database-notifications', \App\Livewire\DatabaseNotifications::class);
        Livewire::component('filament.livewire.database-notifications', \App\Livewire\DatabaseNotifications::class);
    }
}
