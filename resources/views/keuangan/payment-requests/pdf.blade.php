<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Voucher {{ $req->number }}</title>
    <style>
        @page { margin: 26px 30px; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5px; color: #111827; }
        .head { width: 100%; border-collapse: collapse; border-bottom: 2px solid #15803d; margin-bottom: 10px; }
        .head td { vertical-align: top; padding-bottom: 8px; }
        .company { font-size: 12px; font-weight: bold; }
        .muted { color: #4b5563; }
        h1 { font-size: 15px; margin: 0; text-align: right; }
        .no { text-align: right; font-size: 10px; margin-top: 3px; }
        .paid { display: inline-block; margin-top: 4px; padding: 2px 8px; border: 1px solid #15803d; color: #14532d; font-weight: bold; font-size: 8.5px; }
        .info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info td { padding: 3px 0; vertical-align: top; }
        .info .l { width: 110px; color: #4b5563; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background: #f3f4f6; text-align: left; padding: 6px; font-size: 8.5px; border-bottom: 1px solid #d1d5db; }
        table.lines td { padding: 6px; border-bottom: 1px solid #eef0f3; }
        .r { text-align: right; } .b { font-weight: bold; }
        .total td { border-top: 2px solid #111827; font-weight: bold; font-size: 11px; padding-top: 7px; }
        .words { margin-top: 6px; font-style: italic; color: #374151; }
        .sigs { width: 100%; border-collapse: collapse; margin-top: 22px; table-layout: fixed; }
        .sig-cell { border: 1px solid #e5e7eb; text-align: center; padding: 6px; vertical-align: top; }
        .sig-label { font-size: 8.5px; color: #4b5563; font-weight: bold; }
        .sig-space { height: 54px; }
        .sig-img { max-height: 52px; max-width: 140px; }
        .sig-name { font-weight: bold; border-top: 1px solid #9ca3af; padding-top: 3px; margin: 0 8px; }
        .sig-meta { font-size: 7.5px; color: #6b7280; margin-top: 2px; min-height: 18px; }
        .foot { margin-top: 10px; font-size: 7.5px; color: #6b7280; }
    </style>
</head>
<body>
    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
        $sig = fn ($role) => $req->signatures->firstWhere('role', $role);
        $words = \App\Services\DocumentPrintBuilder::terbilang((float) $req->amount);
    @endphp

    <table class="head"><tr>
        <td style="width: 58%;">
            <div class="company">{{ $company['name'] }}</div>
            <div class="muted">{!! collect([$company['address'], $company['phone'] ? 'Telp: '.$company['phone'] : null, $company['npwp'] ? 'NPWP: '.$company['npwp'] : null])->filter()->map(fn ($l) => e($l))->implode('<br>') !!}</div>
        </td>
        <td>
            <h1>BUKTI PENGELUARAN KAS / BANK</h1>
            <div class="no">No. Voucher: <b>{{ $req->cashBankTransaction?->number ?? $req->number }}</b></div>
            <div class="no">Permintaan: {{ $req->number }}</div>
            <div class="no"><span class="paid">SUDAH DIBAYAR</span></div>
        </td>
    </tr></table>

    <table class="info">
        <tr><td class="l">Dibayar kepada</td><td class="b">{{ $req->payee }}</td><td class="l">Tanggal bayar</td><td>{{ $req->paid_at?->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="l">Keperluan</td><td>{{ $req->description }}</td><td class="l">Dibayar dari</td><td>{{ $req->paymentCoa?->code }} {{ $req->paymentCoa?->name }}</td></tr>
        <tr><td class="l">Diminta oleh</td><td>{{ $req->requester?->name }} ({{ $req->request_date?->format('d/m/Y') }})</td><td class="l">Cabang</td><td>{{ $req->cabang?->nama ?? '-' }}</td></tr>
    </table>

    <table class="lines">
        <thead><tr><th style="width: 30%;">Akun (Debit)</th><th>Keterangan</th><th class="r" style="width: 22%;">Nominal</th></tr></thead>
        <tbody>
            @foreach ($req->lines as $l)
                <tr><td>{{ $l->coa?->code }} {{ $l->coa?->name }}</td><td>{{ $l->description ?: '-' }}</td><td class="r">{{ $rp($l->amount) }}</td></tr>
            @endforeach
            <tr class="total"><td colspan="2">TOTAL (Kredit {{ $req->paymentCoa?->name }})</td><td class="r">{{ $rp($req->amount) }}</td></tr>
        </tbody>
    </table>
    @if ($words)<div class="words">Terbilang: {{ $words }}</div>@endif

    <table class="sigs"><tr>
        @include('pdf._signature-cell', ['label' => 'Diminta oleh', 'sig' => $sig('requested'), 'fallback' => $req->requester?->name])
        @include('pdf._signature-cell', ['label' => 'Disetujui oleh', 'sig' => $sig('approved'), 'fallback' => $req->approver?->name])
        @include('pdf._signature-cell', ['label' => 'Dibayar oleh', 'sig' => $sig('paid'), 'fallback' => $req->payer?->name])
        @include('pdf._signature-cell', ['label' => 'Diterima oleh', 'sig' => null, 'fallback' => $req->payee])
    </tr></table>

    <div class="foot">Tanda tangan digital disimpan sistem beserta waktu & alamat IP penandatangan. Dicetak {{ now()->translatedFormat('d M Y H:i') }} oleh {{ auth()->user()?->name }}.</div>
</body>
</html>
