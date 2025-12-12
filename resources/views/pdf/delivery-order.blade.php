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

    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo">
        <h2>PT. DUTA TUNGGAL</h2>
        <p>Alamat Perusahaan</p>
        <div class="title">DELIVERY ORDER</div>
    </div>

    <table style="border: none;">
        <tr>
            <td style="border: none;">No. Delivery Order</td>
            <td style="border: none;">: {{ $deliveryOrder->do_number }}</td>
        </tr>
        <tr>
            <td style="border: none;">Tanggal</td>
            <td style="border: none;">: {{ Carbon\Carbon::parse($deliveryOrder->delivery_date)->locale('id')->format('D,
                d M Y') }}</td>
        </tr>
        <tr>
            <td style="border: none;">Customer</td>
            <td style="border: none;">: @foreach ($deliveryOrder->salesOrders as $item)
                {{ $item->customer->name }},
                @endforeach</td>
        </tr>
        <tr>
            <td style="border: none;">Alamat Pengiriman</td>
            <td style="border: none;">: @foreach ($deliveryOrder->salesOrders as $item)
                {{ $item->shipped_to }}
                @endforeach</td>
        </tr>
        <tr>
            <td style="border: none;">Driver</td>
            <td style="border: none;">: {{ $deliveryOrder->driver->name }}</td>
        </tr>
        <tr>
            <td style="border: none;">Vehicle</td>
            <td style="border: none;">: {{ $deliveryOrder->vehicle->plate }} - {{ $deliveryOrder->vehicle->type }}</td>
        </tr>
        <tr>
            <td style="border: none;">Warehouse</td>
            <td style="border: none;">: {{ $deliveryOrder->warehouse->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td style="border: none;">Cabang</td>
            <td style="border: none;">: {{ $deliveryOrder->cabang->name ?? 'N/A' }}</td>
        </tr>
        @if($deliveryOrder->additional_cost > 0)
        <tr>
            <td style="border: none;">Biaya Tambahan</td>
            <td style="border: none;">: Rp {{ number_format($deliveryOrder->additional_cost, 0, ',', '.') }}</td>
        </tr>
        @if($deliveryOrder->additional_cost_description)
        <tr>
            <td style="border: none;">Deskripsi Biaya Tambahan</td>
            <td style="border: none;">: {{ $deliveryOrder->additional_cost_description }}</td>
        </tr>
        @endif
        @endif
        @if($deliveryOrder->notes)
        <tr>
            <td style="border: none;">Catatan</td>
            <td style="border: none;">: {{ $deliveryOrder->notes }}</td>
        </tr>
        @endif
    </table>

    <br>

    <h4>Detail Barang:</h4>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryOrder->deliveryOrderItem as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->reason }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $subtotal = 0;
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->saleOrderItem) {
                $price = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;
                $subtotal += $price * $item->quantity;
            }
        }
        $total = $subtotal + $deliveryOrder->additional_cost;
    @endphp

    <table style="border: none; margin-top: 20px; width: 50%; margin-left: auto;">
        <tr>
            <td style="border: none; text-align: right; font-weight: bold;">Subtotal:</td>
            <td style="border: none; text-align: right;">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
        </tr>
        @if($deliveryOrder->additional_cost > 0)
        <tr>
            <td style="border: none; text-align: right; font-weight: bold;">Biaya Tambahan:</td>
            <td style="border: none; text-align: right;">Rp {{ number_format($deliveryOrder->additional_cost, 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr>
            <td style="border: none; text-align: right; font-weight: bold; border-top: 1px solid #333;">Total:</td>
            <td style="border: none; text-align: right; border-top: 1px solid #333; font-weight: bold;">Rp {{ number_format($total, 0, ',', '.') }}</td>
        </tr>
    </table>

    <table style="border: none; margin-top: 50px; width: 100%;">
        <tr>
            <td style="border: none; text-align: center;">
                Penerima, <br><br><br><br>
                (____________________)
            </td>
            <td style="border: none; text-align: center;">
                Hormat kami, <br><br><br><br>
                <strong>PT. DUTA TUNGGAL</strong><br>
                (____________________)
            </td>
        </tr>
    </table>

</body>

</html>