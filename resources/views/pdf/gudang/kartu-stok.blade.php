<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Persediaan - {{ $product->sku ?: $product->name }}</title>
    <style>
        @page {
            size: a4 landscape;
            margin: 1.2cm 1.5cm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1f2937;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #047857;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 13pt;
            font-weight: bold;
            text-align: right;
            text-transform: uppercase;
            color: #111827;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 3px 6px;
            font-size: 8.5pt;
        }
        .meta-label {
            font-weight: bold;
            color: #4b5563;
            width: 15%;
        }
        .meta-val {
            color: #111827;
            width: 35%;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
        }
        .kpi-box {
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            padding: 6px 10px;
            text-align: center;
            width: 25%;
        }
        .kpi-title {
            font-size: 7.5pt;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 12pt;
            font-weight: bold;
            font-family: monospace;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 5px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        .data-table td {
            padding: 5px;
            border: 1px solid #e5e7eb;
            font-size: 8pt;
        }
        .font-mono {
            font-family: monospace;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bg-subtotal {
            background-color: #f9fafb;
            font-weight: bold;
        }
        .signatures-table {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }
        .signatures-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            font-size: 8.5pt;
        }
        .sign-line {
            margin-top: 50px;
            border-top: 1px solid #4b5563;
            width: 70%;
            display: inline-block;
        }
    </style>
</head>
<body>

    {{-- Header Perusahaan & Dokumen --}}
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: bottom;">
                <div class="company-name">CV. DUTA TUNGGAL</div>
                <div style="font-size: 8pt; color: #4b5563;">
                    Distributor & Supplier Bahan Bangunan, Besi & Baja<br>
                    Sistem Informasi Manajemen Persediaan (ERP)
                </div>
            </td>
            <td style="width: 45%; vertical-align: bottom; text-align: right;">
                <div class="report-title">KARTU PERSEDIAAN BARANG</div>
                <div style="font-size: 8.5pt; color: #4b5563; margin-top: 2px;">
                    Periode: {{ date('d/m/Y', strtotime($filters['start_date'])) }} s/d {{ date('d/m/Y', strtotime($filters['end_date'])) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Identitas Produk & Gudang --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Nama Produk</td>
            <td class="meta-val">: <strong>{{ $product->name }}</strong></td>
            <td class="meta-label">Gudang Penyimpanan</td>
            <td class="meta-val">: {{ $warehouse ? $warehouse->name : 'Semua Gudang (Konsolidasian)' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Kode / SKU</td>
            <td class="meta-val">: <span class="font-mono">{{ $product->sku ?: '-' }}</span></td>
            <td class="meta-label">Satuan Ukuran</td>
            <td class="meta-val">: {{ $product->uom?->name ?? 'Pcs' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Kategori</td>
            <td class="meta-val">: {{ $product->productCategory?->name ?? '-' }}</td>
            <td class="meta-label">Harga Pokok (HPP)</td>
            <td class="meta-val">: Rp {{ number_format($product->cost_price, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Ringkasan KPI Mutasi Periode --}}
    <table class="kpi-table">
        <tr>
            <td class="kpi-box">
                <div class="kpi-title">Saldo Awal</div>
                <div class="kpi-value" style="color: #1f2937;">{{ number_format($openingQty, 0, ',', '.') }}</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-title">Total Mutasi Masuk (+)</div>
                <div class="kpi-value" style="color: #047857;">+{{ number_format($totalIn, 0, ',', '.') }}</div>
            </td>
            <td class="kpi-box">
                <div class="kpi-title">Total Mutasi Keluar (-)</div>
                <div class="kpi-value" style="color: #b91c1c;">-{{ number_format($totalOut, 0, ',', '.') }}</div>
            </td>
            <td class="kpi-box" style="background-color: #eff6ff; border-color: #bfdbfe;">
                <div class="kpi-title" style="color: #1e40af;">Saldo Akhir Berjalan</div>
                <div class="kpi-value" style="color: #1d4ed8;">{{ number_format($closingQty, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    {{-- Tabel Mutasi Kronologis --}}
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th style="width: 75px;">Tanggal</th>
                <th style="width: 120px;">No. Dokumen / Referensi</th>
                <th style="width: 110px;">Tipe Mutasi</th>
                <th style="width: 100px;">Gudang</th>
                <th style="width: 65px;" class="text-right">Masuk (+)</th>
                <th style="width: 65px;" class="text-right">Keluar (-)</th>
                <th style="width: 75px;" class="text-right">Saldo Berjalan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            {{-- Baris Saldo Awal --}}
            <tr style="background-color: #f9fafb; font-weight: bold;">
                <td class="text-center">•</td>
                <td>{{ date('d/m/Y', strtotime($filters['start_date'])) }}</td>
                <td style="font-family: monospace;">SALDO-AWAL</td>
                <td>Saldo Awal Periode</td>
                <td>-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right font-mono" style="color: #111827;">{{ number_format($openingQty, 0, ',', '.') }}</td>
                <td style="font-style: italic; color: #6b7280;">Saldo bawaan per awal periode pembukuan</td>
            </tr>

            @forelse ($movements as $idx => $m)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ date('d/m/Y H:i', strtotime($m['date'])) }}</td>
                    <td class="font-mono"><strong>{{ $m['reference_number'] }}</strong></td>
                    <td>{{ $m['type_label'] }}</td>
                    <td>{{ $m['warehouse_name'] }}</td>
                    <td class="text-right font-mono" style="color: #047857; font-weight: bold;">
                        {{ $m['qty_in'] > 0 ? '+' . number_format($m['qty_in'], 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-right font-mono" style="color: #b91c1c; font-weight: bold;">
                        {{ $m['qty_out'] > 0 ? '-' . number_format($m['qty_out'], 0, ',', '.') : '-' }}
                    </td>
                    <td class="text-right font-mono" style="font-weight: bold; background-color: #f8fafc;">
                        {{ number_format($m['running_balance'], 0, ',', '.') }}
                    </td>
                    <td style="color: #4b5563;">{{ $m['notes'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="padding: 15px; color: #9ca3af;">
                        Tidak ada pergerakan mutasi fisik barang dalam periode tanggal ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-subtotal">
                <td colspan="5" class="text-right" style="font-size: 8pt; text-transform: uppercase;">
                    TOTAL MUTASI PERIODE & SALDO AKHIR:
                </td>
                <td class="text-right font-mono" style="color: #047857; font-weight: bold;">
                    +{{ number_format($totalIn, 0, ',', '.') }}
                </td>
                <td class="text-right font-mono" style="color: #b91c1c; font-weight: bold;">
                    -{{ number_format($totalOut, 0, ',', '.') }}
                </td>
                <td class="text-right font-mono" style="color: #1d4ed8; font-weight: bold; background-color: #dbeafe;">
                    {{ number_format($closingQty, 0, ',', '.') }}
                </td>
                <td style="font-size: 7.5pt; color: #6b7280;">
                    Posisi per {{ date('d/m/Y', strtotime($filters['end_date'])) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Kolom Otorisasi Tanda Tangan --}}
    <table class="signatures-table">
        <tr>
            <td>
                <div>Petugas Gudang,</div>
                <div class="sign-line"></div>
                <div style="font-size: 8pt; color: #6b7280; margin-top: 3px;">Staff Administrasi Gudang</div>
            </td>
            <td>
                <div>Diperiksa Oleh,</div>
                <div class="sign-line"></div>
                <div style="font-size: 8pt; color: #6b7280; margin-top: 3px;">Kepala Bagian Gudang</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div class="sign-line"></div>
                <div style="font-size: 8pt; color: #6b7280; margin-top: 3px;">Manajer Operasional / Finance</div>
            </td>
        </tr>
    </table>

    {{-- Cetak info waktu --}}
    <div style="font-size: 7pt; color: #9ca3af; margin-top: 20px; text-align: right;">
        Dicetak otomatis oleh Sistem ERP CV. Duta Tunggal pada {{ now()->format('d/m/Y H:i:s') }} WIB
    </div>

</body>
</html>
