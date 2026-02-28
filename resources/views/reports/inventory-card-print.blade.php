<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Persediaan</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            background: #fff;
        }

        .page-wrapper {
            padding: 20px 24px;
        }

        /* ---- Header ---- */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1E40AF;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #1E40AF;
        }

        .report-subtitle {
            font-size: 11px;
            color: #555;
            margin-top: 3px;
        }

        .report-meta {
            text-align: right;
            font-size: 10px;
            color: #555;
        }

        .report-meta strong {
            color: #111;
        }

        /* ---- Info badges ---- */
        .info-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 14px;
        }

        .info-badge {
            background: #EFF6FF;
            border: 1px solid #BFDBFE;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 10px;
            color: #1E40AF;
        }

        .info-badge strong {
            font-weight: 700;
        }

        /* ---- Table ---- */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }

        thead tr th {
            background-color: #1E40AF;
            color: #fff;
            padding: 7px 8px;
            text-align: center;
            border: 1px solid #1E40AF;
            font-size: 10px;
            white-space: nowrap;
        }

        thead tr.sub-header th {
            background-color: #DBEAFE;
            color: #1E40AF;
            font-weight: 600;
            padding: 4px 8px;
        }

        tbody tr td {
            padding: 5px 8px;
            border: 1px solid #E5E7EB;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td {
            background-color: #F9FAFB;
        }

        tbody tr:hover td {
            background-color: #EFF6FF;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-green { color: #15803D; }
        .text-red   { color: #DC2626; }
        .text-muted { color: #6B7280; font-size: 9.5px; }
        .font-bold  { font-weight: bold; }

        tfoot tr td {
            padding: 6px 8px;
            background-color: #E5E7EB;
            border: 1px solid #9CA3AF;
            font-weight: bold;
        }

        /* ---- Summary ---- */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-top: 16px;
        }

        .summary-card {
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 10px 12px;
        }

        .summary-card .label {
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6B7280;
            margin-bottom: 4px;
        }

        .summary-card .value {
            font-size: 14px;
            font-weight: bold;
        }

        .summary-card.green { border-color: #86EFAC; background: #F0FDF4; }
        .summary-card.green .value { color: #15803D; }

        .summary-card.red { border-color: #FCA5A5; background: #FEF2F2; }
        .summary-card.red .value { color: #DC2626; }

        .summary-card.blue { border-color: #93C5FD; background: #EFF6FF; }
        .summary-card.blue .value { color: #1D4ED8; }

        /* ---- Footer ---- */
        .report-footer {
            margin-top: 20px;
            border-top: 1px solid #E5E7EB;
            padding-top: 8px;
            font-size: 9.5px;
            color: #9CA3AF;
            display: flex;
            justify-content: space-between;
        }

        /* ---- Print button (hanya di screen) ---- */
        .print-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .btn-print {
            background: #1E40AF;
            color: #fff;
        }

        .btn-close {
            background: #E5E7EB;
            color: #374151;
        }

        .btn-pdf {
            background: #DC2626;
            color: #fff;
        }

        .btn-excel {
            background: #16A34A;
            color: #fff;
        }

    </style>
</head>
<body>
<div class="page-wrapper">

    @if(!isset($data['isPdf']) || !$data['isPdf'])
    {{-- ---- Print/Close buttons (hanya di browser) ---- --}}
    <div class="print-actions">
        <button class="btn btn-print" onclick="window.print()">&#128424; Cetak</button>
        <a class="btn btn-pdf" href="{{ route('inventory-card.pdf', request()->query()) }}" target="_blank">&#128196; Download PDF</a>
        <a class="btn btn-excel" href="{{ route('inventory-card.excel', request()->query()) }}" target="_blank">&#128202; Download Excel</a>
        <button class="btn btn-close" onclick="window.close()">&#10005; Tutup</button>
    </div>
    @endif

    {{-- ---- Header ---- --}}
    <div class="report-header">
        <div>
            <div class="report-title">KARTU PERSEDIAAN</div>
            <div class="report-subtitle">Laporan Pergerakan Persediaan Per Produk &amp; Gudang</div>
        </div>
        <div class="report-meta">
            <div>Dicetak: <strong>{{ now()->format('d/m/Y H:i') }}</strong></div>
            <div>Periode: <strong>{{ \Carbon\Carbon::parse($data['period']['start'])->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($data['period']['end'])->format('d/m/Y') }}</strong></div>
        </div>
    </div>

    {{-- ---- Info bar ---- --}}
    <div class="info-bar">
        <div class="info-badge">Produk: <strong>{{ $data['product_label'] }}</strong></div>
        <div class="info-badge">Gudang: <strong>{{ $data['warehouse_label'] }}</strong></div>
        <div class="info-badge">Jumlah Baris: <strong>{{ count($data['rows']) }}</strong></div>
    </div>

    {{-- ---- Table ---- --}}
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:3%">No</th>
                <th rowspan="2" style="width:20%">Produk</th>
                <th rowspan="2" style="width:15%">Gudang</th>
                <th colspan="2">Saldo Awal</th>
                <th colspan="2">Masuk</th>
                <th colspan="2">Keluar</th>
                <th colspan="2">Saldo Akhir</th>
            </tr>
            <tr class="sub-header">
                <th>Qty</th><th>Nilai (Rp)</th>
                <th>Qty</th><th>Nilai (Rp)</th>
                <th>Qty</th><th>Nilai (Rp)</th>
                <th>Qty</th><th>Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['rows'] as $i => $row)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>
                        <div>{{ $row['product_name'] }}</div>
                        @if($row['product_sku'])
                            <div class="text-muted">{{ $row['product_sku'] }}</div>
                        @endif
                    </td>
                    <td>
                        <div>{{ $row['warehouse_name'] }}</div>
                        @if($row['warehouse_code'])
                            <div class="text-muted">{{ $row['warehouse_code'] }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format($row['opening_qty'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['opening_value'], 0, ',', '.') }}</td>
                    <td class="text-right text-green font-bold">{{ number_format($row['qty_in'], 2, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($row['value_in'], 0, ',', '.') }}</td>
                    <td class="text-right text-red font-bold">{{ number_format($row['qty_out'], 2, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($row['value_out'], 0, ',', '.') }}</td>
                    <td class="text-right font-bold">{{ number_format($row['closing_qty'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($row['closing_value'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="text-center" style="padding: 20px; color: #9CA3AF;">
                        Tidak ada data pergerakan pada periode ini
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($data['rows']) > 0)
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">TOTAL KESELURUHAN</td>
                    <td class="text-right">{{ number_format($data['totals']['opening_qty'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($data['totals']['opening_value'], 0, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($data['totals']['qty_in'], 2, ',', '.') }}</td>
                    <td class="text-right text-green">{{ number_format($data['totals']['value_in'], 0, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($data['totals']['qty_out'], 2, ',', '.') }}</td>
                    <td class="text-right text-red">{{ number_format($data['totals']['value_out'], 0, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($data['totals']['closing_qty'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($data['totals']['closing_value'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- ---- Summary Cards ---- --}}
    @if(count($data['rows']) > 0)
        <div class="summary-grid" style="margin-top:14px">
            <div class="summary-card">
                <div class="label">Saldo Awal</div>
                <div class="value">Rp {{ number_format($data['totals']['opening_value'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card green">
                <div class="label">Total Nilai Masuk</div>
                <div class="value">Rp {{ number_format($data['totals']['value_in'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card red">
                <div class="label">Total Nilai Keluar</div>
                <div class="value">Rp {{ number_format($data['totals']['value_out'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card blue">
                <div class="label">Saldo Akhir</div>
                <div class="value">Rp {{ number_format($data['totals']['closing_value'], 0, ',', '.') }}</div>
            </div>
        </div>
    @endif

    {{-- ---- Footer ---- --}}
    <div class="report-footer">
        <div>Duta Tunggal ERP — Kartu Persediaan</div>
        <div>{{ now()->format('d/m/Y H:i:s') }}</div>
    </div>

</div>
</body>
</html>
