@extends('layouts.app', ['title' => 'Riwayat Transaksi Kasir POS'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('kasir.pos.index') }}" class="hover:text-emerald-700">Kasir POS</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Riwayat Transaksi</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Riwayat Transaksi POS Ritel</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Daftar seluruh transaksi kasir ritel, status pembayaran, dan cetak ulang struk belanja.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('kasir.pos.index') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2" stroke-width="1.8"></rect>
                    <line x1="8" y1="21" x2="16" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                    <line x1="12" y1="17" x2="12" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                </svg>
                <span>Masuk Terminal Kasir</span>
            </a>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-4 sm:p-5 shadow-sm">
        <form action="{{ route('kasir.pos.history') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6">
                <label class="block text-[10.5px] font-bold text-gray-400 uppercase mb-1">Cari Transaksi</label>
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nomor struk POS atau nama pelanggan..."
                    class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
            </div>

            <div class="sm:col-span-4">
                <label class="block text-[10.5px] font-bold text-gray-400 uppercase mb-1">Tanggal Transaksi</label>
                <input
                    type="date"
                    name="date"
                    value="{{ $date }}"
                    class="w-full px-3 py-2 text-xs border border-gray-300 rounded-xl focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                >
            </div>

            <div class="sm:col-span-2 flex items-end gap-1.5">
                <button
                    type="submit"
                    class="w-full py-2 px-3 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold transition shadow-xs text-center"
                >
                    Filter
                </button>
                @if($search || $date)
                    <a
                        href="{{ route('kasir.pos.history') }}"
                        class="p-2 border border-gray-300 rounded-xl hover:bg-gray-50 text-gray-500 text-xs font-bold"
                        title="Reset Filter"
                    >
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Transactions Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">No. Transaksi</th>
                        <th class="py-3 px-4">Tanggal & Waktu</th>
                        <th class="py-3 px-4">Kasir</th>
                        <th class="py-3 px-4">Pelanggan</th>
                        <th class="py-3 px-4">Gudang</th>
                        <th class="py-3 px-4 text-center">Metode</th>
                        <th class="py-3 px-4 text-right">Total Transaksi</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                {{ $order->order_number }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $order->order_date ? $order->order_date->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ $order->cashier?->name ?? 'Kasir' }}
                            </td>
                            <td class="py-3 px-4 font-medium text-gray-900">
                                {{ $order->customer_name }}
                            </td>
                            <td class="py-3 px-4 text-gray-500 text-[11px]">
                                {{ $order->warehouse?->name ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="uppercase px-2 py-0.5 rounded text-[10px] font-bold {{ $order->payment_method === 'cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $order->payment_method }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-extrabold text-gray-900">
                                Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @php
                                    $st = strtolower((string)$order->status);
                                    $badgeClass = match($st) {
                                        'completed', 'paid', 'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'pending', 'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'cancelled', 'batal' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default => 'bg-gray-50 text-gray-700 border-gray-200',
                                    };
                                    $dotClass = match($st) {
                                        'completed', 'paid', 'selesai' => 'bg-emerald-500',
                                        'pending', 'draft' => 'bg-amber-500',
                                        'cancelled', 'batal' => 'bg-rose-500',
                                        default => 'bg-gray-400',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a
                                    href="{{ route('kasir.pos.receipt', $order) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition"
                                >
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                        <rect x="6" y="14" width="12" height="8"></rect>
                                    </svg>
                                    <span>Struk</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                Belum ada riwayat transaksi POS yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
