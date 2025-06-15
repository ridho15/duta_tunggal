<?php

namespace App\Providers;

use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderService;
use App\Services\ManufacturingService;
use App\Services\PurchaseOrderService;
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

        $this->app->bind(
            PurchaseOrderService::class,
            QualityControlService::class,
            ManufacturingService::class,
            SalesOrderService::class,
            QuotationService::class,
            DeliveryOrderService::class,
            ReturnProductService::class,
            DeliveryOrderItemService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
