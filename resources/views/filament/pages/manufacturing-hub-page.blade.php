<x-filament-panels::page>
@php
    $accentColor = '#7c2d12';
    $accentLight = '#fdba74';
    $iconBg      = '#fff7ed';
    $heroFrom    = '#ffedd5';
    $heroTo      = '#fed7aa';
    $sections = [
        [
            'title' => 'Perencanaan Produksi',
            'items' => [
                ['label' => 'Bill of Material',    'url' => \App\Filament\Resources\BillOfMaterialResource::getUrl(),     'icon' => 'document-text',           'desc' => 'Struktur material dan komponen produksi'],
                ['label' => 'Production Plan',     'url' => \App\Filament\Resources\ProductionPlanResource::getUrl(),      'icon' => 'calendar-days',           'desc' => 'Rencana produksi terjadwal'],
                ['label' => 'Manufacturing Order', 'url' => \App\Filament\Resources\ManufacturingOrderResource::getUrl(),  'icon' => 'clipboard-document-list', 'desc' => 'Instruksi produksi per order'],
            ],
        ],
        [
            'title' => 'Eksekusi Produksi',
            'items' => [
                ['label' => 'Material Issue',       'url' => \App\Filament\Resources\MaterialIssueResource::getUrl(),           'icon' => 'archive-box-arrow-down', 'desc' => 'Pengeluaran material ke produksi'],
                ['label' => 'Production',           'url' => \App\Filament\Resources\ProductionResource::getUrl(),               'icon' => 'play-circle',           'desc' => 'Eksekusi dan progres produksi'],
                ['label' => 'QC Manufacture',       'url' => \App\Filament\Resources\QualityControlManufactureResource::getUrl(), 'icon' => 'check-badge',            'desc' => 'Kontrol kualitas hasil produksi'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="manufacturing-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-cog-6-tooth class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Manufaktur</div>
            <h1 class="hubv2-hero-title">Pusat Manufaktur</h1>
            <p class="hubv2-hero-subtitle">Kelola perencanaan, pengeluaran material, produksi, dan kontrol kualitas dari satu hub yang terpusat.</p>
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