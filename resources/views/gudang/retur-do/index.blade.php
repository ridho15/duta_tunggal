@extends('layouts.app')

@section('title', 'Persetujuan Retur DO – ERP Duta Tunggal')

@section('content')
<div class="space-y-5" x-data>
    <div>
        <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
            <span>Gudang & Stok</span>
            <span class="text-gray-300">/</span>
            <span class="text-[#15803d] font-bold">Persetujuan Retur DO</span>
        </nav>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Persetujuan Retur DO</h1>
        <p class="text-xs text-gray-500 mt-1">
            Retur by DO yang dicatat checker di <a href="{{ route('gudang.kiriman.index') }}" class="font-semibold text-[#15803d] hover:underline">Siapkan &amp; Kirim</a> (barang kurang / rusak sebelum Surat Jalan).
            Setelah disetujui: barang kurang kembali ke stok gudang asal, barang rusak masuk karantina, dan DO pengganti (…C) dibuat untuk dikirim.
        </p>
    </div>

    <div class="flex items-center gap-2 border-b border-gray-200">
        @foreach (['menunggu' => 'Menunggu Persetujuan', 'riwayat' => 'Riwayat'] as $key => $label)
            <a href="{{ route('gudang.retur-do.index', ['tab' => $key]) }}"
               class="px-3 py-2 text-sm font-semibold border-b-2 -mb-px {{ $tab === $key ? 'border-[#15803d] text-[#14532d]' : 'border-transparent text-gray-500 hover:text-gray-800' }}">
                {{ $label }}
                @if ($key === 'menunggu' && $pendingCount > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold">{{ $pendingCount }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if (! $canApprove && $tab === 'menunggu' && $pendingCount > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
            Anda bisa melihat daftar ini, tetapi yang dapat menyetujui/menolak hanya kepala gudang atau atasan (izin <span class="font-mono">approve return product</span>).
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($returns as $return)
            @php
                $do = $return->fromModel;
                $customer = $do?->salesOrders?->first()?->customer?->name;
                $statusLabel = match ($return->status) {
                    'draft' => ['Menunggu Persetujuan', 'bg-amber-100 text-amber-800 border-amber-200'],
                    'approved' => ['Disetujui', 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                    'rejected' => ['Ditolak', 'bg-rose-100 text-rose-800 border-rose-200'],
                    default => [ucfirst((string) $return->status), 'bg-gray-100 text-gray-700 border-gray-200'],
                };
            @endphp
            <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono font-bold text-gray-900">{{ $return->return_number }}</span>
                            <span class="px-2 py-0.5 rounded-full border text-[11px] font-bold {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            DO asal
                            @if ($do?->id)
                                <a href="{{ route('penjualan.do.show', $do->id) }}" class="font-mono font-semibold text-[#15803d] hover:underline">{{ $do->do_number }}</a>
                            @else
                                <span class="font-mono">-</span>
                            @endif
                            @if ($customer) · {{ $customer }} @endif
                            · Gudang {{ $return->warehouse?->name ?? '-' }}
                            · dicatat {{ $return->created_at?->format('d/m/Y H:i') }}
                        </div>
                    </div>

                    @if ($canApprove && $return->status === 'draft')
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button"
                                    @click="$dispatch('open-modal-reject-do-return', { action: '{{ route('gudang.retur-do.reject', $return) }}', number: '{{ $return->return_number }}' })"
                                    class="px-3.5 py-2 text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100">
                                Tolak
                            </button>
                            <button type="button"
                                    @click="$dispatch('open-modal-approve-do-return', { action: '{{ route('gudang.retur-do.approve', $return) }}', number: '{{ $return->return_number }}' })"
                                    class="px-4 py-2 text-xs font-bold text-white bg-[#15803d] hover:bg-[#166534] rounded-xl">
                                Setujui
                            </button>
                        </div>
                    @endif
                </div>

                <table class="w-full mt-3 text-xs">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-1.5 font-semibold">Barang</th>
                            <th class="py-1.5 font-semibold">Jenis</th>
                            <th class="py-1.5 font-semibold text-right">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($return->returnProductItem as $item)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="py-1.5"><span class="font-mono text-gray-500">{{ $item->product?->sku }}</span> {{ $item->product?->name }}</td>
                                <td class="py-1.5">
                                    @if ($item->condition === 'damage')
                                        <span class="text-rose-700 font-semibold">Rusak / reject → karantina</span>
                                    @else
                                        <span class="text-gray-700 font-semibold">Kurang → kembali ke stok</span>
                                    @endif
                                </td>
                                <td class="py-1.5 text-right font-mono font-bold">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($return->reason)
                    <p class="mt-2 text-[11px] text-gray-500 whitespace-pre-line">{{ $return->reason }}</p>
                @endif
            </div>
        @empty
            <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center text-sm text-gray-500">
                {{ $tab === 'menunggu' ? 'Tidak ada Retur DO yang menunggu persetujuan.' : 'Belum ada riwayat Retur DO.' }}
            </div>
        @endforelse
    </div>

    {{ $returns->links() }}

    <x-confirmation-modal name="approve-do-return" title="Setujui Retur DO" variant="success" confirm-text="Ya, Setujui Retur" cancel-text="Batal" method="POST">
        <p class="text-sm text-gray-600">
            Setujui retur <span class="font-bold font-mono text-gray-900" x-text="data.number"></span>?
            Barang kurang dikembalikan ke stok gudang asal, barang rusak masuk karantina, dan DO pengganti dibuat untuk barang yang tetap dikirim.
        </p>
    </x-confirmation-modal>

    <x-confirmation-modal name="reject-do-return" title="Tolak Retur DO" variant="danger" confirm-text="Ya, Tolak Retur" cancel-text="Batal" method="POST">
        <p class="text-sm text-gray-600">
            Tolak retur <span class="font-bold font-mono text-gray-900" x-text="data.number"></span>? Stok dan DO tidak berubah; DO bisa dicek dan diretur ulang.
        </p>
        <label class="block mt-3 text-xs font-bold text-gray-700">
            Alasan penolakan <span class="text-red-500">*</span>
            <textarea name="reason" rows="2" required minlength="5" maxlength="500"
                      class="mt-1 w-full py-2 px-3 text-xs font-normal bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d]"></textarea>
        </label>
    </x-confirmation-modal>
</div>
@endsection
