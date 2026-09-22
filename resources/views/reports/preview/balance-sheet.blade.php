<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Posisi Keuangan – {{ config('app.name') }}</title>
    <style>
        /* ── Shared ── */
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        /* ── Print toolbar ── */
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #1e293b; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; font-size: 1rem; font-weight: 700; margin: 0; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .pt-btn-print { background: #2563eb; color: #fff; }
        .pt-btn-close  { background: #475569; color: #fff; }
        /* ── Content wrapper ── */
        .report-wrap { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
        /* ── Classic Balance Sheet ── */
        .bs-classic-wrapper { font-family: Arial, 'Helvetica Neue', sans-serif; font-size: 12px; color: #222; background: #fff; padding: 0; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.07); }
        .bs-classic-header { text-align: center; padding: 12px 0 8px; border-bottom: 2px solid #00a8cc; }
        .bs-classic-company { font-size: 15px; font-weight: 800; color: #0d2b4e; letter-spacing: .04em; text-transform: uppercase; }
        .bs-classic-title { font-size: 26px; font-weight: 900; background: linear-gradient(90deg,#00bcd4 0%,#0288d1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; letter-spacing: .06em; line-height: 1.2; margin: 2px 0; }
        .bs-classic-date { font-size: 12px; font-weight: 700; color: #e65100; margin-top: 2px; }
        .bs-outer-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .bs-outer-table > thead > tr > th { background: linear-gradient(135deg,#0097a7 0%,#006064 100%); color: #fff; font-size: 13px; font-weight: 800; text-align: center; padding: 8px 4px; letter-spacing: .05em; border: 1px solid #006064; }
        .bs-outer-table > thead > tr.bs-col-sub-header > th { background: #e0f7fa; font-size: 11px; font-weight: 700; color: #004d40; padding: 4px 6px; border: 1px solid #b2ebf2; }
        .bs-outer-table > thead > tr.bs-col-sub-header > th:first-child { border-right: 2px solid #00acc1; }
        .bs-col-left { width: 50%; vertical-align: top; border-right: 2px solid #00acc1; padding: 0; }
        .bs-col-right { width: 50%; vertical-align: top; padding: 0; }
        .bs-sub-header-row { display: flex; background: #e0f7fa; border-bottom: 1px solid #b2ebf2; font-size: 10px; font-weight: 700; color: #004d40; }
        .bs-sub-header-row .bs-th-code { width: 90px; padding: 4px 6px; border-right: 1px solid #b2ebf2; }
        .bs-sub-header-row .bs-th-name { flex: 1; padding: 4px 6px; border-right: 1px solid #b2ebf2; }
        .bs-sub-header-row .bs-th-bal  { width: 110px; padding: 4px 6px; text-align: right; }
        .bs-inner-table { width: 100%; border-collapse: collapse; }
        .bs-parent-row td { background: #b2ebf2; font-weight: 800; font-size: 11px; color: #004d40; padding: 4px 6px; border-bottom: 1px solid #80deea; }
        .bs-parent-td-code { width: 90px; }
        .bs-child-row td { font-size: 11px; color: #333; padding: 3px 6px; border-bottom: 1px solid #e8f5e9; }
        .bs-child-row:hover td { background: #f9fbe7; }
        .bs-td-code  { width: 90px; font-family: 'Courier New', monospace; color: #546e7a; font-size: 10px; }
        .bs-td-child-name { padding-left: 4px; }
        .bs-td-amount { width: 110px; text-align: right; font-family: 'Courier New', monospace; font-weight: 600; white-space: nowrap; }
        .bs-negative { color: #c62828 !important; }
        .bs-total-row td { font-weight: 800; font-size: 11px; color: #0d47a1; padding: 4px 6px; background: #e3f2fd; border-top: 1px solid #90caf9; border-bottom: 2px solid #42a5f5; }
        .bs-total-row .bs-td-amount { font-weight: 800; color: #0d47a1; }
        .bs-grand-total-row td { background: linear-gradient(135deg,#0d47a1 0%,#1565c0 100%); color: #fff; font-size: 12px; font-weight: 900; padding: 7px 8px; border-top: 3px solid #0d47a1; }
        .bs-grand-total-row td:first-child { border-right: 2px solid #fff; }
        .bs-grand-total-amount { text-align: right; font-family: 'Courier New', monospace; }
        .bs-status-row td { text-align: center; font-weight: 700; font-size: 11px; padding: 5px; }
        .bs-status-ok   { background: #e8f5e9; color: #1b5e20; }
        .bs-status-fail { background: #ffebee; color: #b71c1c; }
        .bs-classic-footer { display: flex; justify-content: space-between; padding: 8px 6px 4px; font-size: 10px; color: #546e7a; border-top: 1px solid #b0bec5; margin-top: 4px; }
        .bs-unbalanced-warning { background: #fff3e0; border: 1px solid #ffb74d; color: #e65100; padding: 6px 10px; font-size: 11px; font-weight: 700; margin-bottom: 6px; }
        /* ── Standard mode ── */
        .fr-report-header { background: linear-gradient(135deg,#1e3a5f 0%,#2563eb 100%); color: #fff; border-radius: 16px; padding: 2rem; margin-bottom: 1.5rem; text-align: center; box-shadow: 0 8px 24px rgba(37,99,235,.25); }
        .fr-company-name { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; }
        .fr-report-type { font-size: 1.125rem; font-weight: 600; opacity: .9; margin-top: .25rem; }
        .fr-report-period { font-size: .9rem; opacity: .75; margin-top: .25rem; }
        .fr-card-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .fr-card { background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .fr-card-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .35rem; }
        .fr-card-value { font-size: 1.5rem; font-weight: 800; }
        .fr-card-value.green { color: #059669; }
        .fr-card-value.amber { color: #d97706; }
        .fr-card-value.blue  { color: #2563eb; }
        .fr-card-value.red   { color: #dc2626; }
        .fr-body { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 1.5rem; }
        .fr-section-hdr { display: flex; align-items: center; gap: .75rem; padding: .9rem 1.5rem; font-size: 1rem; font-weight: 700; color: #fff; letter-spacing: .03em; }
        .fr-section-hdr.green { background: linear-gradient(135deg,#059669,#10b981); }
        .fr-section-hdr.amber { background: linear-gradient(135deg,#d97706,#f59e0b); }
        .fr-section-hdr.blue  { background: linear-gradient(135deg,#1d4ed8,#3b82f6); }
        .fr-sub-hdr { padding: .6rem 1.5rem; font-size: .85rem; font-weight: 700; color: #374151; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
        .fr-row { display: flex; justify-content: space-between; align-items: center; padding: .55rem 1.5rem .55rem 2.5rem; border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
        .fr-row:hover { background: #f8fafc; }
        .fr-row-code { color: #94a3b8; font-size: .78rem; margin-right: .4rem; font-family: monospace; }
        .fr-row-name { flex: 1; color: #374151; }
        .fr-row-amount { font-weight: 600; font-family: monospace; font-size: .9rem; white-space: nowrap; }
        .fr-row-amount.negative { color: #dc2626; }
        .fr-subtotal { display: flex; justify-content: space-between; align-items: center; padding: .65rem 1.5rem; background: #f1f5f9; border-top: 2px solid #94a3b8; }
        .fr-subtotal-label { font-weight: 700; font-size: .875rem; color: #374151; }
        .fr-subtotal-amount { font-weight: 700; font-size: .9rem; font-family: monospace; }
        .fr-total { display: flex; justify-content: space-between; align-items: center; padding: .85rem 1.5rem; background: linear-gradient(135deg,#1e3a5f,#1e40af); color: #fff; }
        .fr-total-label { font-weight: 800; font-size: 1rem; }
        .fr-total-amount { font-weight: 800; font-size: 1.05rem; font-family: monospace; }
        .fr-balance-check { margin: 0; padding: .75rem 1.5rem; font-weight: 700; font-size: .875rem; text-align: center; }
        .fr-balance-check.ok   { background: #dcfce7; color: #166534; }
        .fr-balance-check.fail { background: #fee2e2; color: #991b1b; }
        .fr-notes { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1rem 1.5rem; font-size: .85rem; color: #92400e; margin-bottom: 1rem; }
        .fr-notes-title { font-weight: 700; margin-bottom: .4rem; }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none !important; }
            .bs-classic-wrapper { box-shadow: none; }
            .fr-body { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <h1>&#128196; Laporan Posisi Keuangan (Neraca)</h1>
        <button class="pt-btn pt-btn-print" onclick="window.print()">&#128424; Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">&#10005; Tutup</button>
    </div>

    <div class="report-wrap">

        @if($classicView && $classicData)
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- CLASSIC TWO-COLUMN BALANCE SHEET                                  --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php
            $cd  = $classicData;
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
            <div class="bs-unbalanced-warning">&#9888; Neraca tidak seimbang — selisih: {{ $fmtAmt($cd['difference']) }}</div>
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
                                <span class="bs-th-code">Account No</span>
                                <span class="bs-th-name">Account Name</span>
                                <span class="bs-th-bal">Balance</span>
                            </div>
                        </th>
                        <th>
                            <div class="bs-sub-header-row">
                                <span class="bs-th-code">Account No</span>
                                <span class="bs-th-name">Account Name</span>
                                <span class="bs-th-bal">Balance</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="bs-col-left">
                            <table class="bs-inner-table">
                                @forelse($cd['asset_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-td-code bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td class="bs-parent-td-name" colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $child)
                                    <tr class="bs-child-row">
                                        <td class="bs-td-code">{{ $child['code'] }}</td>
                                        <td class="bs-td-name bs-td-child-name">-- {{ $child['name'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($child['balance']) ? 'bs-negative' : '' }}">{{ $fmtAmt($child['balance']) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="bs-total-row">
                                        <td class="bs-td-code"></td>
                                        <td style="font-weight:800;">{{ $group['total_label'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}"><strong>{{ $fmtAmt($group['total']) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" style="padding:8px 6px;color:#9e9e9e;font-style:italic;">Tidak ada data aset.</td></tr>
                                @endforelse
                            </table>
                        </td>
                        <td class="bs-col-right">
                            <table class="bs-inner-table">
                                @foreach($cd['liability_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-td-code bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td class="bs-parent-td-name" colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $child)
                                    <tr class="bs-child-row">
                                        <td class="bs-td-code">{{ $child['code'] }}</td>
                                        <td class="bs-td-name bs-td-child-name">-- {{ $child['name'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($child['balance']) ? 'bs-negative' : '' }}">{{ $fmtAmt($child['balance']) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="bs-total-row">
                                        <td class="bs-td-code"></td>
                                        <td style="font-weight:800;">{{ $group['total_label'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}"><strong>{{ $fmtAmt($group['total']) }}</strong></td>
                                    </tr>
                                @endforeach
                                @foreach($cd['equity_groups'] as $group)
                                    <tr class="bs-parent-row">
                                        <td class="bs-td-code bs-parent-td-code">{{ $group['parent_code'] }}</td>
                                        <td class="bs-parent-td-name" colspan="2">{{ $group['parent_name'] }}</td>
                                    </tr>
                                    @foreach($group['children'] as $child)
                                    <tr class="bs-child-row">
                                        <td class="bs-td-code">{{ $child['code'] }}</td>
                                        <td class="bs-td-name bs-td-child-name">-- {{ $child['name'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($child['balance']) ? 'bs-negative' : '' }}">{{ $fmtAmt($child['balance']) }}</td>
                                    </tr>
                                    @endforeach
                                    <tr class="bs-total-row">
                                        <td class="bs-td-code"></td>
                                        <td style="font-weight:800;">{{ $group['total_label'] }}</td>
                                        <td class="bs-td-amount {{ $isNeg($group['total']) ? 'bs-negative' : '' }}"><strong>{{ $fmtAmt($group['total']) }}</strong></td>
                                    </tr>
                                @endforeach
                                @if(abs($cd['retained_earnings']) > 0.005)
                                <tr class="bs-child-row">
                                    <td class="bs-td-code"></td>
                                    <td class="bs-td-name bs-td-child-name">-- Laba Ditahan (Retained Earnings)</td>
                                    <td class="bs-td-amount {{ $isNeg($cd['retained_earnings']) ? 'bs-negative' : '' }}">{{ $fmtAmt($cd['retained_earnings']) }}</td>
                                </tr>
                                @endif
                                @if(empty($cd['liability_groups']) && empty($cd['equity_groups']))
                                    <tr><td colspan="3" style="padding:8px 6px;color:#9e9e9e;font-style:italic;">Tidak ada data kewajiban &amp; ekuitas.</td></tr>
                                @endif
                            </table>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="bs-grand-total-row">
                        <td>TOTAL ASSET <span class="bs-grand-total-amount" style="float:right;">{{ $fmtAmt($cd['total_assets']) }}</span></td>
                        <td>TOTAL LIABILITIES &amp; EQUITY <span class="bs-grand-total-amount" style="float:right;">{{ $fmtAmt($cd['total_liabilities_and_equity']) }}</span></td>
                    </tr>
                    <tr class="bs-status-row">
                        <td colspan="2" class="{{ $cd['is_balanced'] ? 'bs-status-ok' : 'bs-status-fail' }}">
                            @if($cd['is_balanced'])&#10003; Neraca Seimbang — Total Aset = Total Kewajiban &amp; Ekuitas
                            @else &#9888; Neraca Tidak Seimbang — Selisih: {{ $fmtAmt($cd['difference']) }}@endif
                        </td>
                    </tr>
                </tfoot>
            </table>

            <div class="bs-classic-footer no-print">
                <span>Designed by : Key Software Accounting</span>
                <span>{{ config('app.name', 'PT. DUTA TUNGGAL') }} &mdash; {{ $asOfFormatted }}</span>
            </div>
        </div>

        @else
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        {{-- STANDARD LAYOUT                                                    --}}
        {{-- ══════════════════════════════════════════════════════════════════ --}}
        @php
            $asOfFormatted = \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM GGGG');
        @endphp

        @if(!$data['balanced'])
        <div class="fr-notes" style="border-color:#fca5a5;background:#fef2f2;color:#991b1b;">
            <div class="fr-notes-title">&#9888; Neraca Tidak Seimbang</div>
            <p>{{ $data['balance_warning'] }}</p>
            <p style="margin-top:.35rem;font-weight:700;">Selisih aktual: Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}</p>
        </div>
        @endif

        <div class="fr-report-header">
            <div class="fr-company-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fr-report-type">LAPORAN POSISI KEUANGAN (NERACA)</div>
            <div class="fr-report-period">Per {{ $asOfFormatted }}</div>
        </div>

        <div class="fr-card-grid no-print">
            <div class="fr-card">
                <div class="fr-card-label">&#127968; Total Aset</div>
                <div class="fr-card-value green">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#128196; Total Kewajiban</div>
                <div class="fr-card-value amber">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#127981; Total Ekuitas</div>
                <div class="fr-card-value blue">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#9878; Status Neraca</div>
                @if($data['balanced'])
                    <div class="fr-card-value green" style="font-size:1.1rem;">&#10003; Seimbang</div>
                @else
                    <div class="fr-card-value red" style="font-size:1.1rem;">&#10007; Tidak Seimbang</div>
                @endif
            </div>
        </div>

        <div class="fr-body">
            <div class="fr-section-hdr green">&#127968; ASET</div>
            @foreach($data['assets'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name"><span class="fr-row-code">{{ $row['coa']->code }}</span>{{ $row['coa']->name }}</span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="fr-total">
                <span class="fr-total-label">TOTAL ASET</span>
                <span class="fr-total-amount">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</span>
            </div>

            <div class="fr-section-hdr amber">&#128196; LIABILITAS (KEWAJIBAN)</div>
            @foreach($data['liabilities'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name"><span class="fr-row-code">{{ $row['coa']->code }}</span>{{ $row['coa']->name }}</span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="fr-total" style="background:linear-gradient(135deg,#92400e,#d97706);">
                <span class="fr-total-label">TOTAL LIABILITAS</span>
                <span class="fr-total-amount">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</span>
            </div>

            <div class="fr-section-hdr blue">&#127981; EKUITAS (MODAL)</div>
            @foreach($data['equity'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name"><span class="fr-row-code">{{ $row['coa']->code }}</span>{{ $row['coa']->name }}</span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            @if($data['retained_earnings'] != 0)
                <div class="fr-row">
                    <span class="fr-row-name"><span class="fr-row-code">3400</span>Laba Ditahan</span>
                    <span class="fr-row-amount {{ $data['retained_earnings'] < 0 ? 'negative' : '' }}">
                        @if($data['retained_earnings'] < 0)(Rp {{ number_format(abs($data['retained_earnings']), 0, ',', '.') }})
                        @else Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}@endif
                    </span>
                </div>
            @endif
            @if(isset($data['current_earnings']) && $data['current_earnings'] != 0)
                <div class="fr-row">
                    <span class="fr-row-name"><span class="fr-row-code">3500</span>Laba Periode Berjalan</span>
                    <span class="fr-row-amount {{ $data['current_earnings'] < 0 ? 'negative' : '' }}">
                        @if($data['current_earnings'] < 0)(Rp {{ number_format(abs($data['current_earnings']), 0, ',', '.') }})
                        @else Rp {{ number_format($data['current_earnings'], 0, ',', '.') }}@endif
                    </span>
                </div>
            @endif
            <div class="fr-total" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
                <span class="fr-total-label">TOTAL EKUITAS</span>
                <span class="fr-total-amount">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</span>
            </div>

            <div class="fr-total" style="background:linear-gradient(135deg,#0f172a,#1e293b);font-size:1.1rem;padding:1rem 1.5rem;">
                <span class="fr-total-label" style="font-size:1.1rem;">TOTAL LIABILITAS &amp; EKUITAS</span>
                <span class="fr-total-amount" style="font-size:1.1rem;">Rp {{ number_format($data['liab_total'] + $data['equity_total'], 0, ',', '.') }}</span>
            </div>

            <div class="fr-balance-check {{ $data['balanced'] ? 'ok' : 'fail' }}">
                @if($data['balanced'])&#10003; Neraca Seimbang — Total Aset = Total Liabilitas + Ekuitas
                @else &#9888; Neraca Tidak Seimbang — Selisih: Rp {{ number_format(abs($data['asset_total'] - ($data['liab_total'] + $data['equity_total'])), 0, ',', '.') }}@endif
            </div>
        </div>

        @if($data['has_unbalanced_entries'])
        <div class="fr-notes" style="border-color:#fca5a5;background:#fef2f2;color:#991b1b;">
            <div class="fr-notes-title">&#9888; Peringatan: Terdapat Jurnal Tidak Seimbang</div>
            <p>Ada {{ count($data['unbalanced_entries']) }} jurnal dengan total debit ≠ total kredit. Harap verifikasi data jurnal.</p>
        </div>
        @endif

        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Catatan:</div>
            <ul style="list-style:disc;padding-left:1.2rem;margin-top:.25rem;line-height:1.7;">
                <li>Akumulasi penyusutan ditampilkan sebagai pengurang Aset Tetap</li>
                <li>Total Aset = Total Liabilitas + Total Ekuitas</li>
                <li>Data diambil dari Buku Besar (General Ledger) per tanggal yang dipilih</li>
            </ul>
        </div>

        @endif {{-- end classic/standard --}}
    </div>
</body>
</html>
