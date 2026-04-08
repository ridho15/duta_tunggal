<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ALK Grafik</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .wrap { padding: 24px; }
        .header { border-radius: 16px; background: #0f172a; color: #fff; padding: 20px 24px; margin-bottom: 20px; }
        .header h1 { margin: 0 0 6px; font-size: 22px; }
        .header p { margin: 0; font-size: 11px; color: #cbd5e1; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 10px; margin: 0 -10px 14px; }
        .grid td { width: 33.33%; vertical-align: top; }
        .card { border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px; }
        .label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
        .value { font-size: 16px; font-weight: bold; }
        .section { border: 1px solid #cbd5e1; border-radius: 14px; margin-bottom: 18px; overflow: hidden; }
        .section-header { background: #e2e8f0; padding: 10px 14px; font-weight: bold; }
        .section-body { padding: 12px 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        th { background: #f8fafc; font-size: 10px; text-transform: uppercase; text-align: left; }
        td.num, th.num { text-align: right; }
        .note-ok { background: #dcfce7; color: #166534; padding: 8px 10px; border-radius: 8px; }
        .note-warn { background: #fee2e2; color: #991b1b; padding: 8px 10px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="header">
            <h1>Laporan ALK Grafik</h1>
            <p>
                Periode {{ \Carbon\Carbon::parse($report['start_date'])->isoFormat('D MMMM GGGG') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->isoFormat('D MMMM GGGG') }}
                | Cabang {{ $report['branch_name'] ?? 'Semua Cabang' }}
            </p>
        </div>

        <table class="grid">
            <tr>
                <td><div class="card"><div class="label">Pendapatan</div><div class="value">Rp {{ number_format(data_get($report, 'summary.revenue', 0), 0, ',', '.') }}</div></div></td>
                <td><div class="card"><div class="label">Beban</div><div class="value">Rp {{ number_format(data_get($report, 'summary.expense', 0), 0, ',', '.') }}</div></div></td>
                <td><div class="card"><div class="label">Laba Bersih</div><div class="value">Rp {{ number_format(data_get($report, 'summary.net_profit', 0), 0, ',', '.') }}</div></div></td>
            </tr>
            <tr>
                <td><div class="card"><div class="label">Total Aset</div><div class="value">Rp {{ number_format(data_get($report, 'summary.total_assets', 0), 0, ',', '.') }}</div></div></td>
                <td><div class="card"><div class="label">Total Liabilitas</div><div class="value">Rp {{ number_format(data_get($report, 'summary.total_liabilities', 0), 0, ',', '.') }}</div></div></td>
                <td><div class="card"><div class="label">Total Ekuitas</div><div class="value">Rp {{ number_format(data_get($report, 'summary.total_equity', 0), 0, ',', '.') }}</div></div></td>
            </tr>
        </table>

        <div class="section">
            <div class="section-header">Rasio Keuangan Utama</div>
            <div class="section-body">
                <table>
                    <thead>
                        <tr>
                            <th>Rasio</th>
                            <th class="num">Nilai</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Current Ratio</td>
                            <td class="num">{{ data_get($report, 'ratios.current_ratio') !== null ? number_format(data_get($report, 'ratios.current_ratio'), 2) . 'x' : 'N/A' }}</td>
                            <td>Likuiditas jangka pendek</td>
                        </tr>
                        <tr>
                            <td>Debt to Equity</td>
                            <td class="num">{{ data_get($report, 'ratios.debt_to_equity') !== null ? number_format(data_get($report, 'ratios.debt_to_equity'), 2) . 'x' : 'N/A' }}</td>
                            <td>Tingkat leverage</td>
                        </tr>
                        <tr>
                            <td>ROA</td>
                            <td class="num">{{ data_get($report, 'ratios.roa') !== null ? number_format(data_get($report, 'ratios.roa'), 2) . '%' : 'N/A' }}</td>
                            <td>Efisiensi aset terhadap laba</td>
                        </tr>
                        <tr>
                            <td>ROE</td>
                            <td class="num">{{ data_get($report, 'ratios.roe') !== null ? number_format(data_get($report, 'ratios.roe'), 2) . '%' : 'N/A' }}</td>
                            <td>Pengembalian terhadap ekuitas</td>
                        </tr>
                        <tr>
                            <td>Profit Margin</td>
                            <td class="num">{{ data_get($report, 'ratios.profit_margin') !== null ? number_format(data_get($report, 'ratios.profit_margin'), 2) . '%' : 'N/A' }}</td>
                            <td>Margin laba bersih</td>
                        </tr>
                    </tbody>
                </table>
                <div style="margin-top:10px;" class="{{ data_get($report, 'summary.is_balanced') ? 'note-ok' : 'note-warn' }}">
                    {{ data_get($report, 'summary.is_balanced') ? 'Neraca seimbang.' : 'Neraca belum seimbang.' }}
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-header">Detail Tren Bulanan</div>
            <div class="section-body">
                <table>
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="num">Pendapatan</th>
                            <th class="num">Beban</th>
                            <th class="num">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['trend'] as $row)
                            <tr>
                                <td>{{ $row['month'] }}</td>
                                <td class="num">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($row['expense'], 0, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($row['profit'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>