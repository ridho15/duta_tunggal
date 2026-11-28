@extends('laporan.pusat.pdf._base', [
    'title' => 'Detail Penjualan',
    'subtitle' => 'Periode ' . \Carbon\Carbon::parse($filters['date_from'])->translatedFormat('d M Y') . ' s/d ' . \Carbon\Carbon::parse($filters['date_to'])->translatedFormat('d M Y') . ($filters['q'] ? ' · cari "' . $filters['q'] . '"' : ''),
])
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp
@section('body')
<table class="kpis"><tr>
    <td><div class="l">Total penjualan (Rp)</div><div class="v">{{ $rp($summary['total']) }}</div></td>
    <td><div class="l">Qty terjual</div><div class="v">{{ $num($summary['qty']) }}</div></td>
    <td><div class="l">Jumlah faktur</div><div class="v">{{ $summary['invoices'] }}</div></td>
    <td><div class="l">Nilai retur (Rp)</div><div class="v">{{ $rp($summary['returns']) }}</div></td>
</tr></table>
<table class="t">
    <thead><tr>
        <th>Tanggal</th><th>No. Faktur / SO / e-Faktur</th><th>Customer</th><th>Produk</th>
        <th class="r">Qty</th><th class="r">Harga</th><th class="r">Disc</th><th class="r">Total</th><th>Status Kirim</th><th class="r">Retur</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td>{{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/Y') : '-' }}</td>
            <td><span class="b">{{ $r['invoice_number'] }}</span><br><span class="muted">{{ $r['so_number'] }}@if ($r['tax_invoice_number']) · {{ $r['tax_invoice_number'] }}@endif</span></td>
            <td>{{ $r['customer'] }}<br><span class="muted">{{ $r['branch'] }}</span></td>
            <td>{{ $r['product'] }}<br><span class="muted mono">{{ $r['sku'] }}</span></td>
            <td class="r b">{{ $num($r['qty']) }}</td>
            <td class="r">{{ $rp($r['price']) }}</td>
            <td class="r">{{ $r['discount_pct'] > 0 ? $num($r['discount_pct']) . '%' : '-' }}</td>
            <td class="r b">{{ $rp($r['total']) }}</td>
            <td class="{{ in_array($r['delivery']['key'], ['none', 'partial'], true) ? 'warn' : '' }}">{{ $r['delivery']['label'] }}</td>
            <td class="r">{{ $r['return_qty'] > 0 ? $num($r['return_qty']) . ' (' . $rp($r['return_total']) . ')' : '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="muted">Tidak ada penjualan pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
