@extends('layouts.app')

@section('title', 'Stok Barang Terjual · Duta Tunggal ERP')

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $flag = [
        'empty' => ['Habis', 'bg-red-50 text-red-800 border-red-200'],
        'low' => ['Di bawah minimum', 'bg-amber-50 text-amber-800 border-amber-300'],
        'ok' => [null, ''],
    ];
    $sameDay = $filters['date_from'] === $filters['date_to'];
@endphp

@section('content')
<div class="space-y-5">
    @include('laporan.pusat._head', [
        'title' => 'Stok Barang Terjual',
        'question' => 'Barang yang keluar karena penjualan (DO dikirim) per gudang, dibanding stok terakhir dan stok minimum gudang itu.',
        'pdfRoute' => 'laporan.stok-terjual.pdf',
        'excelRoute' => 'laporan.stok-terjual.excel',
    ])

    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end" role="search">
        <label class="text-xs font-semibold text-gray-700">Tanggal jual dari
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        </label>
        <label class="text-xs font-semibold text-gray-700">Sampai
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        </label>
        <label class="text-xs font-semibold text-gray-700">Cabang
            <select name="cabang_id" class="cursor-pointer mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                <option value="">Semua cabang</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected((string) $filters['cabang_id'] === (string) $b->id)>{{ $b->nama }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-700">Gudang
            <select name="warehouse_id" class="cursor-pointer mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
                <option value="">Semua gudang</option>
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" @selected((string) $filters['warehouse_id'] === (string) $w->id)>{{ $w->kode }} · {{ $w->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-700">Produk
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Kode / nama produk" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer flex-1 min-h-10 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm font-bold hover:bg-black active:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500">Tampilkan</button>
            <a href="{{ route('laporan.stok-terjual.index') }}" class="min-h-10 inline-flex items-center whitespace-nowrap px-3 py-2 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100">Hari ini</a>
        </div>
        @if ($errors->any())
            <p class="sm:col-span-2 lg:col-span-6 text-xs font-semibold text-red-700" role="alert">{{ $errors->first() }}</p>
        @endif
    </form>

    @include('laporan.pusat._kpis', ['kpis' => [
        ['Qty terjual', $num($summary['qty']), $sameDay ? \Carbon\Carbon::parse($filters['date_from'])->translatedFormat('l, d M Y') : \Carbon\Carbon::parse($filters['date_from'])->translatedFormat('d M') . ' s/d ' . \Carbon\Carbon::parse($filters['date_to'])->translatedFormat('d M Y'), null],
        ['Jenis produk terjual', number_format($summary['products'], 0, ',', '.'), null, null],
        ['Perlu restok', number_format($summary['below_min'], 0, ',', '.'), 'produk di bawah stok minimum', $summary['below_min'] > 0 ? 'warn' : null],
    ]])

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if (empty($rows))
            <div class="p-10 text-center text-sm text-gray-700">
                Tidak ada barang terjual {{ $sameDay ? 'pada tanggal ini' : 'pada periode ini' }}. Barang tercatat terjual saat DO dikirim.
                <a href="{{ route('laporan.stok-terjual.index', ['date_from' => now()->subDays(6)->toDateString(), 'date_to' => now()->toDateString()]) }}" class="ml-1 font-semibold text-[#15803d] underline underline-offset-2">Lihat 7 hari terakhir</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3">Produk</th>
                            <th scope="col" class="px-4 py-3">Gudang</th>
                            <th scope="col" class="px-4 py-3 text-right">Jumlah Jual</th>
                            <th scope="col" class="px-4 py-3 text-right">Stok Terakhir</th>
                            <th scope="col" class="px-4 py-3 text-right">Stok Min.</th>
                            <th scope="col" class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    @foreach ($rows as $p)
                        <tbody class="border-t border-gray-200">
                            @foreach ($p['lines'] as $i => $l)
                                <tr class="align-top {{ $l['flag'] !== 'ok' ? 'bg-amber-50/40' : '' }}">
                                    @if ($i === 0)
                                        <td class="px-4 py-3 min-w-56" rowspan="{{ count($p['lines']) }}">
                                            <span class="font-semibold text-gray-900">{{ $p['name'] }}</span>
                                            <span class="block text-xs text-gray-600 font-mono">{{ $p['sku'] }}</span>
                                            @if (count($p['lines']) > 1)
                                                <span class="block mt-1 text-xs text-gray-700">Total terjual <b class="tabular-nums">{{ $num($p['sold']) }}</b></span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-2.5 text-gray-800 whitespace-nowrap" title="{{ $l['warehouse_name'] }}">{{ $l['warehouse'] }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900">{{ $num($l['sold']) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums {{ $l['flag'] === 'empty' ? 'text-red-700 font-semibold' : 'text-gray-900' }}">{{ $num($l['stock']) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-600">{{ $l['min'] > 0 ? $num($l['min']) : '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if ($flag[$l['flag']][0])
                                            <span class="inline-flex px-2 py-0.5 rounded-md border text-xs font-semibold whitespace-nowrap {{ $flag[$l['flag']][1] }}">{{ $flag[$l['flag']][0] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-gray-700">
                <span>Produk {{ $page->firstItem() }}–{{ $page->lastItem() }} dari {{ number_format($page->total(), 0, ',', '.') }}, urut dari yang paling banyak terjual</span>
                {{ $page->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
