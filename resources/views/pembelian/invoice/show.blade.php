@extends('layouts.app')

@section('title', 'Faktur Pembelian ' . $invoice->invoice_number . ' – Duta Tunggal ERP')

@section('content')
@php
    $status = strtolower((string)$invoice->status);
    $isDraft = ($status === 'draft');
    $isCancelled = ($status === 'cancelled');
    $isPaid = ($status === 'paid');
    $isPartiallyPaid = ($status === 'partially_paid');
    $isSent = ($status === 'sent');
    $remaining = $invoice->getRemainingAmount();
@endphp

<div
    class="max-w-6xl mx-auto space-y-6"
    x-data="{
        activeTab: 'items',
        cancelModalOpen: false,
        postModalOpen: false,
        deleteModalOpen: false,
    }"
>
    {{-- Header Breadcrumb & Judul --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.invoices.index') }}" class="text-gray-500 hover:text-gray-900 transition">Faktur Pembelian</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $invoice->invoice_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $invoice->invoice_number }}</h1>
                @php
                    $badgeClass = match ($status) {
                        'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
                        'sent' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'partially_paid' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'paid' => 'bg-emerald-50 text-[#15803d] border-emerald-200',
                        'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                    {{ $invoice->status_label }}
                </span>
                @if ($invoice->isOverdue())
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700 border border-red-200">
                        Lewat Jatuh Tempo
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Dibuat pada {{ $invoice->created_at ? $invoice->created_at->format('d/m/Y H:i') : '-' }} &bull; Cabang: {{ $invoice->cabang?->name ?? 'Pusat' }}
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Cetak PDF --}}
            <a
                href="{{ route('pembelian.invoices.pdf', $invoice) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            {{-- Tombol Khusus Draft --}}
            @if ($isDraft)
                <a
                    href="{{ route('pembelian.invoices.edit', $invoice) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-amber-200 text-amber-700 bg-amber-50 hover:bg-amber-100 text-xs font-semibold rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Draft
                </a>

                <button
                    type="button"
                    @click="postModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Posting ke Hutang Usaha (AP)
                </button>

                <button
                    type="button"
                    @click="deleteModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-red-200 text-red-600 bg-red-50 hover:bg-red-100 text-xs font-semibold rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Hapus Draft
                </button>
            @endif

            {{-- Tombol Bayar Tagihan Ini --}}
            @if (!$isDraft && !$isCancelled && $remaining > 0)
                <a
                    href="{{ route('pembelian.payments.create', ['supplier_id' => $invoice->supplier_id ?: $invoice->purchaseOrder?->supplier_id, 'invoice_id' => $invoice->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="3" y="6" width="18" height="14" rx="2" stroke-width="2"></rect>
                        <path d="M3 10h18M16 15h2" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    + Bayar Tagihan Ini
                </a>
            @endif

            {{-- Tombol Batalkan Faktur --}}
            @if (!$isDraft && !$isCancelled && $remaining > 0)
                <button
                    type="button"
                    @click="cancelModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-rose-200 text-rose-700 bg-rose-50 hover:bg-rose-100 text-xs font-semibold rounded-lg transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Batalkan Faktur
                </button>
            @endif

            {{-- Shortcut Retur Pembelian --}}
            @if (!$isCancelled && $invoice->supplier_id)
                <a
                    href="{{ route('pembelian.returns.create', ['supplier_id' => $invoice->supplier_id]) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-2 border border-amber-200 text-amber-800 bg-amber-50 hover:bg-amber-100 text-xs font-semibold rounded-lg transition"
                    title="Buat Retur Pembelian ke Vendor ini"
                >
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 14 4 9l5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M4 9h10a6 6 0 0 1 0 12h-3" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                    + Buat Retur
                </a>
            @endif
        </div>
    </div>

    {{-- Banner Pembatalan jika Dibatalkan --}}
    @if ($isCancelled)
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div class="flex-1 text-xs text-rose-800 space-y-1">
                <div class="font-bold text-sm">Faktur Pembelian Ini Telah Dibatalkan</div>
                <p>
                    Dibatalkan pada {{ $invoice->cancelled_at ? $invoice->cancelled_at->format('d/m/Y H:i') : '-' }}
                    oleh <strong>{{ $invoice->cancelledBy?->name ?? 'User' }}</strong>.
                </p>
                <p class="font-medium">Alasan: <em>"{{ $invoice->cancel_reason ?? '-' }}"</em></p>
                <p class="text-[11px] text-rose-600">Seluruh entri jurnal akuntansi telah dibalikkan dan penerimaan barang (GRN) terkait telah dibebaskan untuk ditagihkan kembali.</p>
            </div>
        </div>
    @else
        {{-- Visual Stepper 4 Tahap --}}
        <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between max-w-4xl mx-auto relative">
                {{-- Connector Line --}}
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-1 bg-gray-200 -z-0"></div>
                <div
                    class="absolute left-0 top-1/2 -translate-y-1/2 h-1 bg-[#15803d] -z-0 transition-all duration-500"
                    style="width: {{ $isPaid ? '100%' : ($isPartiallyPaid ? '66%' : ($isSent ? '33%' : '0%')) }}"
                ></div>

                {{-- Step 1: Draft --}}
                <div class="flex flex-col items-center gap-2 bg-white px-3 relative z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $isDraft ? 'bg-[#15803d] text-white ring-4 ring-emerald-100' : 'bg-[#15803d] text-white' }}">
                        ✓
                    </div>
                    <span class="text-xs font-bold text-gray-900">1. Draft</span>
                </div>

                {{-- Step 2: Menunggu Pembayaran (Posted) --}}
                <div class="flex flex-col items-center gap-2 bg-white px-3 relative z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $isSent ? 'bg-[#15803d] text-white ring-4 ring-emerald-100' : ($isPartiallyPaid || $isPaid ? 'bg-[#15803d] text-white' : 'bg-gray-200 text-gray-500') }}">
                        {{ $isPartiallyPaid || $isPaid ? '✓' : '2' }}
                    </div>
                    <span class="text-xs font-bold {{ $isSent || $isPartiallyPaid || $isPaid ? 'text-gray-900' : 'text-gray-400' }}">
                        2. Menunggu Pembayaran
                    </span>
                </div>

                {{-- Step 3: Dibayar Sebagian --}}
                <div class="flex flex-col items-center gap-2 bg-white px-3 relative z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $isPartiallyPaid ? 'bg-amber-500 text-white ring-4 ring-amber-100' : ($isPaid ? 'bg-[#15803d] text-white' : 'bg-gray-200 text-gray-500') }}">
                        {{ $isPaid ? '✓' : '3' }}
                    </div>
                    <span class="text-xs font-bold {{ $isPartiallyPaid || $isPaid ? 'text-gray-900' : 'text-gray-400' }}">
                        3. Dibayar Sebagian
                    </span>
                </div>

                {{-- Step 4: Lunas (Paid) --}}
                <div class="flex flex-col items-center gap-2 bg-white px-3 relative z-10">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold {{ $isPaid ? 'bg-[#15803d] text-white ring-4 ring-emerald-100' : 'bg-gray-200 text-gray-500' }}">
                        {{ $isPaid ? '✓' : '4' }}
                    </div>
                    <span class="text-xs font-bold {{ $isPaid ? 'text-[#15803d]' : 'text-gray-400' }}">
                        4. Lunas (Paid)
                    </span>
                </div>
            </div>
        </div>
    @endif

    {{-- Kartu Panel 3-Way Matching --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
            Pemeriksaan 3-Way Matching (PO &bull; GRN &bull; INVOICE)
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Dokumen 1: Purchase Order --}}
            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/50 space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">1. Pesanan Pembelian (PO)</div>
                @forelse ($relatedPos as $po)
                    <div class="flex items-center justify-between">
                        <a href="{{ route('pembelian.po.show', $po) }}" class="text-xs font-bold text-[#15803d] hover:underline">
                            {{ $po->po_number }}
                        </a>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                            {{ ucfirst($po->status) }}
                        </span>
                    </div>
                    <div class="text-[11px] text-gray-500">
                        Total PO: Rp {{ number_format((float)$po->total_amount, 0, ',', '.') }}
                    </div>
                @empty
                    <div class="text-xs text-gray-400">Tidak ada PO terikat langsung</div>
                @endforelse
            </div>

            {{-- Dokumen 2: Goods Receipt --}}
            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/50 space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">2. Penerimaan Barang (GRN)</div>
                @forelse ($relatedReceipts as $rcp)
                    <div class="flex items-center justify-between">
                        <a href="{{ route('gudang.penerimaan.show', $rcp) }}" class="text-xs font-bold text-[#15803d] hover:underline">
                            {{ $rcp->receipt_number }}
                        </a>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                            {{ ucfirst($rcp->status) }}
                        </span>
                    </div>
                    <div class="text-[11px] text-gray-500">
                        Gudang: {{ $rcp->purchaseReceiptItem->first()?->warehouse?->name ?? $rcp->purchaseOrder?->warehouse?->name ?? 'Gudang Pusat' }}
                    </div>
                @empty
                    <div class="text-xs text-gray-400">Tidak ada GRN terikat</div>
                @endforelse
            </div>

            {{-- Dokumen 3: Tagihan Vendor --}}
            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50/50 space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500">3. Faktur Vendor / Supplier</div>
                <div class="text-xs font-bold text-gray-900">
                    {{ $invoice->supplier_invoice_number ?? '-' }}
                </div>
                @if ($invoice->tax_invoice_number)
                    <div class="text-[11px] text-gray-500">
                        No. Faktur Pajak: <span class="font-mono">{{ $invoice->tax_invoice_number }}</span>
                    </div>
                @endif
                <div class="text-[11px] text-gray-500">
                    Jatuh Tempo: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Konten Utama --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Navigasi Tab --}}
        <div class="flex items-center gap-2 p-2 border-b border-gray-100 bg-gray-50/50">
            <button
                type="button"
                @click="activeTab = 'items'"
                class="px-4 py-2 text-xs font-bold rounded-lg transition"
                :class="activeTab === 'items' ? 'bg-white text-[#15803d] shadow-sm border border-gray-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
            >
                Rincian Barang ({{ $invoice->invoiceItem->count() }})
            </button>
            <button
                type="button"
                @click="activeTab = 'ap'"
                class="px-4 py-2 text-xs font-bold rounded-lg transition"
                :class="activeTab === 'ap' ? 'bg-white text-[#15803d] shadow-sm border border-gray-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
            >
                Hutang Dagang (AP)
            </button>
            <button
                type="button"
                @click="activeTab = 'journals'"
                class="px-4 py-2 text-xs font-bold rounded-lg transition"
                :class="activeTab === 'journals' ? 'bg-white text-[#15803d] shadow-sm border border-gray-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
            >
                Jurnal Akuntansi (GL)
            </button>
        </div>

        {{-- Tab 1: Rincian Barang --}}
        <div x-show="activeTab === 'items'" class="p-6 space-y-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-2.5 px-3">Produk / Barang</th>
                            <th class="py-2.5 px-3 text-right">Qty</th>
                            <th class="py-2.5 px-3">Satuan</th>
                            <th class="py-2.5 px-3 text-right">Harga PO (Rp)</th>
                            <th class="py-2.5 px-3 text-right">Harga Vendor (Rp)</th>
                            <th class="py-2.5 px-3 text-right">PPN %</th>
                            <th class="py-2.5 px-3 text-right">Subtotal (Rp)</th>
                            <th class="py-2.5 px-3 text-right">Selisih (PPV)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @foreach ($invoice->invoiceItem as $item)
                            @php
                                $poPrice = (float)($item->po_price ?? $item->price);
                                $vendorPrice = (float)$item->price;
                                $qty = (float)$item->quantity;
                                $diff = ($vendorPrice - $poPrice) * $qty;
                            @endphp
                            <tr class="hover:bg-gray-50/75 transition">
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-gray-900">{{ $item->product?->name ?? 'Produk' }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">{{ $item->product?->sku ?? '-' }}</div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ number_format($qty, 0, ',', '.') }}</td>
                                <td class="py-2.5 px-3 text-gray-500">{{ $item->product?->uom?->name ?? 'Pcs' }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-500 font-mono">Rp {{ number_format($poPrice, 0, ',', '.') }}</td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900 font-mono">Rp {{ number_format($vendorPrice, 0, ',', '.') }}</td>
                                <td class="py-2.5 px-3 text-right text-gray-600">{{ (float)($item->tax_rate ?? 0) }}%</td>
                                <td class="py-2.5 px-3 text-right font-bold text-gray-900 font-mono">Rp {{ number_format((float)$item->total, 0, ',', '.') }}</td>
                                <td class="py-2.5 px-3 text-right font-semibold font-mono {{ $diff > 0 ? 'text-amber-700' : ($diff < 0 ? 'text-emerald-700' : 'text-gray-400') }}">
                                    Rp {{ number_format($diff, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Ringkasan Angka Finansial --}}
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                <div class="text-xs text-gray-500">
                    <div>Pemasok: <strong class="text-gray-900">{{ $invoice->supplier?->perusahaan ?? $invoice->supplier_name }}</strong></div>
                    <div>Termin Pembayaran: <strong class="text-gray-900">{{ $invoice->due_date ? $invoice->invoice_date?->diffInDays($invoice->due_date) : 30 }} hari</strong></div>
                </div>
                <div class="w-full sm:w-72 space-y-1.5 text-xs">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal DPP:</span>
                        <span class="font-bold text-gray-900 font-mono">Rp {{ number_format((float)$invoice->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>PPN Masukan ({{ (float)($invoice->ppn_rate ?? 0) }}%):</span>
                        <span class="font-bold text-gray-900 font-mono">Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}</span>
                    </div>
                    @if ($invoice->other_fee_total > 0)
                        <div class="flex justify-between text-gray-600">
                            <span>Ongkir / Biaya Lain:</span>
                            <span class="font-bold text-gray-900 font-mono">Rp {{ number_format((float)$invoice->other_fee_total, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if (abs((float)$invoice->price_variance_amount) > 0.01)
                        <div class="flex justify-between text-gray-600">
                            <span>Selisih Harga (PPV):</span>
                            <span class="font-bold font-mono {{ (float)$invoice->price_variance_amount > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                                Rp {{ number_format((float)$invoice->price_variance_amount, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm font-bold text-gray-900 border-t border-gray-200 pt-2">
                        <span>Total Tagihan:</span>
                        <span class="text-base text-[#15803d] font-mono">Rp {{ number_format((float)$invoice->total, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab 2: Informasi Hutang Dagang (AP) --}}
        <div x-show="activeTab === 'ap'" class="p-6 space-y-4">
            @if ($invoice->accountPayable && $invoice->accountPayable->exists)
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                        <span class="text-xs text-gray-500 uppercase tracking-wider block font-semibold">Total Tagihan</span>
                        <span class="text-lg font-bold text-gray-900 font-mono mt-1 block">
                            Rp {{ number_format((float)$invoice->accountPayable->total, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl">
                        <span class="text-xs text-emerald-700 uppercase tracking-wider block font-semibold">Total Terbayar</span>
                        <span class="text-lg font-bold text-[#15803d] font-mono mt-1 block">
                            Rp {{ number_format((float)($invoice->accountPayable->total - $invoice->accountPayable->remaining), 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="p-4 {{ $invoice->accountPayable->remaining > 0 ? 'bg-amber-50 border-amber-200' : 'bg-gray-50 border-gray-200' }} border rounded-xl">
                        <span class="text-xs {{ $invoice->accountPayable->remaining > 0 ? 'text-amber-700' : 'text-gray-500' }} uppercase tracking-wider block font-semibold">Sisa Saldo Hutang</span>
                        <span class="text-lg font-bold {{ $invoice->accountPayable->remaining > 0 ? 'text-amber-800' : 'text-gray-900' }} font-mono mt-1 block">
                            Rp {{ number_format((float)$invoice->accountPayable->remaining, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl">
                        <span class="text-xs text-gray-500 uppercase tracking-wider block font-semibold">Status Hutang</span>
                        <span class="inline-flex mt-1 items-center px-2 py-0.5 rounded text-xs font-bold {{ $invoice->accountPayable->remaining <= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $invoice->accountPayable->remaining <= 0 ? 'Lunas' : ($invoice->accountPayable->remaining < $invoice->accountPayable->total ? 'Dibayar Sebagian' : 'Belum Dibayar') }}
                        </span>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        Pembayaran tagihan ini dicatat melalui modul <strong>Pembelian &gt; Pembayaran Hutang (Vendor Payment)</strong>.
                    </div>
                    @if ($invoice->accountPayable->remaining > 0)
                        <a
                            href="{{ route('pembelian.payments.create', ['supplier_id' => $invoice->supplier_id ?: $invoice->purchaseOrder?->supplier_id, 'invoice_id' => $invoice->id]) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition self-start sm:self-auto"
                        >
                            + Catat Pembayaran Tagihan Ini
                        </a>
                    @endif
                </div>
            @else
                <div class="py-8 text-center text-gray-400 text-xs">
                    Faktur ini masih berstatus <strong>Draft</strong> sehingga belum didaftarkan pada Buku Pembantu Hutang Dagang (AP). Klik tombol <strong>"Posting ke Hutang Usaha"</strong> di atas untuk mempostingnya.
                </div>
            @endif
        </div>

        {{-- Tab 3: Jurnal Akuntansi (GL) --}}
        <div x-show="activeTab === 'journals'" class="p-6 space-y-4">
            @php
                $journals = $invoice->journalEntries;
            @endphp
            @if ($journals->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                                <th class="py-2.5 px-3">No. Jurnal</th>
                                <th class="py-2.5 px-3">Tanggal</th>
                                <th class="py-2.5 px-3">Kode &amp; Nama Akun (COA)</th>
                                <th class="py-2.5 px-3 text-right">Debit (Rp)</th>
                                <th class="py-2.5 px-3 text-right">Kredit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            @foreach ($journals as $journal)
                                <tr class="hover:bg-gray-50/75 transition">
                                    <td class="py-2 px-3 font-mono font-semibold text-gray-900">{{ $journal->reference ?? ('JRN-' . $journal->id) }}</td>
                                    <td class="py-2 px-3 text-gray-600">{{ $journal->date ? \Carbon\Carbon::parse($journal->date)->format('d/m/Y') : '-' }}</td>
                                    <td class="py-2 px-3">
                                        <span class="font-mono font-bold text-gray-900">{{ $journal->coa?->code }}</span> - {{ $journal->coa?->name }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold {{ (float)$journal->debit > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ (float)$journal->debit > 0 ? 'Rp ' . number_format((float)$journal->debit, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2 px-3 text-right font-mono font-semibold {{ (float)$journal->credit > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ (float)$journal->credit > 0 ? 'Rp ' . number_format((float)$journal->credit, 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-gray-400 text-xs">
                    Belum ada entri jurnal akuntansi yang terbentuk. Jurnal otomatis diposting saat faktur diterbitkan.
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Konfirmasi Posting --}}
    <x-confirmation-modal
        id="post-invoice-modal"
        name="postModalOpen"
        title="Posting Faktur Pembelian ke AP &amp; Buku Besar"
        variant="success"
        confirm-text="Ya, Posting Sekarang"
        cancel-text="Batal"
        action="{{ route('pembelian.invoices.post', $invoice) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui dan memposting Faktur Pembelian <strong class="text-gray-900">{{ $invoice->invoice_number }}</strong>?
        </p>
        <p class="text-xs text-gray-500 mt-2">
            Sistem akan secara otomatis membentuk entri jurnal Buku Besar (Debit Persediaan/PPN Masukan, Kredit Hutang Dagang) serta mencatat tagihan ini pada Buku Pembantu Hutang Dagang (AP).
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Hapus Draft --}}
    <x-confirmation-modal
        id="delete-invoice-modal"
        name="deleteModalOpen"
        title="Hapus Faktur Pembelian Draft"
        variant="danger"
        confirm-text="Ya, Hapus Faktur"
        cancel-text="Batal"
        action="{{ route('pembelian.invoices.destroy', $invoice) }}"
        method="POST"
    >
        @method('DELETE')
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus Faktur Pembelian draft <strong class="text-gray-900">{{ $invoice->invoice_number }}</strong>?
        </p>
        <p class="text-xs text-red-600 mt-2">
            Penerimaan barang (GRN) yang tertagih pada faktur ini akan kembali dapat ditagihkan. Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Batalkan Faktur --}}
    <div
        x-show="cancelModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        x-cloak
        style="display: none;"
    >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="cancelModalOpen = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

            <div class="inline-block p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100">
                <form action="{{ route('pembelian.invoices.cancel', $invoice) }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 bg-rose-100 text-rose-600 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Batalkan Faktur Pembelian</h3>
                            <p class="text-xs text-gray-500">Jurnal akuntansi akan dibalikkan dan hutang dihapus.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Alasan Pembatalan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            name="cancel_reason"
                            required
                            rows="3"
                            placeholder="Contoh: Salah jumlah penagihan, supplier menerbitkan faktur revisi..."
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="cancelModalOpen = false"
                            class="px-4 py-2 border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition"
                        >
                            Tutup
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                        >
                            Konfirmasi Pembatalan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
