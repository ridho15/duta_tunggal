@extends('layouts.app')

@section('title', 'Laporan Neraca (Balance Sheet) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="balanceSheetFilter()">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Laporan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Keuangan</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Neraca</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Laporan Neraca (Balance Sheet)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Laporan posisi keuangan entitas per tanggal buku (Aset = Liabilitas + Ekuitas).</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a
                href="{{ route('laporan.financial.balance-sheet.pdf', request()->query()) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak PDF (Portrait)
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
    @include('laporan.financial.partials.tabs', ['activeTab' => 'neraca'])

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
        <form method="GET" action="{{ route('laporan.financial.balance-sheet') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
                {{-- Tanggal Posisi Neraca (As of Date) --}}
                <div class="lg:col-span-4">
                    <label for="as_of_date" class="block text-xs font-semibold text-gray-700 mb-1">
                        Posisi Per Tanggal (As of Date) <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="as_of_date"
                        id="as_of_date"
                        x-model="asOfDate"
                        required
                        class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 text-gray-900 bg-white"
                    >
                </div>

                {{-- Filter Cabang --}}
                <div class="lg:col-span-5">
                    <label for="cabang_id" class="block text-xs font-semibold text-gray-700 mb-1">
                        Entitas Cabang
                    </label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->nama . ' (' . $cb->kode . ')'])->values()" :value="$cabangId" placeholder="Semua Cabang (Konsolidasian)" size="sm" id="cabang_id" />
                </div>

                {{-- Tombol Terapkan --}}
                <div class="lg:col-span-3 flex items-center gap-2">
                    <button
                        type="submit"
                        class="w-full inline-flex justify-center items-center gap-1.5 px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                        Tampilkan Neraca
                    </button>
                    <a
                        href="{{ route('laporan.financial.balance-sheet') }}"
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
                    <span class="text-gray-400 font-semibold text-[11px] uppercase tracking-wider">Pilih Tanggal Cepat:</span>
                    <button type="button" @click="setToday()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Hari Ini</button>
                    <button type="button" @click="setEndOfCurrentMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Akhir Bulan Ini</button>
                    <button type="button" @click="setEndOfLastMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Akhir Bulan Lalu</button>
                    <button type="button" @click="setEndOfLastYear()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Akhir Tahun Lalu</button>
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
        $totAssets = $reportData['total_assets'];
        $totLiab = $reportData['total_liabilities'];
        $totEq = $reportData['total_equity'];
        $totLiabEq = $reportData['total_liabilities_and_equity'];
        $diff = abs((float) $reportData['difference']);
        $isBalanced = $diff < 0.01;
    @endphp

    {{-- KPI Cards Neraca --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Aset --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Total Aset (Aktiva)</span>
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($totAssets, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Aset Lancar &amp; Aset Tetap Bersih</p>
        </div>

        {{-- Card 2: Total Liabilitas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Total Liabilitas (Utang)</span>
                <span class="p-1.5 bg-amber-50 text-amber-700 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($totLiab, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Kewajiban jangka pendek &amp; panjang</p>
        </div>

        {{-- Card 3: Total Ekuitas Modal --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Total Ekuitas (Modal)</span>
                <span class="p-1.5 bg-purple-50 text-purple-700 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($totEq, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Termasuk Laba Ditahan &amp; Berjalan</p>
        </div>

        {{-- Card 4: Persamaan Keseimbangan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Status Persamaan Neraca</span>
                <span class="p-1.5 {{ $isBalanced ? 'bg-emerald-50 text-[#15803d]' : 'bg-rose-50 text-rose-700' }} rounded-md">
                    @if ($isBalanced)
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    @endif
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono {{ $isBalanced ? 'text-emerald-700' : 'text-rose-600' }}">
                {{ $isBalanced ? 'SEIMBANG (0.00)' : 'SELISIH: Rp ' . number_format($diff, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Aset = Liabilitas + Ekuitas</p>
        </div>
    </div>

    {{-- Layout 2 Kolom Neraca: Kiri ASET vs Kanan LIABILITAS & EKUITAS --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        {{-- ==================== KOLOM KIRI: ASET (AKTIVA) ==================== --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4">
            <div class="p-4 border-b border-gray-200 bg-emerald-50/60 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-[#15803d]"></span>
                    <h3 class="text-sm font-bold text-emerald-950 uppercase tracking-wider">ASET (AKTIVA)</h3>
                </div>
                <span class="text-xs font-mono font-bold text-emerald-800">Per {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</span>
            </div>

            <div class="p-4 space-y-6">
                {{-- ASET LANCAR --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between pb-1 border-b border-gray-100 font-bold text-xs uppercase tracking-wider text-gray-800">
                        <span>Aset Lancar (Current Assets)</span>
                        <span>Nominal (Rp)</span>
                    </div>
                    <div class="divide-y divide-gray-50 text-xs">
                        @forelse ($reportData['current_assets']['accounts'] as $acc)
                            <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-gray-400 text-[11px]">{{ $acc->code }}</span>
                                    <span class="text-gray-800">{{ $acc->name }}</span>
                                </div>
                                <span class="font-mono font-semibold text-gray-900">{{ number_format($acc->balance, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-center text-gray-400 italic text-xs">Tidak ada akun aset lancar</div>
                        @endforelse
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-gray-50 p-2 rounded text-gray-900">
                        <span>TOTAL ASET LANCAR</span>
                        <span class="font-mono">Rp {{ number_format($reportData['current_assets']['total'], 2, ',', '.') }}</span>
                    </div>
                </div>

                {{-- ASET TETAP & KONTRA ASET --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between pb-1 border-b border-gray-100 font-bold text-xs uppercase tracking-wider text-gray-800">
                        <span>Aset Tetap (Fixed Assets)</span>
                        <span>Nominal (Rp)</span>
                    </div>
                    <div class="divide-y divide-gray-50 text-xs">
                        @forelse ($reportData['fixed_assets']['accounts'] as $acc)
                            <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-gray-400 text-[11px]">{{ $acc->code }}</span>
                                    <span class="text-gray-800">{{ $acc->name }}</span>
                                </div>
                                <span class="font-mono font-semibold text-gray-900">{{ number_format($acc->balance, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-center text-gray-400 italic text-xs">Tidak ada akun aset tetap</div>
                        @endforelse

                        {{-- Kontra Aset (Akumulasi Penyusutan) --}}
                        @foreach ($reportData['contra_assets']['accounts'] as $acc)
                            <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded text-rose-700">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-rose-400 text-[11px]">{{ $acc->code }}</span>
                                    <span>{{ $acc->name }} (Penyusutan)</span>
                                </div>
                                <span class="font-mono font-semibold">({{ number_format(abs($acc->balance), 2, ',', '.') }})</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-gray-50 p-2 rounded text-gray-900">
                        <span>TOTAL ASET TETAP BERSIH</span>
                        <span class="font-mono">Rp {{ number_format($reportData['fixed_assets']['total'] - $reportData['contra_assets']['total'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- TOTAL ASET --}}
            <div class="p-4 bg-emerald-100/75 border-t-2 border-[#15803d] flex items-center justify-between font-bold text-sm text-emerald-950">
                <span class="uppercase tracking-wider">TOTAL ASET (AKTIVA)</span>
                <span class="font-mono text-base font-extrabold text-[#15803d]">
                    Rp {{ number_format($totAssets, 2, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- ==================== KOLOM KANAN: LIABILITAS & EKUITAS (PASIVA) ==================== --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden space-y-4">
            <div class="p-4 border-b border-gray-200 bg-blue-50/60 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-600"></span>
                    <h3 class="text-sm font-bold text-blue-950 uppercase tracking-wider">LIABILITAS &amp; EKUITAS (PASIVA)</h3>
                </div>
                <span class="text-xs font-mono font-bold text-blue-800">Per {{ \Carbon\Carbon::parse($asOfDate)->format('d M Y') }}</span>
            </div>

            <div class="p-4 space-y-6">
                {{-- LIABILITAS JANGKA PENDEK --}}
                <div class="space-y-2">
                    <div class="flex items-center justify-between pb-1 border-b border-gray-100 font-bold text-xs uppercase tracking-wider text-gray-800">
                        <span>Liabilitas Jangka Pendek (Current Liabilities)</span>
                        <span>Nominal (Rp)</span>
                    </div>
                    <div class="divide-y divide-gray-50 text-xs">
                        @forelse ($reportData['current_liabilities']['accounts'] as $acc)
                            <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-gray-400 text-[11px]">{{ $acc->code }}</span>
                                    <span class="text-gray-800">{{ $acc->name }}</span>
                                </div>
                                <span class="font-mono font-semibold text-gray-900">{{ number_format($acc->balance, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-center text-gray-400 italic text-xs">Tidak ada utang lancar</div>
                        @endforelse
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-gray-50 p-2 rounded text-gray-900">
                        <span>TOTAL LIABILITAS JANGKA PENDEK</span>
                        <span class="font-mono">Rp {{ number_format($reportData['current_liabilities']['total'], 2, ',', '.') }}</span>
                    </div>
                </div>

                {{-- LIABILITAS JANGKA PANJANG --}}
                @if ($reportData['long_term_liabilities']['total'] > 0 || count($reportData['long_term_liabilities']['accounts']) > 0)
                    <div class="space-y-2">
                        <div class="flex items-center justify-between pb-1 border-b border-gray-100 font-bold text-xs uppercase tracking-wider text-gray-800">
                            <span>Liabilitas Jangka Panjang (Long-Term)</span>
                            <span>Nominal (Rp)</span>
                        </div>
                        <div class="divide-y divide-gray-50 text-xs">
                            @foreach ($reportData['long_term_liabilities']['accounts'] as $acc)
                                <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono text-gray-400 text-[11px]">{{ $acc->code }}</span>
                                        <span class="text-gray-800">{{ $acc->name }}</span>
                                    </div>
                                    <span class="font-mono font-semibold text-gray-900">{{ number_format($acc->balance, 2, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-gray-50 p-2 rounded text-gray-900">
                            <span>TOTAL LIABILITAS JANGKA PANJANG</span>
                            <span class="font-mono">Rp {{ number_format($reportData['long_term_liabilities']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                @endif

                {{-- SUBTOTAL LIABILITAS --}}
                <div class="flex items-center justify-between p-2 rounded bg-amber-50 font-bold text-xs text-amber-950 border border-amber-200">
                    <span>TOTAL LIABILITAS (KEWAJIBAN)</span>
                    <span class="font-mono text-sm">Rp {{ number_format($totLiab, 2, ',', '.') }}</span>
                </div>

                {{-- EKUITAS (EQUITY) --}}
                <div class="space-y-2 pt-2 border-t border-gray-200">
                    <div class="flex items-center justify-between pb-1 border-b border-gray-100 font-bold text-xs uppercase tracking-wider text-gray-800">
                        <span>Ekuitas &amp; Modal Pemilik</span>
                        <span>Nominal (Rp)</span>
                    </div>
                    <div class="divide-y divide-gray-50 text-xs">
                        @forelse ($reportData['equity']['accounts'] as $acc)
                            <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-mono text-gray-400 text-[11px]">{{ $acc->code }}</span>
                                    <span class="text-gray-800">{{ $acc->name }}</span>
                                </div>
                                <span class="font-mono font-semibold text-gray-900">{{ number_format($acc->balance, 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="py-2 text-center text-gray-400 italic text-xs">Tidak ada akun modal terdaftar</div>
                        @endforelse

                        {{-- Laba Ditahan / Laba Periode Berjalan --}}
                        <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-1 rounded font-medium {{ $reportData['retained_earnings'] >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-gray-400 text-[11px]">3999</span>
                                <span>Laba Ditahan &amp; Laba Tahun Berjalan</span>
                            </div>
                            <span class="font-mono font-bold">
                                {{ number_format($reportData['retained_earnings'], 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-2 rounded bg-purple-50 font-bold text-xs text-purple-950 border border-purple-200">
                        <span>TOTAL EKUITAS (MODAL)</span>
                        <span class="font-mono text-sm">Rp {{ number_format($totEq, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- TOTAL LIABILITAS & EKUITAS --}}
            <div class="p-4 bg-blue-100/75 border-t-2 border-blue-600 flex items-center justify-between font-bold text-sm text-blue-950">
                <span class="uppercase tracking-wider">TOTAL LIABILITAS &amp; EKUITAS</span>
                <span class="font-mono text-base font-extrabold text-blue-700">
                    Rp {{ number_format($totLiabEq, 2, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
</div>

<script>
function balanceSheetFilter() {
    return {
        asOfDate: '{{ $asOfDate }}',
        setToday() {
            this.asOfDate = new Date().toISOString().split('T')[0];
        },
        setEndOfCurrentMonth() {
            const now = new Date();
            const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            this.asOfDate = lastDay.toISOString().split('T')[0];
        },
        setEndOfLastMonth() {
            const now = new Date();
            const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
            this.asOfDate = lastDay.toISOString().split('T')[0];
        },
        setEndOfLastYear() {
            const now = new Date();
            const lastDay = new Date(now.getFullYear() - 1, 11, 31);
            this.asOfDate = lastDay.toISOString().split('T')[0];
        }
    }
}
</script>
@endsection
