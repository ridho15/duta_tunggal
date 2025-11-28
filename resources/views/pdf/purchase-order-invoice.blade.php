<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Invoice Pembelian</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        .header,
        .footer {
            text-align: center;
        }

        .invoice-box {
            border: 1px solid #ccc;
            border-radius: 10px;
            padding: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table th,
        .table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <div class="header">
            <img src="{{ public_path('images/logo.png') }}" alt="Logo" height="50">
            <h2>PT.DUTA TUNGGAL</h2>
            <p>Invoice #: {{ $invoice->invoice_number }}<br>
                Tanggal: {{ Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->format('D, d M Y') }}</p>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Nama Barang</th>
                    <th>Qty</th>
                    <th>Harga Satuan</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->invoiceItem as $item)
                <tr>
                    <td>{{ $item->product->sku }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td class="right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <br>
        <table width="100%">
            <tr>
                <td class="right" style="width: 80%;">Subtotal:</td>
                <td class="right">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="right">Tax:</td>
                <td class="right">Rp {{ number_format($invoice->tax, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="right">Biaya Lain-lain:</td>
                <td class="right">Rp {{ number_format($invoice->other_fee_total, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td class="right"><strong>Total Pembayaran:</strong></td>
                <td class="right"><strong>Rp {{ number_format($invoice->total, 0, ',', '.') }}</strong></td>
            </tr>
        </table>

        <br><br>
        <div class="footer">
            <p>Terima kasih telah bertransaksi dengan PT.DUTA TUNGGAL.</p>
        </div>
    </div>
</body>

</html>