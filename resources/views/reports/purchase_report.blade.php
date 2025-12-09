<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pembelian - {{ $start_date }} s/d {{ $end_date }}</title>
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
        .summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .summary h3 {
            margin: 0 0 15px 0;
            font-size: 14px;
            text-align: center;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
        }
        .summary-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid #fff;
            transition: transform 0.2s;
        }
        .summary-item:hover {
            transform: translateY(-2px);
        }
        .summary-item .icon {
            font-size: 16px;
            display: block;
            margin-bottom: 5px;
        }
        .summary-item .value {
            font-size: 12px;
            font-weight: bold;
            display: block;
            margin-bottom: 3px;
        }
        .summary-item .label {
            font-size: 9px;
            color: rgba(255, 255, 255, 0.8);
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
            color: #6c757d;
        }
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-draft {
            background-color: #6c757d;
            color: white;
        }
        .status-confirmed {
            background-color: #17a2b8;
            color: white;
        }
        .status-processing {
            background-color: #ffc107;
            color: black;
        }
        .status-completed {
            background-color: #28a745;
            color: white;
        }
        .status-cancelled {
            background-color: #dc3545;
            color: white;
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
            <h2>LAPORAN PEMBELIAN</h2>
            <p>Periode: {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}</p>
            <p>Tanggal Cetak: {{ now()->format('d/m/Y H:i:s') }}</p>
            <p>Dicetak Oleh: {{ auth()->user()->name ?? 'System' }}</p>
        </div>

        <div class="qr-section">
            <img src="data:image/png;base64,{{ base64_encode(\Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG('Laporan Pembelian ' . $start_date . ' - ' . $end_date, 'QRCODE', 4, 4)) }}" alt="QR Code">
            <p>Scan untuk verifikasi</p>
        </div>
    </div>

    <div class="summary">
        <h3>📊 Ringkasan Laporan Pembelian</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <span class="icon">📋</span>
                <span class="value">{{ $data->count() }}</span>
                <span class="label">Total Transaksi</span>
            </div>
            <div class="summary-item">
                <span class="icon">💰</span>
                <span class="value">Rp {{ number_format($data->sum('total_amount'), 0, ',', '.') }}</span>
                <span class="label">Total Nilai</span>
            </div>
            <div class="summary-item">
                <span class="icon">📊</span>
                <span class="value">Rp {{ $data->count() > 0 ? number_format($data->sum('total_amount') / $data->count(), 0, ',', '.') : '0' }}</span>
                <span class="label">Rata-rata per Transaksi</span>
            </div>
            <div class="summary-item">
                <span class="icon">✅</span>
                <span class="value">{{ $data->where('status', 'confirmed')->count() }}</span>
                <span class="label">Transaksi Confirmed</span>
            </div>
            <div class="summary-item">
                <span class="icon">⏳</span>
                <span class="value">{{ $data->where('status', 'processing')->count() }}</span>
                <span class="label">Transaksi Processing</span>
            </div>
            <div class="summary-item">
                <span class="icon">🚫</span>
                <span class="value">{{ $data->where('status', 'cancelled')->count() }}</span>
                <span class="label">Transaksi Cancelled</span>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">No.</th>
                <th>No. PO</th>
                <th>Tanggal</th>
                <th>Kode Supplier</th>
                <th>Nama Supplier</th>
                <th>Alamat Supplier</th>
                <th>No. Telp</th>
                <th class="text-right">Total</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $order)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $order->po_number }}</td>
                <td>{{ $order->order_date->format('d/m/Y') }}</td>
                <td>{{ $order->supplier->code ?? '-' }}</td>
                <td>{{ $order->supplier->name ?? '-' }}</td>
                <td>{{ $order->supplier->address ?? '-' }}</td>
                <td>{{ $order->supplier->phone ?? '-' }}</td>
                <td class="text-right">Rp {{ number_format($order->total_amount ?? 0, 0, ',', '.') }}</td>
                <td class="text-center">
                    <span class="status-badge status-{{ $order->status }}">
                        {{ $order->status }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr style="background-color: #e9ecef; font-weight: bold;">
                <td colspan="7" class="text-right">TOTAL:</td>
                <td class="text-right">Rp {{ number_format($data->sum('total_amount'), 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <div>
            <strong>Duta Tunggal ERP</strong><br>
            Sistem Informasi Manajemen Terpadu
        </div>
        <div style="text-align: right;">
            Halaman 1 dari 1<br>
            Dicetak pada {{ now()->format('d/m/Y H:i:s') }}
        </div>
    </div>
</body>
</html>