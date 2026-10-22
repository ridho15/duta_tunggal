@extends('layouts.app', ['title' => 'Perintah Kirim (Delivery Order)'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Perintah Kirim (DO)</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Perintah Kirim (Delivery Order)</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Dokumen alokasi dan pengeluaran barang dari gudang berdasarkan Surat Pesanan Penjualan (SO).
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('gudang.kiriman.index') }}"
                class="inline-flex items-center gap-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 px-3.5 py-2.5 rounded-xl font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.8"></path>
                    <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="1.8"></rect>
                    <path d="m9 14 2 2 4-4" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
                <span>Siapkan di Gudang</span>
            </a>

            <a
                href="{{ route('penjualan.so.index', ['status' => 'approved']) }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Buat DO dari SO</span>
            </a>
        </div>
    </div>

    {{-- KPI Cards Ringkasan --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Total DO</div>
            <div class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($counts['semua']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">Seluruh pesanan pengiriman</div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-blue-600 uppercase tracking-wider">Siap Dicek Gudang</div>
            <div class="text-2xl font-extrabold text-blue-700 mt-1">{{ number_format($counts['siap_dicek']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">Menunggu checklist checker</div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-amber-600 uppercase tracking-wider">Siap Terbit SJ</div>
            <div class="text-2xl font-extrabold text-amber-700 mt-1">{{ number_format($counts['siap_sj']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">Pengecekan fisik selesai</div>
        </div>

        <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
            <div class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider">Terkirim / Ada SJ</div>
            <div class="text-2xl font-extrabold text-[#15803d] mt-1">{{ number_format($counts['terkirim']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">Surat Jalan telah terbit</div>
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

        <a href="{{ route('penjualan.do.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.do.index', ['tab' => 'menunggu_gudang', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'menunggu_gudang') }}">
            <span>Menunggu Gudang</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'menunggu_gudang' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['menunggu_gudang'] }}</span>
        </a>

        <a href="{{ route('penjualan.do.index', ['tab' => 'siap_dicek', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'siap_dicek') }}">
            <span>Siap Dicek</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'siap_dicek' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800' }}">{{ $counts['siap_dicek'] }}</span>
        </a>

        <a href="{{ route('penjualan.do.index', ['tab' => 'siap_sj', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'siap_sj') }}">
            <span>Siap Buat SJ</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'siap_sj' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $counts['siap_sj'] }}</span>
        </a>

        <a href="{{ route('penjualan.do.index', ['tab' => 'terkirim', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'terkirim') }}">
            <span>Terkirim</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'terkirim' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['terkirim'] }}</span>
        </a>

        <a href="{{ route('penjualan.do.index', ['tab' => 'diretur', 'q' => $search, 'cabang_id' => $cabangId, 'warehouse_id' => $warehouseId]) }}"
           class="{{ $tabClass($tab === 'diretur') }}">
            <span>Diretur / Batal</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'diretur' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-800' }}">{{ $counts['diretur'] }}</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-3 shadow-xs">
        <form method="GET" action="{{ route('penjualan.do.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
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
                    placeholder="Cari nomor DO, nomor SO, customer, atau gudang..."
                    class="w-full pl-10 pr-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] placeholder-gray-400"
                >
            </div>

            {{-- Cabang Filter --}}
            <div class="sm:w-48">
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

            {{-- Warehouse Filter --}}
            <div class="sm:w-48">
                <select
                    name="warehouse_id"
                    onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white focus:outline-none focus:border-[#15803d]"
                >
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>
                            {{ $w->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button
                type="submit"
                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-sm font-bold transition flex items-center justify-center gap-1.5"
            >
                <span>Filter</span>
            </button>

            @if($search || $cabangId || $warehouseId || $tab !== 'semua')
                <a
                    href="{{ route('penjualan.do.index') }}"
                    class="px-3 py-2 text-xs font-semibold text-gray-400 hover:text-red-600 transition flex items-center justify-center"
                    title="Reset Filter"
                >
                    Reset
                </a>
            @endif
        </form>
    </div>

    {{-- Tabel Delivery Order --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                        <th class="py-3.5 px-4">No. DO & Tanggal</th>
                        <th class="py-3.5 px-4">Pesanan (SO) & Customer</th>
                        <th class="py-3.5 px-4">Asal Gudang</th>
                        <th class="py-3.5 px-4">Barang</th>
                        <th class="py-3.5 px-4">Status Pengiriman</th>
                        <th class="py-3.5 px-4">Surat Jalan</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deliveryOrders as $do)
                        @php
                            $primarySo = $do->salesOrders->first();
                            $hasIssuedSj = $do->hasIssuedSuratJalan();
                            $activeSj = $do->suratJalan->first(fn($sj) => in_array($sj->status, \App\Models\SuratJalan::ACTIVE_STATUSES));
                            $itemCount = $do->deliveryOrderItem->count();
                            $totalQty = $do->deliveryOrderItem->sum('quantity');
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition group">
                            {{-- No DO & Tanggal --}}
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900 text-sm">
                                    <a href="{{ route('penjualan.do.show', $do->id) }}" class="hover:text-[#15803d] transition">
                                        {{ $do->do_number }}
                                    </a>
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    {{ $do->delivery_date ? \Carbon\Carbon::parse($do->delivery_date)->format('d M Y') : $do->created_at->format('d M Y') }}
                                </div>
                            </td>

                            {{-- Pesanan (SO) & Customer --}}
                            <td class="py-3.5 px-4">
                                @if($primarySo)
                                    <div class="font-bold text-gray-800">
                                        {{ $primarySo->customer?->name ?? 'Customer Umum' }}
                                    </div>
                                    <div class="text-[11px] text-gray-500 mt-0.5 flex items-center gap-1">
                                        <a href="{{ route('penjualan.so.show', $primarySo->id) }}" class="text-[#15803d] hover:underline">
                                            {{ $primarySo->so_number }}
                                        </a>
                                        @if($do->salesOrders->count() > 1)
                                            <span class="text-[10px] bg-gray-100 text-gray-600 rounded px-1">+{{ $do->salesOrders->count() - 1 }} SO</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 italic">Tanpa SO</span>
                                @endif
                            </td>

                            {{-- Asal Gudang & Cabang --}}
                            <td class="py-3.5 px-4">
                                <div class="font-medium text-gray-800">
                                    {{ $do->warehouse?->name ?? 'Gudang Pusat' }}
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    Cabang {{ $do->cabang?->nama ?? '-' }}
                                </div>
                            </td>

                            {{-- Rincian Barang --}}
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-700">
                                    {{ $itemCount }} Produk
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    Total {{ number_format($totalQty, 0, ',', '.') }} pcs
                                </div>
                            </td>

                            {{-- Status Pengiriman --}}
                            <td class="py-3.5 px-4">
                                @if($do->returned_at || $do->status === 'returned')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-800 border border-red-200">
                                        DIRETUR
                                    </span>
                                @elseif($do->status === 'confirmed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        SIAP BUAT SJ
                                    </span>
                                @elseif($do->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                        SIAP CEK GUDANG
                                    </span>
                                @elseif(in_array($do->status, \App\Models\DeliveryOrder::DELIVERED_STATUSES) || $hasIssuedSj)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        TERKIRIM
                                    </span>
                                @elseif($do->status === 'closed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                        DITUTUP
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 border border-gray-200">
                                        {{ \App\Models\DeliveryOrder::statusLabel($do->status) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Surat Jalan --}}
                            <td class="py-3.5 px-4">
                                @if($activeSj)
                                    <a href="{{ route('penjualan.sj.show', $activeSj->id) }}" class="font-bold text-[#15803d] hover:underline flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="2"></path>
                                        </svg>
                                        <span>{{ $activeSj->sj_number }}</span>
                                    </a>
                                    <div class="text-[10px] text-gray-400">
                                        {{ $activeSj->status === 'issued' ? 'Terbit' : ucfirst($activeSj->status) }}
                                    </div>
                                @elseif($do->status === 'confirmed' && empty($do->returned_at))
                                    <span class="text-amber-600 font-semibold text-[11px] flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Belum Ada SJ
                                    </span>
                                @else
                                    <span class="text-gray-400 text-[11px]">-</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('penjualan.do.show', $do->id) }}"
                                        class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-width="2"></path>
                                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-width="2"></path>
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('penjualan.do.pdf', $do->id) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                                        title="Cetak PDF DO"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                                        </svg>
                                    </a>

                                    @if($do->status === 'confirmed' && empty($do->returned_at) && ! $hasIssuedSj)
                                        <a
                                            href="{{ route('penjualan.sj.create', ['so_id' => $primarySo?->id, 'do_id' => $do->id]) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#15803d] hover:bg-[#14532d] text-white rounded-lg font-bold text-[11px] shadow-xs transition"
                                            title="Buat Surat Jalan"
                                        >
                                            <span>Buat SJ</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <svg class="w-12 h-12 stroke-[1.2] mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M3 6h11v10H3zM14 9h4l3 3v4h-7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <circle cx="7" cy="18" r="2" stroke-width="1.8"></circle>
                                        <circle cx="17" cy="18" r="2" stroke-width="1.8"></circle>
                                    </svg>
                                    <div class="text-sm font-bold text-gray-700">Tidak ada Perintah Kirim (DO) ditemukan</div>
                                    <p class="text-xs text-gray-500 mt-1 max-w-sm">
                                        Coba sesuaikan pencarian atau filter status untuk menemukan dokumen pengiriman yang dicari.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($deliveryOrders->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $deliveryOrders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
