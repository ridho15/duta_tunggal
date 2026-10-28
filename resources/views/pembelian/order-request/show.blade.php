@extends('layouts.app')

@section('title', 'Permintaan Pembelian: ' . $orderRequest->request_number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{ showRejectModal: false }">
    {{-- Header & Action Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.or.index') }}" class="hover:text-[#15803d] transition">Permintaan Barang (OR)</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-900 font-bold">{{ $orderRequest->request_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $orderRequest->request_number }}</h1>
                @php
                    $statusConfig = match ($orderRequest->status) {
                        'draft' => ['bg' => 'bg-gray-100 text-gray-700 border-gray-300', 'label' => 'Draf'],
                        'request_approve' => ['bg' => 'bg-amber-100 text-amber-800 border-amber-300', 'label' => 'Menunggu Approval'],
                        'approved' => ['bg' => 'bg-emerald-100 text-emerald-800 border-emerald-300', 'label' => 'Disetujui'],
                        'partial' => ['bg' => 'bg-sky-100 text-sky-800 border-sky-300', 'label' => 'Diproses Sebagian'],
                        'complete' => ['bg' => 'bg-purple-100 text-purple-800 border-purple-300', 'label' => 'Selesai Terpenuhi'],
                        'rejected' => ['bg' => 'bg-rose-100 text-rose-800 border-rose-300', 'label' => 'Ditolak'],
                        'closed' => ['bg' => 'bg-slate-100 text-slate-700 border-slate-300', 'label' => 'Ditutup'],
                        default => ['bg' => 'bg-gray-100 text-gray-700 border-gray-300', 'label' => ucfirst($orderRequest->status)],
                    };
                @endphp
                <span class="px-2.5 py-1 rounded-full text-xs font-bold border {{ $statusConfig['bg'] }}">
                    {{ $statusConfig['label'] }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                Dibuat oleh <span class="font-semibold text-gray-700">{{ $orderRequest->createdBy?->name ?? 'User' }}</span> pada {{ $orderRequest->created_at ? $orderRequest->created_at->format('d/m/Y H:i') : '-' }}
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Tombol Terbitkan PO (Primary Action jika Approved / Partial) --}}
            @if (in_array($orderRequest->status, ['approved', 'partial'], true))
                <a
                    href="{{ route('pembelian.po.create', ['order_request_id' => $orderRequest->id]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Terbitkan Purchase Order (PO)
                </a>
            @endif

            {{-- Tombol Ajukan Approval --}}
            @if (in_array($orderRequest->status, ['draft', 'rejected'], true))
                <form action="{{ route('pembelian.or.request-approve', $orderRequest) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        Ajukan Persetujuan
                    </button>
                </form>
            @endif

            {{-- Tombol Otorisasi Persetujuan / Penolakan --}}
            @if (in_array($orderRequest->status, ['draft', 'request_approve'], true))
                <button
                    type="button"
                    @click="$dispatch('open-modal-approve-or', { action: '{{ route('pembelian.or.approve', $orderRequest) }}' })"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Setujui Permintaan
                </button>

                <button
                    type="button"
                    @click="showRejectModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-800 text-sm font-semibold rounded-lg transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Tolak
                </button>
            @endif

            {{-- Tombol Ubah --}}
            @if (in_array($orderRequest->status, ['draft', 'rejected'], true))
                <a
                    href="{{ route('pembelian.or.edit', $orderRequest) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Ubah
                </a>
            @endif

            {{-- Tombol Cetak PDF --}}
            <a
                href="{{ route('pembelian.or.pdf', $orderRequest) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            {{-- Tombol Hapus (Draf) --}}
            @if ($orderRequest->status === 'draft')
                <button
                    type="button"
                    @click="$dispatch('open-modal-delete-or', { action: '{{ route('pembelian.or.destroy', $orderRequest) }}' })"
                    class="p-2 border border-gray-300 bg-white hover:bg-rose-50 text-rose-600 rounded-lg shadow-sm transition"
                    title="Hapus Draf"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            @endif
        </div>
    </div>


    {{-- Status Stepper --}}
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        @php
            $isDraft = $orderRequest->status === 'draft';
            $isReqApprove = $orderRequest->status === 'request_approve';
            $isApproved = in_array($orderRequest->status, ['approved', 'partial', 'complete'], true);
            $isPO = $orderRequest->purchaseOrders->isNotEmpty() || in_array($orderRequest->status, ['partial', 'complete'], true);
            $isRejected = $orderRequest->status === 'rejected';
        @endphp

        @if ($isRejected)
            <div class="flex items-center gap-3 p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-900">
                <div class="w-8 h-8 rounded-full bg-rose-600 text-white flex items-center justify-center font-bold text-sm flex-shrink-0">
                    ✕
                </div>
                <div>
                    <div class="font-bold text-sm">Permintaan Pembelian Ini Telah Ditolak</div>
                    <div class="text-xs text-rose-700 whitespace-pre-line mt-0.5">{{ $orderRequest->note ?: 'Tidak ada catatan penolakan.' }}</div>
                </div>
            </div>
        @else
            <div class="flex items-center justify-between max-w-4xl mx-auto relative">
                {{-- Step 1 --}}
                <div class="flex flex-col items-center relative z-10">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs bg-[#15803d] text-white">
                        ✓
                    </div>
                    <span class="text-xs font-bold text-gray-900 mt-1.5">1. Draf</span>
                    <span class="text-[10px] text-gray-500">Diajukan</span>
                </div>

                <div class="flex-1 h-0.5 {{ $isReqApprove || $isApproved ? 'bg-[#15803d]' : 'bg-gray-200' }} mx-2"></div>

                {{-- Step 2 --}}
                <div class="flex flex-col items-center relative z-10">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs {{ $isReqApprove ? 'bg-amber-500 text-white animate-pulse' : ($isApproved ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-400 border border-gray-300') }}">
                        {{ $isApproved ? '✓' : '2' }}
                    </div>
                    <span class="text-xs font-bold {{ $isReqApprove ? 'text-amber-600' : ($isApproved ? 'text-gray-900' : 'text-gray-400') }} mt-1.5">
                        2. Verifikasi Atasan
                    </span>
                    <span class="text-[10px] text-gray-500">Otorisasi</span>
                </div>

                <div class="flex-1 h-0.5 {{ $isApproved ? 'bg-[#15803d]' : 'bg-gray-200' }} mx-2"></div>

                {{-- Step 3 --}}
                <div class="flex flex-col items-center relative z-10">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs {{ $isApproved ? 'bg-[#15803d] text-white' : 'bg-gray-100 text-gray-400 border border-gray-300' }}">
                        {{ $isApproved ? '✓' : '3' }}
                    </div>
                    <span class="text-xs font-bold {{ $isApproved ? 'text-gray-900' : 'text-gray-400' }} mt-1.5">
                        3. Disetujui
                    </span>
                    <span class="text-[10px] text-gray-500">Siap Proses</span>
                </div>

                <div class="flex-1 h-0.5 {{ $isPO ? 'bg-[#15803d]' : 'bg-gray-200' }} mx-2"></div>

                {{-- Step 4 --}}
                <div class="flex flex-col items-center relative z-10">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs {{ $isPO ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-400 border border-gray-300' }}">
                        {{ $isPO ? '✓' : '4' }}
                    </div>
                    <span class="text-xs font-bold {{ $isPO ? 'text-purple-700' : 'text-gray-400' }} mt-1.5">
                        4. Terbit PO
                    </span>
                    <span class="text-[10px] text-gray-500">Purchasing</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Detail Info Card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 mb-4">Informasi Dokumen</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-xs">
            <div>
                <p class="text-gray-400 uppercase font-semibold">Nomor Permintaan</p>
                <p class="text-sm font-bold text-gray-900 mt-1 font-mono">{{ $orderRequest->request_number }}</p>
            </div>

            <div>
                <p class="text-gray-400 uppercase font-semibold">Cabang Pemohon</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">{{ $cabang?->nama ?? '-' }}</p>
            </div>

            <div>
                <p class="text-gray-400 uppercase font-semibold">Tanggal Request</p>
                <p class="text-sm font-semibold text-gray-800 mt-1">
                    {{ $orderRequest->request_date ? $orderRequest->request_date->format('d F Y') : '-' }}
                </p>
            </div>

            <div>
                <p class="text-gray-400 uppercase font-semibold">Tanggal Dibutuhkan</p>
                <p class="text-sm font-semibold mt-1 {{ $orderRequest->required_date && $orderRequest->required_date->isPast() && !in_array($orderRequest->status, ['complete', 'closed']) ? 'text-rose-600 font-bold' : 'text-gray-800' }}">
                    {{ $orderRequest->required_date ? $orderRequest->required_date->format('d F Y') : '-' }}
                </p>
            </div>

            <div class="sm:col-span-2">
                <p class="text-gray-400 uppercase font-semibold">Keperluan / Divisi</p>
                <p class="text-sm font-medium text-gray-900 mt-1">{{ $orderRequest->purpose ?: 'Kebutuhan Operasional' }}</p>
            </div>

            <div class="sm:col-span-2">
                <p class="text-gray-400 uppercase font-semibold">Catatan Operasional</p>
                <p class="text-sm text-gray-700 mt-1 whitespace-pre-line">{{ $orderRequest->note ?: '-' }}</p>
            </div>
        </div>
    </div>

    {{-- Items Table Card --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50/50">
            <div>
                <h2 class="text-base font-bold text-gray-900">Rincian Barang yang Diminta</h2>
                <p class="text-xs text-gray-500">Daftar item produk, kuantitas kebutuhan, dan status pemenuhan ke PO.</p>
            </div>
            <div class="text-xs font-semibold text-gray-600">
                Total: <span class="text-gray-900 font-bold">{{ $totalItems }} Item</span> &bull; 
                Jumlah: <span class="text-gray-900 font-bold">{{ number_format($totalQuantity, 0) }} Unit</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4 w-10 text-center">#</th>
                        <th class="py-3 px-3">Produk & SKU</th>
                        <th class="py-3 px-3">Satuan</th>
                        <th class="py-3 px-3 text-right">Qty Diminta</th>
                        <th class="py-3 px-3 text-right">Qty Terpenuhi</th>
                        <th class="py-3 px-3 text-right">Sisa Kebutuhan</th>
                        <th class="py-3 px-3">Supplier Rekomendasi</th>
                        <th class="py-3 px-3 text-right">Estimasi Harga</th>
                        <th class="py-3 px-3 text-right">Subtotal</th>
                        <th class="py-3 px-3 text-center">Status Item</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($orderRequest->orderRequestItem as $index => $item)
                        @php
                            $qty = (float) $item->quantity;
                            $fulfilled = (float) ($item->fulfilled_quantity ?? 0);
                            $remaining = max(0, $qty - $fulfilled);
                            $pct = $qty > 0 ? min(100, round(($fulfilled / $qty) * 100)) : 0;
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="py-3 px-4 text-center font-mono text-gray-400">{{ $index + 1 }}</td>

                            <td class="py-3 px-3 font-semibold text-gray-900">
                                <div>{{ $item->product?->name ?? 'Produk Tidak Ditemukan' }}</div>
                                <div class="text-[10.5px] text-gray-400 font-normal">SKU: {{ $item->product?->sku ?? '-' }}</div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    @if ($item->cabang)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-700">
                                            Cabang: {{ $item->cabang->nama }}
                                        </span>
                                    @endif
                                    @if ($item->tipe_pajak && $item->tipe_pajak !== 'none')
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700">
                                            {{ ucfirst($item->tipe_pajak) }} ({{ (float)$item->tax }}%)
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500">
                                            Non PPN
                                        </span>
                                    @endif
                                    @if ((float)$item->discount > 0)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700">
                                            Disc {{ (float)$item->discount }}%
                                        </span>
                                    @endif
                                </div>
                                @if ($item->note)
                                    <div class="text-[10.5px] text-blue-600 font-normal italic mt-1">Catatan: {{ $item->note }}</div>
                                @endif
                            </td>

                            <td class="py-3 px-3 text-gray-600">{{ $item->product?->uom?->name ?? 'Pcs' }}</td>

                            <td class="py-3 px-3 text-right font-bold text-gray-900">{{ number_format($qty, 0) }}</td>

                            <td class="py-3 px-3 text-right font-semibold text-emerald-700">
                                {{ number_format($fulfilled, 0) }}
                                @if ($fulfilled > 0)
                                    <div class="w-16 bg-gray-200 rounded-full h-1.5 ml-auto mt-1">
                                        <div class="bg-emerald-600 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                @endif
                            </td>

                            <td class="py-3 px-3 text-right font-bold {{ $remaining > 0 ? 'text-amber-700' : 'text-gray-400' }}">
                                {{ number_format($remaining, 0) }}
                            </td>

                            <td class="py-3 px-3 text-gray-700">
                                {{ $item->supplier?->perusahaan ?? '-' }}
                            </td>

                            <td class="py-3 px-3 text-right text-gray-700 font-mono">
                                <div>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</div>
                                @if ((float)$item->original_price > 0 && (float)$item->original_price !== (float)$item->unit_price)
                                    <div class="text-[10px] text-gray-400 line-through">Acuan: Rp {{ number_format($item->original_price, 0, ',', '.') }}</div>
                                @endif
                            </td>

                            <td class="py-3 px-3 text-right font-bold text-gray-900 font-mono">
                                Rp {{ number_format($item->subtotal > 0 ? $item->subtotal : ($qty * $item->unit_price), 0, ',', '.') }}
                            </td>

                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if ($item->status === 'approved')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Disetujui</span>
                                @elseif ($item->status === 'rejected')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800" title="{{ $item->rejection_note }}">Ditolak</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600">Draf</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-gray-400">Tidak ada baris barang.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50/70 border-t border-gray-200 font-bold text-xs text-gray-900">
                        <td colspan="3" class="py-3 px-4 text-right uppercase">Total Kuantitas:</td>
                        <td class="py-3 px-3 text-right font-extrabold">{{ number_format($totalQuantity, 0) }}</td>
                        <td class="py-3 px-3 text-right text-emerald-700">{{ number_format($totalFulfilled, 0) }}</td>
                        <td class="py-3 px-3 text-right text-amber-700">{{ number_format(max(0, $totalQuantity - $totalFulfilled), 0) }}</td>
                        <td colspan="2" class="py-3 px-3 text-right uppercase">Total Estimasi Nilai:</td>
                        <td class="py-3 px-3 text-right font-extrabold text-[#15803d] font-mono">
                            Rp {{ number_format($estimatedSubtotal, 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Connected Purchase Orders (Jika ada PO yang diterbitkan) --}}
    @if ($orderRequest->purchaseOrders->isNotEmpty())
        <div class="bg-white rounded-xl border border-blue-200 shadow-sm p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-blue-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-xs">
                        PO
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Purchase Order (PO) Terkait</h2>
                        <p class="text-xs text-gray-500">Dokumen PO yang diterbitkan berdasarkan Order Request ini.</p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-blue-50/50 border-b border-blue-100 text-blue-900 font-bold uppercase">
                            <th class="py-2.5 px-3">Nomor PO</th>
                            <th class="py-2.5 px-3">Supplier</th>
                            <th class="py-2.5 px-3">Tanggal Order</th>
                            <th class="py-2.5 px-3">Status PO</th>
                            <th class="py-2.5 px-3 text-right">Total Nilai PO</th>
                            <th class="py-2.5 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($orderRequest->purchaseOrders as $po)
                            <tr class="hover:bg-blue-50/30">
                                <td class="py-2.5 px-3 font-bold font-mono text-blue-700">
                                    <a href="{{ route('pembelian.po.show', $po) }}" class="hover:underline">
                                        {{ $po->po_number }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-3 text-gray-800 font-medium">{{ $po->supplier?->perusahaan ?? '-' }}</td>
                                <td class="py-2.5 px-3 text-gray-600">{{ $po->order_date ? $po->order_date->format('d/m/Y') : '-' }}</td>
                                <td class="py-2.5 px-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                        {{ ucfirst($po->status) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-900">
                                    Rp {{ number_format($po->total_amount, 0, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <a
                                        href="{{ route('pembelian.po.show', $po) }}"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        Buka PO &rarr;
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Rejection Modal --}}
    <div
        x-show="showRejectModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div 
            @click.away="showRejectModal = false"
            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 space-y-4"
        >
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2 text-rose-600 font-bold text-base">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Tolak Permintaan Pembelian
                </div>
                <button @click="showRejectModal = false" class="text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            </div>

            <form action="{{ route('pembelian.or.reject', $orderRequest) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-700 mb-1">
                        Alasan Penolakan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="rejection_note"
                        rows="3"
                        required
                        placeholder="Tuliskan alasan penolakan secara jelas agar pemohon dapat merevisi kebutuhan..."
                        class="w-full py-2 px-3 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="showRejectModal = false"
                        class="px-4 py-2 border border-gray-300 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-lg transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        Konfirmasi Tolak
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Konfirmasi Setujui Permintaan Pembelian --}}
    <x-confirmation-modal
        name="approve-or"
        title="Setujui Permintaan Pembelian"
        type="success"
        confirm-text="Ya, Setujui Permintaan"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui permintaan pembelian <span class="font-bold text-gray-900 font-mono">{{ $orderRequest->request_number }}</span>?
        </p>
        <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg p-2.5 mt-2">
            Permintaan yang disetujui dapat segera diproses lebih lanjut menjadi Purchase Order (PO).
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Hapus Draf Permintaan Pembelian --}}
    <x-confirmation-modal
        name="delete-or"
        title="Hapus Draf Permintaan Pembelian"
        type="danger"
        confirm-text="Ya, Hapus Draf"
        cancel-text="Batal"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus draf permintaan pembelian <span class="font-bold text-gray-900 font-mono">{{ $orderRequest->request_number }}</span>?
        </p>
        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg p-2.5 mt-2">
            Tindakan ini permanen dan tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>
</div>
@endsection
