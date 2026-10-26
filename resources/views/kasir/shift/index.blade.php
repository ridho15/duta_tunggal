@extends('layouts.app', ['title' => 'Laporan Shift & Penjualan Kasir'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumb & Top Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-200">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-1">
                <a href="{{ route('kasir.pos.index') }}" class="hover:text-gray-700">Kasir POS</a>
                <span>&rsaquo;</span>
                <span class="text-gray-800 font-semibold">Laporan Shift Kasir</span>
            </div>
            <h1 class="text-2xl font-black text-gray-900 tracking-tight">Laporan Shift & Penjualan Kasir</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Rekapitulasi operasional shift kasir, omzet per kasir, dan audit fisik rekonsiliasi kas laci (Z-Report).
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('kasir.pos.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl border border-gray-200 shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2" stroke-width="1.8"></rect>
                    <line x1="8" y1="21" x2="16" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                    <line x1="12" y1="17" x2="12" y2="21" stroke-width="1.8" stroke-linecap="round"></line>
                </svg>
                <span>Terminal Kasir</span>
            </a>

            <a
                href="{{ route('kasir.laporan.pdf', request()->query()) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold rounded-xl border border-gray-200 shadow-sm transition active:scale-[0.98]"
                title="Cetak Laporan PDF (A4 Landscape)"
            >
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>Cetak PDF</span>
            </a>

            <a
                href="{{ route('kasir.laporan.excel', request()->query()) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
                title="Unduh Data ke Spreadsheet Excel (.xlsx)"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Ekspor Excel</span>
            </a>
        </div>
    </div>

    {{-- Ringkasan KPI Cards --}}
    @if (!empty($kpi))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Omzet POS
                </span>
                <span class="text-base sm:text-lg font-black text-[#15803d] font-mono tracking-tight block">
                    Rp {{ number_format($kpi['total_sales'], 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Penjualan Tunai
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    Rp {{ number_format($kpi['total_cash'], 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Non-Tunai (QRIS/EDC)
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    Rp {{ number_format($kpi['total_non_cash'], 0, ',', '.') }}
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Total Transaksi
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($kpi['total_transactions'], 0, ',', '.') }} Struk
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Shift Selesai
                </span>
                <span class="text-base sm:text-lg font-black text-gray-900 font-mono tracking-tight block">
                    {{ number_format($kpi['closed_shifts'], 0, ',', '.') }} Shift
                </span>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block mb-1">
                    Selisih Kas Fisik
                </span>
                <span class="text-base sm:text-lg font-black font-mono tracking-tight block {{ $kpi['total_difference'] < 0 ? 'text-red-600' : 'text-[#15803d]' }}">
                    Rp {{ number_format($kpi['total_difference'], 0, ',', '.') }}
                </span>
            </div>
        </div>
    @endif

    {{-- Filter Toolbar --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
        <form method="GET" action="{{ route('kasir.laporan.index') }}" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                {{-- Tanggal Mulai --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Mulai
                    </label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition font-mono"
                    >
                </div>

                {{-- Tanggal Akhir --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Tanggal Akhir
                    </label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition font-mono"
                    >
                </div>

                {{-- Kasir Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Pilih Kasir
                    </label>
                    <select
                        name="user_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Kasir --</option>
                        @foreach ($cashiers as $c)
                            <option value="{{ $c->id }}" {{ (string)$userId === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Gudang Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Gudang Kasir
                    </label>
                    <select
                        name="warehouse_id"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Gudang --</option>
                        @foreach ($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string)$warehouseId === (string)$w->id ? 'selected' : '' }}>
                                {{ $w->name }} ({{ $w->kode }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Status Filter --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">
                        Status Shift
                    </label>
                    <select
                        name="status"
                        class="w-full text-xs rounded-xl border border-gray-200 px-3 py-2 bg-gray-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Status --</option>
                        <option value="closed" {{ $status === 'closed' ? 'selected' : '' }}>Ditutup (Selesai)</option>
                        <option value="open" {{ $status === 'open' ? 'selected' : '' }}>Masih Aktif (Buka)</option>
                    </select>
                </div>
            </div>

            {{-- Filter Footer --}}
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <a
                    href="{{ route('kasir.laporan.index') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-xl transition"
                    title="Reset Filter"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Reset</span>
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Terapkan Filter</span>
                </button>
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/75 border-b border-gray-200 text-gray-600 font-bold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="px-3.5 py-3">No. Shift</th>
                        <th class="px-3.5 py-3">Kasir</th>
                        <th class="px-3.5 py-3">Gudang</th>
                        <th class="px-3.5 py-3">Waktu Buka / Tutup</th>
                        <th class="px-3.5 py-3 text-right">Modal Awal</th>
                        <th class="px-3.5 py-3 text-right">Penjualan Tunai</th>
                        <th class="px-3.5 py-3 text-right">Non-Tunai</th>
                        <th class="px-3.5 py-3 text-right font-black">Total Omzet</th>
                        <th class="px-3.5 py-3 text-center">Trx</th>
                        <th class="px-3.5 py-3 text-right">Selisih Kas</th>
                        <th class="px-3.5 py-3 text-center">Status</th>
                        <th class="px-3.5 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($shifts as $s)
                        @php
                            $diff = (float) $s->cash_difference;
                            $diffClass = $diff == 0 ? 'text-[#15803d]' : ($diff < 0 ? 'text-red-600' : 'text-blue-600');
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="px-3.5 py-2.5 font-mono font-bold text-gray-900">
                                <a href="{{ route('kasir.shift.summary', $s) }}" class="text-[#15803d] hover:underline">
                                    {{ $s->shift_number }}
                                </a>
                            </td>
                            <td class="px-3.5 py-2.5 font-medium text-gray-900">
                                {{ $s->user?->name ?? 'Kasir' }}
                            </td>
                            <td class="px-3.5 py-2.5 text-gray-600">
                                {{ $s->warehouse?->name ?? '-' }}
                            </td>
                            <td class="px-3.5 py-2.5 font-mono text-[11px] text-gray-600 leading-tight">
                                <div>{{ $s->opened_at ? $s->opened_at->format('d/m/y H:i') : '-' }}</div>
                                <div class="text-gray-400 text-[10px]">{{ $s->closed_at ? $s->closed_at->format('d/m/y H:i') : 'Aktif' }}</div>
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                Rp {{ number_format((float)$s->starting_cash, 0, ',', '.') }}
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                Rp {{ number_format((float)$s->cash_sales_total, 0, ',', '.') }}
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono text-gray-700">
                                Rp {{ number_format((float)$s->non_cash_sales_total, 0, ',', '.') }}
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono font-extrabold text-gray-900">
                                Rp {{ number_format((float)$s->total_sales, 0, ',', '.') }}
                            </td>
                            <td class="px-3.5 py-2.5 text-center font-mono font-semibold text-gray-800">
                                {{ $s->total_transactions }}
                            </td>
                            <td class="px-3.5 py-2.5 text-right font-mono font-bold {{ $diffClass }}">
                                {{ $s->cash_difference !== null ? 'Rp ' . number_format($diff, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-3.5 py-2.5 text-center">
                                @if ($s->status === 'closed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700 border border-gray-200 uppercase tracking-wider">
                                        Ditutup
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-[#15803d] border border-emerald-200 uppercase tracking-wider">
                                        Aktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-3.5 py-2.5 text-center">
                                <a
                                    href="{{ route('kasir.shift.summary', $s) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-white hover:bg-gray-50 text-gray-700 text-[11px] font-bold rounded-lg border border-gray-200 shadow-xs transition"
                                    title="Buka Rekapitulasi Z-Report"
                                >
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Z-Report</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-12 text-gray-400">
                                Tidak ada data shift kasir yang sesuai dengan filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        <div class="px-4 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <span class="text-xs text-gray-500 font-mono">
                Menampilkan {{ $shifts->firstItem() ?? 0 }} - {{ $shifts->lastItem() ?? 0 }} dari {{ $shifts->total() }} shift
            </span>

            <div>
                {{ $shifts->links() }}
            </div>
        </div>
    </div>

</div>
@endsection
