@extends('layouts.app')

@section('title', 'Penyesuaian Stok – Duta Tunggal ERP')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Gudang</div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Penyesuaian Stok</h1>
            <p class="text-sm text-gray-500 mt-0.5">Koreksi qty stok per produk. Stok dan jurnal baru berubah setelah penyesuaian disetujui.</p>
        </div>
        <a href="{{ route('gudang.adjustment.create') }}" class="px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition whitespace-nowrap">Buat Penyesuaian</a>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label for="status" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Status</label>
            <select id="status" name="status" onchange="this.form.submit()" class="px-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] outline-none">
                <option value="">Semua</option>
                @foreach ($statuses as $value => $text)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $text }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="search" class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">Nomor</label>
            <input id="search" type="search" name="search" value="{{ $search }}" placeholder="ADJ-..." class="px-3 py-2 text-xs sm:text-sm border border-gray-200 rounded-xl focus:ring-1 focus:ring-[#15803d] outline-none">
        </div>
        <button type="submit" class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition">Cari</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        @if ($adjustments->isEmpty())
            <div class="p-10 text-center">
                <p class="text-sm font-semibold text-gray-700">{{ $status || $search ? 'Tidak ada penyesuaian yang cocok.' : 'Belum ada penyesuaian stok.' }}</p>
                @unless ($status || $search)
                    <p class="text-xs text-gray-500 mt-1">Buat penyesuaian untuk mengoreksi stok yang selisih dari hitungan fisik.</p>
                @endunless
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px]">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">Nomor</th>
                            <th scope="col" class="px-4 py-3 font-bold">Tanggal</th>
                            <th scope="col" class="px-4 py-3 font-bold">Gudang</th>
                            <th scope="col" class="px-4 py-3 font-bold">Tipe</th>
                            <th scope="col" class="px-4 py-3 font-bold">Alasan</th>
                            <th scope="col" class="px-4 py-3 font-bold text-right">Item</th>
                            <th scope="col" class="px-4 py-3 font-bold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($adjustments as $adjustment)
                            <tr class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-semibold whitespace-nowrap"><a href="{{ route('gudang.adjustment.show', $adjustment) }}" class="text-[#15803d] hover:underline">{{ $adjustment->adjustment_number }}</a></td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $adjustment->adjustment_date?->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $adjustment->warehouse->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold {{ $adjustment->adjustment_type === 'increase' ? 'text-[#15803d]' : 'text-rose-600' }}">{{ $adjustment->adjustment_type === 'increase' ? 'Penambahan (+)' : 'Pengurangan (-)' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $adjustment->reason }}</td>
                                <td class="px-4 py-3 text-right text-gray-700">{{ $adjustment->items_count }}</td>
                                <td class="px-4 py-3 whitespace-nowrap font-semibold {{ ['approved' => 'text-[#15803d]', 'rejected' => 'text-rose-600'][$adjustment->status] ?? 'text-gray-600' }}">{{ $statuses[$adjustment->status] ?? $adjustment->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-100">{{ $adjustments->links() }}</div>
        @endif
    </div>
</div>
@endsection
