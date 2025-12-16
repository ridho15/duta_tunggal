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
            font-weight: normal;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .profit-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .subtotal-row {
            font-weight: bold;
        }
        .number-column {
            text-align: right;
        }
        .section-header {
            background-color: #e0e0e0;
            font-weight: bold;
            font-size: 13px;
        }
        .child-row td:nth-child(2) {
            padding-left: 30px;
        }
        .negative {
            color: #000;
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
                <th style="width: 15%">Kode</th>
                <th style="width: 65%">Deskripsi</th>
                <th style="width: 20%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                @php
                    $isArray = is_array($item);
                    $code = $isArray ? ($item['code'] ?? '') : ($item->code ?? '');
                    $description = $isArray ? ($item['description'] ?? '') : ($item->description ?? '');
                    $amount = $isArray ? ($item['amount'] ?? '') : ($item->amount ?? '');
                    $isHeader = $isArray ? ($item['is_header'] ?? false) : ($item->is_header ?? false);
                    $isTotal = $isArray ? ($item['is_total'] ?? false) : ($item->is_total ?? false);
                    $isSpacer = $isArray ? ($item['is_spacer'] ?? false) : ($item->is_spacer ?? false);
                @endphp

                @php
                    $rowType = $isArray ? ($item['row_type'] ?? '') : ($item->row_type ?? '');
                    $isChild = $rowType === 'child';
                    $isSubtotal = $rowType === 'subtotal';
                    $isComputed = $rowType === 'computed';
                    $amountNum = (float) $amount;
                    $isNegative = $amountNum < 0;
                    $absAmount = abs($amountNum);
                    $formattedAmount = number_format($absAmount, 0, ',', '.');
                    if ($isNegative) {
                        $displayAmount = "({$formattedAmount})";
                    } else {
                        $displayAmount = $formattedAmount;
                    }
                @endphp

                @if($isSpacer)
                    <tr><td colspan="3">&nbsp;</td></tr>
                @elseif($isHeader)
                    <tr class="section-header"><td colspan="3">{{ $description }}</td></tr>
                @elseif($isTotal || $isComputed)
                    <tr class="{{ $isComputed ? 'profit-row' : 'total-row' }}">
                        <td></td>
                        <td>{{ $description }}</td>
                        <td class="number-column {{ $isNegative ? 'negative' : '' }}">
                            <strong>{{ $displayAmount }}</strong>
                        </td>
                    </tr>
                @elseif($isSubtotal)
                    <tr class="subtotal-row">
                        <td></td>
                        <td>{{ $description }}</td>
                        <td class="number-column {{ $isNegative ? 'negative' : '' }}">
                            <strong>{{ $displayAmount }}</strong>
                        </td>
                    </tr>
                @else
                    <tr class="{{ $isChild ? 'child-row' : '' }}">
                        <td>{{ $code }}</td>
                        <td class="account-name">{{ $description }}</td>
                        <td class="number-column {{ $isNegative ? 'negative' : '' }}">
                            <strong>{{ $displayAmount }}</strong>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dihasilkan secara otomatis oleh sistem ERP Duta Tunggal</p>
    </div>
</body>
</html>