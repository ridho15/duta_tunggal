@extends('layouts.app')

@section('title', 'Detail Pelepasan Aset Tetap – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
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
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                    Selesai / Aset Dihentikan (Disposed)
                </span>
            </div>
        </div>

        <div class="flex items-center gap-2">
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
</div>
@endsection
