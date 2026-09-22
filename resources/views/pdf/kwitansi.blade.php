<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kwitansi {{ $receipt->id }}</title>
    <style>
        @page { margin: 14mm 16mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: #000; }
        .box { border: 2px solid #333; padding: 8px 12px; margin: 10px 0; }
    </style>
</head>

<body>
    @php $doc = $doc ?? app(\App\Services\DocumentPrintBuilder::class)->receipt($receipt); @endphp
    @include('pdf.partials.watermark')
    @include('pdf.partials.company-header')
    @include('pdf.partials.doc-meta')

    <div class="box">
        <div style="font-size: 10px; color: #555;">Terbilang</div>
        <div style="font-size: 14px; font-style: italic;">{{ $doc['amount_words'] }}</div>
    </div>
    <div class="box" style="width: 45%;">
        <div style="font-size: 10px; color: #555;">Jumlah</div>
        <div style="font-size: 20px; font-weight: bold;">{{ \App\Support\LineAmounts::money($doc['amount']) }}</div>
    </div>

    @include('pdf.partials.signature-block')

    <div style="margin-top: 16px; font-size: 9px; color: #666; text-align: center;">
        Kwitansi ini sah setelah dana diterima. Dicetak {{ $doc['printed_at'] }}@if ($doc['printed_by']) oleh {{ $doc['printed_by'] }}@endif
    </div>
</body>

</html>
