<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Pengiriman Driver</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 5px 0;
        }
        .subtitle {
            font-size: 12px;
            margin: 3px 0;
        }
        .info-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .info-table td {
            padding: 3px 8px;
            vertical-align: top;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.items th {
            background-color: #e0e0e0;
            border: 1px solid #555;
            padding: 6px 5px;
            text-align: center;
            font-weight: bold;
        }
        table.items td {
            border: 1px solid #555;
            padding: 5px;
            vertical-align: top;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .bold        { font-weight: bold; }
        .total-row td {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
        }
        .sign-table {
            width: 100%;
        }
        .sign-table td {
            text-align: center;
            padding-top: 50px;
            width: 33%;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">REKAPITULASI PENGIRIMAN DRIVER</div>
        <div class="subtitle">PT Duta Tunggal</div>
        <div class="subtitle">Tanggal Cetak: {{ now()->format('d M Y H:i') }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td style="width:120px"><strong>Driver</strong></td>
            <td>: {{ $driver->name ?? '-' }}</td>
            <td style="width:120px"><strong>Tanggal Pengiriman</strong></td>
            <td>: {{ $date }}</td>
        </tr>
        <tr>
            <td><strong>Total DO</strong></td>
            <td>: {{ $deliveryOrders->count() }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:5%">No</th>
                <th style="width:15%">No DO</th>
                <th style="width:20%">Customer</th>
                <th style="width:25%">Produk</th>
                <th style="width:8%" class="text-center">Qty</th>
                <th style="width:15%">Status</th>
                <th style="width:12%">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @forelse ($deliveryOrders as $do)
                @php
                    $customers = $do->salesOrders->map(fn($so) => $so->customer?->perusahaan ?? $so->customer?->name ?? '-')->unique()->implode(', ');
                    $items = $do->deliveryOrderItem;
                    $rowspan = max(1, $items->count());
                @endphp
                @if ($items->isEmpty())
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td class="bold">{{ $do->do_number }}</td>
                        <td>{{ $customers }}</td>
                        <td colspan="2" class="text-center">-</td>
                        <td class="text-center">
                            {{ match($do->status) {
                                'draft'           => 'Draft',
                                'request_approve' => 'Menunggu Approval',
                                'approved'        => 'Disetujui',
                                'sent'            => 'Terkirim',
                                'completed'       => 'Selesai',
                                'delivery_failed' => 'Gagal Kirim',
                                default           => $do->status
                            } }}
                        </td>
                        <td>{{ $do->notes }}</td>
                    </tr>
                @else
                    @foreach ($items as $idx => $item)
                        <tr>
                            @if ($idx === 0)
                                <td class="text-center" rowspan="{{ $rowspan }}">{{ $no++ }}</td>
                                <td class="bold" rowspan="{{ $rowspan }}">{{ $do->do_number }}</td>
                                <td rowspan="{{ $rowspan }}">{{ $customers }}</td>
                            @endif
                            <td>{{ $item->product?->name ?? '-' }}<br><small>SKU: {{ $item->product?->sku ?? '-' }}</small></td>
                            <td class="text-center">{{ $item->quantity }} {{ $item->product?->unit ?? '' }}</td>
                            @if ($idx === 0)
                                <td class="text-center" rowspan="{{ $rowspan }}">
                                    {{ match($do->status) {
                                        'draft'           => 'Draft',
                                        'request_approve' => 'Menunggu Approval',
                                        'approved'        => 'Disetujui',
                                        'sent'            => 'Terkirim',
                                        'completed'       => 'Selesai',
                                        'delivery_failed' => 'Gagal Kirim',
                                        default           => $do->status
                                    } }}
                                </td>
                                <td rowspan="{{ $rowspan }}">{{ $do->notes }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr>
                    <td colspan="7" class="text-center">Tidak ada data pengiriman untuk driver dan tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <table class="sign-table">
            <tr>
                <td>Disiapkan oleh,<br><br><br><br>( __________________ )<br>Gudang</td>
                <td>Driver,<br><br><br><br>( __________________ )<br>{{ $driver->name ?? '' }}</td>
                <td>Diketahui oleh,<br><br><br><br>( __________________ )<br>Supervisor</td>
            </tr>
        </table>
    </div>
</body>
</html>
