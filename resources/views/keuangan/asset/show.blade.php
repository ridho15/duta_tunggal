@extends('layouts.app')

@section('title', "Detail Aset {$asset->code} – Duta Tunggal ERP")

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    {{-- Header & Aksi Cepat --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan & Akuntansi</span>
                <span class="text-gray-300">/</span>
                <a href="{{ route('keuangan.asset.index') }}" class="hover:underline">Aset Tetap</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">{{ $asset->code }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $asset->name }}</h1>
                @if (in_array($asset->status, ['active', 'posted']))
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        Aktif
                    </span>
                @elseif ($asset->status === 'fully_depreciated')
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                        Disusutkan Penuh
                    </span>
                @elseif ($asset->status === 'disposed')
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">
                        Pelepasan / Dihapus
                    </span>
                @else
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-800">
                        {{ ucfirst($asset->status) }}
                    </span>
                @endif
            </div>
            <p class="text-xs font-mono font-semibold text-gray-500 mt-1">Kode: {{ $asset->code }} | Cabang: {{ $asset->cabang->nama ?? '-' }}</p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center flex-wrap gap-2">
            {{-- Cetak PDF --}}
            <a
                href="{{ route('keuangan.asset.pdf', $asset) }}"
                target="_blank"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Kartu Aset (PDF)
            </a>

            {{-- Susutkan Bulan Ini --}}
            @if ($asset->status === 'active' && $asset->monthly_depreciation > 0)
                <form action="{{ route('keuangan.asset.depreciation.process-single', $asset) }}" method="POST" class="inline" onsubmit="return confirm('Proses penyusutan bulan ini sebesar {{ \App\Helpers\MoneyHelper::rupiah($asset->monthly_depreciation) }}?')">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                        title="Posting penyusutan bulan ini ke Buku Besar"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Susutkan Bulan Ini
                    </button>
                </form>
            @endif

            {{-- Hitung Ulang Penyusutan --}}
            <form action="{{ route('keuangan.asset.calculate-depreciation', $asset) }}" method="POST" class="inline">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold rounded-xl transition"
                    title="Hitung ulang jadwal depresiasi"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Hitung Depresiasi
                </button>
            </form>

            {{-- Post Jurnal Akuisisi Manual Jika Belum --}}
            @if (!$hasJournals)
                <form action="{{ route('keuangan.asset.post-acquisition-journal', $asset) }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Post Jurnal Akuisisi
                    </button>
                </form>
            @endif

            {{-- Mutasi Antar Cabang --}}
            @if ($asset->status === 'active')
                <a
                    href="{{ route('keuangan.asset.transfer.create', ['asset_id' => $asset->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-xl transition"
                    title="Ajukan mutasi lokasi aset ke cabang lain"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    Mutasi Cabang
                </a>

                <a
                    href="{{ route('keuangan.asset.disposal.create', ['asset_id' => $asset->id]) }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl transition"
                    title="Pelepasan / penghapusan aset"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Pelepasan / Disposal
                </a>
            @endif

            {{-- Edit --}}
            <a
                href="{{ route('keuangan.asset.edit', $asset) }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Ubah
            </a>
        </div>
    </div>

    {{-- Banner Status Disposed --}}
    @if ($asset->status === 'disposed')
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <span class="font-bold text-sm block">Aset Telah Dilepaskan / Dihapuskan (Disposed)</span>
                    <span class="text-rose-700">Aset ini sudah dihentikan dari operasional dan tidak lagi disusutkan.</span>
                </div>
            </div>
            @if ($asset->disposals->isNotEmpty())
                <a
                    href="{{ route('keuangan.asset.disposal.show', $asset->disposals->first()->id) }}"
                    class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs rounded-xl shadow-sm transition whitespace-nowrap self-start sm:self-auto"
                >
                    Lihat Berita Acara Pelepasan
                </a>
            @endif
        </div>
    @endif

    {{-- Flash Notifications --}}
    @if (session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2 font-medium">
                <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    {{-- Top 4 Financial Metric Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Biaya Perolehan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Biaya Perolehan</p>
            <h3 class="text-2xl font-bold text-gray-900 mt-1">
                {{ \App\Helpers\MoneyHelper::rupiah($asset->purchase_cost) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Harga perolehan awal aset</span>
        </div>

        {{-- Akumulasi Penyusutan --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Akum. Penyusutan</p>
            <h3 class="text-2xl font-bold text-amber-600 mt-1">
                {{ \App\Helpers\MoneyHelper::rupiah($asset->accumulated_depreciation) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Total yang telah disusutkan</span>
        </div>

        {{-- Nilai Buku Saat Ini --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Nilai Buku Bersih (NBV)</p>
            <h3 class="text-2xl font-bold text-emerald-700 mt-1">
                {{ \App\Helpers\MoneyHelper::rupiah($asset->book_value) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Sisa nilai buku saat ini</span>
        </div>

        {{-- Nilai Sisa (Salvage) --}}
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Nilai Sisa / Residu</p>
            <h3 class="text-2xl font-bold text-gray-700 mt-1">
                {{ \App\Helpers\MoneyHelper::rupiah($asset->salvage_value) }}
            </h3>
            <span class="text-[11px] text-gray-400 mt-0.5 block">Estimasi nilai akhir manfaat</span>
        </div>
    </div>

    {{-- Grid 2 Kolom: Parameter & Progress Manfaat vs COA Mapping --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri (2 Span): Detail Parameter & Progress Siklus Hidup --}}
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-6">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-3">Siklus Hidup dan Parameter Depresiasi</h3>

                {{-- Progress Bar Masa Manfaat --}}
                <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 space-y-2">
                    <div class="flex items-center justify-between text-xs font-medium text-gray-700">
                        <span>Penyusutan Nilai Aset Terpakai</span>
                        <span class="font-bold font-mono">{{ $depreciationPercentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div
                            class="bg-[#15803d] h-3 rounded-full transition-all duration-500"
                            style="width: {{ $depreciationPercentage }}%"
                        ></div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-gray-400 pt-1">
                        <span>Mulai Pakai: {{ $asset->usage_date ? $asset->usage_date->format('d M Y') : '-' }}</span>
                        <span>Masa Manfaat: {{ $asset->useful_life_years }} Tahun ({{ $asset->useful_life_years * 12 }} Bulan)</span>
                        <span>Sisa: {{ $asset->remaining_life_months }} Bulan</span>
                    </div>
                </div>
            </div>

            {{-- Parameter Rincian --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2">
                <div>
                    <span class="text-[11px] text-gray-400 uppercase font-semibold block">Metode</span>
                    <span class="text-sm font-bold text-gray-800 block mt-0.5">
                        {{ match($asset->depreciation_method) {
                            'straight_line' => 'Garis Lurus',
                            'declining_balance' => 'Saldo Menurun Ganda',
                            'sum_of_years_digits' => 'Jumlah Digit Tahun',
                            'units_of_production' => 'Unit Produksi',
                            default => $asset->depreciation_method
                        } }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] text-gray-400 uppercase font-semibold block">Depresiasi / Bulan</span>
                    <span class="text-sm font-bold text-gray-900 font-mono block mt-0.5">
                        {{ \App\Helpers\MoneyHelper::rupiah($asset->monthly_depreciation) }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] text-gray-400 uppercase font-semibold block">Depresiasi / Tahun</span>
                    <span class="text-sm font-bold text-gray-900 font-mono block mt-0.5">
                        {{ \App\Helpers\MoneyHelper::rupiah($asset->annual_depreciation) }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] text-gray-400 uppercase font-semibold block">Tgl Perolehan</span>
                    <span class="text-sm font-bold text-gray-800 block mt-0.5">
                        {{ $asset->purchase_date ? $asset->purchase_date->format('d/m/Y') : '-' }}
                    </span>
                </div>
            </div>

            {{-- Keterangan / Spesifikasi --}}
            @if ($asset->notes)
                <div class="pt-2 border-t border-gray-100">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-1">Catatan / Spesifikasi Fisik</span>
                    <p class="text-xs text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 whitespace-pre-line">{{ $asset->notes }}</p>
                </div>
            @endif
        </div>

        {{-- Kolom Kanan (1 Span): Pemetaan Akun Buku Besar (COA) --}}
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm space-y-4">
            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b border-gray-100 pb-2">
                Pemetaan Akun (COA)
            </h3>

            {{-- Akun Aset Tetap --}}
            <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100">
                <span class="text-[10px] font-bold text-blue-700 uppercase tracking-wider block">Akun Aset Tetap (Debit)</span>
                <span class="font-mono font-bold text-xs text-blue-900 block mt-0.5">{{ $asset->assetCoa->code ?? '-' }}</span>
                <span class="text-xs text-blue-800 block">{{ $asset->assetCoa->name ?? '-' }}</span>
            </div>

            {{-- Akun Akumulasi Penyusutan --}}
            <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-100">
                <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider block">Akumulasi Penyusutan (Kredit)</span>
                <span class="font-mono font-bold text-xs text-amber-900 block mt-0.5">{{ $asset->accumulatedDepreciationCoa->code ?? '-' }}</span>
                <span class="text-xs text-amber-800 block">{{ $asset->accumulatedDepreciationCoa->name ?? '-' }}</span>
            </div>

            {{-- Akun Beban Penyusutan --}}
            <div class="p-3 bg-purple-50/60 rounded-xl border border-purple-100">
                <span class="text-[10px] font-bold text-purple-700 uppercase tracking-wider block">Beban Penyusutan (Debit)</span>
                <span class="font-mono font-bold text-xs text-purple-900 block mt-0.5">{{ $asset->depreciationExpenseCoa->code ?? '-' }}</span>
                <span class="text-xs text-purple-800 block">{{ $asset->depreciationExpenseCoa->name ?? '-' }}</span>
            </div>

            {{-- Status Jurnal Buku Besar --}}
            <div class="pt-2">
                @if ($hasJournals)
                    <div class="flex items-center gap-2 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-2 rounded-xl border border-emerald-200">
                        <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>Jurnal Terposting ke Buku Besar</span>
                    </div>
                @else
                    <div class="flex items-center gap-2 text-xs font-bold text-rose-700 bg-rose-50 px-3 py-2 rounded-xl border border-rose-200">
                        <svg class="w-4 h-4 text-rose-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Belum Ada Jurnal Akuisisi</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Tabel Riwayat Penyusutan Bulanan (Asset Depreciation History) --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Riwayat Penyusutan Bulanan (Depreciation History)</h3>
                <p class="text-xs text-gray-500 mt-0.5">Daftar beban penyusutan periodik yang telah tercatat dan terposting.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg">
                    {{ $asset->depreciationEntries->count() }} Periode Tercatat
                </span>
                <a href="{{ route('keuangan.asset.depreciation.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold text-xs rounded-xl transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                    <span>Pusat Depresiasi</span>
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Periode</th>
                        <th class="py-3 px-4">Tanggal Jurnal</th>
                        <th class="py-3 px-4 text-right">Beban Penyusutan (Rp)</th>
                        <th class="py-3 px-4 text-right">Akumulasi Penyusutan (Rp)</th>
                        <th class="py-3 px-4 text-right">Nilai Buku (Rp)</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($asset->depreciationEntries as $entry)
                        @php
                            $monthNames = [
                                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
                            ];
                        @endphp
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap font-bold text-gray-900">
                                {{ $monthNames[(int) $entry->period_month] ?? $entry->period_month }} {{ $entry->period_year }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ $entry->depreciation_date ? $entry->depreciation_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-rose-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($entry->amount) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-amber-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($entry->accumulated_total) }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-semibold text-emerald-700 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($entry->book_value) }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($entry->status === 'recorded')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        Tercatat
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">
                                        Dibatalkan (Reversed)
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($entry->status === 'recorded')
                                    <form action="{{ route('keuangan.asset.depreciation.reverse', $entry->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan (reverse) depresiasi periode ini? Jurnal beban dan akumulasi akan dihapus dari buku besar.');" class="inline">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 rounded-lg border border-rose-200 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                            </svg>
                                            <span>Batalkan (Reversal)</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-gray-400 italic">Sudah Reversal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-400 text-xs">
                                Belum ada riwayat penyusutan bulanan untuk aset ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabel Riwayat Mutasi Antar Cabang --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Riwayat Mutasi Antar Cabang</h3>
                <p class="text-xs text-gray-500 mt-0.5">Catatan riwayat perpindahan lokasi fisik aset antar cabang perusahaan.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-2.5 py-1 bg-blue-50 text-blue-800 border border-blue-200 rounded-lg">
                    {{ $asset->transfers->count() }} Mutasi Tercatat
                </span>
                @if ($asset->status === 'active')
                    <a href="{{ route('keuangan.asset.transfer.create', ['asset_id' => $asset->id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs rounded-xl transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Ajukan Mutasi</span>
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Tgl Rencana</th>
                        <th class="py-3 px-4">Cabang Asal</th>
                        <th class="py-3 px-4">Cabang Tujuan</th>
                        <th class="py-3 px-4">Alasan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($asset->transfers as $trf)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ $trf->transfer_date ? $trf->transfer_date->format('d/m/Y') : '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap text-gray-800">
                                {{ $trf->fromCabang->nama ?? '-' }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap font-semibold text-gray-900">
                                {{ $trf->toCabang->nama ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600 max-w-xs truncate">
                                {{ $trf->reason ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if ($trf->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">Menunggu Persetujuan</span>
                                @elseif ($trf->status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800">Disetujui (Transit)</span>
                                @elseif ($trf->status === 'completed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Selesai</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">Dibatalkan</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <a href="{{ route('keuangan.asset.transfer.show', $trf) }}" class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
                                    Detail Mutasi
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-400 text-xs">
                                Aset ini belum pernah dimutasi ke cabang lain.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Tabel Jurnal Akuntansi Buku Besar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Log Entri Jurnal Buku Besar (General Ledger Entries)</h3>
                <p class="text-xs text-gray-500 mt-0.5">Semua transaksi debet & kredit terkait aset ini yang tercatat di pembukuan.</p>
            </div>
            <span class="text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg">
                {{ $asset->journalEntries->count() }} Baris Jurnal
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-100 text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Tipe Transaksi</th>
                        <th class="py-3 px-4">Kode & Nama Akun (COA)</th>
                        <th class="py-3 px-4">Keterangan</th>
                        <th class="py-3 px-4 text-right">Debit (Rp)</th>
                        <th class="py-3 px-4 text-right">Kredit (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @php
                        $sumDebit = 0;
                        $sumCredit = 0;
                    @endphp
                    @forelse ($asset->journalEntries as $entry)
                        @php
                            $sumDebit += $entry->debit;
                            $sumCredit += $entry->credit;
                        @endphp
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="py-3 px-4 whitespace-nowrap text-gray-700">
                                {{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold {{ $entry->journal_type === 'asset_acquisition' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                    {{ $entry->journal_type === 'asset_acquisition' ? 'Akuisisi Aset' : ($entry->journal_type === 'asset_depreciation' ? 'Penyusutan Bulanan' : $entry->journal_type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="font-mono font-bold text-gray-700">{{ $entry->coa->code ?? '-' }}</span>
                                <span class="text-gray-600 block text-[11px]">{{ $entry->coa->name ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4 text-gray-700 max-w-xs truncate">
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
                            <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                                Belum ada jurnal yang tercatat untuk aset ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($asset->journalEntries->isNotEmpty())
                    <tfoot>
                        <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-xs text-gray-900">
                            <td colspan="4" class="py-3 px-4 text-right uppercase tracking-wider">Total Mutasi:</td>
                            <td class="py-3 px-4 text-right font-mono text-emerald-700">{{ \App\Helpers\MoneyHelper::rupiah($sumDebit) }}</td>
                            <td class="py-3 px-4 text-right font-mono text-rose-700">{{ \App\Helpers\MoneyHelper::rupiah($sumCredit) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
