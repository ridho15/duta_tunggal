<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ageing Report – {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 0; }
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #1e293b; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; font-size: 1rem; font-weight: 700; margin: 0; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .pt-btn-print { background: #2563eb; color: #fff; }
        .pt-btn-close  { background: #475569; color: #fff; }
        .report-wrap { max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .rh { background: linear-gradient(135deg,#0f172a,#1e3a8a); color:#fff; border-radius:16px; padding:2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 8px 24px rgba(15,23,42,.35); }
        .rh-name { font-size:1.5rem; font-weight:800; }
        .rh-type { font-size:1.125rem; font-weight:600; opacity:.9; margin-top:.25rem; }
        .rh-date { font-size:.9rem; opacity:.75; margin-top:.25rem; }
        .grid-4 { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .badge-card { border-radius:12px; padding:1.1rem 1.5rem; border:1px solid; }
        .badge-card.current  { background:#f0fdf4; border-color:#bbf7d0; }
        .badge-card.d31_60   { background:#fefce8; border-color:#fde047; }
        .badge-card.d61_90   { background:#fff7ed; border-color:#fdba74; }
        .badge-card.over90   { background:#fef2f2; border-color:#fca5a5; }
        .badge-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#374151; margin-bottom:.35rem; }
        .badge-value { font-size:1.2rem; font-weight:800; font-family:monospace; }
        .badge-value.current { color:#15803d; }
        .badge-value.yellow  { color:#a16207; }
        .badge-value.orange  { color:#c2410c; }
        .badge-value.red     { color:#dc2626; }
        .cf-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; }
        @media(max-width:700px) { .cf-grid { grid-template-columns:1fr; } }
        .cf-box { border-radius:12px; padding:1.25rem 1.5rem; border:1px solid; }
        .cf-box.blue { background:#eff6ff; border-color:#bfdbfe; }
        .cf-box.red  { background:#fef2f2; border-color:#fecaca; }
        .cf-box h3 { font-size:1rem; font-weight:700; margin:0 0 .75rem; }
        .cf-box.blue h3 { color:#1e3a8a; }
        .cf-box.red  h3 { color:#7f1d1d; }
        .cf-row { display:flex; justify-content:space-between; font-size:.85rem; padding:.3rem 0; }
        .cf-row.br { border-top:1px solid; padding-top:.5rem; margin-top:.25rem; font-weight:700; }
        .cf-box.blue .cf-row.br { border-color:#bfdbfe; color:#1e3a8a; }
        .cf-box.red  .cf-row.br { border-color:#fecaca; color:#7f1d1d; }
        .section-hdr { font-size:1rem; font-weight:700; padding:.75rem 0 .4rem; margin-top:1rem; border-bottom:2px solid #e2e8f0; color:#1e293b; }
        .table-wrap { overflow-x:auto; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.07); margin-bottom:1.5rem; }
        table { width:100%; border-collapse:collapse; background:#fff; min-width:700px; }
        thead th { background:#1e293b; color:#fff; padding:.6rem 1rem; font-size:.78rem; font-weight:700; text-align:left; white-space:nowrap; }
        thead th:last-child,.col-right { text-align:right; }
        tbody tr { border-bottom:1px solid #f1f5f9; }
        tbody tr:hover { background:#f8fafc; }
        tbody td { padding:.5rem 1rem; font-size:.85rem; }
        .amount { font-family:monospace; font-weight:600; text-align:right; }
        .bucket { display:inline-block; padding:.2rem .6rem; border-radius:99px; font-size:.72rem; font-weight:700; }
        .bucket-current { background:#dcfce7; color:#15803d; }
        .bucket-31_60   { background:#fef9c3; color:#a16207; }
        .bucket-61_90   { background:#ffedd5; color:#c2410c; }
        .bucket-over90  { background:#fee2e2; color:#dc2626; }
        tfoot td { background:#f1f5f9; font-weight:800; padding:.65rem 1rem; font-size:.875rem; border-top:2px solid #cbd5e1; }
        tfoot td.amount { font-family:monospace; }
        .empty-state { text-align:center; padding:2rem; color:#94a3b8; font-size:.9rem; }
        @media print {
            body { background:#fff; }
            .print-toolbar,.no-print { display:none !important; }
            .table-wrap { box-shadow:none; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar no-print">
        <h1>&#128202; Ageing Report</h1>
        <button class="pt-btn pt-btn-print" onclick="window.print()">&#128424; Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">&#10005; Tutup</button>
    </div>

    <div class="report-wrap">
        <div class="rh">
            <div class="rh-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="rh-type">AGEING REPORT
                @if($reportType === 'receivables') — Account Receivables
                @elseif($reportType === 'payables') — Account Payables
                @else — AR &amp; AP@endif
            </div>
            <div class="rh-date">Per Tanggal {{ \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM GGGG') }}</div>
        </div>

        {{-- AR Aging Buckets (if not payables-only) --}}
        @if($reportType !== 'payables')
        <div style="font-weight:700;font-size:.9rem;margin-bottom:.5rem;color:#374151;">&#128200; Account Receivables — Aging Summary</div>
        <div class="grid-4 no-print">
            @php $buckets = ['Current'=>['cls'=>'current','vcls'=>'current','icon'=>'&#128994;'],'31–60'=>['cls'=>'d31_60','vcls'=>'yellow','icon'=>'&#128993;'],'61–90'=>['cls'=>'d61_90','vcls'=>'orange','icon'=>'&#128992;'],'>90'=>['cls'=>'over90','vcls'=>'red','icon'=>'&#128308;']]; @endphp
            @foreach($buckets as $bucket => $conf)
            <div class="badge-card {{ $conf['cls'] }}">
                <div class="badge-label">{!! $conf['icon'] !!} {{ $bucket === 'Current' ? '0–30 hari' : ($bucket === '>90' ? '&gt;90 hari' : $bucket . ' hari') }}</div>
                <div class="badge-value {{ $conf['vcls'] }}">Rp {{ number_format($arSummary[$bucket] ?? 0, 0, ',', '.') }}</div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- AP Aging Buckets (if not receivables-only) --}}
        @if($reportType !== 'receivables')
        <div style="font-weight:700;font-size:.9rem;margin-bottom:.5rem;color:#374151;">&#128201; Account Payables — Aging Summary</div>
        <div class="grid-4 no-print">
            @foreach($buckets as $bucket => $conf)
            <div class="badge-card {{ $conf['cls'] }}">
                <div class="badge-label">{!! $conf['icon'] !!} {{ $bucket === 'Current' ? '0–30 hari' : ($bucket === '>90' ? '&gt;90 hari' : $bucket . ' hari') }}</div>
                <div class="badge-value {{ $conf['vcls'] }}">Rp {{ number_format($apSummary[$bucket] ?? 0, 0, ',', '.') }}</div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Cash Flow Impact --}}
        <div class="cf-grid no-print">
            <div class="cf-box blue">
                <h3>&#128178; Cash Flow Impact</h3>
                <div class="cf-row"><span>Expected Cash Inflow (30 hari)</span><span style="font-family:monospace;">Rp {{ number_format($expectedInflow, 0, ',', '.') }}</span></div>
                <div class="cf-row"><span>Expected Cash Outflow (30 hari)</span><span style="font-family:monospace;">Rp {{ number_format($expectedOutflow, 0, ',', '.') }}</span></div>
                <div class="cf-row br"><span>Net Cash Flow</span><span style="font-family:monospace;">Rp {{ number_format($expectedInflow - $expectedOutflow, 0, ',', '.') }}</span></div>
            </div>
            <div class="cf-box red">
                <h3>&#9888; Risk Assessment</h3>
                <div class="cf-row"><span>Overdue Receivables</span><span style="font-family:monospace;">Rp {{ number_format($overdueAR, 0, ',', '.') }}</span></div>
                <div class="cf-row"><span>Overdue Payables</span><span style="font-family:monospace;">Rp {{ number_format($overdueAP, 0, ',', '.') }}</span></div>
                <div class="cf-row br"><span>Working Capital Gap</span><span style="font-family:monospace;">Rp {{ number_format($overdueAR - $overdueAP, 0, ',', '.') }}</span></div>
            </div>
        </div>

        {{-- AR Detail Table --}}
        @if($reportType !== 'payables' && $arRecords->isNotEmpty())
        <div class="section-hdr">&#128197; Detail Account Receivables</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>No Invoice</th>
                        <th>Tgl Invoice</th>
                        <th>Jatuh Tempo</th>
                        <th>Hari Beredar</th>
                        <th>Aging</th>
                        <th class="col-right">Sisa (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($arRecords as $i => $rec)
                    <tr>
                        <td style="color:#94a3b8;font-size:.75rem;">{{ $i + 1 }}</td>
                        <td>{{ $rec->customer->name ?? '-' }}</td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $rec->invoice->no_invoice ?? '-' }}</td>
                        <td>{{ $rec->invoice?->invoice_date ? \Carbon\Carbon::parse($rec->invoice->invoice_date)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $rec->invoice?->due_date ? \Carbon\Carbon::parse($rec->invoice->due_date)->format('d/m/Y') : '-' }}</td>
                        <td style="text-align:center;">{{ $rec->days_outstanding_computed }}</td>
                        <td>
                            @php $b = $rec->aging_bucket_computed; @endphp
                            <span class="bucket {{ $b === 'Current' ? 'bucket-current' : ($b === '31–60' ? 'bucket-31_60' : ($b === '61–90' ? 'bucket-61_90' : 'bucket-over90')) }}">{{ $b }}</span>
                        </td>
                        <td class="amount">{{ number_format($rec->remaining, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" style="font-weight:800;">TOTAL</td>
                        <td class="amount">{{ number_format($arRecords->sum('remaining'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @elseif($reportType !== 'payables')
        <div class="empty-state">Tidak ada data Account Receivables.</div>
        @endif

        {{-- AP Detail Table --}}
        @if($reportType !== 'receivables' && $apRecords->isNotEmpty())
        <div class="section-hdr">&#128201; Detail Account Payables</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier</th>
                        <th>No Invoice</th>
                        <th>Tgl Invoice</th>
                        <th>Jatuh Tempo</th>
                        <th>Hari Beredar</th>
                        <th>Aging</th>
                        <th class="col-right">Sisa (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($apRecords as $i => $rec)
                    <tr>
                        <td style="color:#94a3b8;font-size:.75rem;">{{ $i + 1 }}</td>
                        <td>{{ $rec->supplier->perusahaan ?? '-' }}</td>
                        <td style="font-family:monospace;font-size:.8rem;">{{ $rec->invoice->no_invoice ?? '-' }}</td>
                        <td>{{ $rec->invoice?->invoice_date ? \Carbon\Carbon::parse($rec->invoice->invoice_date)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $rec->invoice?->due_date ? \Carbon\Carbon::parse($rec->invoice->due_date)->format('d/m/Y') : '-' }}</td>
                        <td style="text-align:center;">{{ $rec->days_outstanding_computed }}</td>
                        <td>
                            @php $b = $rec->aging_bucket_computed; @endphp
                            <span class="bucket {{ $b === 'Current' ? 'bucket-current' : ($b === '31–60' ? 'bucket-31_60' : ($b === '61–90' ? 'bucket-61_90' : 'bucket-over90')) }}">{{ $b }}</span>
                        </td>
                        <td class="amount">{{ number_format($rec->remaining, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7" style="font-weight:800;">TOTAL</td>
                        <td class="amount">{{ number_format($apRecords->sum('remaining'), 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        @elseif($reportType !== 'receivables')
        <div class="empty-state">Tidak ada data Account Payables.</div>
        @endif
    </div>
</body>
</html>
