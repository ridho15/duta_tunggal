@extends('layouts.app')

@section('title', 'Buku Besar per Akun – Duta Tunggal ERP')

@section('content')
<div class="space-y-6">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('keuangan.journal.index') }}" class="hover:text-gray-700">Jurnal Umum</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Buku Besar per Akun</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Buku Besar per Akun (General Ledger)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Analisis histori mutasi debit &amp; kredit serta kalkulasi saldo berjalan (running balance) per akun COA.</p>
        </div>
        <div>
            <a
                href="{{ route('keuangan.journal.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                &larr; Kembali ke Jurnal Umum
            </a>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
        <form method="GET" action="{{ route('keuangan.journal.ledger') }}" class="flex flex-col lg:flex-row lg:items-end justify-between gap-3.5">
            {{-- Grup Kiri: Pilih Akun COA & Cabang --}}
            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label for="coa_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Pilih Akun Chart of Account (COA) <span class="text-rose-500">*</span>
                    </label>
                    <x-searchable-select name="coa_id" :options="collect($chartOfAccounts)->map(fn ($acc) => ['id' => $acc->id, 'label' => $acc->code . ' - ' . $acc->name . ' (' . $acc->type . ')'])->values()" :value="($coaId) ?: collect($chartOfAccounts)->first()?->id" :required="true" size="sm" id="coa_id" />
                </div>

                <div>
                    <label for="cabang_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Cabang
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cabang) => ['id' => $cabang->id, 'label' => $cabang->nama])->values()" :value="$cabangId" placeholder="Semua Cabang" size="sm" id="cabang_id" />
                </div>
            </div>

            {{-- Grup Kanan: Rentang Tanggal Terpadu & Tombol Aksi (Anti-Overlap) --}}
            <div class="flex flex-wrap sm:flex-nowrap items-end gap-2 shrink-0">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">
                        Periode Tanggal
                    </label>
                    <div class="inline-flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2 py-0.5 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition">
                        <input
                            type="date"
                            name="start_date"
                            id="start_date"
                            value="{{ $startDate }}"
                            class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Dari Tanggal"
                        >
                        <span class="text-xs text-gray-400 px-1 font-medium">s/d</span>
                        <input
                            type="date"
                            name="end_date"
                            id="end_date"
                            value="{{ $endDate }}"
                            class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Sampai Tanggal"
                        >
                    </div>
                </div>

                <div class="self-end pb-0.5">
                    <button
                        type="submit"
                        class="px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Ringkasan Saldo Akun --}}
    @if ($selectedCoa)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-400 font-mono">[{{ $selectedCoa->code }}]</span>
                        <h2 class="text-xl font-bold text-gray-900">{{ $selectedCoa->name }}</h2>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                            {{ $selectedCoa->type }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Posisi Normal: <strong class="text-gray-900 uppercase">{{ $isDebitNormal ? 'Debit' : 'Kredit' }}</strong>
                        &bull; Periode: {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <span class="text-xs text-gray-400 uppercase font-semibold block">Saldo Akhir Periode:</span>
                    <span class="text-2xl font-bold {{ $endingBalance < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                        {{ \App\Helpers\MoneyHelper::rupiah($endingBalance) }}
                    </span>
                </div>
            </div>

            {{-- 4 Mini Stat Box --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <span class="text-[10.5px] uppercase font-bold text-gray-400 block">Saldo Awal</span>
                    <span class="text-sm font-bold text-gray-900 block mt-1">
                        {{ \App\Helpers\MoneyHelper::rupiah($openingBalance) }}
                    </span>
                </div>
                <div class="p-3 bg-emerald-50/50 rounded-xl border border-emerald-100">
                    <span class="text-[10.5px] uppercase font-bold text-emerald-700 block">Total Mutasi Debit</span>
                    <span class="text-sm font-bold text-emerald-800 block mt-1">
                        + {{ \App\Helpers\MoneyHelper::rupiah($totalDebitPeriod) }}
                    </span>
                </div>
                <div class="p-3 bg-rose-50/50 rounded-xl border border-rose-100">
                    <span class="text-[10.5px] uppercase font-bold text-rose-700 block">Total Mutasi Kredit</span>
                    <span class="text-sm font-bold text-rose-800 block mt-1">
                        - {{ \App\Helpers\MoneyHelper::rupiah($totalCreditPeriod) }}
                    </span>
                </div>
                <div class="p-3 bg-blue-50/50 rounded-xl border border-blue-100">
                    <span class="text-[10.5px] uppercase font-bold text-blue-700 block">Jumlah Mutasi</span>
                    <span class="text-sm font-bold text-blue-900 block mt-1">
                        {{ $ledgerEntries->count() }} Baris Transaksi
                    </span>
                </div>
            </div>
        </div>

        {{-- Tabel Mutasi Buku Pembantu --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Histori Mutasi &amp; Saldo Berjalan</h3>
                <span class="text-xs text-gray-500 font-mono">{{ $selectedCoa->code }}</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700">
                    <thead class="bg-gray-50/80 text-gray-500 font-semibold uppercase text-[11px] border-b border-gray-100 tracking-wider">
                        <tr>
                            <th class="py-3 px-4 w-28">Tanggal</th>
                            <th class="py-3 px-4 w-36">No. Referensi</th>
                            <th class="py-3 px-4">Keterangan / Memo</th>
                            <th class="py-3 px-4 w-28">Cabang</th>
                            <th class="py-3 px-4 w-32 text-right">Debit (Rp)</th>
                            <th class="py-3 px-4 w-32 text-right">Kredit (Rp)</th>
                            <th class="py-3 px-4 w-36 text-right">Saldo Berjalan (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-sans">
                        {{-- Baris Saldo Awal / Bawaan --}}
                        <tr class="bg-amber-50/30 font-semibold text-gray-900">
                            <td class="py-2.5 px-4 text-gray-500 font-mono">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</td>
                            <td class="py-2.5 px-4 font-mono text-gray-400">
                                {{ method_exists($ledgerEntries, 'currentPage') && $ledgerEntries->currentPage() > 1 ? 'SALDO-BAWAAN' : 'SALDO-AWAL' }}
                            </td>
                            <td class="py-2.5 px-4 text-gray-700">
                                @if (method_exists($ledgerEntries, 'currentPage') && $ledgerEntries->currentPage() > 1)
                                    Saldo Bawaan dari Halaman Sebelumnya (Hal. {{ $ledgerEntries->currentPage() }})
                                @else
                                    Saldo Awal per {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-gray-400">-</td>
                            <td class="py-2.5 px-4 text-right font-mono text-gray-400">-</td>
                            <td class="py-2.5 px-4 text-right font-mono text-gray-400">-</td>
                            <td class="py-2.5 px-4 text-right font-mono font-bold text-gray-900">
                                {{ \App\Helpers\MoneyHelper::rupiah($pageStartingBalance ?? $openingBalance) }}
                            </td>
                        </tr>

                        @forelse ($ledgerEntries as $entry)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-2.5 px-4 whitespace-nowrap text-gray-600 font-mono">
                                    {{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}
                                </td>
                                <td class="py-2.5 px-4 whitespace-nowrap font-mono font-bold text-gray-900">
                                    <a href="{{ route('keuangan.journal.show', $entry->reference) }}" class="text-emerald-700 hover:underline">
                                        {{ $entry->reference }}
                                    </a>
                                </td>
                                <td class="py-2.5 px-4 text-gray-700">
                                    {{ $entry->description ?: '-' }}
                                </td>
                                <td class="py-2.5 px-4 whitespace-nowrap text-gray-500">
                                    {{ $entry->cabang?->nama ?? 'Pusat' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                                    {{ (float)$entry->debit > 0 ? number_format($entry->debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-semibold text-gray-900 whitespace-nowrap">
                                    {{ (float)$entry->credit > 0 ? number_format($entry->credit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold whitespace-nowrap {{ $entry->running_balance < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                    {{ \App\Helpers\MoneyHelper::rupiah($entry->running_balance) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-400 italic">
                                    Tidak ada mutasi transaksi pada periode {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold border-t-2 border-gray-200">
                        <tr>
                            <td colspan="4" class="py-3 px-4 text-right text-gray-700 uppercase text-[11px]">
                                Total Mutasi &amp; Saldo Akhir:
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-800">
                                {{ number_format($totalDebitPeriod, 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-rose-800">
                                {{ number_format($totalCreditPeriod, 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-sm {{ $endingBalance < 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                                {{ \App\Helpers\MoneyHelper::rupiah($endingBalance) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($ledgerEntries && method_exists($ledgerEntries, 'hasPages') && $ledgerEntries->hasPages())
                <div class="px-4 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $ledgerEntries->links() }}
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
