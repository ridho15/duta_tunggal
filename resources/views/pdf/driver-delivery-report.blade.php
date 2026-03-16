<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pengiriman - {{ $driver }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 20px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header h2 {
            margin: 4px 0;
            font-size: 16px;
            text-transform: uppercase;
        }

        .header .title {
            font-size: 14px;
            font-weight: bold;
            margin-top: 8px;
            letter-spacing: 1px;
        }

        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        .meta-table td {
            border: none;
            padding: 3px 6px;
        }

        .meta-table td:first-child {
            width: 160px;
            font-weight: bold;
        }

        .sj-section {
            margin-bottom: 20px;
            border: 1px solid #aaa;
            border-radius: 4px;
            padding: 10px;
        }

        .sj-header {
            font-weight: bold;
            font-size: 12px;
            background-color: #f0f0f0;
            padding: 6px 8px;
            margin: -10px -10px 10px -10px;
            border-bottom: 1px solid #aaa;
        }

        .sj-meta {
            margin-bottom: 8px;
        }

        .sj-meta span {
            margin-right: 20px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        table.items-table th {
            background-color: #333;
            color: #fff;
            padding: 5px 6px;
            text-align: left;
        }

        table.items-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            vertical-align: top;
        }

        table.items-table tr:nth-child(even) td {
            background-color: #f9f9f9;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
        }

        .signature-table td {
            border: none;
            text-align: center;
            width: 33%;
            padding: 8px;
        }

        .signature-box {
            border: 1px solid #aaa;
            padding: 8px;
            min-height: 60px;
            margin-top: 8px;
        }

        .total-row td {
            font-weight: bold;
            background-color: #e9ecef;
            border: 1px solid #999;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #888;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-terbit { background-color: #d1fae5; color: #065f46; }
        .badge-draft  { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>

<body>

    <div class="header">
        <h2>PT. DUTA TUNGGAL</h2>
        <div class="title">REKAP PENGIRIMAN HARIAN</div>
    </div>

    <table class="meta-table">
        <tr>
            <td>Driver / Pengirim</td>
            <td>: <strong>{{ $driver }}</strong></td>
        </tr>
        <tr>
            <td>Tanggal Pengiriman</td>
            <td>: <strong>{{ \Carbon\Carbon::parse($date)->locale('id')->isoFormat('dddd, D MMMM Y') }}</strong></td>
        </tr>
        <tr>
            <td>Jumlah Surat Jalan</td>
            <td>: <strong>{{ $suratJalans->count() }} SJ</strong></td>
        </tr>
        <tr>
            <td>Dicetak</td>
            <td>: {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if ($suratJalans->isEmpty())
        <div class="no-data">
            Tidak ada Surat Jalan untuk driver <strong>{{ $driver }}</strong> pada tanggal {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}.
        </div>
    @else
        @php $sjNo = 1; @endphp
        @foreach ($suratJalans as $sj)
            @php
                $customers = collect();
                $addresses = collect();
                foreach ($sj->deliveryOrder as $do) {
                    foreach ($do->salesOrders as $so) {
                        if ($so->customer) {
                            $customers->push($so->customer->name);
                        }
                        if ($so->shipped_to) {
                            $addresses->push($so->shipped_to);
                        }
                    }
                }
                $customerList = $customers->unique()->implode(', ');
                $addressList  = $addresses->unique()->implode(', ');
            @endphp

            <div class="sj-section">
                <div class="sj-header">
                    #{{ $sjNo++ }} &nbsp;|&nbsp; {{ $sj->sj_number }}
                    &nbsp;
                    <span class="badge {{ $sj->status == 1 ? 'badge-terbit' : 'badge-draft' }}">
                        {{ $sj->status == 1 ? 'Terbit' : 'Draft' }}
                    </span>
                    &nbsp;|&nbsp; Metode: {{ $sj->shipping_method ?? '-' }}
                </div>

                <div class="sj-meta">
                    <span><strong>Customer:</strong> {{ $customerList ?: '-' }}</span>
                    <span><strong>Alamat:</strong> {{ $addressList ?: '-' }}</span>
                </div>

                @php $itemNo = 1; @endphp
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:30px">No</th>
                            <th style="width:80px">No. DO</th>
                            <th>Produk (SKU)</th>
                            <th style="width:50px; text-align:right">Qty</th>
                            <th style="width:60px">Satuan</th>
                            <th style="width:120px">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sj->deliveryOrder as $do)
                            @forelse ($do->deliveryOrderItem as $item)
                                <tr>
                                    <td>{{ $itemNo++ }}</td>
                                    <td>{{ $do->do_number }}</td>
                                    <td>{{ $item->product->name ?? '-' }}
                                        @if ($item->product && $item->product->sku)
                                            <br><span style="color:#666">({{ $item->product->sku }})</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                                    <td>{{ $item->product->unit ?? '-' }}</td>
                                    <td>{{ $item->reason ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#999">Tidak ada item</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; color:#999">Tidak ada Delivery Order</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Signature box per SJ --}}
                <table style="width:100%; border-collapse:collapse; margin-top:16px; font-size:10px;">
                    <tr>
                        <td style="border:none; text-align:center; width:33%">
                            Penerima,<br><br><br><br>
                            (____________________)
                        </td>
                        <td style="border:none; text-align:center; width:33%">
                            Pengirim,<br><br><br><br>
                            <strong>{{ $sj->sender_name ?? $driver }}</strong>
                        </td>
                        <td style="border:none; text-align:center; width:33%">
                            Mengetahui,<br><br><br><br>
                            (____________________)
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif

</body>

</html>
