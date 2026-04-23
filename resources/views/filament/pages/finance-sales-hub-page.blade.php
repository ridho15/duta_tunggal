<x-filament-panels::page>
@php
    $accentColor = '#2563eb';
    $accentLight = '#93c5fd';
    $iconBg      = '#eff6ff';
    $heroFrom    = '#eff6ff';
    $heroTo      = '#bfdbfe';
    $items = [
        ['label' => 'Piutang Usaha',      'url' => \App\Filament\Resources\AccountReceivableResource::getUrl(), 'icon' => 'banknotes',     'desc' => 'Kelola hak tagih dari pelanggan'],
        ['label' => 'Invoice Penjualan',  'url' => \App\Filament\Resources\SalesInvoiceResource::getUrl(),     'icon' => 'document-text', 'desc' => 'Tagihan penjualan kepada pelanggan'],
        ['label' => 'Penjualan Lainnya',  'url' => \App\Filament\Resources\OtherSaleResource::getUrl(),        'icon' => 'shopping-bag',  'desc' => 'Transaksi penjualan non-standar'],
    ];
@endphp

@include('filament.pages.partials.hub-styles')

<div id="finance-sales-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-banknotes class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Keuangan Penjualan</div>
            <h1 class="hubv2-hero-title">Pusat Keuangan Penjualan</h1>
            <p class="hubv2-hero-subtitle">Kelola piutang usaha, invoice, dan transaksi penjualan dari satu halaman terpadu.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ count($items) }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    {{-- Cards --}}
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