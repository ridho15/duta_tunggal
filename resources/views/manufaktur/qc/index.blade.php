@extends('layouts.app')

@section('title', 'Kontrol Kualitas Pabrik (QC Manufaktur) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Manufaktur & Produksi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">QC Pabrik</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kontrol Kualitas Pabrik (Quality Control Manufaktur)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Inspeksi mutu hasil produksi barang jadi sebelum dimasukkan ke dalam persediaan gudang siap jual.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.qc.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Terbitkan QC Manual
            </a>
        </div>
    </div>


    {{-- 5 KPI Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-[11px] font-medium text-gray-500 uppercase tracking-wider">Total Inspeksi</p>
            <h3 class="text-xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_qc']) }}</h3>
            <p class="text-[10.5px] text-gray-400 mt-0.5">Semua lembar QC</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-[11px] font-medium text-blue-600 uppercase tracking-wider">Belum Diproses</p>
            <h3 class="text-xl font-bold text-blue-700 mt-1">{{ number_format($kpis['pending_qc']) }}</h3>
            <p class="text-[10.5px] text-blue-600/70 mt-0.5">Menunggu pemeriksaan</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-[11px] font-medium text-emerald-600 uppercase tracking-wider">Sudah Diproses</p>
            <h3 class="text-xl font-bold text-emerald-700 mt-1">{{ number_format($kpis['processed_qc']) }}</h3>
            <p class="text-[10.5px] text-emerald-600/70 mt-0.5">Selesai diinspeksi</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-[11px] font-medium text-emerald-600 uppercase tracking-wider">Unit Lolos (Passed)</p>
            <h3 class="text-xl font-bold text-emerald-700 mt-1">{{ number_format((float)$kpis['total_passed'], 0, ',', '.') }}</h3>
            <p class="text-[10.5px] text-emerald-600/70 mt-0.5">Masuk stok gudang</p>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm col-span-2 sm:col-span-1">
            <p class="text-[11px] font-medium text-rose-600 uppercase tracking-wider">Unit Ditolak (Reject)</p>
            <h3 class="text-xl font-bold text-rose-700 mt-1">{{ number_format((float)$kpis['total_rejected'], 0, ',', '.') }}</h3>
            <p class="text-[10.5px] text-rose-600/70 mt-0.5">Cacat / tidak masuk stok</p>
        </div>
    </div>

    {{-- Filter Bar & Segmented Tabs --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            {{-- Status Segmented Tabs --}}
            <div class="inline-flex p-1 bg-gray-50 rounded-xl border border-gray-200/70 text-xs font-medium">
                <a
                    href="{{ route('manufaktur.qc.index', array_merge(request()->except(['status', 'page']))) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ !request()->has('status') || request('status') === '' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Semua
                </a>
                <a
                    href="{{ route('manufaktur.qc.index', array_merge(request()->except('page'), ['status' => '0'])) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ request('status') === '0' ? 'bg-white text-blue-700 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Belum Diproses
                </a>
                <a
                    href="{{ route('manufaktur.qc.index', array_merge(request()->except('page'), ['status' => '1'])) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ request('status') === '1' ? 'bg-white text-emerald-700 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Sudah Diproses
                </a>
            </div>

            <div class="text-xs text-gray-400">
                Menampilkan {{ $qcList->firstItem() ?? 0 }} - {{ $qcList->lastItem() ?? 0 }} dari {{ $qcList->total() }} inspeksi
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('manufaktur.qc.index') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            @if(request()->filled('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            {{-- Grup Kiri: Pencarian Kata Kunci & Filter Gudang --}}
            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Cari No. QC, Produk, No. SPK..."
                        class="w-full pl-9 pr-3 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                </div>

                <div>
                    <x-searchable-select name="warehouse_id" :options="collect($warehouses)->map(fn ($w) => ['id' => $w->id, 'label' => $w->name])->values()" :value="request('warehouse_id')" placeholder="-- Semua Gudang Tujuan --" size="sm" />
                </div>
            </div>

            {{-- Grup Kanan: Rentang Tanggal Terpadu & Tombol Aksi (Anti-Overlap) --}}
            <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                <div class="inline-flex items-center bg-gray-50/70 border border-gray-200 rounded-xl px-2 py-0.5 focus-within:bg-white focus-within:border-[#15803d] focus-within:ring-1 focus-within:ring-[#15803d] transition">
                    <input
                        type="date"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                        title="Dari Tanggal"
                    >
                    <span class="text-xs text-gray-400 px-1 font-medium">s/d</span>
                    <input
                        type="date"
                        name="date_until"
                        value="{{ request('date_until') }}"
                        class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                        title="Sampai Tanggal"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl transition shadow-xs whitespace-nowrap"
                    title="Terapkan Filter"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>
                @if(request()->anyFilled(['search', 'warehouse_id', 'date_from', 'date_until', 'status']))
                    <a
                        href="{{ route('manufaktur.qc.index') }}"
                        class="p-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl transition flex items-center justify-center"
                        title="Reset Filter"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Main Table Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider">
                        <th class="py-3 px-4">No. QC</th>
                        <th class="py-3 px-4">Referensi Produksi / SPK</th>
                        <th class="py-3 px-4">Produk Jadi</th>
                        <th class="py-3 px-4 text-right">Lolos (Passed)</th>
                        <th class="py-3 px-4 text-right">Ditolak (Reject)</th>
                        <th class="py-3 px-4">Gudang & Rak</th>
                        <th class="py-3 px-4">Petugas QC</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($qcList as $item)
                        @php
                            $prod = $item->fromModel;
                            $spk = $prod?->manufacturingOrder;
                            $product = $item->product ?? $spk?->productionPlan?->product;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                <a href="{{ route('manufaktur.qc.show', $item) }}" class="text-[#15803d] hover:underline">
                                    {{ $item->qc_number }}
                                </a>
                                <div class="text-[10px] text-gray-400 font-sans font-normal">
                                    {{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-' }}
                                </div>
                            </td>

                            <td class="py-3 px-4">
                                @if ($prod)
                                    <div class="font-mono font-semibold text-gray-800">
                                        {{ $prod->production_number }}
                                    </div>
                                    <div class="text-[11px] text-gray-500">
                                        SPK: {{ $spk?->mo_number ?? '-' }}
                                    </div>
                                @else
                                    <span class="text-gray-400 italic">-</span>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">
                                    {{ $product?->name ?? 'Produk Tidak Ditemukan' }}
                                </div>
                                <div class="text-[10.5px] font-mono text-gray-400">
                                    {{ $product?->sku ?? '-' }}
                                </div>
                            </td>

                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700">
                                {{ number_format((float)$item->passed_quantity, 2, ',', '.') }}
                                <span class="text-[10px] text-gray-400 font-sans">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                            </td>

                            <td class="py-3 px-4 text-right font-mono font-bold text-rose-700">
                                {{ number_format((float)$item->rejected_quantity, 2, ',', '.') }}
                                <span class="text-[10px] text-gray-400 font-sans">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                            </td>

                            <td class="py-3 px-4">
                                <div class="text-gray-800 font-medium">
                                    {{ $item->warehouse?->name ?? '-' }}
                                </div>
                                @if ($item->rak)
                                    <div class="text-[10.5px] text-gray-500">
                                        Rak: {{ $item->rak->name }} ({{ $item->rak->code }})
                                    </div>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-gray-700">
                                {{ $item->inspectedBy?->name ?? '-' }}
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($item->status == 1)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Sudah Diproses
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                        Belum Diproses
                                    </span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('manufaktur.qc.show', $item) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail QC"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.qc.pdf', $item) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak Sertifikat Mutu QC"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    @if ($item->status == 0)
                                        <a
                                            href="{{ route('manufaktur.qc.process-form', $item) }}"
                                            class="inline-flex items-center gap-1 px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-[11px] font-bold shadow-xs transition"
                                            title="Proses Inspeksi Mutu"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Proses QC
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <div class="w-12 h-12 mx-auto mb-3 bg-gray-50 rounded-full flex items-center justify-center text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-gray-600">Belum ada dokumen inspeksi QC pabrik</p>
                                <p class="text-xs text-gray-400 mt-1">Dokumen QC dibuat otomatis saat hasil produksi berstatus Selesai (Finished).</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($qcList->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $qcList->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
