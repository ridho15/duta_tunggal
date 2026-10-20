@auth
@php
    $dtPrimary = \App\Support\MobileNav::primary();
    $dtStock = \App\Support\MobileNav::stock();
    $dtCreate = \App\Support\MobileNav::createOptions();
    $dtPath = '/' . ltrim(request()->path(), '/');
    $dtIsActive = fn (?array $item) => $item && str_starts_with($dtPath, $item['active']);
    $dtHomeActive = rtrim($dtPath, '/') === rtrim(parse_url(\App\Support\MobileNav::home(), PHP_URL_PATH) ?: '/admin', '/');
@endphp
{{-- Spesifikasi UI/UX 6.8 — navigasi bawah tampilan HP (< 768px) --}}
<div x-data="{ createOpen: false }" class="dt-mobile-nav md:hidden">
    <nav class="dt-mobile-nav-bar" aria-label="Navigasi bawah">
        <a href="{{ \App\Support\MobileNav::home() }}" @class(['dt-mobile-nav-item', 'is-active' => $dtHomeActive])>
            <x-filament::icon icon="heroicon-o-home" class="h-6 w-6" />
            <span>Beranda</span>
        </a>

        @if ($dtPrimary)
            <a href="{{ $dtPrimary['url'] }}" @class(['dt-mobile-nav-item', 'is-active' => $dtIsActive($dtPrimary)])>
                <x-filament::icon :icon="$dtPrimary['icon']" class="h-6 w-6" />
                <span>{{ $dtPrimary['label'] }}</span>
            </a>
        @else
            <span class="dt-mobile-nav-item" aria-hidden="true"></span>
        @endif

        @if (count($dtCreate))
            <button type="button" class="dt-mobile-nav-create" x-on:click="createOpen = true" aria-label="Buat baru">
                <x-filament::icon icon="heroicon-o-plus" class="h-7 w-7" />
            </button>
        @else
            <span class="dt-mobile-nav-item" aria-hidden="true"></span>
        @endif

        @if ($dtStock)
            <a href="{{ $dtStock['url'] }}" @class(['dt-mobile-nav-item', 'is-active' => $dtIsActive($dtStock)])>
                <x-filament::icon icon="heroicon-o-cube" class="h-6 w-6" />
                <span>Stok</span>
            </a>
        @else
            <span class="dt-mobile-nav-item" aria-hidden="true"></span>
        @endif

        <button type="button" class="dt-mobile-nav-item" x-on:click="$store.sidebar.open()">
            <x-filament::icon icon="heroicon-o-bars-3" class="h-6 w-6" />
            <span>Menu</span>
        </button>
    </nav>

    @if (count($dtCreate))
        <div x-show="createOpen" x-cloak x-transition.opacity class="dt-sheet-backdrop" x-on:click="createOpen = false"></div>
        <div x-show="createOpen" x-cloak x-transition class="dt-sheet" role="dialog" aria-label="Buat baru">
            <div class="dt-sheet-handle"></div>
            <div class="dt-sheet-title">Buat baru</div>
            @foreach ($dtCreate as $option)
                <a href="{{ $option['url'] }}" class="dt-sheet-option">
                    <span class="dt-sheet-option-icon"><x-filament::icon :icon="$option['icon']" class="h-5 w-5" /></span>
                    <span>
                        <span class="dt-sheet-option-label">{{ $option['label'] }}</span>
                        <span class="dt-sheet-option-desc">{{ $option['description'] }}</span>
                    </span>
                </a>
            @endforeach
            <button type="button" class="dt-sheet-cancel" x-on:click="createOpen = false">Batal</button>
        </div>
    @endif
</div>
@endauth
