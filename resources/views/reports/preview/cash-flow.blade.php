<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Arus Kas – {{ config('app.name', 'PT Duta Tunggal') }}</title>

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
            details {
                display: none !important;
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Laporan Arus Kas (Cash Flow Statement)</h1>
                    <p class="text-xs text-gray-500">Mutasi Kas Operasional, Investasi &amp; Pendanaan</p>
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

        {{-- Executive Header Card --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
            <span class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                {{ config('app.name', 'PT Duta Tunggal') }}
            </span>
            <h2 class="text-2xl font-black text-gray-900 tracking-tight">LAPORAN ARUS KAS</h2>
            <p class="text-xs sm:text-sm text-gray-500 mt-1">
                Untuk Periode {{ \Carbon\Carbon::parse($report['period']['start'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['period']['end'])->isoFormat('D MMMM GGGG') }}
                &nbsp;&bull;&nbsp; Metode: {{ strtoupper($report['method'] ?? 'Direct') }}
            </p>
        </div>

        {{-- Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Saldo Kas Awal</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-blue-700 mt-1">
                    Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Posisi awal periode</span>
            </div>

            @foreach($report['sections'] ?? [] as $sec)
                @php $tot = $sec['total'] ?? 0; @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ $sec['label'] ?? '-' }}</span>
                    <div class="text-lg sm:text-xl font-black font-mono tracking-tight {{ $tot >= 0 ? 'text-[#15803d]' : 'text-rose-600' }} mt-1">
                        @if($tot < 0)
                            (Rp {{ number_format(abs($tot), 0, ',', '.') }})
                        @else
                            Rp {{ number_format($tot, 0, ',', '.') }}
                        @endif
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1">Total arus kas bersih</span>
                </div>
            @endforeach

            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Saldo Kas Akhir</span>
                <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-amber-700 mt-1">
                    Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}
                </div>
                <span class="text-[10px] text-gray-400 mt-1">Posisi penutupan kas &amp; bank</span>
            </div>
        </div>

        @if(!empty($selectedBranches))
            <div class="p-3.5 bg-blue-50/70 border border-blue-200 rounded-xl text-xs text-blue-900 no-print flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <span>Cabang: <strong>{{ implode(', ', $selectedBranches) }}</strong></span>
            </div>
        @endif

        {{-- Cash Flow Statement Body --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            @foreach($report['sections'] ?? [] as $section)
                @php
                    $sKey = $section['key'] ?? 'other';
                    $secTotal = $section['total'] ?? 0;
                @endphp
                
                {{-- Section Header --}}
                <div class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    @if($sKey === 'operating')
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    @elseif($sKey === 'investing')
                        <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                    @else
                        <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                    @endif
                    <span>Aktivitas {{ strtoupper($section['label'] ?? '') }}</span>
                </div>

                {{-- Line Items --}}
                <div class="divide-y divide-gray-100 text-xs">
                    @foreach($section['items'] ?? [] as $item)
                        @php $amt = $item['amount'] ?? 0; @endphp
                        <div class="flex items-start justify-between px-6 py-3 hover:bg-gray-50/60 transition-colors">
                            <div class="flex-1 pr-4">
                                <div class="font-medium text-gray-800">{{ $item['label'] ?? '-' }}</div>
                                @if(!empty($item['metadata']['sources'] ?? []))
                                    <div class="text-[11px] text-gray-400 mt-0.5">Sumber: {{ implode(', ', $item['metadata']['sources']) }}</div>
                                @endif
                                @if(!empty($item['metadata']['detail'] ?? []))
                                    <details class="mt-1 text-[11px]">
                                        <summary class="cursor-pointer text-[#15803d] font-semibold hover:underline">Lihat Rincian</summary>
                                        <div class="mt-1.5 pl-3 border-l-2 border-emerald-200 space-y-0.5 text-gray-600">
                                            @foreach($item['metadata']['detail'] as $det)
                                                <div>{{ $det['customer'] ?? $det['customer_name'] ?? '-' }}: Rp {{ number_format($det['total'] ?? $det['amount'] ?? 0, 0, ',', '.') }}</div>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                            <span class="font-mono font-semibold whitespace-nowrap {{ $amt >= 0 ? 'text-gray-900' : 'text-rose-600' }}">
                                @if($amt < 0)
                                    (Rp {{ number_format(abs($amt), 0, ',', '.') }})
                                @else
                                    Rp {{ number_format($amt, 0, ',', '.') }}
                                @endif
                            </span>
                        </div>
                    @endforeach

                    {{-- Section Subtotal --}}
                    <div class="flex items-center justify-between px-6 py-3 bg-gray-50/70 font-bold border-t border-gray-200">
                        <span class="uppercase tracking-wider text-[11px] text-gray-700">Arus Kas Bersih — {{ $section['label'] ?? '' }}</span>
                        <span class="font-mono text-sm {{ $secTotal < 0 ? 'text-rose-600' : 'text-[#15803d]' }}">
                            @if($secTotal < 0)
                                (Rp {{ number_format(abs($secTotal), 0, ',', '.') }})
                            @else
                                Rp {{ number_format($secTotal, 0, ',', '.') }}
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach

            {{-- Net Cash Change --}}
            @php $netChange = $report['net_change'] ?? 0; @endphp
            <div class="flex items-center justify-between px-6 py-3.5 bg-amber-50/70 border-t-2 border-amber-200 text-xs font-bold text-amber-950">
                <span class="uppercase tracking-wider">Kenaikan (Penurunan) Bersih Kas</span>
                <span class="font-mono text-sm {{ $netChange < 0 ? 'text-rose-600' : 'text-amber-900' }}">
                    @if($netChange < 0)
                        (Rp {{ number_format(abs($netChange), 0, ',', '.') }})
                    @else
                        Rp {{ number_format($netChange, 0, ',', '.') }}
                    @endif
                </span>
            </div>

            {{-- Opening Balance Row --}}
            <div class="flex items-center justify-between px-6 py-3 bg-gray-50 border-t border-gray-200 text-xs font-semibold text-gray-700">
                <span>Saldo Kas Awal Periode</span>
                <span class="font-mono font-bold text-gray-900">Rp {{ number_format($report['opening_balance'], 0, ',', '.') }}</span>
            </div>

            {{-- Closing Balance Row --}}
            <div class="p-6 bg-emerald-700 text-white flex items-center justify-between">
                <div>
                    <span class="text-xs uppercase font-bold tracking-wider opacity-90 block">Posisi Kas Penutupan</span>
                    <h3 class="text-base sm:text-lg font-black tracking-tight mt-0.5">SALDO KAS AKHIR PERIODE</h3>
                </div>
                <div class="text-right">
                    <span class="font-mono text-xl sm:text-2xl font-black">
                        Rp {{ number_format($report['closing_balance'], 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Notes & Reading Tips --}}
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4.5 text-xs text-gray-600 no-print">
            <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[11px] mb-1.5">Klasifikasi Aktivitas Arus Kas:</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Arus Kas Operasional</span>
                    <span class="text-gray-500 text-[11px]">Penerimaan pelanggan, pembayaran supplier, dan biaya gaji</span>
                </div>
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Arus Kas Investasi</span>
                    <span class="text-gray-500 text-[11px]">Perolehan dan pelepasan aset tetap serta investasi jangka panjang</span>
                </div>
                <div class="p-2.5 rounded-lg bg-white border border-gray-200/80">
                    <span class="font-semibold text-gray-800 block">Arus Kas Pendanaan</span>
                    <span class="text-gray-500 text-[11px]">Penerimaan/pembayaran hutang bank, setoran modal, dan dividen</span>
                </div>
            </div>
        </div>

    </main>

</body>
</html>
