<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Order Request - PT DUTA TUNGGAL</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 15mm 20mm;
            @bottom-right {
                content: "Hal. " counter(page) " dari " counter(pages);
                font-size: 9pt;
                color: #666;
            }
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

        .center {
            text-align: center !important;
        }

        .right {
            text-align: right !important;
        }

        .notes-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #222;
        }

        .notes-section p {
            margin: 0;
            font-size: 11px;
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
            text-align: center;
            width: 50%;
            vertical-align: bottom;
            padding: 0;
        }

        .signature-box {
            display: inline-block;
            width: 250px;
        }

        .signature-name {
            font-weight: bold;
            border-top: 1px solid #888;
            padding-top: 5px;
            width: 80%;
            margin: 0 auto;
        }

        .page-header {
            display: none;
        }

        @media print {
            .page-header {
                display: table;
                width: 100%;
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body>

    {{-- Main Header --}}
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
        <div class="title">ORDER REQUEST</div>
    </div>

    {{-- Document Info --}}
    <table class="info-table">
        <tr>
            <td class="label">No. Order Request</td>
            <td>: <strong>{{ $orderRequest->request_number }}</strong></td>
            <td class="label">Tanggal</td>
            <td>: {{ \Carbon\Carbon::parse($orderRequest->request_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Dibuat Oleh</td>
            <td>: {{ $orderRequest->createdBy->name ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
        @if ($orderRequest->approve_at)
        @if ($orderRequest->relationLoaded('approveBy'))
        <tr>
            <td class="label">Disetujui Oleh</td>
            <td>: {{ $orderRequest->approveBy->name ?? '-' }}</td>
            <td class="label">Tgl. Approval</td>
            <td>: {{ \Carbon\Carbon::parse($orderRequest->approve_at)->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
        @endif
        @if ($orderRequest->note)
        <tr>
            <td class="label">Keterangan</td>
            <td>: {{ $orderRequest->note }}</td>
            <td></td>
            <td></td>
        </tr>
        @endif
    </table>

    {{-- Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th class="center" style="width: 5%;">No</th>
                <th>Product (SKU - Nama)</th>
                <th class="center" style="width: 10%;">Supplier</th>
                <th class="center" style="width: 8%;">Satuan</th>
                <th class="center" style="width: 8%;">Qty</th>
                <th>Keterangan / Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($orderRequest->orderRequestItem as $index => $item)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td>({{ $item->product->sku ?? '-' }}) {{ $item->product->name ?? '-' }}</td>
                <td class="center">{{ $item->supplier->perusahaan ?? '-' }}</td>
                <td class="center">{{ $item->product->uom->name ?? '-' }}</td>
                <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                <td>{{ $item->note ?? '-' }}</td>
            </tr>
            @endforeach

            {{-- Empty row placeholder --}}
            @if ($orderRequest->orderRequestItem->isEmpty())
            <tr>
                <td class="center">1</td>
                <td>-</td>
                <td class="center">-</td>
                <td class="center">-</td>
                <td class="center">-</td>
                <td>-</td>
            </tr>
            @endif
        </tbody>
    </table>

    {{-- Notes Section --}}
    @if ($orderRequest->notes)
    <div class="notes-section">
        <p><strong>Catatan:</strong> {{ $orderRequest->notes }}</p>
    </div>
    @endif

    {{-- Signature Section --}}
    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">
                            Jakarta, {{ \Carbon\Carbon::parse($orderRequest->approve_at ?? now())->format('d/m/Y') }}<br>
                            Hormat kami,
                        </p>
                        <div style="height: 70px; margin: 10px 0;"></div>
                        <div class="signature-name">
                            {{ $orderRequest->createdBy->name ?? 'PT DUTA TUNGGAL' }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">Disetujui Oleh,</p>
                        <div style="height: 70px; margin: 10px 0;"></div>
                        <div class="signature-name">
                            {{ $orderRequest->approveBy->name ?? '_________________' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>