<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drill Down Financial Report - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fb;
            color: #0f172a;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            @page { size: A4 landscape; margin: 10mm; }
            .section, .detail-group, .summary-card { box-shadow: none; }
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #dbe4f0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .toolbar-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            border: 0;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            text-decoration: none;
        }
        .btn-print { background: linear-gradient(135deg, #0f766e, #14b8a6); }
        .btn-close { background: linear-gradient(135deg, #475569, #334155); }
        .btn-excel { background: linear-gradient(135deg, #166534, #22c55e); }
        .btn-pdf { background: linear-gradient(135deg, #991b1b, #ef4444); }
        .page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            color: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 16px 36px rgba(29, 78, 216, 0.22);
            margin-bottom: 18px;
        }
        .hero-kicker {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            opacity: 0.8;
            margin-bottom: 6px;
        }
        .hero-title {
            margin: 0;
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .hero-subtitle {
            margin-top: 8px;
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .summary-card {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .summary-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 6px;
        }
        .summary-value {
            font-size: 22px;
            font-weight: 900;
        }
        .summary-value.indigo { color: #4338ca; }
        .summary-value.green { color: #15803d; }
        .summary-value.red { color: #b91c1c; }
        .section,
        .detail-group {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .section-header,
        .detail-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #e0f2fe, #f8fafc);
            border-bottom: 1px solid #dbe4f0;
        }
        .section-title,
        .detail-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .section-subtitle,
        .detail-meta {
            font-size: 12px;
            color: #475569;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead tr {
            background: #0f172a;
            color: #fff;
        }
        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        th {
            text-align: left;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        td.text-right,
        th.text-right {
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            white-space: nowrap;
        }
        tbody tr:hover td {
            background: #f8fafc;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
        }
        .badge.asset { background: #dcfce7; color: #15803d; }
        .badge.liability { background: #fef9c3; color: #a16207; }
        .badge.equity { background: #dbeafe; color: #1d4ed8; }
        .badge.revenue { background: #f0fdf4; color: #16a34a; }
        .badge.expense { background: #fee2e2; color: #dc2626; }
        .total-row td {
            background: #eff6ff;
            font-weight: 800;
            color: #1e3a8a;
            border-top: 2px solid #93c5fd;
        }
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div class="toolbar-title">Drill Down Financial Report</div>
        <div class="toolbar-actions">
            <a class="btn btn-excel" href="{{ route('reports.drill-down-financial-report.excel', array_filter($report['filters'] ?? [], fn ($value) => $value !== null && $value !== '' && $value !== [])) }}">Download Excel</a>
            <a class="btn btn-pdf" href="{{ route('reports.drill-down-financial-report.pdf', array_filter($report['filters'] ?? [], fn ($value) => $value !== null && $value !== '' && $value !== [])) }}">Download PDF</a>
            <button class="btn btn-print" onclick="window.print()">Cetak / PDF</button>
            <button class="btn btn-close" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="page">
        <div class="hero">
            <div class="hero-kicker">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <h1 class="hero-title">Drill Down Financial Report</h1>
            <div class="hero-subtitle">
                Periode {{ $report['period'] }}
                <br>Cabang: {{ $selectedBranch?->nama ?? 'Semua cabang dalam scope akses' }}
                @if(!empty($report['filters']['account_type']))
                    <br>Tipe akun: {{ $report['filters']['account_type'] }}
                @endif
                @if(!empty($report['filters']['coa_id']))
                    <br>Filter COA aktif
                @endif
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Transaksi</div>
                <div class="summary-value indigo">{{ number_format($report['count']) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Debit</div>
                <div class="summary-value green">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Kredit</div>
                <div class="summary-value red">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</div>
            </div>
        </div>

        <section class="section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Detail per Akun</h2>
                    <div class="section-subtitle">{{ count($report['grouped']) }} akun ditemukan</div>
                </div>
                <div class="section-subtitle">{{ $report['period'] }}</div>
            </div>

            @forelse($report['grouped'] as $group)
                @php
                    $coaType = $group['coa']?->type;
                    $badgeClass = match($coaType) {
                        'Asset' => 'asset',
                        'Liability' => 'liability',
                        'Equity' => 'equity',
                        'Revenue' => 'revenue',
                        'Expense' => 'expense',
                        default => 'asset',
                    };
                @endphp
                <details class="detail-group">
                    <summary class="detail-summary">
                        <div>
                            <div class="detail-title">{{ $group['coa']?->code ?? '-' }} - {{ $group['coa']?->name ?? '-' }}</div>
                            <div class="detail-meta">{{ count($group['lines']) }} transaksi</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:14px; flex-wrap:wrap; justify-content:flex-end;">
                            <span class="badge {{ strtolower($badgeClass) }}">{{ $coaType ?? '-' }}</span>
                            <span class="detail-meta">Debit: Rp {{ number_format($group['total_debit'], 0, ',', '.') }}</span>
                            <span class="detail-meta">Kredit: Rp {{ number_format($group['total_credit'], 0, ',', '.') }}</span>
                            <span class="detail-meta">Saldo: Rp {{ number_format(abs($group['balance']), 0, ',', '.') }}{{ $group['balance'] < 0 ? ' (K)' : '' }}</span>
                        </div>
                    </summary>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Referensi</th>
                                    <th>Keterangan</th>
                                    <th class="text-right">Debit</th>
                                    <th class="text-right">Kredit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['lines'] as $line)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($line->date)->format('d/m/Y') }}</td>
                                        <td>{{ $line->reference ?: '—' }}</td>
                                        <td>{{ $line->description ?: '—' }}</td>
                                        <td class="text-right">{{ $line->debit > 0 ? 'Rp ' . number_format($line->debit, 0, ',', '.') : '—' }}</td>
                                        <td class="text-right">{{ $line->credit > 0 ? 'Rp ' . number_format($line->credit, 0, ',', '.') : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="total-row">
                                    <td colspan="3">Subtotal {{ $group['coa']?->name ?? '-' }}</td>
                                    <td class="text-right">Rp {{ number_format($group['total_debit'], 0, ',', '.') }}</td>
                                    <td class="text-right">Rp {{ number_format($group['total_credit'], 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </details>
            @empty
                <div class="empty-state">Tidak ada transaksi yang sesuai dengan filter yang dipilih.</div>
            @endforelse
        </section>
    </div>
</body>
</html>