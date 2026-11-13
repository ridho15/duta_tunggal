<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ageing Report – {{ config('app.name', 'PT Duta Tunggal') }}</title>

    {{-- Typography & Tailwind Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    @php
        $buckets = [
            'Current' => [
                'label' => '0–30 hari',
                'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'text' => 'text-[#15803d]',
                'dot' => 'bg-emerald-500',
            ],
            '31–60' => [
                'label' => '31–60 hari',
                'badge' => 'bg-amber-50 text-amber-700 border-amber-200',
                'text' => 'text-amber-700',
                'dot' => 'bg-amber-500',
            ],
            '61–90' => [
                'label' => '61–90 hari',
                'badge' => 'bg-orange-50 text-orange-700 border-orange-200',
                'text' => 'text-orange-700',
                'dot' => 'bg-orange-500',
            ],
            '>90' => [
                'label' => '>90 hari',
                'badge' => 'bg-rose-50 text-rose-700 border-rose-200',
                'text' => 'text-rose-700',
                'dot' => 'bg-rose-500',
            ],
        ];
    @endphp

    <style>
        body {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
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
<body class="min-h-screen antialiased">

    {{-- Top Action Bar --}}
    <header class="no-print sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200 px-4 sm:px-6 py-3">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm border border-emerald-200 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Laporan Umur Piutang &amp; Hutang (Ageing Report)</h1>
                    <p class="text-xs text-gray-500">Analisis Jatuh Tempo, Arus Kas &amp; Tingkat Risiko Kolektibilitas</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
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

    {{-- Report Content Shell --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 sm:p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200/60">
                            {{ config('app.name', 'PT Duta Tunggal') }}
                        </span>
                        <span class="text-gray-300">•</span>
                        <span class="text-xs font-medium text-gray-500">
                            Divisi Finansial &amp; Akuntansi
                        </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">
                        Laporan Umur (Ageing Report)
                        @if($reportType === 'receivables')
                            — Piutang Usaha (AR)
                        @elseif($reportType === 'payables')
                            — Hutang Usaha (AP)
                        @else
                            — Piutang &amp; Hutang (AR &amp; AP)
                        @endif
                    </h2>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">
                        Posisi per tanggal {{ \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM GGGG') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gray-50 border border-gray-200 text-xs font-semibold text-gray-700">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Cakupan: {{ $reportType === 'receivables' ? 'Piutang' : ($reportType === 'payables' ? 'Hutang' : 'Piutang & Hutang') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- AR Aging Buckets --}}
        @if($reportType !== 'payables')
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                            Piutang Usaha (Account Receivables) — Ringkasan Umur
                        </h3>
                    </div>
                    <span class="text-xs text-gray-400 hidden sm:inline">Kolektibilitas Tagihan Penjualan</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
                    @foreach($buckets as $bucket => $conf)
                        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between hover:border-gray-300 transition-colors">
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                        {{ $bucket }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border {{ $conf['badge'] }}">
                                        {{ $conf['label'] }}
                                    </span>
                                </div>
                                <div class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $conf['text'] }}">
                                    Rp {{ number_format($arSummary[$bucket] ?? 0, 0, ',', '.') }}
                                </div>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-2 block">
                                {{ $bucket === 'Current' ? 'Belum jatuh tempo' : 'Lewat jatuh tempo' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- AP Aging Buckets --}}
        @if($reportType !== 'receivables')
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">
                            Hutang Usaha (Account Payables) — Ringkasan Umur
                        </h3>
                    </div>
                    <span class="text-xs text-gray-400 hidden sm:inline">Kewajiban Pembelian Supplier</span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
                    @foreach($buckets as $bucket => $conf)
                        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between hover:border-gray-300 transition-colors">
                            <div>
                                <div class="flex items-center justify-between gap-1 mb-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                        {{ $bucket }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border {{ $conf['badge'] }}">
                                        {{ $conf['label'] }}
                                    </span>
                                </div>
                                <div class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $conf['text'] }}">
                                    Rp {{ number_format($apSummary[$bucket] ?? 0, 0, ',', '.') }}
                                </div>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-2 block">
                                {{ $bucket === 'Current' ? 'Belum jatuh tempo' : 'Lewat jatuh tempo' }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Cash Flow Impact & Risk Assessment --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 no-print">
            {{-- Proyeksi Arus Kas --}}
            <div class="bg-white rounded-2xl border border-blue-200/80 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                            <h3 class="text-sm font-bold text-gray-900">Dampak Arus Kas (30 Hari ke Depan)</h3>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded">Proyeksi</span>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-gray-100">
                            <span class="text-gray-500 font-medium">Estimasi Penerimaan Kas (Inflow)</span>
                            <span class="font-mono font-bold text-emerald-700">Rp {{ number_format($expectedInflow, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-gray-100">
                            <span class="text-gray-500 font-medium">Estimasi Pengeluaran Kas (Outflow)</span>
                            <span class="font-mono font-bold text-rose-700">Rp {{ number_format($expectedOutflow, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-blue-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-900">Net Proyeksi Kas (Surplus/Defisit)</span>
                    <span class="font-mono text-sm font-black {{ ($expectedInflow - $expectedOutflow) >= 0 ? 'text-[#15803d]' : 'text-rose-700' }}">
                        Rp {{ number_format($expectedInflow - $expectedOutflow, 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Penilaian Risiko --}}
            <div class="bg-white rounded-2xl border border-rose-200/80 shadow-sm p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-rose-50 text-rose-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </span>
                            <h3 class="text-sm font-bold text-gray-900">Analisis Risiko Jatuh Tempo</h3>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700 bg-rose-50 px-2 py-0.5 rounded">Evaluasi Risiko</span>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-gray-100">
                            <span class="text-gray-500 font-medium">Piutang Macet / Tertunda (Overdue AR)</span>
                            <span class="font-mono font-bold text-rose-700">Rp {{ number_format($overdueAR, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center justify-between py-1.5 border-b border-gray-100">
                            <span class="text-gray-500 font-medium">Hutang Jatuh Tempo (Overdue AP)</span>
                            <span class="font-mono font-bold text-amber-700">Rp {{ number_format($overdueAP, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-rose-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-900">Kesenjangan Modal Kerja (Working Capital Gap)</span>
                    <span class="font-mono text-sm font-black text-gray-900">
                        Rp {{ number_format($overdueAR - $overdueAP, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- AR Detail Table --}}
        @if($reportType !== 'payables' && $arRecords->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Detail Piutang Usaha (Account Receivables)</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Daftar rincian faktur piutang beserta usia peredaran tagihan.</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-lg">
                        {{ $arRecords->count() }} Dokumen
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-4">No Invoice</th>
                                <th class="py-3 px-4">Tgl Invoice</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-center">Hari Beredar</th>
                                <th class="py-3 px-4">Aging</th>
                                <th class="py-3 px-4 text-right">Sisa Tagihan (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            @foreach($arRecords as $i => $rec)
                                @php
                                    $b = $rec->aging_bucket_computed;
                                    $conf = $buckets[$b] ?? [
                                        'badge' => 'bg-gray-50 text-gray-700 border-gray-200',
                                        'text' => 'text-gray-700',
                                    ];
                                @endphp
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="py-3 px-4 text-center text-gray-400 font-mono">{{ $i + 1 }}</td>
                                    <td class="py-3 px-4 font-semibold text-gray-900">{{ $rec->customer->name ?? '-' }}</td>
                                    <td class="py-3 px-4 font-mono text-gray-700 font-medium">{{ $rec->invoice->no_invoice ?? '-' }}</td>
                                    <td class="py-3 px-4 text-gray-600">
                                        {{ $rec->invoice?->invoice_date ? \Carbon\Carbon::parse($rec->invoice->invoice_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">
                                        {{ $rec->invoice?->due_date ? \Carbon\Carbon::parse($rec->invoice->due_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-semibold text-gray-700">
                                        {{ $rec->days_outstanding_computed }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $conf['badge'] }}">
                                            {{ $b }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                        {{ number_format($rec->remaining, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50/90 border-t-2 border-gray-200 text-xs font-black text-gray-900">
                                <td colspan="7" class="py-3 px-4 text-right uppercase tracking-wider text-gray-600">Total Piutang Usaha</td>
                                <td class="py-3 px-4 text-right font-mono text-sm text-[#15803d]">
                                    {{ number_format($arRecords->sum('remaining'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @elseif($reportType !== 'payables')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-xs text-gray-400">
                Tidak ada data Account Receivables pada periode ini.
            </div>
        @endif

        {{-- AP Detail Table --}}
        @if($reportType !== 'receivables' && $apRecords->isNotEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Detail Hutang Usaha (Account Payables)</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Daftar rincian tagihan supplier beserta jatuh tempo kewajiban.</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-1 rounded-lg">
                        {{ $apRecords->count() }} Dokumen
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="py-3 px-4 w-12 text-center">#</th>
                                <th class="py-3 px-4">Supplier</th>
                                <th class="py-3 px-4">No Invoice</th>
                                <th class="py-3 px-4">Tgl Invoice</th>
                                <th class="py-3 px-4">Jatuh Tempo</th>
                                <th class="py-3 px-4 text-center">Hari Beredar</th>
                                <th class="py-3 px-4">Aging</th>
                                <th class="py-3 px-4 text-right">Sisa Hutang (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-xs">
                            @foreach($apRecords as $i => $rec)
                                @php
                                    $b = $rec->aging_bucket_computed;
                                    $conf = $buckets[$b] ?? [
                                        'badge' => 'bg-gray-50 text-gray-700 border-gray-200',
                                        'text' => 'text-gray-700',
                                    ];
                                @endphp
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="py-3 px-4 text-center text-gray-400 font-mono">{{ $i + 1 }}</td>
                                    <td class="py-3 px-4 font-semibold text-gray-900">{{ $rec->supplier->perusahaan ?? '-' }}</td>
                                    <td class="py-3 px-4 font-mono text-gray-700 font-medium">{{ $rec->invoice->no_invoice ?? '-' }}</td>
                                    <td class="py-3 px-4 text-gray-600">
                                        {{ $rec->invoice?->invoice_date ? \Carbon\Carbon::parse($rec->invoice->invoice_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">
                                        {{ $rec->invoice?->due_date ? \Carbon\Carbon::parse($rec->invoice->due_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-semibold text-gray-700">
                                        {{ $rec->days_outstanding_computed }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $conf['badge'] }}">
                                            {{ $b }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-mono font-bold text-gray-900">
                                        {{ number_format($rec->remaining, 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50/90 border-t-2 border-gray-200 text-xs font-black text-gray-900">
                                <td colspan="7" class="py-3 px-4 text-right uppercase tracking-wider text-gray-600">Total Hutang Usaha</td>
                                <td class="py-3 px-4 text-right font-mono text-sm text-rose-700">
                                    {{ number_format($apRecords->sum('remaining'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @elseif($reportType !== 'receivables')
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-xs text-gray-400">
                Tidak ada data Account Payables pada periode ini.
            </div>
        @endif

    </main>

</body>
</html>
