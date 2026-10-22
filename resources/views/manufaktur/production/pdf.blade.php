<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lembar Hasil Produksi - {{ $production->production_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #15803d;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 16pt;
            font-weight: bold;
            color: #15803d;
            margin: 0;
        }
        .company-address {
            font-size: 8pt;
            color: #666;
            margin: 2px 0 0 0;
        }
        .doc-title {
            text-align: right;
            vertical-align: top;
        }
        .doc-title h2 {
            margin: 0;
            font-size: 14pt;
            color: #111;
        }
        .doc-title .doc-number {
            font-family: monospace;
            font-size: 11pt;
            font-weight: bold;
            color: #15803d;
            margin-top: 4px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 9pt;
        }
        .meta-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .meta-label {
            width: 25%;
            color: #666;
        }
        .meta-value {
            width: 75%;
            font-weight: bold;
            color: #111;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 9pt;
        }
        .items-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            text-transform: uppercase;
            font-size: 8pt;
        }
        .items-table td {
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-finished {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-draft {
            background-color: #fef3c7;
            color: #b45309;
        }
        .signatures {
            width: 100%;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signatures td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8.5pt;
        }
        .sign-line {
            margin-top: 60px;
            border-bottom: 1px solid #333;
            width: 75%;
            margin-left: auto;
            margin-right: auto;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $spk = $production->manufacturingOrder;
        $plan = $spk?->productionPlan;
        $product = $plan?->product;
    @endphp

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="company-name">DUTA TUNGGAL ERP</h1>
                    <p class="company-address">Divisi Manufaktur & Perakitan Produk Jadi<br>Jl. Raya Industri Bahan Bangunan No. 88</p>
                </td>
                <td class="doc-title">
                    <h2>BUKTI HASIL PRODUKSI (LHP)</h2>
                    <div class="doc-number">{{ $production->production_number }}</div>
                    <div style="margin-top: 4px;">
                        <span class="status-badge {{ $production->status === 'finished' ? 'status-finished' : 'status-draft' }}">
                            {{ strtoupper($production->status) }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr>
                        <td class="meta-label">No. SPK (MO):</td>
                        <td class="meta-value">{{ $spk?->mo_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Resep / BOM:</td>
                        <td class="meta-value">{{ $plan?->billOfMaterial?->nama_bom ?? '-' }} ({{ $plan?->billOfMaterial?->code ?? '-' }})</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Cabang / Pabrik:</td>
                        <td class="meta-value">{{ $spk?->cabang?->nama ?? 'Pusat' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr>
                        <td class="meta-label">Tanggal Selesai:</td>
                        <td class="meta-value">{{ $production->production_date ? $production->production_date->format('d F Y') : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Gudang Alokasi:</td>
                        <td class="meta-value">{{ $plan?->warehouse?->name ?? 'Gudang Utama' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Dokumen QC:</td>
                        <td class="meta-value">{{ $production->qualityControl?->qc_number ?? 'Belum Diperiksa' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Kode SKU</th>
                <th style="width: 45%;">Nama Produk Jadi</th>
                <th style="width: 15%; text-align: right;">Target SPK</th>
                <th style="width: 15%; text-align: right;">Hasil Jadi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td style="font-family: monospace; font-weight: bold;">{{ $product?->sku ?? '-' }}</td>
                <td>
                    <strong>{{ $product?->name ?? 'Produk Hasil Produksi' }}</strong>
                    <div style="font-size: 8pt; color: #666;">Kategori: {{ $product?->category?->name ?? '-' }}</div>
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format((float)($plan?->quantity ?? 0), 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #15803d;">
                    {{ number_format((float)($production->quantity_produced ?? $plan?->quantity ?? 0), 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                </td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                Dibuat Oleh,<br><strong>Operator / Kepala Regu</strong>
                <div class="sign-line"></div>
                Tanggal: ..............................
            </td>
            <td>
                Diperiksa Oleh,<br><strong>Petugas Quality Control (QC)</strong>
                <div class="sign-line"></div>
                Tanggal: ..............................
            </td>
            <td>
                Disetujui Oleh,<br><strong>Manajer Pabrik / Gudang</strong>
                <div class="sign-line"></div>
                Tanggal: ..............................
            </td>
        </tr>
    </table>

    <div class="footer">
        Duta Tunggal ERP — Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
