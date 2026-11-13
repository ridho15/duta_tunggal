<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Balance - {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}</title>

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
            @page {
                size: A4 landscape;
                margin: 10mm;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Neraca Saldo (Trial Balance)</h1>
                    <p class="text-xs text-gray-500">Daftar Akun Buku Besar, Mutasi Debit/Kredit &amp; Saldo Akhir</p>
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

    {{-- Main Shell --}}
    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <span class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                {{ config('app.name', 'PT Duta Tunggal') }}
            </span>
            <h2 class="text-2xl font-black text-gray-900 tracking-tight">NERACA SALDO (TRIAL BALANCE)</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Periode {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}
                @if($selectedCabang)
                    &nbsp;&bull;&nbsp; Cabang: {{ $selectedCabang->nama }}
                @else
                    &nbsp;&bull;&nbsp; Semua Cabang
                @endif
                &nbsp;&bull;&nbsp; {{ $showZeroBalance ? 'Termasuk saldo nol' : 'Tanpa saldo nol' }}
            </p>
        </div>

        {{-- 4 Meta KPI Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Tanggal Cetak</span>
                <div class="text-sm sm:text-base font-bold text-gray-900 mt-1">
                    {{ now()->format('d/m/Y H:i') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Waktu dokumen digenerate</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Akun</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-gray-900 mt-1">
                    {{ $report['rows']->count() }} Akun
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Jumlah akun aktif berelasi</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Periode Debit</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-[#15803d] mt-1">
                    {{ number_format((float) $report['grand_totals']['period_debit'], 2, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Total mutasi debit berjalan</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Periode Kredit</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-blue-700 mt-1">
                    {{ number_format((float) $report['grand_totals']['period_credit'], 2, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Total mutasi kredit berjalan</span>
            </div>
        </div>

        {{-- Table Container --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-xs" id="trial-balance-report">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="tb-data-table">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            <th class="py-3 px-4 w-28">No Akun</th>
                            <th class="py-3 px-4">Nama Akun</th>
                            <th class="py-3 px-3 w-20 text-center">Saldo Normal</th>
                            <th class="py-3 px-3 w-24 text-center">Tipe Akun</th>
                            <th class="py-3 px-4 text-right w-36">Saldo Awal</th>
                            <th class="py-3 px-4 text-right w-36">Mutasi Debit</th>
                            <th class="py-3 px-4 text-right w-36">Mutasi Kredit</th>
                            <th class="py-3 px-4 text-right w-36">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['rows'] as $row)
                            <tr class="{{ $row->is_parent ? 'bg-gray-50/80 font-bold text-gray-900' : 'hover:bg-gray-50/60 transition-colors text-gray-700' }}">
                                <td class="py-2.5 px-4 font-mono font-semibold {{ $row->is_parent ? 'text-gray-900' : 'text-gray-500' }}">
                                    {{ $row->code }}
                                </td>
                                <td class="py-2.5 px-4 {{ $row->is_parent ? 'pl-4' : 'pl-7' }}">
                                    {{ $row->name }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold {{ $row->normal_balance === 'D' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-purple-50 text-purple-700 border border-purple-200' }}">
                                        {{ $row->normal_balance }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center text-[10px] uppercase font-semibold text-gray-500">
                                    {{ strtoupper($row->type) }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-medium">
                                    {{ number_format((float) $row->beginning_balance, 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-medium text-emerald-800">
                                    {{ number_format((float) $row->period_debit, 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-medium text-blue-800">
                                    {{ number_format((float) $row->period_credit, 2, ',', '.') }}
                                </td>
                                <td class="py-2.5 px-4 text-right font-mono font-semibold {{ $row->ending_balance < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                                    @if($row->ending_balance < 0)
                                        ({{ number_format(abs((float) $row->ending_balance), 2, ',', '.') }})
                                    @else
                                        {{ number_format((float) $row->ending_balance, 2, ',', '.') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-gray-400">
                                    Tidak ada data untuk periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-800 text-white font-bold">
                            <td colspan="4" class="py-3 px-4 uppercase tracking-wider text-xs">TOTAL KESELURUHAN</td>
                            <td class="py-3 px-4 text-right font-mono font-black">{{ number_format((float) $report['grand_totals']['beginning_balance'], 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono font-black text-emerald-300">{{ number_format((float) $report['grand_totals']['period_debit'], 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono font-black text-blue-300">{{ number_format((float) $report['grand_totals']['period_credit'], 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono font-black">{{ number_format((float) $report['grand_totals']['ending_balance'], 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between text-xs text-gray-400 no-print">
            <span>Generated from Trial Balance Service &bull; PT Duta Tunggal</span>
            <span>ERP Financial Module</span>
        </div>

    </main>

</body>
</html>
