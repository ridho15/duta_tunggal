<?php

namespace App\Providers;

use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\Invoice;
use App\Models\ManufacturingOrder;
use App\Models\StockMovement;
use App\Models\VendorPaymentDetail;
use App\Observers\DepositLogObserser;
use App\Observers\DepositObserver;
use App\Observers\GlobalActivityObserver;
use App\Observers\InvoiceObserver;
use App\Observers\ManufacturingOrder as ObserversManufacturingOrder;
use App\Observers\StockMovementObserver;
use App\Observers\VendorPaymentDetailObserver;
use App\Services\CabangService;
use App\Services\ChartOfAccountService;
use App\Services\CustomerService;
use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderService;
use App\Services\InvoiceService;
use App\Services\ManufacturingService;
use App\Services\OrderRequestService;
use App\Services\ProductionService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use App\Services\QuotationService;
use App\Services\ReturnProductService;
use App\Services\SalesOrderService;
use App\Services\SupplierService;
use App\Services\SuratJalanService;
use App\Services\WarehouseService;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $loader = AliasLoader::getInstance();
        $loader->alias('Debugbar', Debugbar::class);

        $this->app->bind(QualityControlService::class, function ($app) {
            return new QualityControlService;
        });
        $this->app->bind(ManufacturingService::class, function ($app) {
            return new ManufacturingService;
        });
        $this->app->bind(SalesOrderService::class, function ($app) {
            return new SalesOrderService;
        });
        $this->app->bind(QuotationService::class, function ($app) {
            return new QuotationService;
        });
        $this->app->bind(DeliveryOrderService::class, function ($app) {
            return new DeliveryOrderService;
        });
        $this->app->bind(ReturnProductService::class, function ($app) {
            return new ReturnProductService;
        });
        $this->app->bind(DeliveryOrderItemService::class, function ($app) {
            return new DeliveryOrderItemService;
        });
        $this->app->bind(OrderRequestService::class, function ($app) {
            return new OrderRequestService;
        });
        $this->app->bind(PurchaseOrderService::class, function ($app) {
            return new PurchaseOrderService;
        });
        $this->app->bind(PurchaseReceiptService::class, function ($app) {
            return new PurchaseReceiptService;
        });
        $this->app->bind(ProductService::class, function ($app) {
            return new ProductService;
        });
        $this->app->bind(PurchaseReturnService::class, function ($app) {
            return new PurchaseReturnService;
        });
        $this->app->bind(InvoiceService::class, function ($app) {
            return new InvoiceService;
        });
        $this->app->bind(ProductionService::class, function ($app) {
            return new ProductionService;
        });
        $this->app->bind(ChartOfAccountService::class, function ($app) {
            return new ChartOfAccountService;
        });
        $this->app->bind(WarehouseService::class, function ($app) {
            return new WarehouseService;
        });
        $this->app->bind(SupplierService::class, function ($app) {
            return new SupplierService;
        });
        $this->app->bind(CustomerService::class, function ($app) {
            return new CustomerService;
        });
        $this->app->bind(CabangService::class, function ($app) {
            return new CabangService;
        });
        $this->app->bind(SuratJalanService::class, function ($app) {
            return new SuratJalanService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        StockMovement::observe(StockMovementObserver::class);
        ManufacturingOrder::observe(ObserversManufacturingOrder::class);
        Invoice::observe(InvoiceObserver::class);
        VendorPaymentDetail::observe(VendorPaymentDetailObserver::class);
        Deposit::observe(DepositObserver::class);
        DepositLog::observe(DepositLogObserser::class);
    }
}
