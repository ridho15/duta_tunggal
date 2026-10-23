<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Inventaris Aset - {{ $asset->code }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 15mm 15mm 15mm;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #2d3748;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border-bottom: 2.5px solid #15803d;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1a202c;
            letter-spacing: 0.5px;
        }

        .company-tag {
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            color: #15803d;
            background-color: #f0fdf4;
            border: 1px solid #86efac;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 4px;
        }

        .company-address {
            font-size: 9px;
            color: #4a5568;
            margin-top: 3px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            font-size: 15px;
            font-weight: 800;
            color: #15803d;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .doc-title .doc-number {
            font-size: 12px;
            font-weight: bold;
            color: #1a202c;
            margin-top: 3px;
            font-family: 'Courier New', Courier, monospace;
        }

        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 14px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 4px;
            font-size: 9.5px;
            vertical-align: top;
        }

        .info-label {
            width: 25%;
            font-weight: bold;
            color: #4a5568;
        }

        .info-value {
            width: 75%;
            color: #1a202c;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #15803d;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .data-table td {
            padding: 6px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .barcode-box {
            border: 1.5px dashed #94a3b8;
            background-color: #ffffff;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
            margin-top: 14px;
            margin-bottom: 14px;
        }

        .barcode-title {
            font-size: 10px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 6px;
        }

        .barcode-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 4px;
            color: #0f172a;
        }

        .sign-table {
            width: 100%;
            margin-top: 24px;
            border-collapse: collapse;
        }

        .sign-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 9.5px;
        }

        .sign-box {
            height: 55px;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }

        .footer {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <div class="company-name">
                    DUTA TUNGGAL <span class="company-tag">ERP</span>
                </div>
                <div class="company-address">
                    Cabang: {{ $asset->cabang->nama ?? 'Semua Cabang' }} ({{ $asset->cabang->kode ?? 'HO' }})<br>
                    Sistem Informasi Manajemen Inventaris & Aset Tetap
                </div>
            </td>
            <td style="width: 40%;" class="doc-title">
                <h1>KARTU IDENTITAS ASET</h1>
                <div class="doc-number">{{ $asset->code }}</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">
                    Dicetak: {{ now()->translatedFormat('d F Y, H:i') }} WIB
                </div>
            </td>
        </tr>
    </table>

    {{-- Box Barcode & Kode Aset --}}
    <div class="barcode-box">
        <div class="barcode-title">KODE IDENTIFIKASI FISIK BARANG</div>
        <div class="barcode-code">*{{ $asset->code }}*</div>
        <div style="font-size: 8.5px; color: #64748b; margin-top: 4px;">
            {{ $asset->name }} | Lokasi: {{ $asset->cabang->nama ?? '-' }}
        </div>
    </div>

    {{-- 1. Data Inventaris Barang --}}
    <div class="section-title">1. Data Fisik & Penempatan Aset</div>
    <div class="info-card">
        <table class="info-table">
            <tr>
                <td class="info-label">Nama Barang / Aset:</td>
                <td class="info-value"><strong>{{ $asset->name }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Kategori Akun COA:</td>
                <td class="info-value">{{ $asset->assetCoa->code ?? '-' }} - {{ $asset->assetCoa->name ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Cabang Penempatan:</td>
                <td class="info-value">{{ $asset->cabang->kode ?? '-' }} - {{ $asset->cabang->nama ?? '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Tautan Katalog Produk:</td>
                <td class="info-value">{{ $asset->product ? ($asset->product->sku . ' - ' . $asset->product->name) : 'Tidak ditautkan' }}</td>
            </tr>
            <tr>
                <td class="info-label">Tanggal Perolehan (Beli):</td>
                <td class="info-value">{{ $asset->purchase_date ? $asset->purchase_date->translatedFormat('d F Y') : '-' }}</td>
            </tr>
            <tr>
                <td class="info-label">Tanggal Mulai Beroperasi:</td>
                <td class="info-value">{{ $asset->usage_date ? $asset->usage_date->translatedFormat('d F Y') : '-' }}</td>
            </tr>
            @if ($asset->notes)
            <tr>
                <td class="info-label">Spesifikasi / Catatan:</td>
                <td class="info-value">{{ $asset->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- 2. Data Finansial & Depresiasi --}}
    <div class="section-title">2. Parameter Finansial & Akuntansi</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Komponen Nilai</th>
                <th>Rincian Parameter</th>
                <th class="text-right">Nominal (Rupiah)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Biaya Perolehan (Harga Beli)</strong></td>
                <td>Harga faktur + ongkos angkut + biaya instalasi</td>
                <td class="text-right"><strong>{{ \App\Helpers\MoneyHelper::rupiah($asset->purchase_cost) }}</strong></td>
            </tr>
            <tr>
                <td><strong>Nilai Sisa (Salvage Value)</strong></td>
                <td>Estimasi nilai residu di akhir masa manfaat</td>
                <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($asset->salvage_value) }}</td>
            </tr>
            <tr>
                <td><strong>Masa Manfaat Ekonomis</strong></td>
                <td>
                    {{ $asset->useful_life_years }} Tahun ({{ $asset->useful_life_years * 12 }} Bulan)
                    | Sisa: {{ $asset->remaining_life_months }} Bulan
                </td>
                <td class="text-right">-</td>
            </tr>
            <tr>
                <td><strong>Metode Penyusutan</strong></td>
                <td>
                    {{ match($asset->depreciation_method) {
                        'straight_line' => 'Garis Lurus (Straight Line)',
                        'declining_balance' => 'Saldo Menurun Ganda (Declining Balance)',
                        'sum_of_years_digits' => 'Jumlah Digit Tahun (Sum of Years)',
                        'units_of_production' => 'Unit Produksi',
                        default => $asset->depreciation_method
                    } }}
                </td>
                <td class="text-right">-</td>
            </tr>
            <tr>
                <td><strong>Beban Penyusutan per Bulan</strong></td>
                <td>Dialokasikan ke Akun: {{ $asset->depreciationExpenseCoa->code ?? '-' }}</td>
                <td class="text-right">{{ \App\Helpers\MoneyHelper::rupiah($asset->monthly_depreciation) }}</td>
            </tr>
            <tr>
                <td><strong>Akumulasi Penyusutan Tercatat</strong></td>
                <td>Akun Akumulasi: {{ $asset->accumulatedDepreciationCoa->code ?? '-' }}</td>
                <td class="text-right" style="color: #d97706;">{{ \App\Helpers\MoneyHelper::rupiah($asset->accumulated_depreciation) }}</td>
            </tr>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td style="color: #15803d;">NILAI BUKU SAAT INI (NET BOOK VALUE)</td>
                <td>Biaya Perolehan dikurangi Akumulasi Penyusutan</td>
                <td class="text-right" style="color: #15803d; font-size: 11px;">
                    {{ \App\Helpers\MoneyHelper::rupiah($asset->book_value) }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Lembar Tanda Tangan Verifikasi Fisik --}}
    <table class="sign-table">
        <tr>
            <td>
                Petugas Inventaris / Logistik,
                <div class="sign-box"></div>
                <div class="sign-name">( ............................................ )</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">Tgl: .................................</div>
            </td>
            <td>
                Penanggung Jawab Cabang,
                <div class="sign-box"></div>
                <div class="sign-name">( ............................................ )</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">Tgl: .................................</div>
            </td>
            <td>
                Finance & Accounting,
                <div class="sign-box"></div>
                <div class="sign-name">{{ Auth::user()->name ?? 'Accounting Dept.' }}</div>
                <div style="font-size: 8px; color: #64748b; margin-top: 2px;">Tgl: {{ now()->translatedFormat('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <div class="footer">
        Dokumen Resmi ERP Duta Tunggal — Lembar Kartu Aset Sah | Halaman 1 dari 1
    </div>
</body>
</html>
