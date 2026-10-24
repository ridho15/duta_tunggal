@extends('layouts.app')

@section('title', 'Laporan Laba Rugi (Income Statement) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="incomeStatementFilter()">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Laporan</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('laporan.financial.index') }}" class="hover:text-gray-700">Keuangan</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Laba Rugi</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Laporan Laba Rugi (Income Statement)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Ringkasan multi-step pendapatan operasional, harga pokok penjualan (HPP), beban usaha, dan kalkulasi laba bersih.</p>
        </div>
        <div class="flex items-center gap-2.5 flex-wrap">
            <a
                href="{{ route('laporan.financial.income-statement.pdf', request()->query()) }}"
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
    @include('laporan.financial.partials.tabs', ['activeTab' => 'laba-rugi'])

    {{-- Filter Bar --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
        <form method="GET" action="{{ route('laporan.financial.income-statement') }}" class="space-y-4">
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
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->nama . ' (' . $cb->kode . ')'])->values()" :value="$cabangId" placeholder="Semua Cabang (Konsolidasian)" size="sm" id="cabang_id" />
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
                        href="{{ route('laporan.financial.income-statement') }}"
                        title="Reset Filter"
                        class="px-2.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-xs font-semibold transition"
                    >
                        Reset
                    </a>
                </div>
            </div>

            {{-- Preset Cepat Periode --}}
            <div class="flex items-center gap-2 flex-wrap pt-3 border-t border-gray-100 text-xs">
                <span class="text-gray-400 font-semibold text-[11px] uppercase tracking-wider">Preset Cepat:</span>
                <button type="button" @click="setThisMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Bulan Ini</button>
                <button type="button" @click="setLastMonth()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Bulan Lalu</button>
                <button type="button" @click="setThisQuarter()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Triwulan Ini</button>
                <button type="button" @click="setYearToDate()" class="px-2.5 py-1 rounded bg-gray-50 hover:bg-emerald-50 text-gray-600 hover:text-emerald-700 border border-gray-200 transition font-medium text-[11px]">Tahun Ini (YTD)</button>
            </div>
        </form>
    </div>

    @php
        $salesRev = $reportData['sales_revenue']['total'];
        $grossProf = $reportData['gross_profit'];
        $opExp = $reportData['operating_expenses']['total'];
        $netProf = $reportData['net_profit'];

        $grossMargin = $salesRev > 0 ? ($grossProf / $salesRev) * 100 : 0;
        $netMargin = $salesRev > 0 ? ($netProf / $salesRev) * 100 : 0;
        $isProfit = $netProf >= 0;
    @endphp

    {{-- KPI Cards Laba Rugi --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Card 1: Total Pendapatan Usaha --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Pendapatan Usaha</span>
                <span class="p-1.5 bg-blue-50 text-blue-700 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($salesRev, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Penjualan produk &amp; jasa bersih</p>
        </div>

        {{-- Card 2: Laba Kotor (Gross Profit) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Laba Kotor (Gross Profit)</span>
                <span class="p-1.5 bg-emerald-50 text-[#15803d] rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono {{ $grossProf >= 0 ? 'text-emerald-700' : 'text-rose-600' }}">
                Rp {{ number_format($grossProf, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-500 mt-0.5 font-medium">Margin Kotor: <span class="font-bold text-gray-800">{{ number_format($grossMargin, 1, ',', '.') }}%</span></p>
        </div>

        {{-- Card 3: Beban Usaha Operasional --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Beban Operasional</span>
                <span class="p-1.5 bg-rose-50 text-rose-700 rounded-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono text-gray-900">
                Rp {{ number_format($opExp, 2, ',', '.') }}
            </div>
            <p class="text-[11px] text-gray-400 mt-0.5">Biaya umum, gaji, &amp; pemasaran</p>
        </div>

        {{-- Card 4: Laba Bersih (Net Profit) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs font-bold text-gray-500 uppercase tracking-wider">
                <span>Laba Bersih (Net Profit)</span>
                <span class="p-1.5 {{ $isProfit ? 'bg-emerald-50 text-[#15803d]' : 'bg-rose-50 text-rose-700' }} rounded-md">
                    @if ($isProfit)
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    @else
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                    @endif
                </span>
            </div>
            <div class="mt-2 text-xl font-bold font-mono {{ $isProfit ? 'text-emerald-700' : 'text-rose-600' }}">
                Rp {{ number_format($netProf, 2, ',', '.') }}
            </div>
            <p class="text-[11px] font-medium {{ $isProfit ? 'text-emerald-700' : 'text-rose-600' }} mt-0.5">
                {{ $isProfit ? 'Surplus / Profit Bersih' : 'Defisit / Rugi Bersih' }} ({{ number_format($netMargin, 1, ',', '.') }}%)
            </p>
        </div>
    </div>

    {{-- Tabel Multi-Step Laba Rugi --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-gray-200 bg-gray-50/75 flex items-center justify-between flex-wrap gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                    Laporan Laba Rugi Komprehensif
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Periode: <span class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</span>
                    &bull; Entitas: <span class="font-medium text-gray-800">{{ $selectedCabang ? $selectedCabang->nama : 'Konsolidasian (Semua Cabang)' }}</span>
                </p>
            </div>
            <span class="text-xs font-mono font-semibold px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded-md">
                Format Multi-Step Akuntansi
            </span>
        </div>

        <div class="p-5 space-y-6">
            {{-- 1. PENDAPATAN USAHA (SALES REVENUE) --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between pb-1.5 border-b border-gray-200 font-bold text-xs uppercase tracking-wider text-gray-900">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                        1. Pendapatan Usaha (Sales Revenue)
                    </span>
                    <span>Nominal (Rp)</span>
                </div>
                <div class="divide-y divide-gray-100 text-xs">
                    @forelse ($reportData['sales_revenue']['accounts'] as $acc)
                        <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-2 rounded">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-gray-500 text-[11px]">{{ $acc['code'] }}</span>
                                <span class="text-gray-800 font-medium">{{ $acc['name'] }}</span>
                            </div>
                            <span class="font-mono font-semibold text-gray-900">
                                {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <div class="py-3 text-center text-gray-400 italic text-xs">Tidak ada transaksi pendapatan pada periode ini.</div>
                    @endforelse
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-blue-50/50 p-2.5 rounded-lg text-blue-900">
                    <span>TOTAL PENDAPATAN USAHA</span>
                    <span class="font-mono text-sm">Rp {{ number_format($reportData['sales_revenue']['total'], 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- 2. HARGA POKOK PENJUALAN (COGS) --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between pb-1.5 border-b border-gray-200 font-bold text-xs uppercase tracking-wider text-gray-900">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                        2. Harga Pokok Penjualan (HPP / COGS)
                    </span>
                    <span>Nominal (Rp)</span>
                </div>
                <div class="divide-y divide-gray-100 text-xs">
                    @forelse ($reportData['cogs']['accounts'] as $acc)
                        <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-2 rounded">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-gray-500 text-[11px]">{{ $acc['code'] }}</span>
                                <span class="text-gray-800 font-medium">{{ $acc['name'] }}</span>
                            </div>
                            <span class="font-mono font-semibold text-gray-900">
                                {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <div class="py-3 text-center text-gray-400 italic text-xs">Tidak ada data HPP pada periode ini.</div>
                    @endforelse
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-amber-50/50 p-2.5 rounded-lg text-amber-900">
                    <span>TOTAL HARGA POKOK PENJUALAN</span>
                    <span class="font-mono text-sm">Rp {{ number_format($reportData['cogs']['total'], 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- HIGHLIGHT 1: LABA KOTOR (GROSS PROFIT) --}}
            <div class="p-3.5 rounded-xl border-2 {{ $grossProf >= 0 ? 'bg-emerald-50 border-emerald-300 text-emerald-950' : 'bg-rose-50 border-rose-300 text-rose-950' }} flex items-center justify-between font-bold text-xs sm:text-sm">
                <div>
                    <span class="uppercase tracking-wider">LABA KOTOR (GROSS PROFIT)</span>
                    <span class="text-xs font-normal text-gray-600 block sm:inline sm:ml-2">
                        (Pendapatan Usaha &minus; HPP)
                    </span>
                </div>
                <div class="text-right">
                    <span class="font-mono text-base font-extrabold {{ $grossProf >= 0 ? 'text-emerald-800' : 'text-rose-700' }}">
                        Rp {{ number_format($grossProf, 2, ',', '.') }}
                    </span>
                    <div class="text-[11px] font-medium text-gray-600">Margin: {{ number_format($grossMargin, 1, ',', '.') }}%</div>
                </div>
            </div>

            {{-- 3. BEBAN OPERASIONAL (OPERATING EXPENSES) --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between pb-1.5 border-b border-gray-200 font-bold text-xs uppercase tracking-wider text-gray-900">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                        3. Beban Operasional &amp; Usaha
                    </span>
                    <span>Nominal (Rp)</span>
                </div>
                <div class="divide-y divide-gray-100 text-xs">
                    @forelse ($reportData['operating_expenses']['accounts'] as $acc)
                        <div class="py-2 flex items-center justify-between hover:bg-gray-50 px-2 rounded">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-gray-500 text-[11px]">{{ $acc['code'] }}</span>
                                <span class="text-gray-800 font-medium">{{ $acc['name'] }}</span>
                            </div>
                            <span class="font-mono font-semibold text-gray-900">
                                {{ number_format((float) $acc['balance'], 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <div class="py-3 text-center text-gray-400 italic text-xs">Tidak ada beban operasional pada periode ini.</div>
                    @endforelse
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-200 font-bold text-xs bg-rose-50/50 p-2.5 rounded-lg text-rose-900">
                    <span>TOTAL BEBAN OPERASIONAL</span>
                    <span class="font-mono text-sm">Rp {{ number_format($reportData['operating_expenses']['total'], 2, ',', '.') }}</span>
                </div>
            </div>

            {{-- HIGHLIGHT 2: LABA OPERASIONAL (OPERATING PROFIT) --}}
            <div class="p-3.5 rounded-xl border {{ $reportData['operating_profit'] >= 0 ? 'bg-gray-50 border-gray-300 text-gray-900' : 'bg-rose-50 border-rose-300 text-rose-950' }} flex items-center justify-between font-bold text-xs">
                <div>
                    <span class="uppercase tracking-wider">LABA OPERASIONAL (EBIT)</span>
                    <span class="text-xs font-normal text-gray-500 ml-2">(Laba Kotor &minus; Beban Operasional)</span>
                </div>
                <span class="font-mono text-sm font-bold {{ $reportData['operating_profit'] >= 0 ? 'text-gray-900' : 'text-rose-700' }}">
                    Rp {{ number_format($reportData['operating_profit'], 2, ',', '.') }}
                </span>
            </div>

            {{-- 4. PENDAPATAN & BEBAN LAIN-LAIN --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between pb-1.5 border-b border-gray-200 font-bold text-xs uppercase tracking-wider text-gray-900">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                        4. Pendapatan &amp; Beban Non-Operasional (Lain-lain)
                    </span>
                    <span>Nominal (Rp)</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    {{-- Pendapatan Lain --}}
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 space-y-2">
                        <span class="font-bold text-gray-700 block text-[11px] uppercase">Pendapatan Lain-lain:</span>
                        @forelse ($reportData['other_income']['accounts'] as $acc)
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>{{ $acc['name'] }}</span>
                                <span class="font-mono font-medium">{{ number_format($acc['balance'], 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 italic text-[11px]">Tidak ada pendapatan lain</div>
                        @endforelse
                        <div class="flex justify-between pt-1 font-bold text-gray-900">
                            <span>Subtotal:</span>
                            <span class="font-mono">Rp {{ number_format($reportData['other_income']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Beban Lain --}}
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 space-y-2">
                        <span class="font-bold text-gray-700 block text-[11px] uppercase">Beban Lain-lain:</span>
                        @forelse ($reportData['other_expense']['accounts'] as $acc)
                            <div class="flex justify-between py-1 border-b border-gray-100">
                                <span>{{ $acc['name'] }}</span>
                                <span class="font-mono font-medium">{{ number_format($acc['balance'], 2, ',', '.') }}</span>
                            </div>
                        @empty
                            <div class="text-gray-400 italic text-[11px]">Tidak ada beban lain</div>
                        @endforelse
                        <div class="flex justify-between pt-1 font-bold text-gray-900">
                            <span>Subtotal:</span>
                            <span class="font-mono">Rp {{ number_format($reportData['other_expense']['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 5. BEBAN PAJAK PENGHASILAN --}}
            @if ($reportData['tax_expense']['total'] > 0)
                <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-200 text-xs font-semibold">
                    <span class="text-gray-700 uppercase">5. Beban Pajak Penghasilan (PPH)</span>
                    <span class="font-mono text-gray-900">Rp {{ number_format($reportData['tax_expense']['total'], 2, ',', '.') }}</span>
                </div>
            @endif

            {{-- GRAND FINALE: LABA BERSIH PERIODE BERJALAN --}}
            <div class="p-5 rounded-xl border-2 {{ $isProfit ? 'bg-gradient-to-r from-emerald-50 to-teal-50 border-[#15803d]' : 'bg-gradient-to-r from-rose-50 to-amber-50 border-rose-500' }} shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full {{ $isProfit ? 'bg-[#15803d]' : 'bg-rose-600' }}"></span>
                        <h4 class="text-base font-extrabold uppercase tracking-wider {{ $isProfit ? 'text-emerald-950' : 'text-rose-950' }}">
                            {{ $isProfit ? 'LABA BERSIH TAHUN / PERIODE BERJALAN (NET PROFIT)' : 'RUGI BERSIH TAHUN / PERIODE BERJALAN (NET LOSS)' }}
                        </h4>
                    </div>
                    <p class="text-xs {{ $isProfit ? 'text-emerald-800' : 'text-rose-800' }} mt-0.5">
                        Laba Operasional &plusmn; Pendapatan/Beban Non-Operasional &minus; Beban Pajak
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-black font-mono {{ $isProfit ? 'text-[#15803d]' : 'text-rose-700' }}">
                        Rp {{ number_format($netProf, 2, ',', '.') }}
                    </div>
                    <div class="text-xs font-bold {{ $isProfit ? 'text-emerald-700' : 'text-rose-600' }}">
                        Net Profit Margin: {{ number_format($netMargin, 2, ',', '.') }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function incomeStatementFilter() {
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
