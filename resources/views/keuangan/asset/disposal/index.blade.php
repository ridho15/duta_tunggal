@extends('layouts.app')

@section('title', 'Pelepasan & Disposal Aset Tetap – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pelepasan & Penghapusan</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pelepasan & Disposal Aset Tetap</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pencatatan penjualan aset, penghapusan (scrap/rusak), rekonsiliasi kas masuk, dan auto-posting laba/rugi pelepasan ke Buku Besar.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('keuangan.asset.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Master Aset
            </a>
            <a
                href="{{ route('keuangan.asset.disposal.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Pelepasan Aset Baru
            </a>
        </div>
    </div>


    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Unit Dilepas --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unit Dilepas</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_disposals']) }} Unit</h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Total aset yang telah dihentikan</span>
        </div>

        {{-- Total Nilai Buku Bersih (NBV) --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Nilai Buku Lepas</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1 font-mono">
                {{ \App\Helpers\MoneyHelper::rupiah($kpis['total_book_value']) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Nilai buku saat pelepasan</span>
        </div>

        {{-- Kas Penjualan --}}
        <div class="p-5 rounded-2xl border border-emerald-100 bg-emerald-50/20 shadow-sm">
            <p class="text-xs font-semibold text-[#15803d] uppercase tracking-wider">Total Kas Penjualan</p>
            <h3 class="text-2xl font-bold text-[#15803d] mt-1 font-mono">
                {{ \App\Helpers\MoneyHelper::rupiah($kpis['total_sale_price']) }}
            </h3>
            <span class="text-[11px] text-emerald-600/75 mt-0.5 block">Penerimaan kas dari penjualan</span>
        </div>

        {{-- Net Gain / Loss --}}
        <div class="bg-white p-5 rounded-2xl border {{ $kpis['net_gain_loss'] >= 0 ? 'border-emerald-100 bg-emerald-50/20' : 'border-rose-100 bg-rose-50/20' }} shadow-sm">
            <p class="text-xs font-semibold {{ $kpis['net_gain_loss'] >= 0 ? 'text-[#15803d]' : 'text-rose-700' }} uppercase tracking-wider">
                Net Laba / Rugi Pelepasan
            </p>
            <h3 class="text-2xl font-bold {{ $kpis['net_gain_loss'] >= 0 ? 'text-[#15803d]' : 'text-rose-700' }} mt-1 font-mono">
                {{ $kpis['net_gain_loss'] >= 0 ? '+' : '-' }}{{ \App\Helpers\MoneyHelper::rupiah(abs($kpis['net_gain_loss'])) }}
            </h3>
            <span class="text-[11px] {{ $kpis['net_gain_loss'] >= 0 ? 'text-emerald-600/75' : 'text-rose-600/75' }} mt-0.5 block">
                {{ $kpis['net_gain_loss'] >= 0 ? 'Keuntungan bersih pelepasan' : 'Kerugian bersih pelepasan' }}
            </span>
        </div>
    </div>

    {{-- Filter Bar & Pencarian --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('keuangan.asset.disposal.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Jenis Pelepasan --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Jenis Pelepasan</label>
                <select name="disposal_type" class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200">
                    <option value="all" {{ request('disposal_type', 'all') === 'all' ? 'selected' : '' }}>Semua Jenis</option>
                    <option value="sale" {{ request('disposal_type') === 'sale' ? 'selected' : '' }}>Penjualan (Sale)</option>
                    <option value="scrap" {{ request('disposal_type') === 'scrap' ? 'selected' : '' }}>Scrap / Rusak Total</option>
                    <option value="donation" {{ request('disposal_type') === 'donation' ? 'selected' : '' }}>Donasi (Donation)</option>
                    <option value="theft" {{ request('disposal_type') === 'theft' ? 'selected' : '' }}>Hilang / Pencurian</option>
                    <option value="other" {{ request('disposal_type') === 'other' ? 'selected' : '' }}>Lainnya</option>
                </select>
            </div>

            {{-- Cabang --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Cabang</label>
                <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cabang) => ['id' => $cabang->id, 'label' => $cabang->nama])->values()" :value="request('cabang_id')" placeholder="Semua Cabang" size="sm" />
            </div>

            {{-- Pencarian --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Cari Aset</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Kode / nama aset..."
                    class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200"
                >
            </div>

            {{-- Tombol Filter --}}
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 px-3 bg-[#15803d] hover:bg-emerald-800 text-white font-semibold text-xs rounded-xl shadow-sm transition">
                    Terapkan Filter
                </button>
                <a href="{{ route('keuangan.asset.disposal.index') }}" class="p-2 text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>
            </div>
        </form>
    </div>

    {{-- Tabel Riwayat Disposal --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Daftar Transaksi Pelepasan Aset Tetap</h3>
            <span class="text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg">
                Total: {{ $disposals->total() }} Transaksi
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Tgl Pelepasan</th>
                        <th class="py-3 px-4">Aset Tetap</th>
                        <th class="py-3 px-4">Cabang</th>
                        <th class="py-3 px-4 text-center">Jenis Pelepasan</th>
                        <th class="py-3 px-4 text-right">Nilai Buku (NBV)</th>
                        <th class="py-3 px-4 text-right">Harga Jual Kas</th>
                        <th class="py-3 px-4 text-right">Laba / (Rugi) Pelepasan</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($disposals as $item)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ $item->disposal_date ? $item->disposal_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <a href="{{ route('keuangan.asset.show', $item->asset_id) }}" class="font-bold text-[#15803d] hover:underline block">
                                    {{ $item->asset->name ?? '-' }}
                                </a>
                                <span class="text-[11px] font-mono text-gray-400 block">{{ $item->asset->code ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-800">
                                {{ $item->asset->cabang->nama ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($item->disposal_type === 'sale')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Penjualan (Sale)
                                    </span>
                                @elseif ($item->disposal_type === 'scrap')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Scrap / Rusak
                                    </span>
                                @elseif ($item->disposal_type === 'donation')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                        Donasi
                                    </span>
                                @elseif ($item->disposal_type === 'theft')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                        Hilang / Dicuri
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        {{ ucfirst($item->disposal_type) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-gray-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($item->book_value_at_disposal) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-700 whitespace-nowrap">
                                {{ $item->sale_price > 0 ? \App\Helpers\MoneyHelper::rupiah($item->sale_price) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold whitespace-nowrap {{ ($item->gain_loss_amount ?? 0) >= 0 ? 'text-[#15803d]' : 'text-rose-700' }}">
                                @if ($item->gain_loss_amount !== null)
                                    {{ $item->gain_loss_amount >= 0 ? '+' : '-' }}{{ \App\Helpers\MoneyHelper::rupiah(abs($item->gain_loss_amount)) }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('keuangan.asset.disposal.show', $item) }}"
                                        class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Rincian & Jurnal"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('keuangan.asset.disposal.pdf', $item) }}"
                                        target="_blank"
                                        class="p-1.5 text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded-lg transition"
                                        title="Cetak Berita Acara Pelepasan (PDF)"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                <p class="font-medium text-xs">Belum ada transaksi pelepasan aset.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($disposals->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $disposals->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
