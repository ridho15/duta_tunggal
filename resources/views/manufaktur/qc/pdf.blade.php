<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sertifikat Mutu QC - {{ $qc->qc_number }}</title>
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
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
        }
        .badge-pending {
            background-color: #dbeafe;
            color: #1d4ed8;
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
        $prod = $qc->fromModel;
        $spk = $prod?->manufacturingOrder;
        $plan = $spk?->productionPlan;
        $product = $qc->product ?? $plan?->product;
    @endphp

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="company-name">DUTA TUNGGAL ERP</h1>
                    <p class="company-address">Divisi Penjamin Mutu & Kontrol Kualitas Produk (Quality Assurance)<br>Jl. Raya Industri Bahan Bangunan No. 88</p>
                </td>
                <td class="doc-title">
                    <h2>SERTIFIKAT MUTU QC MANUFAKTUR</h2>
                    <div class="doc-number">{{ $qc->qc_number }}</div>
                    <div style="margin-top: 4px;">
                        <span class="badge {{ $qc->status == 1 ? 'badge-success' : 'badge-pending' }}">
                            {{ $qc->status == 1 ? 'SUDAH DIPROSES' : 'BELUM DIPROSES' }}
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
                        <td class="meta-label">No. Bukti Produksi:</td>
                        <td class="meta-value">{{ $prod?->production_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">No. SPK (MO):</td>
                        <td class="meta-value">{{ $spk?->mo_number ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Petugas Inspeksi:</td>
                        <td class="meta-value">{{ $qc->inspectedBy?->name ?? '-' }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr>
                        <td class="meta-label">Tanggal Uji Mutu:</td>
                        <td class="meta-value">{{ $qc->date_send_stock ? \Carbon\Carbon::parse($qc->date_send_stock)->format('d F Y') : ($qc->created_at ? $qc->created_at->format('d F Y') : '-') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Gudang Tujuan:</td>
                        <td class="meta-value">{{ $qc->warehouse?->name ?? 'Gudang Utama' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Lokasi Rak:</td>
                        <td class="meta-value">{{ $qc->rak ? $qc->rak->name . ' (' . $qc->rak->code . ')' : '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 18%;">Kode SKU</th>
                <th style="width: 35%;">Nama Produk Jadi</th>
                <th style="width: 14%; text-align: right;">Total Diuji</th>
                <th style="width: 14%; text-align: right;">Lolos (Passed)</th>
                <th style="width: 14%; text-align: right;">Reject</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalTested = (float)$qc->passed_quantity + (float)$qc->rejected_quantity;
            @endphp
            <tr>
                <td style="text-align: center;">1</td>
                <td style="font-family: monospace; font-weight: bold;">{{ $product?->sku ?? '-' }}</td>
                <td>
                    <strong>{{ $product?->name ?? 'Produk Hasil Produksi' }}</strong>
                    <div style="font-size: 8pt; color: #666;">Kategori: {{ $product?->category?->name ?? '-' }}</div>
                </td>
                <td style="text-align: right; font-family: monospace;">
                    {{ number_format($totalTested, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #15803d;">
                    {{ number_format((float)$qc->passed_quantity, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                </td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #b91c1c;">
                    {{ number_format((float)$qc->rejected_quantity, 2, ',', '.') }} {{ $product?->uom?->name ?? 'Pcs' }}
                </td>
            </tr>
        </tbody>
    </table>

    @if ($qc->reason_reject)
        <div style="margin-bottom: 20px; padding: 10px; background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; font-size: 8.5pt;">
            <strong style="color: #991b1b;">Alasan Penolakan / Catatan Cacat:</strong>
            <p style="margin: 4px 0 0 0; color: #7f1d1d;">{{ $qc->reason_reject }}</p>
        </div>
    @endif

    @if ($qc->notes)
        <div style="margin-bottom: 20px; padding: 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 8.5pt;">
            <strong style="color: #374151;">Catatan Pemeriksaan Mutu:</strong>
            <p style="margin: 4px 0 0 0; color: #4b5563;">{{ $qc->notes }}</p>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                Diperiksa Oleh,<br><strong>Petugas Quality Control</strong>
                <div class="sign-line"></div>
                ({{ $qc->inspectedBy?->name ?? '..............................' }})
            </td>
            <td>
                Diterima Oleh,<br><strong>Kepala Gudang Jadi</strong>
                <div class="sign-line"></div>
                (...........................................)
            </td>
            <td>
                Disetujui Oleh,<br><strong>Manajer QA / Pabrik</strong>
                <div class="sign-line"></div>
                (...........................................)
            </td>
        </tr>
    </table>

    <div class="footer">
        Duta Tunggal ERP — Dicetak pada: {{ now()->format('d/m/Y H:i:s') }}
    </div>
</body>
</html>
