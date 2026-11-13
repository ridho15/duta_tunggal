<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan HPP – {{ config('app.name', 'PT Duta Tunggal') }}</title>

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
                    <p class="text-xs text-gray-500">Kalkulasi Bahan Baku, Tenaga Kerja &amp; Biaya Overhead</p>
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
    <main class="max-w-4xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        @php
            $raw = $report['raw_materials'];
            $overhead = $report['overhead'];
            $wip = $report['wip'];
            $dataQuality = $report['data_quality'] ?? [];
            $warnings = $dataQuality['warnings'] ?? [];
        @endphp

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <span class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                {{ config('app.name', 'PT Duta Tunggal') }}
            </span>
            <h2 class="text-2xl font-black text-gray-900 tracking-tight">LAPORAN HARGA POKOK PRODUKSI</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Periode {{ \Carbon\Carbon::parse($report['period']['start'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['period']['end'])->isoFormat('D MMMM GGGG') }}
            </p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Bahan Baku Digunakan</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-blue-700 mt-1">
                    Rp {{ number_format($raw['used'], 2, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Awal + Pembelian - Akhir</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Biaya Produksi</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-amber-700 mt-1">
                    Rp {{ number_format($report['production_cost'], 2, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Bahan Baku + BTKL + BOP</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Harga Pokok Produksi</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-[#15803d] mt-1">
                    Rp {{ number_format($report['cogm'], 2, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Total selesai periode ini</span>
            </div>
        </div>

        @if(!empty($selectedBranches))
            <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl text-xs text-blue-900 no-print flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Cabang: <strong>{{ implode(', ', $selectedBranches) }}</strong></span>
            </div>
        @endif

        @if(!empty($warnings))
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-900 no-print">
                <div class="font-bold mb-1">Peringatan Kualitas Data HPP</div>
                <p>Laporan ini menggunakan fallback untuk sebagian nilai. Periksa sumber posting jurnal atau stok berikut:</p>
                <ul class="list-disc pl-5 mt-1.5 space-y-0.5">
                    @foreach($warnings as $warning)
                        <li>{{ is_array($warning) ? ($warning['message'] ?? json_encode($warning)) : $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- HPP Table --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-xs">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                        <th class="py-3 px-6">Deskripsi Elemen Biaya</th>
                        <th class="py-3 px-6 text-right">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    {{-- I. Bahan Baku --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px]">
                        <td colspan="2" class="py-2.5 px-6">I. Bahan Baku</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">Persediaan Awal Bahan Baku</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">{{ number_format($raw['opening'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">+ Pembelian Bahan Baku</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">{{ number_format($raw['purchases'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="bg-gray-50/60 font-semibold text-gray-800">
                        <td class="py-2 px-6 pl-8">= Total Bahan Baku Tersedia</td>
                        <td class="py-2 px-6 text-right font-mono text-gray-900">{{ number_format($raw['available'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">- Persediaan Akhir Bahan Baku</td>
                        <td class="py-2.5 px-6 text-right font-mono text-rose-600">({{ number_format($raw['closing'], 2, ',', '.') }})</td>
                    </tr>
                    <tr class="bg-blue-50/50 font-bold text-blue-950 border-t border-gray-200">
                        <td class="py-2.5 px-6">= Bahan Baku yang Digunakan</td>
                        <td class="py-2.5 px-6 text-right font-mono text-sm text-blue-800">{{ number_format($raw['used'], 2, ',', '.') }}</td>
                    </tr>

                    {{-- II. Tenaga Kerja Langsung --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">II. Biaya Tenaga Kerja Langsung</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">+ Biaya Tenaga Kerja Langsung</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">{{ number_format($report['direct_labor'], 2, ',', '.') }}</td>
                    </tr>

                    {{-- III. Biaya Overhead Pabrik --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">III. Biaya Overhead Pabrik</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">
                            <div>+ Biaya Overhead Pabrik</div>
                            @if(!empty($overhead['items']))
                                <div class="mt-1 space-y-0.5 pl-3 border-l-2 border-gray-200 text-[11px] text-gray-500">
                                    @foreach($overhead['items'] as $item)
                                        <div class="flex items-center justify-between py-0.5">
                                            <span>{{ $item['label'] }}</span>
                                            <span class="font-mono">Rp {{ number_format($item['amount'], 2, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900 align-top">{{ number_format($overhead['total'], 2, ',', '.') }}</td>
                    </tr>

                    {{-- Total Biaya Produksi --}}
                    <tr class="bg-amber-50/60 font-bold text-amber-950 border-t border-gray-200">
                        <td class="py-2.5 px-6">= Total Biaya Produksi</td>
                        <td class="py-2.5 px-6 text-right font-mono text-sm text-amber-800">{{ number_format($report['production_cost'], 2, ',', '.') }}</td>
                    </tr>

                    {{-- IV. Barang Dalam Proses (WIP) --}}
                    <tr class="bg-gray-50 font-bold text-gray-700 uppercase tracking-wide text-[11px] border-t border-gray-200">
                        <td colspan="2" class="py-2.5 px-6">IV. Barang Dalam Proses (WIP)</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">+ Persediaan Awal WIP</td>
                        <td class="py-2.5 px-6 text-right font-mono text-gray-900">{{ number_format($wip['opening'], 2, ',', '.') }}</td>
                    </tr>
                    <tr class="hover:bg-gray-50/60">
                        <td class="py-2.5 px-6 pl-8 text-gray-700">- Persediaan Akhir WIP</td>
                        <td class="py-2.5 px-6 text-right font-mono text-rose-600">({{ number_format($wip['closing'], 2, ',', '.') }})</td>
                    </tr>
                </tbody>

                <tfoot>
                    <tr class="bg-slate-800 text-white font-bold">
                        <td class="py-4 px-6 uppercase tracking-wider text-xs">
                            HARGA POKOK PRODUKSI (Cost of Goods Manufactured)
                        </td>
                        <td class="py-4 px-6 text-right font-mono text-base sm:text-lg font-black">
                            {{ number_format($report['cogm'], 2, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </main>

</body>
</html>
