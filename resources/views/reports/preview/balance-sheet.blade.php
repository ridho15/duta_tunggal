<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Posisi Keuangan – {{ config('app.name', 'PT Duta Tunggal') }}</title>

    {{-- Typography & Tailwind Assets --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 0; }
        .report-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }

        /* ── Classic Balance Sheet ── */
        .bs-classic-wrapper { font-family: Arial, 'Helvetica Neue', sans-serif; font-size: 12px; color: #222; background: #fff; padding: 0; border-radius: 12px; border: 1px solid #e2e8f0; }
        .bs-classic-header { text-align: center; padding: 16px 0 12px; border-bottom: 2px solid #0f766e; }
        .bs-classic-company { font-size: 15px; font-weight: 800; color: #0f172a; letter-spacing: .04em; text-transform: uppercase; }
        .bs-classic-title { font-size: 24px; font-weight: 900; color: #0f172a; letter-spacing: .06em; line-height: 1.2; margin: 4px 0 2px; }
        .bs-classic-date { font-size: 12px; font-weight: 700; color: #475569; margin-top: 2px; }
        .bs-outer-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .bs-outer-table > thead > tr > th { background: #0f766e; color: #fff; font-size: 13px; font-weight: 800; text-align: center; padding: 8px 4px; letter-spacing: .05em; border: 1px solid #0d5f58; }
        .bs-outer-table > thead > tr.bs-col-sub-header > th { background: #f0fdfa; font-size: 11px; font-weight: 700; color: #115e59; padding: 4px 6px; border: 1px solid #ccfbf1; }
        .bs-outer-table > thead > tr.bs-col-sub-header > th:first-child { border-right: 2px solid #0f766e; }
        .bs-col-left { width: 50%; vertical-align: top; border-right: 2px solid #0f766e; padding: 0; }
        .bs-col-right { width: 50%; vertical-align: top; padding: 0; }
        .bs-sub-header-row { display: flex; background: #f0fdfa; border-bottom: 1px solid #ccfbf1; font-size: 10px; font-weight: 700; color: #115e59; }
        .bs-sub-header-row .bs-th-code { width: 90px; padding: 4px 6px; border-right: 1px solid #ccfbf1; }
        .bs-sub-header-row .bs-th-name { flex: 1; padding: 4px 6px; border-right: 1px solid #ccfbf1; }
        .bs-sub-header-row .bs-th-bal  { width: 110px; padding: 4px 6px; text-align: right; }
        .bs-inner-table { width: 100%; border-collapse: collapse; }
        .bs-parent-row td { background: #f0fdfa; font-weight: 800; font-size: 11px; color: #115e59; padding: 4px 6px; border-bottom: 1px solid #99f6e4; }
        .bs-parent-td-code { width: 90px; }
        .bs-child-row td { font-size: 11px; color: #333; padding: 3px 6px; border-bottom: 1px solid #f1f5f9; }
        .bs-child-row:hover td { background: #f8fafc; }
        .bs-td-code  { width: 90px; font-family: monospace; color: #64748b; font-size: 10px; }
        .bs-td-child-name { padding-left: 4px; }
        .bs-td-amount { width: 110px; text-align: right; font-family: monospace; font-weight: 600; white-space: nowrap; }
        .bs-negative { color: #dc2626 !important; }
        .bs-total-row td { font-weight: 800; font-size: 11px; color: #0f766e; padding: 4px 6px; background: #f0fdfa; border-top: 1px solid #99f6e4; border-bottom: 2px solid #0f766e; }
        .bs-total-row .bs-td-amount { font-weight: 800; color: #0f766e; }
        .bs-grand-total-row td { background: #0f172a; color: #fff; font-size: 12px; font-weight: 900; padding: 7px 8px; border-top: 3px solid #0f172a; }
        .bs-grand-total-row td:first-child { border-right: 2px solid #fff; }
        .bs-grand-total-amount { text-align: right; font-family: monospace; }
        .bs-status-row td { text-align: center; font-weight: 700; font-size: 11px; padding: 5px; }
        .bs-status-ok   { background: #ecfdf5; color: #065f46; }
        .bs-status-fail { background: #fef2f2; color: #991b1b; }
        .bs-classic-footer { display: flex; justify-content: space-between; padding: 8px 6px 4px; font-size: 10px; color: #64748b; border-top: 1px solid #e2e8f0; margin-top: 4px; }
        .bs-unbalanced-warning { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 6px 10px; font-size: 11px; font-weight: 700; margin-bottom: 6px; }

        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .bs-classic-wrapper { border: 1px solid #ccc; }
        }
    </style>
</head>
<body class="min-h-screen antialiased">

    {{-- Top Action Bar --}}
    <header class="no-print sticky top-0 z-50 bg-white/95 backdrop-blur-md border-b border-gray-200 px-4 sm:px-6 py-3">
        <div class="max-w-6xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#15803d] flex items-center justify-center font-bold text-sm border border-emerald-200 shadow-2xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-sm font-bold text-gray-900 leading-tight">Laporan Posisi Keuangan (Neraca)</h1>
                    <p class="text-xs text-gray-500">Struktur Aset, Liabilitas, dan Ekuitas Perusahaan</p>
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

    <div class="report-wrap">

        @if($classicView && $classicData)
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- CLASSIC TWO-COLUMN BALANCE SHEET                                  --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php
            $cd = $classicData;
            $asOfFormatted = \Carbon\Carbon::parse($asOfDate)->translatedFormat('d-F-Y');
            $fmtAmt = function($v) {
                if ($v < 0) return '(' . number_format(abs($v), 2, '.', ',') . ')';
                return number_format($v, 2, '.', ',');
            };
            $isNeg = fn($v) => $v < 0;
        @endphp

        <div class="bs-classic-wrapper">
            <div class="bs-classic-header">
                <div class="bs-classic-company">{{ config('app.name', 'PT. DUTA TUNGGAL') }}</div>
                <div class="bs-classic-title">BALANCE SHEET</div>
                <div class="bs-classic-date">As Of : {{ $asOfFormatted }}</div>
            </div>

            @if(!$cd['is_balanced'])
            <div class="bs-unbalanced-warning">Neraca tidak seimbang — selisih: {{ $fmtAmt($cd['difference']) }}</div>
            @endif

            <table class="bs-outer-table">
                <thead>
                    <tr>
                        <th>ASSET</th>
                        <th>LIABILITIES &amp; EQUITY</th>
                    </tr>
                    <tr class="bs-col-sub-header">
                        <th>
                            <div class="bs-sub-header-row">
                                <span class="bs-th-code">Account No.</span>
                                <span class="bs-th-name">Account Name</span>
                                <span class="bs-th-bal">Balance</span>
                            </div>
                        </th>
                        <th>
                            <div class="bs-sub-header-row">
                                <span class="bs-th-code">Account No.</span>
                                <span class="bs-th-name">Account Name</span>
                                <span class="bs-th-bal">Balance</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        {{-- LEFT COLUMN: ASSETS --}}
                        <td class="bs-col-left">
                            <table class="bs-inner-table">
                                @foreach($cd['asset_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $item)
                                        <tr class="bs-child-row">
                                            <td class="bs-td-code">{{ $item['code'] }}</td>
                                            <td class="bs-td-child-name">{{ $item['name'] }}</td>
                                            <td class="bs-td-amount {{ $isNeg($item['balance']) ? 'bs-negative' : '' }}">
                                                {{ $fmtAmt($item['balance']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="bs-total-row">
                                        <td colspan="2">TOTAL {{ strtoupper($group['parent_name']) }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}">
                                            {{ $fmtAmt($group['total']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>

                        {{-- RIGHT COLUMN: LIABILITIES & EQUITY --}}
                        <td class="bs-col-right">
                            <table class="bs-inner-table">
                                {{-- Liabilities --}}
                                @foreach($cd['liability_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $item)
                                        <tr class="bs-child-row">
                                            <td class="bs-td-code">{{ $item['code'] }}</td>
                                            <td class="bs-td-child-name">{{ $item['name'] }}</td>
                                            <td class="bs-td-amount {{ $isNeg($item['balance']) ? 'bs-negative' : '' }}">
                                                {{ $fmtAmt($item['balance']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="bs-total-row">
                                        <td colspan="2">TOTAL {{ strtoupper($group['parent_name']) }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}">
                                            {{ $fmtAmt($group['total']) }}
                                        </td>
                                    </tr>
                                @endforeach

                                {{-- Equity --}}
                                @foreach($cd['equity_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $item)
                                        <tr class="bs-child-row">
                                            <td class="bs-td-code">{{ $item['code'] }}</td>
                                            <td class="bs-td-child-name">{{ $item['name'] }}</td>
                                            <td class="bs-td-amount {{ $isNeg($item['balance']) ? 'bs-negative' : '' }}">
                                                {{ $fmtAmt($item['balance']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    @if($group['parent_code'] === '3000' && ($cd['retained_earnings'] != 0 || $cd['current_earnings'] != 0))
                                        @if($cd['retained_earnings'] != 0)
                                            <tr class="bs-child-row">
                                                <td class="bs-td-code">3400</td>
                                                <td class="bs-td-child-name">Laba Ditahan</td>
                                                <td class="bs-td-amount {{ $isNeg($cd['retained_earnings']) ? 'bs-negative' : '' }}">
                                                    {{ $fmtAmt($cd['retained_earnings']) }}
                                                </td>
                                            </tr>
                                        @endif
                                        @if($cd['current_earnings'] != 0)
                                            <tr class="bs-child-row">
                                                <td class="bs-td-code">3500</td>
                                                <td class="bs-td-child-name">Laba Periode Berjalan</td>
                                                <td class="bs-td-amount {{ $isNeg($cd['current_earnings']) ? 'bs-negative' : '' }}">
                                                    {{ $fmtAmt($cd['current_earnings']) }}
                                                </td>
                                            </tr>
                                        @endif
                                    @endif
                                    <tr class="bs-total-row">
                                        <td colspan="2">TOTAL {{ strtoupper($group['parent_name']) }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}">
                                            {{ $fmtAmt($group['total']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- GRAND TOTAL ROW --}}
                    <tr class="bs-grand-total-row">
                        <td>
                            <table style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td style="font-weight:900;">TOTAL ASSET</td>
                                    <td class="bs-grand-total-amount" style="width:110px;">
                                        {{ $fmtAmt($cd['total_assets']) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table style="width:100%;border-collapse:collapse;">
                                <tr>
                                    <td style="font-weight:900;">TOTAL LIABILITIES &amp; EQUITY</td>
                                    <td class="bs-grand-total-amount" style="width:110px;">
                                        {{ $fmtAmt($cd['total_liabilities_and_equity']) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- STATUS ROW --}}
                    <tr class="bs-status-row">
                        <td colspan="2" class="{{ $cd['is_balanced'] ? 'bs-status-ok' : 'bs-status-fail' }}">
                            @if($cd['is_balanced'])
                                NERACA SEIMBANG (BALANCE)
                            @else
                                TIDAK SEIMBANG — Selisih: {{ $fmtAmt($cd['difference']) }}
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="bs-classic-footer">
                <span>{{ config('app.name', 'PT Duta Tunggal') }} — Sistem ERP</span>
                <span>Dicetak: {{ now()->translatedFormat('d-F-Y H:i') }}</span>
            </div>
        </div>

        @else
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STANDARD VIEW (Clean Executive Modern ERP Layout)                 --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php
            $asOfFormatted = \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM GGGG');
        @endphp

        <div class="space-y-6">
            @if(!$data['balanced'])
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900">
                    <div class="font-bold mb-1">Perhatian: Neraca Tidak Seimbang</div>
                    <p>{{ $data['balance_warning'] }}</p>
                    <p class="mt-1 font-mono font-bold">Selisih aktual: Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}</p>
                </div>
            @endif

            {{-- Executive Header Card --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 text-center">
                <span class="text-xs font-bold uppercase tracking-wider text-[#15803d] bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200/60 inline-block mb-2">
                    {{ config('app.name', 'PT Duta Tunggal') }}
                </span>
                <h2 class="text-2xl font-black text-gray-900 tracking-tight">LAPORAN POSISI KEUANGAN (NERACA)</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-1">
                    Posisi Per Tanggal {{ $asOfFormatted }}
                </p>
            </div>

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 no-print">
                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Aset</span>
                    <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-[#15803d] mt-1">
                        Rp {{ number_format($data['asset_total'], 0, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1">Aset Lancar &amp; Tidak Lancar</span>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Kewajiban</span>
                    <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-amber-700 mt-1">
                        Rp {{ number_format($data['liab_total'], 0, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1">Liabilitas Jangka Pendek &amp; Panjang</span>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Total Ekuitas</span>
                    <div class="text-lg sm:text-xl font-black font-mono tracking-tight text-blue-700 mt-1">
                        Rp {{ number_format($data['equity_total'], 0, ',', '.') }}
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1">Modal disetor &amp; Laba ditahan</span>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Status Neraca</span>
                    <div class="mt-1">
                        @if($data['balanced'])
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                Seimbang (Balanced)
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200">
                                Tidak Seimbang
                            </span>
                        @endif
                    </div>
                    <span class="text-[10px] text-gray-400 mt-1">Aset = Liabilitas + Ekuitas</span>
                </div>
            </div>

            {{-- Balance Sheet Body --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden text-xs">

                {{-- 1. ASET --}}
                <div class="bg-gray-50 border-b border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                    <span>1. Aset (Aktiva)</span>
                </div>
                @foreach($data['assets'] as $group)
                    <div class="bg-gray-50/50 px-6 py-2 border-b border-gray-100 font-bold text-gray-600 uppercase tracking-wide text-[11px]">
                        {{ $group['parent'] }}
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($group['items'] as $row)
                            @php $bal = $row['balance']; @endphp
                            <div class="flex items-center justify-between px-6 py-2.5 hover:bg-gray-50/60 transition-colors">
                                <span class="text-gray-700">
                                    <span class="font-mono text-gray-400 mr-2 text-[11px]">{{ $row['coa']->code }}</span>
                                    {{ $row['coa']->name }}
                                </span>
                                <span class="font-mono font-semibold {{ $bal < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                                    @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                                </span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/70 border-t border-gray-200 font-semibold text-gray-700">
                            <span>Subtotal {{ $group['parent'] }}</span>
                            <span class="font-mono font-bold text-gray-900">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="flex items-center justify-between px-6 py-3.5 bg-emerald-50/60 border-t-2 border-emerald-200 font-bold text-emerald-950">
                    <span class="uppercase tracking-wider text-[11px]">Total Aset</span>
                    <span class="font-mono text-sm text-[#15803d]">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</span>
                </div>

                {{-- 2. LIABILITAS --}}
                <div class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-600"></span>
                    <span>2. Liabilitas (Kewajiban)</span>
                </div>
                @foreach($data['liabilities'] as $group)
                    <div class="bg-gray-50/50 px-6 py-2 border-b border-gray-100 font-bold text-gray-600 uppercase tracking-wide text-[11px]">
                        {{ $group['parent'] }}
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($group['items'] as $row)
                            @php $bal = $row['balance']; @endphp
                            <div class="flex items-center justify-between px-6 py-2.5 hover:bg-gray-50/60 transition-colors">
                                <span class="text-gray-700">
                                    <span class="font-mono text-gray-400 mr-2 text-[11px]">{{ $row['coa']->code }}</span>
                                    {{ $row['coa']->name }}
                                </span>
                                <span class="font-mono font-semibold {{ $bal < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                                    @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                                </span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/70 border-t border-gray-200 font-semibold text-gray-700">
                            <span>Subtotal {{ $group['parent'] }}</span>
                            <span class="font-mono font-bold text-gray-900">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach
                <div class="flex items-center justify-between px-6 py-3.5 bg-amber-50/60 border-t-2 border-amber-200 font-bold text-amber-950">
                    <span class="uppercase tracking-wider text-[11px]">Total Liabilitas</span>
                    <span class="font-mono text-sm text-amber-800">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</span>
                </div>

                {{-- 3. EKUITAS --}}
                <div class="bg-gray-50 border-y border-gray-200 px-6 py-2.5 text-xs font-bold text-gray-700 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                    <span>3. Ekuitas (Modal)</span>
                </div>
                @foreach($data['equity'] as $group)
                    <div class="bg-gray-50/50 px-6 py-2 border-b border-gray-100 font-bold text-gray-600 uppercase tracking-wide text-[11px]">
                        {{ $group['parent'] }}
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($group['items'] as $row)
                            @php $bal = $row['balance']; @endphp
                            <div class="flex items-center justify-between px-6 py-2.5 hover:bg-gray-50/60 transition-colors">
                                <span class="text-gray-700">
                                    <span class="font-mono text-gray-400 mr-2 text-[11px]">{{ $row['coa']->code }}</span>
                                    {{ $row['coa']->name }}
                                </span>
                                <span class="font-mono font-semibold {{ $bal < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                                    @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                                </span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between px-6 py-2.5 bg-gray-50/70 border-t border-gray-200 font-semibold text-gray-700">
                            <span>Subtotal {{ $group['parent'] }}</span>
                            <span class="font-mono font-bold text-gray-900">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    </div>
                @endforeach

                @if($data['retained_earnings'] != 0)
                    <div class="flex items-center justify-between px-6 py-2.5 hover:bg-gray-50/60 transition-colors border-t border-gray-100">
                        <span class="text-gray-700">
                            <span class="font-mono text-gray-400 mr-2 text-[11px]">3400</span>Laba Ditahan
                        </span>
                        <span class="font-mono font-semibold {{ $data['retained_earnings'] < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                            @if($data['retained_earnings'] < 0)(Rp {{ number_format(abs($data['retained_earnings']), 0, ',', '.') }})
                            @else Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endif

                @if(isset($data['current_earnings']) && $data['current_earnings'] != 0)
                    <div class="flex items-center justify-between px-6 py-2.5 hover:bg-gray-50/60 transition-colors border-t border-gray-100">
                        <span class="text-gray-700">
                            <span class="font-mono text-gray-400 mr-2 text-[11px]">3500</span>Laba Periode Berjalan
                        </span>
                        <span class="font-mono font-semibold {{ $data['current_earnings'] < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                            @if($data['current_earnings'] < 0)(Rp {{ number_format(abs($data['current_earnings']), 0, ',', '.') }})
                            @else Rp {{ number_format($data['current_earnings'], 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endif

                <div class="flex items-center justify-between px-6 py-3.5 bg-blue-50/60 border-t-2 border-blue-200 font-bold text-blue-950">
                    <span class="uppercase tracking-wider text-[11px]">Total Ekuitas</span>
                    <span class="font-mono text-sm text-blue-800">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</span>
                </div>

                {{-- TOTAL LIABILITAS & EKUITAS --}}
                <div class="p-6 bg-slate-800 text-white flex items-center justify-between">
                    <div>
                        <span class="text-xs uppercase font-bold tracking-wider opacity-80 block">Keseimbangan Neraca</span>
                        <h3 class="text-base sm:text-lg font-black tracking-tight mt-0.5">TOTAL LIABILITAS &amp; EKUITAS</h3>
                    </div>
                    <div class="text-right">
                        <span class="font-mono text-xl sm:text-2xl font-black">
                            Rp {{ number_format($data['liab_total'] + $data['equity_total'], 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Balance check alert banner --}}
                <div class="px-6 py-3 text-center text-xs font-bold {{ $data['balanced'] ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">
                    @if($data['balanced'])
                        Neraca Seimbang — Total Aset = Total Liabilitas + Ekuitas
                    @else
                        Neraca Tidak Seimbang — Selisih: Rp {{ number_format(abs($data['asset_total'] - ($data['liab_total'] + $data['equity_total'])), 0, ',', '.') }}
                    @endif
                </div>

            </div>

            @if($data['has_unbalanced_entries'])
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-900">
                    <div class="font-bold mb-1">Peringatan: Terdapat Jurnal Tidak Seimbang</div>
                    <p>Ada {{ count($data['unbalanced_entries']) }} jurnal dengan total debit ≠ total kredit. Harap verifikasi data jurnal.</p>
                </div>
            @endif

            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4.5 text-xs text-gray-600 no-print">
                <h4 class="font-bold text-gray-900 uppercase tracking-wider text-[11px] mb-1.5">Catatan Teknis Akuntansi:</h4>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Akumulasi penyusutan ditampilkan sebagai pengurang Aset Tetap</li>
                    <li>Total Aset = Total Liabilitas + Total Ekuitas</li>
                    <li>Data diambil dari Buku Besar (General Ledger) per tanggal yang dipilih</li>
                </ul>
            </div>
        </div>

        @endif {{-- end classic/standard --}}
    </div>
</body>
</html>
