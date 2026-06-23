<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trial Balance - {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            @page { size: A4 landscape; margin: 12mm; }
        }

        .page {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        .toolbar {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            margin-bottom: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
        }

        .toolbar .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
        }

        .btn-print { background: linear-gradient(135deg, #0f766e, #115e59); }
        .btn-close { background: linear-gradient(135deg, #475569, #334155); }

        .header {
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 60%, #14b8a6 100%);
            color: #fff;
            border-radius: 20px;
            padding: 26px 28px;
            margin-bottom: 18px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.16);
        }

        .brand {
            font-size: 13px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            opacity: 0.86;
            margin-bottom: 6px;
        }

        .title {
            font-size: 30px;
            font-weight: 900;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            margin-top: 8px;
            font-size: 14px;
            color: rgba(255,255,255,.88);
        }

        .meta {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .meta-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .meta-label {
            font-size: 11px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .meta-value {
            margin-top: 6px;
            font-size: 15px;
            font-weight: 800;
            color: #111827;
        }

        .table-wrap {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(15, 23, 42, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        thead th {
            background: #0f766e;
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            white-space: nowrap;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-right: 1px solid rgba(255,255,255,.12);
        }

        thead th:last-child { border-right: 0; }

        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #ecfeff; }

        tbody td {
            padding: 10px;
            border-top: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .center { text-align: center; }
        .code { font-weight: 800; }
        .name { font-weight: 600; }
        .parent { background: #f0fdfa !important; font-weight: 800; }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-d { background: #dbeafe; color: #1d4ed8; }
        .badge-c { background: #fce7f3; color: #be185d; }

        tfoot td {
            background: #0f172a;
            color: #fff;
            font-weight: 900;
            padding: 12px 10px;
            border-top: 2px solid #0f766e;
        }

        .empty {
            padding: 40px 20px;
            text-align: center;
            color: #6b7280;
        }

        .footer {
            margin-top: 14px;
            color: #6b7280;
            font-size: 11px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 1024px) {
            .meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px) {
            .page { padding: 14px; }
            .meta { grid-template-columns: 1fr; }
            .title { font-size: 22px; }
            .toolbar { flex-direction: column; align-items: stretch; }
            .toolbar .actions { justify-content: stretch; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="toolbar no-print">
        <div>
            <strong>Preview Trial Balance</strong>
        </div>
        <div class="actions">
            <button class="btn btn-print" onclick="window.print()">Cetak / PDF</button>
            <button class="btn btn-close" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="header">
        <div class="brand">{{ config('app.name', 'DUTA TUNGGAL ERP') }}</div>
        <h1 class="title">Trial Balance</h1>
        <div class="subtitle">
            Periode {{ $startDate->format('d/m/Y') }} s/d {{ $endDate->format('d/m/Y') }}
            @if($selectedCabang)
                | Cabang: {{ $selectedCabang->nama }}
            @else
                | Semua Cabang
            @endif
            | {{ $showZeroBalance ? 'Termasuk saldo nol' : 'Tanpa saldo nol' }}
        </div>
    </div>

    <div class="meta">
        <div class="meta-card">
            <div class="meta-label">Tanggal Cetak</div>
            <div class="meta-value">{{ now()->format('d/m/Y H:i') }}</div>
        </div>
        <div class="meta-card">
            <div class="meta-label">Total Akun</div>
            <div class="meta-value">{{ $report['rows']->count() }}</div>
        </div>
        <div class="meta-card">
            <div class="meta-label">Periode Debit</div>
            <div class="meta-value">{{ number_format((float) $report['grand_totals']['period_debit'], 2, ',', '.') }}</div>
        </div>
        <div class="meta-card">
            <div class="meta-label">Periode Kredit</div>
            <div class="meta-value">{{ number_format((float) $report['grand_totals']['period_credit'], 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="table-wrap" id="trial-balance-report">
        <table id="tb-data-table">
            <thead>
            <tr>
                <th style="width:120px">Account No</th>
                <th>Account Name</th>
                <th style="width:90px">Normal Balance</th>
                <th style="width:120px">Account Type</th>
                <th class="num" style="width:150px">Beginning Balance</th>
                <th class="num" style="width:150px">Debit</th>
                <th class="num" style="width:150px">Credit</th>
                <th class="num" style="width:150px">Ending Balance</th>
            </tr>
            </thead>
            <tbody>
            @forelse($report['rows'] as $row)
                <tr class="{{ $row->is_parent ? 'parent' : '' }}">
                    <td class="code">{{ $row->code }}</td>
                    <td class="name" style="padding-left: {{ $row->is_parent ? '0' : '18px' }}">{{ $row->name }}</td>
                    <td class="center">
                        <span class="badge {{ $row->normal_balance === 'D' ? 'badge-d' : 'badge-c' }}">{{ $row->normal_balance }}</span>
                    </td>
                    <td class="center">{{ strtoupper($row->type) }}</td>
                    <td class="num">{{ number_format((float) $row->beginning_balance, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $row->period_debit, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $row->period_credit, 2, ',', '.') }}</td>
                    <td class="num">
                        @if($row->ending_balance < 0)
                            ({{ number_format(abs((float) $row->ending_balance), 2, ',', '.') }})
                        @else
                            {{ number_format((float) $row->ending_balance, 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="empty">Tidak ada data untuk periode ini.</div>
                    </td>
                </tr>
            @endforelse
            </tbody>
            <tfoot>
            <tr>
                <td colspan="4">TOTAL</td>
                <td class="num">{{ number_format((float) $report['grand_totals']['beginning_balance'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $report['grand_totals']['period_debit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $report['grand_totals']['period_credit'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $report['grand_totals']['ending_balance'], 2, ',', '.') }}</td>
            </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        <div>Generated from Trial Balance Service</div>
        <div>Preview standalone tanpa layout Filament</div>
    </div>
</div>
</body>
</html>