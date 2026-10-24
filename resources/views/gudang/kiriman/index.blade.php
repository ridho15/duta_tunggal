@extends('layouts.app', ['title' => 'Siapkan & Kirim – Checker'])

@section('content')
<div class="max-w-2xl mx-auto space-y-4">

    {{-- Header --}}
    <div class="flex items-center justify-between pb-2 border-b border-gray-200">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Siapkan & Kirim</h1>
                <span class="text-[9px] font-bold text-[#15803d] border border-[#86efac] bg-[#f0fdf4] rounded px-1.5 py-0.5">
                    CHECKER
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Antrean barang yang harus dicek dan disiapkan di gudang.</p>
        </div>

        {{-- Warehouse Selector Dropdown --}}
        <div>
            <form method="GET" action="{{ route('gudang.kiriman.index') }}" id="warehouseForm">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <select data-searchable
                    name="warehouse_id"
                    onchange="document.getElementById('warehouseForm').submit()"
                    class="text-xs font-bold text-gray-800 bg-white border border-gray-300 rounded-xl px-2.5 py-1.5 focus:outline-none focus:border-[#15803d]"
                >
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ $currentWarehouseId == $wh->id ? 'selected' : '' }}>
                            {{ $wh->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    {{-- Search & Quick Tab Pills --}}
    <div class="space-y-3">
        <form method="GET" action="{{ route('gudang.kiriman.index') }}" class="flex gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="warehouse_id" value="{{ $currentWarehouseId }}">

            <div class="relative flex-1">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari no. DO / customer..."
                    class="w-full h-11 border border-gray-300 rounded-xl px-3.5 text-xs text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#15803d] bg-white"
                >
            </div>
            <button type="submit" class="h-11 px-4 bg-gray-900 text-white rounded-xl text-xs font-bold hover:bg-black transition">
                Cari
            </button>
        </form>

        {{-- Status Filter Tabs --}}
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-bold scrollbar-none">
            <a
                href="{{ route('gudang.kiriman.index', ['tab' => 'siap_dicek', 'warehouse_id' => $currentWarehouseId]) }}"
                class="px-3.5 py-1.5 rounded-full whitespace-nowrap {{ $tab === 'siap_dicek' ? 'bg-[#15803d] text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}"
            >
                Siap dicek · {{ $counts['siap_dicek'] }}
            </a>

            <a
                href="{{ route('gudang.kiriman.index', ['tab' => 'menunggu_sj', 'warehouse_id' => $currentWarehouseId]) }}"
                class="px-3.5 py-1.5 rounded-full whitespace-nowrap {{ $tab === 'menunggu_sj' ? 'bg-[#15803d] text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}"
            >
                Menunggu SJ · {{ $counts['menunggu_sj'] }}
            </a>

            <a
                href="{{ route('gudang.kiriman.index', ['tab' => 'terkirim', 'warehouse_id' => $currentWarehouseId]) }}"
                class="px-3.5 py-1.5 rounded-full whitespace-nowrap {{ $tab === 'terkirim' ? 'bg-[#15803d] text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' }}"
            >
                Terkirim hari ini · {{ $counts['terkirim'] }}
            </a>
        </div>
    </div>

    {{-- Active Warehouse Label Pill --}}
    <div>
        <span class="inline-flex items-center text-[10.5px] font-bold tracking-wider text-[#15803d] bg-[#f0fdf4] border border-[#86efac] rounded-md px-2.5 py-1 uppercase">
            GUDANG {{ $activeWarehouse->name ?? 'CABANG' }}
        </span>
    </div>

    {{-- Cards List DO --}}
    <div class="space-y-3">
        @forelse($deliveryOrders as $do)
            @php
                $primarySO = $do->salesOrders->first();
                $customer = $primarySO?->customer;
                $totalItems = $do->deliveryOrderItem->count();
                $totalPcs = (int) $do->deliveryOrderItem->sum('quantity');
                $isWaitingSJ = $do->status === 'confirmed' && $do->suratJalan->isEmpty();
            @endphp

            @if($isWaitingSJ)
                {{-- Card Menunggu SJ (Dashed) --}}
                <div class="bg-white border border-dashed border-gray-200 rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-base text-gray-700">{{ $do->do_number }}</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold text-amber-700 bg-amber-50 border border-amber-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            Menunggu SJ
                        </span>
                    </div>
                    <div class="text-xs text-gray-600">
                        {{ $customer->name ?? 'Customer' }} · {{ $totalItems }} barang · {{ $totalPcs }} pcs
                    </div>
                    <div class="text-[11px] text-gray-400">
                        Sudah dicek. Belum bisa dikirim: menunggu Surat Jalan dari admin pengiriman.
                    </div>
                </div>
            @else
                {{-- Card Siap Dicek --}}
                <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm space-y-3 hover:border-gray-200 transition">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-base text-gray-900">{{ $do->do_number }}</span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold text-blue-700 bg-blue-50 border border-blue-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            Siap Dicek
                        </span>
                    </div>

                    <div class="text-xs text-gray-600">
                        <span class="font-semibold text-gray-800">{{ $customer->name ?? 'Customer' }}</span> · {{ $totalItems }} barang · {{ $totalPcs }} pcs
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <span class="text-xs text-gray-400">
                            Rak: A-01, B-02
                        </span>

                        <a
                            href="{{ route('gudang.kiriman.check', $do->id) }}"
                            class="bg-[#15803d] hover:bg-[#166534] text-white px-4 py-2 rounded-xl font-semibold text-xs shadow-sm transition active:scale-[0.98]"
                        >
                            Mulai cek
                        </a>
                    </div>
                </div>
            @endif
        @empty
            <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-gray-400 text-xs">
                Tidak ada kiriman pada tab ini di gudang {{ $activeWarehouse->name ?? '' }}.
            </div>
        @endforelse
    </div>

    {{-- Bottom Action Link Cetak Picking List --}}
    <div class="pt-4 text-center">
        <a
            href="{{ route('gudang.kiriman.pickingList', ['warehouse_id' => $currentWarehouseId]) }}"
            target="_blank"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#15803d] hover:text-[#14532d] hover:underline"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" stroke-width="2"></path>
            </svg>
            <span>Cetak picking list gudang ini</span>
        </a>
    </div>

</div>
@endsection
