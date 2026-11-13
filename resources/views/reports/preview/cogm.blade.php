<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harga Pokok Produksi (COGM) – {{ config('app.name', 'PT Duta Tunggal') }}</title>

    {{-- Typography & Tailwind Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

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
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm border border-emerald-200 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Laporan Harga Pokok Produksi (COGM)</h1>
                    <p class="text-xs text-gray-500">Kalkulasi Biaya Bahan, Tenaga Kerja &amp; WIP Manufaktur</p>
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

    {{-- Main Report Shell --}}
    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <span class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                {{ config('app.name', 'PT Duta Tunggal') }}
            </span>
            <h2 class="text-2xl font-black text-gray-900 tracking-tight">LAPORAN HARGA POKOK PRODUKSI</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Periode: {{ $startDate->isoFormat('D MMMM YYYY') }} s/d {{ $endDate->isoFormat('D MMMM YYYY') }}
                @if($selectedCabang)
                    &nbsp;&bull;&nbsp; Cabang: <strong>{{ $selectedCabang->nama }}</strong>
                @endif
                @if($selectedProduct)
                    &nbsp;&bull;&nbsp; Produk: <strong>{{ $selectedProduct->name }}</strong>
                @endif
            </p>
        </div>

        {{-- Meta Info Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Periode Mulai</span>
                <div class="text-sm font-bold text-gray-900 mt-1">{{ $startDate->format('d/m/Y') }}</div>
                <span class="text-[10px] text-gray-400 mt-1">Awal rentang laporan</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Periode Akhir</span>
                <div class="text-sm font-bold text-gray-900 mt-1">{{ $endDate->format('d/m/Y') }}</div>
                <span class="text-[10px] text-gray-400 mt-1">Akhir rentang laporan</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Cabang</span>
                <div class="text-sm font-bold text-gray-900 mt-1 truncate">{{ $selectedCabang ? $selectedCabang->nama : 'Semua Cabang' }}</div>
                <span class="text-[10px] text-gray-400 mt-1">Filter entitas cabang</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Jumlah MO</span>
                <div class="text-lg font-black font-mono text-gray-900 mt-1">{{ $report['mo_count'] }} Order</div>
                <span class="text-[10px] text-gray-400 mt-1">Total manufacturing order</span>
            </div>
        </div>

        {{-- 4 Summary Metric Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Bahan Baku Terpakai</span>
                <div class="text-base sm:text-lg font-black font-mono tracking-tight text-blue-700 mt-1">
                    Rp {{ number_format($report['raw_material_used'], 0, ',', '.') }}
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Tenaga Kerja</span>
                <div class="text-base sm:text-lg font-black font-mono tracking-tight text-gray-900 mt-1">
                    Rp {{ number_format($report['labor_cost'], 0, ',', '.') }}
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Overhead Pabrik</span>
                <div class="text-base sm:text-lg font-black font-mono tracking-tight text-amber-700 mt-1">
                    Rp {{ number_format($report['overhead'], 0, ',', '.') }}
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Nilai Akhir COGM</span>
                <div class="text-base sm:text-lg font-black font-mono tracking-tight text-[#15803d] mt-1">
                    Rp {{ number_format($report['cogm'], 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- COGM Statement Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-xs">
            <div class="px-6 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Rincian Perhitungan COGM</h3>
                <span class="text-[11px] text-gray-400 font-medium">{{ $startDate->isoFormat('D MMM YYYY') }} – {{ $endDate->isoFormat('D MMM YYYY') }}</span>
            </div>

            <table class="w-full text-left border-collapse">
                <tbody class="divide-y divide-gray-100">
                    {{-- I. Saldo Awal WIP --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px]">
                        <td colspan="2" class="py-2.5 px-6">I. Saldo Awal WIP (Barang Dalam Proses)</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Persediaan Awal Barang Dalam Proses</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900 font-semibold">Rp {{ number_format($report['opening_wip'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- II. Biaya Produksi Periode Ini --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">II. Biaya Produksi Periode Ini</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Bahan Baku Terpakai (Raw Material Used)</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">Rp {{ number_format($report['raw_material_used'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Biaya Tenaga Kerja Langsung (Direct Labor)</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">Rp {{ number_format($report['labor_cost'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Biaya Overhead Pabrik (Manufacturing Overhead)</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">Rp {{ number_format($report['overhead'], 0, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-blue-50/50 font-bold text-blue-950 border-t border-gray-200">
                        <td class="py-2.5 px-6">= Total Biaya Produksi Ditambahkan</td>
                        <td class="py-2.5 px-6 text-right font-mono text-sm text-blue-800">Rp {{ number_format($report['total_cost_added'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- III. Total WIP Tersedia --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">III. Total WIP Tersedia</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Saldo Awal WIP + Total Biaya Produksi</td>
                        <td class="py-2.5 px-6 text-right font-mono font-bold text-gray-900">Rp {{ number_format($report['total_wip'], 0, ',', '.') }}</td>
                    </tr>

                    {{-- IV. Saldo Akhir WIP --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">IV. Saldo Akhir WIP</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Dikurangi: Persediaan Akhir Barang Dalam Proses</td>
                        <td class="py-2.5 px-6 text-right font-mono text-rose-600 font-semibold">(Rp {{ number_format($report['closing_wip'], 0, ',', '.') }})</td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr class="bg-slate-800 text-white font-bold">
                        <td class="py-4 px-6 uppercase tracking-wider text-xs">
                            HARGA POKOK PRODUKSI (Cost of Goods Manufactured)
                        </td>
                        <td class="py-4 px-6 text-right font-mono text-base sm:text-lg font-black text-emerald-300">
                            Rp {{ number_format($report['cogm'], 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Manufacturing Orders Detail --}}
        @if($report['mo_count'] > 0)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-xs">
                <div class="px-6 py-3.5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700">Detail Manufacturing Orders ({{ $report['mo_count'] }} Order)</h3>
                    <span class="text-[11px] text-gray-400 font-medium">Rincian Surat Perintah Kerja</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                <th class="py-3 px-4">No. MO</th>
                                <th class="py-3 px-4">Produk</th>
                                <th class="py-3 px-4 text-center">Qty Target</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4 text-right">Tanggal Dibuat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($report['orders'] as $mo)
                                @php
                                    $productName = optional(optional($mo->productionPlan)->product)->name ?? '-';
                                    $status = $mo->status ?? 'draft';
                                    $badgeClass = match($status) {
                                        'completed'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'in_progress' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        default       => 'bg-gray-50 text-gray-700 border-gray-200',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="py-2.5 px-4 font-mono font-semibold text-gray-900">{{ $mo->mo_number ?? '#' . $mo->id }}</td>
                                    <td class="py-2.5 px-4 font-medium text-gray-800">{{ $productName }}</td>
                                    <td class="py-2.5 px-4 text-center font-mono">{{ number_format($mo->quantity ?? 0) }}</td>
                                    <td class="py-2.5 px-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $badgeClass }}">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 text-right text-gray-500">
                                        {{ \Carbon\Carbon::parse($mo->created_at)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between text-xs text-gray-400 no-print">
            <span>Dicetak pada {{ now()->isoFormat('D MMMM YYYY, HH:mm') }} &bull; {{ config('app.name', 'PT Duta Tunggal') }}</span>
            <span>ERP Manufacturing &amp; Costing Module</span>
        </div>

    </main>

</body>
</html>
