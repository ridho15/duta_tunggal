@extends('layouts.app')

@section('title', 'Workspace Stock Opname ' . $opname->opname_number . ' – ERP Duta Tunggal')

@section('content')
<div class="space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('gudang.opname.index') }}" class="hover:text-gray-700">Stock Opname</a>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold font-mono">{{ $opname->opname_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">
                    {{ $opname->opname_number }}
                </h1>
                {{-- Status Badge --}}
                @if ($opname->status === 'approved')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Disetujui & Disesuaikan
                    </span>
                @elseif ($opname->status === 'completed')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Selesai Hitung (Menunggu Approval)
                    </span>
                @elseif ($opname->status === 'in_progress')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-sky-50 text-sky-700 border border-sky-200">
                        <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                        Sedang Dihitung Fisik
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Draft (Persiapan)
                    </span>
                @endif
            </div>
        </div>

        {{-- Top Actions --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('gudang.opname.index') }}"
               class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                ← Kembali
            </a>

            @if ($opname->status === 'draft')
                <button type="button" @click="$dispatch('open-modal-delete-opname')" class="px-3 py-2 text-xs font-semibold text-rose-600 bg-white border border-rose-200 rounded-lg hover:bg-rose-50 transition">
                    Hapus Sesi
                </button>

                <form action="{{ route('gudang.opname.start', $opname) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" stroke-width="2"></path>
                            <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path>
                        </svg>
                        <span>Mulai Hitung Fisik</span>
                    </button>
                </form>
            @endif

            @if ($opname->status === 'completed')
                <button type="button" @click="$dispatch('open-modal-recount-opname')" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                    Hitung Ulang
                </button>

                <button type="button"
                        @click="$dispatch('open-modal-approve-opname')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#14532d] rounded-lg transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Setujui &amp; Sesuaikan Stok</span>
                </button>
            @endif

            {{-- Cetak PDF & Excel --}}
            <a href="{{ route('gudang.opname.pdf', $opname) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs"
               title="Cetak Berita Acara / Formulir Hitung Fisik">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
                <span>PDF</span>
            </a>

            @if (in_array($opname->status, ['in_progress', 'completed', 'approved']))
                <a href="{{ route('gudang.opname.excel', $opname) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs"
                   title="Ekspor CSV / Excel">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Excel</span>
                </a>
            @endif
        </div>
    </div>


    {{-- Banner Approved & Jurnal GL --}}
    @if ($opname->status === 'approved')
        <div class="p-4.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-sm text-emerald-900">Stock Opname Telah Selesai & Dibukukan</h4>
                    <p class="text-emerald-800 mt-0.5 text-[11.5px]">
                        Disetujui oleh <strong>{{ $opname->approver?->name ?? 'Supervisor' }}</strong> pada {{ \Carbon\Carbon::parse($opname->approved_at)->translatedFormat('d F Y H:i') }}.
                        Saldo fisik stok telah disesuaikan di gudang <strong>{{ $opname->warehouse?->name }}</strong>.
                    </p>
                </div>
            </div>
            <a href="{{ route('keuangan.journal.index', ['search' => 'ADJ-' . $opname->opname_number]) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700 transition flex-shrink-0 shadow-2xs">
                <span>Lihat Jurnal Penyesuaian</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </a>
        </div>
    @endif

    {{-- Header Info Sesi Opname --}}
    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-2xs space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
                <span class="text-gray-400 block mb-1">Tanggal Pelaksanaan</span>
                <span class="font-bold text-gray-900 text-sm">
                    {{ \Carbon\Carbon::parse($opname->opname_date)->translatedFormat('d F Y') }}
                </span>
            </div>
            <div>
                <span class="text-gray-400 block mb-1">Gudang Audit</span>
                <div class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ $opname->warehouse?->name ?? '-' }}</span>
                </div>
                <span class="text-[11px] text-gray-400">Kode: {{ $opname->warehouse?->kode ?? '-' }}</span>
            </div>
            <div>
                <span class="text-gray-400 block mb-1">Petugas Pembuka Sesi</span>
                <span class="font-bold text-gray-900 text-sm">
                    {{ $opname->creator?->name ?? 'Admin Gudang' }}
                </span>
                <span class="text-[11px] text-gray-400 block">Dibuat {{ $opname->created_at->diffForHumans() }}</span>
            </div>
            <div>
                <span class="text-gray-400 block mb-1">Keterangan Sesi</span>
                <span class="font-medium text-gray-700 italic">
                    {{ $opname->notes ?: 'Tidak ada catatan tambahan' }}
                </span>
            </div>
        </div>
    </div>

    {{-- State 1: DRAFT (Belum Mulai) --}}
    @if ($opname->status === 'draft')
        <div class="bg-white rounded-xl p-10 border border-gray-200 text-center shadow-2xs space-y-4">
            <div class="w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" stroke-width="1.8"></path>
                    <rect x="9" y="3" width="6" height="4" rx="1" stroke-width="1.8"></rect>
                    <path d="M9 14l2 2 4-4" stroke-width="1.8" stroke-linecap="round"></path>
                </svg>
            </div>
            <div class="max-w-md mx-auto space-y-1">
                <h3 class="text-base font-bold text-gray-900">Sesi Opname Siap Dimulai</h3>
                <p class="text-xs text-gray-500">
                    Klik tombol di bawah untuk mengambil snapshot stok sistem seluruh barang di <strong>{{ $opname->warehouse?->name }}</strong> dan membuka lembar kerja penghitungan fisik.
                </p>
            </div>
            <div class="pt-2 flex items-center justify-center gap-3">
                <a href="{{ route('gudang.opname.pdf', $opname) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8"></path>
                    </svg>
                    <span>Cetak Formulir Hitung Fisik (PDF)</span>
                </a>
                <form action="{{ route('gudang.opname.start', $opname) }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" stroke-width="2"></path>
                            <path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"></path>
                        </svg>
                        <span>Mulai Hitung Fisik Sekarang</span>
                    </button>
                </form>
            </div>
        </div>
    @else
        {{-- State 2, 3, 4: IN_PROGRESS / COMPLETED / APPROVED --}}
        {{-- KPI Cards Ringkasan Hitung Fisik --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Total Item --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Total Produk</span>
                <span class="text-xl font-bold text-gray-900">{{ $summary['total_items'] }}</span>
            </div>

            {{-- Dihitung --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Telah Dihitung</span>
                <span class="text-xl font-bold text-sky-600">{{ $summary['counted_items'] }} / {{ $summary['total_items'] }}</span>
            </div>

            {{-- Cocok --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Stok Cocok</span>
                <span class="text-xl font-bold text-emerald-600">{{ $summary['matched_items'] }}</span>
            </div>

            {{-- Surplus (+) --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Surplus (+)</span>
                <span class="text-xl font-bold text-emerald-700">+{{ $summary['surplus_items'] }}</span>
            </div>

            {{-- Defisit (-) --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Defisit (-)</span>
                <span class="text-xl font-bold text-rose-600">-{{ $summary['deficit_items'] }}</span>
            </div>

            {{-- Total Nilai Selisih --}}
            <div class="bg-white rounded-xl p-3.5 border border-gray-200 text-center shadow-2xs">
                <span class="text-[10px] uppercase font-bold text-gray-400 block mb-1">Total Selisih Rp</span>
                <span class="text-sm font-bold {{ $summary['total_diff_value'] >= 0 ? 'text-emerald-700' : 'text-rose-600' }} block mt-1">
                    {{ $summary['total_diff_value'] >= 0 ? '+' : '' }}Rp {{ number_format($summary['total_diff_value'], 0, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Form Workspace Input Hitung Fisik --}}
        <form action="{{ route('gudang.opname.save-progress', $opname) }}" method="POST" id="opnameForm" class="space-y-4">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
                <div class="p-4 bg-gray-50/80 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            Lembar Kerja Hasil Hitung Fisik ({{ $opname->items->count() }} Item)
                        </h3>
                    </div>

                    {{-- Local Table Filter --}}
                    <div class="flex items-center gap-2">
                        <input type="text"
                               id="tableSearch"
                               onkeyup="filterTable()"
                               placeholder="Cari produk pada tabel..."
                               class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-800 w-56">
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse" id="opnameTable">
                        <thead class="sticky top-0 z-10 bg-gray-100 border-b border-gray-200 shadow-2xs">
                            <tr class="text-gray-600 uppercase tracking-wider text-[11px] font-semibold">
                                <th class="py-3 px-3 w-10 text-center">No</th>
                                <th class="py-3 px-3">Kode / SKU</th>
                                <th class="py-3 px-3">Nama Produk / Barang</th>
                                <th class="py-3 px-3 text-center">Satuan</th>
                                <th class="py-3 px-3">Lokasi Rak</th>
                                <th class="py-3 px-3 text-right">Stok Sistem</th>
                                <th class="py-3 px-3 text-center w-36">Hasil Fisik</th>
                                <th class="py-3 px-3 text-right">Selisih Qty</th>
                                <th class="py-3 px-3 text-right">Selisih Nilai (Rp)</th>
                                <th class="py-3 px-3 w-48">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700" id="opnameTableBody">
                            @foreach ($opname->items as $idx => $item)
                                @php
                                    $diff = $item->difference_qty;
                                    $isUncounted = is_null($item->physical_qty);
                                    $isMatched = !$isUncounted && (float) $diff === 0.0;
                                    $isSurplus = !$isUncounted && (float) $diff > 0.0;
                                    $isDeficit = !$isUncounted && (float) $diff < 0.0;
                                @endphp
                                <tr class="hover:bg-gray-50/70 transition item-row" data-product-name="{{ strtolower($item->product?->name ?? '') }} {{ strtolower($item->product?->sku ?? '') }}">
                                    {{-- No --}}
                                    <td class="py-2.5 px-3 text-center font-mono text-gray-400">
                                        {{ $idx + 1 }}
                                    </td>

                                    {{-- SKU --}}
                                    <td class="py-2.5 px-3 font-mono font-bold text-gray-800">
                                        {{ $item->product?->sku ?? '-' }}
                                    </td>

                                    {{-- Nama Produk --}}
                                    <td class="py-2.5 px-3">
                                        <div class="font-bold text-gray-900">{{ $item->product?->name ?? '-' }}</div>
                                        @if ($item->product?->barcode)
                                            <span class="text-[10px] text-gray-400 font-mono">Barcode: {{ $item->product->barcode }}</span>
                                        @endif
                                    </td>

                                    {{-- UOM --}}
                                    <td class="py-2.5 px-3 text-center text-gray-600">
                                        {{ $item->product?->uom?->name ?? 'Pcs' }}
                                    </td>

                                    {{-- Rak --}}
                                    <td class="py-2.5 px-3 text-gray-600">
                                        {{ $item->rak?->name ?? '-' }}
                                    </td>

                                    {{-- Stok Sistem --}}
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-700">
                                        {{ number_format($item->system_qty, 0, ',', '.') }}
                                    </td>

                                    {{-- Input Fisik --}}
                                    <td class="py-2.5 px-3 text-center">
                                        @if ($opname->status === 'in_progress')
                                            <input type="number"
                                                   name="counts[{{ $item->id }}]"
                                                   id="input-{{ $item->id }}"
                                                   step="any"
                                                   min="0"
                                                   value="{{ $item->physical_qty !== null ? $item->physical_qty : '' }}"
                                                   placeholder="Input fisik..."
                                                   oninput="calculateRowVariance({{ $item->id }}, {{ $item->system_qty }}, {{ $item->unit_cost ?: ($item->average_cost ?: 0) }})"
                                                   class="w-28 px-2 py-1 text-xs text-center font-bold border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500 text-emerald-800">
                                        @else
                                            <span class="font-mono font-bold text-gray-900 text-sm">
                                                {{ $item->physical_qty !== null ? number_format($item->physical_qty, 0, ',', '.') : '-' }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Selisih Qty --}}
                                    <td class="py-2.5 px-3 text-right font-mono font-bold" id="diff-qty-{{ $item->id }}">
                                        @if ($isUncounted)
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-500">Belum</span>
                                        @elseif ($isMatched)
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700">Cocok</span>
                                        @elseif ($isSurplus)
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800">+{{ number_format($diff, 0, ',', '.') }}</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700">{{ number_format($diff, 0, ',', '.') }}</span>
                                        @endif
                                    </td>

                                    {{-- Selisih Nilai --}}
                                    <td class="py-2.5 px-3 text-right font-mono font-bold" id="diff-val-{{ $item->id }}">
                                        @if ($isUncounted)
                                            <span class="text-gray-300">-</span>
                                        @elseif ($item->difference_value > 0)
                                            <span class="text-emerald-700">+Rp {{ number_format($item->difference_value, 0, ',', '.') }}</span>
                                        @elseif ($item->difference_value < 0)
                                            <span class="text-rose-600">-Rp {{ number_format(abs($item->difference_value), 0, ',', '.') }}</span>
                                        @else
                                            <span class="text-gray-400">Rp 0</span>
                                        @endif
                                    </td>

                                    {{-- Catatan --}}
                                    <td class="py-2.5 px-3">
                                        @if ($opname->status === 'in_progress')
                                            <input type="text"
                                                   name="notes[{{ $item->id }}]"
                                                   value="{{ $item->notes }}"
                                                   placeholder="Keterangan selisih..."
                                                   class="w-full px-2 py-1 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-emerald-500 text-gray-700">
                                        @else
                                            <span class="text-gray-500 text-[11px]">{{ $item->notes ?: '-' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Action Bar saat Status IN_PROGRESS --}}
                @if ($opname->status === 'in_progress')
                    <div class="p-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                        <span class="text-xs text-gray-500">
                            Simpan hasil hitung secara berkala agar tidak hilang saat input belum selesai.
                        </span>
                        <div class="flex items-center gap-2.5">
                            <button type="submit"
                                    name="action"
                                    value="save"
                                    class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                                Simpan Progres Hitung
                            </button>
                            <button type="submit"
                                    name="action"
                                    value="complete"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                                <span>Tandai Selesai Hitung</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </form>
    @endif
</div>

<script>
    function filterTable() {
        const input = document.getElementById('tableSearch');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('.item-row');

        rows.forEach(row => {
            const text = row.getAttribute('data-product-name');
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function calculateRowVariance(itemId, systemQty, unitCost) {
        const input = document.getElementById(`input-${itemId}`);
        const diffQtyElem = document.getElementById(`diff-qty-${itemId}`);
        const diffValElem = document.getElementById(`diff-val-${itemId}`);

        if (!input || !diffQtyElem || !diffValElem) return;

        const val = input.value.trim();
        if (val === '') {
            diffQtyElem.innerHTML = '<span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-500">Belum</span>';
            diffValElem.innerHTML = '<span class="text-gray-300">-</span>';
            return;
        }

        const physical = parseFloat(val);
        const diff = physical - systemQty;
        const diffVal = diff * unitCost;

        if (diff === 0) {
            diffQtyElem.innerHTML = '<span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700">Cocok</span>';
            diffValElem.innerHTML = '<span class="text-gray-400">Rp 0</span>';
        } else if (diff > 0) {
            diffQtyElem.innerHTML = `<span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-800">+${diff.toLocaleString('id-ID')}</span>`;
            diffValElem.innerHTML = `<span class="text-emerald-700">+Rp ${Math.abs(diffVal).toLocaleString('id-ID')}</span>`;
        } else {
            diffQtyElem.innerHTML = `<span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-700">${diff.toLocaleString('id-ID')}</span>`;
            diffValElem.innerHTML = `<span class="text-rose-600">-Rp ${Math.abs(diffVal).toLocaleString('id-ID')}</span>`;
        }
    }
</script>
    {{-- Modal Konfirmasi Hapus Draft Opname --}}
    <x-confirmation-modal
        name="delete-opname"
        title="Hapus Sesi Stock Opname"
        variant="danger"
        confirm-text="Ya, Hapus Sesi"
        cancel-text="Batal"
        action="{{ route('gudang.opname.destroy', $opname) }}"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus sesi stock opname draft <strong>{{ $opname->opname_number }}</strong>?
        </p>
        <p class="text-xs text-rose-600 mt-2 font-medium">
            Tindakan ini tidak dapat dibatalkan dan seluruh draf perhitungan fisik akan dihapus.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Hitung Ulang --}}
    <x-confirmation-modal
        name="recount-opname"
        title="Hitung Ulang Stock Opname"
        variant="warning"
        confirm-text="Ya, Hitung Ulang"
        cancel-text="Batal"
        action="{{ route('gudang.opname.recount', $opname) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Kembalikan sesi stock opname <strong>{{ $opname->opname_number }}</strong> ke tahap hitung fisik?
        </p>
        <p class="text-xs text-amber-700 mt-2">
            Status akan kembali ke proses perhitungan sehingga petugas gudang dapat merevisi kuantitas fisik.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Setujui Opname --}}
    <x-confirmation-modal
        name="approve-opname"
        title="Setujui Hasil Stock Opname"
        variant="success"
        confirm-text="Ya, Setujui &amp; Sesuaikan Stok"
        cancel-text="Batal"
        action="{{ route('gudang.opname.approve', $opname) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui hasil stock opname <strong>{{ $opname->opname_number }}</strong>?
        </p>
        <p class="text-xs text-[#15803d] mt-2 font-medium">
            Stok sistem akan disesuaikan otomatis mengikuti hasil fisik riil, dan jurnal penyesuaian persediaan akan langsung dibukukan ke Buku Besar.
        </p>
    </x-confirmation-modal>
@endsection
