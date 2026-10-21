@extends('layouts.app')

@section('title', "Detail Nota Kredit {$creditNote->credit_note_number} – Duta Tunggal ERP")

@section('content')
<div class="space-y-6" x-data="{ showIssueModal: false, showTaxDocModal: false }">
    {{-- Header & Aksi --}}
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('penjualan.credit-notes.index') }}" class="hover:text-gray-700 transition">Nota Kredit</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-mono">{{ $creditNote->credit_note_number }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $creditNote->credit_note_number }}</h1>
                @php
                    $typeBadge = match ($creditNote->type) {
                        \App\Models\CreditNote::TYPE_CANCELLATION => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'label' => 'Pembatalan Invoice'],
                        \App\Models\CreditNote::TYPE_RETURN => ['bg' => 'bg-amber-50', 'text' => 'text-amber-800', 'border' => 'border-amber-200', 'label' => 'Retur Barang'],
                        \App\Models\CreditNote::TYPE_CORRECTION => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'label' => 'Koreksi Sebagian'],
                        default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'label' => $creditNote->type],
                    };

                    $statusBadge = match ($creditNote->status) {
                        \App\Models\CreditNote::STATUS_ISSUED => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'label' => 'Terbit (Issued)'],
                        default => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'label' => 'Draf'],
                    };
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $typeBadge['bg'] }} {{ $typeBadge['text'] }} {{ $typeBadge['border'] }}">
                    {{ $typeBadge['label'] }}
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }} {{ $statusBadge['border'] }}">
                    {{ $statusBadge['label'] }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Dibuat tanggal {{ $creditNote->credit_date ? \Carbon\Carbon::parse($creditNote->credit_date)->translatedFormat('d F Y') : '-' }}
                oleh <span class="font-semibold text-gray-700">{{ $creditNote->createdBy->name ?? 'Sistem' }}</span>
                @if ($creditNote->isIssued())
                    • Diterbitkan oleh <span class="font-semibold text-emerald-800">{{ $creditNote->issuedBy->name ?? 'Admin' }}</span> pada {{ $creditNote->issued_at ? \Carbon\Carbon::parse($creditNote->issued_at)->translatedFormat('d M Y H:i') : '-' }}
                @endif
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex flex-wrap items-center gap-2">
            <a
                href="{{ route('penjualan.credit-notes.index') }}"
                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
            >
                Kembali
            </a>

            {{-- Tombol Cetak PDF --}}
            <a
                href="{{ route('penjualan.credit-notes.pdf', $creditNote->id) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </a>

            {{-- Aksi Draf --}}
            @if ($creditNote->isDraft())
                {{-- Tombol Terbitkan --}}
                <button
                    type="button"
                    @click="showIssueModal = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Terbitkan Nota Kredit
                </button>

                {{-- Hapus Draf --}}
                <form
                    method="POST"
                    action="{{ route('penjualan.credit-notes.destroy', $creditNote->id) }}"
                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus draf Nota Kredit ini? Tindakan ini tidak dapat dibatalkan.');"
                    class="inline-block"
                >
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition"
                        title="Hapus Draf"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </form>
            @else
                {{-- Tombol Ubah Faktur Pajak Retur --}}
                <button
                    type="button"
                    @click="showTaxDocModal = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-50 border border-gray-300 hover:bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg transition"
                >
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    No. Faktur Pajak Retur
                </button>
            @endif
        </div>
    </div>

    {{-- Feedback Alerts --}}
    @if (session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    {{-- Visual 2-Step Workflow Stepper --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-center justify-between max-w-xl mx-auto">
            {{-- Step 1: Draf --}}
            <div class="flex flex-col items-center flex-1">
                <div class="w-9 h-9 rounded-full bg-[#15803d] text-white flex items-center justify-center font-bold text-sm shadow-sm ring-4 ring-emerald-50">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <span class="text-xs font-bold text-gray-900 mt-2">1. Draf Pengajuan</span>
                <span class="text-[11px] text-gray-400">Pengisian Qty & Nilai</span>
            </div>

            {{-- Divider Bar --}}
            <div class="h-1 flex-1 mx-4 {{ $creditNote->isIssued() ? 'bg-[#15803d]' : 'bg-gray-200' }} rounded"></div>

            {{-- Step 2: Terbit (Issued) --}}
            <div class="flex flex-col items-center flex-1">
                <div class="w-9 h-9 rounded-full {{ $creditNote->isIssued() ? 'bg-[#15803d] text-white ring-4 ring-emerald-50' : 'bg-gray-200 text-gray-500' }} flex items-center justify-center font-bold text-sm shadow-sm">
                    @if ($creditNote->isIssued())
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    @else
                        2
                    @endif
                </div>
                <span class="text-xs font-bold {{ $creditNote->isIssued() ? 'text-gray-900' : 'text-gray-500' }} mt-2">2. Terbit & Terjurnal</span>
                <span class="text-[11px] text-gray-400">Final (Posting Jurnal & AR)</span>
            </div>
        </div>
    </div>

    {{-- 3 Kartu Parameter Finansial --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Total Nilai Nota Kredit --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Nilai Nota Kredit</span>
            <div class="mt-2 text-2xl font-black text-gray-900">
                Rp {{ number_format((float) $creditNote->total, 0, ',', '.') }}
            </div>
            <div class="mt-2 text-xs text-gray-500 space-y-0.5">
                <div class="flex justify-between">
                    <span>DPP (Subtotal):</span>
                    <span class="font-semibold text-gray-700">Rp {{ number_format((float) $creditNote->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>PPN Reversal:</span>
                    <span class="font-semibold text-gray-700">Rp {{ number_format((float) $creditNote->tax_amount, 0, ',', '.') }}</span>
                </div>
                @if ((float) $creditNote->other_fee_amount > 0)
                    <div class="flex justify-between">
                        <span>Biaya Pengiriman:</span>
                        <span class="font-semibold text-gray-700">Rp {{ number_format((float) $creditNote->other_fee_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Alokasi Potong Piutang --}}
        <div class="bg-white rounded-xl border border-emerald-200 p-5 shadow-sm bg-gradient-to-br from-emerald-50/30 to-white">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Alokasi Potong Piutang</span>
                <span class="text-xs px-2 py-0.5 rounded font-bold {{ $creditNote->isIssued() ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">
                    {{ $creditNote->isIssued() ? 'Tereksekusi' : 'Estimasi' }}
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-emerald-900">
                @if ($creditNote->isIssued())
                    Rp {{ number_format((float) $creditNote->applied_to_ar, 0, ',', '.') }}
                @else
                    Rp {{ number_format((float) ($impact['apply_to_ar'] ?? 0), 0, ',', '.') }}
                @endif
            </div>
            <p class="mt-2 text-xs text-emerald-700">
                Mengurangi saldo piutang faktur <strong class="font-mono">{{ $creditNote->invoice->invoice_number }}</strong> secara langsung tanpa menghasilkan piutang minus.
            </p>
        </div>

        {{-- Alokasi Deposit Customer --}}
        <div class="bg-white rounded-xl border border-blue-200 p-5 shadow-sm bg-gradient-to-br from-blue-50/30 to-white">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-blue-800">Alokasi Deposit Customer</span>
                <span class="text-xs px-2 py-0.5 rounded font-bold {{ $creditNote->isIssued() ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600' }}">
                    {{ $creditNote->isIssued() ? 'Tereksekusi' : 'Estimasi' }}
                </span>
            </div>
            <div class="mt-2 text-2xl font-black text-blue-900">
                @if ($creditNote->isIssued())
                    Rp {{ number_format((float) $creditNote->applied_to_deposit, 0, ',', '.') }}
                @else
                    Rp {{ number_format((float) ($impact['to_deposit'] ?? 0), 0, ',', '.') }}
                @endif
            </div>
            <p class="mt-2 text-xs text-blue-700">
                Kelebihan kredit atas faktur yang telah dibayar masuk sebagai saldo simpanan (store credit) pelanggan.
            </p>
        </div>
    </div>

    {{-- Informasi Dokumen & Referensi --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Metadata Dokumen --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center justify-between">
                <span>Rincian Dokumen & Referensi</span>
                <span class="text-xs font-normal text-gray-400">ID: {{ $creditNote->id }}</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-gray-400 block font-semibold mb-0.5">Pelanggan (Customer):</span>
                    <span class="text-gray-900 font-bold text-sm block">{{ $creditNote->customer->name ?? '-' }}</span>
                    <span class="text-gray-500 font-mono">{{ $creditNote->customer->code ?? '' }}</span>
                </div>
                <div>
                    <span class="text-gray-400 block font-semibold mb-0.5">Faktur Penjualan (Invoice Rujukan):</span>
                    <a
                        href="{{ route('penjualan.invoices.show', $creditNote->invoice_id) }}"
                        class="text-[#15803d] font-bold text-sm font-mono hover:underline inline-flex items-center gap-1"
                    >
                        {{ $creditNote->invoice->invoice_number ?? '-' }}
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                    <span class="text-gray-500 block">Total Faktur: Rp {{ number_format((float) ($creditNote->invoice->total ?? 0), 0, ',', '.') }}</span>
                </div>
                @if ($creditNote->customerReturn)
                    <div>
                        <span class="text-gray-400 block font-semibold mb-0.5">Dokumen Retur Rujukan:</span>
                        <a
                            href="{{ route('penjualan.returns.show', $creditNote->customer_return_id) }}"
                            class="text-amber-800 font-bold text-sm font-mono hover:underline inline-flex items-center gap-1"
                        >
                            {{ $creditNote->customerReturn->return_number }}
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                @endif
                <div>
                    <span class="text-gray-400 block font-semibold mb-0.5">Nomor Nota Retur Pajak:</span>
                    <span class="font-mono text-gray-800 font-semibold">
                        {{ $creditNote->tax_document_number ?: 'Belum diisi (Metadata Pajak)' }}
                    </span>
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100">
                <span class="text-gray-400 block font-semibold text-xs mb-1">Alasan Pembuatan:</span>
                <p class="text-sm text-gray-800 bg-gray-50 p-3 rounded-lg border border-gray-200">
                    {{ $creditNote->reason }}
                </p>
            </div>
        </div>

        {{-- Kolom Kanan: Status Tagihan & Jejak Audit --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
            <h2 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3">
                Kondisi Tagihan & Audit
            </h2>

            <div class="space-y-3 text-xs">
                @if ($ar)
                    <div class="bg-gray-50 p-3 rounded-lg space-y-1.5 border border-gray-200">
                        <div class="font-bold text-gray-700 uppercase tracking-wider text-[11px]">Posisi Piutang Faktur</div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Nilai Tagihan Faktur:</span>
                            <span class="font-bold text-gray-800">Rp {{ number_format((float) $ar->total, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sudah Dibayar Pelanggan:</span>
                            <span class="font-bold text-emerald-700">Rp {{ number_format((float) $ar->paid, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Sisa Piutang Berjalan:</span>
                            <span class="font-bold text-amber-700">Rp {{ number_format((float) $ar->remaining, 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endif

                <div class="space-y-2 pt-2 text-gray-600">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Dibuat Oleh:</span>
                        <span class="font-semibold text-gray-800">{{ $creditNote->createdBy->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Waktu Dibuat:</span>
                        <span class="font-medium">{{ $creditNote->created_at ? $creditNote->created_at->translatedFormat('d M Y H:i') : '-' }}</span>
                    </div>
                    @if ($creditNote->isIssued())
                        <div class="flex justify-between">
                            <span class="text-gray-400">Diterbitkan Oleh:</span>
                            <span class="font-semibold text-emerald-800">{{ $creditNote->issuedBy->name ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Waktu Terbit:</span>
                            <span class="font-medium text-emerald-700">{{ $creditNote->issued_at ? \Carbon\Carbon::parse($creditNote->issued_at)->translatedFormat('d M Y H:i') : '-' }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Item --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 bg-gray-50/70 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                Rincian Item Nota Kredit
            </h2>
            <span class="text-xs text-gray-500 font-medium">
                {{ $creditNote->items->count() }} baris item
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3 px-4 text-center w-12">No</th>
                        <th class="py-3 px-4">Deskripsi / Produk</th>
                        <th class="py-3 px-4 text-right">Qty</th>
                        <th class="py-3 px-4 text-right">Harga Satuan</th>
                        <th class="py-3 px-4 text-right">DPP</th>
                        <th class="py-3 px-4 text-right">PPN</th>
                        <th class="py-3 px-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($creditNote->items as $idx => $line)
                        <tr class="hover:bg-gray-50/70 transition">
                            <td class="py-3 px-4 text-center font-bold text-gray-400">{{ $idx + 1 }}</td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">
                                    {{ $line->product->name ?? ($line->description ?: 'Barang #' . $line->id) }}
                                </div>
                                @if ($line->product?->code)
                                    <div class="text-[11px] text-gray-400 font-mono">{{ $line->product->code }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">
                                {{ number_format((float) $line->quantity, 2, ',', '.') }}
                                <span class="text-xs font-normal text-gray-500">{{ $line->product->unit ?? '' }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-gray-700">
                                Rp {{ number_format((float) $line->unit_price, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-gray-800">
                                Rp {{ number_format((float) $line->subtotal, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-gray-600">
                                Rp {{ number_format((float) $line->tax_amount, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-extrabold text-gray-900">
                                Rp {{ number_format((float) $line->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-gray-400">Tidak ada baris item</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-900 text-sm">
                    <tr>
                        <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-xs text-gray-600">Total Nilai Kredit:</td>
                        <td class="py-3 px-4 text-right font-bold">Rp {{ number_format((float) $creditNote->subtotal, 0, ',', '.') }}</td>
                        <td class="py-3 px-4 text-right font-bold">Rp {{ number_format((float) $creditNote->tax_amount, 0, ',', '.') }}</td>
                        <td class="py-3 px-4 text-right font-black text-base text-[#15803d]">Rp {{ number_format((float) $creditNote->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Tab Jurnal Umum Akuntansi (Saat Dokumen Terbit) --}}
    @if ($creditNote->isIssued())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="p-4 bg-gray-50/70 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="p-1.5 bg-emerald-100 text-emerald-800 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                        Posting Jurnal Umum Akuntansi (General Ledger)
                    </h2>
                </div>
                @php
                    $totalDebit = (float) $creditNote->journalEntries->sum('debit');
                    $totalCredit = (float) $creditNote->journalEntries->sum('credit');
                    $isBalanced = abs($totalDebit - $totalCredit) < 0.05 && $totalDebit > 0;
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $isBalanced ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                    {{ $isBalanced ? '✓ Jurnal Seimbang (Balanced)' : 'Belum Seimbang' }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="py-3 px-4 w-32">Tanggal</th>
                            <th class="py-3 px-4 w-36">Kode Akun</th>
                            <th class="py-3 px-4">Nama Akun (COA)</th>
                            <th class="py-3 px-4">Keterangan</th>
                            <th class="py-3 px-4 text-right w-36">Debit</th>
                            <th class="py-3 px-4 text-right w-36">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($creditNote->journalEntries as $journal)
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-4 font-mono text-xs text-gray-500">
                                    {{ $journal->date ? \Carbon\Carbon::parse($journal->date)->translatedFormat('d/m/Y') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 font-mono font-bold text-gray-800 text-xs">
                                    {{ $journal->coa->code ?? ($journal->coa_code ?? '-') }}
                                </td>
                                <td class="py-2.5 px-4 font-medium text-gray-900">
                                    {{ $journal->coa->name ?? ($journal->coa_name ?? '-') }}
                                </td>
                                <td class="py-2.5 px-4 text-xs text-gray-500">
                                    {{ $journal->description }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-gray-900">
                                    {{ (float) $journal->debit > 0 ? 'Rp ' . number_format((float) $journal->debit, 0, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold text-gray-900">
                                    {{ (float) $journal->credit > 0 ? 'Rp ' . number_format((float) $journal->credit, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-400">Tidak ada catatan jurnal ditemukan</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-900 text-sm">
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-xs text-gray-600">Total Debit & Kredit:</td>
                            <td class="py-3 px-4 text-right font-mono font-black text-[#15803d]">Rp {{ number_format($totalDebit, 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono font-black text-[#15803d]">Rp {{ number_format($totalCredit, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- MODAL 1: Konfirmasi Penerbitan (Issue Modal) --}}
    <div
        x-show="showIssueModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-5 shadow-2xl" @click.outside="showIssueModal = false">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                    <span class="p-1.5 bg-emerald-100 text-emerald-800 rounded-lg">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    Terbitkan Nota Kredit
                </h3>
                <button type="button" @click="showIssueModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Ringkasan Dampak Finansial --}}
            <div class="bg-emerald-50/50 border border-emerald-200 rounded-xl p-4 text-xs space-y-2">
                <div class="font-bold text-emerald-900 uppercase tracking-wider text-[11px]">
                    Dampak Penerbitan Dokumen
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Total Nilai Nota Kredit:</span>
                    <span class="font-extrabold text-gray-900">Rp {{ number_format((float) $creditNote->total, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-600">
                    <span>Mengurangi Piutang Invoice:</span>
                    <span class="font-bold text-emerald-800">
                        Rp {{ number_format((float) ($impact['apply_to_ar'] ?? $creditNote->applied_to_ar), 0, ',', '.') }}
                    </span>
                </div>
                <div class="flex justify-between text-blue-800 font-semibold">
                    <span>Masuk ke Deposit Customer:</span>
                    <span class="font-bold">
                        Rp {{ number_format((float) ($impact['to_deposit'] ?? $creditNote->applied_to_deposit), 0, ',', '.') }}
                    </span>
                </div>
                <div class="text-[11px] text-gray-500 pt-1 border-t border-emerald-100">
                    * Dokumen yang diterbitkan bersifat <strong>FINAL</strong> dan langsung memposting jurnal akuntansi seimbang.
                </div>
            </div>

            <form method="POST" action="{{ route('penjualan.credit-notes.issue', $creditNote->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Nota Retur Pajak (Opsional)
                    </label>
                    <input
                        type="text"
                        name="tax_document_number"
                        value="{{ $creditNote->tax_document_number }}"
                        placeholder="Contoh: NR-2026-0001"
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <span class="text-[11px] text-gray-400 mt-0.5 block">Format bebas; dapat diisi atau diperbarui setelah terbit.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Alasan Override Persetujuan (Jika Menyetujui Dokumen Buatan Sendiri)
                    </label>
                    <textarea
                        name="override_reason"
                        rows="2"
                        placeholder="Hanya diperlukan bila menyetujui dokumen yang dibuat sendiri..."
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button
                        type="button"
                        @click="showIssueModal = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        Ya, Terbitkan Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 2: Ubah Nomor Faktur Pajak Retur --}}
    <div
        x-show="showTaxDocModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-2xl" @click.outside="showTaxDocModal = false">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-base font-bold text-gray-900">Perbarui Nomor Nota Retur Pajak</h3>
                <button type="button" @click="showTaxDocModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('penjualan.credit-notes.updateTaxDocument', $creditNote->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Nomor Nota Retur Pajak
                    </label>
                    <input
                        type="text"
                        name="tax_document_number"
                        value="{{ $creditNote->tax_document_number }}"
                        placeholder="Contoh: NR-2026-0001"
                        class="w-full text-sm border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                    <span class="text-[11px] text-gray-400 mt-1 block">Perubahan metadata ini tidak mengubah posting jurnal atau nilai piutang.</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button
                        type="button"
                        @click="showTaxDocModal = false"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-sm font-semibold rounded-lg shadow-sm transition"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
