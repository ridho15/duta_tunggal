@extends('layouts.app')

@section('title', 'Jurnal Umum & Buku Besar – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    deleteModalOpen: false,
    deleteActionUrl: '',
    deleteReference: '',
    confirmDelete(url, ref) {
        this.deleteActionUrl = url;
        this.deleteReference = ref;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Buku Besar &amp; Jurnal</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Jurnal Umum &amp; Buku Besar</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftar seluruh jurnal pembukuan ganda (double-entry) otomatis dari modul operasional dan jurnal penyesuaian manual.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('keuangan.journal.ledger') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
                Buku Besar per Akun
            </a>
            <a
                href="{{ route('keuangan.journal.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d]/30"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Jurnal Penyesuaian Manual
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Mutasi Debit --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Debit (Bulan Ini)</span>
                <span class="p-2 bg-emerald-50 text-[#15803d] rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalDebitMonth) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Total aliran pembukuan sisi Debit</p>
        </div>

        {{-- Total Mutasi Kredit --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Kredit (Bulan Ini)</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalCreditMonth) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Total aliran pembukuan sisi Kredit</p>
        </div>

        {{-- Total Voucher Transaksi --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Voucher Transaksi</span>
                <span class="p-2 bg-purple-50 text-purple-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ number_format($totalVouchersMonth) }}</span>
                <span class="text-xs text-gray-500">Voucher</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Jumlah voucher jurnal terposting</p>
        </div>

        {{-- Status Balance --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Integritas Jurnal</span>
                <span class="p-2 {{ $balanceDifference < 0.01 ? 'bg-emerald-50 text-[#15803d]' : 'bg-rose-50 text-rose-600' }} rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold {{ $balanceDifference < 0.01 ? 'text-emerald-700' : 'text-rose-600' }}">
                    {{ $balanceDifference < 0.01 ? '100% Balanced' : 'Tidak Seimbang' }}
                </span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">
                {{ $balanceDifference < 0.01 ? 'Debit dan Kredit seimbang sempurna' : 'Selisih: ' . \App\Helpers\MoneyHelper::rupiah($balanceDifference) }}
            </p>
        </div>
    </div>

    {{-- Main Container Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Status / Module Tabs --}}
        <div class="border-b border-gray-100 px-4 sm:px-6 pt-3 bg-gray-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-3 text-xs sm:text-sm font-semibold scrollbar-none">
                @php
                    $tabs = [
                        'semua' => ['label' => 'Semua Modul', 'count' => $counts['semua']],
                        'pembelian' => ['label' => 'Pembelian', 'count' => $counts['pembelian']],
                        'penjualan' => ['label' => 'Penjualan', 'count' => $counts['penjualan']],
                        'kasbank' => ['label' => 'Kas & Bank', 'count' => $counts['kasbank']],
                        'transfer' => ['label' => 'Transfer', 'count' => $counts['transfer']],
                        'pembayaran' => ['label' => 'Pembayaran', 'count' => $counts['pembayaran']],
                        'persediaan' => ['label' => 'Persediaan', 'count' => $counts['persediaan']],
                        'manual' => ['label' => 'Manual / Penyesuaian', 'count' => $counts['manual']],
                    ];
                @endphp

                @foreach ($tabs as $key => $meta)
                    @php
                        $isActive = ($tab === $key);
                    @endphp
                    <a
                        href="{{ route('keuangan.journal.index', array_merge(request()->except('page'), ['tab' => $key])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition whitespace-nowrap {{ $isActive ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <span>{{ $meta['label'] }}</span>
                        <span class="text-xs px-1.5 py-0.2 rounded-full {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                            {{ number_format($meta['count']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        <div class="p-4 sm:px-6 border-b border-gray-100 bg-white">
            <form method="GET" action="{{ route('keuangan.journal.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">

                {{-- Pencarian Kata Kunci --}}
                <div class="lg:col-span-4 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari no. referensi, keterangan, akun..."
                        class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900 placeholder-gray-400"
                    >
                </div>

                {{-- Filter Akun COA --}}
                <div class="lg:col-span-3">
                    <x-searchable-select name="coa_id" :options="collect($chartOfAccounts)->map(fn ($acc) => ['id' => $acc->id, 'label' => $acc->code . ' - ' . $acc->name])->values()" :value="$coaId" placeholder="-- Semua Akun COA --" size="sm" :auto-submit="true" />
                </div>

                {{-- Tanggal Dari --}}
                <div class="lg:col-span-2">
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        title="Dari Tanggal"
                    >
                </div>

                {{-- Tanggal Sampai --}}
                <div class="lg:col-span-2">
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900"
                        title="Sampai Tanggal"
                    >
                </div>

                {{-- Tombol Filter & Reset --}}
                <div class="lg:col-span-1 flex items-center gap-1.5">
                    <button
                        type="submit"
                        class="flex-1 px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                        title="Terapkan Filter"
                    >
                        Filter
                    </button>
                    @if ($search || $startDate || $endDate || $coaId || $cabangId)
                        <a
                            href="{{ route('keuangan.journal.index', ['tab' => $tab]) }}"
                            class="p-2 text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                            title="Reset Filter"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Daftar Voucher Jurnal Berkelompok --}}
        <div class="divide-y divide-gray-200">
            @forelse ($groupedVouchers as $voucher)
                @php
                    $lines = $journalLines->get($voucher->reference, collect());
                    $isManual = empty($voucher->source_type) || in_array($voucher->journal_type, ['manual', 'adjustment', 'reversal']);
                    $isBalanced = abs($voucher->total_debit - $voucher->total_credit) < 0.01;
                @endphp
                <div class="p-4 sm:p-5 hover:bg-gray-50/50 transition">
                    {{-- Header Voucher Transaksi --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 pb-3 border-b border-gray-100">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="font-mono font-bold text-sm text-gray-900">
                                <a href="{{ route('keuangan.journal.show', $voucher->reference) }}" class="text-emerald-700 hover:underline">
                                    {{ $voucher->reference }}
                                </a>
                            </span>
                            <span class="text-gray-300">&bull;</span>
                            <span class="text-xs text-gray-600">
                                {{ \Carbon\Carbon::parse($voucher->date)->translatedFormat('d M Y') }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-gray-100 text-gray-700 uppercase">
                                {{ str_replace('_', ' ', $voucher->journal_type) }}
                            </span>
                            @if ($isBalanced)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Balanced
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    Unbalanced
                                </span>
                            @endif
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 font-semibold mr-2">
                                Total: <strong class="text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($voucher->total_debit) }}</strong>
                            </span>
                            <a
                                href="{{ route('keuangan.journal.show', $voucher->reference) }}"
                                class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                title="Lihat Detail Voucher"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a
                                href="{{ route('keuangan.journal.pdf', $voucher->reference) }}"
                                target="_blank"
                                class="p-1.5 text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                title="Cetak Voucher PDF"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            </a>
                            @if ($isManual)
                                <button
                                    type="button"
                                    @click="confirmDelete('{{ route('keuangan.journal.destroy', $voucher->reference) }}', '{{ $voucher->reference }}')"
                                    class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                    title="Hapus Jurnal Manual"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Deskripsi Umum Voucher --}}
                    @if ($voucher->description)
                        <div class="text-xs text-gray-600 mt-2 italic">
                            {{ $voucher->description }}
                        </div>
                    @endif

                    {{-- Sub-tabel Baris Jurnal (Double-Entry Legs) --}}
                    <div class="mt-3 overflow-x-auto rounded-xl border border-gray-100 bg-white">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50/80 text-gray-500 font-semibold uppercase text-[11px] border-b border-gray-100">
                                <tr>
                                    <th class="py-2.5 px-3 w-1/4">Kode &amp; Nama Akun COA</th>
                                    <th class="py-2.5 px-3">Keterangan Baris</th>
                                    <th class="py-2.5 px-3 w-36 text-right">Debit (Rp)</th>
                                    <th class="py-2.5 px-3 w-36 text-right">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($lines as $line)
                                    @php
                                        $isCredit = ((float)$line->credit > 0);
                                    @endphp
                                    <tr class="hover:bg-gray-50/40">
                                        <td class="py-2 px-3">
                                            <div class="font-semibold text-gray-900 {{ $isCredit ? 'pl-4 text-gray-800' : '' }}">
                                                {{ $line->coa?->name ?? 'Akun #' . $line->coa_id }}
                                            </div>
                                            <div class="font-mono text-[10.5px] text-gray-400 {{ $isCredit ? 'pl-4' : '' }}">
                                                {{ $line->coa?->code }} &bull; {{ $line->coa?->type }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-gray-500">
                                            {{ $line->description ?: '-' }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 whitespace-nowrap">
                                            {{ (float)$line->debit > 0 ? number_format($line->debit, 2, ',', '.') : '-' }}
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono font-bold text-gray-900 whitespace-nowrap">
                                            {{ (float)$line->credit > 0 ? number_format($line->credit, 2, ',', '.') : '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <div class="py-12 text-center text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm font-semibold text-gray-600">Tidak ada jurnal ditemukan</p>
                        <p class="text-xs text-gray-400 mt-0.5">Gunakan filter di atas atau klik tombol "+ Jurnal Penyesuaian Manual".</p>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($groupedVouchers && $groupedVouchers->hasPages())
            <div class="px-4 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                {{ $groupedVouchers->links('components.pagination-clean') }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus Jurnal Manual --}}
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
                <h3 class="text-lg font-bold text-gray-900">Batalkan / Hapus Jurnal Manual</h3>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin membatalkan dan menghapus voucher jurnal <span class="font-bold text-gray-900 font-mono" x-text="deleteReference"></span>?
                <br><br>
                <span class="text-rose-600 font-semibold">Peringatan:</span> Baris jurnal debit dan kredit pada voucher ini akan dihapus secara permanen dari Buku Besar.
            </p>
            <form :action="deleteActionUrl" method="POST" class="flex items-center justify-end gap-2.5">
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
                    Ya, Hapus Jurnal
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
