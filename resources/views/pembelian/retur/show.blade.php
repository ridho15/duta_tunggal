@extends('layouts.app')

@section('title', 'Detail Retur Pembelian ' . $return->nota_retur . ' – Duta Tunggal ERP')

@section('content')
@php
    $receipt = $return->purchaseReceipt;
    $po = $receipt?->purchaseOrder;
    $supplier = $receipt?->supplier ?? $po?->supplier;

    $totalQty = $return->purchaseReturnItem->sum(fn ($i) => (float)$i->qty_returned);
    $totalAmount = $return->purchaseReturnItem->sum(fn ($i) => (float)$i->qty_returned * (float)$i->unit_price);

    // Status styling
    $statusClasses = [
        'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
        'pending_approval' => 'bg-amber-50 text-amber-700 border-amber-300',
        'approved' => 'bg-emerald-50 text-[#15803d] border-emerald-300',
        'rejected' => 'bg-red-50 text-red-700 border-red-300',
    ];
    $statusLabels = [
        'draft' => 'Draft Disimpan',
        'pending_approval' => 'Menunggu Persetujuan',
        'approved' => 'Disetujui (Approved)',
        'rejected' => 'Ditolak (Rejected)',
    ];
@endphp

<div
    class="max-w-7xl mx-auto space-y-6"
    x-data="{
        activeTab: 'items',
        showApproveModal: false,
        showRejectModal: false,
        showDeleteModal: false,
    }"
