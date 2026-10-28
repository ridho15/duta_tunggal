@extends('layouts.app', ['title' => 'Faktur ' . $invoice->invoice_number])

@section('content')
@php
    $isOverdue = $invoice->status !== \App\Models\Invoice::STATUS_PAID && $invoice->due_date && $invoice->due_date < today();
@endphp

<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.invoices.index') }}" class="hover:text-gray-600 transition">Invoice Penjualan</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $invoice->invoice_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $invoice->invoice_number }}</h1>
                @if($invoice->status === \App\Models\Invoice::STATUS_PAID)
                    <span class="text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1">
                        LUNAS
                    </span>
                @elseif($isOverdue)
                    <span class="text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-1">
                        JATUH TEMPO · TERLAMBAT
                    </span>
                @else
                    <span class="text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                        MENUNGGU PEMBAYARAN
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                {{ $invoice->customer_name ?? 'Customer' }} · Tanggal Faktur {{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d F Y') : '-' }} · Jatuh Tempo {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') : '-' }}
            </p>
        </div>

        {{-- Top Action Buttons --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.invoices.pdf', $invoice->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                    <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                </svg>
                <span>Cetak Faktur (PDF)</span>
            </a>

            @if($remainingAmount > 0)
                <a
                    href="{{ route('penjualan.receipts.create', ['invoice_id' => $invoice->id]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" stroke-width="2"></path>
                    </svg>
                    <span>Terima Pembayaran</span>
                </a>
            @endif

            @if(!in_array(strtolower((string) $invoice->status), ['draft', 'cancelled', 'canceled']))
                <button
                    type="button"
                    onclick="document.getElementById('taxNumberModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>{{ filled($invoice->tax_invoice_number) ? 'Ubah No. Faktur Pajak' : 'Isi No. Faktur Pajak' }}</span>
                </button>

                <a
                    href="{{ route('penjualan.credit-notes.create', ['invoice_id' => $invoice->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 font-bold text-xs shadow-xs transition"
                    title="Buat Nota Kredit untuk koreksi atau pembatalan faktur ini"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 0 0-4-4H4m0 0l3-3m-3 3l3 3"></path>
                        <rect x="2" y="4" width="20" height="16" rx="2" stroke-width="2"></rect>
                    </svg>
                    <span>Buat Nota Kredit</span>
                </a>
            @endif
        </div>
    </div>

    {{-- Progress Stepper 6 Langkah --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-xs">
            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>1. SO Dibuat</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    {{ $primarySO?->so_number ?? '-' }}
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>2. Diapprove</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Disetujui
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>3. DO Gudang</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Teralokasi
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>4. Surat Jalan</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    {{ $suratJalans->pluck('sj_number')->implode(', ') }}
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>5. Terkirim</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Selesai
                </div>
            </div>

            <div class="space-y-1 border-l-2 md:border-l-0 pl-2 md:pl-0 border-[#15803d]">
                <div class="font-black text-gray-900 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span> <span>6. Faktur</span>
                </div>
                <div class="text-[11px] font-semibold text-emerald-700">
                    {{ $invoice->status_label ?? $invoice->status }}
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Tagihan & Ringkasan Keuangan --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- Kartu Info Pelanggan & Pajak --}}
        <div class="md:col-span-2 bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center justify-between">
                <span>Informasi Penagihan & Pajak</span>
                <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-700 rounded">
                    Tipe Pajak: {{ $invoice->tipe_pajak ?? 'None' }}
                </span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Ditagihkan Kepada:</span>
                    <span class="font-bold text-gray-800 text-sm block">{{ $invoice->customer_name }}</span>
                    <span class="text-gray-500 block">Telp: {{ $invoice->customer_phone ?? '-' }}</span>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Cabang Penerbit:</span>
                    <span class="font-bold text-gray-800 text-sm block">{{ $invoice->cabang->nama ?? 'Pusat' }}</span>
                    <span class="text-gray-500 block">{{ $invoice->cabang->alamat ?? '-' }}</span>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Tanggal Jatuh Tempo:</span>
                    <span class="font-semibold {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-800' }} block">
                        {{ $invoice->due_date ? \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') : '-' }}
                    </span>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Surat Jalan Terkait:</span>
                    <div class="flex flex-wrap gap-1">
                        @foreach($suratJalans as $sj)
                            <a href="{{ route('penjualan.sj.show', $sj->id) }}" class="text-[#15803d] font-bold hover:underline">
                                {{ $sj->sj_number }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">No. Faktur Pajak:</span>
                    <span class="font-bold {{ filled($invoice->tax_invoice_number) ? 'text-emerald-700 font-mono' : 'text-gray-400 italic' }} text-sm block">
                        {{ $invoice->tax_invoice_number ?? 'Belum diisi' }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Kartu Ringkasan Pembayaran --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 pb-2 border-b border-gray-100">
                Ringkasan Pembayaran
            </h2>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between text-gray-600">
                    <span>DPP (Dasar Pengenaan Pajak)</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format((float)$invoice->dpp, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between text-gray-600">
                    <span>PPN ({{ (int)$invoice->ppn_rate }}%)</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format((float)$invoice->tax, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between text-gray-600">
                    <span>Biaya Lain / Ongkos Kirim</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format((float)$invoice->other_fee_total, 0, ',', '.') }}</span>
                </div>

                <div class="pt-2 border-t border-gray-100 flex justify-between items-center text-sm">
                    <span class="font-bold text-gray-900">Total Tagihan</span>
                    <span class="font-black text-gray-900 text-base">
                        Rp {{ number_format((float)$invoice->total, 0, ',', '.') }}
                    </span>
                </div>

                <div class="pt-2 border-t border-dashed border-gray-200 flex justify-between items-center text-xs">
                    <span class="text-gray-500">Sudah Dibayar</span>
                    <span class="font-bold text-emerald-700">Rp {{ number_format((float)$paidAmount, 0, ',', '.') }}</span>
                </div>

                <div class="flex justify-between items-center text-xs">
                    <span class="font-bold text-gray-700">Sisa Tagihan</span>
                    <span class="font-black {{ $remainingAmount > 0 ? 'text-red-600 text-sm' : 'text-emerald-700' }}">
                        Rp {{ number_format((float)$remainingAmount, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Item Tagihan --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Barang yang Ditagihkan</h2>
                <p class="text-xs text-gray-400">Rincian produk dan harga satuan sesuai kesepakatan Pesanan Penjualan.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3 w-12 text-center">No</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Harga Satuan</th>
                        <th class="px-4 py-3 text-right">Diskon</th>
                        <th class="px-4 py-3 text-right">DPP</th>
                        <th class="px-4 py-3 text-right">PPN</th>
                        <th class="px-5 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs">
                    @forelse($invoice->invoiceItem as $index => $item)
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-5 py-3 text-center text-gray-400 font-medium">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <span class="font-bold text-gray-800 block">{{ $item->product->name ?? 'Produk' }}</span>
                                <span class="text-gray-400 text-[11px] font-mono">{{ $item->product->sku ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-gray-900">
                                {{ (int)$item->quantity }} {{ $item->product->uom->abbreviation ?? 'pcs' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700">
                                Rp {{ number_format((float)$item->price, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-500">
                                {{ (float)$item->discount > 0 ? (float)$item->discount . '%' : '-' }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-800 font-medium">
                                Rp {{ number_format((float)$item->subtotal, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right text-gray-600">
                                Rp {{ number_format((float)$item->tax_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-right font-black text-gray-900">
                                Rp {{ number_format((float)$item->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-gray-400 text-xs">
                                Tidak ada rincian baris barang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Isi No. Faktur Pajak --}}
    <div id="taxNumberModal" class="{{ $errors->has('tax_invoice_number') ? '' : 'hidden' }} fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <h3 class="text-base font-bold text-gray-900">No. Faktur Pajak</h3>
                <button type="button" onclick="document.getElementById('taxNumberModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"></path></svg>
                </button>
            </div>

            <form method="POST" action="{{ route('penjualan.invoices.updateTaxNumber', $invoice->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="tax_invoice_number" class="block text-xs font-semibold text-gray-700 mb-1">
                        No. Faktur Pajak
                    </label>
                    <input
                        type="text"
                        name="tax_invoice_number"
                        id="tax_invoice_number"
                        value="{{ old('tax_invoice_number', $invoice->tax_invoice_number) }}"
                        placeholder="010.000-26.12345678"
                        class="w-full px-3 py-2 border rounded-xl text-sm font-mono {{ $errors->has('tax_invoice_number') ? 'border-red-500 bg-red-50/50' : 'border-gray-300' }} focus:outline-none focus:border-[#15803d]"
                    >
                    @if($errors->has('tax_invoice_number'))
                        <p class="text-xs text-red-600 font-semibold mt-1">
                            {{ $errors->first('tax_invoice_number') }}
                        </p>
                    @endif
                    <p class="text-[11px] text-gray-400 mt-1">
                        16 digit. Kosongkan untuk menghapus nomor. Perubahan tercatat di riwayat aktivitas.
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        onclick="document.getElementById('taxNumberModal').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-200 text-gray-600 rounded-xl text-xs font-bold hover:bg-gray-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-[#15803d] hover:bg-[#14532d] text-white rounded-xl text-xs font-bold shadow-xs transition"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
