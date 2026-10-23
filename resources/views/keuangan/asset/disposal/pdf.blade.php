<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Berita Acara Pelepasan Aset #{{ $disposal->id }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.5;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #e11d48;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #15803d;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .doc-title {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-no {
            text-align: right;
            font-size: 10px;
            color: #4b5563;
            font-family: monospace;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin-top: 16px;
            margin-bottom: 8px;
        }
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        table.info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.info-table td.label {
            width: 30%;
            color: #6b7280;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5px;
        }
        table.info-table td.value {
            width: 70%;
            color: #111827;
            font-weight: 500;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        table.data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        table.data-table td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-space {
            height: 60px;
        }
        .signature-line {
            border-top: 1px solid #374151;
            padding-top: 4px;
            font-weight: bold;
            font-size: 10px;
        }
        .signature-role {
            font-size: 9px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    {{-- Header --}}
    <table style="width: 100%;" class="header">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-subtitle">Enterprise Resource Planning System</div>
            </td>
            <td style="width: 50%; vertical-align: middle;">
                <div class="doc-title">Berita Acara Pelepasan Aset</div>
                <div class="doc-no">No: DISP-AST-{{ str_pad($disposal->id, 5, '0', STR_PAD_LEFT) }}</div>
                <div style="text-align: right; font-size: 9px; color: #6b7280;">
                    Tgl Dokumen: {{ $disposal->disposal_date ? $disposal->disposal_date->format('d/m/Y') : now()->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Keterangan Pembuka --}}
    <p style="margin-bottom: 12px; font-size: 10px; color: #374151; text-align: justify;">
        Pada hari ini, tanggal <strong>{{ $disposal->disposal_date ? $disposal->disposal_date->translatedFormat('d F Y') : '-' }}</strong>, telah dilakukan proses pelepasan/penghapusan aset tetap operasional perusahaan dengan rincian data sebagai berikut:
    </p>

    {{-- Identitas Aset Tetap --}}
    <div class="section-title">1. Identitas Aset Tetap</div>
    <table class="info-table">
        <tr>
            <td class="label">Kode Aset</td>
            <td class="value">: <strong style="font-family: monospace;">{{ $disposal->asset->code ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Nama Aset</td>
            <td class="value">: <strong>{{ $disposal->asset->name ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Cabang Lokasi</td>
            <td class="value">: {{ $disposal->asset->cabang->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Akun Aset (COA)</td>
            <td class="value">: {{ $disposal->asset->assetCoa->code ?? '-' }} — {{ $disposal->asset->assetCoa->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Jenis Pelepasan</td>
            <td class="value">: <strong>
                {{ match($disposal->disposal_type) {
                    'sale' => 'Penjualan Aset (Sale)',
                    'scrap' => 'Scrap / Rusak Total / Afkir',
                    'donation' => 'Donasi / Sumbangan',
                    'theft' => 'Hilang / Kasus Pencurian',
                    default => ucfirst($disposal->disposal_type)
                } }}
            </strong></td>
        </tr>
    </table>

    {{-- Rekonsiliasi Finansial & Akuntansi --}}
    <div class="section-title">2. Rekonsiliasi Finansial & Nilai Buku Pelepasan</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Komponen Finansial</th>
                <th style="text-align: right;">Nominal (Rp)</th>
                <th>Keterangan Akuntansi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Nilai Perolehan Awal (Historical Cost)</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold;">
                    {{ \App\Helpers\MoneyHelper::rupiah($disposal->asset->purchase_cost ?? 0) }}
                </td>
                <td>Dihapus dari Akun Aset Tetap (Kredit)</td>
            </tr>
            <tr>
                <td>Akumulasi Penyusutan Tercatat</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #b45309;">
                    {{ \App\Helpers\MoneyHelper::rupiah($disposal->asset->accumulated_depreciation ?? 0) }}
                </td>
                <td>Dihapus dari Akumulasi Penyusutan (Debit)</td>
            </tr>
            <tr style="background-color: #f9fafb;">
                <td style="font-weight: bold;">Nilai Buku Bersih Saat Pelepasan (NBV)</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #111827;">
                    {{ \App\Helpers\MoneyHelper::rupiah($disposal->book_value_at_disposal) }}
                </td>
                <td>Sisa nilai aset yang dihentikan</td>
            </tr>
            <tr>
                <td>Penerimaan Kas Penjualan</td>
                <td style="text-align: right; font-family: monospace; font-weight: bold; color: #15803d;">
                    {{ $disposal->sale_price > 0 ? \App\Helpers\MoneyHelper::rupiah($disposal->sale_price) : '-' }}
                </td>
                <td>Dicatat ke Akun Kas/Bank (Debit)</td>
            </tr>
            <tr style="background-color: #fef2f2; font-weight: bold;">
                <td style="color: {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? '#15803d' : '#b91c1c' }};">
                    {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'Laba Pelepasan Aset Tetap (Gain)' : 'Rugi Pelepasan Aset Tetap (Loss)' }}
                </td>
                <td style="text-align: right; font-family: monospace; color: {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? '#15803d' : '#b91c1c' }};">
                    {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? '+' : '-' }}{{ \App\Helpers\MoneyHelper::rupiah(abs($disposal->gain_loss_amount ?? 0)) }}
                </td>
                <td style="font-size: 9px; color: #4b5563;">
                    {{ ($disposal->gain_loss_amount ?? 0) >= 0 ? 'Pendapatan Lain-lain (Kredit)' : 'Beban Kerugian Pelepasan (Debit)' }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Catatan Berita Acara --}}
    <div class="section-title">3. Catatan / Alasan Pelepasan</div>
    <div style="padding: 8px 12px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 10px; color: #374151;">
        {{ $disposal->notes ?: 'Tidak ada catatan tambahan.' }}
    </div>

    {{-- Lembar Pengesahan / Tanda Tangan 3 Pihak --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="signature-role">Petugas Pembuat Berita Acara</div>
                <div class="signature-space"></div>
                <div class="signature-line">{{ $disposal->approvedBy->name ?? 'Staff Inventaris' }}</div>
                <div class="signature-role">Pengelola Aset Tetap</div>
            </td>
            <td>
                <div class="signature-role">Penanggung Jawab Unit / Cabang</div>
                <div class="signature-space"></div>
                <div class="signature-line">Kepala Cabang</div>
                <div class="signature-role">{{ $disposal->asset->cabang->nama ?? 'Cabang' }}</div>
            </td>
            <td>
                <div class="signature-role">Mengetahui & Menyetujui</div>
                <div class="signature-space"></div>
                <div class="signature-line">Accounting & Finance</div>
                <div class="signature-role">Head of Finance & Accounting</div>
            </td>
        </tr>
    </table>
</body>
</html>
