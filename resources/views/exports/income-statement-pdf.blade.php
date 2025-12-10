<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Laba Rugi</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .header .period {
            margin: 10px 0;
            font-size: 14px;
        }
        .header .cabang {
            margin: 5px 0;
            font-size: 12px;
            color: #666;
        }
        .header .export-info {
            margin: 5px 0;
            font-size: 10px;
            color: #999;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        .account-name {
            font-weight: bold;
        }
        .total-row {
            background-color: #e8f4f8;
            font-weight: bold;
        }
        .number-column {
            text-align: right;
        }
        .section-header {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 13px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Laporan Laba Rugi (Income Statement)</h1>
        <div class="period">Periode: {{ $startDate }} - {{ $endDate }}</div>
        @if($cabang)
            <div class="cabang">Cabang: ({{ $cabang->kode }}) {{ $cabang->nama }}</div>
        @endif
        <div class="export-info">Tanggal Export: {{ $exportDate }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50%">Akun</th>
                <th style="width: 15%">Debit</th>
                <th style="width: 15%">Kredit</th>
                <th style="width: 20%">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td class="account-name">{{ $item->account_name }}</td>
                    <td class="number-column">
                        @if($item->debit > 0)
                            Rp {{ number_format($item->debit, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="number-column">
                        @if($item->credit > 0)
                            Rp {{ number_format($item->credit, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="number-column">
                        <strong>Rp {{ number_format($item->balance, 0, ',', '.') }}</strong>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dihasilkan secara otomatis oleh sistem ERP Duta Tunggal</p>
    </div>
</body>
</html>