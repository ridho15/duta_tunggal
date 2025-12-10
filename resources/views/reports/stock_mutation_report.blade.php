<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Mutasi Barang - {{ $start_date }} s/d {{ $end_date }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 1cm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .logo-section {
            flex: 0 0 100px;
            text-align: center;
        }
        .logo-section img {
            max-width: 80px;
            max-height: 80px;
        }
        .company-info {
            flex: 1;
        }
        .company-info h1 {
            margin: 0;
            font-size: 18px;
            color: #2c3e50;
        }
        .company-info p {
            margin: 2px 0;
            font-size: 9px;
        }
        .report-info {
            flex: 1;
            text-align: center;
        }
        .report-info h2 {
            margin: 0;
            font-size: 16px;
            color: #2c3e50;
        }
        .report-info p {
            margin: 2px 0;
            font-size: 9px;
        }
        .qr-section {
            flex: 0 0 80px;
            text-align: center;
        }
        .qr-section img {
            width: 70px;
            height: 70px;
        }
        .qr-section p {
            margin: 2px 0;
            font-size: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 9px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            font-size: 8px;
        }
        .summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            color: #2c3e50;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        .summary-table {
            border-collapse: collapse;
            border: none;
            width: 100%;
        }
        .summary-table td {
            border: none;
            padding: 0 7.5px; /* setengah dari gap sebelumnya 15px */
        }
        .summary-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .summary-item:hover {
            transform: translateY(-2px);
        }
        .summary-item .icon {
            font-size: 16px;
            margin-bottom: 5px;
            display: block;
        }
        .summary-item .value {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 3px;
            display: block;
        }
        .summary-item .label {
            font-size: 9px;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .summary-item:nth-child(1) {
            border-left: 4px solid #28a745;
        }
        .summary-item:nth-child(1) .icon {
            color: #28a745;
        }
        .summary-item:nth-child(2) {
            border-left: 4px solid #007bff;
        }
        .summary-item:nth-child(2) .icon {
            color: #007bff;
        }
        .summary-item:nth-child(3) {
            border-left: 4px solid #6f42c1;
        }
        .summary-item:nth-child(3) .icon {
            color: #6f42c1;
        }
        .summary-item:nth-child(4) {
            border-left: 4px solid #17a2b8;
        }
        .summary-item:nth-child(4) .icon {
            color: #17a2b8;
        }
        .summary-item:nth-child(5) {
            border-left: 4px solid #ffc107;
        }
        .summary-item:nth-child(5) .icon {
            color: #ffc107;
        }
        .summary-item:nth-child(6) {
            border-left: 4px solid #dc3545;
        }
        .summary-item:nth-child(6) .icon {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-section">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Perusahaan" onerror="this.style.display='none'">
        </div>

        <div class="company-info">
            <h1>Duta Tunggal ERP</h1>
            <p>Sistem Informasi Manajemen Terpadu</p>
            <p>Jl. Contoh No. 123, Jakarta</p>
            <p>Telp: (021) 12345678 | Email: info@dutatrading.com</p>
        </div>

        <div class="report-info">
            <h2>LAPORAN MUTASI BARANG</h2>
            <p>Periode: {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}</p>
            <p>Tanggal Cetak: {{ now()->format('d/m/Y H:i:s') }}</p>
            <p>Dicetak Oleh: {{ auth()->user()->name ?? 'System' }}</p>
        </div>

        <div class="qr-section">
            <img src="data:image/png;base64,{{ base64_encode(\Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG('Laporan Mutasi Barang ' . $start_date . ' - ' . $end_date, 'QRCODE', 4, 4)) }}" alt="QR Code">
            <p>Scan untuk verifikasi</p>
        </div>
    </div>

    <div class="summary">
        <h3>📊 Ringkasan Laporan Mutasi Barang</h3>
        <table class="summary-table">
            <tbody>
                <tr>
                    <td class="summary-item">
                        <span class="icon">📅</span>
                        <span class="value">{{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}</span>
                        <span class="label">Periode</span>
                    </td>
                    <td class="summary-item">
                        <span class="icon">🏭</span>
                        <span class="value">{{ count($report['warehouseData']) }}</span>
                        <span class="label">Total Gudang</span>
                    </td>
                    <td class="summary-item">
                        <span class="icon">📦</span>
                        <span class="value">{{ $report['totals']['total_movements'] ?? 0 }}</span>
                        <span class="label">Total Mutasi</span>
                    </td>
                    <td class="summary-item">
                        <span class="icon">📈</span>
                        <span class="value">{{ collect($report['warehouseData'])->flatMap(fn($w) => $w['movements'])->pluck('product_name')->unique()->count() }}</span>
                        <span class="label">Produk Terlibat</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @foreach($report['warehouseData'] as $warehouseId => $warehouse)
    <div style="page-break-inside: avoid;">
        <h3 style="color: #2c3e50; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Gudang: {{ $warehouse['name'] }}</h3>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">No</th>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="width: 20%;">Produk</th>
                    <th style="width: 10%;">Rak</th>
                    <th class="text-center" style="width: 10%;">Tipe</th>
                    <th class="text-right" style="width: 10%;">Qty Masuk</th>
                    <th class="text-right" style="width: 10%;">Qty Keluar</th>
                    <th class="text-right" style="width: 10%;">Saldo</th>
                    <th style="width: 20%;">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 1; @endphp
                @foreach($warehouse['movements'] as $movement)
                <tr>
                    <td class="text-center">{{ $no++ }}</td>
                    <td>{{ \Carbon\Carbon::parse($movement['date'])->format('d/m/Y') }}</td>
                    <td>{{ $movement['product_name'] }}</td>
                    <td>{{ $movement['rak_name'] ?? '-' }}</td>
                    <td class="text-center">
                        <span style="padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold;
                            {{ $movement['type'] === 'in' ? 'background-color: #d4edda; color: #155724;' :
                               ($movement['type'] === 'out' ? 'background-color: #f8d7da; color: #721c24;' :
                               'background-color: #fff3cd; color: #856404;') }}">
                            {{ ucfirst($movement['type']) }}
                        </span>
                    </td>
                    <td class="text-right">{{ $movement['type'] === 'in' ? number_format($movement['quantity'], 0, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ $movement['type'] === 'out' ? number_format($movement['quantity'], 0, ',', '.') : '-' }}</td>
                    <td class="text-right">{{ number_format($movement['balance'], 0, ',', '.') }}</td>
                    <td>{{ $movement['notes'] ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    <div class="footer">
        <div>
            <p><strong>Dokumen ini dihasilkan secara otomatis oleh sistem ERP</strong></p>
            <p>Untuk informasi lebih lanjut, hubungi departemen IT</p>
            <p>File: stock_mutation_report_{{ now()->format('Ymd_His') }}.pdf</p>
        </div>
        <div style="text-align: right;">
            <p>Halaman 1 dari 1</p>
            <p>Duta Tunggal ERP v1.0</p>
            <p>© {{ now()->format('Y') }} PT. Duta Tunggal</p>
        </div>
    </div>
</body>
</html>