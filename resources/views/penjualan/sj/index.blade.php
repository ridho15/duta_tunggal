@extends('layouts.app', ['title' => 'Surat Jalan'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Surat Jalan</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Surat Jalan</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Dokumen pengiriman resmi serah-terima barang ke pihak logistik dan customer.
            </p>
        </div>

        <div>
            <a
                href="{{ route('penjualan.sj.create') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Buat Surat Jalan</span>
            </a>
        </div>
    </div>

    {{-- Quick Status Filter Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs font-semibold">
        @php
            $tabClass = function(bool $active) {
                return $active
                    ? 'px-3.5 py-1.5 rounded-full bg-[#15803d] text-white flex items-center gap-2 shadow-sm'
                    : 'px-3.5 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition';
            };
        @endphp

        <a href="{{ route('penjualan.sj.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.sj.index', ['tab' => 'terbit', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'terbit') }}">
            <span>Terbit</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'terbit' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['terbit'] }}</span>
        </a>

        <a href="{{ route('penjualan.sj.index', ['tab' => 'draft', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'draft') }}">
            <span>Draft</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'draft' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['draft'] }}</span>
        </a>

        <a href="{{ route('penjualan.sj.index', ['tab' => 'dibatalkan', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'dibatalkan') }}">
            <span>Dibatalkan</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'dibatalkan' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-800' }}">{{ $counts['dibatalkan'] }}</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-3 shadow-xs">
        <form method="GET" action="{{ route('penjualan.sj.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Search Input --}}
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" stroke-width="2"></circle>
                        <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nomor SJ, nomor DO, customer, atau kurir..."
                    class="w-full pl-10 pr-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] placeholder-gray-400"
                >
            </div>

            {{-- Cabang Filter --}}
            <div class="sm:w-56">
                <select
                    name="cabang_id"
                    onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white focus:outline-none focus:border-[#15803d]"
                >
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>
                            {{ $c->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition">
                Cari
            </button>
        </form>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3.5">No. Surat Jalan</th>
                        <th class="px-4 py-3.5">Customer & SO</th>
                        <th class="px-4 py-3.5">Delivery Order (Gudang)</th>
                        <th class="px-4 py-3.5">Armada / Driver</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($suratJalans as $sj)
                        @php
                            $primaryDO = $sj->deliveryOrder->first();
                            $primarySO = $primaryDO?->salesOrders?->first();
                            $customer = $primarySO?->customer;
                            $schedule = $sj->deliverySchedules->first();
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition group">
                            {{-- No SJ & Tanggal --}}
                            <td class="px-5 py-4">
                                <a href="{{ route('penjualan.sj.show', $sj->id) }}" class="font-extrabold text-gray-900 hover:text-[#15803d] text-[15px] block">
                                    {{ $sj->sj_number }}
                                </a>
                                <span class="text-xs text-gray-400 block mt-0.5">
                                    {{ $sj->issued_at ? $sj->issued_at->format('d M Y') : '-' }} · {{ $sj->cabang->nama ?? 'Semua Cabang' }}
                                </span>
                            </td>

                            {{-- Customer & SO --}}
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-800">
                                    {{ $customer->name ?? 'Customer Umum' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    SO: <span class="font-medium text-gray-600">{{ $primarySO?->so_number ?? '-' }}</span>
                                </div>
                            </td>

                            {{-- DO & Gudang --}}
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($sj->deliveryOrder as $do)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 bg-gray-100 text-gray-700 rounded-md border border-gray-200">
                                            <span>{{ $do->do_number }}</span>
                                            <span class="text-gray-400 font-normal">({{ $do->warehouse->name ?? 'Gudang' }})</span>
                                        </span>
                                    @endforeach
                                </div>
                            </td>

                            {{-- Armada / Driver --}}
                            <td class="px-4 py-4">
                                @if($schedule)
                                    <div class="text-xs font-semibold text-gray-800">
                                        {{ $schedule->driver_name ?? '-' }}
                                    </div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ $schedule->vehicle_info ?? $schedule->tracking_number ?? 'Internal' }}
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Pengiriman Langsung</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-4">
                                @if($sj->status === \App\Models\SuratJalan::STATUS_ISSUED)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Terbit
                                    </span>
                                @elseif($sj->status === \App\Models\SuratJalan::STATUS_DRAFT)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                                        Draft
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-1">
                                        Dibatalkan
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a
                                        href="{{ route('penjualan.sj.pdf', $sj->id) }}"
                                        target="_blank"
                                        title="Cetak Surat Jalan (PDF)"
                                        class="p-1.5 text-gray-400 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('penjualan.sj.show', $sj->id) }}"
                                        class="text-xs font-bold text-[#15803d] hover:text-[#14532d] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg px-3 py-1.5 transition"
                                    >
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Tidak ada data Surat Jalan ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($suratJalans->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $suratJalans->links() }}
            </div>
        @endif
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden space-y-3">
        @forelse($suratJalans as $sj)
            @php
                $primaryDO = $sj->deliveryOrder->first();
                $primarySO = $primaryDO?->salesOrders?->first();
                $customer = $primarySO?->customer;
                $schedule = $sj->deliverySchedules->first();
            @endphp
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('penjualan.sj.show', $sj->id) }}" class="font-extrabold text-gray-900 text-base">
                            {{ $sj->sj_number }}
                        </a>
                        <div class="text-xs text-gray-500 font-medium mt-0.5">
                            {{ $customer->name ?? 'Customer Umum' }}
                        </div>
                    </div>
                    @if($sj->status === \App\Models\SuratJalan::STATUS_ISSUED)
                        <span class="text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-md px-2 py-0.5">
                            Terbit
                        </span>
                    @elseif($sj->status === \App\Models\SuratJalan::STATUS_DRAFT)
                        <span class="text-[10px] font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-md px-2 py-0.5">
                            Draft
                        </span>
                    @else
                        <span class="text-[10px] font-bold text-red-700 bg-red-50 border border-red-200 rounded-md px-2 py-0.5">
                            Dibatalkan
                        </span>
                    @endif
                </div>

                <div class="text-xs text-gray-600 space-y-1 bg-gray-50 rounded-xl p-2.5">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Tanggal:</span>
                        <span class="font-medium text-gray-800">{{ $sj->issued_at ? $sj->issued_at->format('d M Y') : '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">DO:</span>
                        <span class="font-medium text-gray-800">{{ $sj->deliveryOrder->pluck('do_number')->implode(', ') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Kurir:</span>
                        <span class="font-medium text-gray-800">{{ $schedule?->driver_name ?? 'Langsung' }}</span>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <a
                        href="{{ route('penjualan.sj.pdf', $sj->id) }}"
                        target="_blank"
                        class="text-xs font-bold text-gray-600 hover:text-gray-900 flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                        </svg>
                        <span>Cetak PDF</span>
                    </a>

                    <a
                        href="{{ route('penjualan.sj.show', $sj->id) }}"
                        class="text-xs font-bold text-[#15803d] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-xl px-4 py-2 transition"
                    >
                        Lihat Detail
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-gray-400 text-sm">
                Tidak ada data Surat Jalan.
            </div>
        @endforelse

        @if($suratJalans->hasPages())
            <div class="pt-2">
                {{ $suratJalans->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
