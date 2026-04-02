<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Surat Jalan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 16px; }
        .header h2 { margin: 4px 0; font-size: 16px; text-transform: uppercase; }
        .header .title { font-size: 14px; font-weight: bold; margin-top: 8px; letter-spacing: 1px; }
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .meta-table td { border: none; padding: 3px 6px; vertical-align: top; }
        .meta-table td:first-child { width: 160px; font-weight: bold; }
        table.items-table { width: 100%; border-collapse: collapse; font-size: 10px; }
        table.items-table th { background-color: #1E40AF; color: #fff; padding: 5px 6px; text-align: left; }
        table.items-table td { border: 1px solid #ccc; padding: 4px 6px; vertical-align: top; }
        table.items-table tr:nth-child(even) td { background-color: #f0f4ff; }
        .no-data { text-align: center; padding: 20px; color: #888; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h2>PT. DUTA TUNGGAL</h2>
        <div class="title">REKAP SURAT JALAN</div>
    </div>

    @php
        $statusLabel = match ((string) $statusPengiriman) {
            'all' => 'Semua',
            '1' => 'Terbit',
            default => 'Tidak Terbit',
        };
    @endphp

    <table class="meta-table">
        <tr>
            <td>Periode</td>
            <td>: <strong>
                @if($tanggalMulai && $tanggalSelesai)
                    {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                @elseif($tanggalMulai)
                    Mulai {{ \Carbon\Carbon::parse($tanggalMulai)->format('d/m/Y') }}
                @elseif($tanggalSelesai)
                    Sampai {{ \Carbon\Carbon::parse($tanggalSelesai)->format('d/m/Y') }}
                @else
                    Semua Tanggal
                @endif
            </strong></td>
        </tr>
        <tr>
            <td>Status</td>
            <td>: <strong>{{ $statusLabel }}</strong></td>
        </tr>
        <tr>
            <td>Total Surat Jalan</td>
            <td>: <strong>{{ $suratJalans->count() }}</strong></td>
        </tr>
        <tr>
            <td>Dicetak</td>
            <td>: {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if($suratJalans->isEmpty())
        <div class="no-data">Tidak ada surat jalan untuk filter yang dipilih.</div>
    @else
        @php $no = 1; @endphp
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:30px">No</th>
                    <th style="width:110px">No. Surat Jalan</th>
                    <th style="width:95px">Tgl Terbit</th>
                    <th style="width:100px">Cabang</th>
                    <th>Customer</th>
                    <th>Delivery Order</th>
                    <th style="width:70px">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($suratJalans as $suratJalan)
                    @php
                        $deliveryOrders = $suratJalan->deliveryOrder;
                        $customers = collect();

                        foreach ($deliveryOrders as $deliveryOrder) {
                            foreach ($deliveryOrder->salesOrders as $salesOrder) {
                                if ($salesOrder->customer) {
                                    $customers->push($salesOrder->customer->name);
                                }
                            }
                        }
                    @endphp
                    <tr>
                        <td>{{ $no++ }}</td>
                        <td>{{ $suratJalan->sj_number }}</td>
                        <td>{{ $suratJalan->issued_at ? \Carbon\Carbon::parse($suratJalan->issued_at)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $suratJalan->cabang->nama ?? $suratJalan->cabang->name ?? '-' }}</td>
                        <td>{{ $customers->unique()->implode(', ') ?: '-' }}</td>
                        <td>{{ $deliveryOrders->pluck('do_number')->implode(', ') ?: '-' }}</td>
                        <td>{{ (int) $suratJalan->status === 1 ? 'Terbit' : 'Draft' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>