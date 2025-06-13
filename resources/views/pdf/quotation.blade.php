<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quotation - PT Duta Tunggal</title>
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
            width: 100px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .signature {
            margin-top: 50px;
            text-align: right;
        }

        h1 {
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ public_path('images/logo.png') }}" class="logo" alt="Logo">
        <h2>PT DUTA TUNGGAL</h2>
        <p>Alamat Perusahaan | Telp: 021-123456 | Email: info@dutatunggal.co.id</p>
    </div>

    <h3>QUOTATION</h3>

    <p><strong>Date:</strong> {{ Carbon\Carbon::parse($quotation->date)->locale('id')->format('D, d M Y') }}</p>
    <p><strong>Valid Until:</strong> {{ $quotation->valid_until ?
        Carbon\Carbon::parse($quotation->valid_until)->locale('id')->format('D, d M Y') : '-' }}</p>
    <p><strong>To:</strong> {{ $quotation->customer->name }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Discount</th>
                <th>Tax</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->quotationItem as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td>Rp.{{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                <td>Rp.{{ number_format($item['discount'], 0, ',', '.') }}</td>
                <td>Rp.{{ number_format($item['tax'], 0, ',', '.') }}</td>
                <td>Rp.{{ number_format(($item['quantity'] * $item['unit_price']) - $item['discount'] + $item['tax'], 0,
                    ',', '.') }}</td>
            </tr>
            @endforeach
            <tr>
                <td colspan="6" style="text-align: right;"><strong>Total</strong></td>
                <td><strong>Rp.{{ number_format($quotation->total_amount, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p><strong>Notes:</strong></p>
    <p>{{ $quotation->notes }}</p>

    <div class="signature">
        <p>Hormat Kami,</p>
        <img src="{{ public_path('storage' . $quotation->approveBy->signature) }}" alt="" style="height: 75px; width: 130px; object-fit: contain">
        <p><strong>PT DUTA TUNGGAL</strong></p>
    </div>
</body>

</html>