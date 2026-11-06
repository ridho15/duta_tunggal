<?php

namespace App\Providers;

use App\Events\TransferPosted;
use App\Listeners\AutoCreateBankReconciliation;
use Illuminate\Support\ServiceProvider;
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
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
