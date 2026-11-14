@extends('layouts.app')

@section('title', 'Detail Pelepasan Aset Tetap – Duta Tunggal ERP')

@section('content')
<div x-data="{ voidModalOpen: false }" class="space-y-6 max-w-5xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.disposal.index') }}" class="hover:underline">Pelepasan Aset</a>
                <span class="text-gray-300">/</span>
                <span class="text-rose-600 font-bold">Detail #{{ $disposal->id }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pelepasan #{{ $disposal->id }}</h1>
                @if ($disposal->isCancelled())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-300">
                        Dibatalkan / Voided
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                        Selesai / Aset Dihentikan (Disposed)
                    </span>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if (!$disposal->isCancelled())
                <button
                    type="button"
                    @click="voidModalOpen = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                    title="Batalkan / Void Pelepasan Aset (Super Admin / Finance)"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Void Pelepasan Aset
                </button>
            @endif
            <a
                href="{{ route('keuangan.asset.disposal.pdf', $disposal) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm font-semibold rounded-xl border border-purple-200 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Berita Acara (PDF)
            </a>
            <a
                href="{{ route('keuangan.asset.disposal.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Kembali
            </a>
        </div>
    </div>


    {{-- 4 Kartu Ringkasan Finansial Pelepasan --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Nilai Perolehan Awal --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nilai Perolehan Aset</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1 font-mono">
                {{ \App\Helpers\MoneyHelper::rupiah($disposal->asset->purchase_cost ?? 0) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Harga beli awal yang dihapus</span>
        </div>

        {{-- Nilai Buku Saat Pelepasan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nilai Buku (NBV)</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1 font-mono">
                {{ \App\Helpers\MoneyHelper::rupiah($disposal->book_value_at_disposal) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Sisa nilai buku saat dihentikan</span>
        </div>

        {{-- Kas Diterima --}}
        <div class="p-5 rounded-2xl border border-emerald-100 bg-emerald-50/20 shadow-sm">
            <p class="text-xs font-semibold text-[#15803d] uppercase tracking-wider">Kas Penjualan</p>
            <h3 class="text-2xl font-bold text-[#15803d] mt-1 font-mono">
                {{ $disposal->sale_price > 0 ? \App\Helpers\MoneyHelper::rupiah($disposal->sale_price) : '-' }}
            </h3>
            <span class="text-[11px] text-emerald-600/75 mt-0.5 block">Penerimaan kas masuk</span>
        </div>

        {{-- Laba / Rugi Pelepasan --}}
        <div class="bg-white p-5 rounded-2xl border {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'border-emerald-100 bg-emerald-50/20' : 'border-rose-100 bg-rose-50/20' }} shadow-sm">
            <p class="text-xs font-semibold {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'text-[#15803d]' : 'text-rose-700' }} uppercase tracking-wider">
                {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'Keuntungan (Gain)' : 'Kerugian (Loss)' }}
            </p>
            <h3 class="text-2xl font-bold {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'text-[#15803d]' : 'text-rose-700' }} mt-1 font-mono">
                {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? '+' : '-' }}{{ \App\Helpers\MoneyHelper::rupiah(abs($disposal->gain_loss_amount ?? 0)) }}
            </h3>
            <span class="text-[11px] {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'text-emerald-600/75' : 'text-rose-600/75' }} mt-0.5 block">
                Hasil akuntansi laba rugi
            </span>
        </div>
    </div>

    {{-- Detail Aset & Berita Acara --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Informasi Aset Tetap
            </h3>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Nama & Kode Aset</span>
                <a href="{{ route('keuangan.asset.show', $disposal->asset_id) }}" class="text-base font-bold text-[#15803d] hover:underline block mt-0.5">
                    {{ $disposal->asset->name ?? '-' }}
                </a>
                <span class="text-xs font-mono text-gray-500 block">{{ $disposal->asset->code ?? '-' }}</span>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Cabang</span>
                    <span class="text-xs font-semibold text-gray-800 block mt-0.5">{{ $disposal->asset->cabang->nama ?? '-' }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Tanggal Pelepasan</span>
                    <span class="text-xs font-semibold text-gray-800 block mt-0.5">
                        {{ $disposal->disposal_date ? $disposal->disposal_date->format('d/m/Y') : '-' }}
                    </span>
                </div>
            </div>

            <div class="pt-2 border-t border-gray-100">
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Jenis Pelepasan</span>
                <div class="mt-1">
                    @if ($disposal->disposal_type === 'sale')
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-800">
                            Penjualan Aset Tetap (Sale)
                        </span>
                    @elseif ($disposal->disposal_type === 'scrap')
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-rose-100 text-rose-800">
                            Scrap / Rusak Total / Afkir
                        </span>
                    @elseif ($disposal->disposal_type === 'donation')
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-blue-100 text-blue-800">
                            Donasi / Sumbangan
                        </span>
                    @elseif ($disposal->disposal_type === 'theft')
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-purple-100 text-purple-800">
                            Hilang / Dicuri
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-bold bg-gray-100 text-gray-800">
                            {{ ucfirst($disposal->disposal_type) }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Catatan & Lampiran Berita Acara
            </h3>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Catatan Pelepasan</span>
                <p class="text-xs text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 whitespace-pre-line leading-relaxed">
                    {{ $disposal->notes ?: 'Tidak ada catatan pelepasan.' }}
                </p>
            </div>

            <div>
                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block mb-1">Dokumen Berita Acara</span>
                @if ($disposal->disposal_document)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-purple-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-xs font-medium text-gray-700 truncate max-w-xs">Berkas Berita Acara Disposal</span>
                        </div>
                        <a
                            href="{{ asset('storage/' . $disposal->disposal_document) }}"
                            target="_blank"
                            class="px-3 py-1 bg-white hover:bg-gray-100 text-purple-700 text-xs font-semibold rounded-lg border border-gray-200 transition"
                        >
                            Buka Berkas
                        </a>
                    </div>
                @else
                    <span class="text-xs text-gray-400 italic">Tidak ada dokumen yang dilampirkan.</span>
                @endif
            </div>

            <div class="pt-2 border-t border-gray-100 text-xs text-gray-500">
                <div class="flex justify-between">
                    <span>Disetujui / Dicatat Oleh:</span>
                    <span class="font-medium text-gray-800">{{ $disposal->approvedBy->name ?? 'System' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabel Jurnal Akuntansi Buku Besar Yang Terposting --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Log Entri Jurnal Akuntansi Pelepasan Aset</h3>
                <p class="text-xs text-gray-500 mt-0.5">Penjurnalan otomatis penghapusan aset, akumulasi, kas masuk, dan rekonsiliasi laba/rugi pelepasan.</p>
            </div>
            <span class="text-xs font-bold px-2.5 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg">
                {{ $disposal->journalEntries->count() }} Baris Jurnal (Seimbang)
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Tanggal Jurnal</th>
                        <th class="py-3 px-4">Kode Akun</th>
                        <th class="py-3 px-4">Nama Akun (COA)</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-right">Debit (Rp)</th>
                        <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $totalDebit = 0;
                        $totalCredit = 0;
                    @endphp
                    @forelse ($disposal->journalEntries as $entry)
                        @php
                            $totalDebit += $entry->debit;
                            $totalCredit += $entry->credit;
                        @endphp
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-mono font-bold text-gray-800">
                                {{ $entry->coa->code ?? '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-900 font-medium">
                                {{ $entry->coa->name ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600 max-w-xs truncate">
                                {{ $entry->description ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold {{ $entry->debit > 0 ? 'text-emerald-700' : 'text-gray-400' }}">
                                {{ $entry->debit > 0 ? \App\Helpers\MoneyHelper::rupiah($entry->debit) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold {{ $entry->credit > 0 ? 'text-rose-700' : 'text-gray-400' }}">
                                {{ $entry->credit > 0 ? \App\Helpers\MoneyHelper::rupiah($entry->credit) : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-400">
                                Belum ada jurnal yang terhubung dengan pelepasan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($disposal->journalEntries->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-xs text-gray-900">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider">Total Keseimbangan Buku Besar:</td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-700">{{ \App\Helpers\MoneyHelper::rupiah($totalDebit) }}</td>
                            <td class="py-3 px-4 text-right font-mono text-rose-700">{{ \App\Helpers\MoneyHelper::rupiah($totalCredit) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Modal Konfirmasi Void Pelepasan Aset --}}
    <div
        x-show="voidModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="voidModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="voidModalOpen = false"
                aria-hidden="true"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="voidModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100"
            >
                <form action="{{ route('keuangan.asset.disposal.void', $disposal) }}" method="POST">
                    @csrf
                    <div class="bg-white px-6 pt-6 pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-base font-extrabold text-gray-900" id="modal-title">
                                    Batalkan / Void Pelepasan Aset
                                </h3>
                                <div class="mt-2 text-xs text-gray-600 space-y-2">
                                    <p>
                                        Anda akan membatalkan penghapusan aset <strong class="text-gray-900">{{ $disposal->asset->name }}</strong> ({{ $disposal->asset->code }}).
                                    </p>
                                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                                        <strong>Dampak Pembatalan:</strong>
                                        <ul class="list-disc pl-4 mt-1 space-y-0.5">
                                            <li>Status aset akan dipulihkan menjadi <strong>Aktif</strong>.</li>
                                            <li>Seluruh jurnal disposal (kerugian/keuntungan & akumulasi) akan dibersihkan dari Buku Besar.</li>
                                            <li>Pencatatan depresiasi bulanan dapat dilanjutkan kembali.</li>
                                        </ul>
                                    </div>
                                    <div class="mt-3 text-left">
                                        <label for="reason" class="block text-xs font-semibold text-gray-700 mb-1">
                                            Alasan Pembatalan / Void * (Minimal 5 karakter)
                                        </label>
                                        <textarea
                                            id="reason"
                                            name="reason"
                                            rows="3"
                                            required
                                            minlength="5"
                                            class="w-full text-xs rounded-xl border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm"
                                            placeholder="Contoh: Kesalahan input unit aset yang dihapuskan atau transaksi penjualan dibatalkan oleh pembeli"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-6 py-3.5 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="submit"
                            class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 focus:outline-none transition sm:w-auto"
                        >
                            Konfirmasi Void Pelepasan
                        </button>
                        <button
                            type="button"
                            @click="voidModalOpen = false"
                            class="mt-2 sm:mt-0 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none transition sm:w-auto"
                        >
                            Tutup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
