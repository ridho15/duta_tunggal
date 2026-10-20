<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Picking List – Gudang {{ $warehouse->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #111827;
            background: #ffffff;
            font-size: 12px;
        }
        .container {
            max-width: 794px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
        }
        .header h1 {
            margin: 4px 0 0;
            font-size: 22px;
            font-weight: 800;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            font-size: 12px;
        }
        th, td {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
        }
        th {
            background: #f3f4f6;
            text-align: left;
            font-weight: 700;
        }
        .check-box {
            width: 32px;
            height: 20px;
            border: 1px solid #9ca3af;
            display: inline-block;
        }
        .no-print {
            margin-bottom: 20px;
            padding: 10px 16px;
            background: #f0fdf4;
            border: 1px solid #86efac;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="no-print">
        <span style="font-weight: 700; color: #15803d;">Pratinjau Cetak Picking List Gudang</span>
        <button onclick="window.print()" style="background: #15803d; color: #fff; border: 0; padding: 6px 14px; border-radius: 6px; font-weight: 700; cursor: pointer;">
            ⎙ Cetak Sekarang (A4)
        </button>
    </div>

    {{-- Header --}}
    <div class="header">
        <div>
            <div style="font-size: 13px; font-weight: 800; letter-spacing: 0.5px;">DUTA TUNGGAL ERP</div>
            <h1>PICKING LIST GUDANG</h1>
            <div style="font-size: 12px; color: #6b7280; margin-top: 2px;">
                Gudang <b>{{ $warehouse->name }}</b> · Dicetak {{ now()->translatedFormat('l, d M Y H:i') }} oleh {{ auth()->user()->name ?? 'Checker' }}
            </div>
        </div>

        <div style="text-align: right; font-size: 12px;">
            <b>{{ $deliveryOrders->count() }} DO</b> · {{ count($aggregatedItems) }} produk · {{ (int) collect($aggregatedItems)->sum('total_quantity') }} pcs<br>
            <span style="color: #6b7280;">Urutan: Rak → Produk</span>
        </div>
    </div>

    {{-- Table Items --}}
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">Rak</th>
                <th>Produk</th>
                <th style="width: 110px;">Kode SKU</th>
                <th style="text-align: right; width: 60px;">Total</th>
                <th>Untuk DO (Qty)</th>
                <th style="width: 40px; text-align: center;">✓</th>
            </tr>
        </thead>
        <tbody>
            @forelse($aggregatedItems as $item)
                <tr>
                    <td style="font-weight: 700;">{{ $item['rak'] }}</td>
                    <td style="font-weight: 600;">{{ $item['name'] }}</td>
                    <td style="font-family: monospace; color: #4b5563;">{{ $item['sku'] }}</td>
                    <td style="text-align: right; font-weight: 800; font-size: 13px;">{{ (int)$item['total_quantity'] }}</td>
                    <td style="color: #374151;">{{ implode(' · ', $item['for_dos']) }}</td>
                    <td style="text-align: center;">
                        <span class="check-box"></span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #9ca3af; padding: 20px;">
                        Tidak ada barang yang perlu diambil di gudang ini saat ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Ringkasan per DO --}}
    <h2 style="font-size: 14px; margin: 26px 0 8px; font-weight: 800;">Ringkasan per Delivery Order</h2>
    <table>
        <thead>
            <tr>
                <th>No. Delivery Order · Customer</th>
                <th>Kuantitas</th>
                <th style="width: 180px;">Verifikasi Checker</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deliveryOrders as $do)
                @php
                    $primarySO = $do->salesOrders->first();
                    $customer = $primarySO?->customer;
                    $itemsCount = $do->deliveryOrderItem->count();
                    $pcsCount = (int)$do->deliveryOrderItem->sum('quantity');
                @endphp
                <tr>
                    <td>
                        <b>{{ $do->do_number }}</b> · {{ $customer->name ?? 'Customer' }}
                    </td>
                    <td>{{ $itemsCount }} barang · {{ $pcsCount }} pcs</td>
                    <td style="color: #6b7280;">Dicek: ________________</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="font-size: 11px; color: #6b7280; margin-top: 18px;">
        Dokumen ini dibuat otomatis oleh sistem untuk panduan fisik pengambilan barang di rak gudang.
    </div>

</div>

</body>
</html>
