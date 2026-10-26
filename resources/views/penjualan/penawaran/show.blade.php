@extends('layouts.app')

@section('title', 'Penawaran ' . $quotation->quotation_number . ' – ERP Duta Tunggal')

@section('content')
<div class="max-w-[1100px] mx-auto space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 pb-4 border-b border-gray-200">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('penjualan.quotations.index') }}" class="hover:text-emerald-700">Penawaran Harga</a>
                <span class="text-gray-300">/</span>
                <span class="text-emerald-700 font-bold">{{ $quotation->quotation_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">{{ $quotation->quotation_number }}</h1>
                @if($quotation->revision_no > 0)
                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-bold rounded border border-blue-200">
                        Revisi {{ $quotation->revision_no }}
                    </span>
                @endif
                @php
                    $isExpired = $quotation->isExpired();
                    $statusBadgeStyle = match($quotation->status) {
                        'draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                        'request_approve' => 'bg-amber-100 text-amber-800 border-amber-300',
                        'approve' => $isExpired ? 'bg-red-100 text-red-800 border-red-300' : 'bg-emerald-100 text-emerald-800 border-emerald-300',
                        'reject' => 'bg-rose-100 text-rose-800 border-rose-300',
                        'expired' => 'bg-red-100 text-red-800 border-red-300',
                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                    };
                    $displayStatusText = ($quotation->status === 'approve' && $isExpired) ? 'Kedaluwarsa' : $quotation->statusLabel();
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusBadgeStyle }}">
                    {{ $displayStatusText }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Diterbitkan pada {{ \Carbon\Carbon::parse($quotation->date)->translatedFormat('d F Y') }}
                @if($quotation->valid_until)
                    &bull; Berlaku sampai: <span class="{{ $isExpired ? 'text-red-600 font-bold' : 'text-gray-700 font-medium' }}">{{ \Carbon\Carbon::parse($quotation->valid_until)->translatedFormat('d F Y') }}</span>
                @endif
            </p>
        </div>

        {{-- Top Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('penjualan.quotations.index') }}"
               class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                &larr; Daftar
            </a>

            {{-- Cetak PDF --}}
            <a href="{{ route('penjualan.quotations.pdf', $quotation) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition shadow-2xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak PDF</span>
            </a>

            {{-- Aksi Edit untuk Draft / Reject --}}
            @if($canEdit)
                <a href="{{ route('penjualan.quotations.edit', $quotation) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Edit Penawaran</span>
                </a>
            @endif

            {{-- Ajukan Persetujuan (dari Draft) --}}
            @if($canRequestApprove)
                <button type="button"
                        @click="$dispatch('open-modal-request-approve-quotation', { action: '{{ route('penjualan.quotations.requestApprove', $quotation) }}' })"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-amber-600 rounded-lg hover:bg-amber-700 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Ajukan Persetujuan</span>
                </button>
            @endif

            {{-- Tombol Setujui / Tolak (untuk yang menunggu persetujuan) --}}
            @if($quotation->status === 'request_approve')
                @if($canReject)
                    <button type="button" onclick="openRejectModal()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Tolak</span>
                    </button>
                @endif
                @if($canApprove)
                    <button type="button"
                            @click="$dispatch('open-modal-approve-quotation', { action: '{{ route('penjualan.quotations.approve', $quotation) }}' })"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Setujui (Approve)</span>
                    </button>
                @endif
            @endif

            {{-- Buat Revisi (untuk Disetujui / Kedaluwarsa) --}}
            @if($canRevise)
                <button type="button"
                        @click="$dispatch('open-modal-revision-quotation', { action: '{{ route('penjualan.quotations.revision', $quotation) }}' })"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Buat Revisi</span>
                </button>
            @endif

            {{-- Konversi ke Sales Order (jika approved & usable) --}}
            @if($canConvertToSo)
                <a href="{{ route('penjualan.quotations.convertToSaleOrder', $quotation) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-black text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    <span>Buat Sales Order (SO)</span>
                </a>
            @endif

            {{-- Hapus (Draft / Reject) --}}
            @if($canDelete)
                <button type="button"
                        @click="$dispatch('open-modal-delete-quotation', { action: '{{ route('penjualan.quotations.destroy', $quotation) }}' })"
                        class="p-2 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition" title="Hapus Draft">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
            @endif
        </div>
    </div>


    {{-- Status Banner Info (if expired, rejected, superseded, or has active SO) --}}
    @if($isExpired)
        <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800 flex items-start gap-3 shadow-2xs">
            <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <div class="font-bold">Masa Berlaku Penawaran Telah Habis</div>
                <div class="mt-0.5 text-red-700">
                    Penawaran ini berlaku sampai tanggal {{ \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') }} dan tidak dapat dijadikan Sales Order secara langsung. Silakan klik tombol <strong>"Buat Revisi"</strong> untuk memperbarui masa berlaku dan estimasi harga.
                </div>
            </div>
        </div>
    @elseif($quotation->superseded_at)
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-start gap-3 shadow-2xs">
            <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <div class="font-bold">Dokumen Telah Digantikan Versi Baru</div>
                <div class="mt-0.5 text-amber-700">
                    Penawaran ini telah digantikan oleh revisi yang lebih baru pada {{ \Carbon\Carbon::parse($quotation->superseded_at)->format('d/m/Y H:i') }}.
                </div>
            </div>
        </div>
    @elseif($quotation->saleOrders->isNotEmpty())
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-start gap-3 shadow-2xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <div class="font-bold">Telah Dikonversi Menjadi Sales Order</div>
                <div class="mt-0.5 text-emerald-700 flex flex-wrap gap-2 items-center">
                    <span>Penawaran ini telah digunakan untuk Sales Order:</span>
                    @foreach($quotation->saleOrders as $so)
                        <a href="{{ route('penjualan.so.show', $so) }}" class="inline-flex items-center gap-1 font-bold text-emerald-900 underline hover:text-emerald-950">
                            {{ $so->so_number }} ({{ $so->status }})
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Detail Panels Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Panel 1: Pelanggan & Alamat --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-2xs space-y-2.5">
            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider pb-2 border-b border-gray-100 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Informasi Pelanggan</span>
            </div>
            <div>
                <div class="text-sm font-bold text-gray-900">{{ $quotation->customer->name ?? '-' }}</div>
                @if($quotation->customer->perusahaan)
                    <div class="text-xs text-gray-600 font-medium">{{ $quotation->customer->perusahaan }}</div>
                @endif
                <div class="text-xs text-gray-500 mt-1">{{ $quotation->customer->address ?? 'Alamat tidak terdaftar' }}</div>
                <div class="text-xs text-gray-500 mt-0.5">Telp: {{ $quotation->customer->phone ?? $quotation->customer->telephone ?? '-' }}</div>
            </div>
            @if($quotation->shipped_to)
                <div class="pt-2 border-t border-gray-100">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Tujuan Pengiriman:</div>
                    <div class="text-xs text-gray-700 mt-0.5">{{ $quotation->shipped_to }}</div>
                </div>
            @endif
        </div>

        {{-- Panel 2: Parameter Finansial & Cabang --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-2xs space-y-2.5">
            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider pb-2 border-b border-gray-100 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Syarat & Cabang</span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500">Cabang Penjual:</span>
                    <span class="font-bold text-gray-800">{{ $quotation->cabang->nama ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Mata Uang:</span>
                    <span class="font-bold text-gray-800">{{ $quotation->currency->code ?? 'IDR' }} (Kurs: {{ number_format($quotation->exchange_rate ?? 1, 2) }})</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Tempo Pembayaran:</span>
                    <span class="font-bold text-gray-800">{{ $quotation->tempo_pembayaran ? $quotation->tempo_pembayaran . ' Hari' : 'Cash / Tunai' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Status Pembayaran:</span>
                    <span class="font-medium text-gray-700 capitalize">{{ $quotation->status_payment ?? 'Belum Dibayar' }}</span>
                </div>
            </div>
        </div>

        {{-- Panel 3: Otorisasi & Riwayat Dokumen --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-2xs space-y-2.5">
            <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider pb-2 border-b border-gray-100 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                <span>Otorisasi & Alur Kerja</span>
            </div>
            <div class="space-y-1.5 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500">Dibuat Oleh:</span>
                    <span class="font-semibold text-gray-800">{{ $quotation->createdBy->name ?? 'Sales' }}</span>
                </div>
                @if($quotation->request_approve_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Diajukan:</span>
                        <span class="font-medium text-gray-700">{{ \Carbon\Carbon::parse($quotation->request_approve_at)->format('d/m/Y H:i') }}</span>
                    </div>
                @endif
                @if($quotation->approve_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Disetujui Oleh:</span>
                        <span class="font-bold text-emerald-700">{{ $quotation->approveBy->name ?? 'Manajer' }} ({{ \Carbon\Carbon::parse($quotation->approve_at)->format('d/m/Y') }})</span>
                    </div>
                @endif
                @if($quotation->reject_at)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Ditolak Oleh:</span>
                        <span class="font-bold text-red-700">{{ $quotation->rejectBy->name ?? 'Manajer' }} ({{ \Carbon\Carbon::parse($quotation->reject_at)->format('d/m/Y') }})</span>
                    </div>
                @endif
                @if($quotation->revision_of_id && $quotation->revisionOf)
                    <div class="pt-1.5 border-t border-gray-100 flex justify-between">
                        <span class="text-gray-500">Revisi Dari:</span>
                        <a href="{{ route('penjualan.quotations.show', $quotation->revisionOf) }}" class="font-bold text-blue-600 hover:underline">
                            {{ $quotation->revisionOf->quotation_number }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Item Penawaran --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
        <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
            <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <span>Daftar Barang & Estimasi Harga</span>
                <span class="px-2 py-0.2 bg-emerald-100 text-emerald-800 text-[10px] font-black rounded-full">
                    {{ $quotation->quotationItem->count() }} Item
                </span>
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/80 text-gray-500 font-semibold border-b border-gray-200 text-[11px]">
                        <th class="py-2.5 px-4 w-12 text-center">#</th>
                        <th class="py-2.5 px-3">Produk / SKU</th>
                        <th class="py-2.5 px-3 text-right">Kuantitas</th>
                        <th class="py-2.5 px-2 text-center">Satuan</th>
                        <th class="py-2.5 px-3 text-right">Harga Satuan</th>
                        <th class="py-2.5 px-3 text-right">Diskon (%)</th>
                        <th class="py-2.5 px-3 text-center">Pajak</th>
                        <th class="py-2.5 px-4 text-right">Total Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $calcSubtotalGross = 0;
                        $calcTotalDisc = 0;
                        $calcTotalDpp = 0;
                        $calcTotalTax = 0;
                    @endphp
                    @foreach($quotation->quotationItem as $index => $item)
                        @php
                            $qty = (float) $item->quantity;
                            $price = (float) $item->unit_price;
                            $discPct = (float) ($item->discount ?? 0);
                            $gross = $qty * $price;
                            $discNominal = $gross * ($discPct / 100);
                            $net = $gross - $discNominal;

                            $taxType = strtolower(trim((string)($item->tax_type ?? 'none')));
                            if (in_array($taxType, ['ppn excluded', 'eksklusif'])) {
                                $taxNominal = $net * 0.11;
                                $rowTotal = $net + $taxNominal;
                                $calcTotalDpp += $net;
                                $calcTotalTax += $taxNominal;
                            } elseif (in_array($taxType, ['ppn included', 'inklusif', 'inclusive'])) {
                                $dpp = $net / 1.11;
                                $taxNominal = $net - $dpp;
                                $rowTotal = $net;
                                $calcTotalDpp += $dpp;
                                $calcTotalTax += $taxNominal;
                            } else {
                                $taxNominal = 0;
                                $rowTotal = $net;
                                $calcTotalDpp += $net;
                            }

                            $calcSubtotalGross += $gross;
                            $calcTotalDisc += $discNominal;
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="py-3 px-4 text-center text-gray-400 font-mono">{{ $index + 1 }}</td>
                            <td class="py-3 px-3">
                                <div class="font-bold text-gray-900">{{ $item->product->name ?? 'Produk #' . $item->product_id }}</div>
                                <div class="text-[11px] text-gray-500 font-mono">SKU: {{ $item->product->sku ?? '-' }}</div>
                                @if($item->notes)
                                    <div class="text-[10px] text-gray-400 italic mt-0.5">{{ $item->notes }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right font-black text-gray-900">
                                {{ number_format($item->quantity, 2) }}
                            </td>
                            <td class="py-3 px-2 text-center text-gray-500 font-medium">
                                {{ $item->product->uom->abbreviation ?? 'PCS' }}
                            </td>
                            <td class="py-3 px-3 text-right text-gray-800 font-medium">
                                Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-3 text-right text-gray-600">
                                @if($item->discount > 0)
                                    <span class="text-red-600 font-bold">{{ $item->discount }}%</span>
                                    <div class="text-[10px] text-gray-400">(-Rp {{ number_format($discNominal, 0, ',', '.') }})</div>
                                @else
                                    <span class="text-gray-400">0%</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2 py-0.5 text-[10px] rounded-full font-medium {{ in_array($taxType, ['ppn excluded', 'ppn included', 'inklusif', 'eksklusif']) ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-500' }}">
                                    {{ $item->tax_type ?? 'None' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-black text-gray-900">
                                Rp {{ number_format($item->total_price, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Bottom Financial Summary & Notes --}}
        <div class="p-5 border-t border-gray-100 bg-gray-50/50 flex flex-col md:flex-row justify-between gap-6">
            <div class="flex-1 space-y-2">
                <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Catatan Tambahan:</div>
                <div class="p-3 bg-white border border-gray-200 rounded-lg text-xs text-gray-700 whitespace-pre-line leading-relaxed min-h-[60px]">
                    {{ $quotation->notes ?: 'Tidak ada catatan khusus pada penawaran ini.' }}
                </div>
            </div>

            <div class="w-full md:w-80 bg-white border border-gray-200 rounded-xl p-4 space-y-2 text-xs shadow-2xs">
                <div class="flex justify-between text-gray-600 font-medium">
                    <span>Subtotal Kotor:</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format($calcSubtotalGross, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-600 font-medium">
                    <span>Total Diskon:</span>
                    <span class="text-red-600 font-semibold">- Rp {{ number_format($calcTotalDisc, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-600 font-medium">
                    <span>Dasar Pengenaan Pajak (DPP):</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format($calcTotalDpp, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-600 font-medium">
                    <span>Total PPN (11%):</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format($calcTotalTax, 0, ',', '.') }}</span>
                </div>
                <div class="pt-2 border-t border-gray-200 flex justify-between text-sm font-black text-gray-900">
                    <span>Nilai Penawaran:</span>
                    <span class="text-emerald-700 text-base">Rp {{ number_format($quotation->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Reject Reason Modal --}}
<div id="rejectModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white border border-gray-200 rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">Tolak Penawaran Harga</h3>
            <button type="button" onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>

        <form method="POST" action="{{ route('penjualan.quotations.reject', $quotation) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">
                    Alasan Penolakan <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required
                          placeholder="Jelaskan alasan penolakan atau instruksi revisi harga untuk tim sales..."
                          class="w-full py-2 px-3 text-xs bg-white border border-gray-300 rounded-lg focus:ring-1 focus:ring-rose-600 focus:border-rose-600"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeRejectModal()"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit"
                        class="px-4 py-2 text-xs font-bold text-white bg-rose-600 rounded-lg hover:bg-rose-700 shadow-2xs">
                    Konfirmasi Tolak
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
</script>

    {{-- Modal Ajukan Persetujuan --}}
    <x-confirmation-modal
        name="request-approve-quotation"
        title="Ajukan Persetujuan Penawaran"
        type="warning"
        confirm-text="Ya, Ajukan Persetujuan"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Ajukan dokumen penawaran harga <span class="font-bold text-gray-900 font-mono">{{ $quotation->quotation_number }}</span> untuk diverifikasi dan disetujui oleh manajer?
        </p>
    </x-confirmation-modal>

    {{-- Modal Setujui Penawaran --}}
    <x-confirmation-modal
        name="approve-quotation"
        title="Setujui Penawaran Harga"
        type="success"
        confirm-text="Ya, Setujui Penawaran"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui penawaran harga <span class="font-bold text-gray-900 font-mono">{{ $quotation->quotation_number }}</span>? Dokumen penawaran resmi dapat diterbitkan ke pelanggan atau dikonversi menjadi Sales Order (SO).
        </p>
    </x-confirmation-modal>

    {{-- Modal Buat Revisi --}}
    <x-confirmation-modal
        name="revision-quotation"
        title="Terbitkan Revisi Penawaran"
        type="info"
        confirm-text="Ya, Buat Dokumen Revisi"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Terbitkan dokumen revisi baru dari penawaran <span class="font-bold text-gray-900 font-mono">{{ $quotation->quotation_number }}</span>?
        </p>
        <p class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg p-2.5 mt-2">
            Versi revisi baru akan bernomor otomatis <strong>-R{{ $quotation->revision_no + 1 }}</strong> dan dokumen ini akan ditandai superseded.
        </p>
    </x-confirmation-modal>

    {{-- Modal Hapus Draft --}}
    <x-confirmation-modal
        name="delete-quotation"
        title="Hapus Penawaran Harga"
        type="danger"
        confirm-text="Ya, Hapus Draft"
        cancel-text="Batal"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus draft penawaran harga <span class="font-bold text-gray-900 font-mono">{{ $quotation->quotation_number }}</span> secara permanen?
        </p>
        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg p-2.5 mt-2">
            Tindakan ini tidak dapat dipulihkan kembali.
        </p>
    </x-confirmation-modal>
@endsection
