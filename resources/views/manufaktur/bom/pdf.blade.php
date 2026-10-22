<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Resep BOM – {{ $billOfMaterial->code }}</title>
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
        .cost-summary {
            width: 250px;
            float: right;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .cost-summary td {
            padding: 4px 6px;
            font-size: 10.5px;
        }
        .grand-total {
            border-top: 2px solid #15803d;
            font-weight: bold;
            color: #15803d;
        }
        .signature-table {
            width: 100%;
            margin-top: 50px;
            text-align: center;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: bottom;
            height: 60px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-name">DUTA TUNGGAL ERP</div>
                    <div style="font-size: 9px; color: #666;">Distributor & Manufaktur Bahan Bangunan • Pabrik / Cabang: {{ $billOfMaterial->cabang?->nama ?? 'Pusat' }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="doc-title">LEMBAR FORMULA RESEP (BOM)</div>
                    <div style="font-size: 10px; font-weight: bold; color: #333;">NO: {{ $billOfMaterial->code }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td class="label">Nama Formula:</td>
            <td><strong>{{ $billOfMaterial->nama_bom }}</strong></td>
            <td class="label" style="text-align: right;">Tanggal Cetak:</td>
            <td style="text-align: right; width: 120px;">{{ date('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Produk Jadi:</td>
            <td>{{ $billOfMaterial->product?->name }} (SKU: {{ $billOfMaterial->product?->sku ?? '-' }})</td>
            <td class="label" style="text-align: right;">Status Resep:</td>
            <td style="text-align: right; width: 120px;">
                <strong>{{ $billOfMaterial->is_active ? 'AKTIF' : 'NONAKTIF' }}</strong>
            </td>
        </tr>
        <tr>
            <td class="label">Target Output:</td>
            <td><strong>{{ rtrim(rtrim((string) $billOfMaterial->quantity, '0'), '.') }} {{ $billOfMaterial->uom?->name ?? 'Pcs' }}</strong></td>
            <td class="label" style="text-align: right;">Estimasi HPP/Unit:</td>
            <td style="text-align: right; width: 120px; font-weight: bold; color: #15803d;">
                Rp {{ number_format($billOfMaterial->quantity > 0 ? (float)$billOfMaterial->total_cost / (float)$billOfMaterial->quantity : (float)$billOfMaterial->total_cost, 0, ',', '.') }}
            </td>
        </tr>
        @if ($billOfMaterial->note)
            <tr>
                <td class="label">Catatan Teknis:</td>
                <td colspan="3">{{ $billOfMaterial->note }}</td>
            </tr>
        @endif
    </table>

    <div style="font-size: 11px; font-weight: bold; color: #15803d; margin-top: 15px; margin-bottom: 5px;">
        KOMPONEN BAHAN BAKU YANG DIBUTUHKAN:
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;" class="text-center">No</th>
                <th style="width: 100px;">SKU</th>
                <th>Nama Bahan Baku</th>
                <th style="width: 80px;" class="text-center">Kebutuhan Qty</th>
                <th style="width: 70px;" class="text-center">Satuan</th>
                <th style="width: 90px;" class="text-right">Biaya Satuan</th>
                <th style="width: 100px;" class="text-right">Subtotal Biaya</th>
            </tr>
        </thead>
        <tbody>
            @php $totalMaterialCost = 0; @endphp
            @foreach ($billOfMaterial->items as $idx => $item)
                @php
                    $subtotal = (float)($item->subtotal ?? ($item->quantity * $item->unit_price));
                    $totalMaterialCost += $subtotal;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $item->product?->sku ?? '-' }}</td>
                    <td>{{ $item->product?->name ?? '-' }}</td>
                    <td class="text-center font-bold">{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }}</td>
                    <td class="text-center">{{ $item->uom?->name ?? 'Pcs' }}</td>
                    <td class="text-right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right font-bold">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="cost-summary">
        <tr>
            <td>Total Biaya Bahan:</td>
            <td class="text-right font-bold">Rp {{ number_format($totalMaterialCost, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Biaya Tenaga Kerja (TKL):</td>
            <td class="text-right font-bold">Rp {{ number_format((float) $billOfMaterial->labor_cost, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Biaya Overhead Pabrik (FOH):</td>
            <td class="text-right font-bold">Rp {{ number_format((float) $billOfMaterial->overhead_cost, 0, ',', '.') }}</td>
        </tr>
        <tr class="grand-total">
            <td>Grand Total Biaya BOM:</td>
            <td class="text-right">Rp {{ number_format((float) $billOfMaterial->total_cost, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    <table class="signature-table">
        <tr>
            <td>
                <div>Disusun Oleh,</div>
                <div style="height: 50px;"></div>
                <div>( Bagian R&D / Formula )</div>
            </td>
            <td>
                <div>Diperiksa Oleh,</div>
                <div style="height: 50px;"></div>
                <div>( Kepala Produksi / QC )</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div style="height: 50px;"></div>
                <div>( Manajer Pabrik )</div>
            </td>
        </tr>
    </table>
</body>
</html>
