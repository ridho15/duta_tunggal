@extends('layouts.app')

@section('title', 'Detail Penerimaan Barang ' . ($receipt->receipt_number ?: 'GRN-' . $receipt->id) . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    confirmModalOpen: false, 
    deleteModalOpen: false,
    previewPhotoModalOpen: false,
    activePhotoUrl: '' 
}">
    {{-- Header Dokumen --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('gudang.penerimaan.index') }}" class="hover:underline">Penerimaan Barang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold font-mono">{{ $receipt->receipt_number ?: ('GRN-' . $receipt->id) }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">
                    {{ $receipt->receipt_number ?: ('GRN-' . $receipt->id) }}
                </h1>
                {{-- Status Badge --}}
                @if ($receipt->status === 'completed')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Selesai Masuk Stok
                    </span>
                @elseif ($receipt->status === 'partial')
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        Diterima Sebagian
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Draf (Belum Masuk Stok)
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Dibuat pada {{ optional($receipt->created_at)->format('d/m/Y H:i') }} &bull; Penerima: <span class="font-semibold text-gray-700">{{ $receipt->receivedBy->name ?? 'Gudang' }}</span>
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2">
            {{-- Cetak PDF --}}
            <a
                href="{{ route('gudang.penerimaan.pdf', $receipt) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl border border-gray-200 shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Surat Jalan Masuk
            </a>

            {{-- Shortcut Buat Retur Pembelian (Jika Selesai/Parsial) --}}
            @if (in_array($receipt->status, ['completed', 'partial']))
                <a
                    href="{{ route('pembelian.returns.create', ['supplier_id' => $receipt->supplier_id ?? $receipt->purchaseOrder?->supplier_id, 'receipt_id' => $receipt->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 text-sm font-semibold rounded-xl border border-amber-300 transition"
                    title="Buat Retur Pembelian atas barang yang diterima"
                >
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 14 4 9l5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    Buat Retur
                </a>
            @endif

            {{-- Jika Masih Draf --}}
            @if ($receipt->status === 'draft')
                {{-- Edit --}}
                <a
                    href="{{ route('gudang.penerimaan.edit', $receipt) }}"
                    class="px-3.5 py-2 text-sm font-semibold text-gray-700 bg-amber-50 hover:bg-amber-100 border border-amber-300 rounded-xl transition"
                >
                    Edit Draf
                </a>

                {{-- Hapus --}}
                <button
                    type="button"
                    @click="deleteModalOpen = true"
                    class="px-3 py-2 text-sm font-semibold text-red-600 bg-red-50 hover:bg-red-100 border border-red-200 rounded-xl transition"
                    title="Hapus Draf"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>

                {{-- Konfirmasi Masuk Stok --}}
                <button
                    type="button"
                    @click="confirmModalOpen = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Konfirmasi & Masuk Stok
                </button>
            @endif
        </div>
    </div>

    {{-- Banner Retur Pembelian Otomatis (Jika Terdapat Barang Reject) --}}
    @if ($receipt->purchaseReturns->isNotEmpty())
        @foreach ($receipt->purchaseReturns as $ret)
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-amber-100 text-amber-800 rounded-xl flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M9 14 4 9l5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="2" stroke-linecap="round"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-amber-900 uppercase tracking-wide">Retur Pembelian Otomatis (QC Reject)</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900 font-mono">{{ $ret->nota_retur }}</span>
                        </div>
                        <p class="text-xs text-amber-700 mt-0.5">
                            Sebanyak <strong>{{ number_format($ret->purchaseReturnItem->sum('qty_returned')) }} pcs</strong> barang reject dialihkan otomatis ke dokumen retur supplier dan tidak masuk kartu stok gudang.
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('pembelian.returns.show', $ret) }}"
                    class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition inline-flex items-center gap-1 flex-shrink-0 self-start sm:self-auto"
                >
                    Lihat Dokumen Retur &rarr;
                </a>
            </div>
        @endforeach
    @endif

    {{-- Visual Stepper Process --}}
    @php
        $isCompleted = ($receipt->status === 'completed');
        $isDraft = ($receipt->status === 'draft');
    @endphp
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 relative">
            {{-- Step 1: Draf Penerimaan Fisik --}}
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-[#15803d] text-white font-bold flex items-center justify-center flex-shrink-0 text-sm shadow">
                    ✓
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#15803d]">Tahap 1</span>
                    <h4 class="text-sm font-bold text-gray-900 leading-tight">Draf Penerimaan Fisik</h4>
                    <p class="text-[11px] text-gray-500 mt-0.5">Surat jalan dicatat petugas gudang</p>
                </div>
            </div>

            {{-- Step 2: Pemeriksaan & Verifikasi --}}
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-sm {{ $isCompleted ? 'bg-[#15803d] text-white shadow' : 'bg-emerald-100 text-[#15803d] ring-2 ring-[#15803d]/30' }}">
                    {{ $isCompleted ? '✓' : '2' }}
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-[#15803d]">Tahap 2</span>
                    <h4 class="text-sm font-bold text-gray-900 leading-tight">Pemeriksaan Barang</h4>
                    <p class="text-[11px] text-gray-500 mt-0.5">Pemeriksaan kondisi & kuantitas</p>
                </div>
            </div>

            {{-- Step 3: Masuk Stok Gudang & Jurnal --}}
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-sm {{ $isCompleted ? 'bg-[#15803d] text-white shadow' : 'bg-gray-100 text-gray-400' }}">
                    {{ $isCompleted ? '✓' : '3' }}
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider {{ $isCompleted ? 'text-[#15803d]' : 'text-gray-400' }}">Tahap 3</span>
                    <h4 class="text-sm font-bold text-gray-900 leading-tight">Masuk Stok Persediaan</h4>
                    <p class="text-[11px] text-gray-500 mt-0.5">Stok gudang bertambah & jurnal dibukukan</p>
                </div>
            </div>

            {{-- Step 4: Siap Faktur Pembelian (AP) --}}
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full font-bold flex items-center justify-center flex-shrink-0 text-sm {{ $isCompleted ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-400' }}">
                    4
                </div>
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider {{ $isCompleted ? 'text-indigo-600' : 'text-gray-400' }}">Tahap 4</span>
                    <h4 class="text-sm font-bold text-gray-900 leading-tight">Faktur Pembelian (AP)</h4>
                    <p class="text-[11px] text-gray-500 mt-0.5">Siap diterbitkan tagihan supplier</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Main 2-Column Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Column (Wide, 8 cols) --}}
        <div class="lg:col-span-8 space-y-6">
            {{-- Card 1: Informasi Dokumen & Vendor --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Informasi Dokumen & Pengiriman</h3>
                    @if ($receipt->purchaseOrder && $receipt->purchaseOrder->exists)
                        <a
                            href="{{ route('pembelian.po.show', $receipt->purchaseOrder) }}"
                            class="text-xs font-bold text-[#15803d] hover:text-[#166534] hover:underline flex items-center gap-1 font-mono"
                        >
                            <span>Buka PO {{ $receipt->purchaseOrder->po_number }}</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Supplier / Vendor</span>
                        <span class="text-base font-bold text-gray-900 block mt-0.5">
                            {{ $receipt->purchaseOrder->supplier->name ?? 'Supplier' }}
                        </span>
                        <span class="text-xs text-gray-500 block mt-0.5">
                            Kode: {{ $receipt->purchaseOrder->supplier->kode ?? '-' }} &bull; Telp: {{ $receipt->purchaseOrder->supplier->telepon ?? '-' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Surat Jalan Vendor</span>
                        <span class="text-base font-bold text-gray-900 block mt-0.5 font-mono">
                            {{ $receipt->supplier_delivery_note_number ?: '(Tidak dicantumkan)' }}
                        </span>
                        <span class="text-xs text-gray-500 block mt-0.5">
                            Tanggal Terima: <span class="font-semibold text-gray-700">{{ optional($receipt->receipt_date)->format('d F Y') ?? '-' }}</span>
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Petugas Penerima Gudang</span>
                        <span class="text-sm font-bold text-gray-900 block mt-0.5">
                            {{ $receipt->receivedBy->name ?? '-' }}
                        </span>
                        <span class="text-xs text-gray-500 block">
                            Cabang: {{ $receipt->cabang->nama ?? 'Pusat' }}
                        </span>
                    </div>

                    <div>
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider block">Mata Uang & Kurs</span>
                        <span class="text-sm font-bold text-gray-900 block mt-0.5">
                            {{ $receipt->currency->name ?? 'Indonesian Rupiah' }} ({{ $receipt->currency->symbol ?? 'Rp' }})
                        </span>
                        <span class="text-xs text-gray-500 block">
                            Status Dokumen: <span class="font-semibold uppercase text-[#15803d]">{{ $receipt->status }}</span>
                        </span>
                    </div>

                    @if ($receipt->clean_notes)
                        <div class="sm:col-span-2 bg-gray-50 rounded-xl p-3 border border-gray-100">
                            <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Catatan Penerimaan Fisik:</span>
                            <p class="text-xs text-gray-700 whitespace-pre-line">{{ $receipt->clean_notes }}</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card 2: Rincian Barang Diterima --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Rincian Barang yang Diterima</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Kuantitas fisik tiba, barang diterima baik, dan barang yang ditolak.</p>
                    </div>
                    <span class="px-2.5 py-1 bg-gray-100 text-gray-700 text-xs font-bold rounded-lg font-mono">
                        {{ $receipt->purchaseReceiptItem->count() }} Baris Barang
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-[11px] uppercase font-bold text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-3 px-4">Nama Produk & SKU</th>
                                <th class="py-3 px-4">Gudang / Rak</th>
                                <th class="py-3 px-4 text-center">Qty PO</th>
                                <th class="py-3 px-4 text-center">Qty Tiba Fisik</th>
                                <th class="py-3 px-4 text-center text-[#15803d]">Diterima Baik</th>
                                <th class="py-3 px-4 text-center text-red-600">Ditolak</th>
                                <th class="py-3 px-4 text-center">Status Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($receipt->purchaseReceiptItem as $item)
                                @php
                                    $poItem = $item->purchaseOrderItem;
                                @endphp
                                <tr class="hover:bg-emerald-50/20 transition">
                                    {{-- Produk --}}
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-gray-900 leading-tight">
                                            {{ $item->product->name ?? 'Produk #' . $item->product_id }}
                                        </div>
                                        <div class="text-xs text-gray-400 font-mono mt-0.5">
                                            SKU: {{ $item->product->sku ?? '-' }} &bull; Satuan: {{ $item->product->unit->name ?? 'Pcs' }}
                                        </div>
                                    </td>

                                    {{-- Gudang & Rak --}}
                                    <td class="py-3 px-4 text-xs whitespace-nowrap">
                                        <span class="font-semibold text-gray-800 block">
                                            {{ $item->warehouse->name ?? 'Gudang Utama' }}
                                        </span>
                                        <span class="text-gray-400 block mt-0.5">
                                            Rak: {{ $item->rak->name ?? '(Default)' }}
                                        </span>
                                    </td>

                                    {{-- Qty PO --}}
                                    <td class="py-3 px-4 text-center font-mono font-medium text-gray-500 whitespace-nowrap">
                                        {{ number_format($poItem->quantity ?? 0) }}
                                    </td>

                                    {{-- Qty Tiba Fisik --}}
                                    <td class="py-3 px-4 text-center font-mono font-bold text-gray-900 whitespace-nowrap">
                                        {{ number_format($item->qty_received) }}
                                    </td>

                                    {{-- Qty Diterima Baik --}}
                                    <td class="py-3 px-4 text-center whitespace-nowrap font-mono font-bold text-[#15803d] bg-emerald-50/20">
                                        {{ number_format($item->qty_accepted) }}
                                    </td>

                                    {{-- Qty Ditolak --}}
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if ($item->qty_rejected > 0)
                                            <span class="font-mono font-bold text-red-600 block">
                                                {{ number_format($item->qty_rejected) }}
                                            </span>
                                            <span class="text-[10px] text-red-500 block mt-0.5 max-w-[120px] truncate" title="{{ $item->reason_rejected }}">
                                                {{ $item->reason_rejected }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">-</span>
                                        @endif
                                    </td>

                                    {{-- Status Item --}}
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        @if ($item->status === 'completed')
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-[#15803d]">
                                                Tercatat
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-800">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50/70 border-t border-gray-200 font-bold text-xs text-gray-800">
                            <tr>
                                <td colspan="3" class="py-3 px-4 text-right uppercase tracking-wider text-gray-500">
                                    Total Kuantitas:
                                </td>
                                <td class="py-3 px-4 text-center text-sm font-mono font-bold text-gray-900">
                                    {{ number_format($receipt->purchaseReceiptItem->sum('qty_received')) }}
                                </td>
                                <td class="py-3 px-4 text-center text-sm font-mono font-bold text-[#15803d] bg-emerald-50/30">
                                    {{ number_format($receipt->purchaseReceiptItem->sum('qty_accepted')) }}
                                </td>
                                <td class="py-3 px-4 text-center text-sm font-mono font-bold text-red-600">
                                    {{ number_format($receipt->purchaseReceiptItem->sum('qty_rejected')) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Card 3: Lampiran Foto Surat Jalan & Fisik --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Lampiran Foto Surat Jalan & Barang Fisik</h3>
                    <span class="text-xs text-gray-400 font-mono">
                        {{ $receipt->purchaseReceiptPhoto->count() }} Lampiran
                    </span>
                </div>

                @if ($receipt->purchaseReceiptPhoto->count() > 0)
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach ($receipt->purchaseReceiptPhoto as $photo)
                            @php
                                $photoUrl = asset('storage/' . $photo->photo_url);
                            @endphp
                            <div
                                @click="activePhotoUrl = '{{ $photoUrl }}'; previewPhotoModalOpen = true;"
                                class="group relative rounded-xl border border-gray-200 overflow-hidden bg-gray-50 aspect-video cursor-pointer hover:border-[#15803d] transition shadow-sm"
                            >
                                <img
                                    src="{{ $photoUrl }}"
                                    alt="Lampiran Penerimaan"
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-200"
                                    onerror="this.src='https://placehold.co/400x300?text=Foto+Lampiran'"
                                />
                                <div class="absolute inset-0 bg-gray-900/40 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-semibold">
                                    <span>🔍 Perbesar</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 text-center text-gray-400">
                        <svg class="w-8 h-8 mx-auto text-gray-300 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span class="text-xs font-semibold text-gray-500">Tidak ada lampiran foto surat jalan pada penerimaan ini.</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Right Column (Sidebar, 4 cols) --}}
        <div class="lg:col-span-4 space-y-6">
            {{-- Summary Card --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-4">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Ringkasan Penerimaan
                </h3>

                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center py-1">
                        <span class="text-gray-500">Total Baris Barang</span>
                        <span class="font-bold text-gray-900 font-mono">{{ $receipt->purchaseReceiptItem->count() }} item</span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-t border-gray-100">
                        <span class="text-gray-500">Total Qty Tiba Fisik</span>
                        <span class="font-bold text-gray-900 font-mono">{{ number_format($receipt->purchaseReceiptItem->sum('qty_received')) }}</span>
                    </div>

                    <div class="flex justify-between items-center py-1 border-t border-gray-100">
                        <span class="text-[#15803d] font-semibold">Total Qty Diterima Baik</span>
                        <span class="font-bold text-[#15803d] font-mono">{{ number_format($receipt->purchaseReceiptItem->sum('qty_accepted')) }}</span>
                    </div>

                    @if ($receipt->purchaseReceiptItem->sum('qty_rejected') > 0)
                        <div class="flex justify-between items-center py-1 border-t border-gray-100 bg-red-50/50 p-2 rounded-xl">
                            <span class="text-red-700 font-semibold text-xs">Total Qty Ditolak</span>
                            <span class="font-bold text-red-700 text-xs font-mono">{{ number_format($receipt->purchaseReceiptItem->sum('qty_rejected')) }}</span>
                        </div>
                    @endif

                    <div class="pt-3 border-t border-gray-200 flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-500 uppercase">Status Masuk Stok</span>
                        @if ($receipt->status === 'completed')
                            <span class="text-xs font-bold text-[#15803d] bg-emerald-100 px-2 py-0.5 rounded-md">
                                SUDAH MASUK
                            </span>
                        @else
                            <span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-md">
                                BELUM MASUK
                            </span>
                        @endif
                    </div>
                </div>

                @if ($receipt->status === 'draft')
                    <div class="pt-2">
                        <button
                            type="button"
                            @click="confirmModalOpen = true"
                            class="w-full py-2.5 px-4 bg-[#15803d] hover:bg-[#14532d] text-white text-sm font-bold rounded-xl shadow-sm transition active:scale-[0.98] text-center"
                        >
                            ✓ Konfirmasi Masuk Stok Sekarang
                        </button>
                    </div>
                @endif
            </div>

            {{-- Alur Terusan Pembelian --}}
            <div class="bg-gradient-to-br from-emerald-50/50 to-teal-50/50 rounded-2xl border border-emerald-100 p-5 space-y-3">
                <div class="flex items-center gap-2 text-emerald-900 font-bold text-sm">
                    <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                    <span>Langkah Selanjutnya</span>
                </div>
                <p class="text-xs text-emerald-800/80 leading-relaxed">
                    Setelah barang resmi diterima di gudang, dokumen ini dapat langsung ditagihkan melalui modul <strong>Faktur Pembelian (Purchase Invoice)</strong>.
                </p>
                <div class="pt-2 flex flex-col sm:flex-row items-start sm:items-center gap-2">
                    @if (in_array(strtolower((string)$receipt->status), ['partial', 'completed']))
                        <a
                            href="{{ route('pembelian.invoices.create', ['receipt_id' => $receipt->id, 'supplier_id' => $receipt->purchaseOrder?->supplier_id]) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                            Buat Faktur Pembelian
                        </a>
                    @endif
                    <a
                        href="{{ route('pembelian.po.show', $receipt->purchase_order_id) }}"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#15803d] hover:text-[#166534] hover:underline py-1.5"
                    >
                        <span>Halaman Purchase Order</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>

            {{-- Jurnal Persediaan Terkait (Jika Completed) --}}
            @if ($receipt->journalEntries->count() > 0)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                        Jurnal Persediaan Terkait
                    </h3>
                    <div class="space-y-2 text-xs">
                        @foreach ($receipt->journalEntries as $entry)
                            <div class="flex justify-between items-center py-1 border-b border-gray-50">
                                <div>
                                    <span class="font-semibold text-gray-800">{{ $entry->coa->name ?? 'COA #' . $entry->coa_id }}</span>
                                    <span class="text-gray-400 block font-mono text-[10px]">{{ $entry->coa->code ?? '' }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-gray-900 block font-mono">Rp {{ number_format($entry->amount ?? 0, 0, ',', '.') }}</span>
                                    <span class="text-[10px] font-semibold text-gray-500 uppercase">{{ $entry->type ?? 'DEBET' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Konfirmasi Masuk Stok --}}
    <div
        x-show="confirmModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="confirmModalOpen = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 transform transition-all"
        >
            <div class="flex items-center gap-3 text-[#15803d] mb-3">
                <div class="p-2.5 bg-emerald-50 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Konfirmasi Masuk Stok Gudang</h3>
                    <p class="text-xs text-gray-500">Penerimaan resmi fisik barang</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 mb-4">
                Apakah Anda yakin ingin mengonfirmasi penerimaan barang <span class="font-bold text-gray-900 font-mono">{{ $receipt->receipt_number }}</span>?
            </p>

            <div class="bg-emerald-50 p-3.5 rounded-xl text-xs text-[#15803d] space-y-1 mb-6 border border-emerald-100">
                <div class="font-bold">Dampak Konfirmasi:</div>
                <ul class="list-disc list-inside space-y-0.5 text-emerald-800">
                    <li>Kuantitas barang disetujui akan resmi menambah saldo stok fisik di gudang terkait.</li>
                    <li>Jurnal akuntansi persediaan akan otomatis dibukukan.</li>
                    <li>Status pemenuhan PO akan diperbarui secara berantai.</li>
                </ul>
            </div>

            <form action="{{ route('gudang.penerimaan.confirm', $receipt) }}" method="POST" class="flex items-center justify-end gap-2">
                @csrf
                <button
                    type="button"
                    @click="confirmModalOpen = false"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-bold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl shadow-sm transition"
                >
                    Ya, Konfirmasi & Masuk Stok
                </button>
            </form>
        </div>
    </div>

    {{-- Modal Hapus Draf --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="deleteModalOpen = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 transform transition-all"
        >
            <div class="flex items-center gap-3 text-red-600 mb-3">
                <div class="p-2.5 bg-red-50 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Hapus Draf Penerimaan</h3>
                    <p class="text-xs text-gray-500">Tindakan ini tidak dapat dibatalkan</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 mb-6">
                Apakah Anda yakin ingin menghapus draf penerimaan <span class="font-bold text-gray-900 font-mono">{{ $receipt->receipt_number }}</span>?
            </p>

            <form action="{{ route('gudang.penerimaan.destroy', $receipt) }}" method="POST" class="flex items-center justify-end gap-2">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="deleteModalOpen = false"
                    class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-sm transition"
                >
                    Hapus Dokumen
                </button>
            </form>
        </div>
    </div>

    {{-- Modal Preview Foto --}}
    <div
        x-show="previewPhotoModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-950/80 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="previewPhotoModalOpen = false"
            class="bg-white rounded-2xl max-w-3xl w-full p-4 shadow-2xl border border-gray-100 overflow-hidden"
        >
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                <h4 class="text-sm font-bold text-gray-900">Pratinjau Foto Lampiran</h4>
                <button
                    type="button"
                    @click="previewPhotoModalOpen = false"
                    class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition"
                    title="Tutup"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="flex items-center justify-center max-h-[70vh] overflow-hidden rounded-xl bg-gray-900">
                <img :src="activePhotoUrl" alt="Preview Foto" class="max-h-[70vh] w-auto object-contain">
            </div>
        </div>
    </div>
</div>
@endsection
