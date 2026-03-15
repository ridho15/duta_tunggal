<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Pengiriman Fleksibel</title>
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

        .group-section {
            margin-bottom: 25px;
            border: 1px solid #aaa;
            border-radius: 4px;
            padding: 10px;
        }

        .group-header {
            font-weight: bold;
            font-size: 13px;
            background-color: #e3f2fd;
            color: #1565c0;
            padding: 8px 10px;
            margin: -10px -10px 15px -10px;
            border-bottom: 1px solid #aaa;
        }

        .sj-section {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            background-color: #fafafa;
        }

        .sj-header {
            font-weight: bold;
            font-size: 12px;
            background-color: #f5f5f5;
            padding: 5px 8px;
            margin: -8px -8px 8px -8px;
            border-bottom: 1px solid #ddd;
        }

        .sj-meta {
            margin-bottom: 6px;
        }

        .sj-meta span {
            margin-right: 20px;
            font-size: 10px;
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

        .summary-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }

        .summary-box h4 {
            margin: 0 0 8px 0;
            font-size: 12px;
            color: #495057;
        }

        .summary-stats {
            display: flex;
            gap: 15px;
        }

        .stat-item {
            font-size: 11px;
        }

        .stat-item strong {
            color: #007bff;
        }
    </style>
</head>

<body>

    <div class="header">
        <h2>PT. DUTA TUNGGAL</h2>
        <div class="title">REKAP PENGIRIMAN FLEKSIBEL</div>
    </div>

    <table class="meta-table">
        <tr>
            <td>Driver / Pengirim</td>
            <td>: <strong>{{ empty($drivers) ? 'Semua' : implode(', ', $drivers) }}</strong></td>
        </tr>
        <tr>
            <td>Periode</td>
            <td>: <strong>{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</strong></td>
        </tr>
        <tr>
            <td>Kelompokkan Berdasarkan</td>
            <td>: <strong>{{ $groupBy === 'driver' ? 'Driver / Pengirim' : ($groupBy === 'date' ? 'Tanggal' : 'Tidak Kelompokkan') }}</strong></td>
        </tr>
        <tr>
            <td>Dicetak</td>
            <td>: {{ now()->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    @if (collect($groupedData)->flatten()->isEmpty())
        <div class="no-data">
            Tidak ada Surat Jalan untuk kriteria yang dipilih.
        </div>
    @else
        @foreach ($groupedData as $groupKey => $suratJalans)
            @if ($groupBy !== 'none')
                <div class="group-section">
                    <div class="group-header">
                        {{ $groupBy === 'driver' ? 'Driver: ' . $groupKey : 'Tanggal: ' . \Carbon\Carbon::parse($groupKey)->format('d/m/Y') }}
                        ({{ $suratJalans->count() }} SJ)
                    </div>
                </div>
            @endif

            @php
                $totalSJ = $suratJalans->count();
                $totalDO = $suratJalans->sum(function ($sj) { return $sj->deliveryOrder->count(); });
                $totalItems = $suratJalans->sum(function ($sj) {
                    return $sj->deliveryOrder->sum(function ($do) {
                        return $do->deliveryOrderItem->count();
                    });
                });
            @endphp

            @if ($groupBy !== 'none')
                <div class="summary-box">
                    <h4>Ringkasan</h4>
                    <div class="summary-stats">
                        <div class="stat-item"><strong>{{ $totalSJ }}</strong> Surat Jalan</div>
                        <div class="stat-item"><strong>{{ $totalDO }}</strong> Delivery Order</div>
                        <div class="stat-item"><strong>{{ $totalItems }}</strong> Item Produk</div>
                    </div>
                </div>
            @endif

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
                        &nbsp;|&nbsp; Tanggal: {{ $sj->issued_at->format('d/m/Y') }}
                    </div>

                    <div class="sj-meta">
                        <span><strong>Customer:</strong> {{ $customerList ?: '-' }}</span>
                        <span><strong>Alamat:</strong> {{ $addressList ?: '-' }}</span>
                        <span><strong>Pengirim:</strong> {{ $sj->sender_name ?: '-' }}</span>
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
                    <table style="width:100%; border-collapse:collapse; margin-top:12px; font-size:10px;">
                        <tr>
                            <td style="border:none; text-align:center; width:33%">
                                Penerima,<br><br><br><br>
                                (____________________)
                            </td>
                            <td style="border:none; text-align:center; width:33%">
                                Pengirim,<br><br><br><br>
                                <strong>{{ $sj->sender_name ?: 'Tidak Diketahui' }}</strong>
                            </td>
                            <td style="border:none; text-align:center; width:33%">
                                Mengetahui,<br><br><br><br>
                                (____________________)
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        @endforeach
    @endif

</body>

</html>