<x-filament-panels::page>
@php
    $accentColor = '#dc2626';
    $accentLight = '#fca5a5';
    $iconBg      = '#fef2f2';
    $heroFrom    = '#fee2e2';
    $heroTo      = '#fecaca';
    $sections = [
        [
            'title' => 'Transaksi Penjualan',
            'items' => [
                ['label' => 'Quotations',      'url' => \App\Filament\Resources\QuotationResource::getUrl(),        'icon' => 'document-text',        'desc' => 'Kelola penawaran harga untuk pelanggan'],
                ['label' => 'Sale Orders',     'url' => \App\Filament\Resources\SaleOrderResource::getUrl(),        'icon' => 'shopping-bag',         'desc' => 'Buat dan pantau pesanan penjualan'],
            ],
        ],
        [
            'title' => 'Analisis Penjualan',
            'items' => [
                ['label' => 'Sales Report',    'url' => \App\Filament\Pages\SalesReportPage::getUrl(),              'icon' => 'chart-bar-square',     'desc' => 'Pantau ringkasan dan performa penjualan'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="sales-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-shopping-cart class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Penjualan</div>
            <h1 class="hubv2-hero-title">Pusat Penjualan</h1>
            <p class="hubv2-hero-subtitle">Kelola penawaran, pesanan, faktur, transaksi penjualan lain, dan ringkasan performa dari satu halaman.</p>
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