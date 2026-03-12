<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - PT Duta Tunggal</title>
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

    <div class="header">
        <img src="{{ public_path('logo_duta_tunggal.png') }}" class="logo" alt="Logo">
        <h2>PT.DUTA TUNGGAL</h2>
        <p>Alamat Perusahaan</p>
        <div class="title">SURAT JALAN</div>
    </div>

    <table style="border: none;">
        <tr>
            <td style="border: none;">No. Surat Jalan</td>
            <td style="border: none;">: {{ $suratJalan->sj_number }}</td>
        </tr>
        <tr>
            <td style="border: none;">Tanggal</td>
            <td style="border: none;">: {{ Carbon\Carbon::parse($suratJalan->issued_at)->locale('id')->format('D, d M
                Y')
                }}</td>
        </tr>
        <tr>
            <td style="border: none;">Customer</td>
            <td style="border: none;">: @foreach ($suratJalan->deliveryOrder as $deliveryOrder)
                @foreach ($deliveryOrder->salesOrders as $salesOrder)
                {{ $salesOrder->customer->name }},
                @endforeach
                @endforeach</td>
        </tr>
        <tr>
            <td style="border: none;">Alamat Pengiriman</td>
            <td style="border: none;">: @foreach ($suratJalan->deliveryOrder as $deliveryOrder)
                @foreach ($deliveryOrder->salesOrders as $salesOrder)
                {{ $salesOrder->shipped_to ?? '-' }},
                @endforeach
                @endforeach</td>
        </tr>
        <tr>
            <td style="border: none;">Cabang</td>
            <td style="border: none;">: @foreach ($suratJalan->deliveryOrder as $deliveryOrder)
                {{ $deliveryOrder->cabang->name ?? 'N/A' }},
                @endforeach</td>
        </tr>
    </table>

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
            $number = 1;
            @endphp
            @foreach ($suratJalan->deliveryOrder as $index => $deliveryOrder)
            @foreach ($deliveryOrder->deliveryOrderItem as $index2 => $deliveryOrderItem)
            @php
                $price = 0;
                $discountPct = 0;
                $taxRate = 0;
                $taxAmount = 0;
                $lineSubtotal = 0;
                if ($deliveryOrderItem->saleOrderItem) {
                    $price = $deliveryOrderItem->saleOrderItem->unit_price;
                    $discountPct = $deliveryOrderItem->saleOrderItem->discount;
                    $taxRate = $deliveryOrderItem->saleOrderItem->tax;
                    $base = $deliveryOrderItem->quantity * $price * (1 - $discountPct/100);
                    $tr = $deliveryOrderItem->saleOrderItem->tipe_pajak ?? 'Eksklusif';
                    $taxResult = \App\Services\TaxService::compute($base, $taxRate, $tr);
                    $taxAmount = $taxResult['ppn'];
                    $lineSubtotal = $taxResult['total'];
                }
            @endphp
            <tr>
                <td>{{ $number }}</td>
                <td>({{ $deliveryOrderItem->product->sku }}) {{ $deliveryOrderItem->product->name }}</td>
                <td>{{ $deliveryOrderItem->quantity }}</td>
                <td>Rp {{ number_format($price,0,',','.') }}</td>
                <td>{{ number_format($discountPct,2) }}%</td>
                <td>{{ number_format($taxRate,2) }}%</td>
                <td>Rp {{ number_format($taxAmount,0,',','.') }}</td>
                <td>Rp {{ number_format($lineSubtotal,0,',','.') }}</td>
                <td>{{ $deliveryOrderItem->reason }}</td>
            </tr>
            @php
            $number++;
            @endphp
            @endforeach
            @endforeach
        </tbody>
    </table>

    <table style="border: none; margin-top: 50px; width: 100%;">
        <tr>
            <td style="border: none; text-align: center;">
                Penerima, <br><br><br><br>
                (____________________)
            </td>
            <td style="border: none; text-align: center;">
                Hormat kami, <br><br><br><br>
                <strong>PT.DUTA TUNGGAL</strong><br>
                (____________________)
            </td>
        </tr>
    </table>

</body>

</html>