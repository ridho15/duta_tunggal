<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\ArApManagementPage;
use App\Filament\Pages\AlkGraficPage;
use App\Filament\Pages\AccountingHubPage;
use App\Filament\Pages\DashboardHubPage;
use App\Filament\Pages\AssetManagementHubPage;
use App\Filament\Pages\BalanceSheetPage;
use App\Filament\Pages\BukuBesarPage;
use App\Filament\Pages\CostOfGoodsManufacturingPage;
use App\Filament\Pages\DeliveryHubPage;
use App\Filament\Pages\DrillDownFinancialReportPage;
use App\Filament\Pages\FinancialStatementPage;
use App\Filament\Pages\FinancePurchaseHubPage;
use App\Filament\Pages\FinanceReportHubPage;
use App\Filament\Pages\FinanceSalesHubPage;
use App\Filament\Pages\IncomeStatementPage;
use App\Filament\Pages\InventoryHubPage;
use App\Filament\Pages\InventoryReportPage;
use App\Filament\Pages\JournalConsolidationPage;
use App\Filament\Pages\ManufacturingHubPage;
use App\Filament\Pages\MasterDataHubPage;
use App\Filament\Pages\PaymentHubPage;
use App\Filament\Pages\SalesHubPage;
use App\Filament\Pages\UserRolesManagementHubPage;
use App\Filament\Pages\TrialBalancePage;
use App\Filament\Pages\ProfitLossMultiDivisionPage;
use App\Filament\Pages\MyDashboard;
use App\Filament\Pages\PurchaseHubPage;
use App\Filament\Pages\PurchaseReportPage;
use App\Filament\Pages\SalesReportPage;
use App\Filament\Pages\ViewAgeingReport;
use App\Filament\Pages\WarehouseHubPage;
use App\Filament\Resources\OtherSaleResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Enums\ThemeMode;
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
            ->defaultThemeMode(ThemeMode::Light)
            ->darkMode(false)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                JournalEntryResource::class,
                OtherSaleResource::class,
            ])
            // ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages') // Commented out to avoid Livewire component conflicts
            ->pages([
                DashboardHubPage::class,
                SalesHubPage::class,
                PurchaseHubPage::class,
                DeliveryHubPage::class,
                AccountingHubPage::class,
                InventoryHubPage::class,
                MasterDataHubPage::class,
                UserRolesManagementHubPage::class,
                ManufacturingHubPage::class,
                MyDashboard::class,
                FinanceSalesHubPage::class,
                FinancePurchaseHubPage::class,
                PaymentHubPage::class,
                FinanceReportHubPage::class,
                AssetManagementHubPage::class,
                WarehouseHubPage::class,
                InventoryReportPage::class,
                ArApManagementPage::class,
                BalanceSheetPage::class,
                BukuBesarPage::class,
                IncomeStatementPage::class,
                TrialBalancePage::class,
                DrillDownFinancialReportPage::class,
                FinancialStatementPage::class,
                ProfitLossMultiDivisionPage::class,
                CostOfGoodsManufacturingPage::class,
                AlkGraficPage::class,
                JournalConsolidationPage::class,
                SalesReportPage::class,
                PurchaseReportPage::class,
                ViewAgeingReport::class,
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
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Dashboard')->icon('heroicon-o-home'),
                \Filament\Navigation\NavigationGroup::make('Penjualan')->icon('heroicon-o-shopping-cart'),
                \Filament\Navigation\NavigationGroup::make('Pembelian')->icon('heroicon-o-shopping-bag'),
                \Filament\Navigation\NavigationGroup::make('Pengiriman')->icon('heroicon-o-truck'),
                \Filament\Navigation\NavigationGroup::make('Akuntansi')->icon('heroicon-o-calculator'),
                \Filament\Navigation\NavigationGroup::make('Inventory')->icon('heroicon-o-archive-box'),
                \Filament\Navigation\NavigationGroup::make('Master Data')->icon('heroicon-o-circle-stack'),
                \Filament\Navigation\NavigationGroup::make('Manajemen User dan Role')->icon('heroicon-o-users'),
                \Filament\Navigation\NavigationGroup::make('Manufaktur')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->assets([
                // Custom CSS for sale orders
                \Filament\Support\Assets\Css::make('custom-sale-order', secure_asset('css/custom-sale-order.css')),
                // Custom CSS for filament sidebar
                \Filament\Support\Assets\Css::make('filament-sidebar', secure_asset('css/filament-sidebar.css')),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s');
    }
}
