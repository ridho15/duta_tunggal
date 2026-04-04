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
                ['label' => 'Stock Transfer',    'url' => \App\Filament\Resources\StockTransferResource::getUrl(),         'icon' => 'arrows-right-left',        'desc' => 'Pindahkan stok antar gudang/lokasi'],
                ['label' => 'Stock Adjustment',  'url' => \App\Filament\Resources\StockAdjustmentResource::getUrl(),       'icon' => 'adjustments-horizontal',   'desc' => 'Koreksi selisih stok secara manual'],
                ['label' => 'Stock Opname',      'url' => \App\Filament\Resources\StockOpnameResource::getUrl(),           'icon' => 'clipboard-document-check', 'desc' => 'Hitung fisik persediaan gudang'],
                ['label' => 'Return Product',    'url' => \App\Filament\Resources\ReturnProductResource::getUrl(),         'icon' => 'arrow-uturn-left',          'desc' => 'Proses pengembalian produk dari pelanggan'],
            ],
        ],
        [
            'title' => 'Monitoring & Konfirmasi',
            'items' => [
                ['label' => 'Inventory Stock',   'url' => \App\Filament\Resources\InventoryStockResource::getUrl(),        'icon' => 'archive-box',              'desc' => 'Monitor posisi stok real-time'],
                ['label' => 'Stock Movement',    'url' => \App\Filament\Resources\StockMovementResource::getUrl(),         'icon' => 'arrow-trending-up',        'desc' => 'Riwayat mutasi masuk/keluar stok'],
                ['label' => 'Konfirmasi Gudang', 'url' => \App\Filament\Resources\WarehouseConfirmationResource::getUrl(), 'icon' => 'check-badge',              'desc' => 'Persetujuan penerimaan/pengeluaran gudang'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="warehouse-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-archive-box class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Gudang</div>
            <h1 class="hubv2-hero-title">Pusat Gudang</h1>
            <p class="hubv2-hero-subtitle">Kelola transaksi gudang, kontrol stok, dan konfirmasi penerimaan/pengeluaran barang.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ $totalItems }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    {{-- Sections --}}
    @foreach($sections as $section)
    <div class="hubv2-sh">
        <span class="hubv2-sh-dot"></span>
        <span class="hubv2-sh-name">{{ $section['title'] }}</span>
        <span class="hubv2-sh-rule"></span>
    </div>
    <div class="hubv2-grid">
        @foreach($section['items'] as $item)
        <a href="{{ $item['url'] }}" class="hubv2-card" data-hub-card target="_blank" rel="noopener noreferrer">
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