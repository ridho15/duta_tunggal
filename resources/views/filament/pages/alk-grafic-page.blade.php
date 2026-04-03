<x-filament-panels::page>
    {{-- Load Chart.js v4 from CDN --}}
    @once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @endonce

    <div class="space-y-6">

        {{-- ============================================================
             HERO BANNER
        ============================================================ --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 p-8 shadow-xl">
            {{-- Background grid pattern --}}
            <div class="pointer-events-none absolute inset-0 opacity-10">
                <svg class="h-full w-full" viewBox="0 0 200 60" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="alk-grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="200" height="60" fill="url(#alk-grid)"/>
                </svg>
            </div>
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <span class="mb-3 inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white backdrop-blur-sm">
                        Finance Analytics
                    </span>
                    <h1 class="mt-2 text-3xl font-bold tracking-tight text-white">ALK Grafik</h1>
                    <p class="mt-2 max-w-lg text-sm text-indigo-200">
                        Analisis Laporan Keuangan — Pantau rasio keuangan, tren pendapatan, dan kondisi neraca perusahaan secara visual dan komprehensif.
                    </p>
                </div>
                <div class="hidden md:flex h-16 w-16 items-center justify-center rounded-2xl bg-white/10">
                    <x-heroicon-o-chart-pie class="h-9 w-9 text-white"/>
                </div>
            </div>
        </div>

        {{-- ============================================================
             FILTER PANEL
        ============================================================ --}}
        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
                    <x-heroicon-o-funnel class="h-4 w-4 text-indigo-600 dark:text-indigo-400"/>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800 dark:text-gray-100">Filter Periode Analisis</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Pilih periode dan cabang untuk analisis</p>
                </div>
            </div>
            <div class="p-6">
                <div class="grid gap-5 md:grid-cols-3">
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tanggal Mulai</label>
                        <input type="date" wire:model="start_date"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tanggal Akhir</label>
                        <input type="date" wire:model="end_date"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cabang</label>
                        <select wire:model="cabang_id"
                            class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-900 transition-all focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100">
                            <option value="">— Semua Cabang —</option>
                            @foreach(\App\Models\Cabang::all() as $cabang)
                                <option value="{{ $cabang->id }}">{{ $cabang->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        @if($this->showPreview)
            @php $data = $this->getAlkData(); @endphp

            {{-- Period badge --}}
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-4 py-1.5 text-sm font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                    <x-heroicon-o-calendar-days class="h-4 w-4"/>
                    Periode: {{ $data['period'] }}
                </span>
            </div>

            {{-- ============================================================
                 FINANCIAL SUMMARY CARDS (4 KPIs)
            ============================================================ --}}
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                @php
                    $kpis = [
                        [
                            'label'    => 'Total Aset',
                            'value'    => $data['total_assets'],
                            'grad'     => 'from-sky-500 to-cyan-600',
                            'icon'     => 'heroicon-o-building-office-2',
                            'textcls'  => 'text-sky-700 dark:text-sky-300',
                        ],
                        [
                            'label'    => 'Total Liabilitas',
                            'value'    => $data['total_liabilities'],
                            'grad'     => 'from-rose-500 to-red-600',
                            'icon'     => 'heroicon-o-arrow-trending-down',
                            'textcls'  => 'text-rose-700 dark:text-rose-300',
                        ],
                        [
                            'label'    => 'Total Ekuitas',
                            'value'    => $data['total_equity'],
                            'grad'     => 'from-emerald-500 to-teal-600',
                            'icon'     => 'heroicon-o-banknotes',
                            'textcls'  => 'text-emerald-700 dark:text-emerald-300',
                        ],
                        [
                            'label'    => 'Laba Bersih',
                            'value'    => $data['net_profit'],
                            'grad'     => $data['net_profit'] >= 0 ? 'from-violet-500 to-purple-600' : 'from-rose-500 to-red-600',
                            'icon'     => $data['net_profit'] >= 0 ? 'heroicon-o-arrow-trending-up' : 'heroicon-o-arrow-trending-down',
                            'textcls'  => $data['net_profit'] >= 0 ? 'text-violet-700 dark:text-violet-300' : 'text-rose-700 dark:text-rose-300',
                        ],
                    ];
                @endphp
                @foreach($kpis as $kpi)
                <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br {{ $kpi['grad'] }} shadow-sm">
                            <x-dynamic-component :component="$kpi['icon']" class="h-5 w-5 text-white"/>
                        </div>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $kpi['label'] }}</p>
                    <p class="mt-1 text-xl font-bold leading-tight {{ $kpi['textcls'] }}">
                        Rp {{ number_format($kpi['value'], 0, ',', '.') }}
                    </p>
                    {{-- Decorative accent --}}
                    <div class="pointer-events-none absolute -right-4 -top-4 h-16 w-16 rounded-full bg-gradient-to-br {{ $kpi['grad'] }} opacity-5"></div>
                </div>
                @endforeach
            </div>

            {{-- ============================================================
                 KEY FINANCIAL RATIOS
            ============================================================ --}}
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30">
                        <x-heroicon-o-calculator class="h-4 w-4 text-violet-600 dark:text-violet-400"/>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Rasio Keuangan Utama</h2>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Likuiditas · Solvabilitas · Profitabilitas</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
                        @php
                            $ratios = [
                                ['label' => 'Current Ratio',  'value' => $data['current_ratio'],  'unit' => 'x', 'good' => fn($v) => $v >= 1.5, 'hint' => '≥ 1.5 ideal', 'cat' => 'Likuiditas'],
                                ['label' => 'Debt to Equity', 'value' => $data['debt_to_equity'], 'unit' => 'x', 'good' => fn($v) => $v <= 1,   'hint' => '≤ 1.0 ideal', 'cat' => 'Solvabilitas'],
                                ['label' => 'ROA',            'value' => $data['roa'],            'unit' => '%', 'good' => fn($v) => $v > 0,    'hint' => '> 0% ideal',  'cat' => 'Profitabilitas'],
                                ['label' => 'ROE',            'value' => $data['roe'],            'unit' => '%', 'good' => fn($v) => $v > 0,    'hint' => '> 0% ideal',  'cat' => 'Profitabilitas'],
                                ['label' => 'Profit Margin',  'value' => $data['profit_margin'],  'unit' => '%', 'good' => fn($v) => $v > 0,    'hint' => '> 0% ideal',  'cat' => 'Profitabilitas'],
                            ];
                        @endphp
                        @foreach($ratios as $ratio)
                        @php
                            $isGood  = $ratio['value'] !== null && $ratio['good']($ratio['value']);
                            $isNull  = $ratio['value'] === null;
                            $cardBg  = $isNull  ? 'bg-gray-50 border-gray-100 dark:bg-gray-800/50 dark:border-gray-700'
                                     : ($isGood ? 'bg-emerald-50 border-emerald-100 dark:bg-emerald-900/20 dark:border-emerald-800/30'
                                                : 'bg-rose-50 border-rose-100 dark:bg-rose-900/20 dark:border-rose-800/30');
                            $valCls  = $isNull  ? 'text-gray-400'
                                     : ($isGood ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-rose-600 dark:text-rose-400');
                            $badge   = $isNull  ? ''
                                     : ($isGood ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300');
                        @endphp
                        <div class="rounded-xl border p-4 {{ $cardBg }}">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ $ratio['cat'] }}</span>
                                @if(!$isNull)
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">
                                    {{ $isGood ? 'Baik' : 'Perhatian' }}
                                </span>
                                @endif
                            </div>
                            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $ratio['label'] }}</p>
                            @if(!$isNull)
                                <p class="mt-1 text-2xl font-bold leading-tight {{ $valCls }}">
                                    {{ number_format($ratio['value'], 2) }}<span class="ml-0.5 text-sm font-medium">{{ $ratio['unit'] }}</span>
                                </p>
                            @else
                                <p class="mt-1 text-2xl font-bold text-gray-400">N/A</p>
                            @endif
                            <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">{{ $ratio['hint'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 CHARTS ROW
            ============================================================ --}}
            <div class="grid gap-4 md:grid-cols-3">

                {{-- Bar + Line combo: Monthly Revenue Trend --}}
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 md:col-span-2">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/30">
                            <x-heroicon-o-chart-bar class="h-4 w-4 text-sky-600 dark:text-sky-400"/>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Tren Pendapatan & Pengeluaran</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">6 Bulan Terakhir</p>
                        </div>
                    </div>
                    <div class="p-6" wire:ignore>
                        <div
                            x-data="{
                                chart: null,
                                initChart() {
                                    if (this.chart) { this.chart.destroy(); this.chart = null; }
                                    const ctx = this.$refs.trendCanvas.getContext('2d');
                                    const isDark = document.documentElement.classList.contains('dark');
                                    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.04)';
                                    const tickColor = isDark ? '#9ca3af' : '#6b7280';
                                    this.chart = new Chart(ctx, {
                                        data: {
                                            labels: {{ Js::from(array_column($data['trend'], 'month')) }},
                                            datasets: [
                                                {
                                                    type: 'bar',
                                                    label: 'Pendapatan',
                                                    data: {{ Js::from(array_column($data['trend'], 'revenue')) }},
                                                    backgroundColor: 'rgba(16,185,129,0.75)',
                                                    borderColor: 'rgba(16,185,129,1)',
                                                    borderWidth: 1,
                                                    borderRadius: 6,
                                                    order: 2
                                                },
                                                {
                                                    type: 'bar',
                                                    label: 'Pengeluaran',
                                                    data: {{ Js::from(array_column($data['trend'], 'expense')) }},
                                                    backgroundColor: 'rgba(239,68,68,0.75)',
                                                    borderColor: 'rgba(239,68,68,1)',
                                                    borderWidth: 1,
                                                    borderRadius: 6,
                                                    order: 2
                                                },
                                                {
                                                    type: 'line',
                                                    label: 'Laba/Rugi',
                                                    data: {{ Js::from(array_column($data['trend'], 'profit')) }},
                                                    borderColor: 'rgba(99,102,241,1)',
                                                    backgroundColor: 'rgba(99,102,241,0.1)',
                                                    pointBackgroundColor: 'rgba(99,102,241,1)',
                                                    pointRadius: 4,
                                                    pointHoverRadius: 6,
                                                    borderWidth: 2.5,
                                                    tension: 0.4,
                                                    fill: false,
                                                    order: 1
                                                }
                                            ]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            interaction: { mode: 'index', intersect: false },
                                            plugins: {
                                                legend: {
                                                    position: 'bottom',
                                                    labels: { color: tickColor, padding: 16, font: { size: 11 } }
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(ctx) {
                                                            return ' ' + ctx.dataset.label + ': Rp ' + Number(ctx.raw).toLocaleString('id-ID');
                                                        }
                                                    }
                                                }
                                            },
                                            scales: {
                                                x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
                                                y: {
                                                    grid: { color: gridColor },
                                                    ticks: {
                                                        color: tickColor, font: { size: 11 },
                                                        callback: function(v) {
                                                            const abs = Math.abs(v);
                                                            if (abs >= 1e9)  return 'Rp ' + (v/1e9).toFixed(1)  + 'M';
                                                            if (abs >= 1e6)  return 'Rp ' + (v/1e6).toFixed(1)  + 'Jt';
                                                            if (abs >= 1e3)  return 'Rp ' + (v/1e3).toFixed(0)  + 'Rb';
                                                            return 'Rp ' + v;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                }
                            }"
                            x-init="$nextTick(() => initChart())"
                        >
                            <div style="position:relative; height:280px;">
                                <canvas x-ref="trendCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Doughnut: Balance Composition --}}
                <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30">
                            <x-heroicon-o-chart-pie class="h-4 w-4 text-violet-600 dark:text-violet-400"/>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Komposisi Neraca</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Liabilitas vs Ekuitas</p>
                        </div>
                    </div>
                    <div class="p-6" wire:ignore>
                        <div
                            x-data="{
                                chart: null,
                                initChart() {
                                    if (this.chart) { this.chart.destroy(); this.chart = null; }
                                    const ctx = this.$refs.donutCanvas.getContext('2d');
                                    const isDark = document.documentElement.classList.contains('dark');
                                    const tickColor = isDark ? '#9ca3af' : '#6b7280';
                                    this.chart = new Chart(ctx, {
                                        type: 'doughnut',
                                        data: {
                                            labels: ['Liabilitas', 'Ekuitas'],
                                            datasets: [{
                                                data: [{{ $data['total_liabilities'] }}, {{ $data['total_equity'] }}],
                                                backgroundColor: ['rgba(239,68,68,0.8)', 'rgba(16,185,129,0.8)'],
                                                borderColor:     ['rgba(239,68,68,1)',   'rgba(16,185,129,1)'],
                                                borderWidth: 2,
                                                hoverOffset: 8,
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            cutout: '65%',
                                            plugins: {
                                                legend: {
                                                    position: 'bottom',
                                                    labels: { color: tickColor, padding: 12, font: { size: 11 } }
                                                },
                                                tooltip: {
                                                    callbacks: {
                                                        label: function(ctx) {
                                                            const total = ctx.dataset.data.reduce((a,b) => a+b, 0);
                                                            const pct = total > 0 ? ((ctx.raw/total)*100).toFixed(1) : 0;
                                                            return ' ' + ctx.label + ': Rp ' + Number(ctx.raw).toLocaleString('id-ID') + ' (' + pct + '%)';
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    });
                                }
                            }"
                            x-init="$nextTick(() => initChart())"
                        >
                            <div style="position:relative; height:200px;">
                                <canvas x-ref="donutCanvas"></canvas>
                            </div>
                            <div class="mt-4 rounded-xl bg-gray-50 p-3 text-center dark:bg-gray-800/50">
                                <p class="text-xs text-gray-400 dark:text-gray-500">Total Aset</p>
                                <p class="mt-0.5 text-lg font-bold text-gray-700 dark:text-gray-200">
                                    Rp {{ number_format($data['total_assets'], 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================
                 MONTHLY TREND TABLE
            ============================================================ --}}
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                        <x-heroicon-o-table-cells class="h-4 w-4 text-emerald-600 dark:text-emerald-400"/>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">Detail Tren Bulanan</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Rincinan pendapatan, pengeluaran, dan laba per bulan</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/70 dark:border-gray-800 dark:bg-gray-800/50">
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Bulan</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Pendapatan</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Pengeluaran</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Laba / Rugi</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Margin</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Visual</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800/70">
                            @php $maxRev = max(array_column($data['trend'], 'revenue') ?: [1]); @endphp
                            @foreach($data['trend'] as $row)
                            @php
                                $marginPct = $row['revenue'] > 0 ? ($row['profit'] / $row['revenue']) * 100 : 0;
                                $barRev    = $maxRev > 0 ? min(100, ($row['revenue'] / $maxRev) * 100) : 0;
                                $barExp    = $maxRev > 0 ? min(100, ($row['expense'] / $maxRev) * 100) : 0;
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/60 dark:hover:bg-gray-800/30">
                                <td class="px-6 py-4 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $row['month'] }}</td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($row['revenue'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-rose-700 dark:text-rose-400">
                                    Rp {{ number_format($row['expense'], 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <span class="inline-flex items-center justify-end gap-1 font-bold {{ $row['profit'] >= 0 ? 'text-indigo-700 dark:text-indigo-400' : 'text-rose-700 dark:text-rose-400' }}">
                                        @if($row['profit'] >= 0)
                                            <x-heroicon-m-arrow-trending-up class="h-3.5 w-3.5"/>
                                        @else
                                            <x-heroicon-m-arrow-trending-down class="h-3.5 w-3.5"/>
                                        @endif
                                        Rp {{ number_format($row['profit'], 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $marginPct >= 0 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' }}">
                                        {{ number_format($marginPct, 1) }}%
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="w-28 space-y-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <div class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500"></div>
                                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full bg-emerald-500 transition-all" style="width:{{ $barRev }}%"></div>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <div class="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-rose-400"></div>
                                            <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                <div class="h-1.5 rounded-full bg-rose-400 transition-all" style="width:{{ $barExp }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center gap-5 border-t border-gray-50 px-6 py-3 dark:border-gray-800">
                    <span class="flex items-center gap-1.5 text-xs text-gray-400"><span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> Pendapatan</span>
                    <span class="flex items-center gap-1.5 text-xs text-gray-400"><span class="inline-block h-2 w-2 rounded-full bg-rose-400"></span> Pengeluaran</span>
                    <span class="flex items-center gap-1.5 text-xs text-gray-400"><span class="inline-block h-1.5 w-4 rounded-full bg-indigo-500"></span> Laba/Rugi</span>
                </div>
            </div>

        @else
            {{-- Empty State --}}
            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white p-16 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/40">
                    <x-heroicon-o-chart-pie class="h-10 w-10 text-indigo-400 dark:text-indigo-500"/>
                </div>
                <h3 class="text-base font-semibold text-gray-700 dark:text-gray-200">Belum Ada Data Analisis</h3>
                <p class="mx-auto mt-2 max-w-sm text-sm text-gray-400 dark:text-gray-500">
                    Atur filter periode dan cabang, kemudian klik
                    <strong class="text-indigo-600 dark:text-indigo-400">Tampilkan Analisis</strong>
                    untuk melihat laporan keuangan.
                </p>
            </div>
        @endif

    </div>
</x-filament-panels::page>
