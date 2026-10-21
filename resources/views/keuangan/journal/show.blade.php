@extends('layouts.app')

@section('title', 'Voucher Jurnal ' . $header->reference . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{
    deleteModalOpen: false
}">
    {{-- Header Halaman & Breadcrumb --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.journal.index') }}" class="hover:text-gray-700">Keuangan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.journal.index') }}" class="hover:text-gray-700">Jurnal Umum</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $header->reference }}</span>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">{{ $header->reference }}</h1>
                @php
                    $typeBadges = [
                        'manual' => ['label' => 'Jurnal Manual', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                        'adjustment' => ['label' => 'Penyesuaian (AJP)', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                        'reversal' => ['label' => 'Pembalikan (Reversal)', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                        'purchase' => ['label' => 'Pembelian', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                        'purchase_invoice' => ['label' => 'Tagihan Pembelian', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                        'purchase_payment' => ['label' => 'Pembayaran Pembelian', 'class' => 'bg-cyan-50 text-cyan-700 border-cyan-200'],
                        'purchase_return' => ['label' => 'Retur Pembelian', 'class' => 'bg-teal-50 text-teal-700 border-teal-200'],
                        'sales' => ['label' => 'Penjualan', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        'sales_invoice' => ['label' => 'Faktur Penjualan', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        'sales_payment' => ['label' => 'Penerimaan Piutang', 'class' => 'bg-green-50 text-green-700 border-green-200'],
                        'cash_bank' => ['label' => 'Kas & Bank', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'],
                        'cashbank_in' => ['label' => 'Kas/Bank Masuk', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        'cashbank_out' => ['label' => 'Kas/Bank Keluar', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                        'cashbank_transfer' => ['label' => 'Transfer Kas/Bank', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                        'inventory' => ['label' => 'Persediaan', 'class' => 'bg-orange-50 text-orange-700 border-orange-200'],
                    ];
                    $badge = $typeBadges[$header->journal_type] ?? [
                        'label' => ucwords(str_replace('_', ' ', $header->journal_type)),
                        'class' => 'bg-gray-50 text-gray-700 border-gray-200'
                    ];
                    $diff = abs((float) $header->total_debit - (float) $header->total_credit);
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badge['class'] }}">
                    {{ $badge['label'] }}
                </span>
                @if ($diff < 0.01)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Seimbang (Balanced)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Tidak Seimbang (Selisih: {{ number_format($diff, 2, ',', '.') }})
                    </span>
                @endif

                @if (!$header->is_manual)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-600 border border-gray-200">
                        <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        Otomatis Sistem
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-1">
                Tanggal Transaksi Buku: <span class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($header->date)->translatedFormat('l, d F Y') }}</span>
                @if ($header->cabang)
                    &bull; Cabang: <span class="font-medium text-gray-800">{{ $header->cabang->name }}</span>
                @endif
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2.5 flex-wrap">
            <a
                href="{{ route('keuangan.journal.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali
            </a>
            <a
                href="{{ route('keuangan.journal.pdf', $header->reference) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak PDF
            </a>
            @if ($header->is_manual)
                <button
                    type="button"
                    @click="deleteModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-rose-300 hover:bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Hapus Voucher
                </button>
            @endif
        </div>
    </div>

    {{-- Layout Grid Atas: Header Information Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Card 1: Informasi Voucher --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-2">
                Informasi Voucher
            </h2>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Nomor Voucher</span>
                    <span class="font-mono font-bold text-gray-900">{{ $header->reference }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Tanggal Buku</span>
                    <span class="font-semibold text-gray-900">{{ \Carbon\Carbon::parse($header->date)->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Tipe Jurnal</span>
                    <span class="font-semibold text-gray-800">{{ $badge['label'] }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400">Cabang</span>
                    <span class="font-semibold text-gray-800">{{ $header->cabang?->name ?? 'Semua Cabang / Kantor Pusat' }}</span>
                </div>
                @if ($header->created_at)
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Waktu Pembukuan</span>
                        <span class="text-gray-600">{{ \Carbon\Carbon::parse($header->created_at)->format('d/m/Y H:i') }} WIB</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card 2: Dokumen Sumber & Deskripsi --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3">
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-2">
                Dokumen Sumber
            </h2>
            <div class="space-y-2 text-xs">
                <div>
                    <span class="text-gray-400 block mb-0.5">Asal Dokumen Transaksi</span>
                    @if ($header->source_type)
                        <div class="font-medium text-gray-900 flex items-center gap-1.5">
                            <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span>
                            {{ class_basename($header->source_type) }} #{{ $header->source_id }}
                        </div>
                        {{-- Link langsung ke modul terkait jika sesuai --}}
                        @php
                            $sourceUrl = null;
                            $sourceName = class_basename($header->source_type);
                            if (str_contains($sourceName, 'PurchaseInvoice') && Route::has('pembelian.invoice.show')) {
                                $sourceUrl = route('pembelian.invoice.show', $header->source_id);
                            } elseif (str_contains($sourceName, 'PurchaseOrder') && Route::has('pembelian.po.show')) {
                                $sourceUrl = route('pembelian.po.show', $header->source_id);
                            } elseif (str_contains($sourceName, 'CashBank') && Route::has('keuangan.cashbank.show')) {
                                $sourceUrl = route('keuangan.cashbank.show', $header->source_id);
                            } elseif (str_contains($sourceName, 'PurchaseReturn') && Route::has('pembelian.retur.show')) {
                                $sourceUrl = route('pembelian.retur.show', $header->source_id);
                            }
                        @endphp
                        @if ($sourceUrl)
                            <a href="{{ $sourceUrl }}" class="inline-flex items-center gap-1 text-[#15803d] hover:underline font-semibold mt-1">
                                Buka Dokumen Sumber &rarr;
                            </a>
                        @endif
                    @else
                        <div class="text-gray-700 italic">
                            Jurnal Input Manual / Penyesuaian Akuntansi
                        </div>
                    @endif
                </div>

                <div class="pt-1">
                    <span class="text-gray-400 block mb-0.5">Keterangan / Memo Global</span>
                    <div class="p-2 bg-gray-50 rounded border border-gray-100 text-gray-800 leading-relaxed font-normal">
                        {{ $header->description ?: 'Tidak ada keterangan voucher.' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 3: Total Nominal & Keseimbangan --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3 flex flex-col justify-between">
            <div>
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100 pb-2">
                    Nilai Voucher
                </h2>
                <div class="mt-2 space-y-2 text-xs">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Total Debit (DR)</span>
                        <span class="font-mono font-bold text-emerald-700 text-sm">Rp {{ number_format((float) $header->total_debit, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Total Kredit (CR)</span>
                        <span class="font-mono font-bold text-blue-700 text-sm">Rp {{ number_format((float) $header->total_credit, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                        <span class="text-gray-500">Selisih (Balance)</span>
                        <span class="font-mono font-bold {{ $diff < 0.01 ? 'text-gray-400' : 'text-rose-600 text-sm' }}">
                            Rp {{ number_format($diff, 2, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-3 p-2.5 rounded-lg {{ $diff < 0.01 ? 'bg-emerald-50 border border-emerald-100 text-emerald-800' : 'bg-rose-50 border border-rose-100 text-rose-800' }} text-xs flex items-center gap-2">
                @if ($diff < 0.01)
                    <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    <span>Jurnal valid & seimbang sesuai kaidah akuntansi ganda.</span>
                @else
                    <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    <span>Perhatian: Entri jurnal ini tidak seimbang. Segera buat jurnal koreksi.</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Entri Jurnal --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-200 bg-gray-50/75 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Rincian Baris Jurnal (Double-Entry Posting)</h3>
                <p class="text-xs text-gray-500 mt-0.5">Daftar akun debit dan kredit yang diposting ke dalam Buku Besar umum.</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded-md shadow-xs">
                {{ $lines->count() }} Baris Pembukuan
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-100/75 text-gray-600 uppercase font-semibold border-b border-gray-200 text-[11px]">
                    <tr>
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4 w-32">Kode Akun</th>
                        <th class="py-3 px-4">Nama Rekening Akun</th>
                        <th class="py-3 px-4">Keterangan / Memo Baris</th>
                        <th class="py-3 px-4 text-right w-44">Debit (DR)</th>
                        <th class="py-3 px-4 text-right w-44">Kredit (CR)</th>
                        <th class="py-3 px-4 text-center w-28">Buku Besar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($lines as $index => $line)
                        @php
                            $isDebit = (float) $line->debit > 0;
                            $isCredit = (float) $line->credit > 0;
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors {{ $isCredit ? 'bg-gray-50/30' : '' }}">
                            <td class="py-3 px-4 text-center text-gray-400 font-mono">{{ $index + 1 }}</td>
                            <td class="py-3 px-4 font-mono font-semibold {{ $isDebit ? 'text-gray-900' : 'text-gray-600 pl-6' }}">
                                {{ $line->coa?->code ?? '-' }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 {{ $isCredit ? 'pl-4' : '' }}">
                                    @if ($isCredit)
                                        <span class="text-gray-400 font-mono text-[10px] mr-1">&rdquor;</span>
                                    @endif
                                    {{ $line->coa?->name ?? 'Akun Tidak Ditemukan' }}
                                </div>
                                @if ($line->coa?->type)
                                    <div class="text-[10px] text-gray-400 mt-0.5 {{ $isCredit ? 'pl-4' : '' }}">
                                        Kategori: <span class="font-medium text-gray-600">{{ $line->coa->type }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-gray-600 leading-relaxed">
                                {{ $line->description ?: '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold {{ $isDebit ? 'text-emerald-700 bg-emerald-50/20' : 'text-gray-300' }}">
                                {{ $isDebit ? 'Rp ' . number_format((float) $line->debit, 2, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold {{ $isCredit ? 'text-blue-700 bg-blue-50/20' : 'text-gray-300' }}">
                                {{ $isCredit ? 'Rp ' . number_format((float) $line->credit, 2, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($line->coa_id)
                                    <a
                                        href="{{ route('keuangan.journal.ledger', ['coa_id' => $line->coa_id]) }}"
                                        title="Buka Kartu Buku Besar untuk {{ $line->coa?->name }}"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#15803d] hover:text-[#166534] bg-emerald-50 hover:bg-emerald-100 px-2 py-1 rounded border border-emerald-200 transition"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                        Ledger
                                    </a>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500">
                                Tidak ada entri data jurnal pada voucher ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-100/90 font-bold border-t-2 border-gray-300 text-xs">
                    <tr>
                        <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider text-gray-700">
                            Total Keseimbangan (Balance):
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-emerald-800 text-sm">
                            Rp {{ number_format((float) $header->total_debit, 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-blue-800 text-sm">
                            Rp {{ number_format((float) $header->total_credit, 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-center">
                            @if ($diff < 0.01)
                                <span class="text-[11px] text-emerald-700 font-semibold uppercase">0.00 OK</span>
                            @else
                                <span class="text-[11px] text-rose-700 font-semibold uppercase">UNBALANCED</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Voucher Manual --}}
    @if ($header->is_manual)
        <div
            x-show="deleteModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
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
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-bold text-gray-900" id="modal-title">
                                    Hapus Voucher Jurnal Manual?
                                </h3>
                                <div class="mt-2 text-sm text-gray-500 space-y-2">
                                    <p>
                                        Anda akan menghapus voucher jurnal <span class="font-mono font-bold text-gray-900">{{ $header->reference }}</span>.
                                    </p>
                                    <p class="text-rose-600 text-xs font-semibold">
                                        Perhatian: Seluruh {{ $lines->count() }} baris debit dan kredit akan dihapus permanen dari Buku Besar. Tindakan ini tidak dapat dibatalkan.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <form action="{{ route('keuangan.journal.destroy', $header->reference) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700 transition sm:w-auto"
                            >
                                Ya, Hapus Voucher
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 transition sm:mt-0 sm:w-auto"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
