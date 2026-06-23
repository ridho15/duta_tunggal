<x-filament-panels::page>
@php
    $accentColor = '#059669';
    $accentLight = '#6ee7b7';
    $iconBg      = '#ecfdf5';
    $heroFrom    = '#ecfdf5';
    $heroTo      = '#a7f3d0';
    $sections = [
        [
            'title' => 'Transaksi Gudang',
            'items' => [
                ['label' => 'Stock Transfer',    'url' => \App\Filament\Resources\StockTransferResource::getUrl(),         'icon' => 'arrows-right-left',        'desc' => 'Pindahkan stok antar gudang/lokasi', 'class' => \App\Filament\Resources\StockTransferResource::class],
                ['label' => 'Stock Adjustment',  'url' => \App\Filament\Resources\StockAdjustmentResource::getUrl(),       'icon' => 'adjustments-horizontal',   'desc' => 'Koreksi selisih stok secara manual', 'class' => \App\Filament\Resources\StockAdjustmentResource::class],
                ['label' => 'Stock Opname',      'url' => \App\Filament\Resources\StockOpnameResource::getUrl(),           'icon' => 'clipboard-document-check', 'desc' => 'Hitung fisik persediaan gudang', 'class' => \App\Filament\Resources\StockOpnameResource::class],
                ['label' => 'Return Product',    'url' => \App\Filament\Resources\ReturnProductResource::getUrl(),         'icon' => 'arrow-uturn-left',         'desc' => 'Proses pengembalian produk dari pelanggan', 'class' => \App\Filament\Resources\ReturnProductResource::class],
            ],
        ],
        [
            'title' => 'Monitoring Inventory',
            'items' => [
                ['label' => 'Inventory Stock',   'url' => \App\Filament\Resources\InventoryStockResource::getUrl(),        'icon' => 'archive-box',              'desc' => 'Monitor posisi stok real-time', 'class' => \App\Filament\Resources\InventoryStockResource::class],
                ['label' => 'Stock Movement',    'url' => \App\Filament\Resources\StockMovementResource::getUrl(),         'icon' => 'arrow-trending-up',        'desc' => 'Riwayat mutasi masuk/keluar stok', 'class' => \App\Filament\Resources\StockMovementResource::class],
                ['label' => 'Konfirmasi Gudang', 'url' => \App\Filament\Resources\WarehouseConfirmationResource::getUrl(), 'icon' => 'check-badge',              'desc' => 'Persetujuan penerimaan/pengeluaran gudang', 'class' => \App\Filament\Resources\WarehouseConfirmationResource::class],
                ['label' => 'Kartu Persediaan',  'url' => \App\Filament\Resources\Reports\InventoryCardResource::getUrl(),'icon' => 'clipboard-document-list',   'desc' => 'Lihat kartu persediaan / stock card', 'class' => \App\Filament\Resources\Reports\InventoryCardResource::class],
                ['label' => 'Laporan Stok',      'url' => \App\Filament\Pages\InventoryReportPage::getUrl(),              'icon' => 'chart-bar-square',         'desc' => 'Laporan stok, mutasi, dan aging inventory', 'class' => \App\Filament\Pages\InventoryReportPage::class],
            ],
        ],
    ];

    $filteredSections = [];
    foreach ($sections as $section) {
        $filteredItems = [];
        foreach ($section['items'] as $item) {
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
        if (!empty($filteredItems)) {
            $section['items'] = $filteredItems;
            $filteredSections[] = $section;
        }
    }
    $sections = $filteredSections;
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="inventory-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-archive-box class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Inventory</div>
            <h1 class="hubv2-hero-title">Inventory</h1>
            <p class="hubv2-hero-subtitle">Kelola transaksi gudang, kontrol stok, dan monitoring inventory dari satu halaman.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ $totalItems }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    @foreach($sections as $section)
    <div class="hubv2-sh">
        <span class="hubv2-sh-dot"></span>
        <span class="hubv2-sh-name">{{ $section['title'] }}</span>
        <span class="hubv2-sh-rule"></span>
    </div>
    <div class="hubv2-grid">
        @foreach($section['items'] as $item)
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
    @endforeach
</div>
</x-filament-panels::page>