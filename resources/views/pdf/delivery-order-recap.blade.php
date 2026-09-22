<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekap Delivery Order</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; }

        .header { text-align: center; margin-bottom: 16px; border-bottom: 2px solid #1E40AF; padding-bottom: 10px; }
        .header h1 { font-size: 16px; font-weight: bold; color: #1E40AF; }
        .header p { font-size: 10px; color: #555; margin-top: 2px; }

        .meta-info { margin-bottom: 12px; font-size: 10px; }
        .meta-info table { border-collapse: collapse; }
        .meta-info td { padding: 2px 6px; }
        .meta-info td:first-child { font-weight: bold; width: 130px; }

        .driver-section { margin-bottom: 20px; }
        .driver-title {
            background-color: #1E40AF;
            color: #ffffff;
            padding: 5px 8px;
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
        }

        table.do-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.do-table th {
            background-color: #DBEAFE;
            border: 1px solid #93C5FD;
            padding: 4px 6px;
            font-weight: bold;
            text-align: center;
            font-size: 9px;
        }
        table.do-table td {
            border: 1px solid #BFDBFE;
            padding: 4px 6px;
            font-size: 9px;
            vertical-align: top;
        }
        table.do-table tr:nth-child(even) td {
            background-color: #EFF6FF;
        }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .driver-summary {
            font-size: 9px;
            text-align: right;
            padding: 3px 6px;
            background-color: #DBEAFE;
            border: 1px solid #93C5FD;
            margin-bottom: 4px;
        }

        .grand-summary {
            margin-top: 14px;
            border-top: 2px solid #1E40AF;
            padding-top: 8px;
        }
        .grand-summary table { width: 60%; margin-left: auto; border-collapse: collapse; }
        .grand-summary td { padding: 3px 8px; font-size: 10px; border: 1px solid #93C5FD; }
        .grand-summary td:first-child { font-weight: bold; background-color: #DBEAFE; }

        .footer { margin-top: 20px; font-size: 9px; color: #777; text-align: center; border-top: 1px solid #ccc; padding-top: 6px; }

        .badge-delivered  { color: #065F46; font-weight: bold; }
        .badge-pending    { color: #92400e; font-weight: bold; }
        .badge-cancelled  { color: #991b1b; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header">
        <h1>REKAP DELIVERY ORDER</h1>
        <p>PT. Duta Tunggal &mdash; Laporan Pengiriman per Driver</p>
    </div>

    <div class="meta-info">
        <table>
            <tr>
                <td>Periode</td>
                <td>:
                    @if($dateFrom || $dateTo)
                        {{ $dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '...' }}
                        &ndash;
                        {{ $dateTo   ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y')   : '...' }}
                    @else
                        Semua
                    @endif
                </td>
            </tr>
            <tr>
                <td>Driver</td>
                <td>: {{ $drivers->pluck('name')->implode(', ') }}</td>
            </tr>
            <tr>
                <td>Dicetak</td>
                <td>: {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
        </table>
    </div>

    @php $grandTotal = 0; @endphp

    @foreach($driverGroups as $driverName => $orders)
        @php
            $driverTotal = 0;
            foreach ($orders as $do) $driverTotal += $do->deliveryOrderItem->count();
            $grandTotal += $driverTotal;
        @endphp

        <div class="driver-section">
            <div class="driver-title">{{ $driverName }}</div>

            <table class="do-table">
                <thead>
                    <tr>
                        <th style="width:4%">No</th>
                        <th style="width:16%">No. DO</th>
                        <th style="width:12%">Tgl Kirim</th>
                        <th style="width:28%">Customer</th>
                        <th style="width:26%">Produk</th>
                        <th style="width:7%">Qty</th>
                        <th style="width:7%">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @foreach($orders as $do)
                        @php
                            $customers = $do->salesOrders->map(fn($so) => $so->customer?->perusahaan ?? $so->customer?->name ?? '-')->filter()->unique()->implode(', ') ?: '-';
                            $items     = $do->deliveryOrderItem;
                            $statusClass = match(strtolower($do->status)) {
                                'delivered'  => 'badge-delivered',
                                'cancelled'  => 'badge-cancelled',
                                default      => 'badge-pending',
                            };
                        @endphp
                        @if($items->isEmpty())
                            <tr>
                                <td class="text-center">{{ $no++ }}</td>
                                <td>{{ $do->do_number }}</td>
                                <td class="text-center">{{ $do->delivery_date ? \Carbon\Carbon::parse($do->delivery_date)->format('d/m/Y') : '-' }}</td>
                                <td>{{ $customers }}</td>
                                <td>-</td>
                                <td class="text-center">-</td>
                                <td class="text-center {{ $statusClass }}">{{ strtoupper($do->status) }}</td>
                            </tr>
                        @else
                            @foreach($items as $i => $item)
                                <tr>
                                    @if($i === 0)
                                        <td class="text-center" rowspan="{{ $items->count() }}">{{ $no++ }}</td>
                                        <td rowspan="{{ $items->count() }}">{{ $do->do_number }}</td>
                                        <td class="text-center" rowspan="{{ $items->count() }}">{{ $do->delivery_date ? \Carbon\Carbon::parse($do->delivery_date)->format('d/m/Y') : '-' }}</td>
                                        <td rowspan="{{ $items->count() }}">{{ $customers }}</td>
                                    @endif
                                    <td>{{ $item->product?->name ?? '-' }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    @if($i === 0)
                                        <td class="text-center {{ $statusClass }}" rowspan="{{ $items->count() }}">{{ strtoupper($do->status) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        @endif
                    @endforeach
                </tbody>
            </table>

            <div class="driver-summary">
                Total DO: {{ count($orders) }} &nbsp;|&nbsp; Total Item: {{ $driverTotal }}
            </div>
        </div>
    @endforeach

    <div class="grand-summary">
        <table>
            <tr>
                <td>Total DO Keseluruhan</td>
                <td>{{ collect($driverGroups)->sum(fn($orders) => count($orders)) }}</td>
            </tr>
            <tr>
                <td>Total Item Keseluruhan</td>
                <td>{{ $grandTotal }}</td>
            </tr>
            <tr>
                <td>Jumlah Driver</td>
                <td>{{ count($driverGroups) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Dokumen ini dicetak secara otomatis oleh sistem ERP Duta Tunggal &mdash; {{ now()->format('d/m/Y H:i:s') }}
    </div>

</body>
</html>
