@extends('laporan.pusat.pdf._base', [
    'title' => 'Daftar Stok Produk',
    'subtitle' => 'Kode ' . ($filters['sku_from'] ?? 'awal') . ' s/d ' . ($filters['sku_to'] ?? 'akhir')
        . ($filters['tipe_pajak'] ? ' · ' . $filters['tipe_pajak'] : '')
        . ($filters['include_zero'] ? ' · termasuk qty kosong' : '')
        . ($filters['with_value'] ? ' · dengan nilai' : ' · tanpa nilai'),
])
@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
    $v = $filters['with_value'];
@endphp
@section('body')
<table class="kpis"><tr>
    <td><div class="l">Total qty</div><div class="v">{{ $num($summary['qty']) }}</div></td>
    <td><div class="l">Jenis produk</div><div class="v">{{ $summary['products'] }}</div></td>
    <td><div class="l">Baris produk-gudang</div><div class="v">{{ $summary['lines'] }}</div></td>
    @if ($v)<td><div class="l">Nilai persediaan (Rp)</div><div class="v">{{ $rp($summary['value']) }}</div></td>@endif
</tr></table>
<table class="t">
    <thead><tr>
        <th>Kode</th><th>Produk</th><th>Gudang</th><th class="r">Qty</th><th>Satuan</th>
        @if ($v)<th class="r">Harga Pokok</th><th class="r">Nilai</th>@else<th class="r">Hitung Fisik</th>@endif
    </tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td class="mono">{{ $r->sku }}</td>
            <td>{{ $r->name }}</td>
            <td>{{ $r->warehouse_code }}</td>
            <td class="r b {{ (float) $r->qty <= 0 ? 'bad' : '' }}">{{ $num($r->qty) }}</td>
            <td>{{ $r->uom ?? '-' }}</td>
            @if ($v)
                <td class="r">{{ $rp($r->cost_price) }}</td><td class="r b">{{ $rp((float) $r->qty * (float) $r->cost_price) }}</td>
            @else
                <td class="r muted">________</td>
            @endif
        </tr>
    @empty
        <tr><td colspan="7" class="muted">Tidak ada produk yang cocok.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
