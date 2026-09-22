<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Nota Kredit {{ $creditNote->credit_note_number }}</title>
    <style>
        @page { margin: 15mm 16mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.4; color: #000; }
        table.items { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.items th, table.items td { border: 1px solid #999; padding: 5px 6px; }
        table.items th { background: #f2f2f2; text-align: left; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>

<body>
    @php
        $doc = $doc ?? app(\App\Services\DocumentPrintBuilder::class)->creditNote($creditNote);
        $money = fn ($amount) => \App\Support\LineAmounts::money($amount);
        $totals = $doc['totals'];
    @endphp
    @include('pdf.partials.watermark')
    @include('pdf.partials.company-header')

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; border: none; padding: 0;">
                <h3 style="margin: 0 0 4px 0;">{{ $doc['party_title'] }}:</h3>
                <strong>{{ $doc['party']['name'] }}</strong><br>
                @if ($doc['party']['company']){{ $doc['party']['company'] }}<br>@endif
                @if ($doc['party']['address']){{ $doc['party']['address'] }}<br>@endif
                @if ($doc['party']['npwp'])NPWP/NIK: {{ $doc['party']['npwp'] }}@endif
            </td>
            <td style="vertical-align: top; border: none; padding: 0;">
                @include('pdf.partials.doc-meta')
            </td>
        </tr>
    </table>

    <div><strong>Alasan:</strong> {{ $doc['reason'] }}</div>

    <table class="items">
        <thead>
            <tr>
                <th class="center" style="width: 4%;">No</th>
                <th>Uraian</th>
                <th class="right">Qty</th>
                <th class="right">Harga Satuan</th>
                <th class="right">DPP</th>
                <th class="right">PPN</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $index => $line)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($line['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                    <td class="right">{{ $money($line['unit_price']) }}</td>
                    <td class="right">{{ $money($line['subtotal']) }}</td>
                    <td class="right">{{ $money($line['tax_amount']) }}</td>
                    <td class="right">{{ $money($line['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width: 48%; margin-left: auto; border-collapse: collapse;">
        <tr><td style="border: none;">DPP</td><td class="right" style="border: none;">{{ $money($totals['subtotal']) }}</td></tr>
        <tr><td style="border: none;">PPN</td><td class="right" style="border: none;">{{ $money($totals['tax']) }}</td></tr>
        @if ($totals['other_fee'] > 0)
            <tr><td style="border: none;">Biaya Pengiriman</td><td class="right" style="border: none;">{{ $money($totals['other_fee']) }}</td></tr>
        @endif
        <tr><td style="border-top: 2px solid #333;"><strong>TOTAL NOTA KREDIT</strong></td><td class="right" style="border-top: 2px solid #333;"><strong>{{ $money($totals['total']) }}</strong></td></tr>
        @if ($totals['to_receivable'] > 0)
            <tr><td style="border: none;">Mengurangi piutang invoice</td><td class="right" style="border: none;">{{ $money($totals['to_receivable']) }}</td></tr>
        @endif
        @if ($totals['to_deposit'] > 0)
            <tr><td style="border: none;">Menjadi Deposit Customer</td><td class="right" style="border: none;">{{ $money($totals['to_deposit']) }}</td></tr>
        @endif
    </table>

    <div style="margin-top: 14px; font-size: 10px;">
        Nota Kredit ini mengoreksi invoice {{ $creditNote->invoice?->invoice_number ?? '-' }}. Nilai kembali diselesaikan dengan mengurangi piutang; kelebihan atas invoice yang sudah dibayar dicatat sebagai Deposit Customer.
    </div>

    @include('pdf.partials.signature-block')

    <div style="margin-top: 16px; font-size: 9px; color: #666; text-align: center;">
        Dicetak {{ $doc['printed_at'] }}@if ($doc['printed_by']) oleh {{ $doc['printed_by'] }}@endif
    </div>
</body>

</html>
