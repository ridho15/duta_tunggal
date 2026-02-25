<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Order Request - PT DUTA TUNGGAL</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            width: 120px;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('logo_duta_tunggal.png') }}" alt="Logo PT DUTA TUNGGAL" class="logo"><br>
        <h2>ORDER REQUEST</h2>
        <p>PT DUTA TUNGGAL</p>
    </div>

    <p><strong>Nomor Order Request:</strong> {{ $orderRequest->request_number }}</p>
    <p><strong>Tanggal:</strong> {{ $orderRequest->request_date }}</p>
    <p><strong>Dibuat oleh:</strong> {{ $orderRequest->createdBy->name }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Product</th>
                <th>Satuan</th>
                <th>Qty</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orderRequest->orderRequestItem as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td>{{ $item->product->uom->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>{{ $item->note }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 40px;">Hormat Kami,</p>
    <p style="margin-top: 60px;">____________________________</p>
</body>

</html>