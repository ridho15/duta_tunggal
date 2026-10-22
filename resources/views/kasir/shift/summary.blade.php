@extends('layouts.app', ['title' => 'Laporan Penutupan Shift Kasir: ' . $shift->shift_number])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('kasir.pos.index') }}" class="hover:text-emerald-700">Kasir POS</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Rekap Shift Kasir (Z-Report)</span>
            </nav>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Rekapitulasi Shift {{ $shift->shift_number }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $shift->status === 'closed' ? 'bg-gray-100 text-gray-800 border border-gray-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-200' }}">
                    {{ $shift->status === 'closed' ? 'Shift Ditutup' : 'Shift Masih Aktif' }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Laporan penutupan kasir (Z-Report), rekapitulasi penjualan per metode, dan verifikasi fisik uang laci.
            </p>
        </div>

        <div class="flex items-center gap-2 print:hidden">
            <a
                href="{{ route('kasir.pos.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold transition shadow-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2" stroke-width="1.8"></rect>
                    <line x1="8" y1="21" x2="16" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                    <line x1="12" y1="17" x2="12" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                </svg>
                <span>Terminal Kasir</span>
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Cetak Rekap Z-Report</span>
            </button>
        </div>
    </div>

    {{-- Meta Shift Information Card --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
        <div>
            <div class="text-[10.5px] font-bold text-gray-400 uppercase">Kasir</div>
            <div class="text-sm font-extrabold text-gray-900 mt-0.5">{{ $shift->user?->name ?? 'Kasir' }}</div>
            <div class="text-gray-500">{{ $shift->cabang?->nama ?? 'Pusat' }}</div>
        </div>
        <div>
            <div class="text-[10.5px] font-bold text-gray-400 uppercase">Gudang Transaksi</div>
            <div class="text-sm font-extrabold text-gray-900 mt-0.5">{{ $shift->warehouse?->name ?? 'Gudang Utama' }}</div>
            <div class="text-gray-500">{{ $shift->warehouse?->kode ?? '-' }}</div>
        </div>
        <div>
            <div class="text-[10.5px] font-bold text-gray-400 uppercase">Waktu Buka Shift</div>
            <div class="text-sm font-extrabold text-gray-900 mt-0.5">{{ $shift->opened_at ? $shift->opened_at->format('d M Y, H:i') : '-' }}</div>
            <div class="text-gray-500">Mulai operasional</div>
        </div>
        <div>
            <div class="text-[10.5px] font-bold text-gray-400 uppercase">Waktu Tutup Shift</div>
            <div class="text-sm font-extrabold text-gray-900 mt-0.5">{{ $shift->closed_at ? $shift->closed_at->format('d M Y, H:i') : 'Masih Buka' }}</div>
            <div class="text-gray-500">Penutupan laci</div>
        </div>
    </div>

    {{-- Cash Reconciliation (Rekonsiliasi Uang Fisik di Laci) --}}
    <div class="bg-white border-2 {{ ($shift->cash_difference ?? 0) == 0 ? 'border-emerald-200 bg-emerald-50/20' : (($shift->cash_difference ?? 0) < 0 ? 'border-rose-300 bg-rose-50/20' : 'border-blue-200 bg-blue-50/20') }} rounded-2xl p-5 shadow-xs">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-extrabold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="6" width="20" height="12" rx="2" stroke-width="1.8"></rect>
                    <circle cx="12" cy="12" r="2" stroke-width="1.8"></circle>
                </svg>
                <span>Rekonsiliasi Kas Laci Kasir (Cash Count)</span>
            </h2>
            @if($shift->status === 'closed')
                @if(($shift->cash_difference ?? 0) == 0)
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        ✓ Fisik Kas Cocok (Seimbang)
                    </span>
                @elseif(($shift->cash_difference ?? 0) < 0)
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-rose-100 text-rose-800 border border-rose-300">
                        ⚠ Fisik Kas Kurang Rp {{ number_format(abs($shift->cash_difference), 0, ',', '.') }}
                    </span>
                @else
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-800 border border-blue-300">
                        ℹ Fisik Kas Berlebih + Rp {{ number_format($shift->cash_difference, 0, ',', '.') }}
                    </span>
                @endif
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-xs">
                <div class="text-[10.5px] font-bold text-gray-400 uppercase">Modal Awal (Starting)</div>
                <div class="text-lg font-black text-gray-900 mt-1">
                    Rp {{ number_format($summary['starting_cash'], 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-gray-400 mt-0.5">Kas awal saat buka shift</div>
            </div>

            <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-xs">
                <div class="text-[10.5px] font-bold text-gray-400 uppercase">Penjualan Tunai</div>
                <div class="text-lg font-black text-emerald-700 mt-1">
                    Rp {{ number_format($summary['payment_methods']['cash']['total'] ?? 0, 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-gray-400 mt-0.5">{{ $summary['payment_methods']['cash']['count'] ?? 0 }} transaksi tunai</div>
            </div>

            <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-xs">
                <div class="text-[10.5px] font-bold text-gray-400 uppercase">Kas Seharusnya di Laci</div>
                <div class="text-lg font-black text-gray-900 mt-1">
                    Rp {{ number_format($summary['expected_cash'], 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-gray-400 mt-0.5">Modal awal + penjualan tunai</div>
            </div>

            <div class="p-3 bg-white rounded-xl border border-gray-200 shadow-xs">
                <div class="text-[10.5px] font-bold text-gray-400 uppercase">Uang Fisik Aktual di Laci</div>
                <div class="text-lg font-black {{ ($shift->cash_difference ?? 0) < 0 ? 'text-rose-700' : 'text-gray-900' }} mt-1">
                    Rp {{ number_format($summary['cash_actual'] ?? $summary['expected_cash'], 0, ',', '.') }}
                </div>
                <div class="text-[10px] text-gray-400 mt-0.5">Hasil hitung fisik kasir</div>
            </div>
        </div>

        @if($shift->notes)
            <div class="mt-3 pt-3 border-t border-gray-200/80 text-xs text-gray-600">
                <strong>Catatan Kasir:</strong> {{ $shift->notes }}
            </div>
        @endif
    </div>

    {{-- Breakdown Payment Methods --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach($summary['payment_methods'] as $code => $pm)
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
                <div class="flex items-center justify-between text-[11px] font-bold text-gray-500 uppercase">
                    <span>{{ $pm['label'] }}</span>
                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-[10px]">
                        {{ $pm['count'] }} trx
                    </span>
                </div>
                <div class="text-xl font-extrabold text-gray-900 mt-2">
                    Rp {{ number_format($pm['total'], 0, ',', '.') }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Transactions In Shift Table --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-extrabold text-gray-900 uppercase tracking-tight">Rincian Transaksi Shift Ini</h3>
                <span class="text-xs text-gray-400">Total {{ $summary['total_transactions'] }} transaksi senilai Rp {{ number_format($summary['total_sales'], 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider text-[10.5px]">
                    <tr>
                        <th class="py-3 px-4">No. Transaksi</th>
                        <th class="py-3 px-4">Jam</th>
                        <th class="py-3 px-4">Pelanggan</th>
                        <th class="py-3 px-4 text-center">Metode</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                        <th class="py-3 px-4 text-right">Diskon</th>
                        <th class="py-3 px-4 text-right">Grand Total</th>
                        <th class="py-3 px-4 text-center">Struk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($summary['orders'] as $order)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 font-mono font-bold text-gray-900">
                                {{ $order->order_number }}
                            </td>
                            <td class="py-3 px-4 text-gray-500">
                                {{ $order->order_date ? $order->order_date->format('H:i:s') : '-' }}
                            </td>
                            <td class="py-3 px-4 font-medium text-gray-800">
                                {{ $order->customer_name }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="uppercase px-2 py-0.5 rounded text-[10px] font-bold {{ $order->payment_method === 'cash' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $order->payment_method }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right text-gray-600">
                                Rp {{ number_format($order->subtotal, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right text-rose-600">
                                {{ $order->discount_amount > 0 ? '- Rp ' . number_format($order->discount_amount, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-extrabold text-gray-900">
                                Rp {{ number_format($order->grand_total, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a
                                    href="{{ route('kasir.pos.receipt', $order) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-xs font-bold transition"
                                >
                                    Lihat Struk
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-gray-400">
                                Tidak ada transaksi yang tercatat pada shift ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
