<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Harga Pokok Produksi</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 0;
            padding: 15px;
            background-color: #ffffff;
            color: #333;
            line-height: 1.4;
            font-size: 14px;
        }

        .report-container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            border: 1px solid #e5e7eb;
        }

        .report-header {
            background: #f8fafc;
            border-bottom: 2px solid #e5e7eb;
            padding: 15px;
            text-align: center;
        }

        .report-header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
        }

        .report-info {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-grid {
            display: table;
            width: 100%;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: 600;
            color: #6b7280;
            padding: 4px 0;
            width: 100px;
            font-size: 13px;
        }

        .info-value {
            display: table-cell;
            color: #374151;
            padding: 4px 0;
            font-size: 13px;
        }

        .content-section {
            padding: 15px;
        }

        .cost-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            font-size: 13px;
        }

        .cost-table th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
            font-size: 13px;
        }

        .cost-table th:last-child {
            text-align: right;
        }

        .cost-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }

        .cost-table .total-row {
            background: #fef3c7;
            font-weight: 600;
            border-top: 1px solid #f59e0b;
        }

        .cost-table .subtotal-row {
            background: #f3f4f6;
            font-weight: 600;
        }

        .cost-table .final-total {
            background: #1f2937;
            color: white;
            font-weight: 700;
        }

        .cost-table .final-total th,
        .cost-table .final-total td {
            padding: 12px;
            border: none;
        }

        .amount-column {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: 500;
            font-size: 13px;
        }

        .description-column {
            font-weight: 500;
        }

        .indent-1 {
            padding-left: 25px !important;
        }

        .indent-2 {
            padding-left: 35px !important;
        }

        .negative-amount {
            color: #dc2626;
        }

        .footer {
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            padding: 12px 15px;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }

        .footer p {
            margin: 0;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .report-container {
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header -->
        <div class="report-header">
            <h1>Laporan Harga Pokok Produksi</h1>
        </div>

        <!-- Report Info -->
        <div class="report-info">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Periode:</div>
                    <div class="info-value">{{ $report['period']['start'] }} s.d. {{ $report['period']['end'] }}</div>
                </div>
                @if(!empty($selectedBranches))
                <div class="info-row">
                    <div class="info-label">Cabang:</div>
                    <div class="info-value">{{ implode(', ', $selectedBranches) }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Tanggal Cetak:</div>
                    <div class="info-value">{{ now()->format('d/m/Y H:i:s') }}</div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-section">
            <table class="cost-table">
                <thead>
                    <tr>
                        <th class="description-column">Deskripsi</th>
                        <th class="amount-column">Jumlah (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Raw Materials Section -->
                    <tr class="subtotal-row">
                        <td colspan="2"><strong>BAHAN BAKU</strong></td>
                    </tr>
                    <tr>
                        <td>Persediaan Awal Bahan Baku</td>
                        <td class="amount-column">{{ formatAmount($report['raw_materials']['opening']) }}</td>
                    </tr>
                    <tr>
                        <td class="indent-1">+ Pembelian Bahan Baku</td>
                        <td class="amount-column">{{ formatAmount($report['raw_materials']['purchases']) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>= Total Bahan Baku Tersedia</strong></td>
                        <td class="amount-column"><strong>{{ formatAmount($report['raw_materials']['available']) }}</strong></td>
                    </tr>
                    <tr>
                        <td class="indent-1 negative-amount">- Persediaan Akhir Bahan Baku</td>
                        <td class="amount-column negative-amount">({{ formatAmount($report['raw_materials']['closing']) }})</td>
                    </tr>
                    <tr class="total-row">
                        <td><strong>= Bahan Baku yang Digunakan</strong></td>
                        <td class="amount-column"><strong>{{ formatAmount($report['raw_materials']['used']) }}</strong></td>
                    </tr>

                    <!-- Direct Labor Section -->
                    <tr class="subtotal-row">
                        <td colspan="2"><strong>TENAGA KERJA LANGSUNG</strong></td>
                    </tr>
                    <tr>
                        <td>Biaya Tenaga Kerja Langsung</td>
                        <td class="amount-column">{{ formatAmount($report['direct_labor']) }}</td>
                    </tr>

                    <!-- Overhead Section -->
                    <tr class="subtotal-row">
                        <td colspan="2"><strong>BIAYA OVERHEAD PABRIK</strong></td>
                    </tr>
                    <tr>
                        <td>Biaya Overhead Pabrik (Total)</td>
                        <td class="amount-column">{{ formatAmount($report['overhead']['total']) }}</td>
                    </tr>
                    @foreach($report['overhead']['items'] as $item)
                    <tr>
                        <td class="indent-2">• {{ $item['label'] }}</td>
                        <td class="amount-column">{{ formatAmount($item['amount']) }}</td>
                    </tr>
                    @endforeach

                    <!-- Production Cost Total -->
                    <tr class="total-row">
                        <td><strong>= TOTAL BIAYA PRODUKSI</strong></td>
                        <td class="amount-column"><strong>{{ formatAmount($report['production_cost']) }}</strong></td>
                    </tr>

                    <!-- WIP Section -->
                    <tr class="subtotal-row">
                        <td colspan="2"><strong>PERSISTDIAN BARANG DALAM PROSES (WIP)</strong></td>
                    </tr>
                    <tr>
                        <td>Persediaan Awal Barang Dalam Proses (WIP)</td>
                        <td class="amount-column">{{ formatAmount($report['wip']['opening']) }}</td>
                    </tr>
                    <tr>
                        <td class="indent-1 negative-amount">- Persediaan Akhir Barang Dalam Proses (WIP)</td>
                        <td class="amount-column negative-amount">({{ formatAmount($report['wip']['closing']) }})</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="final-total">
                        <th class="description-column">HARGA POKOK PRODUKSI (COST OF GOODS MANUFACTURED)</th>
                        <th class="amount-column">{{ formatAmount($report['cogm']) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Laporan ini dihasilkan secara otomatis oleh Sistem ERP - {{ now()->format('d F Y') }}</p>
        </div>
    </div>
</body>
</html>
