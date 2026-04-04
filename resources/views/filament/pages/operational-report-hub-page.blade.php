<x-filament-panels::page>
@php
    $accentColor = '#0d9488';
    $accentLight = '#5eead4';
    $iconBg      = '#f0fdfa';
    $heroFrom    = '#f0fdfa';
    $heroTo      = '#ccfbf1';
    $items = [
        ['label' => 'Laporan Penjualan',  'url' => \App\Filament\Pages\SalesReportPage::getUrl(),    'icon' => 'chart-bar',    'desc' => 'Rekap transaksi & performa penjualan'],
        ['label' => 'Laporan Pembelian',  'url' => \App\Filament\Pages\PurchaseReportPage::getUrl(), 'icon' => 'shopping-cart', 'desc' => 'Rekap transaksi & aktivitas pembelian'],
    ];
@endphp

@include('filament.pages.partials.hub-styles')

<div id="operational-report-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-clipboard-document-list class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Laporan Operasional</div>
            <h1 class="hubv2-hero-title">Laporan Operasional</h1>
            <p class="hubv2-hero-subtitle">Akses laporan penjualan dan pembelian secara cepat dari satu halaman.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ count($items) }}</span>
            <span class="hubv2-hero-meta-lbl">Laporan</span>
        </div>
    </div>

    {{-- Cards --}}
    <div class="hubv2-grid">
        @foreach($items as $item)
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

</div>
</x-filament-panels::page>