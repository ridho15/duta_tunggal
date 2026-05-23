<x-filament-panels::page>
@php
    $accentColor = '#1d4ed8';
    $accentLight = '#93c5fd';
    $iconBg      = '#eff6ff';
    $heroFrom    = '#eff6ff';
    $heroTo      = '#dbeafe';
    $items = [
        ['label' => 'Finance Dashboard',   'url' => \App\Filament\Pages\MyDashboard::getUrl(),          'icon' => 'chart-bar',           'desc' => 'Dashboard widget keuangan dan operasional utama', 'class' => \App\Filament\Pages\MyDashboard::class],
        ['label' => 'Laporan Penjualan',   'url' => \App\Filament\Pages\SalesReportPage::getUrl(),     'icon' => 'chart-bar-square',    'desc' => 'Rekap transaksi & performa penjualan', 'class' => \App\Filament\Pages\SalesReportPage::class],
        ['label' => 'Laporan Pembelian',   'url' => \App\Filament\Pages\PurchaseReportPage::getUrl(),  'icon' => 'shopping-cart',       'desc' => 'Rekap transaksi & aktivitas pembelian', 'class' => \App\Filament\Pages\PurchaseReportPage::class],
    ];

    $filteredItems = [];
    foreach ($items as $item) {
        $class = $item['class'] ?? null;
        if ($class) {
            if (is_subclass_of($class, \Filament\Resources\Resource::class) && !$class::canViewAny()) {
                continue;
            }
            if (is_subclass_of($class, \Filament\Pages\Page::class) && !$class::canAccess()) {
                continue;
            }
        }
        $filteredItems[] = $item;
    }
    $items = $filteredItems;
@endphp

@include('filament.pages.partials.hub-styles')

<div id="dashboard-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-home class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Dashboard</div>
            <h1 class="hubv2-hero-title">Dashboard</h1>
            <p class="hubv2-hero-subtitle">Pintu masuk untuk dashboard keuangan, laporan penjualan, dan laporan pembelian.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ count($items) }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    <div class="hubv2-grid">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="hubv2-card" data-hub-card>
            <div class="hubv2-ci" style="background:{{ $iconBg }};color:{{ $accentColor }};">
                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="w-5 h-5" />
            </div>
            <div class="hubv2-cb">
                <span class="hubv2-cl">{{ $item['label'] }}</span>
                <span class="hubv2-cd">{{ $item['desc'] }}</span>
            </div>
            <div class="hubv2-ca">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </div>
        </a>
        @endforeach
    </div>
</div>
</x-filament-panels::page>