<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $materialIssue->type === 'issue' ? 'Bukti Pengeluaran Bahan' : 'Bukti Retur Sisa Bahan' }} – {{ $materialIssue->issue_number }}</title>
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
            margin-top: 35px;
            text-align: center;
        }
        .signature-table td {
            width: 33.33%;
            vertical-align: bottom;
            height: 65px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-name">DUTA TUNGGAL ERP</div>
                    <div style="font-size: 9px; color: #666;">Distributor & Manufaktur Bahan Bangunan • Cabang: {{ $materialIssue->warehouse?->cabang?->nama ?? 'Pusat' }}</div>
                </td>
                <td style="text-align: right;">
                    <div class="doc-title">
                        {{ $materialIssue->type === 'issue' ? 'BUKTI PENGELUARAN BAHAN (MATERIAL ISSUE)' : 'BUKTI RETUR SISA BAHAN (MATERIAL RETURN)' }}
                    </div>
                    <div style="font-size: 11px; font-weight: bold; color: #333;">NO: {{ $materialIssue->issue_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr>
                        <td class="label">Tanggal Dokumen</td>
                        <td>: {{ $materialIssue->issue_date?->format('d/m/Y') ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Gudang</td>
                        <td>: ({{ $materialIssue->warehouse?->kode ?? '-' }}) {{ $materialIssue->warehouse?->name ?? 'Gudang Utama' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Tipe Transaksi</td>
                        <td>: <strong>{{ $materialIssue->type === 'issue' ? 'Pengeluaran Bahan ke Pabrik' : 'Pengembalian Sisa Bahan ke Gudang' }}</strong></td>
                    </tr>
                </table>
            </td>
            <td style="width: 50%;">
                <table style="width: 100%;">
                    <tr>
                        <td class="label">Referensi SPK / MO</td>
                        <td>: {{ $materialIssue->manufacturingOrder?->mo_number ?? $materialIssue->productionPlan?->plan_number ?? 'Non-SPK / Manual' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Produk yang Diproduksi</td>
                        <td>: {{ $materialIssue->manufacturingOrder?->productionPlan?->product?->name ?? $materialIssue->productionPlan?->product?->name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Status Dokumen</td>
                        <td>: <strong style="text-transform: uppercase;">{{ $materialIssue->status }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div style="font-weight: bold; font-size: 11px; margin-bottom: 5px; color: #1e293b;">
        RINCIAN BAHAN BAKU:
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 30px;">No.</th>
                <th style="width: 80px;">Kode / SKU</th>
                <th>Nama Bahan Baku</th>
                <th style="width: 60px;">Satuan</th>
                <th style="width: 70px;" class="text-right">Kuantitas</th>
                <th style="width: 90px;" class="text-right">Harga Pokok</th>
                <th style="width: 100px;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($materialIssue->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ $item->product?->sku ?? '-' }}</td>
                    <td><strong>{{ $item->product?->name ?? '-' }}</strong></td>
                    <td class="text-center">{{ $item->uom?->name ?? $item->product?->uom?->name ?? 'Pcs' }}</td>
                    <td class="text-right">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->cost_per_unit, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($item->total_cost, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center" style="padding: 15px; color: #999;">Tidak ada bahan terdaftar.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="4" class="text-right">TOTAL BIAYA BAHAN:</td>
                <td class="text-right">{{ number_format($materialIssue->items->sum('quantity'), 2, ',', '.') }}</td>
                <td></td>
                <td class="text-right" style="color: #15803d; font-size: 11px;">
                    Rp {{ number_format($materialIssue->total_cost, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
    </table>

    @if ($materialIssue->notes)
        <div style="margin-top: 10px; font-size: 10px; color: #555; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px;">
            <strong>Catatan:</strong> {{ $materialIssue->notes }}
        </div>
    @endif

    <table class="signature-table">
        <tr>
            <td>
                <div>Yang Meminta / Menerima,</div>
                <br><br><br>
                <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 3px;">
                    ( Supervisor Produksi )
                </div>
            </td>
            <td>
                <div>Yang Menyerahkan / Memeriksa,</div>
                <br><br><br>
                <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 3px;">
                    ( Petugas Gudang )
                </div>
            </td>
            <td>
                <div>Mengetahui,</div>
                <br><br><br>
                <div style="border-top: 1px solid #333; width: 80%; margin: 0 auto; padding-top: 3px;">
                    ( Kepala Pabrik / Gudang )
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
