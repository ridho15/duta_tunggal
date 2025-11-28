<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Pembelian - {{ $purchaseOrder->po_number }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .header,
        .footer {
            text-align: center;
        }

        .company {
            text-align: left;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
            text-align: center;
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 8px;
            border: 1px solid #000;
            text-align: left;
        }

        .no-border {
            border: none !important;
        }

        .right {
            text-align: right;
        }

        .signature {
            margin-top: 50px;
            text-align: center;
        }

        .signature .box {
            width: 200px;
            display: inline-block;
            margin: 0 40px;
        }

        .logo {
            width: 120px;
        }
    </style>
</head>

<body>

    <table class="no-border">
        <tr class="no-border">
            <td class="no-border" style="width: 70%">
                <strong>PT DUTA TUNGGAL</strong><br>
                Jl. Contoh No. 123<br>
                Jakarta, Indonesia<br>
                Telp: (021) 12345678<br>
                Email: admin@dutatunggal.co.id
            </td>
            <td class="no-border right">
                <img src="{{ public_path('images/logo.png') }}" class="logo">
            </td>
        </tr>
    </table>

    <div class="title">PEMBELIAN</div>

    <table>
        <tr>
            <td><strong>No. PO:</strong></td>
            <td>{{ $purchaseOrder->po_number }}</td>
            <td><strong>Tanggal:</strong></td>
            <td>{{ \Carbon\Carbon::parse($purchaseOrder->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Supplier:</strong></td>
            <td colspan="3">{{ $purchaseOrder->supplier->name }}<br>{{ $purchaseOrder->supplier->address }}</td>
        </tr>
        <tr>
            <td><strong>Tipe:</strong></td>
            <td colspan="3">{{ $purchaseOrder->is_asset ? 'Asset' : 'Non Asset' }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Harga Satuan</th>
                <th>Discount</th>
                <th>Tax</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach ($purchaseOrder->purchaseOrderItem as $index => $item)
            @php
            $subtotal = ($item->quantity * $item->unit_price) - $item->discount + $item->tax;
            $total += $subtotal;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td>{{ $item->product->uom->name }}</td>
                <td class="right">Rp.{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td class="right">Rp.{{ number_format($item->discount, 0, ',', '.') }}</td>
                <td class="right">Rp.{{ number_format($item->tax, 0, ',', '.') }}</td>
                <td class="right">Rp.{{ number_format($subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="7" class="right"><strong>Total</strong></td>
                <td class="right"><strong>Rp.{{ number_format($total, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>

    @if($purchaseOrder->is_asset)
    <div style="margin-top: 20px;">
        <h3 style="text-decoration: underline; margin-bottom: 10px;">Informasi Asset</h3>
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Asset</th>
                    <th>Nilai Perolehan</th>
                    <th>Umur Manfaat (Tahun)</th>
                    <th>Nilai Sisa</th>
                    <th>COA Aset</th>
                    <th>COA Akumulasi</th>
                    <th>COA Beban</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseOrder->assets as $index => $asset)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $asset->name }}</td>
                    <td class="right">Rp.{{ number_format($asset->purchase_cost, 0, ',', '.') }}</td>
                    <td class="right">{{ $asset->useful_life_years }}</td>
                    <td class="right">Rp.{{ number_format($asset->salvage_value, 0, ',', '.') }}</td>
                    <td>({{ $asset->assetCoa->code }}) {{ $asset->assetCoa->name }}</td>
                    <td>({{ $asset->accumulatedDepreciationCoa->code }}) {{ $asset->accumulatedDepreciationCoa->name }}</td>
                    <td>({{ $asset->depreciationExpenseCoa->code }}) {{ $asset->depreciationExpenseCoa->name }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="signature">
        <div class="box">
            <p>Disetujui Oleh,</p>
            @if ($purchaseOrder->approvedBy->signature)
            <img src="{{ public_path('storage' . $purchaseOrder->approvedBy->signature) }}" alt="" style="height: 75px">
            @else
            <br><br><br>
            @endif
            <p><strong>{{ $purchaseOrder->approved_by->name ?? 'Owner' }}</strong></p>
        </div>
        <div class="box">
            <p>Dibuat Oleh,</p>
            @if ($purchaseOrder->createdBy->signature)
            <img src="{{ public_path('storage' . $purchaseOrder->createdBy->signature) }}" style="height: 75px" alt="">
            @else
            <br><br><br>
            @endif
            <p><strong>{{ $purchaseOrder->created_by->name ?? 'Staff Purchasing' }}</strong></p>
        </div>
    </div>

</body>

</html>