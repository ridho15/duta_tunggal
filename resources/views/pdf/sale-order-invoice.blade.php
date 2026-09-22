<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
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
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
            line-height: 1.4;
        }

        .header {
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info {
            float: left;
            width: 50%;
        }

        .invoice-title {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-title h1 {
            color: #333;
            font-size: 32px;
            margin: 0 0 10px 0;
        }

        .invoice-details {
            clear: both;
            margin: 30px 0;
        }

        .customer-info {
            float: left;
            width: 50%;
        }

        .invoice-info {
            float: right;
            width: 50%;
            text-align: right;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
            clear: both;
        }

        .invoice-table th,
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .invoice-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .totals-section {
            float: right;
            width: 40%;
            margin-top: 20px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .totals-table .total-row {
            font-weight: bold;
            border-top: 2px solid #333;
            background-color: #f5f5f5;
        }

        .footer {
            clear: both;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            text-align: center;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .rupiah {
            font-family: monospace;
        }

        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-info td {
            padding: 4px 0;
            vertical-align: top;
        }
    </style>
</head>

<body>
    @php
        $customer = $invoice->customer;
        $effectivePpnRate = (float) ($invoice->effective_ppn_rate ?? 0);
        $ppnAmount = (float) ($invoice->ppn_amount ?? 0);
        $displayRate = $effectivePpnRate > 0 ? $effectivePpnRate : (float) ($invoice->tax ?? 0);
        $invoiceItems = $invoice->invoiceItem ?? collect();
        $ppnMonetary = $invoiceItems->sum('tax_amount');

        if ($ppnMonetary <= 0 && $displayRate > 0) {
            $ppnMonetary = (float) $invoice->subtotal * ($displayRate / 100);
        }
    @endphp

    @php $doc = $doc ?? app(\App\Services\DocumentPrintBuilder::class)->invoice($invoice); @endphp
    @include('pdf.partials.watermark')
    @include('pdf.partials.company-header')

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 52%; vertical-align: top; border: none; padding: 0;">
                <h3 style="margin: 0 0 4px 0;">{{ $doc['party_title'] }}:</h3>
                <strong>{{ $doc['party']['name'] }}</strong><br>
                @if ($doc['party']['company']){{ $doc['party']['company'] }}<br>@endif
                @if ($doc['party']['address']){{ $doc['party']['address'] }}<br>@endif
                @if ($doc['party']['phone'])Telp: {{ $doc['party']['phone'] }}<br>@endif
                @if ($doc['party']['email'])Email: {{ $doc['party']['email'] }}<br>@endif
                @if ($doc['party']['npwp'])NPWP/NIK: {{ $doc['party']['npwp'] }}@endif
            </td>
            <td style="vertical-align: top; border: none; padding: 0;">
                @include('pdf.partials.doc-meta')
                @if ($doc['source'])
                    <table style="width: 100%; border-collapse: collapse;"><tr>
                        <td style="width: 32%; padding: 2px 6px 2px 0; border: none;"><strong>No. Sales Order</strong></td>
                        <td style="padding: 2px 0; border: none;">: {{ $doc['source'] }}</td>
                    </tr></table>
                @endif
            </td>
        </tr>
    </table>

    @php
        // Rincian baris baku (Fase 5B): Harga Satuan × Qty = Jumlah · Diskon (% dan Rp) · DPP · PPN (% dan Rp) · Total.
        // Satu sumber: InvoiceItem::breakdown() — sama dengan halaman Lihat Invoice, sehingga PDF = layar.
        $rows = $invoiceItems->map(function ($item) use ($invoice) {
            $item->setRelation('invoice', $invoice);

            return [$item, $item->breakdown()];
        });
        $sumGross = (float) $rows->sum(fn ($r) => $r[1]['gross']);
        $sumDiscount = (float) $rows->sum(fn ($r) => $r[1]['discount_amount']);
        $sumDpp = (float) $rows->sum(fn ($r) => $r[1]['dpp']);
        $sumPpn = (float) $rows->sum(fn ($r) => $r[1]['ppn']);
        $sumLines = (float) $rows->sum(fn ($r) => $r[1]['total']);
        $otherFees = round((float) $invoice->total - $sumLines, 2);
        $money = fn ($amount) => \App\Support\LineAmounts::money($amount);
    @endphp

    <table class="invoice-table">
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th class="text-left">SKU</th>
                <th class="text-left">Produk</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Diskon (%)</th>
                <th class="text-right">Diskon (Rp)</th>
                <th class="text-right">DPP</th>
                <th class="text-right">PPN (%)</th>
                <th class="text-right">PPN (Rp)</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $index => [$item, $line])
            @php $product = $item->product; @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-left">{{ optional($product)->sku ?? 'N/A' }}</td>
                <td class="text-left">{{ optional($product)->name ?? 'N/A' }}</td>
                <td class="text-center">{{ rtrim(rtrim(number_format($line['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                <td class="text-right rupiah">{{ $money($line['unit_price']) }}</td>
                <td class="text-right rupiah">{{ $money($line['gross']) }}</td>
                <td class="text-right">{{ number_format($line['discount_pct'], 2, ',', '.') }}%</td>
                <td class="text-right rupiah">{{ $money($line['discount_amount']) }}</td>
                <td class="text-right rupiah">{{ $money($line['dpp']) }}</td>
                <td class="text-right">{{ number_format($line['tax_rate'], 2, ',', '.') }}%</td>
                <td class="text-right rupiah">{{ $money($line['ppn']) }}</td>
                <td class="text-right rupiah">{{ $money($line['total']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Jumlah (Harga × Qty):</td>
                <td class="text-right rupiah">{{ $money($sumGross) }}</td>
            </tr>
            @if($sumDiscount > 0)
            <tr>
                <td>Diskon:</td>
                <td class="text-right rupiah">- {{ $money($sumDiscount) }}</td>
            </tr>
            @endif
            <tr>
                <td>Subtotal (DPP):</td>
                <td class="text-right rupiah">{{ $money($sumDpp > 0 ? $sumDpp : $invoice->subtotal) }}</td>
            </tr>
            @if($displayRate > 0)
            <tr>
                <td>PPN ({{ number_format($displayRate, 2, ',', '.') }}%) :</td>
                <td class="text-right rupiah">{{ $money($sumPpn > 0 ? $sumPpn : $ppnMonetary) }}</td>
            </tr>
            @endif
            @if($otherFees > 0.005)
            <tr>
                <td>Biaya Lain:</td>
                <td class="text-right rupiah">{{ $money($otherFees) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td><strong>TOTAL:</strong></td>
                <td class="text-right rupiah"><strong>{{ $money($invoice->total) }}</strong></td>
            </tr>
            @foreach ($doc['credit_notes'] as $creditNote)
            <tr>
                <td>Nota Kredit {{ $creditNote['number'] }}:</td>
                <td class="text-right rupiah">- {{ $money($creditNote['total']) }}</td>
            </tr>
            @endforeach
            @if (!empty($doc['credit_notes']))
            <tr class="total-row">
                <td><strong>TOTAL SETELAH NOTA KREDIT:</strong></td>
                <td class="text-right rupiah"><strong>{{ $money((float) $invoice->total - $doc['credit_total']) }}</strong></td>
            </tr>
            @endif
        </table>
    </div>

    <div style="clear: both;"></div>

    @include('pdf.partials.bank-accounts')

    <div style="margin-top: 14px; font-size: 10px;">
        <strong>Syarat dan Ketentuan:</strong>
        <ol style="margin: 4px 0 0 16px; padding: 0;">
            @foreach ($doc['terms'] as $term)
                <li>{{ $term }}</li>
            @endforeach
        </ol>
    </div>

    @include('pdf.partials.signature-block')

    <div class="footer" style="margin-top: 18px;">
        Dicetak {{ $doc['printed_at'] }}@if ($doc['printed_by']) oleh {{ $doc['printed_by'] }}@endif
    </div>
</body>

</html>