<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>SALES ORDER</title>
    @php
        $formatMoney = function (float $amount) {
            return 'Rp ' . number_format($amount, 0, ',', '.');
        };
    @endphp
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 20mm;
        }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.4;
        }

        .header-table {
            width: 100%;
            border: none;
            margin-bottom: 5px;
        }

        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
        }

        .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #222;
        }

        .company-info {
            font-size: 11px;
            color: #555;
        }

        .logo {
            height: 50px;
            object-fit: contain;
        }

        .title-container {
            text-align: center;
            margin: 15px 0;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: inline-block;
            border-bottom: 2px solid #222;
            padding-bottom: 3px;
        }

        .info-table {
            width: 100%;
            border: none;
            margin-bottom: 15px;
        }

        .info-table td {
            border: none;
            padding: 4px 8px;
            vertical-align: top;
        }

        .info-table td.label {
            font-weight: bold;
            width: 130px;
            color: #555;
            white-space: nowrap;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }

        .items-table th {
            background-color: #f4f6f8;
            font-weight: bold;
            color: #444;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .right {
            text-align: right !important;
        }

        .center {
            text-align: center !important;
        }

        .summary-row td {
            border-top: none;
            border-bottom: none;
            padding: 5px 8px;
            font-size: 11px;
        }

        .summary-row.total td {
            border-top: 1px solid #222;
            border-bottom: 2px solid #222;
            background-color: #f4f6f8;
            font-size: 12px;
            font-weight: bold;
            color: #111;
        }

        .signature-section {
            margin-top: 40px;
            width: 100%;
        }

        .signature-table {
            width: 100%;
            border: none;
        }

        .signature-table td {
            border: none;
            text-align: right;
            vertical-align: bottom;
            padding: 0;
        }

        .signature-box {
            display: inline-block;
            width: 250px;
            text-align: center;
        }

        .signature-img {
            height: 70px;
            margin: 10px 0;
            object-fit: contain;
        }
        
        .signature-name {
            font-weight: bold;
            border-top: 1px solid #888;
            padding-top: 5px;
            width: 80%;
            margin: 0 auto;
        }
    </style>
</head>

<body>

    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <div class="company-name">PT DUTA TUNGGAL</div>
                <div class="company-info">
                    Jl. Contoh No. 123<br>
                    Jakarta, Indonesia<br>
                    Telp: (021) 12345678<br>
                    Email: admin@dutatunggal.co.id
                </div>
            </td>
            <td class="right">
                <img src="{{ public_path('logo_duta_tunggal.png') }}" class="logo" alt="Logo">
            </td>
        </tr>
    </table>

    <div class="title-container">
        <div class="title">SALES ORDER</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">No. SO</td>
            <td>: <strong>{{ $saleOrder->so_number }}</strong></td>
            <td class="label">Tanggal SO</td>
            <td>: {{ \Carbon\Carbon::parse($saleOrder->order_date)->locale('id')->format('d M Y') }}</td>
        </tr>
        <tr>
            <td class="label">Customer</td>
            <td>: <strong>{{ $saleOrder->customer->name ?? '-' }}</strong></td>
            <td class="label">Cabang</td>
            <td>: {{ $saleOrder->cabang->nama ?? '-' }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="center" style="width: 5%;">No</th>
                <th>Nama Barang</th>
                <th class="center" style="width: 8%;">Qty</th>
                <th class="right" style="width: 13%;">Harga Satuan</th>
                <th class="right" style="width: 13%;">Diskon (Rp)</th>
                <th class="center" style="width: 13%;">Tipe Pajak</th>
                <th class="right" style="width: 8%;">Tax (%)</th>
                <th class="right" style="width: 12%;">Tax (Rp)</th>
                <th class="right" style="width: 15%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $total = 0; @endphp
            @foreach ($saleOrder->saleOrderItem as $index => $item)
                @php
                    // compute using TaxService for accuracy (handles inclusive/exclusive)
                    $lineBase = $item->quantity * $item->unit_price;
                    $discountAmount = $lineBase * ($item->discount / 100);
                    $afterDiscount = $lineBase - $discountAmount;
                    $taxType = \App\Services\TaxService::normalizeType($item->tipe_pajak ?? 'PPN Excluded');
                    $taxResult = \App\Services\TaxService::compute($afterDiscount, (float)$item->tax, $taxType);
                    $taxAmount = $taxResult['ppn'];
                    $subtotal = $taxResult['total'];
                    $total += $subtotal;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="right">{{ $formatMoney($discountAmount) }}</td>
                    <td class="center">{{ $taxType }}</td>
                    <td class="right">{{ number_format($item->tax, 2, ',', '.') }}%</td>
                    <td class="right">{{ $formatMoney($taxAmount) }}</td>
                    <td class="right">{{ $formatMoney($subtotal) }}</td>
                </tr>
            @endforeach
            <tr class="summary-row total">
                <td colspan="8" class="right">GRAND TOTAL</td>
                <td class="right">{{ $formatMoney($total) }}</td>
            </tr>
        </tbody>
    </table>

    <div style="margin-top: 15px; font-size: 11px;">
        <strong>Terbilang:</strong> <em>{{ \App\Http\Controllers\HelperController::terbilang($saleOrder->total_amount) }} Rupiah</em>
    </div>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">
                            Jakarta, {{ \Carbon\Carbon::parse($saleOrder->approve_at ?? now())->locale('id')->format('d M Y') }}<br>
                            Hormat kami,
                        </p>
                        @if ($saleOrder->approveBy->signature ?? null)
                            <img src="{{ public_path('storage' . $saleOrder->approveBy->signature) }}" class="signature-img" alt="Signature">
                        @else
                            <div style="height: 70px; margin: 10px 0;"></div>
                        @endif
                        <div class="signature-name">
                            {{ $saleOrder->approveBy->name ?? 'PT DUTA TUNGGAL' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>