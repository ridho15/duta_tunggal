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
use Filament\View\PanelsRenderHook;

class AdminPanelProvider extends PanelProvider
{
    public const THEME = 'resources/css/filament/admin/theme.css';

    /**
     * Tema Vite hanya dipasang bila sudah di-build (`npm run build`). Tanpa ini, deploy yang
     * melewati build frontend (update.sh --quick) akan membuat panel error "Vite manifest not found".
     */
    public static function hasViteTheme(): bool
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return false;
        }

        $entries = json_decode((string) file_get_contents($manifest), true);

        return is_array($entries) && array_key_exists(static::THEME, $entries);
    }

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
            ->font('Plus Jakarta Sans')
            ->brandName('Duta Tunggal ERP')
            // Spesifikasi UI/UX 3.2: teks "DUTA TUNGGAL" 15/800 + badge "ERP" hijau
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString(
                '<span style="display:inline-flex;align-items:center;gap:6px;font-size:15px;font-weight:800;letter-spacing:.3px;color:#111827;">'
                . 'DUTA TUNGGAL'
                . '<span style="font-size:9px;font-weight:700;color:#15803D;border:1px solid #BBF7D0;background:#F0FDF4;border-radius:6px;padding:1px 5px;line-height:14px;">ERP</span>'
                . '</span>'
            ))
            ->brandLogoHeight('1.25rem')
            ->colors([
                'primary' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Gray,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->resources([
                JournalEntryResource::class,
                OtherSaleResource::class,
            ])
            // ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages') // Commented out to avoid Livewire component conflicts
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                MyDashboard::class,
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
                \App\Filament\Pages\ModulAktifPage::class,
                // Tahap 3 — layar HP
                \App\Filament\Pages\Hp\KirimanPage::class,
                \App\Filament\Pages\Hp\KirimanDetailPage::class,
                \App\Filament\Pages\Hp\ReturDoPage::class,
                \App\Filament\Pages\Hp\OpnameHpPage::class,
                \App\Filament\Pages\Hp\CekStokHpPage::class,
                \App\Filament\Pages\BantuanPage::class,
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
            ->when(
                static::hasViteTheme(),
                // Tema Spesifikasi UI/UX Bagian 2 (warna, radius, sidebar 240px, header 50px)
                fn (Panel $panel) => $panel->viteTheme(static::THEME),
            )
            ->sidebarCollapsibleOnDesktop(false)
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('BERANDA'),
                \Filament\Navigation\NavigationGroup::make('PENJUALAN'),
                \Filament\Navigation\NavigationGroup::make('GUDANG'),
                \Filament\Navigation\NavigationGroup::make('PEMBELIAN'),
                \Filament\Navigation\NavigationGroup::make('KEUANGAN'),
                \Filament\Navigation\NavigationGroup::make('LAPORAN'),
                \Filament\Navigation\NavigationGroup::make('DATA MASTER'),
                \Filament\Navigation\NavigationGroup::make('PENGATURAN'),
                // Hanya tampil bila modul Produksi diaktifkan di PENGATURAN → Modul Aktif
                \Filament\Navigation\NavigationGroup::make('PRODUKSI'),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarWidth('15rem')
            ->assets(array_filter([
                // Custom CSS for sale orders
                \Filament\Support\Assets\Css::make('custom-sale-order', asset('css/custom-sale-order.css')),
                // CSS sidebar lama hanya dipakai bila tema Vite belum di-build (lihat hasViteTheme()).
                static::hasViteTheme()
                    ? null
                    : \Filament\Support\Assets\Css::make('filament-sidebar', asset('css/filament-sidebar.css')),
            ]))
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.anti-double-click'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.livewire-error-handler'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.hooks.topbar-user-info'),
            )
            // Tahap 3 — tampilan HP: gaya, pemindai barcode, navigasi bawah (Spesifikasi UI/UX 6.8)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.hooks.hp-styles'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.hp-scripts'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.mobile-bottom-nav'),
            )
            // Tahap 3 — banner mode latihan (hanya bila APP_TRAINING_MODE=true)
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.hooks.training-banner'),
            );
    }
}
