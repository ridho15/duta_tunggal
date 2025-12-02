{{-- resources/views/pdf/kwitansi-sales-order.blade.php --}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Kwitansi Penjualan - {{ $saleOrder->so_number }}</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .kop {
            text-align: center;
            margin-bottom: 20px;
        }

        .garis {
            border-top: 2px solid #000;
            margin-top: 5px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            margin-bottom: 20px;
        }

        .table-items {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }

        .table-items th,
        .table-items td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }

        .table-items th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .ttd {
            margin-top: 50px;
            text-align: right;
        }

        .total-section {
            margin-top: 20px;
            text-align: right;
        }

        .total-amount {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="kop">
        <strong>PT Duta Tunggal</strong><br>
        Jl. Contoh Alamat No. 123, Padang<br>
        Telp. 08xx-xxxx-xxxx
        <div class="garis"></div>
        <h3>KWITANSI PENJUALAN</h3>
    </div>

    <table>
        <tr>
            <td width="150">No. Sales Order</td>
            <td>: {{ $saleOrder->so_number }}</td>
        </tr>
        <tr>
            <td>Tanggal Order</td>
            <td>: {{ \Carbon\Carbon::parse($saleOrder->order_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>Customer</td>
            <td>: {{ $saleOrder->customer->name }} ({{ $saleOrder->customer->code }})</td>
        </tr>
        <tr>
            <td>Alamat Pengiriman</td>
            <td>: {{ $saleOrder->shipped_to }}</td>
        </tr>
        @if($saleOrder->delivery_date)
        <tr>
            <td>Tanggal Pengiriman</td>
            <td>: {{ \Carbon\Carbon::parse($saleOrder->delivery_date)->format('d/m/Y') }}</td>
        </tr>
        @endif
    </table>

    <table class="table-items">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="40%">Nama Barang</th>
                <th width="15%">Qty</th>
                <th width="20%">Harga Satuan</th>
                <th width="20%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($saleOrder->saleOrderItem as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->product->name }} ({{ $item->product->sku }})</td>
                <td>{{ number_format($item->quantity, 0, ',', '.') }}</td>
                <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td>Rp {{ number_format(($item->unit_price * $item->quantity), 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-section">
        <strong>Total Pembelian: Rp {{ number_format($saleOrder->total_amount, 0, ',', '.') }}</strong>
    </div>

    <div class="ttd">
        Padang, {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}<br>
        Hormat Kami,<br><br><br><br>
        <strong>{{ auth()->user()->name ?? 'Petugas Penjualan' }}</strong>
    </div>

    <div style="margin-top: 30px; font-size: 10px; text-align: center; color: #666;">
        <em>Terima kasih atas kepercayaan Anda berbelanja bersama kami</em>
    </div>
</body>

</html>