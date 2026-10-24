@extends('layouts.app')

@section('title', 'Pusat Depresiasi Bulanan Aset – Duta Tunggal ERP')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pusat Depresiasi Bulanan</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pusat Depresiasi & Penyusutan Bulanan</h1>
            <p class="text-sm text-gray-500 mt-0.5">Pemrosesan penyusutan massal berkala, posting jurnal beban & akumulasi, serta pembatalan (reversal) jurnal.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('keuangan.asset.depreciation.pdf', ['period_month' => $selectedMonth, 'period_year' => $selectedYear, 'cabang_id' => request('cabang_id')]) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-sm font-semibold rounded-xl border border-purple-200 transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Rekapitulasi (PDF)
            </a>
            <a
                href="{{ route('keuangan.asset.index') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Daftar Master Aset
            </a>
        </div>
    </div>


    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Beban Bulan Ini --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Depresiasi Bulan Ini</p>
                <h3 class="text-xl font-bold text-gray-900 mt-1">
                    {{ \App\Helpers\MoneyHelper::rupiah($kpis['month_depreciation_total']) }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Periode {{ \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1)->translatedFormat('F Y') }}</p>
            </div>
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        {{-- Unit Terproses --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Aset Disusutkan</p>
                <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($kpis['total_recorded_units']) }} Unit</h3>
                <p class="text-xs text-gray-400 mt-0.5">Sudah terbit jurnal</p>
            </div>
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        {{-- Aset Menunggu Disusutkan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-amber-600 uppercase tracking-wider">Menunggu Disusutkan</p>
                <h3 class="text-2xl font-bold text-amber-600 mt-1">{{ number_format($kpis['pending_assets_count']) }} Unit</h3>
                <p class="text-xs text-gray-400 mt-0.5">Siap diproses periode ini</p>
            </div>
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        {{-- Estimasi Nilai Pending --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Estimasi Beban Pending</p>
                <h3 class="text-xl font-bold text-gray-900 mt-1">
                    {{ \App\Helpers\MoneyHelper::rupiah($kpis['pending_amount_estimate']) }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Nilai jika disusutkan massal</p>
            </div>
            <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Panel Eksekusi Penyusutan Massal (Run Monthly Batch) --}}
    <div class="bg-gradient-to-r from-emerald-50 via-teal-50 to-white p-6 rounded-2xl border border-emerald-200/80 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#15803d] text-white">
                        Batch Processing
                    </span>
                    <h2 class="text-lg font-bold text-gray-900">Eksekusi Penyusutan Bulanan Massal</h2>
                </div>
                <p class="text-xs text-gray-600 max-w-2xl leading-relaxed">
                    Sistem akan memproses seluruh aset aktif yang telah beroperasi pada periode yang ditentukan, menghitung akumulasi penyusutan terbaru, dan otomatis memposting entri jurnal akuntansi berpasangan (<strong>Debit Beban Penyusutan / Kredit Akumulasi Penyusutan</strong>) ke Buku Besar.
                </p>
            </div>

            {{-- Form Eksekusi --}}
            <form
                action="{{ route('keuangan.asset.depreciation.process-monthly') }}"
                method="POST"
                id="batchDepreciationForm"
                class="flex flex-wrap items-end gap-3 bg-white p-4 rounded-xl border border-emerald-100 shadow-sm"
            >
                @csrf

                {{-- Pilih Bulan --}}
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Bulan</label>
                    <select
                        name="period_month"
                        id="period_month_exec"
                        required
                        class="py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d]"
                    >
                        @for ($m = 1; $m <= 12; $m++)
                            @php
                                $mName = \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F');
                            @endphp
                            <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>
                                {{ str_pad($m, 2, '0', STR_PAD_LEFT) }} - {{ $mName }}
                            </option>
                        @endfor
                    </select>
                </div>

                {{-- Pilih Tahun --}}
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Tahun</label>
                    <select
                        name="period_year"
                        id="period_year_exec"
                        required
                        class="py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-xs font-semibold text-gray-800 focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d]"
                    >
                        @for ($y = now()->year - 2; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ $selectedYear === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>

                {{-- Filter Cabang (Opsional jika canManageAll) --}}
                @if ($canManageAll)
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase mb-1">Cabang</label>
                    <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($cb) => ['id' => $cb->id, 'label' => $cb->kode])->values()" :value="request('cabang_id')" placeholder="Semua Cabang" size="sm" />
                </div>
                @endif

                {{-- Tombol Eksekusi --}}
                <div>
                    <button
                        type="button"
                        @click="
                            const m = document.getElementById('period_month_exec');
                            const mText = m.options[m.selectedIndex].text;
                            const yVal = document.getElementById('period_year_exec').value;
                            $dispatch('open-modal-batch-depreciation', { periodText: mText + ' ' + yVal });
                        "
                        class="inline-flex items-center gap-2 px-5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Proses Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Filter Bar Riwayat --}}
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm">
        <form method="GET" action="{{ route('keuangan.asset.depreciation.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            {{-- Pencarian --}}
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Cari Aset</label>
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Kode AST-XXXX, nama aset..."
                        class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition"
                    >
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            {{-- Filter Bulan --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Bulan Periode</label>
                <select name="period_month" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="">Semua Bulan</option>
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ request('period_month', $selectedMonth) == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(null, $m, 1)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            {{-- Filter Tahun --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Tahun Periode</label>
                <select name="period_year" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="">Semua Tahun</option>
                    @for ($y = now()->year - 3; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ request('period_year', $selectedYear) == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>

            {{-- Filter Status --}}
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Status Entri</label>
                <select name="status" class="w-full py-2 px-3 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:bg-white focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] transition">
                    <option value="all">Semua Status</option>
                    <option value="recorded" {{ request('status') === 'recorded' ? 'selected' : '' }}>Tercatat (Recorded)</option>
                    <option value="reversed" {{ request('status') === 'reversed' ? 'selected' : '' }}>Dibatalkan (Reversed)</option>
                </select>
            </div>

            {{-- Tombol Filter & Reset --}}
            <div class="flex items-end gap-2">
                <button
                    type="submit"
                    class="flex-1 py-2 px-3 bg-gray-800 hover:bg-gray-900 text-white rounded-xl text-sm font-semibold transition active:scale-[0.98]"
                >
                    Filter
                </button>
                <a
                    href="{{ route('keuangan.asset.depreciation.index') }}"
                    class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-semibold transition"
                    title="Reset Filter"
                >
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Tabel Riwayat Entri Depresiasi --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">Periode</th>
                        <th class="py-3.5 px-4">Kode & Nama Aset</th>
                        <th class="py-3.5 px-4">Cabang</th>
                        <th class="py-3.5 px-4">Akun Beban / Akumulasi</th>
                        <th class="py-3.5 px-4 text-right">Beban Depresiasi</th>
                        <th class="py-3.5 px-4 text-right">Akumulasi Total</th>
                        <th class="py-3.5 px-4 text-right">Nilai Buku Akhir</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($depreciations as $item)
                        <tr class="hover:bg-gray-50/60 transition">
                            {{-- Periode --}}
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="font-bold text-gray-900 block">
                                    {{ \Carbon\Carbon::createFromDate($item->period_year, $item->period_month, 1)->translatedFormat('F Y') }}
                                </span>
                                <span class="text-xs text-gray-400">
                                    Tgl: {{ $item->depreciation_date ? $item->depreciation_date->format('d/m/Y') : '-' }}
                                </span>
                            </td>

                            {{-- Aset --}}
                            <td class="py-3.5 px-4">
                                <a href="{{ route('keuangan.asset.show', $item->asset_id) }}" class="font-mono font-bold text-[#15803d] hover:underline block">
                                    {{ $item->asset->code ?? '-' }}
                                </a>
                                <span class="font-medium text-gray-900 block truncate max-w-xs">{{ $item->asset->name ?? '-' }}</span>
                            </td>

                            {{-- Cabang --}}
                            <td class="py-3.5 px-4 whitespace-nowrap text-gray-700">
                                {{ $item->asset->cabang->nama ?? '-' }}
                            </td>

                            {{-- Akun --}}
                            <td class="py-3.5 px-4 text-xs whitespace-nowrap">
                                <div class="text-purple-800 font-mono">Dr: {{ $item->asset->depreciationExpenseCoa->code ?? '-' }}</div>
                                <div class="text-amber-800 font-mono">Cr: {{ $item->asset->accumulatedDepreciationCoa->code ?? '-' }}</div>
                            </td>

                            {{-- Beban Depresiasi --}}
                            <td class="py-3.5 px-4 text-right font-bold text-gray-900 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($item->amount) }}
                            </td>

                            {{-- Akumulasi Total --}}
                            <td class="py-3.5 px-4 text-right text-amber-600 font-medium whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($item->accumulated_total) }}
                            </td>

                            {{-- Nilai Buku Akhir --}}
                            <td class="py-3.5 px-4 text-right font-bold text-emerald-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($item->book_value) }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                @if ($item->status === 'recorded')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Tercatat
                                    </span>
                                @elseif ($item->status === 'reversed')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Dibatalkan
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-gray-50 text-gray-700 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                        {{ ucfirst($item->status) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1.5">
                                    {{-- Lihat Detail Aset --}}
                                    <a
                                        href="{{ route('keuangan.asset.show', $item->asset_id) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Buka Profil Aset"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Reversal Action --}}
                                    @if ($item->status === 'recorded')
                                        <button
                                            type="button"
                                            @click="$dispatch('open-modal-reverse-depreciation', { action: '{{ route('keuangan.asset.depreciation.reverse', $item) }}', name: '{{ addslashes($item->asset->name ?? 'Aset') }}', period: '{{ \Carbon\Carbon::createFromDate($item->period_year, $item->period_month, 1)->translatedFormat('F Y') }}' })"
                                            class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                            title="Batalkan (Reverse) Penyusutan"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-500">
                                <div class="max-w-sm mx-auto flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-3">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-700">Belum Ada Rekaman Penyusutan</p>
                                    <p class="text-xs text-gray-400 mt-1 mb-4">Gunakan panel di atas untuk memproses penyusutan massal pada periode yang diinginkan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($depreciations->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $depreciations->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Eksekusi Penyusutan Massal --}}
    <x-confirmation-modal
        name="batch-depreciation"
        title="Konfirmasi Eksekusi Penyusutan Massal"
        type="success"
        confirm-text="Ya, Proses Penyusutan Massal"
        cancel-text="Batal"
        :has-form="false"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin mengeksekusi penyusutan bulanan massal untuk periode <span class="font-bold text-gray-900 font-mono" x-text="data.periodText"></span>?
        </p>
        <p class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 rounded-lg p-2.5 mt-2">
            Sistem akan menghitung depresiasi seluruh aset aktif dan otomatis memposting jurnal akuntansi ke Buku Besar.
        </p>
        <x-slot:footer>
            <button
                type="button"
                onclick="document.getElementById('batchDepreciationForm').submit()"
                class="w-full sm:w-auto inline-flex justify-center rounded-xl px-4 py-2 text-xs font-bold shadow-sm bg-[#15803d] hover:bg-[#14532d] text-white focus:outline-none focus:ring-2 focus:ring-[#15803d]/30 transition"
            >
                Ya, Proses Penyusutan Massal
            </button>
        </x-slot:footer>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Reverse Depresiasi Aset --}}
    <x-confirmation-modal
        name="reverse-depreciation"
        title="Batalkan (Reverse) Penyusutan Aset"
        type="danger"
        confirm-text="Ya, Batalkan Penyusutan"
        cancel-text="Batal"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin membatalkan penyusutan <span class="font-bold text-gray-900" x-text="data.name"></span> untuk periode <span class="font-bold text-gray-900 font-mono" x-text="data.period"></span>?
        </p>
        <p class="text-xs text-rose-600 bg-rose-50 border border-rose-100 rounded-lg p-2.5 mt-2">
            Jurnal buku besar terkait akan dihapus dan nilai buku aset akan disesuaikan kembali.
        </p>
    </x-confirmation-modal>
</div>
@endsection
