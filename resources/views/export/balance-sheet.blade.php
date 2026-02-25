<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neraca - {{ $asOf->format('d M Y') }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            font-weight: bold;
            color: #34495e;
            margin-bottom: 10px;
        }
        .report-info {
            font-size: 14px;
            color: #7f8c8d;
        }
        .balance-sheet {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .balance-sheet th, .balance-sheet td {
            border: 1px solid #bdc3c7;
            padding: 8px 12px;
            text-align: left;
            vertical-align: top;
        }
        .balance-sheet th {
            background-color: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
        }
        .section-header {
            background-color: #3498db !important;
            color: white !important;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .group-header {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .account-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #17a2b8;
        }
        .amount {
            text-align: right;
            font-family: 'Courier New', monospace;
            font-weight: bold;
        }
        .subtotal-row {
            background-color: #e9ecef;
            font-weight: bold;
        }
        .total-row {
            background-color: #343a40;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        .status-row {
            background-color: #28a745;
            color: white;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        .status-unbalanced {
            background-color: #dc3545;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            padding: 0 50px;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 50px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">PT. DUTA TUNGGAL</div>
        <div class="report-title">LAPORAN NERACA</div>
        <div class="report-info">
            Per Tanggal: {{ $asOf->format('d F Y') }}<br>
            Periode Akuntansi: {{ $asOf->startOfYear()->format('d/m/Y') }} - {{ $asOf->format('d/m/Y') }}
        </div>
    </div>

    <table class="balance-sheet">
        <!-- ASSETS SECTION -->
        <tr>
            <th colspan="3" class="section-header">A. ASET (ASSETS)</th>
        </tr>
        @foreach ($data['assets'] as $group)
            @if($group['parent'] !== 'Tanpa Induk')
            <tr>
                <td colspan="3" class="group-header">{{ $group['parent'] }}</td>
            </tr>
            @endif
            @foreach ($group['items'] as $row)
            <tr>
                <td class="account-code">{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td class="amount">Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="2"><strong>Subtotal {{ $group['parent'] }}</strong></td>
                <td class="amount"><strong>Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</strong></td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">TOTAL ASET</td>
            <td class="amount">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</td>
        </tr>

        <!-- LIABILITIES SECTION -->
        <tr>
            <th colspan="3" class="section-header">B. KEWAJIBAN (LIABILITIES)</th>
        </tr>
        @foreach ($data['liabilities'] as $group)
            @if($group['parent'] !== 'Tanpa Induk')
            <tr>
                <td colspan="3" class="group-header">{{ $group['parent'] }}</td>
            </tr>
            @endif
            @foreach ($group['items'] as $row)
            <tr>
                <td class="account-code">{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td class="amount">Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="2"><strong>Subtotal {{ $group['parent'] }}</strong></td>
                <td class="amount"><strong>Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</strong></td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">TOTAL KEWAJIBAN</td>
            <td class="amount">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</td>
        </tr>

        <!-- EQUITY SECTION -->
        <tr>
            <th colspan="3" class="section-header">C. MODAL (EQUITY)</th>
        </tr>
        @foreach ($data['equity'] as $group)
            @if($group['parent'] !== 'Tanpa Induk')
            <tr>
                <td colspan="3" class="group-header">{{ $group['parent'] }}</td>
            </tr>
            @endif
            @foreach ($group['items'] as $row)
            <tr>
                <td class="account-code">{{ $row['coa']->code }}</td>
                <td>{{ $row['coa']->name }}</td>
                <td class="amount">Rp {{ number_format($row['balance'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr class="subtotal-row">
                <td colspan="2"><strong>Subtotal {{ $group['parent'] }}</strong></td>
                <td class="amount"><strong>Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</strong></td>
            </tr>
        @endforeach
        @if($data['retained_earnings'] != 0)
        <tr>
            <td class="account-code">3400</td>
            <td>Laba Ditahan (Retained Earnings)</td>
            <td class="amount">Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}</td>
        </tr>
        @endif
        @if(($data['current_earnings'] ?? 0) != 0)
        <tr>
            <td class="account-code">3500</td>
            <td>Laba Tahun Berjalan (Current Year Earnings)</td>
            <td class="amount">Rp {{ number_format($data['current_earnings'], 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td colspan="2">TOTAL MODAL</td>
            <td class="amount">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</td>
        </tr>

        <!-- BALANCE STATUS -->
        <tr class="{{ $data['balanced'] ? 'status-row' : 'status-row status-unbalanced' }}">
            <td colspan="3" style="text-align: center;">
                STATUS: {{ $data['balanced'] ? 'NERACA SEIMBANG ✓' : 'NERACA TIDAK SEIMBANG ✗' }}
            </td>
        </tr>
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Dibuat Oleh</div>
            <div style="margin-top: 10px; font-size: 11px;">{{ now()->format('d/m/Y') }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Diperiksa Oleh</div>
            <div style="margin-top: 10px; font-size: 11px;">{{ now()->format('d/m/Y') }}</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Disetujui Oleh</div>
            <div style="margin-top: 10px; font-size: 11px;">{{ now()->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="footer">
        <p>Dokumen ini dihasilkan secara otomatis oleh Sistem ERP PT. Duta Tunggal pada {{ now()->format('d F Y H:i:s') }}</p>
        <p>Halaman ini adalah laporan keuangan resmi dan dapat digunakan untuk keperluan audit serta pelaporan</p>
    </div>
</body>
</html>
