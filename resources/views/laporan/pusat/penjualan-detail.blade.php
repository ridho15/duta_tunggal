@extends('layouts.app')

@section('title', 'Detail Penjualan · Duta Tunggal ERP')

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $badge = [
        'done' => 'bg-[#f0fdf4] text-[#14532d] border-[#86efac]',
        'partial' => 'bg-amber-50 text-amber-800 border-amber-300',
        'none' => 'bg-red-50 text-red-800 border-red-200',
        'na' => 'bg-gray-50 text-gray-700 border-gray-200',
    ];
@endphp

@section('content')
<div class="space-y-5">
    @include('laporan.pusat._head', [
        'title' => 'Detail Penjualan',
        'question' => 'Barang apa terjual ke siapa, berapa, sudah dikirim atau belum, dan ada retur atau tidak. Tanggal = tanggal faktur.',
        'pdfRoute' => 'laporan.penjualan-detail.pdf',
        'excelRoute' => 'laporan.penjualan-detail.excel',
    ])

    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end" role="search">
        <label class="text-xs font-semibold text-gray-700">Dari tanggal
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
        <div class="text-xs font-semibold text-gray-700">
            <span class="block mb-1">Customer</span>
            <x-searchable-select name="customer_id" :options="$customer ? [['id' => $customer->id, 'label' => $customer->name, 'subtext' => $customer->code]] : []"
                :remote-url="route('penjualan.customers.search')" :value="$filters['customer_id']" placeholder="Semua customer" />
        </div>
        <label class="text-xs font-semibold text-gray-700">Cari
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="No. SO / faktur / e-faktur / produk" class="mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer flex-1 min-h-10 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm font-bold hover:bg-black active:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500">Tampilkan</button>
            <a href="{{ route('laporan.penjualan-detail.index') }}" class="min-h-10 inline-flex items-center whitespace-nowrap px-3 py-2 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100">Reset</a>
        </div>
        @if ($errors->any())
            <p class="sm:col-span-2 lg:col-span-6 text-xs font-semibold text-red-700" role="alert">{{ $errors->first() }}</p>
        @endif
    </form>

    @include('laporan.pusat._kpis', ['kpis' => [
        ['Total penjualan', $rp($summary['total']), \Carbon\Carbon::parse($filters['date_from'])->translatedFormat('d M') . ' s/d ' . \Carbon\Carbon::parse($filters['date_to'])->translatedFormat('d M Y'), null],
        ['Qty terjual', $num($summary['qty']), null, null],
        ['Jumlah faktur', number_format($summary['invoices'], 0, ',', '.'), null, null],
        ['Nilai retur', $rp($summary['returns']), $summary['total'] > 0 ? number_format($summary['returns'] / $summary['total'] * 100, 1, ',', '.') . '% dari penjualan' : null, $summary['returns'] > 0 ? 'warn' : null],
    ]])

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if (empty($rows))
            <div class="p-10 text-center text-sm text-gray-700">
                Tidak ada penjualan pada periode dan filter ini.
                <a href="{{ route('laporan.penjualan-detail.index', ['date_from' => now()->subMonth()->startOfMonth()->toDateString(), 'date_to' => now()->toDateString()]) }}" class="ml-1 font-semibold text-[#15803d] underline underline-offset-2">Lihat 2 bulan terakhir</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700 sticky top-0">
                        <tr>
                            <th scope="col" class="px-3 py-3 whitespace-nowrap">Tanggal</th>
                            <th scope="col" class="px-3 py-3">No. Referensi</th>
                            <th scope="col" class="px-3 py-3">Customer</th>
                            <th scope="col" class="px-3 py-3">Produk</th>
                            <th scope="col" class="px-3 py-3 text-right">Qty</th>
                            <th scope="col" class="px-3 py-3 text-right">Harga · Disc</th>
                            <th scope="col" class="px-3 py-3 text-right">Total (retur)</th>
                            <th scope="col" class="px-3 py-3">Status Kirim</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $r)
                            <tr class="align-top hover:bg-gray-50/70">
                                <td class="px-3 py-3 whitespace-nowrap text-gray-800 tabular-nums">{{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('d/m/y') : '-' }}</td>
                                <td class="px-3 py-3 text-xs leading-5 whitespace-nowrap">
                                    <span class="font-semibold text-gray-900">{{ $r['invoice_number'] ?? '-' }}</span>
                                    <span class="block text-gray-600">{{ $r['so_number'] ?? '-' }}</span>
                                    @if ($r['tax_invoice_number'])
                                        <span class="block text-gray-600">e-Faktur {{ $r['tax_invoice_number'] }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 min-w-36">
                                    <span class="text-gray-900">{{ $r['customer'] }}</span>
                                    <span class="block text-xs text-gray-600">{{ $r['branch'] }}</span>
                                </td>
                                <td class="px-3 py-3 min-w-40">
                                    <span class="text-gray-900">{{ $r['product'] }}</span>
                                    <span class="block text-xs text-gray-600 font-mono">{{ $r['sku'] }}</span>
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums font-semibold text-gray-900">{{ $num($r['qty']) }}</td>
                                <td class="px-3 py-3 text-right tabular-nums text-gray-800 whitespace-nowrap">{{ $rp($r['price']) }}
                                    @if ($r['discount_pct'] > 0)<span class="block text-xs text-gray-600">disc {{ $num($r['discount_pct']) }}%</span>@endif
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ $rp($r['total']) }}
                                    @if ($r['return_qty'] > 0)
                                        <span class="block text-xs font-semibold text-amber-800">retur {{ $num($r['return_qty']) }} · {{ $rp($r['return_total']) }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex px-2 py-0.5 rounded-md border text-xs font-semibold whitespace-nowrap {{ $badge[$r['delivery']['key']] }}">{{ $r['delivery']['label'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-gray-700">
                <span>Baris {{ $page->firstItem() }}–{{ $page->lastItem() }} dari {{ number_format($page->total(), 0, ',', '.') }}</span>
                {{ $page->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