>
    {{-- Header Breadcrumb & Actions --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.returns.index') }}" class="text-gray-500 hover:text-gray-900 transition">Retur Pembelian</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $return->nota_retur }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $return->nota_retur }}</h1>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $statusClasses[$return->status] ?? 'bg-gray-100 text-gray-700 border-gray-300' }}">
                    <span class="w-2 h-2 rounded-full {{ $return->status === 'approved' ? 'bg-[#15803d]' : ($return->status === 'pending_approval' ? 'bg-amber-500' : ($return->status === 'rejected' ? 'bg-red-500' : 'bg-gray-400')) }}"></span>
                    {{ $statusLabels[$return->status] ?? ucfirst($return->status) }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Dokumen pengembalian barang atas Surat Jalan: <strong>{{ $receipt?->receipt_number ?: '-' }}</strong></p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('pembelian.returns.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>

            {{-- Cetak PDF --}}
            <a
                href="{{ route('pembelian.returns.pdf', $return->id) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            {{-- Edit (Hanya Draft atau Ditolak) --}}
            @if (in_array($return->status, ['draft', 'rejected']))
                <a
                    href="{{ route('pembelian.returns.edit', $return->id) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Retur
                </a>
            @endif

            {{-- Ajukan Persetujuan (Hanya Draft) --}}
            @if ($return->status === 'draft')
                <form action="{{ route('pembelian.returns.submit', $return->id) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        onclick="return confirm('Apakah Anda yakin ingin mengajukan retur ini untuk persetujuan manajer?');"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Ajukan Persetujuan
                    </button>
                </form>
            @endif

            {{-- Tombol Tolak (Pending Approval) --}}
            @if ($return->status === 'pending_approval')
                <button
                    type="button"
                    @click="showRejectModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-red-200 text-red-700 bg-red-50 hover:bg-red-100 text-xs font-bold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Tolak Retur
                </button>
            @endif

            {{-- Tombol Setujui (Draft / Pending Approval) --}}
            @if (in_array($return->status, ['draft', 'pending_approval']))
                <button
                    type="button"
                    @click="showApproveModal = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Setujui Retur (Approve)
                </button>
            @endif

            {{-- Hapus (Draft atau Rejected) --}}
            @if (in_array($return->status, ['draft', 'rejected']))
                <button
                    type="button"
                    @click="showDeleteModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 text-gray-500 hover:text-red-600 hover:bg-red-50 text-xs font-medium rounded-lg transition"
                    title="Hapus Dokumen"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    {{-- Stepper Progress Alur Retur --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Step 1: Draft --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs bg-emerald-100 text-[#15803d]">
                    ✓
                </div>
                <div>
                    <div class="text-xs font-bold text-gray-900">1. Draft Dibuat</div>
                    <div class="text-[11px] text-gray-500">{{ $return->created_at ? $return->created_at->format('d/m/Y H:i') : '-' }}</div>
                </div>
            </div>

            {{-- Step 2: Pengajuan --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ in_array($return->status, ['pending_approval', 'approved', 'rejected']) ? 'bg-emerald-100 text-[#15803d]' : 'bg-gray-100 text-gray-400' }}">
                    {{ in_array($return->status, ['pending_approval', 'approved', 'rejected']) ? '✓' : '2' }}
                </div>
                <div>
                    <div class="text-xs font-bold {{ in_array($return->status, ['pending_approval', 'approved', 'rejected']) ? 'text-gray-900' : 'text-gray-400' }}">
                        2. Pengajuan
                    </div>
                    <div class="text-[11px] text-gray-500">
                        {{ $return->status === 'draft' ? 'Belum diajukan' : 'Menunggu tinjauan' }}
                    </div>
                </div>
            </div>

            {{-- Step 3: Persetujuan / Penolakan --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ $return->status === 'approved' ? 'bg-emerald-100 text-[#15803d]' : ($return->status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-400') }}">
                    {{ $return->status === 'approved' ? '✓' : ($return->status === 'rejected' ? '✗' : '3') }}
                </div>
                <div>
                    <div class="text-xs font-bold {{ in_array($return->status, ['approved', 'rejected']) ? 'text-gray-900' : 'text-gray-400' }}">
                        {{ $return->status === 'rejected' ? '3. Ditolak' : '3. Persetujuan Manajer' }}
                    </div>
                    <div class="text-[11px] text-gray-500">
                        @if ($return->status === 'approved')
                            Disetujui {{ $return->approved_at ? \Carbon\Carbon::parse($return->approved_at)->format('d/m/Y') : '' }}
                        @elseif ($return->status === 'rejected')
                            Ditolak
                        @else
                            Menunggu evaluasi
                        @endif
                    </div>
                </div>
            </div>

            {{-- Step 4: Stok Gudang & Posting Jurnal --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ $return->status === 'approved' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-400' }}">
                    {{ $return->status === 'approved' ? '✓' : '4' }}
                </div>
                <div>
                    <div class="text-xs font-bold {{ $return->status === 'approved' ? 'text-gray-900' : 'text-gray-400' }}">
                        4. Stok &amp; Potong Hutang
                    </div>
                    <div class="text-[11px] text-gray-500">
                        {{ $return->status === 'approved' ? 'Stok dipotong & GL aktif' : 'Belum diproses' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Alert Catatan Penolakan --}}
    @if ($return->status === 'rejected')
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-800 text-xs space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-sm">
                <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Dokumen Retur Ini Ditolak
            </div>
            <p><strong>Alasan:</strong> {{ $return->rejection_notes ?: '-' }}</p>
            <p class="text-[11px] text-gray-500">Ditolak oleh: {{ $return->rejectedBy?->name ?? 'Manajer' }}</p>
        </div>
    @endif

    {{-- Kartu Ringkasan Informasi Header --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom 1 & 2: Informasi Dokumen & Vendor --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-[#15803d]"></span>
                Informasi Dokumen Retur &amp; Vendor
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-gray-500 block">Pemasok / Vendor:</span>
                    <strong class="text-gray-900 text-sm block mt-0.5">
                        {{ $supplier?->perusahaan ?: ($supplier?->name ?: '-') }}
                    </strong>
                    <span class="text-gray-400">Kode: {{ $supplier?->code ?: '-' }} | Telp: {{ $supplier?->phone ?? $supplier?->telephone ?? '-' }}</span>
                </div>

                <div>
                    <span class="text-gray-500 block">Surat Jalan Penerimaan (GRN):</span>
                    @if ($receipt)
                        <a href="{{ route('gudang.penerimaan.show', $receipt->id) }}" class="text-[#15803d] font-bold hover:underline inline-flex items-center gap-1 mt-0.5">
                            {{ $receipt->receipt_number }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @else
                        <strong class="text-gray-900 mt-0.5 block">-</strong>
                    @endif
                    <div class="text-gray-400">PO Ref: {{ $po?->po_number ?: '-' }}</div>
                </div>

                <div>
                    <span class="text-gray-500 block">Tanggal Retur Dokumen:</span>
                    <strong class="text-gray-900 mt-0.5 block">
                        {{ $return->return_date ? \Carbon\Carbon::parse($return->return_date)->translatedFormat('d F Y') : '-' }}
                    </strong>
                </div>

                <div>
                    <span class="text-gray-500 block">Gudang &amp; Cabang Asal:</span>
                    <strong class="text-gray-900 mt-0.5 block">
                        {{ $receipt?->warehouse?->name ?: 'Gudang Utama' }}
                    </strong>
                    <span class="text-gray-400">Cabang: {{ $return->cabang?->nama ?: 'Kantor Pusat' }}</span>
                </div>

                <div>
                    <span class="text-gray-500 block">Surat Jalan Pengembalian (Delivery Note):</span>
                    <strong class="text-gray-900 mt-0.5 block">
                        {{ $return->delivery_note ?: 'Belum Dicatat' }}
                    </strong>
                    @if ($return->physical_return_date)
                        <span class="text-gray-400">Tgl Fisik: {{ \Carbon\Carbon::parse($return->physical_return_date)->format('d/m/Y') }}</span>
                    @endif
                </div>

                <div>
                    <span class="text-gray-500 block">Ekspedisi / Detail Kurir:</span>
                    <strong class="text-gray-900 mt-0.5 block">
                        {{ $return->shipping_details ?: 'Belum Dicatat' }}
                    </strong>
                </div>
            </div>

            @if ($return->notes)
                <div class="pt-3 border-t border-gray-100 text-xs">
                    <span class="text-gray-500 block">Catatan Dokumen:</span>
                    <div class="mt-1 p-2.5 bg-gray-50 rounded-lg text-gray-700 italic">
                        "{{ $return->notes }}"
                    </div>
                </div>
            @endif
        </div>

        {{-- Kolom 3: Finansial & Debit Note Summary --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                Ringkasan Nilai &amp; Debit Note
            </h2>

            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                    <span class="text-gray-500">Total Kuantitas Fisik:</span>
                    <span class="font-bold text-gray-900 text-sm">{{ number_format($totalQty, 2) }} Unit</span>
                </div>

                <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                    <span class="text-gray-500">Total Nilai Barang (DPP):</span>
                    <span class="font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalAmount) }}</span>
                </div>

                <div class="p-3 bg-emerald-50 rounded-lg border border-emerald-100 space-y-1">
                    <div class="text-[11px] font-bold text-[#15803d] uppercase tracking-wider">Potongan Hutang Dagang (AP)</div>
                    <div class="text-xl font-extrabold text-[#15803d]">
                        {{ \App\Helpers\MoneyHelper::rupiah($totalAmount) }}
                    </div>
                    <div class="text-[10px] text-gray-500">
                        @if ($return->status === 'approved')
                            <span class="text-emerald-700 font-semibold">✓ Saldo hutang telah dipotong otomatis.</span>
                        @else
                            Akan memotong invoice AP saat disetujui.
                        @endif
                    </div>
                </div>

                @if ($accountPayable)
                    <div class="pt-2 text-xs">
                        <span class="text-gray-500 block">Faktur Pembelian Terkait:</span>
                        <div class="mt-1 flex items-center justify-between font-semibold">
                            <span>{{ $accountPayable->invoice_number }}</span>
                            <span class="text-gray-600">Sisa: {{ \App\Helpers\MoneyHelper::rupiah($accountPayable->remaining) }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tab Navigasi Konten --}}
    <div class="border-b border-gray-200">
        <nav class="flex space-x-6 text-xs font-semibold">
            <button
                type="button"
                @click="activeTab = 'items'"
                class="pb-3 border-b-2 transition"
                :class="activeTab === 'items' ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Daftar Barang yang Diretur ({{ $return->purchaseReturnItem->count() }})
            </button>

            <button
                type="button"
                @click="activeTab = 'resolution'"
                class="pb-3 border-b-2 transition"
                :class="activeTab === 'resolution' ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Penyelesaian &amp; Debit Note Vendor
            </button>

            <button
                type="button"
                @click="activeTab = 'journal'"
                class="pb-3 border-b-2 transition"
                :class="activeTab === 'journal' ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Jurnal Akuntansi (GL) ({{ $journalEntries->count() }})
            </button>

            <button
                type="button"
                @click="activeTab = 'audit'"
                class="pb-3 border-b-2 transition"
                :class="activeTab === 'audit' ? 'border-[#15803d] text-[#15803d] font-bold' : 'border-transparent text-gray-500 hover:text-gray-700'"
            >
                Riwayat &amp; Audit Trail
            </button>
        </nav>
    </div>

    {{-- Panel Konten Tab 1: Daftar Barang Diretur --}}
    <div x-show="activeTab === 'items'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900">Rincian Fisik Barang &amp; Harga Satuan PO</h2>
            <span class="text-xs text-gray-500">{{ $return->purchaseReturnItem->count() }} Macam Produk</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 border-y border-gray-200 text-gray-600 uppercase font-semibold text-[11px] tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">No</th>
                        <th class="py-3 px-4">Barang &amp; Kode</th>
                        <th class="py-3 px-4 text-center">Satuan</th>
                        <th class="py-3 px-4 text-center">Kuantitas Diretur</th>
                        <th class="py-3 px-4 text-right">Harga Satuan PO</th>
                        <th class="py-3 px-4 text-right">Subtotal Retur</th>
                        <th class="py-3 px-4">Alasan Pengembalian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($return->purchaseReturnItem as $index => $item)
                        @php
                            $subtotalLine = (float)$item->qty_returned * (float)$item->unit_price;
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="py-3.5 px-4 text-center text-gray-400 font-mono">{{ $index + 1 }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $item->product?->name ?? 'Produk Tidak Ditemukan' }}</div>
                                <div class="text-[11px] text-gray-400 font-mono">Kode: {{ $item->product?->code ?? '-' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-center text-gray-600 font-medium">
                                {{ $item->product?->unitOfMeasure?->name ?? 'Unit' }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    {{ number_format($item->qty_returned, 2) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-medium text-gray-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($item->unit_price) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-gray-900 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($subtotalLine) }}
                            </td>
                            <td class="py-3.5 px-4 text-gray-600">
                                {{ $item->reason ?: '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400">Tidak ada item barang pada dokumen retur ini.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-900">
                        <td colspan="3" class="py-3.5 px-4 text-right uppercase tracking-wider text-[11px]">Total:</td>
                        <td class="py-3.5 px-4 text-center text-sm font-extrabold text-amber-700">{{ number_format($totalQty, 2) }}</td>
                        <td></td>
                        <td class="py-3.5 px-4 text-right text-sm font-extrabold text-[#15803d]">
                            {{ \App\Helpers\MoneyHelper::rupiah($totalAmount) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Panel Konten Tab 2: Penyelesaian & Debit Note Vendor --}}
    <div x-show="activeTab === 'resolution'" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-6">
        <div>
            <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                Pelacakan Resolusi Vendor &amp; Nota Debit (Credit Note)
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">Catat nomor nota debit supplier, konfirmasi pemotongan tagihan, atau pengembalian dana tunai.</p>
        </div>

        @if ($return->status === 'approved')
            <form action="{{ route('pembelian.returns.resolution', $return->id) }}" method="POST" class="space-y-6">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {{-- Tanggapan / Bentuk Kompensasi Vendor --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Bentuk Kompensasi / Tanggapan Vendor
                        </label>
                        <select
                            name="supplier_response"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs bg-white focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        >
                            <option value="">-- Pilih Bentuk Kompensasi --</option>
                            <option value="potong_hutang" {{ $return->supplier_response === 'potong_hutang' ? 'selected' : '' }}>Potong Tagihan Faktur (AP Deduction)</option>
                            <option value="refund_dana" {{ $return->supplier_response === 'refund_dana' ? 'selected' : '' }}>Pengembalian Dana Kas / Bank (Refund)</option>
                            <option value="ganti_barang" {{ $return->supplier_response === 'ganti_barang' ? 'selected' : '' }}>Penggantian Barang Baru (Replacement)</option>
                        </select>
                    </div>

                    {{-- No. Nota Kredit / Debit Note Supplier --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            No. Nota Kredit / Debit Note Supplier
                        </label>
                        <input
                            type="text"
                            name="credit_note_number"
                            value="{{ old('credit_note_number', $return->credit_note_number) }}"
                            placeholder="Contoh: CN-VEND-2026/099"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Tanggal Nota Kredit Supplier --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Tanggal Nota Kredit Supplier
                        </label>
                        <input
                            type="date"
                            name="credit_note_date"
                            value="{{ old('credit_note_date', $return->credit_note_date ? \Carbon\Carbon::parse($return->credit_note_date)->format('Y-m-d') : '') }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Nominal Nota Kredit --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Nominal Nota Kredit (Rp)
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            name="credit_note_amount"
                            value="{{ old('credit_note_amount', $return->credit_note_amount) }}"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Nominal Refund Kas/Bank --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Nominal Pengembalian Dana Kas/Bank (Rp)
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            name="refund_amount"
                            value="{{ old('refund_amount', $return->refund_amount) }}"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Tanggal Refund --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Tanggal Pengembalian Dana
                        </label>
                        <input
                            type="date"
                            name="refund_date"
                            value="{{ old('refund_date', $return->refund_date ? \Carbon\Carbon::parse($return->refund_date)->format('Y-m-d') : '') }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- No. Surat Jalan Pengembalian --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            No. Surat Jalan Pengembalian / Delivery Note
                        </label>
                        <input
                            type="text"
                            name="delivery_note"
                            value="{{ old('delivery_note', $return->delivery_note) }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Tanggal Pengembalian Fisik --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Tanggal Pengiriman Fisik
                        </label>
                        <input
                            type="date"
                            name="physical_return_date"
                            value="{{ old('physical_return_date', $return->physical_return_date ? \Carbon\Carbon::parse($return->physical_return_date)->format('Y-m-d') : '') }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>

                    {{-- Ekspedisi / Kurir --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Ekspedisi / Detail Kurir
                        </label>
                        <input
                            type="text"
                            name="shipping_details"
                            value="{{ old('shipping_details', $return->shipping_details) }}"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                        />
                    </div>
                </div>

                {{-- Catatan Pelacakan --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Catatan Pelacakan Resolusi Vendor
                    </label>
                    <textarea
                        name="tracking_notes"
                        rows="3"
                        placeholder="Contoh: Barang telah diserahkan ke bagian gudang vendor pada tgl 20/10/2026. Credit note telah dipotongkan pada faktur INV-001..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    >{{ old('tracking_notes', $return->tracking_notes) }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Data Resolusi Vendor
                    </button>
                </div>
            </form>
        @else
            <div class="p-8 text-center text-gray-400 bg-gray-50 rounded-xl border border-gray-100">
                <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                <div class="text-xs font-bold text-gray-600">Fitur Resolusi &amp; Debit Note Terkunci</div>
                <div class="text-[11px] text-gray-400 mt-1">Pelacakan nota debit supplier dan refund hanya dapat dicatat setelah dokumen retur berstatus <strong>Disetujui (Approved)</strong>.</div>
            </div>
        @endif
    </div>

    {{-- Panel Konten Tab 3: Jurnal Akuntansi (GL) --}}
    <div x-show="activeTab === 'journal'" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-gray-900">Jurnal Akuntansi Buku Besar (General Ledger)</h2>
                <p class="text-xs text-gray-500 mt-0.5">Pemotongan Hutang Dagang (2110) pada debit dan Persediaan Gudang (1140.01) pada kredit.</p>
            </div>
            @if ($journalEntries->isNotEmpty())
                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-[#15803d]">
                    ✓ Jurnal Seimbang (Balanced)
                </span>
            @endif
        </div>

        @if ($journalEntries->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-y border-gray-200 text-gray-600 uppercase font-semibold text-[11px] tracking-wider">
                            <th class="py-3 px-4">No. Akun (COA)</th>
                            <th class="py-3 px-4">Nama Akun</th>
                            <th class="py-3 px-4">Keterangan Baris Jurnal</th>
                            <th class="py-3 px-4 text-right">Debit (Rp)</th>
                            <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-mono">
                        @php
                            $totalDebit = 0;
                            $totalCredit = 0;
                        @endphp
                        @foreach ($journalEntries as $entry)
                            @php
                                $totalDebit += (float)$entry->debit;
                                $totalCredit += (float)$entry->credit;
                            @endphp
                            <tr class="hover:bg-gray-50 transition">
                                <td class="py-3 px-4 font-bold text-gray-900">{{ $entry->coa?->code ?? '-' }}</td>
                                <td class="py-3 px-4 font-sans font-medium text-gray-900">{{ $entry->coa?->name ?? '-' }}</td>
                                <td class="py-3 px-4 font-sans text-gray-600">{{ $entry->description ?? $entry->notes ?? '-' }}</td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900">
                                    {{ (float)$entry->debit > 0 ? \App\Helpers\MoneyHelper::rupiah($entry->debit) : '-' }}
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900">
                                    {{ (float)$entry->credit > 0 ? \App\Helpers\MoneyHelper::rupiah($entry->credit) : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-900 font-mono">
                            <td colspan="3" class="py-3.5 px-4 text-right font-sans uppercase tracking-wider text-[11px]">Total Jurnal:</td>
                            <td class="py-3.5 px-4 text-right text-sm font-extrabold text-[#15803d]">
                                {{ \App\Helpers\MoneyHelper::rupiah($totalDebit) }}
                            </td>
                            <td class="py-3.5 px-4 text-right text-sm font-extrabold text-[#15803d]">
                                {{ \App\Helpers\MoneyHelper::rupiah($totalCredit) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="p-8 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="text-sm font-semibold text-gray-600">Belum Ada Jurnal Akuntansi</div>
                <div class="text-xs text-gray-400 mt-1">Jurnal akuntansi otomatis akan di-posting ke Buku Besar saat dokumen retur berstatus <strong>Disetujui (Approved)</strong>.</div>
            </div>
        @endif
    </div>

    {{-- Panel Konten Tab 4: Riwayat & Audit Trail --}}
    <div x-show="activeTab === 'audit'" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>
            Riwayat Dokumen &amp; Jejak Audit (Audit Trail)
        </h2>

        <div class="space-y-4 text-xs">
            <div class="flex items-start gap-3">
                <span class="w-2 h-2 rounded-full bg-emerald-500 mt-1.5"></span>
                <div>
                    <strong class="text-gray-900">Dokumen Dibuat:</strong>
                    <div class="text-gray-500">Dibuat oleh {{ $return->createdBy?->name ?? 'Pengguna Sistem' }} pada {{ $return->created_at ? $return->created_at->format('d/m/Y H:i') : '-' }}</div>
                </div>
            </div>

            @if ($return->approved_by)
                <div class="flex items-start gap-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-600 mt-1.5"></span>
                    <div>
                        <strong class="text-gray-900">Dokumen Disetujui (Approved):</strong>
                        <div class="text-gray-500">Disetujui oleh {{ $return->approvedBy?->name ?? 'Manajer' }} pada {{ $return->approved_at ? \Carbon\Carbon::parse($return->approved_at)->format('d/m/Y H:i') : '-' }}</div>
                        @if ($return->approval_notes)
                            <div class="text-gray-600 italic mt-0.5">Catatan: "{{ $return->approval_notes }}"</div>
                        @endif
                    </div>
                </div>
            @endif

            @if ($return->rejected_by)
                <div class="flex items-start gap-3">
                    <span class="w-2 h-2 rounded-full bg-red-600 mt-1.5"></span>
                    <div>
                        <strong class="text-gray-900">Dokumen Ditolak (Rejected):</strong>
                        <div class="text-gray-500">Ditolak oleh {{ $return->rejectedBy?->name ?? 'Manajer' }} pada {{ $return->rejected_at ? \Carbon\Carbon::parse($return->rejected_at)->format('d/m/Y H:i') : '-' }}</div>
                        <div class="text-red-700 italic mt-0.5">Alasan: "{{ $return->rejection_notes }}"</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Approve --}}
    <div
        x-show="showApproveModal"
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        style="display: none;"
    >
        <div
            @click.outside="showApproveModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-gray-100"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-[#15803d] flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Setujui Retur Pembelian</h3>
                    <p class="text-xs text-gray-500">Konfirmasi persetujuan dokumen retur {{ $return->nota_retur }}</p>
                </div>
            </div>

            <p class="text-xs text-gray-600 bg-amber-50 p-3 rounded-lg border border-amber-200">
                Persetujuan akan memicu 3 aksi otomatis:
                <br>1. Mengurangi stok gudang produk terkait.
                <br>2. Memotong hutang dagang (AP) vendor terkait.
                <br>3. Menerbitkan jurnal akuntansi otomatis ke Buku Besar.
            </p>

            <form action="{{ route('pembelian.returns.approve', $return->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan Persetujuan (Opsional)</label>
                    <textarea
                        name="approval_notes"
                        rows="2"
                        placeholder="Contoh: Barang fisik telah dicek tim QC dan diserahkan ke vendor..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="showApproveModal = false"
                        class="px-4 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        Ya, Setujui Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Reject --}}
    <div
        x-show="showRejectModal"
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        style="display: none;"
    >
        <div
            @click.outside="showRejectModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-gray-100"
        >
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Tolak Retur Pembelian</h3>
                    <p class="text-xs text-gray-500">Dokumen {{ $return->nota_retur }} akan dikembalikan ke status draft/ditolak.</p>
                </div>
            </div>

            <form action="{{ route('pembelian.returns.reject', $return->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="rejection_notes"
                        rows="3"
                        required
                        placeholder="Tuliskan alasan penolakan secara jelas (minimal 5 karakter)..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="showRejectModal = false"
                        class="px-4 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                    >
                        Ya, Tolak Retur
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Delete --}}
    <div
        x-show="showDeleteModal"
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4"
        style="display: none;"
    >
        <div
            @click.outside="showDeleteModal = false"
            class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 border border-gray-100 text-center"
        >
            <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Hapus Dokumen Retur?</h3>
                <p class="text-xs text-gray-500 mt-1">Dokumen <strong>{{ $return->nota_retur }}</strong> dan seluruh item di dalamnya akan dihapus permanen.</p>
            </div>

            <form action="{{ route('pembelian.returns.destroy', $return->id) }}" method="POST" class="flex items-center justify-center gap-2 pt-2">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="showDeleteModal = false"
                    class="px-4 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                >
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
