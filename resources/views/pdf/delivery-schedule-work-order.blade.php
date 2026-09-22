<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Kerja Driver - {{ $schedule->schedule_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
            margin: 20px;
        }
        .title {
            text-align: center;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .subtitle {
            text-align: center;
            font-size: 12px;
            margin-bottom: 18px;
        }
        .meta-table, .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .meta-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        .item-table th, .item-table td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }
        .item-table th {
            background: #f3f4f6;
            text-align: left;
        }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            margin: 16px 0 8px;
        }
        .do-block {
            border: 1px solid #e5e7eb;
            padding: 8px;
            margin-bottom: 12px;
        }
        .signature {
            width: 100%;
            margin-top: 30px;
        }
        .signature td {
            width: 50%;
            vertical-align: top;
            text-align: center;
            padding-top: 20px;
        }
        .muted {
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="title">SURAT KERJA DRIVER</div>
    <div class="subtitle">Jadwal Pengiriman {{ $schedule->schedule_number }}</div>

    <table class="meta-table">
        <tr>
            <td width="20%"><strong>Tanggal Pengiriman</strong></td>
            <td width="30%">: {{ optional($schedule->scheduled_date)->format('d/m/Y H:i') }}</td>
            <td width="20%"><strong>Cabang</strong></td>
            <td width="30%">: {{ $schedule->cabang->nama ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Driver</strong></td>
            <td>: {{ $schedule->driver->name ?? '-' }}</td>
            <td><strong>Kendaraan</strong></td>
            <td>: {{ $schedule->vehicle->plate ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Metode</strong></td>
            <td>: {{ $schedule->delivery_method === 'kurir_internal' ? 'Kurir Internal' : 'Internal' }}</td>
            <td><strong>Status Jadwal</strong></td>
            <td>: {{ $schedule->status_label ?? ucfirst((string) $schedule->status) }}</td>
        </tr>
    </table>

    <div class="section-title">Daftar Delivery Order</div>

    @forelse($deliveryOrders as $do)
        @php
            $customer = $do->salesOrders->first()?->customer;
        @endphp

        <div class="do-block">
            <table class="meta-table" style="margin-bottom:8px;">
                <tr>
                    <td width="20%"><strong>Nomor DO</strong></td>
                    <td width="30%">: {{ $do->do_number }}</td>
                    <td width="20%"><strong>Tanggal DO</strong></td>
                    <td width="30%">: {{ optional($do->delivery_date)->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td><strong>Customer</strong></td>
                    <td>: {{ $customer->name ?? '-' }}</td>
                    <td><strong>Telepon</strong></td>
                    <td>: {{ $customer->phone ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Alamat</strong></td>
                    <td colspan="3">: {{ $customer->address ?? '-' }}</td>
                </tr>
                <tr>
                    <td><strong>Kota</strong></td>
                    <td colspan="3">: {{ $customer->city ?? '-' }}</td>
                </tr>
            </table>

            <table class="item-table">
                <thead>
                    <tr>
                        <th width="5%">No</th>
                        <th width="45%">Produk</th>
                        <th width="20%">SKU</th>
                        <th width="15%">Qty</th>
                        <th width="15%">Satuan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($do->deliveryOrderItem as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $item->product->name ?? '-' }}</td>
                            <td>{{ $item->product->sku ?? '-' }}</td>
                            <td>{{ number_format((float) $item->quantity, 2, ',', '.') }}</td>
                            <td>{{ $item->product->uom->abbreviation ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="muted">Tidak ada Delivery Order pada jadwal ini.</div>
    @endforelse

    <table class="signature">
        <tr>
            <td>
                Mengetahui,<br>
                Supervisor Logistik
                <br><br><br><br>
                (________________________)
            </td>
            <td>
                Driver
                <br><br><br><br>
                (________________________)
            </td>
        </tr>
    </table>
</body>
</html>
