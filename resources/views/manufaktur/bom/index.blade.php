@extends('layouts.app')

@section('title', 'Resep Produksi (Bill of Materials) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Tombol Aksi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Manufaktur & Produksi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Resep Produksi (BOM)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Master Resep Produksi (Bill of Materials)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Definisi formula baku, rasio kebutuhan bahan baku, serta estimasi biaya standar tenaga kerja dan overhead.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.bom.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Resep BOM Baru
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
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

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Resep BOM</p>
                    <p class="text-2xl font-extrabold text-gray-900 mt-1">{{ number_format($totalBoms) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Semua formula terdaftar</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Resep Aktif</p>
                    <p class="text-2xl font-extrabold text-emerald-600 mt-1">{{ number_format($activeBoms) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-emerald-600 font-medium mt-2">Siap digunakan di SPK</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Resep Nonaktif</p>
                    <p class="text-2xl font-extrabold text-gray-500 mt-1">{{ number_format($inactiveBoms) }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-gray-100 text-gray-500 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Arsip formula lama</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-4 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rata-rata Biaya BOM</p>
                    <p class="text-2xl font-extrabold text-gray-900 mt-1">Rp {{ number_format($avgTotalCost, 0, ',', '.') }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mt-2">Bahan + TKL + Overhead</p>
        </div>
    </div>

    {{-- Filter Bar & Table Card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Navigation Tabs --}}
        <div class="border-b border-gray-100 px-4 pt-3 flex flex-wrap items-center justify-between gap-4 bg-gray-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-2">
                <a
                    href="{{ route('manufaktur.bom.index', array_merge(request()->query(), ['tab' => 'semua'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'semua' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Semua ({{ $totalBoms }})
                </a>
                <a
                    href="{{ route('manufaktur.bom.index', array_merge(request()->query(), ['tab' => 'aktif'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'aktif' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Aktif ({{ $activeBoms }})
                </a>
                <a
                    href="{{ route('manufaktur.bom.index', array_merge(request()->query(), ['tab' => 'nonaktif'])) }}"
                    class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition {{ $tab === 'nonaktif' ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    Nonaktif ({{ $inactiveBoms }})
                </a>
            </div>

            {{-- Form Search & Cabang --}}
            <form method="GET" action="{{ route('manufaktur.bom.index') }}" class="flex items-center gap-2 pb-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                
                @if ($cabangs->count() > 1)
                    <select
                        name="cabang_id"
                        onchange="this.form.submit()"
                        class="text-xs rounded-xl border-gray-300 py-1.5 pl-3 pr-8 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                        <option value="">Semua Cabang</option>
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ $cabangId == $cb->id ? 'selected' : '' }}>
                                {{ $cb->nama }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari kode, nama, produk..."
                        class="w-48 sm:w-64 text-xs rounded-xl border-gray-300 py-1.5 pl-8 pr-3 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <button
                    type="submit"
                    class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition"
                >
                    Filter
                </button>

                @if ($search || $cabangId)
                    <a
                        href="{{ route('manufaktur.bom.index', ['tab' => $tab]) }}"
                        class="px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50 rounded-xl transition font-medium"
                        title="Reset Filter"
                    >
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Tabel BOM --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-600">
                <thead class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Kode & Nama BOM</th>
                        <th class="py-3 px-4">Produk Jadi</th>
                        <th class="py-3 px-4 text-center">Hasil Jadi (Qty)</th>
                        <th class="py-3 px-4 text-center">Komponen Bahan</th>
                        <th class="py-3 px-4 text-right">Biaya Bahan</th>
                        <th class="py-3 px-4 text-right">Total Biaya BOM</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    @forelse ($boms as $bom)
                        @php
                            $materialCost = $bom->items->sum(fn($i) => (float)($i->subtotal ?? ($i->quantity * $i->unit_price)));
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('manufaktur.bom.show', $bom) }}" class="font-bold text-gray-900 hover:text-[#15803d]">
                                    {{ $bom->code }}
                                </a>
                                <div class="text-[11px] text-gray-500 font-normal">{{ $bom->nama_bom }}</div>
                                @if ($bom->cabang && $bom->cabang->nama)
                                    <span class="inline-block mt-0.5 text-[10px] text-gray-400 font-mono">📍 {{ $bom->cabang->nama }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">
                                    {{ $bom->product?->name ?? 'Produk Dihapus' }}
                                </div>
                                <div class="text-[11px] text-gray-400 font-mono">SKU: {{ $bom->product?->sku ?? '-' }}</div>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-gray-900">
                                {{ rtrim(rtrim((string) $bom->quantity, '0'), '.') }}
                                <span class="text-gray-500 font-normal text-[11px]">{{ $bom->uom?->name ?? 'Pcs' }}</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-700">
                                    {{ $bom->items->count() }} item bahan
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-gray-700">
                                Rp {{ number_format($materialCost, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-[#15803d]">
                                Rp {{ number_format($bom->total_cost, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($bom->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <a
                                        href="{{ route('manufaktur.bom.show', $bom) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-gray-100 rounded-lg transition"
                                        title="Lihat Detail Resep"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.bom.edit', $bom) }}"
                                        class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                        title="Ubah Resep"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.bom.pdf', $bom) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak PDF Formula"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('manufaktur.bom.toggle-active', $bom) }}" class="inline">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="p-1.5 {{ $bom->is_active ? 'text-gray-400 hover:text-gray-600' : 'text-emerald-500 hover:text-emerald-700' }} hover:bg-gray-100 rounded-lg transition"
                                            title="{{ $bom->is_active ? 'Nonaktifkan Resep' : 'Aktifkan Resep' }}"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-700">Belum ada Resep Produksi (BOM)</p>
                                <p class="text-xs text-gray-400 mt-0.5">Mulai dengan membuat formula resep pertama untuk produk yang dirakit/diproduksi.</p>
                                <div class="mt-4">
                                    <a
                                        href="{{ route('manufaktur.bom.create') }}"
                                        class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold bg-[#15803d] text-white rounded-xl shadow-sm hover:bg-[#166534] transition"
                                    >
                                        + Tambah BOM Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($boms->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $boms->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
