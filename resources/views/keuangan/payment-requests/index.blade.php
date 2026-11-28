@extends('layouts.app')

@section('title', 'Permintaan Bayar · Duta Tunggal ERP')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $tone = [
        'draft' => 'bg-gray-50 text-gray-700 border-gray-300',
        'submitted' => 'bg-amber-50 text-amber-800 border-amber-300',
        'approved' => 'bg-sky-50 text-sky-800 border-sky-300',
        'paid' => 'bg-[#f0fdf4] text-[#14532d] border-[#86efac]',
        'rejected' => 'bg-red-50 text-red-800 border-red-200',
        'cancelled' => 'bg-gray-50 text-gray-600 border-gray-200',
    ];
    $tabs = [
        'menunggu' => 'Menunggu persetujuan', 'disetujui' => 'Siap dibayar', 'dibayar' => 'Sudah dibayar',
        'draf' => 'Draf', 'ditolak' => 'Ditolak / batal', 'semua' => 'Semua',
    ];
@endphp

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <p class="text-xs font-semibold text-gray-600">Keuangan</p>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Permintaan Bayar</h1>
            <p class="mt-1 text-sm text-gray-700 max-w-2xl">
                Untuk pembayaran non-barang dagangan (asuransi, ATK, perbaikan kendaraan, dll). Alur: buat, ajukan, disetujui, dibayar kasir / finance, lalu voucher bertanda tangan bisa dicetak.
                @unless ($reviewer) Anda melihat permintaan milik Anda sendiri. @endunless
            </p>
        </div>
        @accessRoute('keuangan.payment-requests.create')
            <a href="{{ route('keuangan.payment-requests.create') }}" class="min-h-11 inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] text-white text-sm font-bold hover:bg-[#166534] active:bg-[#14532d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-width="2" d="M12 5v14M5 12h14"/></svg>
                Buat permintaan
            </a>
        @endaccessRoute
    </div>

    <nav class="flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="Status">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('keuangan.payment-requests.index', ['tab' => $key, 'q' => $search ?: null]) }}"
               class="whitespace-nowrap px-3 py-2.5 text-sm border-b-2 -mb-px focus:outline-none focus-visible:ring-2 focus-visible:ring-[#15803d]/40 rounded-t {{ $tab === $key ? 'border-[#15803d] text-[#14532d] font-bold' : 'border-transparent text-gray-700 hover:text-gray-900' }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }} <span class="ml-1 text-xs tabular-nums {{ $tab === $key ? 'text-[#15803d]' : 'text-gray-500' }}">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="flex gap-2" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label for="pr-q" class="sr-only">Cari permintaan</label>
        <input id="pr-q" type="search" name="q" value="{{ $search }}" placeholder="No. permintaan, dibayar kepada, keperluan"
               class="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-[#15803d] focus:ring-2 focus:ring-[#15803d]/30 focus:outline-none">
        <button type="submit" class="cursor-pointer min-h-10 px-4 py-2 rounded-xl border border-gray-300 bg-white text-sm font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400">Cari</button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($list->isEmpty())
            <div class="p-10 text-center text-sm text-gray-700">
                Tidak ada permintaan di tab ini{{ $search ? ' yang cocok dengan "' . $search . '"' : '' }}.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-700">
                        <tr>
                            <th scope="col" class="px-4 py-3">Nomor</th>
                            <th scope="col" class="px-4 py-3">Keperluan</th>
                            <th scope="col" class="px-4 py-3">Dibayar kepada</th>
                            <th scope="col" class="px-4 py-3">Diminta oleh</th>
                            <th scope="col" class="px-4 py-3 text-right">Nominal</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($list as $r)
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <a href="{{ route('keuangan.payment-requests.show', $r) }}" class="font-bold text-gray-900 hover:text-[#15803d] hover:underline underline-offset-2">{{ $r->number }}</a>
                                    <span class="block text-xs text-gray-600">{{ $r->request_date?->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-800 max-w-xs">{{ \Illuminate\Support\Str::limit($r->description, 80) }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $r->payee }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $r->requester?->name ?? '-' }}<span class="block text-xs text-gray-600">{{ $r->cabang?->nama }}</span></td>
                                <td class="px-4 py-3 text-right tabular-nums font-semibold text-gray-900 whitespace-nowrap">{{ $rp($r->amount) }}</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-md border text-xs font-semibold whitespace-nowrap {{ $tone[$r->status] ?? '' }}">{{ $r->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($list->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $list->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
