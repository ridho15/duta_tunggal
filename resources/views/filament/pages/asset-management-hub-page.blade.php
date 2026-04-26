<x-filament-panels::page>
@php
    $accentColor = '#2563eb';
    $accentLight = '#93c5fd';
    $iconBg      = '#eff6ff';
    $heroFrom    = '#dbeafe';
    $heroTo      = '#bfdbfe';
    $sections = [
        [
            'title' => 'Aset Tetap',
            'items' => [
                ['label' => 'Aset Tetap',    'url' => \App\Filament\Resources\AssetResource::getUrl(),         'icon' => 'building-office-2',  'desc' => 'Master aset dan nilai buku'],
                ['label' => 'Transfer Aset', 'url' => \App\Filament\Resources\AssetTransferResource::getUrl(),  'icon' => 'arrow-right-circle', 'desc' => 'Perpindahan aset antar cabang'],
                ['label' => 'Disposal Aset', 'url' => \App\Filament\Resources\AssetDisposalResource::getUrl(),  'icon' => 'archive-box-x-mark', 'desc' => 'Penghapusan aset tetap'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="asset-management-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-building-office-2 class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Asset Management</div>
            <h1 class="hubv2-hero-title">Manajemen Aset</h1>
            <p class="hubv2-hero-subtitle">Kelola aset tetap, mutasi aset, dan disposal dari satu halaman pusat.</p>
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