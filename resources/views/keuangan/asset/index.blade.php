@extends('layouts.app')

@section('title', 'Master Aset Tetap – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Tombol Tambah --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Aset Tetap</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Aset Tetap (Fixed Assets)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pencatatan inventaris aset berwujud, nilai perolehan, otomatisasi penyusutan, dan integrasi buku besar.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('keuangan.asset.depreciation.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-xs sm:text-sm font-semibold rounded-xl border border-amber-200 transition"
            >
                <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Depresiasi Bulanan
            </a>
            <a
                href="{{ route('keuangan.asset.transfer.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-blue-50 hover:bg-blue-100 text-blue-800 text-xs sm:text-sm font-semibold rounded-xl border border-blue-200 transition"
            >
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Mutasi Cabang
            </a>
            <a
                href="{{ route('keuangan.asset.disposal.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-800 text-xs sm:text-sm font-semibold rounded-xl border border-rose-200 transition"
            >
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Pelepasan / Disposal
            </a>
            <a
                href="{{ route('keuangan.asset.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Registrasi Aset Baru
            </a>
        </div>
    </div>

    {{-- Flash Notifications --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- 5 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        {{-- Total Unit --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Unit</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_units']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Semua fisik aset</p>
            </div>
            <div class="w-12 h-12 bg-gray-50 text-gray-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>

        {{-- Total Nilai Perolehan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Nilai Perolehan</p>
                <h3 class="text-lg font-bold text-gray-900 mt-1">{{ \App\Helpers\MoneyHelper::rupiah($kpis['total_cost']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Harga beli tercatat</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        {{-- Akumulasi Penyusutan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Akum. Penyusutan</p>
                <h3 class="text-lg font-bold text-amber-600 mt-1">{{ \App\Helpers\MoneyHelper::rupiah($kpis['total_accumulated_depreciation']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Nilai terdepresiasi</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                </svg>
            </div>
        </div>

        {{-- Nilai Buku Saat Ini --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-emerald-700 uppercase tracking-wider">Nilai Buku (NBV)</p>
                <h3 class="text-lg font-bold text-emerald-700 mt-1">{{ \App\Helpers\MoneyHelper::rupiah($kpis['total_book_value']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Nilai sisa buku saat ini</p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>

        {{-- Aset Aktif --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Aktif</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_active']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Sedang digunakan</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('keuangan.asset.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            {{-- Input Pencarian --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Cari Aset</label>
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Kode AST-XXXX, nama barang, catatan..."
                        class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- Filter Cabang --}}
            @if ($canManageAll)
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Cabang</label>
                <select name="cabang_id" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="">Semua Cabang</option>
                    @foreach ($cabangs as $cb)
                        <option value="{{ $cb->id }}" {{ request('cabang_id') == $cb->id ? 'selected' : '' }}>
                            {{ $cb->kode }} - {{ $cb->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            {{-- Filter Status --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Status</label>
                <select name="status" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="all">Semua Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="fully_depreciated" {{ request('status') === 'fully_depreciated' ? 'selected' : '' }}>Disusutkan Penuh</option>
                    <option value="disposed" {{ request('status') === 'disposed' ? 'selected' : '' }}>Pelepasan / Dihapus</option>
                </select>
            </div>

            {{-- Filter Kategori COA --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Kategori Akun</label>
                <select name="asset_coa_id" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="">Semua Kategori</option>
                    @foreach ($assetCoas as $coa)
                        <option value="{{ $coa->id }}" {{ request('asset_coa_id') == $coa->id ? 'selected' : '' }}>
                            {{ $coa->code }} - {{ $coa->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol Filter & Reset --}}
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 bg-gray-800 hover:bg-gray-900 text-white rounded-xl text-sm font-semibold transition active:scale-[0.98]"
                >
                    Filter
                </button>
                <a
                    href="{{ route('keuangan.asset.index') }}"
                    class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-semibold transition"
                    title="Reset Filter"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Tabel Data Aset --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Kode & Nama Aset</th>
                        <th class="py-3.5 px-4">Cabang</th>
                        <th class="py-3.5 px-4">Kategori Akun</th>
                        <th class="py-3.5 px-4">Tgl Perolehan</th>
                        <th class="py-3.5 px-4 text-right">Biaya Perolehan</th>
                        <th class="py-3.5 px-4 text-right">Akum. Penyusutan</th>
                        <th class="py-3.5 px-4 text-right">Nilai Buku</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($assets as $asset)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            {{-- Kode & Nama --}}
                            <td class="py-3.5 px-4">
                                <a href="{{ route('keuangan.asset.show', $asset) }}" class="font-bold text-[#15803d] hover:underline block">
                                    {{ $asset->code }}
                                </a>
                                <span class="font-medium text-gray-900 block truncate max-w-xs">{{ $asset->name }}</span>
                                @if ($asset->product)
                                    <span class="text-[11px] text-gray-400">SKU: {{ $asset->product->sku }}</span>
                                @endif
                            </td>

                            {{-- Cabang --}}
                            <td class="py-3.5 px-4 text-gray-700 whitespace-nowrap">
                                <span class="font-medium">{{ $asset->cabang->nama ?? '-' }}</span>
                            </td>

                            {{-- Kategori Akun --}}
                            <td class="py-3.5 px-4 text-gray-600 whitespace-nowrap">
                                <span class="text-xs font-mono font-semibold text-gray-500">{{ $asset->assetCoa->code ?? '-' }}</span>
                                <div class="text-xs text-gray-700">{{ $asset->assetCoa->name ?? '-' }}</div>
                            </td>

                            {{-- Tgl Perolehan --}}
                            <td class="py-3.5 px-4 text-gray-600 whitespace-nowrap">
                                <span class="block text-gray-900 font-medium">{{ $asset->purchase_date ? $asset->purchase_date->format('d/m/Y') : '-' }}</span>
                                <span class="text-xs text-gray-400">Pakai: {{ $asset->usage_date ? $asset->usage_date->format('d/m/Y') : '-' }}</span>
                            </td>

                            {{-- Biaya Perolehan --}}
                            <td class="py-3.5 px-4 text-right font-semibold text-gray-900 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($asset->purchase_cost) }}
                            </td>

                            {{-- Akumulasi Penyusutan --}}
                            <td class="py-3.5 px-4 text-right text-amber-600 whitespace-nowrap font-medium">
                                {{ \App\Helpers\MoneyHelper::rupiah($asset->accumulated_depreciation) }}
                            </td>

                            {{-- Nilai Buku --}}
                            <td class="py-3.5 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($asset->book_value) }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if (in_array($asset->status, ['active', 'posted']))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                        Aktif
                                    </span>
                                @elseif ($asset->status === 'fully_depreciated')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                        Disusutkan Penuh
                                    </span>
                                @elseif ($asset->status === 'disposed')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                                        Pelepasan / Dihapus
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                                        {{ ucfirst($asset->status) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    {{-- Detail --}}
                                    <a
                                        href="{{ route('keuangan.asset.show', $asset) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail Profil Aset"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Edit --}}
                                    <a
                                        href="{{ route('keuangan.asset.edit', $asset) }}"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Ubah Data Aset"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    {{-- Cetak PDF --}}
                                    <a
                                        href="{{ route('keuangan.asset.pdf', $asset) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-purple-600 hover:bg-purple-50 rounded-lg transition"
                                        title="Cetak Kartu Aset / Barcode PDF"
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
                            <td colspan="9" class="py-12 text-center text-gray-500">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-3">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-700">Belum Ada Data Aset Tetap</p>
                                    <p class="text-xs text-gray-400 mt-1 mb-4">Mulai daftarkan aset perusahaan untuk mengelola penyusutan dan nilai buku secara otomatis.</p>
                                    <a
                                        href="{{ route('keuangan.asset.create') }}"
                                        class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition"
                                    >
                                        Registrasi Aset Sekarang
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($assets->hasPages())
            <div class="p-4 border-t border-gray-100">
                {{ $assets->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
