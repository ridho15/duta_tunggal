@extends('laporan.pusat.pdf._base', [
    'title' => 'Stok Barang Terjual',
    'subtitle' => 'Tanggal jual ' . \Carbon\Carbon::parse($filters['date_from'])->translatedFormat('d M Y') . ($filters['date_from'] !== $filters['date_to'] ? ' s/d ' . \Carbon\Carbon::parse($filters['date_to'])->translatedFormat('d M Y') : ''),
])
@php $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ','); @endphp
@section('body')
<table class="kpis"><tr>
    <td><div class="l">Qty terjual</div><div class="v">{{ $num($summary['qty']) }}</div></td>
    <td><div class="l">Jenis produk terjual</div><div class="v">{{ $summary['products'] }}</div></td>
    <td><div class="l">Perlu restok (di bawah minimum)</div><div class="v">{{ $summary['below_min'] }}</div></td>
</tr></table>
<table class="t">
    <thead><tr><th>Produk</th><th>Gudang</th><th class="r">Jumlah Jual</th><th class="r">Stok Terakhir</th><th class="r">Stok Min.</th><th>Keterangan</th></tr></thead>
    <tbody>
    @forelse ($rows as $p)
        @foreach ($p['lines'] as $i => $l)
            <tr>
                <td>@if ($i === 0)<span class="b">{{ $p['name'] }}</span><br><span class="muted mono">{{ $p['sku'] }}</span>@endif</td>
                <td>{{ $l['warehouse'] }}</td>
                <td class="r b">{{ $num($l['sold']) }}</td>
                <td class="r {{ $l['flag'] === 'empty' ? 'bad' : '' }}">{{ $num($l['stock']) }}</td>
                <td class="r muted">{{ $l['min'] > 0 ? $num($l['min']) : '-' }}</td>
                <td class="{{ $l['flag'] === 'ok' ? '' : 'warn' }}">{{ ['empty' => 'Habis', 'low' => 'Di bawah minimum', 'ok' => ''][$l['flag']] }}</td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="6" class="muted">Tidak ada barang terjual pada periode ini.</td></tr>
    @endforelse
    </tbody>
</table>
@endsection
