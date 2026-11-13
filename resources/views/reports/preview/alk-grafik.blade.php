<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALK Grafik - {{ config('app.name', 'PT Duta Tunggal') }}</title>
    
    {{-- Typography & Tailwind Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

    @php
        $isEmbedded = request()->boolean('embedded');
        $exportParams = array_filter([
            'start_date' => $report['start_date'],
            'end_date' => $report['end_date'],
            'cabang_id' => request('cabang_id'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $summaryCards = [
            ['label' => 'Pendapatan', 'value' => data_get($report, 'summary.revenue', 0), 'hint' => 'Pendapatan periode berjalan.', 'color' => 'emerald'],
            ['label' => 'Beban', 'value' => data_get($report, 'summary.expense', 0), 'hint' => 'Beban pada periode analisis.', 'color' => 'rose'],
            ['label' => 'Laba Bersih', 'value' => data_get($report, 'summary.net_profit', 0), 'hint' => 'Kinerja laba bersih akhir periode.', 'color' => data_get($report, 'summary.net_profit', 0) >= 0 ? 'emerald' : 'rose'],
            ['label' => 'Total Aset', 'value' => data_get($report, 'summary.total_assets', 0), 'hint' => 'Posisi aset pada akhir periode.', 'color' => 'blue'],
            ['label' => 'Total Liabilitas', 'value' => data_get($report, 'summary.total_liabilities', 0), 'hint' => 'Kewajiban tercatat di neraca.', 'color' => 'amber'],
            ['label' => 'Total Ekuitas', 'value' => data_get($report, 'summary.total_equity', 0), 'hint' => 'Ekuitas setelah akumulasi laba.', 'color' => 'indigo'],
        ];

        $ratioCards = [
            ['label' => 'Current Ratio', 'value' => data_get($report, 'ratios.current_ratio'), 'unit' => 'x', 'hint' => 'Likuiditas jangka pendek.', 'ok' => fn ($value) => $value >= 1.5],
            ['label' => 'Debt to Equity', 'value' => data_get($report, 'ratios.debt_to_equity'), 'unit' => 'x', 'hint' => 'Keseimbangan struktur modal.', 'ok' => fn ($value) => $value <= 1],
            ['label' => 'ROA', 'value' => data_get($report, 'ratios.roa'), 'unit' => '%', 'hint' => 'Efisiensi aset menghasilkan laba.', 'ok' => fn ($value) => $value > 0],
            ['label' => 'ROE', 'value' => data_get($report, 'ratios.roe'), 'unit' => '%', 'hint' => 'Pengembalian ke pemilik modal.', 'ok' => fn ($value) => $value > 0],
            ['label' => 'Profit Margin', 'value' => data_get($report, 'ratios.profit_margin'), 'unit' => '%', 'hint' => 'Margin laba bersih terhadap penjualan.', 'ok' => fn ($value) => $value > 0],
        ];
    @endphp

    <style>
        body {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        body.embedded {
            background-color: transparent;
        }
        .chart-stage {
            position: relative;
            height: 360px;
        }
        .chart-stage.compact {
            height: 320px;
        }
        @media print {
            body {
                background-color: #ffffff !important;
            }
            .no-print {
                display: none !important;
            }
            .shadow-sm, .shadow-md, .shadow-lg, .shadow-2xs {
                box-shadow: none !important;
            }
            .border {
                border-color: #e2e8f0 !important;
            }
        }
    </style>
</head>
<body class="{{ $isEmbedded ? 'embedded' : '' }} min-h-screen antialiased">

    {{-- Top Action Bar (Hanya tampil pada standalone tab, disembunyikan jika embedded) --}}
    @unless($isEmbedded)
        <header class="no-print sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200 px-4 sm:px-6 py-3">
            <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm border border-emerald-200 shadow-2xs">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-sm font-bold text-gray-900 leading-tight">Analisis Laporan Keuangan (ALK) Grafik</h1>
                        <p class="text-xs text-gray-500">Visualisasi Metrik, Rasio Solvabilitas &amp; Tren Keuangan</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('reports.alk-grafik.excel', $exportParams) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 shadow-2xs transition-colors">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Download Excel</span>
                    </a>

                    <a href="{{ route('reports.alk-grafik.pdf', $exportParams) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900 shadow-2xs transition-colors">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        <span>Download PDF</span>
                    </a>

                    <button onclick="window.print()"
                            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-[#15803d] text-white text-xs font-semibold hover:bg-emerald-800 shadow-2xs transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        <span>Cetak / PDF</span>
                    </button>

                    <button onclick="window.close()"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 bg-gray-100 text-xs font-semibold text-gray-600 hover:bg-gray-200 hover:text-gray-900 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </header>
    @endunless

    {{-- Report Content Shell --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6 {{ $isEmbedded ? 'p-2 sm:p-3' : '' }}">

        {{-- Executive Header Card (Bersih, Rapi & Standar ERP Formal) --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60">
                            {{ config('app.name', 'PT Duta Tunggal') }}
                        </span>
                        <span class="text-gray-300">•</span>
                        <span class="text-xs font-medium text-gray-500">
                            Cabang {{ $report['branch_name'] ?? 'Semua Cabang' }}
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">Laporan ALK Grafik</h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        Periode {{ \Carbon\Carbon::parse($report['start_date'])->isoFormat('D MMMM GGGG') }}
                        s/d {{ \Carbon\Carbon::parse($report['end_date'])->isoFormat('D MMMM GGGG') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @if(data_get($report, 'summary.is_balanced'))
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-semibold text-emerald-800">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Neraca seimbang</span>
                        </div>
                    @else
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 border border-rose-200 text-xs font-semibold text-rose-800">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span>Neraca belum seimbang</span>
                        </div>
                    @endif

                    <div class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-medium text-gray-600">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Workbook Excel berisi summary, rasio, komposisi, dan tren</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 6 Ringkasan Posisi & Kinerja Finansial --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
            @foreach($summaryCards as $card)
                <div class="bg-white rounded-xl border border-gray-200 shadow-2xs p-4 flex flex-col justify-between hover:border-gray-300 transition-colors">
                    <div>
                        <div class="flex items-center justify-between gap-1 mb-1.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                {{ $card['label'] }}
                            </span>
                            @if($card['color'] === 'emerald')
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            @elseif($card['color'] === 'rose')
                                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            @elseif($card['color'] === 'blue')
                                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            @elseif($card['color'] === 'amber')
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                            @else
                                <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            @endif
                        </div>
                        <div class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight">
                            Rp {{ number_format($card['value'], 0, ',', '.') }}
                        </div>
                    </div>
                    <div class="text-[10px] text-gray-400 mt-2 line-clamp-1" title="{{ $card['hint'] }}">
                        {{ $card['hint'] }}
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Section Rasio Keuangan Utama --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-900">Rasio Keuangan Utama</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Likuiditas, solvabilitas, dan profitabilitas memakai payload yang sama dengan admin, PDF, dan workbook Excel.
                </p>
            </div>
            
            <div class="p-5 sm:p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                    @foreach($ratioCards as $ratio)
                        @php
                            $value = $ratio['value'];
                            $hasValue = $value !== null;
                            $statusOk = $hasValue && $ratio['ok']($value);
                        @endphp
                        <div class="bg-white rounded-xl border border-gray-200 p-4 flex flex-col justify-between shadow-2xs hover:border-gray-300 transition-colors">
                            <div>
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 block mb-1">
                                    {{ $ratio['label'] }}
                                </span>
                                <div class="text-xl font-black text-gray-900 font-mono tracking-tight">
                                    {{ $hasValue ? number_format($value, 2) . $ratio['unit'] : 'N/A' }}
                                </div>
                                <span class="text-[11px] text-gray-400 block mt-1">
                                    {{ $ratio['hint'] }}
                                </span>
                            </div>

                            @if($hasValue)
                                <div class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between">
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Status</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider {{ $statusOk ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $statusOk ? 'Baik' : 'Perlu perhatian' }}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Status Keseimbangan Neraca --}}
                @if(data_get($report, 'summary.is_balanced'))
                    <div class="flex items-center gap-3 p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 text-xs text-emerald-900">
                        <span class="p-1.5 rounded-lg bg-emerald-100 text-emerald-700 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <span class="font-bold">Kondisi Neraca:</span>
                            Neraca dalam kondisi seimbang. Struktur aset, liabilitas, dan ekuitas dapat dipakai sebagai basis analisis lanjutan.
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-3 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-900">
                        <span class="p-1.5 rounded-lg bg-rose-100 text-rose-700 shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </span>
                        <div>
                            <span class="font-bold">Perhatian Neraca:</span>
                            Neraca belum seimbang dengan selisih Rp {{ number_format(abs((float) data_get($report, 'summary.difference', 0)), 0, ',', '.') }}.
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section Visualisasi Tren & Komposisi Neraca --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-900">Visualisasi Tren dan Komposisi</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Chart difokuskan untuk preview interaktif, sementara PDF dan Excel membawa ringkasan numerik yang sama.
                </p>
            </div>

            <div class="p-5 sm:p-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 bg-gray-50/40 rounded-xl border border-gray-100 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Tren Pendapatan, Beban, dan Laba</h4>
                            <span class="text-[11px] text-gray-400">Bulanan</span>
                        </div>
                        <div class="chart-stage">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                    <div class="bg-gray-50/40 rounded-xl border border-gray-100 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-700">Komposisi Neraca</h4>
                                <span class="text-[11px] text-gray-400">Liabilitas vs Ekuitas</span>
                            </div>
                            <div class="chart-stage compact">
                                <canvas id="compositionChart"></canvas>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200/60 text-center text-xs text-gray-500">
                            Perbandingan kewajiban jangka pendek/panjang terhadap modal sendiri.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section Detail Tren Bulanan --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-sm font-bold text-gray-900">Detail Tren Bulanan</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Rincian bulanan untuk verifikasi angka laporan terhadap periode yang dipilih.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            <th class="py-3 px-5">Bulan</th>
                            <th class="py-3 px-5 text-right">Pendapatan</th>
                            <th class="py-3 px-5 text-right">Beban</th>
                            <th class="py-3 px-5 text-right">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs">
                        @forelse($report['trend'] as $row)
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="py-3 px-5 font-semibold text-gray-900">{{ $row['month'] }}</td>
                                <td class="py-3 px-5 text-right font-mono text-gray-700">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="py-3 px-5 text-right font-mono text-gray-700">Rp {{ number_format($row['expense'], 0, ',', '.') }}</td>
                                <td class="py-3 px-5 text-right font-mono font-bold {{ $row['profit'] >= 0 ? 'text-[#15803d]' : 'text-rose-600' }}">
                                    Rp {{ number_format($row['profit'], 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-gray-400">
                                    Tidak ada data tren pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    {{-- Chart Initialization Scripts --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const trend = @json($report['trend']);

            const trendChart = document.getElementById('trendChart');
            if (trendChart && Array.isArray(trend)) {
                new Chart(trendChart.getContext('2d'), {
                    data: {
                        labels: trend.map(item => item.month),
                        datasets: [
                            {
                                type: 'bar',
                                label: 'Pendapatan',
                                data: trend.map(item => item.revenue),
                                backgroundColor: 'rgba(21, 128, 61, 0.75)',
                                borderColor: 'rgba(21, 128, 61, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                            },
                            {
                                type: 'bar',
                                label: 'Beban',
                                data: trend.map(item => item.expense),
                                backgroundColor: 'rgba(225, 29, 72, 0.7)',
                                borderColor: 'rgba(225, 29, 72, 1)',
                                borderWidth: 1,
                                borderRadius: 6,
                            },
                            {
                                type: 'line',
                                label: 'Laba Bersih',
                                data: trend.map(item => item.profit),
                                borderColor: 'rgba(37, 99, 235, 1)',
                                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                                borderWidth: 2.5,
                                pointRadius: 4,
                                tension: 0.35,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: { family: 'Plus Jakarta Sans', size: 12, weight: 600 }
                                }
                            },
                        },
                        scales: {
                            y: {
                                ticks: {
                                    font: { family: 'JetBrains Mono', size: 11 },
                                    callback: value => 'Rp ' + Number(value).toLocaleString('id-ID'),
                                },
                                grid: { color: 'rgba(226, 232, 240, 0.6)' }
                            },
                            x: {
                                ticks: {
                                    font: { family: 'Plus Jakarta Sans', size: 11, weight: 600 }
                                },
                                grid: { display: false }
                            }
                        },
                    },
                });
            }

            const compositionChart = document.getElementById('compositionChart');
            if (compositionChart) {
                new Chart(compositionChart.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Liabilitas', 'Ekuitas'],
                        datasets: [{
                            data: [@json(data_get($report, 'summary.total_liabilities', 0)), @json(data_get($report, 'summary.total_equity', 0))],
                            backgroundColor: ['rgba(245, 158, 11, 0.85)', 'rgba(21, 128, 61, 0.85)'],
                            borderColor: ['#f59e0b', '#15803d'],
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: { family: 'Plus Jakarta Sans', size: 12, weight: 600 }
                                }
                            },
                        },
                    },
                });
            }
        });
    </script>
</body>
</html>
