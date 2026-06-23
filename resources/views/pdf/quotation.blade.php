<!DOCTYPE html>
<html lang="en">

<head>
    @php
        $formatMoney = function (float $amount) {
            return 'Rp ' . number_format($amount, 0, ',', '.');
        };
    @endphp
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
        <div class="title">QUOTATION</div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">No. Quotation</td>
            <td>: <strong>{{ $quotation->quotation_number }}</strong></td>
            <td class="label">Tanggal</td>
            <td>: {{ \Carbon\Carbon::parse($quotation->date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Customer</td>
            <td>: <strong>{{ $quotation->customer->name ?? '-' }}</strong></td>
            <td class="label">Valid Until</td>
            <td>: {{ $quotation->valid_until ? \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td>: {{ $quotation->customer->address ?? '-' }}</td>
            <td class="label">TOP</td>
            <td>: {{ $quotation->top_type ?? ($quotation->customer->tempo_kredit ?? 30) . ' hari' }}</td>
        </tr>
        <tr>
            <td class="label">Telepon</td>
            <td>: {{ $quotation->customer->phone ?? $quotation->customer->telp ?? '-' }}</td>
            <td class="label">Dibuat Oleh</td>
            <td>: {{ $quotation->createdBy->name ?? 'Staff Penjualan' }}</td>
        </tr>
        @if ($quotation->approve_at)
        <tr>
            <td class="label">Disetujui Oleh</td>
            <td>: {{ $quotation->approveBy->name ?? '-' }}</td>
            <td class="label">Tgl. Approval</td>
            <td>: {{ \Carbon\Carbon::parse($quotation->approve_at)->format('d/m/Y H:i') }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Cabang</td>
            <td>: {{ $quotation->cabang->nama ?? '-' }}</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th class="center" style="width: 4%;">No</th>
                <th>Nama Barang</th>
                <th class="center" style="width: 7%;">Satuan</th>
                <th class="center" style="width: 6%;">Qty</th>
                <th class="right" style="width: 11%;">Harga Satuan</th>
                <th class="right" style="width: 7%;">Disc (%)</th>
                <th class="center" style="width: 9%;">Tipe Pajak</th>
                <th class="right" style="width: 7%;">Tax (%)</th>
                <th class="right" style="width: 10%;">DPP</th>
                <th class="right" style="width: 8%;">PPN</th>
                <th class="right" style="width: 12%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php
                $grandTotal = 0;
                $totalDpp = 0;
                $totalPpn = 0;
            @endphp
            @foreach ($quotation->quotationItem as $index => $item)
                @php
                    $lineBase = $item->quantity * $item->unit_price;
                    $discountAmount = $lineBase * ($item->discount / 100);
                    $afterDiscount = $lineBase - $discountAmount;
                    $taxType = \App\Services\TaxService::normalizeType($item->tax_type ?? 'PPN Excluded');
                    $displayTaxType = match ($taxType) {
                        'Inklusif' => 'PPN Included',
                        'Eksklusif' => 'PPN Excluded',
                        default => 'Non Pajak',
                    };
                    $taxResult = \App\Services\TaxService::compute($afterDiscount, (float) $item->tax, $taxType);
                    $itemDpp = $taxResult['dpp'];
                    $taxAmount = $taxResult['ppn'];
                    $lineSubtotal = $taxResult['total'];

                    $totalDpp += $itemDpp;
                    $totalPpn += $taxAmount;
                    $grandTotal += $lineSubtotal;
                @endphp
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>({{ $item->product->sku }}) {{ $item->product->name }}</td>
                    <td class="center">{{ $item->product->uom->name ?? '-' }}</td>
                    <td class="center">{{ number_format($item->quantity, 0, ',', '.') }}</td>
                    <td class="right">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="right">{{ number_format($item->discount, 2, ',', '.') }}%</td>
                    <td class="center">{{ $displayTaxType }}</td>
                    <td class="right">{{ number_format($item->tax, 2, ',', '.') }}%</td>
                    <td class="right">{{ $formatMoney($itemDpp) }}</td>
                    <td class="right">{{ $formatMoney($taxAmount) }}</td>
                    <td class="right">{{ $formatMoney($lineSubtotal) }}</td>
                </tr>
            @endforeach

            {{-- Summary Rows --}}
            <tr class="summary-row">
                <td colspan="10" class="right"><strong>DPP (Dasar Pengenaan Pajak)</strong></td>
                <td class="right"><strong>{{ $formatMoney($totalDpp) }}</strong></td>
            </tr>
            <tr class="summary-row">
                <td colspan="10" class="right"><strong>PPN (Pajak Pertambahan Nilai)</strong></td>
                <td class="right"><strong>{{ $formatMoney($totalPpn) }}</strong></td>
            </tr>
            <tr class="summary-row total">
                <td colspan="10" class="right">GRAND TOTAL</td>
                <td class="right">{{ $formatMoney($grandTotal) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($quotation->notes)
    <div style="margin-top: 15px;">
        <p><strong>Notes:</strong></p>
        <p>{{ $quotation->notes }}</p>
    </div>
    @endif

    <div style="margin-top: 15px; font-size: 11px;">
        <strong>Terbilang:</strong> <em>{{ \App\Http\Controllers\HelperController::terbilang($grandTotal) }} Rupiah</em>
    </div>

    <div class="signature-section">
        <table class="signature-table">
            <tr>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">
                            Jakarta, {{ \Carbon\Carbon::parse($quotation->approve_at ?? now())->format('d/m/Y') }}<br>
                            Hormat kami,
                        </p>
                        <div style="height: 70px; margin: 10px 0;"></div>
                        <div class="signature-name">
                            {{ $quotation->approveBy->name ?? 'PT DUTA TUNGGAL' }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="signature-box">
                        <p style="margin-bottom: 10px;">Disetujui Oleh,</p>
                        <div style="height: 70px; margin: 10px 0;"></div>
                        <div class="signature-name">
                            {{ $quotation->customer->name ?? 'Customer' }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>

</html>