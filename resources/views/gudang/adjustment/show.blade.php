@extends('layouts.app')

@section('title', $adjustment->adjustment_number.' – Duta Tunggal ERP')

@section('content')
@php
    $statusText = ['draft' => 'Draft', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$adjustment->status] ?? $adjustment->status;
    $statusClass = ['approved' => 'text-[#15803d]', 'rejected' => 'text-rose-600'][$adjustment->status] ?? 'text-gray-600';
    $isIncrease = $adjustment->adjustment_type === 'increase';
    $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('gudang.adjustment.index') }}" class="hover:text-gray-700">Penyesuaian Stok</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Detail</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $adjustment->adjustment_number }}</h1>
            <p class="text-sm mt-0.5">Status: <span class="font-bold {{ $statusClass }}">{{ $statusText }}</span></p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($adjustment->status === 'draft')
                <a href="{{ route('gudang.adjustment.edit', $adjustment) }}" class="px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl transition">Ubah</a>
                <form method="POST" action="{{ route('gudang.adjustment.approve', $adjustment) }}" onsubmit="return confirm('Setujui penyesuaian ini? Stok dan jurnal akan langsung berubah dan tidak dapat dibatalkan dari layar ini.')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition">Setujui</button>
                </form>
                <form method="POST" action="{{ route('gudang.adjustment.reject', $adjustment) }}" onsubmit="return confirm('Tolak penyesuaian ini? Stok tidak akan berubah.')">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-700 text-xs sm:text-sm font-semibold rounded-xl transition">Tolak</button>
                </form>
            @endif
            @if (in_array($adjustment->status, ['draft', 'rejected'], true))
                <form method="POST" action="{{ route('gudang.adjustment.destroy', $adjustment) }}" onsubmit="return confirm('Hapus penyesuaian ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 text-rose-600 hover:bg-rose-50 text-xs sm:text-sm font-semibold rounded-xl transition">Hapus</button>
                </form>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div role="alert" class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm">
            <ul class="list-disc list-inside text-xs space-y-0.5">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 text-sm">
        <div><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Tanggal</dt><dd class="font-semibold text-gray-800">{{ $adjustment->adjustment_date?->format('d/m/Y') }}</dd></div>
        <div><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Gudang</dt><dd class="font-semibold text-gray-800">{{ $adjustment->warehouse->name }}</dd></div>
        <div><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Tipe</dt><dd class="font-semibold {{ $isIncrease ? 'text-[#15803d]' : 'text-rose-600' }}">{{ $isIncrease ? 'Penambahan (+)' : 'Pengurangan (-)' }}</dd></div>
        <div><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Dibuat oleh</dt><dd class="font-semibold text-gray-800">{{ $adjustment->creator->name ?? '-' }}</dd></div>
        <div class="col-span-2 sm:col-span-4"><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Alasan</dt><dd class="text-gray-800">{{ $adjustment->reason }}</dd></div>
        @if ($adjustment->notes)
            <div class="col-span-2 sm:col-span-4"><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">Catatan</dt><dd class="text-gray-800 whitespace-pre-line">{{ $adjustment->notes }}</dd></div>
        @endif
        @if ($adjustment->approved_at)
            <div class="col-span-2 sm:col-span-4"><dt class="text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $adjustment->status === 'approved' ? 'Disetujui' : 'Ditolak' }} oleh</dt><dd class="text-gray-800">{{ $adjustment->approver->name ?? '-' }}, {{ $adjustment->approved_at->format('d/m/Y H:i') }}</dd></div>
        @endif
    </dl>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[11px]">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold">Produk</th>
                        <th scope="col" class="px-4 py-3 font-bold">Rak</th>
                        <th scope="col" class="px-4 py-3 font-bold text-right">Qty saat ini</th>
                        <th scope="col" class="px-4 py-3 font-bold text-right">Qty setelah</th>
                        <th scope="col" class="px-4 py-3 font-bold text-right">Selisih</th>
                        <th scope="col" class="px-4 py-3 font-bold text-right">Harga satuan</th>
                        <th scope="col" class="px-4 py-3 font-bold text-right">Nilai selisih</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($adjustment->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-800">{{ $item->product->name }}@if ($item->product->sku)<span class="block text-[11px] text-gray-500">{{ $item->product->sku }}</span>@endif @if ($item->notes)<span class="block text-[11px] text-gray-500">{{ $item->notes }}</span>@endif</td>
                            <td class="px-4 py-3 text-gray-700">{{ $item->rak_id ? $item->rak->name : 'Seluruh gudang' }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $fmt($item->current_qty) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ $fmt($item->adjusted_qty) }}</td>
                            <td class="px-4 py-3 text-right font-semibold {{ $item->difference_qty > 0 ? 'text-[#15803d]' : 'text-rose-600' }}">{{ $item->difference_qty > 0 ? '+' : '' }}{{ $fmt($item->difference_qty) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ \App\Helpers\MoneyHelper::rupiah($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right text-gray-700">{{ \App\Helpers\MoneyHelper::rupiah($item->difference_value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
