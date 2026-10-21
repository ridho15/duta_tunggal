@extends('layouts.app')

@section('title', 'Transaksi ' . $transaction->number . ' – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{
    deleteModalOpen: false
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.cashbank.index') }}" class="hover:text-gray-700">Kas &amp; Bank</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $transaction->number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">{{ $transaction->number }}</h1>
                @php
                    $typeLabels = [
                        'cash_in' => ['label' => 'Kas Masuk', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        'bank_in' => ['label' => 'Bank Masuk', 'class' => 'bg-blue-50 text-blue-700 border-blue-200'],
                        'cash_out' => ['label' => 'Kas Keluar', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                        'bank_out' => ['label' => 'Bank Keluar', 'class' => 'bg-rose-50 text-rose-700 border-rose-200'],
                    ];
                    $badge = $typeLabels[$transaction->type] ?? ['label' => $transaction->type, 'class' => 'bg-gray-50 text-gray-700 border-gray-200'];
                    $isIn = in_array($transaction->type, ['cash_in', 'bank_in']);
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badge['class'] }}">
                    {{ $badge['label'] }}
                </span>
                @if ($transaction->journalEntries->isNotEmpty())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        &bull; Jurnal Dibukukan
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 mt-0.5">Tanggal transaksi: {{ \Carbon\Carbon::parse($transaction->date)->translatedFormat('d F Y') }}</p>
        </div>

        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('keuangan.cashbank.index') }}"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                &larr; Kembali
            </a>
            <a
                href="{{ route('keuangan.cashbank.pdf', $transaction) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak Voucher PDF
            </a>
            <button
                type="button"
                @click="deleteModalOpen = true"
                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-rose-300 hover:bg-rose-50 text-rose-600 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus
            </button>
        </div>
    </div>

    {{-- Layout Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Informasi Transaksi (2 Cols) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Kartu Detail Utama --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center justify-between">
                    <span>Informasi Mutasi Kas &amp; Bank</span>
                    <span class="text-xs font-normal text-gray-400">ID: #{{ $transaction->id }}</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Rekening Kas / Bank</span>
                        <div class="font-bold text-gray-900 mt-0.5 text-sm">{{ $transaction->accountCoa?->name ?? '-' }}</div>
                        <div class="font-mono text-gray-500 text-[11px]">{{ $transaction->accountCoa?->code }} ({{ $transaction->accountCoa?->type }})</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Akun Lawan / Kategori</span>
                        <div class="font-bold text-gray-900 mt-0.5 text-sm">
                            @if ($transaction->transactionDetails->isNotEmpty())
                                Rincian Multi-Akun ({{ $transaction->transactionDetails->count() }} Pos)
                            @else
                                {{ $transaction->offsetCoa?->name ?? '-' }}
                            @endif
                        </div>
                        <div class="font-mono text-gray-500 text-[11px]">{{ $transaction->offsetCoa?->code ?? '-' }}</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Pihak Terkait (Counterparty)</span>
                        <div class="font-bold text-gray-900 mt-0.5">{{ $transaction->counterparty ?: '-' }}</div>
                    </div>

                    <div>
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Cabang</span>
                        <div class="font-bold text-gray-900 mt-0.5">{{ $transaction->cabang?->nama ?? 'Pusat' }}</div>
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                        <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Total Nominal Transaksi</span>
                        <div class="text-2xl font-bold mt-0.5 {{ $isIn ? 'text-emerald-700' : 'text-gray-900' }}">
                            {{ $isIn ? '+' : '-' }} {{ \App\Helpers\MoneyHelper::rupiah($transaction->amount) }}
                        </div>
                    </div>

                    @if ($transaction->description)
                        <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px]">Keterangan / Memo</span>
                            <p class="text-gray-700 mt-0.5 leading-relaxed bg-gray-50 p-2.5 rounded-lg border border-gray-200">
                                {{ $transaction->description }}
                            </p>
                        </div>
                    @endif

                    @if ($transaction->attachment_path)
                        <div class="sm:col-span-2 pt-2 border-t border-gray-100">
                            <span class="text-gray-400 block font-semibold uppercase text-[10.5px] mb-1.5">Lampiran Dokumen</span>
                            <a
                                href="{{ Storage::url($transaction->attachment_path) }}"
                                target="_blank"
                                class="inline-flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 hover:bg-gray-100 rounded-lg text-emerald-700 font-semibold transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span>Lihat File Lampiran</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Kartu Rincian Breakdown (Jika Ada) --}}
            @if ($transaction->transactionDetails->isNotEmpty())
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 sm:p-6 space-y-3">
                    <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2.5 flex items-center justify-between">
                        <span>Rincian Breakdown Akun</span>
                        <span class="text-xs text-gray-500 font-normal">{{ $transaction->transactionDetails->count() }} Baris</span>
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-bold uppercase text-[11px] border-b border-gray-200">
                                <tr>
                                    <th class="py-2 px-3">Kode COA</th>
                                    <th class="py-2 px-3">Nama Akun</th>
                                    <th class="py-2 px-3">Keterangan Pos</th>
                                    <th class="py-2 px-3 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($transaction->transactionDetails as $detail)
                                    <tr class="hover:bg-gray-50/50">
                                        <td class="py-2 px-3 font-mono font-bold text-gray-900">{{ $detail->chartOfAccount?->code ?? '-' }}</td>
                                        <td class="py-2 px-3 font-semibold text-gray-900">{{ $detail->chartOfAccount?->name ?? '-' }}</td>
                                        <td class="py-2 px-3 text-gray-500">{{ $detail->description ?: '-' }}</td>
                                        <td class="py-2 px-3 text-right font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($detail->amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 border-t border-gray-200 font-bold">
                                <tr>
                                    <td colspan="3" class="py-2 px-3 text-right text-gray-700">Total Rincian:</td>
                                    <td class="py-2 px-3 text-right text-emerald-700">{{ \App\Helpers\MoneyHelper::rupiah($transaction->transactionDetails->sum('amount')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- Kolom Kanan: Jurnal Akuntansi Ganda (1 Col) --}}
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

                @if ($transaction->journalEntries->isNotEmpty())
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
                                    @foreach ($transaction->journalEntries as $entry)
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
                    <p class="text-xs text-gray-400 italic text-center py-4">Belum ada jurnal yang tercatat untuk transaksi ini.</p>
                @endif
            </div>

            {{-- Informasi Meta / Audit --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-xs space-y-2 text-gray-500">
                <div class="flex justify-between">
                    <span>Waktu Input:</span>
                    <span class="font-semibold text-gray-900">{{ $transaction->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Terakhir Update:</span>
                    <span class="font-semibold text-gray-900">{{ $transaction->updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
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
                <h3 class="text-lg font-bold text-gray-900">Batalkan &amp; Hapus Transaksi</h3>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin menghapus transaksi nomor <span class="font-bold text-gray-900 font-mono">{{ $transaction->number }}</span>?
                <br><br>
                <span class="text-rose-600 font-semibold">Peringatan:</span> Transaksi dan seluruh jurnal buku besar terkait akan dihapus secara permanen dari pembukuan.
            </p>
            <form action="{{ route('keuangan.cashbank.destroy', $transaction) }}" method="POST" class="flex items-center justify-end gap-2.5">
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
                    Ya, Hapus Transaksi
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
