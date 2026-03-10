<?php

namespace App\Providers;

use App\Events\TransferPosted;
use App\Listeners\AutoCreateBankReconciliation;
use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogLogout;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            LogSuccessfulLogin::class,
        ],
        Logout::class => [
            LogLogout::class
        ],
        TransferPosted::class => [
            AutoCreateBankReconciliation::class,
        ],
    ];
    public function register(): void
    {
        // Register Filament macros for Indonesian money formatting
        $this->registerFilamentMacros();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    protected function registerFilamentMacros(): void
    {
        // Pastikan tampilan angka uang di Table menggunakan format Indonesia
        Table::$defaultCurrency = 'IDR';
        Table::$defaultNumberLocale = 'id';

        // NOTE: indonesianMoney TextInput macro is defined in AppServiceProvider.
        // Do not redefine it here to avoid overriding the robust PHP-side parsing version.
    }
}
