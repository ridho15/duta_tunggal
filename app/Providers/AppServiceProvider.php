<?php

namespace App\Providers;

use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\CustomerReceiptItem;
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
use App\Observers\CustomerReceiptItemObserver;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Profiler request (config/perf.php): satu instance per aplikasi agar handle() dan terminate() berbagi state.
        $this->app->singleton(\App\Support\Perf\ProfileRecorder::class);

        $loader = AliasLoader::getInstance();
        if (class_exists(Debugbar::class)) {
            $loader->alias('Debugbar', Debugbar::class);
        }

        if ($this->app->environment(['local', 'testing']) && class_exists(\Laravel\Dusk\DuskServiceProvider::class)) {
            $this->app->register(\Laravel\Dusk\DuskServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.tailwind');

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', 'guest');
            return Limit::perMinute(5)->by(strtolower($email) . '|' . $request->ip());
        });

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
