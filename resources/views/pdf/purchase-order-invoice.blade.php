<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice Purchase Order</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            height: 80px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background: #f2f2f2;
        }
    </style>
</head>

<body>
    <div class="header">
        <img class="logo" src="{{ public_path('images/logo.png') }}" alt="Logo">
        <h2>PT DUTA TUNGGAL</h2>
        <h4>Invoice Purchase Order</h4>
    </div>

    <p><strong>Nomor PO:</strong> {{ $po->po_number }}</p>
    <p><strong>Tanggal PO:</strong> {{ Carbon\Carbon::parse($purchaseOrder->order_date) }}</p>
    <p><strong>Supplier:</strong> {{ $purchaseOrder->supplier->name }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Produk</th>
                <th>Qty</th>
                <th>Harga Satuan</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($po->items as $item)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->quantity * $item->price, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h4 style="text-align: right; margin-top: 20px;">
        Total: Rp {{ number_format($po->items->sum(fn($i) => $i->quantity * $i->price), 0, ',', '.') }}
    </h4>
</body>

</html>