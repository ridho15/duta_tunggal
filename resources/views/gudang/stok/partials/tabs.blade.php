@php
    $currentTab = $activeTab ?? 'index';
    $tabs = [
        'index' => [
            'label' => 'Cek Stok Fisik (Overview)',
            'desc' => 'Monitoring stok real-time, fisik vs terpesan per gudang',
            'route' => route('gudang.stok.index'),
            'icon' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M3 8l9 5 9-5M12 13v8" stroke-width="1.8" stroke-linecap="round"></path>',
        ],
        'inventory-card' => [
            'label' => 'Laporan Kartu Persediaan (Konsolidasi)',
            'desc' => 'Rekapitulasi mutasi saldo awal, masuk, keluar & saldo akhir',
            'route' => route('gudang.stok.inventory-card'),
            'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>',
        ],
    ];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
    @foreach ($tabs as $key => $tab)
        @php $isActive = ($currentTab === $key); @endphp
        <a href="{{ $tab['route'] }}"
           class="group relative flex items-center gap-3.5 p-3.5 rounded-xl border transition-all duration-200 {{ $isActive ? 'bg-emerald-50/70 border-[#15803d]/40 text-emerald-950 shadow-sm' : 'bg-white border-gray-200 hover:border-[#15803d]/30 hover:bg-gray-50/80 text-gray-700 shadow-sm' }}">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-colors {{ $isActive ? 'bg-[#15803d] text-white shadow-sm' : 'bg-gray-100 text-gray-500 group-hover:bg-emerald-50 group-hover:text-[#15803d]' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $tab['icon'] !!}
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold {{ $isActive ? 'text-emerald-900' : 'text-gray-900 group-hover:text-[#15803d]' }}">
                    {{ $tab['label'] }}
                </div>
                <div class="text-[11px] {{ $isActive ? 'text-[#15803d]' : 'text-gray-500' }} truncate">
                    {{ $tab['desc'] }}
                </div>
            </div>
            @if ($isActive)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-100 text-[#15803d] border border-emerald-200">
                    Aktif
                </span>
            @endif
        </a>
    @endforeach
</div>
