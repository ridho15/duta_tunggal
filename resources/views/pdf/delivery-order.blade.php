<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Delivery Order - PT Duta Tunggal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .header {
            text-align: center;
        }

        .logo {
            height: 60px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-decoration: underline;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 5px;
            text-align: left;
        }

        .sign {
            text-align: center;
            margin-top: 50px;
        }
    </style>
</head>

<body>

    @php $doc = $doc ?? app(\App\Services\DocumentPrintBuilder::class)->deliveryOrder($deliveryOrder); @endphp
    @include('pdf.partials.watermark')
    @include('pdf.partials.company-header')
    @include('pdf.partials.doc-meta')

    <br>

    <h4>Detail Barang:</h4>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Harga Satuan</th>
                <th>Discount (%)</th>
                <th>Tax (%)</th>
                <th>Tax Amount</th>
                <th>Subtotal</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Nilai DO dari satu sumber (DeliveryOrderValuation/LineAmounts) — sama dengan invoice yang terbit dari DO ini.
                $valuation = app(\App\Services\DeliveryOrderValuation::class)->forDeliveryOrder($deliveryOrder);
                $subtotal = $valuation['goods_total'];
                $total = $valuation['total'];
            @endphp
            @foreach ($deliveryOrder->deliveryOrderItem as $index => $item)
            @php
                $line = $valuation['by_item'][$item->id] ?? [];
                $price = (float) ($line['price'] ?? 0);
                $discountPct = (float) ($line['discount'] ?? 0);
                $taxRate = (float) ($line['tax_rate'] ?? 0);
                $taxAmount = (float) ($line['tax_amount'] ?? 0);
                $lineSubtotal = (float) ($line['total'] ?? 0);
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($price,2,',','.') }}</td>
                <td>{{ number_format($discountPct,2) }}%</td>
                <td>{{ number_format($taxRate,2) }}%</td>
                <td>Rp {{ number_format($taxAmount,2,',','.') }}</td>
                <td>Rp {{ number_format($lineSubtotal,2,',','.') }}</td>
                <td>{{ $item->reason }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table style="border: none; margin-top: 20px; width: 50%; margin-left: auto;">
        <tr>
            <td style="border: none; text-align: right; font-weight: bold;">Subtotal:</td>
            <td style="border: none; text-align: right;">Rp {{ number_format($subtotal, 2, ',', '.') }}</td>
        </tr>
        @if($deliveryOrder->additional_cost > 0)
        <tr>
            <td style="border: none; text-align: right; font-weight: bold;">Biaya Tambahan:</td>
            <td style="border: none; text-align: right;">Rp {{ number_format($deliveryOrder->additional_cost, 2, ',', '.') }}</td>
        </tr>
        @endif
        <tr>
            <td style="border: none; text-align: right; font-weight: bold; border-top: 1px solid #333;">Total:</td>
            <td style="border: none; text-align: right; border-top: 1px solid #333; font-weight: bold;">Rp {{ number_format($total, 2, ',', '.') }}</td>
        </tr>
    </table>

    @include('pdf.partials.signature-block')

    <div style="margin-top: 16px; font-size: 9px; color: #666; text-align: center;">
        Dicetak {{ $doc['printed_at'] }}@if ($doc['printed_by']) oleh {{ $doc['printed_by'] }}@endif
    </div>

</body>

</html>