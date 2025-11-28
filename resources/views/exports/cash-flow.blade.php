<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 1in;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
        }
    </style>
</head>

<body>
    <table>
        <tr>
            <td colspan="3"><strong>Laporan Arus Kas ({{ $report['method'] === 'indirect' ? 'Metode Tidak Langsung' : 'Metode Langsung' }})</strong></td>
        </tr>
        <tr>
            <td>Periode</td>
            <td colspan="2">{{ $report['period']['start'] }} s.d. {{ $report['period']['end'] }}</td>
        </tr>
        @if (!empty($selectedBranches))
            <tr>
                <td>Cabang</td>
                <td colspan="2">{{ implode(', ', $selectedBranches) }}</td>
            </tr>
        @endif
        <tr>
            <td>Saldo Awal</td>
            <td colspan="2">{{ number_format($report['opening_balance'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Kenaikan / Penurunan Bersih</td>
            <td colspan="2">{{ number_format($report['net_change'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Saldo Akhir</td>
            <td colspan="2">{{ number_format($report['closing_balance'], 2, ',', '.') }}</td>
        </tr>
    </table>

    @foreach ($report['sections'] as $section)
        <table style="margin-top: 20px;">
            <thead>
                <tr>
                    <th colspan="3" style="text-align: left;">{{ $section['label'] }}</th>
                </tr>
                <tr>
                    <th style="text-align: left;">Deskripsi</th>
                    <th style="text-align: left;">Sumber</th>
                    <th style="text-align: right;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($section['items'] as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        <td>
                            @if (!empty($item['metadata']['sources']))
                                {{ implode(', ', $item['metadata']['sources']) }}
                            @endif
                        </td>
                        <td style="text-align: right;">{{ number_format($item['amount'], 2, ',', '.') }}</td>
                    </tr>
                    @if (!empty($item['metadata']['detail']))
                        <tr>
                            <td colspan="3">
                                <strong>Detail Penerimaan</strong>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>No. Invoice</th>
                                            <th>Metode</th>
                                            <th>Tanggal</th>
                                            <th style="text-align: right;">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item['metadata']['detail'] as $detail)
                                            @foreach ($detail['transactions'] ?? [] as $txn)
                                                <tr>
                                                    <td>{{ $detail['customer'] }}</td>
                                                    <td>{{ $txn['invoice_id'] ?? '-' }}</td>
                                                    <td>{{ $txn['method'] }}</td>
                                                    <td>{{ $txn['payment_date'] }}</td>
                                                    <td style="text-align: right;">
                                                        {{ number_format($txn['amount'], 2, ',', '.') }}</td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                    @php($breakdown = $item['metadata']['breakdown'] ?? [])
                    @if (!empty($breakdown['inflow']) || !empty($breakdown['outflow']))
                        <tr>
                            <td colspan="3">
                                <strong>Rincian COA</strong>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Jenis</th>
                                            <th>COA</th>
                                            <th style="text-align: right;">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($breakdown['inflow'] ?? [] as $coa)
                                            <tr>
                                                <td>Masuk</td>
                                                <td>{{ $coa['coa_code'] }} - {{ $coa['coa_name'] }}</td>
                                                <td style="text-align: right;">
                                                    {{ number_format(($coa['total'] ?? $coa['amount'] ?? 0), 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                        @foreach ($breakdown['outflow'] ?? [] as $coa)
                                            <tr>
                                                <td>Keluar</td>
                                                <td>{{ $coa['coa_code'] }} - {{ $coa['coa_name'] }}</td>
                                                <td style="text-align: right;">
                                                    {{ number_format(($coa['total'] ?? $coa['amount'] ?? 0), 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align: right;">Total {{ $section['label'] }}</th>
                    <th style="text-align: right;">{{ number_format($section['total'], 2, ',', '.') }}</th>
                </tr>
            </tfoot>
        </table>
    @endforeach

</body>

</html>
