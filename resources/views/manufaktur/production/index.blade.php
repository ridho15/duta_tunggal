@extends('layouts.app')

@section('title', 'Hasil Produksi (Production Output) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Action Button --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Manufaktur & Produksi</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Hasil Produksi</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pencatatan Hasil Produksi (Production Output)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pencatatan aktual barang jadi dari lini perakitan pabrik sebelum inspeksi mutu (QC).</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('manufaktur.production.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Catat Hasil Produksi
            </a>
        </div>
    </div>


    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total Dokumen</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_output']) }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">Semua pencatatan hasil</p>
            </div>
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Draft Produksi</p>
                <h3 class="text-2xl font-bold text-amber-700 mt-1">{{ number_format($kpis['total_draft']) }}</h3>
                <p class="text-xs text-amber-600/70 mt-0.5">Sedang dirakit / belum final</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center text-amber-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-emerald-600 uppercase tracking-wider">Selesai (Finished)</p>
                <h3 class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($kpis['total_finished']) }}</h3>
                <p class="text-xs text-emerald-600/70 mt-0.5">Lanjut inspeksi mutu (QC)</p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-blue-600 uppercase tracking-wider">Total Unit Jadi</p>
                <h3 class="text-2xl font-bold text-blue-700 mt-1">{{ number_format($kpis['total_qty_produced'], 0, ',', '.') }}</h3>
                <p class="text-xs text-blue-600/70 mt-0.5">Kuantitas hasil produksi</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Filter Bar & Segmented Tabs --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-gray-100">
            {{-- Status Segmented Tabs --}}
            <div class="inline-flex p-1 bg-gray-50 rounded-xl border border-gray-200/70 text-xs font-medium">
                <a
                    href="{{ route('manufaktur.production.index', array_merge(request()->except(['status', 'page']))) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ !request()->has('status') || request('status') === '' ? 'bg-white text-gray-900 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Semua
                </a>
                <a
                    href="{{ route('manufaktur.production.index', array_merge(request()->except('page'), ['status' => 'draft'])) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ request('status') === 'draft' ? 'bg-white text-amber-700 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Draft
                </a>
                <a
                    href="{{ route('manufaktur.production.index', array_merge(request()->except('page'), ['status' => 'finished'])) }}"
                    class="px-3 py-1.5 rounded-lg transition {{ request('status') === 'finished' ? 'bg-white text-emerald-700 shadow-xs font-bold' : 'text-gray-500 hover:text-gray-900' }}"
                >
                    Selesai (Finished)
                </a>
            </div>

            <div class="text-xs text-gray-400">
                Menampilkan {{ $productions->firstItem() ?? 0 }} - {{ $productions->lastItem() ?? 0 }} dari {{ $productions->total() }} data
            </div>
        </div>

        {{-- Search & Date Filters --}}
        <form method="GET" action="{{ route('manufaktur.production.index') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            @if(request()->filled('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            {{-- Grup Kiri: Pencarian Kata Kunci --}}
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Cari No. Produksi, No. SPK, nama produk / SKU..."
                    class="w-full pl-9 pr-3 py-2 bg-gray-50/70 border border-gray-200 rounded-xl text-xs text-gray-800 placeholder-gray-400 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                >
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
                @if(request()->anyFilled(['search', 'date_from', 'date_until', 'status']))
                    <a
                        href="{{ route('manufaktur.production.index') }}"
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
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">No. Bukti Produksi</th>
                        <th class="py-3 px-4">Referensi SPK / Produk Jadi</th>
                        <th class="py-3 px-4 text-right">Target SPK</th>
                        <th class="py-3 px-4 text-right">Qty Hasil</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4 text-center">Status Produksi</th>
                        <th class="py-3 px-4 text-center">Status QC</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($productions as $item)
                        @php
                            $spk = $item->manufacturingOrder;
                            $plan = $spk?->productionPlan;
                            $product = $plan?->product;
                            $qc = $item->qualityControl;
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                <a href="{{ route('manufaktur.production.show', $item) }}" class="text-[#15803d] hover:underline">
                                    {{ $item->production_number }}
                                </a>
                                @if($spk?->cabang)
                                    <div class="text-[10px] font-sans text-gray-400 font-normal">
                                        {{ $spk->cabang->nama }}
                                    </div>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">
                                    {{ $product?->name ?? 'Produk Tidak Terdaftar' }}
                                </div>
                                <div class="text-[11px] text-gray-500 flex items-center gap-1.5 mt-0.5">
                                    <span class="font-mono text-gray-400">SKU: {{ $product?->sku ?? '-' }}</span>
                                    <span>•</span>
                                    <span class="text-gray-500">SPK: {{ $spk?->mo_number ?? '-' }}</span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-right font-mono font-medium text-gray-600">
                                {{ number_format((float) ($plan?->quantity ?? 0), 2, ',', '.') }}
                                <span class="text-[10px] text-gray-400 font-sans">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                            </td>

                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                {{ number_format((float) ($item->quantity_produced ?? $plan?->quantity ?? 0), 2, ',', '.') }}
                                <span class="text-[10px] text-gray-400 font-sans">{{ $product?->uom?->name ?? 'Pcs' }}</span>
                            </td>

                            <td class="py-3 px-4 text-gray-600 whitespace-nowrap">
                                {{ $item->production_date ? $item->production_date->format('d/m/Y') : '-' }}
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($item->status === 'finished')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Selesai
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($qc && $qc->exists)
                                    @if ($qc->status == 1)
                                        <a href="{{ route('manufaktur.qc.show', $qc) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            QC Lolos: {{ number_format((float)$qc->passed_quantity, 0) }}
                                        </a>
                                    @else
                                        <a href="{{ route('manufaktur.qc.process-form', $qc) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                            Menunggu QC
                                        </a>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs italic">Belum Ada QC</span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <a
                                        href="{{ route('manufaktur.production.show', $item) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Rincian"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('manufaktur.production.pdf', $item) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak Dokumen LHP"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    @if ($item->status === 'draft')
                                        <a
                                            href="{{ route('manufaktur.production.edit', $item) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition"
                                            title="Ubah Draft"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-finish-production', { action: '{{ route('manufaktur.production.finish', $item) }}', number: '{{ $item->production_number }}' })"
                                            class="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                            title="Selesaikan Produksi & Terbitkan QC"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-delete-production', { action: '{{ route('manufaktur.production.destroy', $item) }}', number: '{{ $item->production_number }}' })"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Draft"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-gray-400">
                                <div class="w-12 h-12 mx-auto mb-3 bg-gray-50 rounded-full flex items-center justify-center text-gray-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                                <p class="text-sm font-medium text-gray-600">Belum ada dokumen hasil produksi</p>
                                <p class="text-xs text-gray-400 mt-1">Catat hasil produksi dari Surat Perintah Kerja (SPK) yang sedang berjalan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($productions->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $productions->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Selesai Produksi --}}
    <x-confirmation-modal
        name="finish-production"
        title="Selesaikan Produksi & Terbitkan QC"
        type="success"
        confirm-text="Ya, Selesaikan Produksi"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyelesaikan proses produksi <span class="font-bold text-gray-900 font-mono" x-text="data.number"></span>?
        </p>
        <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg p-2.5 mt-2">
            Status produksi akan berubah menjadi <strong>Finished</strong> dan pemeriksaan Quality Control (QC) akan diterbitkan secara otomatis.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Hapus Draft Produksi --}}
    <x-confirmation-modal
        name="delete-production"
        title="Hapus Draft Bukti Produksi"
        type="danger"
        confirm-text="Ya, Hapus Draft"
        cancel-text="Batal"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus dokumen draft produksi <span class="font-bold text-gray-900 font-mono" x-text="data.number"></span>?
        </p>
        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg p-2.5 mt-2">
            Dokumen draft yang dihapus tidak dapat dipulihkan kembali.
        </p>
    </x-confirmation-modal>
</div>
@endsection
