<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\ArApManagementPage;
use App\Filament\Pages\BalanceSheetPage;
use App\Filament\Pages\BukuBesarPage;
use App\Filament\Pages\IncomeStatementPage;
use App\Filament\Pages\MyDashboard;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries;
use App\Filament\Widgets\AssetStatsWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('/admin')
            ->login()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                JournalEntryResource::class,
            ])
            // ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages') // Commented out to avoid Livewire component conflicts
            ->pages([
                MyDashboard::class,
                ArApManagementPage::class,
                BalanceSheetPage::class,
                BukuBesarPage::class,
                IncomeStatementPage::class,
                GroupedJournalEntries::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                \App\Filament\Widgets\AssetStatsWidget::class,
                // AccountWidget::class, // Commented out - widget doesn't exist
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->assets([
                // Custom CSS for sale orders
                \Filament\Support\Assets\Css::make('custom-sale-order', asset('css/custom-sale-order.css')),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
    }
}
