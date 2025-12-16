<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 10pt;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        .header {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin-bottom: 20px;
            background-color: #e8f4f8;
            padding: 10px;
        }

        .warehouse-header {
            background-color: #d4edda;
            font-weight: bold;
            font-size: 12pt;
            padding: 8px;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .summary-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            margin-bottom: 15px;
        }

        .summary-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .green-text {
            color: #28a745;
        }

        .red-text {
            color: #dc3545;
        }

        .blue-text {
            color: #007bff;
        }

        .total-summary {
            background-color: #e9ecef;
            font-weight: bold;
            font-size: 11pt;
        }
    </style>
</head>
<body>
    <div class="header">
        LAPORAN MUTASI BARANG PER GUDANG<br>
        Periode: {{ \Carbon\Carbon::parse($report['period']['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($report['period']['end'])->format('d/m/Y') }}<br>
        <small>Dibuat pada: {{ $generated_at->format('d/m/Y H:i:s') }}</small>
    </div>

    @if(empty($report['warehouseData']))
        <p>Tidak ada data mutasi dalam periode yang dipilih.</p>
    @else
        <!-- Grand Summary -->
        <div class="summary-box">
            <div class="summary-title">RINGKASAN KESELURUHAN</div>
            <table>
                <tr>
                    <td width="25%">Total Gudang:</td>
                    <td class="blue-text">{{ count($report['warehouseData']) }}</td>
                    <td width="25%">Total Transaksi:</td>
                    <td class="blue-text">{{ number_format($report['totals']['total_movements']) }}</td>
                </tr>
                <tr>
                    <td>Total Qty Masuk:</td>
                    <td class="green-text">{{ number_format($report['totals']['total_qty_in'], 2) }}</td>
                    <td>Total Qty Keluar:</td>
                    <td class="red-text">{{ number_format($report['totals']['total_qty_out'], 2) }}</td>
                </tr>
                <tr>
                    <td>Net Quantity:</td>
                    <td class="{{ ($report['totals']['total_qty_in'] - $report['totals']['total_qty_out']) >= 0 ? 'green-text' : 'red-text' }}">
                        {{ number_format($report['totals']['total_qty_in'] - $report['totals']['total_qty_out'], 2) }}
                    </td>
                    <td>Total Nilai Masuk:</td>
                    <td class="green-text">Rp {{ number_format($report['totals']['total_value_in'], 0) }}</td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td>Total Nilai Keluar:</td>
                    <td class="red-text">Rp {{ number_format($report['totals']['total_value_out'], 0) }}</td>
                </tr>
            </table>
        </div>

        @foreach($report['warehouseData'] as $warehouse)
            <div class="warehouse-header">
                GUDANG: {{ $warehouse['warehouse_name'] }}
                @if($warehouse['warehouse_code'])
                    ({{ $warehouse['warehouse_code'] }})
                @endif
            </div>

            <!-- Warehouse Summary -->
            <div class="summary-box">
                <table>
                    <tr>
                        <td width="25%">Qty Masuk:</td>
                        <td class="green-text">{{ number_format($warehouse['summary']['qty_in'], 2) }}</td>
                        <td width="25%">Qty Keluar:</td>
                        <td class="red-text">{{ number_format($warehouse['summary']['qty_out'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Net Qty:</td>
                        <td class="{{ $warehouse['summary']['net_qty'] >= 0 ? 'green-text' : 'red-text' }}">
                            {{ number_format($warehouse['summary']['net_qty'], 2) }}
                        </td>
                        <td>Total Transaksi:</td>
                        <td class="blue-text">{{ count($warehouse['movements']) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Movements Table -->
            <table>
                <thead>
                    <tr>
                        <th width="60px">Tanggal</th>
                        <th width="200px">Produk</th>
                        <th width="100px">Tipe</th>
                        <th width="80px">Qty Masuk</th>
                        <th width="80px">Qty Keluar</th>
                        <th width="80px">Saldo</th>
                        <th width="120px">Nilai</th>
                        <th width="100px">Referensi</th>
                        <th width="80px">Rak</th>
                        <th width="150px">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($warehouse['movements'] as $movement)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}</td>
                            <td>
                                {{ $movement['product_name'] }}
                                @if($movement['product_sku'])
                                    <br><small>({{ $movement['product_sku'] }})</small>
                                @endif
                            </td>
                            <td>{{ $movement['type'] }}</td>
                            <td class="green-text">
                                {{ $movement['qty_in'] > 0 ? number_format($movement['qty_in'], 2) : '-' }}
                            </td>
                            <td class="red-text">
                                {{ $movement['qty_out'] > 0 ? number_format($movement['qty_out'], 2) : '-' }}
                            </td>
                            <td class="{{ $movement['balance'] >= 0 ? 'green-text' : 'red-text' }}">
                                {{ number_format($movement['balance'], 2) }}
                            </td>
                            <td>
                                {{ $movement['value'] ? 'Rp ' . number_format($movement['value'], 0) : '-' }}
                            </td>
                            <td>{{ $movement['reference'] ?: '-' }}</td>
                            <td>{{ $movement['rak_name'] ?: '-' }}</td>
                            <td>{{ $movement['notes'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>