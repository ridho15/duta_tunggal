<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan Transfer - {{ $transfer->transfer_number }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 1.2cm 1.5cm 1.2cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border-bottom: 2px solid #047857;
            padding-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-title {
            font-size: 16px;
            font-weight: bold;
            color: #065f46;
            margin: 0;
            text-transform: uppercase;
        }
        .company-sub {
            font-size: 9px;
            color: #6b7280;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 15px;
            font-weight: bold;
            color: #111827;
            text-align: right;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.5px;
        }
        .doc-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            font-weight: bold;
            color: #047857;
            text-align: right;
            margin-top: 3px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 10px;
            width: 25%;
            vertical-align: top;
        }
        .meta-label {
            font-size: 8.5px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: bold;
            display: block;
            margin-bottom: 2px;
        }
        .meta-value {
            font-weight: bold;
            color: #111827;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #047857;
            color: #ffffff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            border: 1px solid #047857;
        }
        .items-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 9.5px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .total-row td {
            background-color: #ecfdf5 !important;
            font-weight: bold;
            border-top: 2px solid #047857;
            color: #065f46;
        }
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .sig-box {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px;
            background-color: #ffffff;
        }
        .sig-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #374151;
            margin-bottom: 50px;
        }
        .sig-line {
            border-top: 1px dashed #9ca3af;
            padding-top: 4px;
            font-size: 9.5px;
            font-weight: bold;
            color: #111827;
        }
        .sig-date {
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 2px;
        }
        .footer-note {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 15px;
            text-align: right;
            border-top: 1px solid #f3f4f6;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    {{-- Header Perusahaan & Judul Dokumen --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-title">CV. DUTA TUNGGAL</div>
                <div class="company-sub">
                    Distributor Perlengkapan Rumah Tangga & Bahan Bangunan<br>
                    Jl. Raya Industri No. 88, Sidoarjo – Jawa Timur | Telp: (031) 8988-888
                </div>
            </td>
            <td style="width: 45%;">
                <div class="doc-title">Surat Jalan Pindah Stok</div>
                <div class="doc-number">No: {{ $transfer->transfer_number }}</div>
            </td>
        </tr>
    </table>

    {{-- Meta Dokumen --}}
    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">Tanggal Transfer</span>
                <span class="meta-value">{{ \Carbon\Carbon::parse($transfer->transfer_date)->translatedFormat('d F Y') }}</span>
            </td>
            <td>
                <span class="meta-label">Gudang Asal (Pengirim)</span>
                <span class="meta-value">{{ $transfer->fromWarehouse?->name ?? '-' }}</span>
                <span style="font-size: 8.5px; color: #6b7280; display: block;">Kode: {{ $transfer->fromWarehouse?->kode ?? '-' }}</span>
            </td>
            <td>
                <span class="meta-label">Gudang Tujuan (Penerima)</span>
                <span class="meta-value">{{ $transfer->toWarehouse?->name ?? '-' }}</span>
                <span style="font-size: 8.5px; color: #6b7280; display: block;">Kode: {{ $transfer->toWarehouse?->kode ?? '-' }}</span>
            </td>
            <td>
                <span class="meta-label">Status Dokumen</span>
                <span class="meta-value" style="color: #047857;">{{ strtoupper($transfer->status) }}</span>
            </td>
        </tr>
    </table>

    {{-- Tabel Item Barang --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">No</th>
                <th style="width: 15%; text-align: left;">Kode / SKU</th>
                <th style="width: 35%; text-align: left;">Nama Produk / Barang</th>
                <th style="width: 10%; text-align: center;">Satuan</th>
                <th style="width: 12%; text-align: left;">Rak Asal</th>
                <th style="width: 12%; text-align: left;">Rak Tujuan</th>
                <th style="width: 11%; text-align: right;">Kuantitas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transfer->stockTransferItem as $idx => $item)
                <tr>
                    <td style="text-align: center; color: #6b7280;">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $item->product?->sku ?? '-' }}</td>
                    <td>
                        <strong>{{ $item->product?->name ?? '-' }}</strong>
                        @if ($item->product?->barcode)
                            <span style="font-size: 8px; color: #6b7280; display: block;">Barcode: {{ $item->product->barcode }}</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->product?->uom?->name ?? 'Pcs' }}</td>
                    <td>{{ $item->fromRak?->name ?? '-' }}</td>
                    <td>{{ $item->toRak?->name ?? '-' }}</td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace;">
                        {{ number_format($item->quantity, 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6" style="text-align: right; text-transform: uppercase;">
                    Total Kuantitas Barang Dipindahkan:
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 11px;">
                    {{ number_format($transfer->stockTransferItem->sum('quantity'), 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Kolom Otorisasi Tanda Tangan --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diserahkan Oleh (Gudang Asal)</div>
                    <div class="sig-line">( ........................................ )</div>
                    <div class="sig-date">Tgl: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Dibawa Oleh (Sopir / Ekspedisi)</div>
                    <div class="sig-line">( ........................................ )</div>
                    <div class="sig-date">No. Polisi Kendaraan: ...................</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diterima Oleh (Gudang Tujuan)</div>
                    <div class="sig-line">( ........................................ )</div>
                    <div class="sig-date">Tgl: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Footer Info --}}
    <div class="footer-note">
        Dicetak otomatis dari Sistem ERP Duta Tunggal pada {{ now()->translatedFormat('d F Y H:i:s') }} | Dokumen resmi pemindahan fisik barang antar gudang
    </div>
</body>
</html>
