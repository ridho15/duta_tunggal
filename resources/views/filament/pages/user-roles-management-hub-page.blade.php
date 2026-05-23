<x-filament-panels::page>
@php
    $accentColor = '#9333ea';
    $accentLight = '#d8b4fe';
    $iconBg      = '#faf5ff';
    $heroFrom    = '#f3e8ff';
    $heroTo      = '#e9d5ff';
    $sections = [
        [
            'title' => 'Akses & Otorisasi',
            'items' => [
                ['label' => 'User',       'url' => \App\Filament\Resources\UserResource::getUrl(),        'icon' => 'user-circle', 'desc' => 'Kelola akun pengguna', 'class' => \App\Filament\Resources\UserResource::class],
                ['label' => 'Role',       'url' => \App\Filament\Resources\RoleResource::getUrl(),        'icon' => 'finger-print', 'desc' => 'Kelola role dan tugas akses', 'class' => \App\Filament\Resources\RoleResource::class],
                ['label' => 'Permission', 'url' => \App\Filament\Resources\PermissionResource::getUrl(),  'icon' => 'key',          'desc' => 'Kelola hak akses sistem', 'class' => \App\Filament\Resources\PermissionResource::class],
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

<div id="user-roles-management-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-users class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Admin Akses</div>
            <h1 class="hubv2-hero-title">Manajemen User & Role</h1>
            <p class="hubv2-hero-subtitle">Kelola pengguna, role, dan permission dari satu halaman agar akses sistem lebih mudah dipelihara.</p>
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