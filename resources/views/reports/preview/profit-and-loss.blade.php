<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi – {{ config('app.name', 'PT Duta Tunggal') }}</title>

    {{-- Typography & Tailwind Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
        rel="stylesheet">
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

            .shadow-sm,
            .shadow-md,
            .shadow-lg,
            .shadow-2xs {
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
                <div
                    class="w-9 h-9 rounded-xl bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm border border-emerald-200 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Laporan Laba Rugi (Income Statement)</h1>
                    <p class="text-xs text-gray-500">Kinerja Pendapatan, Beban &amp; Profitabilitas Bersih</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-[#15803d] text-white text-xs font-semibold hover:bg-emerald-800 shadow-2xs transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
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

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <span
                class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                {{ config('app.name', 'PT Duta Tunggal') }}
            </span>
            <h2 class="text-2xl font-black text-gray-900 tracking-tight">LAPORAN LABA RUGI</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Untuk Periode {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM GGGG') }}
            </p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Pendapatan Usaha</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-[#15803d] mt-1">
                    Rp {{ number_format($data['revenue'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Total penjualan kotor/bersih</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Laba Kotor</span>
                <div
                    class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $data['gross_profit'] >= 0 ? 'text-blue-700' : 'text-rose-600' }} mt-1">
                    Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Penjualan dikurangi HPP</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Laba Usaha (EBIT)</span>
                <div
                    class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $data['operating_profit'] >= 0 ? 'text-blue-700' : 'text-rose-600' }} mt-1">
                    Rp {{ number_format($data['operating_profit'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Laba kotor dikurangi biaya operasional</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Laba Bersih</span>
                <div
                    class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $data['net_profit'] >= 0 ? 'text-[#15803d]' : 'text-rose-600' }} mt-1">
                    Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Hasil akhir setelah pajak</span>
            </div>
        </div>

        {{-- Financial Statement Breakdown Body --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- 1. Pendapatan Usaha --}}
            <div
                class="bg-gray-50 border-b border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                <span>1. Pendapatan Usaha (Penjualan)</span>
            </div>
            <div class="divide-y divide-gray-100 text-xs">
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="text-gray-700 font-medium">Penjualan Bersih</span>
                    <span class="font-mono font-semibold text-gray-900">Rp
                        {{ number_format($data['revenue'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3 bg-emerald-50/50 font-bold">
                    <span class="text-emerald-950 uppercase tracking-wider text-[11px]">Subtotal Pendapatan Usaha</span>
                    <span class="font-mono text-emerald-800 text-sm">Rp
                        {{ number_format($data['revenue'], 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- 2. HPP --}}
            <div
                class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                <span>2. Harga Pokok Penjualan (HPP)</span>
            </div>
            @php $cogs = $data['revenue'] - $data['gross_profit']; @endphp
            <div class="divide-y divide-gray-100 text-xs">
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="text-gray-700 font-medium">Beban Pokok Penjualan</span>
                    <span class="font-mono font-semibold text-rose-600">(Rp
                        {{ number_format($cogs, 0, ',', '.') }})</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3 bg-blue-50/60 font-bold">
                    <span class="text-blue-950 uppercase tracking-wider text-[11px]">Laba Kotor (Gross Profit)</span>
                    <span
                        class="font-mono text-sm {{ $data['gross_profit'] < 0 ? 'text-rose-600' : 'text-blue-800' }}">
                        @if ($data['gross_profit'] < 0)
                            (Rp {{ number_format(abs($data['gross_profit']), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}
                        @endif
                    </span>
                </div>
            </div>

            {{-- 3. Beban Operasional --}}
            <div
                class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                <span>3. Beban Operasional</span>
            </div>
            @php $totalOpEx = max(0, $data['gross_profit'] - $data['operating_profit']); @endphp
            <div class="divide-y divide-gray-100 text-xs">
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="text-gray-700 font-medium">Total Beban Operasional</span>
                    <span class="font-mono font-semibold text-rose-600">(Rp
                        {{ number_format($totalOpEx, 0, ',', '.') }})</span>
                </div>
                <div class="flex items-center justify-between px-6 py-3 bg-blue-50/60 font-bold">
                    <span class="text-blue-950 uppercase tracking-wider text-[11px]">Laba Usaha (Operating Profit /
                        EBIT)</span>
                    <span
                        class="font-mono text-sm {{ $data['operating_profit'] < 0 ? 'text-rose-600' : 'text-blue-800' }}">
                        @if ($data['operating_profit'] < 0)
                            (Rp {{ number_format(abs($data['operating_profit']), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($data['operating_profit'], 0, ',', '.') }}
                        @endif
                    </span>
                </div>
            </div>

            {{-- 4. Pendapatan / Beban Lain-lain --}}
            <div
                class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                <span>4. Pendapatan / (Beban) Lain-lain</span>
            </div>
            <div class="divide-y divide-gray-100 text-xs">
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="text-gray-700 font-medium">
                        @if ($data['other_net'] > 0)
                            + Pendapatan Lain-lain
                        @elseif($data['other_net'] < 0)
                            - Beban Bunga &amp; Lain-lain
                        @else
                            Pendapatan / Beban Lain-lain
                        @endif
                    </span>
                    <span
                        class="font-mono font-semibold {{ $data['other_net'] < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                        @if ($data['other_net'] < 0)
                            (Rp {{ number_format(abs($data['other_net']), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($data['other_net'], 0, ',', '.') }}
                        @endif
                    </span>
                </div>
                <div class="flex items-center justify-between px-6 py-3 bg-amber-50/60 font-bold">
                    <span class="text-amber-950 uppercase tracking-wider text-[11px]">Laba Sebelum Pajak (EBT)</span>
                    <span
                        class="font-mono text-sm {{ $data['profit_before_tax'] < 0 ? 'text-rose-600' : 'text-amber-800' }}">
                        @if ($data['profit_before_tax'] < 0)
                            (Rp {{ number_format(abs($data['profit_before_tax']), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($data['profit_before_tax'], 0, ',', '.') }}
                        @endif
                    </span>
                </div>
            </div>

            {{-- 5. Beban Pajak Penghasilan --}}
            <div
                class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-purple-600"></span>
                <span>5. Beban Pajak Penghasilan</span>
            </div>
            <div class="divide-y divide-gray-100 text-xs">
                <div class="flex items-center justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                    <span class="text-gray-700 font-medium">- Pajak Penghasilan (PPh)</span>
                    <span class="font-mono font-semibold text-rose-600">(Rp
                        {{ number_format($data['tax'], 0, ',', '.') }})</span>
                </div>
            </div>

            {{-- Hasil Akhir: Laba / Rugi Bersih --}}
            <div
                class="p-6 {{ $data['net_profit'] >= 0 ? 'bg-emerald-700 text-white' : 'bg-rose-700 text-white' }} flex items-center justify-between">
                <div>
                    <span class="text-xs uppercase font-bold tracking-wider opacity-90 block">Hasil Akhir
                        Periode</span>
                    <h3 class="text-base sm:text-lg font-black tracking-tight mt-0.5">
                        {{ $data['net_profit'] >= 0 ? 'LABA BERSIH (Net Income)' : 'RUGI BERSIH (Net Loss)' }}
                    </h3>
                </div>
                <div class="text-right">
                    <span class="font-mono text-xl sm:text-2xl font-black">
                        @if ($data['net_profit'] < 0)
                            (Rp {{ number_format(abs($data['net_profit']), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                        @endif
                    </span>
                </div>
            </div>
        </div>

        {{-- Notes & Formula Footnote --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4.5 text-xs text-gray-600 no-print">
            <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[11px] mb-1.5">Standar Akuntansi
                Perhitungan:</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Laba Kotor</span>
                    <span class="text-gray-500 font-mono text-[11px]">Penjualan Bersih - HPP</span>
                </div>
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Laba Usaha (EBIT)</span>
                    <span class="text-gray-500 font-mono text-[11px]">Laba Kotor - Beban Operasional</span>
                </div>
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Laba Bersih</span>
                    <span class="text-gray-500 font-mono text-[11px]">Laba Sebelum Pajak - Pajak PPh</span>
                </div>
            </div>
        </div>

    </main>

</body>

</html>
