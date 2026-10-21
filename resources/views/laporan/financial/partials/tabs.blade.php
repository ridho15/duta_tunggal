@props(['activeTab' => 'neraca-saldo'])

@php
    $tabs = [
        'neraca-saldo' => [
            'label' => 'Neraca Saldo (Trial Balance)',
            'route' => route('laporan.financial.trial-balance'),
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>',
            'desc' => 'Uji keseimbangan debit & kredit',
        ],
        'laba-rugi' => [
            'label' => 'Laba Rugi (Income Statement)',
            'route' => route('laporan.financial.income-statement'),
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>',
            'desc' => 'Kinerja pendapatan & beban',
        ],
        'neraca' => [
            'label' => 'Neraca (Balance Sheet)',
            'route' => route('laporan.financial.balance-sheet'),
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            'desc' => 'Posisi aset, liabilitas & modal',
        ],
    ];
@endphp

<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-1.5 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5">
        @foreach ($tabs as $key => $tab)
            @php $isActive = $activeTab === $key; @endphp
            <a
                href="{{ $tab['route'] }}"
                class="flex items-center gap-3 px-4 py-3 rounded-lg text-left transition {{ $isActive ? 'bg-emerald-50/80 border border-emerald-200 text-[#15803d] shadow-xs' : 'hover:bg-gray-50 text-gray-700 border border-transparent' }}"
            >
                <span class="p-2 rounded-lg {{ $isActive ? 'bg-[#15803d] text-white shadow-xs' : 'bg-gray-100 text-gray-500' }}">
                    {!! $tab['icon'] !!}
                </span>
                <div>
                    <div class="text-xs font-bold {{ $isActive ? 'text-[#15803d]' : 'text-gray-900' }}">
                        {{ $tab['label'] }}
                    </div>
                    <div class="text-[11px] {{ $isActive ? 'text-emerald-700/80' : 'text-gray-400' }}">
                        {{ $tab['desc'] }}
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
