@extends('layouts.app')

@section('title', 'Cetak Banyak Produk · Duta Tunggal ERP')

@php
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $withValue = $filters['with_value'];
    $field = 'mt-1 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none';
@endphp

@section('content')
<div class="space-y-5">
    @include('laporan.pusat._head', [
        'title' => 'Cetak Banyak Produk',
        'question' => 'Daftar stok per produk per gudang untuk rentang kode produk. Pilih filter, cek hasilnya di bawah, lalu Cetak PDF' . (\App\Support\Access::canExportImport() ? ' atau Unduh Excel.' : '.'),
        'pdfRoute' => 'laporan.cetak-produk.pdf',
        'excelRoute' => 'laporan.cetak-produk.excel',
    ])

    <form method="GET" class="bg-white rounded-2xl border border-gray-200 p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end" role="search">
        <label class="text-xs font-semibold text-gray-700">Mulai dari kode produk
            <input type="text" name="sku_from" value="{{ $filters['sku_from'] }}" placeholder="Contoh: ASCR000001" class="{{ $field }} font-mono">
        </label>
        <label class="text-xs font-semibold text-gray-700">Sampai kode produk
            <input type="text" name="sku_to" value="{{ $filters['sku_to'] }}" placeholder="Kosongkan = sampai akhir" class="{{ $field }} font-mono">
        </label>
        <label class="text-xs font-semibold text-gray-700">Cabang
            <select name="cabang_id" class="cursor-pointer {{ $field }}">
                <option value="">Semua cabang</option>
                @foreach ($branches as $b)
                    <option value="{{ $b->id }}" @selected((string) $filters['cabang_id'] === (string) $b->id)>{{ $b->nama }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-700">Tempat penyimpanan (gudang)
            <select name="warehouse_id" class="cursor-pointer {{ $field }}">
                <option value="">Semua gudang</option>
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}" @selected((string) $filters['warehouse_id'] === (string) $w->id)>{{ $w->kode }} · {{ $w->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-700">Tipe pajak produk
            <select name="tipe_pajak" class="cursor-pointer {{ $field }}">
                <option value="">Semua tipe pajak</option>
                @foreach (['Non Pajak', 'Inklusif', 'Eksklusif'] as $t)
                    <option value="{{ $t }}" @selected($filters['tipe_pajak'] === $t)>{{ $t }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-gray-700">Pilihan cetak
            <select name="with_value" class="cursor-pointer {{ $field }}" @disabled(! $canValue)>
                <option value="0">Tanpa nilai (qty saja)</option>
                @if ($canValue)
                    <option value="1" @selected($withValue)>Dengan nilai (harga pokok)</option>
                @endif
            </select>
            @unless ($canValue)
                <span class="block mt-1 font-normal text-gray-600">Nilai harga pokok khusus yang dicentang "Lihat Nilai".</span>
            @endunless
        </label>
        <label class="text-xs font-semibold text-gray-700">Qty kosong dimasukkan?
            <select name="include_zero" class="cursor-pointer {{ $field }}">
                <option value="0">Tidak</option>
                <option value="1" @selected($filters['include_zero'])>Ya</option>
            </select>
        </label>
        <div class="flex gap-2">
            <button type="submit" class="cursor-pointer flex-1 min-h-10 px-4 py-2 rounded-xl bg-gray-900 text-white text-sm font-bold hover:bg-black active:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-500">Tampilkan</button>
            <a href="{{ route('laporan.cetak-produk.index') }}" class="min-h-10 inline-flex items-center whitespace-nowrap px-3 py-2 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-100">Reset</a>
        </div>
        @if ($errors->any())
            <p class="sm:col-span-2 lg:col-span-4 text-xs font-semibold text-red-700" role="alert">{{ $errors->first() }}</p>
        @endif
    </form>

    @include('laporan.pusat._kpis', ['kpis' => array_values(array_filter([
        ['Total qty', $num($summary['qty']), 'di ' . number_format($summary['lines'], 0, ',', '.') . ' baris produk-gudang', null],
        ['Jenis produk', number_format($summary['products'], 0, ',', '.'), null, null],
        $withValue ? ['Nilai persediaan', $rp($summary['value']), 'qty x harga pokok', null] : null,
    ]))])

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($page->isEmpty())
            <div class="p-10 text-center text-sm text-gray-700">
                Tidak ada produk yang cocok.
                @unless ($filters['include_zero'])
                    Produk dengan qty 0 tidak ditampilkan;
                    <a href="{{ route('laporan.cetak-produk.index', array_merge(request()->query(), ['include_zero' => 1])) }}" class="font-semibold text-[#15803d] underline underline-offset-2">sertakan qty kosong</a>.
                @endunless
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3">Kode Produk</th>
                            <th scope="col" class="px-4 py-3">Produk</th>
                            <th scope="col" class="px-4 py-3">Gudang</th>
                            <th scope="col" class="px-4 py-3 text-right">Qty</th>
                            <th scope="col" class="px-4 py-3">Satuan</th>
                            @if ($withValue)
                                <th scope="col" class="px-4 py-3 text-right">Harga Pokok</th>
                                <th scope="col" class="px-4 py-3 text-right">Nilai</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($page as $r)
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-4 py-2.5 font-mono text-xs text-gray-800 whitespace-nowrap">{{ $r->sku }}</td>
                                <td class="px-4 py-2.5 text-gray-900">{{ $r->name }}
                                    @if ($r->tipe_pajak)<span class="block text-xs text-gray-600">{{ $r->tipe_pajak }}</span>@endif
                                </td>
                                <td class="px-4 py-2.5 text-gray-800 whitespace-nowrap" title="{{ $r->warehouse }}">{{ $r->warehouse_code }}<span class="block text-xs text-gray-600">{{ $r->branch }}</span></td>
                                <td class="px-4 py-2.5 text-right tabular-nums font-semibold {{ (float) $r->qty <= 0 ? 'text-red-700' : 'text-gray-900' }}">{{ $num($r->qty) }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ $r->uom ?? '-' }}</td>
                                @if ($withValue)
                                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-800 whitespace-nowrap">{{ $rp($r->cost_price) }}</td>
                                    <td class="px-4 py-2.5 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ $rp((float) $r->qty * (float) $r->cost_price) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-gray-700">
                <span>Baris {{ $page->firstItem() }}–{{ $page->lastItem() }} dari {{ number_format($page->total(), 0, ',', '.') }}, urut kode produk</span>
                {{ $page->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
