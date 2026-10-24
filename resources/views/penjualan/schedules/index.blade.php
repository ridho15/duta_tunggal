@extends('layouts.app', ['title' => 'Jadwal Kirim & Armada'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Jadwal Kirim & Armada</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Jadwal Kirim & Armada</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Manajemen keberangkatan logistik, alokasi armada internal / ekspedisi, manifes muatan, dan serah terima pelanggan.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.schedules.create') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Buat Jadwal Baru</span>
            </a>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-500">Total Jadwal</span>
                <span class="w-7 h-7 rounded-lg bg-gray-100 flex items-center justify-center text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="2"></rect>
                        <path d="M16 2v4M8 2v4M3 10h18" stroke-width="2"></path>
                    </svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-gray-900">{{ number_format($counts['semua']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Seluruh jadwal logistik</div>
        </div>

        <div class="bg-white border border-amber-200/60 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-amber-700">Menunggu Berangkat</span>
                <span class="w-7 h-7 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                        <path d="M12 6v6l4 2" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-amber-600">{{ number_format($counts['pending']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Siap diberangkatkan</div>
        </div>

        <div class="bg-white border border-blue-200/60 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-blue-700">Dalam Perjalanan</span>
                <span class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="2"></path>
                        <circle cx="7" cy="18" r="2" stroke-width="2"></circle>
                        <circle cx="17" cy="18" r="2" stroke-width="2"></circle>
                    </svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-blue-600">{{ number_format($counts['on_the_way']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Stok fisik sedang dikirim</div>
        </div>

        <div class="bg-white border border-emerald-200/60 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-emerald-700">Selesai Terkirim</span>
                <span class="w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="m5 13 4 4L19 7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-600">{{ number_format($counts['delivered']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Sudah sampai pelanggan</div>
        </div>

        <div class="bg-white border border-rose-200/60 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-rose-700">Gagal / Masalah</span>
                <span class="w-7 h-7 rounded-lg bg-rose-50 flex items-center justify-center text-rose-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="m18 6-12 12M6 6l12 12" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </span>
            </div>
            <div class="mt-2 text-2xl font-extrabold text-rose-600">{{ number_format($counts['failed']) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">Stok kembali ke gudang</div>
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

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'pending', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'pending') }}">
            <span>Menunggu</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'pending' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $counts['pending'] }}</span>
        </a>

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'on_the_way', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'on_the_way') }}">
            <span>Di Perjalanan</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'on_the_way' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800' }}">{{ $counts['on_the_way'] }}</span>
        </a>

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'delivered', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'delivered') }}">
            <span>Terkirim</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'delivered' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['delivered'] }}</span>
        </a>

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'failed', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'failed') }}">
            <span>Gagal</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'failed' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800' }}">{{ $counts['failed'] }}</span>
        </a>

        <a href="{{ route('penjualan.schedules.index', ['tab' => 'cancelled', 'q' => $search, 'cabang_id' => $cabangId, 'method' => $method, 'driver_id' => $driverId]) }}"
           class="{{ $tabClass($tab === 'cancelled') }}">
            <span>Dibatalkan</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'cancelled' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['cancelled'] }}</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
        <form method="GET" action="{{ route('penjualan.schedules.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Search Input --}}
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Jadwal</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8" stroke-width="2"></circle>
                            <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round"></path>
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="No Jadwal, Driver, Plat, Resi..."
                        class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d]"
                    >
                </div>
            </div>

            {{-- Cabang Filter --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Cabang</label>
                <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($c) => ['id' => $c->id, 'label' => $c->nama])->values()" :value="$cabangId" placeholder="Semua Cabang" size="sm" :auto-submit="true" />
            </div>

            {{-- Metode Filter --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Metode</label>
                <select name="method" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-[#15803d] bg-white">
                    <option value="">Semua Metode</option>
                    <option value="internal" {{ $method === 'internal' ? 'selected' : '' }}>Internal</option>
                    <option value="kurir_internal" {{ $method === 'kurir_internal' ? 'selected' : '' }}>Kurir Internal</option>
                    <option value="ekspedisi" {{ $method === 'ekspedisi' ? 'selected' : '' }}>Ekspedisi</option>
                </select>
            </div>

            {{-- Driver Filter --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Driver Master</label>
                <x-searchable-select name="driver_id" :options="collect($drivers)->map(fn ($d) => ['id' => $d->id, 'label' => $d->name])->values()" :value="$driverId" placeholder="Semua Driver" size="sm" :auto-submit="true" />
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-gray-900 hover:bg-black text-white px-3 py-2 rounded-xl text-xs font-semibold transition">
                    Filter
                </button>
                @if($search || $cabangId || $method || $driverId || $startDate || $endDate || $tab !== 'semua')
                    <a href="{{ route('penjualan.schedules.index') }}" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-semibold transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Jadwal Table --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-700">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold text-[11px]">
                    <tr>
                        <th class="px-4 py-3.5">Nomor Jadwal</th>
                        <th class="px-4 py-3.5">Tanggal Berangkat</th>
                        <th class="px-4 py-3.5">Metode & Armada</th>
                        <th class="px-4 py-3.5">Manifes Muatan</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($schedules as $schedule)
                        @php
                            $statusConfig = match($schedule->status) {
                                'pending' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500'],
                                'on_the_way' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500 animate-pulse'],
                                'delivered' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500'],
                                'failed' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500'],
                                'cancelled' => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400'],
                                default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400']
                            };
                            $methodBadge = match($schedule->delivery_method) {
                                'internal' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'kurir_internal' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'ekspedisi' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-gray-50 text-gray-600 border-gray-200'
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            {{-- Nomor & Cabang --}}
                            <td class="px-4 py-3">
                                <a href="{{ route('penjualan.schedules.show', $schedule->id) }}" class="font-bold text-gray-900 hover:text-[#15803d]">
                                    {{ $schedule->schedule_number }}
                                </a>
                                <div class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4" stroke-width="1.8"></path>
                                    </svg>
                                    <span>{{ $schedule->cabang->nama ?? 'Semua Cabang' }}</span>
                                </div>
                            </td>

                            {{-- Tanggal Berangkat --}}
                            <td class="px-4 py-3">
                                <div class="font-semibold text-gray-900">
                                    {{ optional($schedule->scheduled_date)->format('d/m/Y') ?? '-' }}
                                </div>
                                <div class="text-[11px] text-gray-500">
                                    {{ optional($schedule->scheduled_date)->format('H:i') }} WIB
                                </div>
                            </td>

                            {{-- Metode & Armada --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $methodBadge }}">
                                        {{ $schedule->delivery_method_label }}
                                    </span>
                                </div>
                                <div class="font-medium text-gray-900 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="7" r="4" stroke-width="2"></circle>
                                        <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke-width="2"></path>
                                    </svg>
                                    <span>{{ $schedule->senderName() }}</span>
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="1.8"></path>
                                        <circle cx="7" cy="18" r="2" stroke-width="1.8"></circle>
                                        <circle cx="17" cy="18" r="2" stroke-width="1.8"></circle>
                                    </svg>
                                    <span>{{ $schedule->vehicleLabel() }}</span>
                                </div>
                            </td>

                            {{-- Manifes Muatan --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 bg-gray-100 rounded text-[11px] font-bold text-gray-800">
                                        {{ $schedule->relatedDeliveryOrderCount() }} DO
                                    </span>
                                    <span class="px-2 py-0.5 bg-gray-100 rounded text-[11px] font-bold text-gray-800">
                                        {{ $schedule->relatedSuratJalanCount() }} SJ
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-500 mt-1 max-w-xs truncate" title="{{ $schedule->relatedDeliveryOrderSummary() }}">
                                    DO: {{ $schedule->relatedDeliveryOrderSummary() }}
                                </div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }} {{ $statusConfig['border'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                                    {{ $schedule->status_label }}
                                </span>
                            </td>

                            {{-- Aksi Cepat --}}
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    {{-- Pratinjau Manifes / Detail --}}
                                    <a
                                        href="{{ route('penjualan.schedules.show', $schedule->id) }}"
                                        class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail Manifes"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke-width="2"></path>
                                            <circle cx="12" cy="12" r="3" stroke-width="2"></circle>
                                        </svg>
                                    </a>

                                    {{-- Cetak Surat Tugas Driver (PDF) --}}
                                    <a
                                        href="{{ route('penjualan.schedules.pdf', $schedule->id) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Cetak Surat Kerja Driver"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="2"></path>
                                            <path d="M6 14h12v8H6z" stroke-width="2"></path>
                                        </svg>
                                    </a>

                                    @if($schedule->status === 'pending')
                                        {{-- Mulai Kirim --}}
                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-start-schedule', { action: '{{ route('penjualan.schedules.start', $schedule->id) }}', number: '{{ $schedule->schedule_number }}' })"
                                            class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold flex items-center gap-1 shadow-xs transition"
                                            title="Mulai Keberangkatan"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <polygon points="5 3 19 12 5 21 5 3" fill="currentColor"></polygon>
                                            </svg>
                                            <span>Berangkat</span>
                                        </button>

                                        {{-- Edit --}}
                                        <a
                                            href="{{ route('penjualan.schedules.edit', $schedule->id) }}"
                                            class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                            title="Edit Jadwal"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke-width="2"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke-width="2"></path>
                                            </svg>
                                        </a>

                                        {{-- Batal --}}
                                        <button
                                            type="button"
                                            onclick="openCancelModal('{{ $schedule->id }}', '{{ $schedule->schedule_number }}')"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Batalkan Jadwal"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke-width="2"></line>
                                            </svg>
                                        </button>
                                    @elseif($schedule->status === 'on_the_way')
                                        {{-- Selesaikan Kirim --}}
                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-complete-schedule', { action: '{{ route('penjualan.schedules.complete', $schedule->id) }}', number: '{{ $schedule->schedule_number }}' })"
                                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold flex items-center gap-1 shadow-xs transition"
                                            title="Tandai Selesai & Diterima"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="m5 13 4 4L19 7" stroke-width="2.5" stroke-linecap="round"></path>
                                            </svg>
                                            <span>Selesai</span>
                                        </button>

                                        {{-- Tandai Gagal --}}
                                        <button
                                            type="button"
                                            onclick="openFailModal('{{ $schedule->id }}', '{{ $schedule->schedule_number }}')"
                                            class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg text-[11px] font-bold flex items-center gap-1 transition"
                                            title="Tandai Gagal Kirim"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path d="m18 6-12 12M6 6l12 12" stroke-width="2.2" stroke-linecap="round"></path>
                                            </svg>
                                            <span>Gagal</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width="18" height="18" rx="2" stroke-width="1.5"></rect>
                                        <path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01" stroke-width="1.5" stroke-linecap="round"></path>
                                    </svg>
                                    <p class="text-sm font-semibold text-gray-700">Tidak ada jadwal pengiriman ditemukan</p>
                                    <p class="text-xs text-gray-400 mt-0.5">Silakan buat jadwal keberangkatan armada baru atau ubah kriteria filter.</p>
                                    <a href="{{ route('penjualan.schedules.create') }}" class="mt-4 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold transition">
                                        Buat Jadwal Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($schedules->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $schedules->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal Pengiriman Gagal --}}
<div id="failModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-base">Tandai Pengiriman Gagal</h3>
            <button type="button" onclick="closeFailModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>

        <form id="failForm" method="POST" action="">
            @csrf
            <div class="space-y-3">
                <p class="text-xs text-gray-600">
                    Jadwal <strong id="failScheduleNumber" class="text-gray-900"></strong> akan ditandai gagal. DO terkait yang sedang dikirim akan menjadi <em>delivery_failed</em> dan stok fisik akan dikembalikan ke gudang secara otomatis.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Kegagalan <span class="text-red-500">*</span></label>
                    <textarea
                        name="reason"
                        rows="3"
                        required
                        minlength="5"
                        placeholder="Contoh: Toko penerima tutup, jalan terputus, atau armada mengalami kendala mesin..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="closeFailModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                    Konfirmasi Gagal
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Pembatalan Jadwal --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-base">Batalkan Jadwal Pengiriman</h3>
            <button type="button" onclick="closeCancelModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>

        <form id="cancelForm" method="POST" action="">
            @csrf
            <div class="space-y-3">
                <p class="text-xs text-gray-600">
                    Batalkan jadwal <strong id="cancelScheduleNumber" class="text-gray-900"></strong>. Muatan SJ dan DO akan dibebaskan sehingga dapat dijadwalkan kembali.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Pembatalan</label>
                    <input
                        type="text"
                        name="cancel_reason"
                        placeholder="Contoh: Perubahan rute atau driver berhalangan..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-gray-500"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="closeCancelModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Tutup
                </button>
                <button type="submit" class="px-4 py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold shadow-sm transition">
                    Batalkan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openFailModal(scheduleId, scheduleNum) {
    document.getElementById('failScheduleNumber').innerText = scheduleNum;
    document.getElementById('failForm').action = '/penjualan/jadwal-kirim/' + scheduleId + '/fail';
    document.getElementById('failModal').classList.remove('hidden');
}

function closeFailModal() {
    document.getElementById('failModal').classList.add('hidden');
}

function openCancelModal(scheduleId, scheduleNum) {
    document.getElementById('cancelScheduleNumber').innerText = scheduleNum;
    document.getElementById('cancelForm').action = '/penjualan/jadwal-kirim/' + scheduleId + '/cancel';
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>

    {{-- Modal Konfirmasi Mulai Pengiriman --}}
    <x-confirmation-modal
        name="start-schedule"
        title="Mulai Pengiriman Armada"
        type="info"
        confirm-text="Ya, Mulai Pengiriman"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin memberangkatkan armada untuk jadwal <span class="font-bold text-gray-900 font-mono" x-text="data.number"></span>?
        </p>
        <p class="text-xs text-blue-600 bg-blue-50 border border-blue-100 rounded-lg p-2.5 mt-2">
            Seluruh Surat Jalan dan Delivery Order terkait akan ditandai terkirim dan stok fisik resmi dikeluarkan dari gudang.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Selesai Pengiriman --}}
    <x-confirmation-modal
        name="complete-schedule"
        title="Tandai Jadwal Pengiriman Selesai"
        type="success"
        confirm-text="Ya, Selesaikan Jadwal"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin jadwal pengiriman <span class="font-bold text-gray-900 font-mono" x-text="data.number"></span> telah selesai dan diterima dengan baik oleh pelanggan?
        </p>
        <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg p-2.5 mt-2">
            Status seluruh DO terkait akan ditandai selesai dan jurnal akuntansi pengiriman barang akan dibukukan secara otomatis.
        </p>
    </x-confirmation-modal>
@endsection
