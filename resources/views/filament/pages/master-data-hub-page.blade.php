<x-filament-panels::page>
@php
    $accentColor = '#0f766e';
    $accentLight = '#5eead4';
    $iconBg      = '#f0fdfa';
    $heroFrom    = '#ccfbf1';
    $heroTo      = '#99f6e4';
    $sections = [
        [
            'title' => 'Produk & Persediaan',
            'items' => [
                ['label' => 'Produk',          'url' => \App\Filament\Resources\ProductResource::getUrl(),          'icon' => 'cube',            'desc' => 'Kelola master produk dan SKU'],
                ['label' => 'Kategori Produk', 'url' => \App\Filament\Resources\ProductCategoryResource::getUrl(), 'icon' => 'tag',             'desc' => 'Atur klasifikasi produk'],
                ['label' => 'Satuan',          'url' => \App\Filament\Resources\UnitOfMeasureResource::getUrl(),    'icon' => 'square-2-stack',  'desc' => 'Kelola satuan barang'],
                ['label' => 'Rak',             'url' => \App\Filament\Resources\RakResource::getUrl(),              'icon' => 'squares-2x2',     'desc' => 'Atur lokasi rak gudang'],
            ],
        ],
        [
            'title' => 'Organisasi & Lokasi',
            'items' => [
                ['label' => 'Gudang',   'url' => \App\Filament\Resources\WarehouseResource::getUrl(), 'icon' => 'archive-box',     'desc' => 'Data gudang dan status aktif'],
                ['label' => 'Cabang',   'url' => \App\Filament\Resources\CabangResource::getUrl(),    'icon' => 'building-office', 'desc' => 'Kelola cabang perusahaan'],
                ['label' => 'Customer', 'url' => \App\Filament\Resources\CustomerResource::getUrl(),   'icon' => 'user-group',      'desc' => 'Master data pelanggan'],
                ['label' => 'Supplier', 'url' => \App\Filament\Resources\SupplierResource::getUrl(),   'icon' => 'truck',           'desc' => 'Master data pemasok'],
            ],
        ],
        [
            'title' => 'Keuangan & Pendukung',
            'items' => [
                ['label' => 'Chart of Account', 'url' => \App\Filament\Resources\ChartOfAccountResource::getUrl(), 'icon' => 'banknotes',       'desc' => 'Struktur akun akuntansi'],
                ['label' => 'Mata Uang',        'url' => \App\Filament\Resources\CurrencyResource::getUrl(),        'icon' => 'currency-dollar', 'desc' => 'Daftar mata uang dan kurs'],
                ['label' => 'Setting Pajak',    'url' => \App\Filament\Resources\TaxSettingResource::getUrl(),      'icon' => 'document-text',   'desc' => 'Konfigurasi tarif pajak'],
                ['label' => 'Kendaraan',        'url' => \App\Filament\Resources\VehicleResource::getUrl(),         'icon' => 'truck',           'desc' => 'Master kendaraan operasional'],
                ['label' => 'Driver',           'url' => \App\Filament\Resources\DriverResource::getUrl(),          'icon' => 'identification',  'desc' => 'Master pengemudi'],
            ],
        ],
    ];
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="master-data-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-circle-stack class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Data Master</div>
            <h1 class="hubv2-hero-title">Pusat Data Master</h1>
            <p class="hubv2-hero-subtitle">Kelola data dasar produk, gudang, cabang, pelanggan, supplier, akun, mata uang, dan pendukung operasional dari satu halaman.</p>
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