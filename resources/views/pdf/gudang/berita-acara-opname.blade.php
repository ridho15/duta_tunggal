<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Stock Opname - {{ $opname->opname_number }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 1.2cm 1.5cm 1.2cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #1f2937;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
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
            font-size: 8.5px;
            color: #6b7280;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 14px;
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
            margin-bottom: 12px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 5px 8px;
            font-size: 9px;
            width: 25%;
            vertical-align: top;
        }
        .meta-label {
            font-size: 8px;
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
            margin-bottom: 15px;
        }
        .items-table th {
            background-color: #047857;
            color: #ffffff;
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 6px;
            border: 1px solid #047857;
        }
        .items-table td {
            padding: 5px 6px;
            border: 1px solid #e5e7eb;
            font-size: 9px;
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
            margin-top: 15px;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }
        .sig-box {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 8px;
            background-color: #ffffff;
        }
        .sig-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #374151;
            margin-bottom: 45px;
        }
        .sig-line {
            border-top: 1px dashed #9ca3af;
            padding-top: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #111827;
        }
        .footer-note {
            font-size: 8px;
            color: #9ca3af;
            margin-top: 10px;
            text-align: right;
            border-top: 1px solid #f3f4f6;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    {{-- Header --}}
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
                <div class="doc-title">
                    {{ $opname->status === 'draft' ? 'Lembar Kerja Hitung Fisik Opname' : 'Berita Acara Stock Opname' }}
                </div>
                <div class="doc-number">No: {{ $opname->opname_number }}</div>
            </td>
        </tr>
    </table>

    {{-- Meta --}}
    <table class="meta-table">
        <tr>
            <td>
                <span class="meta-label">Tanggal Pelaksanaan</span>
                <span class="meta-value">{{ \Carbon\Carbon::parse($opname->opname_date)->translatedFormat('d F Y') }}</span>
            </td>
            <td>
                <span class="meta-label">Lokasi Gudang Audit</span>
                <span class="meta-value">{{ $opname->warehouse?->name ?? '-' }}</span>
                <span style="font-size: 8px; color: #6b7280; display: block;">Kode: {{ $opname->warehouse?->kode ?? '-' }}</span>
            </td>
            <td>
                <span class="meta-label">Petugas / Pembuat</span>
                <span class="meta-value">{{ $opname->creator?->name ?? 'Admin Gudang' }}</span>
                @if ($opname->approver)
                    <span style="font-size: 8px; color: #047857; display: block;">Disetujui: {{ $opname->approver->name }}</span>
                @endif
            </td>
            <td>
                <span class="meta-label">Status Dokumen</span>
                <span class="meta-value" style="color: #047857;">{{ strtoupper($opname->status) }}</span>
            </td>
        </tr>
    </table>

    {{-- Tabel Item Hasil Opname --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%; text-align: center;">No</th>
                <th style="width: 14%; text-align: left;">Kode / SKU</th>
                <th style="width: 28%; text-align: left;">Nama Produk / Barang</th>
                <th style="width: 7%; text-align: center;">Satuan</th>
                <th style="width: 9%; text-align: left;">Lokasi Rak</th>
                <th style="width: 8%; text-align: right;">Stok Sistem</th>
                <th style="width: 8%; text-align: right;">Stok Fisik</th>
                <th style="width: 7%; text-align: right;">Selisih</th>
                <th style="width: 15%; text-align: right;">Nilai Selisih (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($opname->items as $idx => $item)
                @php
                    $diff = $item->difference_qty;
                    $isDiff = !is_null($diff) && (float) $diff !== 0.0;
                @endphp
                <tr style="{{ $isDiff ? 'background-color: #fef2f2;' : '' }}">
                    <td style="text-align: center; color: #6b7280;">{{ $idx + 1 }}</td>
                    <td style="font-family: monospace; font-weight: bold;">{{ $item->product?->sku ?? '-' }}</td>
                    <td>
                        <strong>{{ $item->product?->name ?? '-' }}</strong>
                        @if ($item->notes)
                            <span style="font-size: 8px; color: #6b7280; display: block;">Ket: {{ $item->notes }}</span>
                        @endif
                    </td>
                    <td style="text-align: center;">{{ $item->product?->uom?->name ?? 'Pcs' }}</td>
                    <td>{{ $item->rak?->name ?? '-' }}</td>
                    <td style="text-align: right; font-family: monospace;">
                        {{ number_format($item->system_qty, 0, ',', '.') }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: bold;">
                        {{ $item->physical_qty !== null ? number_format($item->physical_qty, 0, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: bold; color: {{ $diff > 0 ? '#047857' : ($diff < 0 ? '#b91c1c' : '#374151') }};">
                        {{ $diff !== null ? ($diff > 0 ? '+' : '') . number_format($diff, 0, ',', '.') : '-' }}
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: bold; color: {{ $item->difference_value > 0 ? '#047857' : ($item->difference_value < 0 ? '#b91c1c' : '#374151') }};">
                        @if ($item->difference_value !== null)
                            {{ ($item->difference_value > 0 ? '+' : '') . number_format($item->difference_value, 0, ',', '.') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align: right; text-transform: uppercase;">
                    Total Kuantitas & Nilai Selisih:
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($opname->items->sum('system_qty'), 0, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($opname->items->whereNotNull('physical_qty')->sum('physical_qty'), 0, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($opname->items->whereNotNull('physical_qty')->sum('difference_qty'), 0, ',', '.') }}
                </td>
                <td style="text-align: right; font-family: monospace; font-size: 10px;">
                    Rp {{ number_format($opname->items->whereNotNull('physical_qty')->sum('difference_value'), 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Otorisasi Tanda Tangan --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Petugas Hitung Fisik</div>
                    <div class="sig-line">{{ $opname->creator?->name ?? '( ........................................ )' }}</div>
                    <div style="font-size: 8px; color: #6b7280; margin-top: 2px;">Staff Gudang</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Diverifikasi & Disetujui</div>
                    <div class="sig-line">{{ $opname->approver?->name ?? '( ........................................ )' }}</div>
                    <div style="font-size: 8px; color: #6b7280; margin-top: 2px;">Kepala Gudang / Supervisor</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">Dibukukan Keuangan (GL)</div>
                    <div class="sig-line">( ........................................ )</div>
                    <div style="font-size: 8px; color: #6b7280; margin-top: 2px;">Bagian Akuntansi</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Dokumen resmi audit fisik persediaan ERP CV. Duta Tunggal | Dicetak pada: {{ now()->translatedFormat('d F Y H:i:s') }}
    </div>
</body>
</html>
