@extends('layouts.app')

@section('title', 'Neraca Saldo (Trial Balance) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="trialBalanceFilter()">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Laporan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Keuangan</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Neraca Saldo</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Neraca Saldo (Trial Balance)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Daftar saldo awal, mutasi debit &amp; kredit, serta saldo akhir seluruh akun perkiraan per periode pembukuan.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a
                href="{{ route('laporan.financial.trial-balance.pdf', request()->query()) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak PDF (Landscape)
            </a>
            <a
                href="{{ route('keuangan.journal.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-lg shadow-sm transition"
            >
                Buka Jurnal Umum &rarr;
            </a>
        </div>
    </div>

    {{-- Tabs Navigasi Laporan Keuangan --}}
    @include('laporan.financial.partials.tabs', ['activeTab' => 'neraca-saldo'])

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
        <form method="GET" action="{{ route('laporan.financial.trial-balance') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
                {{-- Periode Tanggal Mulai --}}
                <div class="lg:col-span-3">
                    <label for="start_date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Dari Tanggal <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        id="start_date"
                        x-model="startDate"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 bg-white"
                    >
                </div>

                {{-- Periode Tanggal Selesai --}}
                <div class="lg:col-span-3">
                    <label for="end_date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Sampai Tanggal <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="end_date"
                        id="end_date"
                        x-model="endDate"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 bg-white"
                    >
                </div>

                {{-- Filter Cabang --}}
                <div class="lg:col-span-4">
                    <label for="cabang_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Entitas Cabang
                    </label>
                    <select
                        name="cabang_id"
                        id="cabang_id"
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 bg-white"
                    >
                        <option value="">Semua Cabang (Konsolidasian)</option>
                        @foreach ($cabangs as $cb)
                            <option value="{{ $cb->id }}" {{ $cabangId == $cb->id ? 'selected' : '' }}>
                                {{ $cb->nama }} ({{ $cb->kode }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Tombol Terapkan --}}
                <div class="lg:col-span-2 flex items-center gap-2">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Tampilkan
                    </button>
                    <a
                        href="{{ route('laporan.financial.trial-balance') }}"
                        title="Reset Filter"
                        class="px-2.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold transition"
                    >
                        Reset
                    </a>
                </div>
            </div>

            {{-- Preset Cepat & Opsi Nol --}}
            <div class="flex items-center justify-between flex-wrap gap-3 pt-3 border-t border-gray-100 text-xs">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-gray-400 font-semibold text-[11px] uppercase tracking-wider">Preset Cepat:</span>
                    <button type="button" @click="setThisMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Bulan Ini</button>
                    <button type="button" @click="setLastMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Bulan Lalu</button>
                    <button type="button" @click="setThisQuarter()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Triwulan Ini</button>
                    <button type="button" @click="setYearToDate()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Tahun Ini (YTD)</button>
                </div>

                <div class="flex items-center gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none text-gray-700 font-medium">
                        <input
                            type="checkbox"
                            name="show_zero_balance"
                            value="1"
                            {{ $showZero ? 'checked' : '' }}
                            onchange="this.form.submit()"
                            class="rounded border-gray-300 text-[#15803d] focus:ring-emerald-500 text-xs"
                        >
                        <span>Tampilkan Akun Bersaldo Nol</span>
                    </label>
                </div>
            </div>
        </form>
    </div>

    @php
        $totals = $reportData['grand_totals'];
        $movementDiff = abs($totals['period_debit'] - $totals['period_credit']);
        $isMovementBalanced = $movementDiff < 0.01;
    @endphp

    {{-- KPI Cards Neraca Saldo --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Saldo Awal --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Total Saldo Awal</span>
                <span class="p-1.5 bg-gray-100 text-gray-600 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($totals['beginning_balance'], 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Posisi sebelum {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</p>
        </div>

        {{-- Card 2: Mutasi Debit --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Mutasi Debit Periode</span>
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-emerald-700">
                Rp {{ number_format($totals['period_debit'], 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Total posting Debit dalam periode</p>
        </div>

        {{-- Card 3: Mutasi Kredit --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Mutasi Kredit Periode</span>
                <span class="p-1.5 bg-blue-50 text-blue-700 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-blue-700">
                Rp {{ number_format($totals['period_credit'], 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Total posting Kredit dalam periode</p>
        </div>

        {{-- Card 4: Keseimbangan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Integritas Mutasi</span>
                <span class="p-1.5 {{ $isMovementBalanced ? 'bg-emerald-50 text-[#15803d]' : 'bg-rose-50 text-rose-700' }} rounded-md">
                    @if ($isMovementBalanced)
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    @endif
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono {{ $isMovementBalanced ? 'text-emerald-700' : 'text-rose-600' }}">
                {{ $isMovementBalanced ? 'SEIMBANG (0.00)' : 'SELISIH: Rp ' . number_format($movementDiff, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Debit = Kredit: {{ $isMovementBalanced ? 'Valid & Sinkron' : 'Ada Ketidakseimbangan' }}</p>
        </div>
    </div>

    {{-- Tabel Utama Neraca Saldo --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-200 bg-gray-50/75 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                    Lembar Neraca Saldo Per {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Entitas: <span class="font-semibold text-gray-800">{{ $selectedCabang ? $selectedCabang->nama : 'Konsolidasian (Semua Cabang)' }}</span>
                    &bull; Total {{ count($reportData['rows']) }} akun aktif
                </p>
            </div>
            <div class="text-xs text-gray-400">
                Mata Uang: <span class="font-bold text-gray-700 font-mono">IDR (Rp)</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-100/75 text-gray-600 uppercase font-semibold border-b border-gray-200 text-[11px]">
                    <tr>
                        <th class="py-3 px-3 w-12 text-center">#</th>
                        <th class="py-3 px-3 w-28">Kode Akun</th>
                        <th class="py-3 px-3">Nama Rekening Perkiraan (COA)</th>
                        <th class="py-3 px-2 w-24 text-center">Kategori</th>
                        <th class="py-3 px-3 text-right w-36">Saldo Awal</th>
                        <th class="py-3 px-3 text-right w-36">Mutasi Debit</th>
                        <th class="py-3 px-3 text-right w-36">Mutasi Kredit</th>
                        <th class="py-3 px-3 text-right w-36">Saldo Akhir</th>
                        <th class="py-3 px-2 text-center w-20">Buku Besar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-normal">
                    @forelse ($reportData['rows'] as $index => $row)
                        @php
                            $isParent = $row->is_parent;
                            $typeColor = match($row->type) {
                                'Asset' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Liability' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'Equity' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'Revenue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'Expense' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-gray-50 text-gray-700 border-gray-200'
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-colors {{ $isParent ? 'bg-gray-50/50 font-bold' : '' }}">
                            <td class="py-2.5 px-3 text-center text-gray-400 font-mono text-[11px]">
                                {{ $index + 1 }}
                            </td>
                            <td class="py-2.5 px-3 font-mono font-semibold {{ $isParent ? 'text-gray-900 font-bold' : 'text-gray-700 pl-4' }}">
                                {{ $row->code }}
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="{{ $isParent ? 'text-gray-900 font-bold' : 'text-gray-800 pl-3' }}">
                                    @if (!$isParent && $row->parent_id)
                                        <span class="text-gray-300 mr-1">&rdquor;</span>
                                    @endif
                                    {{ $row->name }}
                                </div>
                            </td>
                            <td class="py-2.5 px-2 text-center">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border {{ $typeColor }}">
                                    {{ $row->type }} ({{ $row->normal_balance }})
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono {{ abs($row->beginning_balance) > 0.001 ? 'text-gray-900' : 'text-gray-300' }}">
                                {{ number_format($row->beginning_balance, 2, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono {{ $row->period_debit > 0 ? 'text-emerald-700 font-semibold' : 'text-gray-300' }}">
                                {{ $row->period_debit > 0 ? number_format($row->period_debit, 2, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono {{ $row->period_credit > 0 ? 'text-blue-700 font-semibold' : 'text-gray-300' }}">
                                {{ $row->period_credit > 0 ? number_format($row->period_credit, 2, ',', '.') : '-' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-semibold {{ abs($row->ending_balance) > 0.001 ? 'text-gray-900' : 'text-gray-300' }}">
                                {{ number_format($row->ending_balance, 2, ',', '.') }}
                            </td>
                            <td class="py-2.5 px-2 text-center">
                                <a
                                    href="{{ route('keuangan.journal.ledger', ['coa_id' => $row->id, 'start_date' => $startDate, 'end_date' => $endDate, 'cabang_id' => $cabangId]) }}"
                                    title="Lihat Buku Besar {{ $row->name }}"
                                    class="inline-flex items-center text-[10px] font-semibold text-[#15803d] hover:text-[#166534] bg-emerald-50 hover:bg-emerald-100 px-2 py-0.5 rounded border border-emerald-200 transition"
                                >
                                    Ledger
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-500">
                                <div class="inline-block p-3 rounded-full bg-gray-100 text-gray-400 mb-2">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <p class="text-sm font-semibold text-gray-700">Tidak ada saldo atau mutasi akun pada periode terpilih.</p>
                                <p class="text-xs text-gray-400 mt-1">Coba centang opsi "Tampilkan Akun Bersaldo Nol" atau sesuaikan rentang tanggal.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-100/95 font-bold border-t-2 border-gray-300 text-xs">
                    <tr>
                        <td colspan="4" class="py-3 px-3 text-right uppercase tracking-wider text-gray-700">
                            GRAND TOTAL MUTASI:
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-gray-900">
                            {{ number_format($totals['beginning_balance'], 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-emerald-800 text-sm">
                            {{ number_format($totals['period_debit'], 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-blue-800 text-sm">
                            {{ number_format($totals['period_credit'], 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-3 text-right font-mono text-gray-900">
                            {{ number_format($totals['ending_balance'], 2, ',', '.') }}
                        </td>
                        <td class="py-3 px-2 text-center">
                            @if ($isMovementBalanced)
                                <span class="text-[10px] text-emerald-700 font-bold uppercase">BALANCED</span>
                            @else
                                <span class="text-[10px] text-rose-700 font-bold uppercase">UNBALANCED</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
function trialBalanceFilter() {
    return {
        startDate: '{{ $startDate }}',
        endDate: '{{ $endDate }}',
        setThisMonth() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            this.startDate = firstDay.toISOString().split('T')[0];
            this.endDate = lastDay.toISOString().split('T')[0];
        },
        setLastMonth() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
            this.startDate = firstDay.toISOString().split('T')[0];
            this.endDate = lastDay.toISOString().split('T')[0];
        },
        setThisQuarter() {
            const now = new Date();
            const q = Math.floor(now.getMonth() / 3);
            const firstDay = new Date(now.getFullYear(), q * 3, 1);
            const lastDay = new Date(now.getFullYear(), q * 3 + 3, 0);
            this.startDate = firstDay.toISOString().split('T')[0];
            this.endDate = lastDay.toISOString().split('T')[0];
        },
        setYearToDate() {
            const now = new Date();
            const firstDay = new Date(now.getFullYear(), 0, 1);
            this.startDate = firstDay.toISOString().split('T')[0];
            this.endDate = now.toISOString().split('T')[0];
        }
    }
}
</script>
@endsection
