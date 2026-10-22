<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Perintah Kerja – {{ $manufacturingOrder->mo_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #15803d;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #111;
        }
        .doc-title {
            font-size: 14px;
            font-weight: bold;
            color: #15803d;
            text-transform: uppercase;
            text-align: right;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 3px 0;
        }
        .label {
            font-weight: bold;
            color: #666;
            width: 130px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 10px;
        }
        table.data-table th {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            text-align: center;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: bottom;
            height: 60px;
        }
        .checkbox-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1px solid #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-name">DUTA TUNGGAL ERP</div>
                    <div style="font-size: 9px; color: #666;">Distributor & Manufaktur Bahan Bangunan • Cabang / Pabrik: {{ $manufacturingOrder->cabang?->nama ?? 'Pusat' }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="doc-title">SURAT PERINTAH KERJA (SPK)</div>
                    <div style="font-size: 11px; font-weight: bold; color: #333;">NO: {{ $manufacturingOrder->mo_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Produk Target:</td>
            <td><strong>{{ $plan?->product?->name }} (SKU: {{ $plan?->product?->sku ?? '-' }})</strong></td>
            <td class="label" style="text-align: right;">Tanggal Terbit:</td>
            <td style="text-align: right; width: 130px;">{{ $manufacturingOrder->created_at->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Target Output:</td>
            <td><strong style="font-size: 12px; color: #15803d;">{{ rtrim(rtrim((string)$manufacturingOrder->quantity, '0'), '.') }} {{ $plan?->uom?->name ?? 'Pcs' }}</strong></td>
            <td class="label" style="text-align: right;">Status Dokumen:</td>
            <td style="text-align: right; width: 130px;"><strong>{{ strtoupper($manufacturingOrder->status) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Resep BOM Acuan:</td>
            <td>{{ $bom ? "{$bom->code} - {$bom->nama_bom}" : '-' }}</td>
            <td class="label" style="text-align: right;">Target Mulai:</td>
            <td style="text-align: right; width: 130px;">{{ $manufacturingOrder->start_date ? $manufacturingOrder->start_date->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Gudang Bahan:</td>
            <td>{{ $warehouse?->name ?? 'Gudang Utama' }}</td>
            <td class="label" style="text-align: right;">Target Selesai:</td>
            <td style="text-align: right; width: 130px;">{{ $manufacturingOrder->end_date ? $manufacturingOrder->end_date->format('d/m/Y') : '-' }}</td>
        </tr>
        @if ($plan?->notes)
            <tr>
                <td class="label">Catatan Mandor:</td>
                <td colspan="3">{{ $plan->notes }}</td>
            </tr>
        @endif
    </table>

    <div style="font-size: 11px; font-weight: bold; color: #15803d; margin-top: 15px; margin-bottom: 5px;">
        DAFTAR BAHAN BAKU YANG HARUS DIKELUARKAN GUDANG:
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25px;" class="text-center">No</th>
                <th style="width: 90px;">SKU</th>
                <th>Nama Bahan Baku</th>
                <th style="width: 80px;" class="text-center">Kebutuhan Qty</th>
                <th style="width: 60px;" class="text-center">Satuan</th>
                <th style="width: 100px;" class="text-center">Realisasi Keluar</th>
                <th style="width: 50px;" class="text-center">Cek</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @php $items = $manufacturingOrder->items ?: []; @endphp
            @forelse ($items as $idx => $it)
                @php
                    $prod = \App\Models\Product::find($it['product_id'] ?? null);
                    $uom = \App\Models\UnitOfMeasure::find($it['uom_id'] ?? null);
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $prod?->sku ?? '-' }}</td>
                    <td><strong>{{ $prod?->name ?? 'Material' }}</strong></td>
                    <td class="text-center font-bold">{{ rtrim(rtrim((string)($it['quantity'] ?? 0), '0'), '.') }}</td>
                    <td class="text-center">{{ $uom?->name ?? 'pcs' }}</td>
                    <td class="text-center font-mono">....... {{ $uom?->name ?? 'pcs' }}</td>
                    <td class="text-center"><span class="checkbox-box"></span></td>
                    <td>{{ $it['notes'] ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center py-4">Tidak ada komponen bahan tercatat.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size: 10px; color: #666; margin-top: 5px;">
        * Petunjuk Operator: Pastikan bahan baku yang diambil sesuai dengan nama dan jumlah yang tertera. Seluruh selisih atau kerusakan bahan saat pengerjaan wajib dilaporkan ke QC.
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <div>Diserahkan Oleh,</div>
                <div style="height: 45px;"></div>
                <div>( Bagian Gudang Bahan )</div>
            </td>
            <td>
                <div>Diterima & Dikerjakan,</div>
                <div style="height: 45px;"></div>
                <div>( Mandor / Operator )</div>
            </td>
            <td>
                <div>Diperiksa & Disetujui,</div>
                <div style="height: 45px;"></div>
                <div>( Kepala Pabrik / QC )</div>
            </td>
        </tr>
    </table>
</body>
</html>
