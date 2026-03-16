<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            float: left;
            width: 50%;
        }

        .invoice-title {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-title h1 {
            color: #333;
            font-size: 32px;
            margin: 0;
        }

        .invoice-details {
            clear: both;
            margin: 30px 0;
        }

        .customer-info {
            float: left;
            width: 50%;
        }

        .invoice-info {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            clear: both;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            float: right;
            width: 40%;
            margin-top: 20px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .totals-table .total-row {
            font-weight: bold;
            border-top: 2px solid #333;
            background-color: #f5f5f5;
        }

        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .rupiah {
            font-family: monospace;
        }
    </style>
</head>

<body>
    <div class="header clearfix">
        <div class="company-info">
            <h2>PT.DUTA TUNGGAL</h2>
            <p>Alamat Perusahaan<br>
                Kota Perusahaan<br>
                Telp: Phone Perusahaan<br>
                Email: email@perusahaan.com</p>
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
        </div>
    </div>

    <div class="invoice-details clearfix">
        <div class="customer-info">
            <h3>Tagihan Kepada:</h3>
            <p><strong>{{ $invoice->fromModel->customer->name }}</strong><br>
                {{ $invoice->fromModel->customer->perusahaan }}
                {{ $invoice->fromModel->customer->address }}<br>
                Telp: {{ $invoice->fromModel->customer->phone }}<br>
                Email: {{ $invoice->fromModel->customer->email }}</p>
        </div>
        <div class="invoice-info">
            <table>
                <tr>
                    <td><strong>No. Invoice:</strong></td>
                    <td>{{ $invoice->invoice_number }}</td>
                </tr>
                <tr>
                    <td><strong>Tanggal Invoice:</strong></td>
                    <td>{{ Carbon\Carbon::parse($invoice->invoice_date)->locale('id')->format('D, d M Y') }}</td>
                </tr>
                <tr>
                    <td><strong>Jatuh Tempo:</strong></td>
                    <td>{{ Carbon\Carbon::parse($invoice->due_date)->locale('id')->format('D, d M Y') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th class="text-left">SKU</th>
                <th class="text-left">Produk</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Discount (%)</th>
                <th class="text-right">Tax (%)</th>
                <th class="text-right">Tax Amount</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->invoiceItem as $item)
            @php
                // ensure we have discount/tax_rate as percents
                $taxRate = $item->tax_rate ?? 0;
                $discountPct = $item->discount ?? 0;
            @endphp
            <tr>
                <td class="text-left">{{ $item->product->sku }}</td>
                <td class="text-left">{{ $item->product->name }}</td>
                <td class="text-center">{{ $item->quantity }}</td>
                <td class="text-right rupiah">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($discountPct, 2) }}%</td>
                <td class="text-right">{{ number_format($taxRate, 2) }}%</td>
                <td class="text-right rupiah">Rp {{ number_format($item->tax_amount, 0, ',', '.') }}</td>
                <td class="text-right rupiah">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right rupiah">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr>
                {{-- FIX #9: invoice->tax stores the rate (e.g. 11 for 11%). Derive the monetary PPN amount.
                     Use sum of invoice items' tax_amount if available (most accurate), otherwise fall back
                     to the rate-based computation. --}}
                @php
                    $ppnMonetary = $invoice->invoiceItem()->sum('tax_amount');
                    if ($ppnMonetary <= 0 && $invoice->tax > 0) {
                        $ppnMonetary = $invoice->subtotal * ($invoice->tax / 100);
                    }
                @endphp
                <td>PPN ({{ $invoice->tax }}%) :</td>
                <td class="text-right rupiah">Rp {{ number_format($ppnMonetary, 0, ',', '.') }}</td>
            </tr>
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="text-right rupiah"><strong>Rp {{ number_format($invoice->total, 0, ',', '.')
                        }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p><strong>Syarat dan Ketentuan:</strong></p>
        <p>1. Pembayaran paling lambat pada tanggal jatuh tempo<br>
            2. Pembayaran dapat dilakukan melalui transfer bank<br>
            3. Barang yang sudah dibeli tidak dapat dikembalikan<br>
            4. Untuk pertanyaan hubungi -</p>

        <p style="margin-top: 30px;">
            <strong>Terima kasih atas kepercayaan Anda!</strong>
        </p>
    </div>
</body>

</html>