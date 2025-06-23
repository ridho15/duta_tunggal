<?php

namespace App\Providers;

use App\Models\ReturnProduct;
use App\Models\StockMovement;
use App\Observers\StockMovementObserver;
use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderService;
use App\Services\InvoiceService;
use App\Services\ManufacturingService;
use App\Services\OrderRequestService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use App\Services\QuotationService;
use App\Services\ReturnProductService;
use App\Services\SalesOrderService;
use Barryvdh\Debugbar\Facades\Debugbar;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        StockMovement::observe(StockMovementObserver::class);
    }
}
