@extends('layouts.app')

@section('title', 'Transfer ' . $transfer->number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{
    deleteModalOpen: false
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.cashbank.index', ['tab' => 'transfer']) }}" class="hover:text-gray-700">Kas &amp; Bank</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.cashbank.index', ['tab' => 'transfer']) }}" class="hover:text-gray-700">Transfer</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $transfer->number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">{{ $transfer->number }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    &bull; {{ ucfirst($transfer->status ?? 'posted') }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Tanggal transfer: {{ \Carbon\Carbon::parse($transfer->date)->translatedFormat('d F Y') }}</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('keuangan.cashbank.index', ['tab' => 'transfer']) }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali
            </a>
            <a
                href="{{ route('keuangan.cashbank.transfer.pdf', $transfer) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Bukti Transfer PDF
            </a>
            <button
                type="button"
                @click="deleteModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-rose-300 hover:bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Batalkan
            </button>
        </div>
    </div>

    {{-- Diagram Visual Alur Transfer --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            {{-- Dari Rekening --}}
            <div class="w-full md:w-5/12 p-4 bg-rose-50/60 border border-rose-200 rounded-xl">
                <span class="text-[11px] font-bold text-rose-700 uppercase tracking-wider block mb-1">Sumber Dana (Rekening Asal)</span>
                <div class="text-base font-bold text-gray-900">{{ $transfer->fromCoa?->name ?? '-' }}</div>
                <div class="font-mono text-xs text-gray-500 mt-0.5">{{ $transfer->fromCoa?->code }} &bull; {{ $transfer->fromCoa?->type }}</div>
                <div class="mt-3 pt-2.5 border-t border-rose-100 flex items-center justify-between">
                    <span class="text-xs text-rose-700 font-semibold">Total Pengurangan Saldo:</span>
                    <span class="text-sm font-bold text-rose-700">- {{ \App\Helpers\MoneyHelper::rupiah($transfer->amount + $transfer->other_costs) }}</span>
                </div>
            </div>

            {{-- Arrow Indikator --}}
            <div class="flex flex-col items-center justify-center text-center">
                <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full border border-emerald-200 mb-1">
                    {{ \App\Helpers\MoneyHelper::rupiah($transfer->amount) }}
                </span>
                <svg class="w-8 h-8 text-emerald-600 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
                <svg class="w-6 h-6 text-emerald-600 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                @if ($transfer->other_costs > 0)
                    <span class="text-[10.5px] text-gray-400 mt-1 font-semibold">
                        + Biaya Admin: {{ \App\Helpers\MoneyHelper::rupiah($transfer->other_costs) }}
                    </span>
                @endif
            </div>

            {{-- Ke Rekening --}}
            <div class="w-full md:w-5/12 p-4 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider block mb-1">Penerima Dana (Rekening Tujuan)</span>
                <div class="text-base font-bold text-gray-900">{{ $transfer->toCoa?->name ?? '-' }}</div>
                <div class="font-mono text-xs text-gray-500 mt-0.5">{{ $transfer->toCoa?->code }} &bull; {{ $transfer->toCoa?->type }}</div>
                <div class="mt-3 pt-2.5 border-t border-emerald-100 flex items-center justify-between">
                    <span class="text-xs text-emerald-700 font-semibold">Total Dana Masuk:</span>
                    <span class="text-sm font-bold text-emerald-700">+ {{ \App\Helpers\MoneyHelper::rupiah($transfer->amount) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Informasi & Jurnal --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Meta Informasi (2 Cols) --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center justify-between">
                    <span>Detail Transaksi Transfer</span>
                    <span class="text-xs text-gray-400 font-normal">ID: #{{ $transfer->id }}</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">No. Bukti Transfer</span>
                        <div class="font-bold text-gray-900 mt-0.5 font-mono text-sm">{{ $transfer->number }}</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Tanggal</span>
                        <div class="font-bold text-gray-900 mt-0.5 text-sm">{{ \Carbon\Carbon::parse($transfer->date)->translatedFormat('d F Y') }}</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">No. Referensi Bank</span>
                        <div class="font-bold text-gray-900 mt-0.5 font-mono">{{ $transfer->reference ?: '-' }}</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Cabang</span>
                        <div class="font-bold text-gray-900 mt-0.5">{{ $transfer->cabang?->nama ?? 'Pusat' }}</div>
                    </div>

                    @if ($transfer->other_costs > 0)
                        <div>
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Biaya Administrasi</span>
                            <div class="font-bold text-gray-900 mt-0.5">{{ \App\Helpers\MoneyHelper::rupiah($transfer->other_costs) }}</div>
                        </div>
                        <div>
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Akun Biaya Admin</span>
                            <div class="font-bold text-gray-900 mt-0.5">{{ $transfer->otherCostsCoa?->name ?? 'Biaya Admin' }} ({{ $transfer->otherCostsCoa?->code ?? '-' }})</div>
                        </div>
                    @endif

                    @if ($transfer->description)
                        <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Catatan / Keterangan</span>
                            <p class="text-gray-700 mt-0.5 leading-relaxed bg-gray-50 p-2.5 rounded-lg border border-gray-200">
                                {{ $transfer->description }}
                            </p>
                        </div>
                    @endif

                    @if ($transfer->attachment_path)
                        <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px] mb-1.5">Lampiran Bukti Transfer</span>
                            <a
                                href="{{ Storage::url($transfer->attachment_path) }}"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 hover:bg-gray-100 rounded-lg text-emerald-700 font-semibold transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span>Lihat File Bukti Transfer</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom Kanan: Jurnal Buku Besar (1 Col) --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-2.5">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Jurnal Buku Besar
                    </h2>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Balanced
                    </span>
                </div>

                @if ($transfer->journalEntries->isNotEmpty())
                    <div class="space-y-2">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 text-gray-500 font-bold uppercase text-[10px] border-b border-gray-200">
                                    <tr>
                                        <th class="py-2 px-2.5">Akun</th>
                                        <th class="py-2 px-2 text-right">Debit</th>
                                        <th class="py-2 px-2 text-right">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php
                                        $totalDebit = 0;
                                        $totalCredit = 0;
                                    @endphp
                                    @foreach ($transfer->journalEntries as $entry)
                                        @php
                                            $totalDebit += (float) $entry->debit;
                                            $totalCredit += (float) $entry->credit;
                                        @endphp
                                        <tr class="hover:bg-gray-50/50">
                                            <td class="py-2 px-2.5">
                                                <div class="font-bold text-gray-900 {{ (float)$entry->credit > 0 ? 'pl-2' : '' }}">
                                                    {{ $entry->coa?->name ?? 'Akun #' . $entry->coa_id }}
                                                </div>
                                                <div class="font-mono text-[10.5px] text-gray-400 {{ (float)$entry->credit > 0 ? 'pl-2' : '' }}">
                                                    {{ $entry->coa?->code }}
                                                </div>
                                            </td>
                                            <td class="py-2 px-2 text-right font-mono text-gray-800 whitespace-nowrap">
                                                {{ (float) $entry->debit > 0 ? number_format($entry->debit, 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="py-2 px-2 text-right font-mono text-gray-800 whitespace-nowrap">
                                                {{ (float) $entry->credit > 0 ? number_format($entry->credit, 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50 border-t border-gray-200 font-bold font-mono text-xs">
                                    <tr>
                                        <td class="py-2 px-2.5 text-gray-700">Total:</td>
                                        <td class="py-2 px-2 text-right text-gray-900">{{ number_format($totalDebit, 0, ',', '.') }}</td>
                                        <td class="py-2 px-2 text-right text-gray-900">{{ number_format($totalCredit, 0, ',', '.') }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic text-center py-4">Belum ada jurnal yang tercatat untuk transfer ini.</p>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-xs space-y-2 text-gray-500">
                <div class="flex justify-between">
                    <span>Waktu Input:</span>
                    <span class="font-semibold text-gray-900">{{ $transfer->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Terakhir Update:</span>
                    <span class="font-semibold text-gray-900">{{ $transfer->updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Batal Transfer --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="deleteModalOpen = false"
            class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl border border-gray-100"
        >
            <div class="flex items-center gap-3 text-rose-600 mb-3">
                <div class="p-2.5 bg-rose-50 rounded-full">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Batalkan &amp; Hapus Transfer</h3>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin membatalkan transfer nomor <span class="font-bold text-gray-900 font-mono">{{ $transfer->number }}</span>?
                <br><br>
                <span class="text-rose-600 font-semibold">Peringatan:</span> Transaksi dan jurnal transfer saldo terkait akan dihapus secara permanen.
            </p>
            <form action="{{ route('keuangan.cashbank.transfer.destroy', $transfer) }}" method="POST" class="flex items-center justify-end gap-2.5">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="deleteModalOpen = false"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    Ya, Batalkan Transfer
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
