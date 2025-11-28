<?php

namespace App\Providers;

use App\Events\TransferPosted;
use App\Listeners\AutoCreateBankReconciliation;
use Illuminate\Support\ServiceProvider;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
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

        // Deklarasi mask global untuk input uang menggunakan format Indonesia
        TextInput::macro('indonesianMoney', function (): TextInput {
            /** @var TextInput $this */
            return $this
                ->prefix('Rp')
                ->mask(fn () => static::moneyMask())
                ->formatStateUsing(fn (?string $state) => $state)
                ->stripCharacters(['Rp', '.', ' '])
                ->numeric();
        });
    }

    protected static function moneyMask(): \Filament\Support\RawJs
    {
        return \Filament\Support\RawJs::make(<<<'JS'
            $input.map(function(value) {
                // Hapus semua karakter non-digit
                value = value.replace(/[^\d]/g, '');
                if (!value) {
                    return '';
                }

                // Format dengan pemisah ribuan titik
                const formatter = new Intl.NumberFormat('id-ID');
                return formatter.format(parseInt(value, 10));
            })
        JS);
    }
}
