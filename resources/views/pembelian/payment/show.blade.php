@extends('layouts.app')

@section('title', 'Pembayaran ' . $payment->payment_number . ' – Duta Tunggal ERP')

@section('content')
@php
    $st = strtolower((string)$payment->status);
    $isDraft = ($st === 'draft');
    $isCancelled = ($st === 'cancelled');
    $isPaid = ($st === 'paid');
    $isPartial = ($st === 'partial');
@endphp

<div class="space-y-6" x-data="{ 
    cancelModalOpen: false,
    deleteModalOpen: false
}">
    {{-- Header & Aksi --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.payments.index') }}" class="hover:text-gray-700">Pembayaran Hutang</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $payment->payment_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $payment->payment_number }}</h1>
                @if ($isPaid)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        Lunas
                    </span>
                @elseif ($isPartial)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                        Sebagian
                    </span>
                @elseif ($isDraft)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-700">
                        Draft
                    </span>
                @elseif ($isCancelled)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                        Dibatalkan
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                Dibayarkan kepada <strong class="text-gray-800">{{ $payment->supplier?->perusahaan ?? 'Vendor' }}</strong> pada {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->translatedFormat('d F Y') : '-' }}.
            </p>
        </div>

        {{-- Action Buttons Toolbar --}}
        <div class="flex flex-wrap items-center gap-2">
            {{-- Tombol Cetak PDF Voucher --}}
            <a
                href="{{ route('pembelian.payments.pdf', $payment->id) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Voucher (PDF)
            </a>

            {{-- Aksi Khusus Draft --}}
            @if ($isDraft)
                <a
                    href="{{ route('pembelian.payments.edit', $payment->id) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-xs transition"
                >
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit Draft
                </a>

                <form action="{{ route('pembelian.payments.post', $payment->id) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        onclick="return confirm('Posting pembayaran ini ke Buku Besar dan potong saldo hutang vendor?')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Posting ke Buku Besar
                    </button>
                </form>

                <button
                    type="button"
                    @click="deleteModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Hapus Draft
                </button>
            @endif

            {{-- Aksi Khusus Posted (Paid / Partial) --}}
            @if ($isPaid || $isPartial)
                <button
                    type="button"
                    @click="cancelModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs font-semibold rounded-lg shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Batalkan Pembayaran
                </button>
            @endif
        </div>
    </div>

    {{-- Visual Stepper (4 Tahap) --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 relative">
            {{-- Step 1: Draft --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs bg-emerald-100 text-[#15803d]">
                    ✓
                </div>
                <div>
                    <p class="text-xs font-bold text-gray-900">1. Draft Dibuat</p>
                    <p class="text-[11px] text-gray-400">Rincian faktur terdata</p>
                </div>
            </div>

            {{-- Step 2: Posting GL --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ !$isDraft ? 'bg-emerald-100 text-[#15803d]' : 'bg-gray-100 text-gray-400' }}">
                    {{ !$isDraft ? '✓' : '2' }}
                </div>
                <div>
                    <p class="text-xs font-bold {{ !$isDraft ? 'text-gray-900' : 'text-gray-400' }}">2. Posting Buku Besar</p>
                    <p class="text-[11px] text-gray-400">Jurnal Kas/Bank terbentuk</p>
                </div>
            </div>

            {{-- Step 3: Pelunasan AP --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ ($isPaid || $isPartial) ? 'bg-emerald-100 text-[#15803d]' : 'bg-gray-100 text-gray-400' }}">
                    {{ ($isPaid || $isPartial) ? '✓' : '3' }}
                </div>
                <div>
                    <p class="text-xs font-bold {{ ($isPaid || $isPartial) ? 'text-gray-900' : 'text-gray-400' }}">3. Pemotongan Hutang</p>
                    <p class="text-[11px] text-gray-400">Saldo AP dikurangi</p>
                </div>
            </div>

            {{-- Step 4: Status Akhir --}}
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs {{ $isPaid ? 'bg-emerald-100 text-[#15803d]' : ($isCancelled ? 'bg-rose-100 text-rose-600' : 'bg-gray-100 text-gray-400') }}">
                    {{ $isPaid ? '★' : ($isCancelled ? '✕' : '4') }}
                </div>
                <div>
                    <p class="text-xs font-bold {{ $isPaid ? 'text-emerald-800' : ($isCancelled ? 'text-rose-700' : 'text-gray-400') }}">
                        {{ $isCancelled ? 'Dibatalkan' : ($isPaid ? '4. Lunas Sempurna' : '4. Status Akhir') }}
                    </p>
                    <p class="text-[11px] text-gray-400">{{ $isCancelled ? 'Jurnal & AP direversalkan' : ($isPaid ? 'Siklus pembayaran selesai' : 'Menunggu pelunasan penuh') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Ringkasan Grid (4 Cards) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Pembayaran --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Nominal Pembayaran</span>
            <div class="mt-2 text-xl font-bold text-[#15803d]">
                {{ \App\Helpers\MoneyHelper::rupiah($payment->total_payment) }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Dikeluarkan dari kas/bank perusahaan</p>
        </div>

        {{-- Akun Kas & Bank --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Akun Kas / Bank Pengeluar</span>
            <div class="mt-2 text-sm font-bold text-gray-900 truncate" title="{{ $payment->coa ? $payment->coa->name : '-' }}">
                {{ $payment->coa ? "({$payment->coa->code}) {$payment->coa->name}" : '-' }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Metode: <strong class="text-gray-700">{{ $payment->payment_method }}</strong></p>
        </div>

        {{-- Rekening Tujuan Vendor --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Rekening Tujuan Vendor</span>
            <div class="mt-2 text-sm font-semibold text-gray-900 truncate" title="{{ $payment->target_bank_account ?: '-' }}">
                {{ $payment->target_bank_account ?: '-' }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Ref Transfer: <strong class="text-gray-700">{{ $payment->transfer_reference_number ?: '-' }}</strong></p>
        </div>

        {{-- Cabang & Tanggal --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Tanggal &amp; Cabang</span>
            <div class="mt-2 text-sm font-semibold text-gray-900">
                {{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '-' }}
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Cabang: <strong class="text-gray-700">{{ $payment->cabang?->nama ?? 'Pusat' }}</strong></p>
        </div>
    </div>

    {{-- Tabs Section: Alokasi Faktur, Jurnal Buku Besar, Berkas Bukti --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ activeTab: 'invoices' }">
        {{-- Tab Headers --}}
        <div class="border-b border-gray-200 bg-gray-50/75 px-4 sm:px-6">
            <nav class="flex space-x-6 text-xs sm:text-sm font-semibold">
                <button
                    type="button"
                    @click="activeTab = 'invoices'"
                    :class="activeTab === 'invoices' ? 'border-[#15803d] text-[#15803d] border-b-2 py-3.5' : 'text-gray-500 hover:text-gray-700 py-3.5 border-b-2 border-transparent'"
                    class="transition font-bold flex items-center gap-2"
                >
                    <span>Faktur yang Dilunasi</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-700">
                        {{ $payment->vendorPaymentDetail->count() }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'journals'"
                    :class="activeTab === 'journals' ? 'border-[#15803d] text-[#15803d] border-b-2 py-3.5' : 'text-gray-500 hover:text-gray-700 py-3.5 border-b-2 border-transparent'"
                    class="transition font-bold flex items-center gap-2"
                >
                    <span>Jurnal Buku Besar (GL)</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200 text-gray-700">
                        {{ $payment->journalEntries->count() }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'documents'"
                    :class="activeTab === 'documents' ? 'border-[#15803d] text-[#15803d] border-b-2 py-3.5' : 'text-gray-500 hover:text-gray-700 py-3.5 border-b-2 border-transparent'"
                    class="transition font-bold flex items-center gap-2"
                >
                    <span>Berkas &amp; Catatan</span>
                    @if ($payment->proof_file)
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Tab 1: Alokasi Faktur Pembelian --}}
        <div x-show="activeTab === 'invoices'" class="p-4 sm:p-6 space-y-4">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3">No. Faktur Vendor / Sistem</th>
                            <th class="px-4 py-3">Tanggal Faktur</th>
                            <th class="px-4 py-3 text-right">Total Faktur</th>
                            <th class="px-4 py-3 text-right">Nominal Dilunasi</th>
                            <th class="px-4 py-3 text-right">Sisa Hutang Saat Ini</th>
                            <th class="px-4 py-3 text-center">Status Faktur</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach ($payment->vendorPaymentDetail as $detail)
                            @php
                                $inv = $detail->invoice;
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-900">{{ $inv?->invoice_number ?? 'Invoice #' . $detail->invoice_id }}</div>
                                    @if ($inv?->supplier_invoice_number)
                                        <div class="text-[11px] text-gray-400">Ref Vendor: {{ $inv->supplier_invoice_number }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $inv?->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-700 whitespace-nowrap">
                                    {{ \App\Helpers\MoneyHelper::rupiah($inv?->total ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-[#15803d] whitespace-nowrap">
                                    {{ \App\Helpers\MoneyHelper::rupiah($detail->amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                                    {{ \App\Helpers\MoneyHelper::rupiah($inv?->accountPayable?->remaining ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @php
                                        $invSt = strtolower((string)($inv?->status ?? ''));
                                    @endphp
                                    @if ($invSt === 'paid')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-emerald-100 text-emerald-800">
                                            Lunas
                                        </span>
                                    @elseif (in_array($invSt, ['partially_paid', 'partial']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-100 text-amber-800">
                                            Sebagian
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-bold bg-gray-100 text-gray-700">
                                            {{ $inv?->status ?? 'Unpaid' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if ($inv && !empty($inv->id))
                                        <a
                                            href="{{ route('pembelian.invoices.show', $inv->id) }}"
                                            class="inline-flex items-center gap-1 text-xs font-semibold text-[#15803d] hover:text-[#14532d] hover:underline"
                                        >
                                            <span>Lihat Faktur</span>
                                            <span>&rarr;</span>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 2: Jurnal Akuntansi Buku Besar (GL) --}}
        <div x-show="activeTab === 'journals'" class="p-4 sm:p-6 space-y-4">
            @if ($payment->journalEntries->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3">Tanggal Jurnal</th>
                                <th class="px-4 py-3">No. Referensi</th>
                                <th class="px-4 py-3">Kode &amp; Nama Akun (COA)</th>
                                <th class="px-4 py-3 text-right">Debit (Rp)</th>
                                <th class="px-4 py-3 text-right">Kredit (Rp)</th>
                                <th class="px-4 py-3">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach ($payment->journalEntries as $je)
                                <tr class="hover:bg-gray-50/80 transition">
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        {{ $je->date ? \Carbon\Carbon::parse($je->date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">
                                        {{ $je->reference ?? $payment->payment_number }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-gray-900">{{ $je->coa?->code }} - {{ $je->coa?->name }}</div>
                                        <div class="text-[10.5px] text-gray-400">{{ $je->coa?->type }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                                        {{ (float)$je->debit > 0 ? \App\Helpers\MoneyHelper::rupiah($je->debit) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 whitespace-nowrap">
                                        {{ (float)$je->credit > 0 ? \App\Helpers\MoneyHelper::rupiah($je->credit) : '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $je->description ?: '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold text-gray-900">
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-right uppercase tracking-wider text-xs">Total:</td>
                                <td class="px-4 py-2.5 text-right text-xs text-[#15803d]">
                                    {{ \App\Helpers\MoneyHelper::rupiah($payment->journalEntries->sum('debit')) }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-xs text-[#15803d]">
                                    {{ \App\Helpers\MoneyHelper::rupiah($payment->journalEntries->sum('credit')) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-gray-500">
                    <div class="p-3 bg-gray-100 rounded-full w-12 h-12 mx-auto flex items-center justify-center text-gray-400 mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <p class="font-bold text-gray-700 text-xs">Belum ada jurnal Buku Besar</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">Jurnal akuntansi otomatis terbentuk saat status pembayaran diposting ke Buku Besar.</p>
                </div>
            @endif
        </div>

        {{-- Tab 3: Berkas Pendukung & Catatan --}}
        <div x-show="activeTab === 'documents'" class="p-4 sm:p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Bukti Pembayaran File --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Berkas Bukti Transfer / Pembayaran</h3>
                    @if ($payment->proof_file)
                        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-emerald-100 text-[#15803d] rounded-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-gray-900">Bukti Pembayaran Terlampir</p>
                                    <p class="text-[11px] text-gray-400">{{ basename($payment->proof_file) }}</p>
                                </div>
                            </div>
                            <a
                                href="{{ Storage::url($payment->proof_file) }}"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-lg shadow-sm transition"
                            >
                                Buka File
                            </a>
                        </div>
                    @else
                        <div class="p-4 bg-gray-50 border border-dashed border-gray-200 rounded-xl text-center text-gray-400 text-xs">
                            Tidak ada berkas bukti transfer yang diunggah.
                        </div>
                    @endif
                </div>

                {{-- Catatan Internal --}}
                <div class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Catatan Internal</h3>
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl text-xs text-gray-700 whitespace-pre-line min-h-[5rem]">
                        {{ $payment->notes ?: 'Tidak ada catatan untuk transaksi pembayaran ini.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Batalkan Pembayaran (Posted) --}}
    <div
        x-show="cancelModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="cancelModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="cancelModalOpen = false"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="cancelModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
            >
                <form action="{{ route('pembelian.payments.cancel', $payment->id) }}" method="POST">
                    @csrf

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start gap-4">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10 text-rose-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                <h3 class="text-base leading-6 font-bold text-gray-900" id="modal-title">
                                    Batalkan Pembayaran {{ $payment->payment_number }}
                                </h3>
                                <p class="mt-2 text-xs text-gray-500 leading-relaxed">
                                    Pembatalan akan <strong>mengembalikan saldo hutang (AP)</strong> pada faktur terkait dan <strong>menghapus jurnal pengeluaran kas/bank</strong> yang telah tercatat di Buku Besar.
                                </p>
                                <div class="mt-3">
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
                                        Alasan Pembatalan <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        name="cancel_reason"
                                        rows="3"
                                        required
                                        placeholder="Tuliskan alasan mengapa pembayaran ini dibatalkan..."
                                        class="w-full px-3 py-2 text-xs bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 focus:outline-none transition"
                        >
                            Konfirmasi Batalkan
                        </button>
                        <button
                            type="button"
                            @click="cancelModalOpen = false"
                            class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none transition"
                        >
                            Tutup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Draft --}}
    @if ($isDraft)
        <div
            x-show="deleteModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div
                    x-show="deleteModalOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                    @click="deleteModalOpen = false"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div
                    x-show="deleteModalOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                >
                    <form action="{{ route('pembelian.payments.destroy', $payment->id) }}" method="POST">
                        @csrf
                        @method('DELETE')

                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start gap-4">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10 text-rose-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:text-left">
                                    <h3 class="text-base leading-6 font-bold text-gray-900">
                                        Hapus Draft Pembayaran
                                    </h3>
                                    <p class="mt-2 text-xs text-gray-500 leading-relaxed">
                                        Apakah Anda yakin ingin menghapus draft pembayaran <strong>{{ $payment->payment_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <button
                                type="submit"
                                class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 focus:outline-none transition"
                            >
                                Hapus Pembayaran
                            </button>
                            <button
                                type="button"
                                @click="deleteModalOpen = false"
                                class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none transition"
                            >
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
