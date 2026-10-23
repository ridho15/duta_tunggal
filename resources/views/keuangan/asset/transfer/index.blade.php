@extends('layouts.app')

@section('title', 'Mutasi & Transfer Aset Antar Cabang – Duta Tunggal ERP')

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
                <span class="text-[#15803d] font-bold">Mutasi Antar Cabang</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Mutasi & Transfer Aset Antar Cabang</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pengajuan pemindahan fisik aset antar unit bisnis, alur persetujuan (approval), hingga serah terima selesai.</p>
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
                href="{{ route('keuangan.asset.transfer.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-emerald-800 text-white text-sm font-semibold rounded-xl shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Ajukan Mutasi Baru
            </a>
        </div>
    </div>

    {{-- Alert Notifikasi --}}
    @if (session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl flex items-center gap-3 text-emerald-800 text-sm">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-center gap-3 text-rose-800 text-sm">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Mutasi --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Pengajuan</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_transfers']) }}</h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Seluruh riwayat mutasi</span>
        </div>

        {{-- Pending --}}
        <div class="bg-white p-5 rounded-2xl border border-amber-100 bg-amber-50/20 shadow-sm">
            <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Menunggu Persetujuan</p>
            <h3 class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($kpis['pending_count']) }}</h3>
            <span class="text-[11px] text-amber-600/75 mt-0.5 block">Status pending approval</span>
        </div>

        {{-- Approved (Transit) --}}
        <div class="bg-white p-5 rounded-2xl border border-blue-100 bg-blue-50/20 shadow-sm">
            <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Disetujui (Dalam Transit)</p>
            <h3 class="text-2xl font-bold text-blue-700 mt-1">{{ number_format($kpis['approved_count']) }}</h3>
            <span class="text-[11px] text-blue-600/75 mt-0.5 block">Siap diserahterimakan</span>
        </div>

        {{-- Completed --}}
        <div class="bg-white p-5 rounded-2xl border border-emerald-100 bg-emerald-50/20 shadow-sm">
            <p class="text-xs font-semibold text-[#15803d] uppercase tracking-wider">Mutasi Selesai</p>
            <h3 class="text-2xl font-bold text-[#15803d] mt-1">{{ number_format($kpis['completed_count']) }}</h3>
            <span class="text-[11px] text-emerald-600/75 mt-0.5 block">Lokasi aset telah berpindah</span>
        </div>
    </div>

    {{-- Filter Bar & Pencarian --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('keuangan.asset.transfer.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            {{-- Status Filter --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Status Mutasi</label>
                <select name="status" class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>Semua Status</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Menunggu Persetujuan (Pending)</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Disetujui (Approved)</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Selesai (Completed)</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Dibatalkan (Cancelled)</option>
                </select>
            </div>

            {{-- Cabang Asal --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Cabang Asal</label>
                <select name="from_cabang_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200">
                    <option value="">Semua Cabang Asal</option>
                    @foreach ($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" {{ request('from_cabang_id') == $cabang->id ? 'selected' : '' }}>
                            {{ $cabang->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Cabang Tujuan --}}
            <div>
                <label class="block text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1">Cabang Tujuan</label>
                <select name="to_cabang_id" class="w-full text-xs rounded-xl border-gray-200 focus:border-[#15803d] focus:ring focus:ring-emerald-200">
                    <option value="">Semua Cabang Tujuan</option>
                    @foreach ($cabangs as $cabang)
                        <option value="{{ $cabang->id }}" {{ request('to_cabang_id') == $cabang->id ? 'selected' : '' }}>
                            {{ $cabang->nama }}
                        </option>
                    @endforeach
                </select>
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
                <a href="{{ route('keuangan.asset.transfer.index') }}" class="p-2 text-gray-400 hover:text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </a>
            </div>
        </form>
    </div>

    {{-- Tabel Riwayat Mutasi --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Daftar Pengajuan Mutasi Transfer Aset</h3>
            <span class="text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg">
                Total: {{ $transfers->total() }} Mutasi
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Tgl Pengajuan</th>
                        <th class="py-3 px-4">Aset Tetap</th>
                        <th class="py-3 px-4">Cabang Asal</th>
                        <th class="py-3 px-4">Cabang Tujuan</th>
                        <th class="py-3 px-4">Diajukan Oleh</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transfers as $item)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ $item->transfer_date ? $item->transfer_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <a href="{{ route('keuangan.asset.show', $item->asset_id) }}" class="font-bold text-[#15803d] hover:underline block">
                                    {{ $item->asset->name ?? '-' }}
                                </a>
                                <span class="text-[11px] font-mono text-gray-400 block">{{ $item->asset->code ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-800">
                                {{ $item->fromCabang->nama ?? '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-semibold text-gray-900">
                                {{ $item->toCabang->nama ?? '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-600">
                                {{ $item->requestedBy->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($item->status === 'pending')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                        Menunggu Persetujuan
                                    </span>
                                @elseif ($item->status === 'approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
                                        Disetujui (Transit)
                                    </span>
                                @elseif ($item->status === 'completed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                        Selesai (Completed)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                        Dibatalkan
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('keuangan.asset.transfer.show', $item) }}"
                                        class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail & Proses"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('keuangan.asset.transfer.pdf', $item) }}"
                                        target="_blank"
                                        class="p-1.5 text-purple-600 hover:text-purple-800 hover:bg-purple-50 rounded-lg transition"
                                        title="Cetak Berita Acara (PDF)"
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
                            <td colspan="7" class="py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                <p class="font-medium text-xs">Belum ada data mutasi transfer aset.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($transfers->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $transfers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
