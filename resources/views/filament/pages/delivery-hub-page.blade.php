<x-filament-panels::page>
@php
    $accentColor = '#0891b2';
    $accentLight = '#67e8f9';
    $iconBg      = '#ecfeff';
    $heroFrom    = '#ecfeff';
    $heroTo      = '#a5f3fc';
    $items = [
        ['label' => 'Perintah Pengiriman',    'url' => \App\Filament\Resources\DeliveryOrderResource::getUrl(),   'icon' => 'document-text',  'desc' => 'Instruksi pengiriman pesanan pelanggan'],
        ['label' => 'Surat Jalan',            'url' => \App\Filament\Resources\SuratJalanResource::getUrl(),       'icon' => 'truck',          'desc' => 'Dokumen pengiriman barang ke pelanggan'],
        ['label' => 'Penjadwalan Pengiriman', 'url' => \App\Filament\Resources\DeliveryScheduleResource::getUrl(),'icon' => 'calendar-days',  'desc' => 'Atur jadwal & rute pengiriman'],
    ];
@endphp

@include('filament.pages.partials.hub-styles')

<div id="delivery-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-truck class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Pengiriman</div>
            <h1 class="hubv2-hero-title">Pusat Pengiriman</h1>
            <p class="hubv2-hero-subtitle">Kelola perintah pengiriman, jadwal, dan surat jalan dari satu halaman terpadu.</p>
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