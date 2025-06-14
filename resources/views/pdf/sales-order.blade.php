<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>KUITANSI SALE ORDER</title>
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

        .total {
            font-weight: bold;
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
        <h2>PT.DUTA TUNGGAL</h2>
        <p>Alamat Perusahaan</p>
        <div class="title">KUITANSI</div>
    </div>

    <table style="border: none;">
        <tr>
            <td style="border: none;">No. Kuitansi</td>
            <td style="border: none;">: {{ $saleOrder->so_number }}</td>
        </tr>
        <tr>
            <td style="border: none;">Tanggal</td>
            <td style="border: none;">: {{ Carbon\Carbon::parse($saleOrder->order_date)->locale('id')->format('D, d M
                Y') }}</td>
        </tr>
        <tr>
            <td style="border: none;">Customer</td>
            <td style="border: none;">: {{ $saleOrder->customer->name }}</td>
        </tr>
    </table>

    <br>

    <h4>Detail Sale Order:</h4>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Item</th>
                <th>Qty</th>
                <th>Harga Satuan</th>
                <th>Discount</th>
                <th>Tax</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach ($saleOrder->saleOrderItem as $index => $item)
            @php
            $subtotal = $item->quantity * $item->unit_price - $item->discount + $item->tax;
            $total += $subtotal;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->discount, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->tax, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="6" class="total">Total</td>
                <td class="total">Rp {{ number_format($total, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <br>
    <strong>Terbilang:</strong> <em>{{ App\Http\Controllers\HelperController::terbilang($saleOrder->total_amount)
        }}</em>

    <table style="border: none; margin-top: 50px;">
        <tr>
            <td style="border: none;" width="60%"></td>
            <td style="border: none;" class="sign">
                Jakarta, {{ Carbon\Carbon::parse($saleOrder->approve_at)->locale('id')->format('D, d M Y') }}
                <br>
                Hormat kami,
                <img src="{{ public_path('storage' . $saleOrder->approveBy->signature) }}" alt=""
                    style="height: 75px; width: auto">
                <strong>PT.DUTA TUNGGAL</strong><br>
                (____________________)
            </td>
        </tr>
    </table>

</body>

</html>