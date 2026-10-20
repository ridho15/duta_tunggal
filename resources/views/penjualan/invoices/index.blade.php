@extends('layouts.app', ['title' => 'Invoice Penjualan'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Invoice Penjualan</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Invoice Penjualan</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Tagihan resmi penagihan piutang customer dari Surat Jalan yang telah diterbitkan.
            </p>
        </div>

        <div>
            <a
                href="{{ route('penjualan.invoices.create') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-sm shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Buat Faktur Penjualan</span>
            </a>
        </div>
    </div>

    {{-- Piutang KPI Card --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <span class="text-xs font-semibold text-gray-400 block uppercase tracking-wider">Total Piutang Belum Tertagih</span>
            <div class="text-2xl font-black text-gray-900 mt-1">
                Rp {{ number_format($totalUnpaidAmount, 0, ',', '.') }}
            </div>
            <span class="text-xs text-gray-500 mt-0.5 block">
                Dari {{ $counts['unpaid'] }} faktur aktif yang menunggu pembayaran customer.
            </span>
        </div>

        <div class="flex items-center gap-3">
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs">
                <span class="text-gray-400 block text-[11px]">Jatuh Tempo:</span>
                <span class="font-extrabold text-amber-900 text-sm block">{{ $counts['overdue'] }} Faktur</span>
            </div>
            <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs">
                <span class="text-gray-400 block text-[11px]">Sudah Lunas:</span>
                <span class="font-extrabold text-emerald-900 text-sm block">{{ $counts['paid'] }} Faktur</span>
            </div>
        </div>
    </div>

    {{-- Quick Status Filter Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs font-semibold">
        @php
            $tabClass = function(bool $active) {
                return $active
                    ? 'px-3.5 py-1.5 rounded-full bg-[#15803d] text-white flex items-center gap-2 shadow-sm'
                    : 'px-3.5 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition';
            };
        @endphp

        <a href="{{ route('penjualan.invoices.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.invoices.index', ['tab' => 'unpaid', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'unpaid') }}">
            <span>Menunggu Pembayaran</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'unpaid' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $counts['unpaid'] }}</span>
        </a>

        <a href="{{ route('penjualan.invoices.index', ['tab' => 'overdue', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'overdue') }}">
            <span>Jatuh Tempo</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'overdue' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-800' }}">{{ $counts['overdue'] }}</span>
        </a>

        <a href="{{ route('penjualan.invoices.index', ['tab' => 'paid', 'q' => $search, 'cabang_id' => $cabangId]) }}"
           class="{{ $tabClass($tab === 'paid') }}">
            <span>Lunas</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'paid' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['paid'] }}</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-3 shadow-xs">
        <form method="GET" action="{{ route('penjualan.invoices.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" stroke-width="2"></circle>
                        <path d="m21 21-4.35-4.35" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari nomor faktur atau nama customer..."
                    class="w-full pl-10 pr-3.5 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] placeholder-gray-400"
                >
            </div>

            <div class="sm:w-56">
                <select
                    name="cabang_id"
                    onchange="this.form.submit()"
                    class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm font-medium text-gray-700 bg-white focus:outline-none focus:border-[#15803d]"
                >
                    <option value="">Semua Cabang</option>
                    @foreach($cabangs as $c)
                        <option value="{{ $c->id }}" {{ $cabangId == $c->id ? 'selected' : '' }}>
                            {{ $c->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition">
                Cari
            </button>
        </form>
    </div>

    {{-- Desktop Table View --}}
    <div class="hidden md:block bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3.5">No. Faktur</th>
                        <th class="px-4 py-3.5">Customer & Cabang</th>
                        <th class="px-4 py-3.5">Tanggal & Jatuh Tempo</th>
                        <th class="px-4 py-3.5 text-right">Total Tagihan</th>
                        <th class="px-4 py-3.5">Status</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($invoices as $inv)
                        @php
                            $isOverdue = $inv->status !== \App\Models\Invoice::STATUS_PAID && $inv->due_date && $inv->due_date < today();
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition group">
                            {{-- No Faktur --}}
                            <td class="px-5 py-4">
                                <a href="{{ route('penjualan.invoices.show', $inv->id) }}" class="font-extrabold text-gray-900 hover:text-[#15803d] text-[15px] block">
                                    {{ $inv->invoice_number }}
                                </a>
                                <span class="text-xs text-gray-400 block mt-0.5">
                                    {{ count($inv->surat_jalan_ids ?? []) }} Surat Jalan
                                </span>
                            </td>

                            {{-- Customer --}}
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-800">
                                    {{ $inv->customer_name ?? 'Customer Umum' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    Cabang: <span class="font-medium text-gray-600">{{ $inv->cabang->nama ?? 'Pusat' }}</span>
                                </div>
                            </td>

                            {{-- Tanggal & Jatuh Tempo --}}
                            <td class="px-4 py-4">
                                <div class="text-xs font-medium text-gray-800">
                                    {{ $inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') : '-' }}
                                </div>
                                <div class="text-[11px] {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-400' }} mt-0.5">
                                    Tempo: {{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d M Y') : '-' }}
                                    @if($isOverdue) (Terlambat) @endif
                                </div>
                            </td>

                            {{-- Total Tagihan --}}
                            <td class="px-4 py-4 text-right">
                                <span class="font-black text-gray-900 text-base">
                                    Rp {{ number_format((float)$inv->total, 0, ',', '.') }}
                                </span>
                                @if((float)$inv->tax > 0)
                                    <span class="block text-[11px] text-gray-400">
                                        Termasuk PPN: Rp {{ number_format((float)$inv->tax, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-4">
                                @if($inv->status === \App\Models\Invoice::STATUS_PAID)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Lunas
                                    </span>
                                @elseif($isOverdue)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-1">
                                        Terlambat
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                                        Menunggu Bayar
                                    </span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <a
                                        href="{{ route('penjualan.invoices.pdf', $inv->id) }}"
                                        target="_blank"
                                        title="Cetak Faktur (PDF)"
                                        class="p-1.5 text-gray-400 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                                        </svg>
                                    </a>

                                    <a
                                        href="{{ route('penjualan.invoices.show', $inv->id) }}"
                                        class="text-xs font-bold text-[#15803d] hover:text-[#14532d] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg px-3 py-1.5 transition"
                                    >
                                        Detail
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-gray-400 text-sm">
                                Tidak ada data Faktur Penjualan ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    {{-- Mobile Cards --}}
    <div class="md:hidden space-y-3">
        @forelse($invoices as $inv)
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-xs space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <a href="{{ route('penjualan.invoices.show', $inv->id) }}" class="font-extrabold text-gray-900 text-base">
                            {{ $inv->invoice_number }}
                        </a>
                        <div class="text-xs text-gray-500 font-medium mt-0.5">
                            {{ $inv->customer_name ?? 'Customer Umum' }}
                        </div>
                    </div>
                    @if($inv->status === \App\Models\Invoice::STATUS_PAID)
                        <span class="text-[10px] font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-md px-2 py-0.5">
                            Lunas
                        </span>
                    @elseif($inv->due_date && $inv->due_date < today())
                        <span class="text-[10px] font-bold text-red-700 bg-red-50 border border-red-200 rounded-md px-2 py-0.5">
                            Terlambat
                        </span>
                    @else
                        <span class="text-[10px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-md px-2 py-0.5">
                            Menunggu Bayar
                        </span>
                    @endif
                </div>

                <div class="flex items-center justify-between pt-1">
                    <span class="text-xs text-gray-400">Total Tagihan:</span>
                    <span class="font-black text-gray-900 text-base">
                        Rp {{ number_format((float)$inv->total, 0, ',', '.') }}
                    </span>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <a
                        href="{{ route('penjualan.invoices.pdf', $inv->id) }}"
                        target="_blank"
                        class="text-xs font-bold text-gray-600 hover:text-gray-900 flex items-center gap-1.5"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                        </svg>
                        <span>Cetak PDF</span>
                    </a>

                    <a
                        href="{{ route('penjualan.invoices.show', $inv->id) }}"
                        class="text-xs font-bold text-[#15803d] bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-xl px-4 py-2 transition"
                    >
                        Lihat Detail
                    </a>
                </div>
            </div>
        @empty
            <div class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-gray-400 text-sm">
                Tidak ada data Faktur Penjualan.
            </div>
        @endforelse

        @if($invoices->hasPages())
            <div class="pt-2">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
