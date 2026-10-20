<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Picking List {{ $warehouse->kode ?: $warehouse->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111827; margin: 0; }
        .head { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 11px; font-weight: bold; }
        h1 { font-size: 18px; margin: 2px 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-size: 10px; }
        .num { text-align: right; font-weight: bold; font-size: 13px; }
        .rak { font-weight: bold; }
        .box { width: 26px; }
        .barcode { margin-top: 3px; }
        h2 { font-size: 13px; margin: 16px 0 6px; }
    </style>
</head>
<body>
    <table style="border:0" class="head">
        <tr>
            <td style="border:0;padding:0">
                <div class="brand">DUTA TUNGGAL</div>
                <h1>PICKING LIST</h1>
                <div class="muted">Gudang {{ $warehouse->kode ?: '' }} {{ $warehouse->name }} · Dicetak {{ $printedAt->translatedFormat('l, d M Y H.i') }} oleh {{ $printedBy }}</div>
            </td>
            <td style="border:0;padding:0;text-align:right" class="muted">
                {{ $orders->count() }} DO · {{ $rows->count() }} baris · {{ rtrim(rtrim(number_format($total_qty, 2, ',', '.'), '0'), ',') }} pcs<br>
                Urut: Rak → Produk
            </td>
        </tr>
    </table>

    @if ($rows->isEmpty())
        <p class="muted">Tidak ada DO yang siap dicek &amp; dikirim di gudang ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:50px">Rak</th>
                    <th>Produk</th>
                    <th style="width:120px">Kode</th>
                    <th style="width:55px;text-align:right">Total</th>
                    <th>Untuk DO (qty)</th>
                    <th class="box">✓</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="rak">{{ $row['rak'] ?? '–' }}</td>
                        <td>{{ $row['product'] }}</td>
                        <td>
                            {{ $row['sku'] }}
                            @if ($row['sku'])
                                <div class="barcode">{!! DNS1D::getBarcodeHTML($row['sku'], 'C128', 1, 22) !!}</div>
                            @endif
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format($row['total'], 2, ',', '.'), '0'), ',') }}</td>
                        <td>
                            @foreach ($row['orders'] as $doNumber => $qty)
                                {{ $doNumber }} ({{ rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',') }})@if (! $loop->last) · @endif
                            @endforeach
                        </td>
                        <td class="box"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Ringkasan per DO</h2>
        <table>
            @foreach ($orders as $order)
                @php $items = $order->deliveryOrderItem->filter(fn ($i) => (float) $i->quantity > 0); @endphp
                <tr>
                    <td>{{ $order->do_number }} · {{ $order->salesOrders->first()?->customer?->name ?? '-' }}</td>
                    <td style="width:130px">{{ $items->count() }} barang · {{ rtrim(rtrim(number_format($items->sum('quantity'), 2, ',', '.'), '0'), ',') }} pcs</td>
                    <td style="width:170px">Dicek oleh: ______________</td>
                </tr>
            @endforeach
        </table>
        <p class="muted" style="margin-top:12px">Isi: DO berstatus "Siap dicek &amp; dikirim" untuk gudang ini. Barcode di kolom Kode bisa discan dari HP.</p>
    @endif
</body>
</html>
