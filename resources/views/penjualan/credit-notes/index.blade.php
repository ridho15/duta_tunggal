@extends('layouts.app')

@section('title', 'Nota Kredit (Credit Note) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d]">Nota Kredit</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Nota Kredit (Credit Note)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Koreksi keuangan resmi atas faktur terbit: pembatalan, retur dana, atau penyesuaian nilai tagihan.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.credit-notes.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat Nota Kredit
            </a>
        </div>
    </div>

    {{-- 4 KPI Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Nota Kredit --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Dokumen</span>
                <span class="p-2 bg-gray-100 rounded-lg text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-gray-900">{{ number_format($kpis['total_count'], 0, ',', '.') }}</span>
                <span class="text-xs text-gray-500 font-medium">dokumen</span>
            </div>
            <div class="mt-2 text-xs font-semibold text-gray-600">
                Rp {{ number_format($kpis['total_amount'], 0, ',', '.') }}
            </div>
        </div>

        {{-- Draf Menunggu Terbit --}}
        <div class="bg-white rounded-2xl border border-amber-100 p-4 shadow-sm hover:border-amber-200 transition bg-gradient-to-br from-amber-50/40 to-white">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-amber-700">Draf Menunggu Terbit</span>
                <span class="p-2 bg-amber-100 rounded-lg text-amber-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-amber-900">{{ number_format($kpis['draft_count'], 0, ',', '.') }}</span>
                <span class="text-xs text-amber-700 font-medium">draf</span>
            </div>
            <div class="mt-2 text-xs font-semibold text-amber-800">
                Rp {{ number_format($kpis['draft_amount'], 0, ',', '.') }}
            </div>
        </div>

        {{-- Diterbitkan (Issued) --}}
        <div class="bg-white rounded-2xl border border-emerald-100 p-4 shadow-sm hover:border-emerald-200 transition bg-gradient-to-br from-emerald-50/40 to-white">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Kredit Diterbitkan</span>
                <span class="p-2 bg-emerald-100 rounded-lg text-emerald-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-black text-emerald-900">{{ number_format($kpis['issued_count'], 0, ',', '.') }}</span>
                <span class="text-xs text-emerald-700 font-medium">terbit final</span>
            </div>
            <div class="mt-2 text-xs font-semibold text-emerald-800">
                Rp {{ number_format($kpis['issued_amount'], 0, ',', '.') }}
            </div>
        </div>

        {{-- Alokasi Kredit Terbit --}}
        <div class="bg-white rounded-2xl border border-blue-100 p-4 shadow-sm hover:border-blue-200 transition bg-gradient-to-br from-blue-50/40 to-white">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-blue-700">Alokasi Finansial</span>
                <span class="p-2 bg-blue-100 rounded-lg text-blue-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 space-y-1">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500 font-medium">Potong Piutang:</span>
                    <span class="font-bold text-gray-800">Rp {{ number_format($kpis['applied_to_ar_total'], 0, ',', '.') }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-gray-500 font-medium">Deposit Customer:</span>
                    <span class="font-bold text-blue-700">Rp {{ number_format($kpis['applied_to_deposit_total'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Status & Filter Box --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-4">
        {{-- Tabs --}}
        <div class="flex border-b border-gray-200 gap-6 overflow-x-auto text-sm font-semibold">
            <a
                href="{{ route('penjualan.credit-notes.index', ['tab' => 'semua', 'q' => $search, 'type' => $type, 'cabang_id' => $cabangId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="pb-3 border-b-2 {{ $tab === 'semua' ? 'border-[#15803d] text-[#15803d]' : 'border-transparent text-gray-500 hover:text-gray-700' }} transition whitespace-nowrap"
            >
                Semua Dokumen
                <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $tab === 'semua' ? 'bg-[#dcfce7] text-[#15803d]' : 'bg-gray-100 text-gray-600' }} font-bold">
                    {{ $kpis['total_count'] }}
                </span>
            </a>
            <a
                href="{{ route('penjualan.credit-notes.index', ['tab' => 'draft', 'q' => $search, 'type' => $type, 'cabang_id' => $cabangId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="pb-3 border-b-2 {{ $tab === 'draft' ? 'border-amber-600 text-amber-700' : 'border-transparent text-gray-500 hover:text-gray-700' }} transition whitespace-nowrap"
            >
                Draf
                <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $tab === 'draft' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }} font-bold">
                    {{ $kpis['draft_count'] }}
                </span>
            </a>
            <a
                href="{{ route('penjualan.credit-notes.index', ['tab' => 'issued', 'q' => $search, 'type' => $type, 'cabang_id' => $cabangId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                class="pb-3 border-b-2 {{ $tab === 'issued' ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700' }} transition whitespace-nowrap"
            >
                Terbit (Issued)
                <span class="ml-1.5 px-2 py-0.5 text-xs rounded-full {{ $tab === 'issued' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }} font-bold">
                    {{ $kpis['issued_count'] }}
                </span>
            </a>
        </div>

        {{-- Form Filter & Pencarian --}}
        <form method="GET" action="{{ route('penjualan.credit-notes.index') }}" class="flex flex-col lg:flex-row lg:items-end justify-between gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            {{-- Grup Kiri: Input Pencarian & Dropdown Filter --}}
            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Cari Nota Kredit</label>
                    <div class="relative">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari No. CN, Pelanggan, No. Invoice..."
                            class="w-full pl-9 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                        >
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Jenis Nota Kredit</label>
                    <select name="type" class="w-full py-1.5 px-3 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]">
                        <option value="">Semua Jenis</option>
                        @foreach (\App\Models\CreditNote::TYPE_LABELS as $key => $label)
                            <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Grup Kanan: Rentang Tanggal Terpadu & Aksi Filter (Anti-Overlap) --}}
            <div class="flex flex-wrap sm:flex-nowrap items-end gap-2 shrink-0">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Periode Tanggal</label>
                    <div class="inline-flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2 py-0.5 focus-within:border-[#15803d] focus-within:ring-2 focus-within:ring-[#15803d] transition">
                        <input
                            type="date"
                            name="date_from"
                            value="{{ $dateFrom }}"
                            class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Dari Tanggal"
                        >
                        <span class="text-xs text-gray-400 px-1 font-medium">s/d</span>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ $dateTo }}"
                            class="bg-transparent border-0 text-xs py-1 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Sampai Tanggal"
                        >
                    </div>
                </div>

                <div class="flex items-center gap-1.5 self-end pb-0.5">
                    <button
                        type="submit"
                        class="px-3.5 py-1.5 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded-lg shadow-xs transition active:scale-[0.98]"
                        title="Terapkan Filter"
                    >
                        Filter
                    </button>
                    @if ($search || $type || $cabangId || $dateFrom || $dateTo || $tab !== 'semua')
                        <a
                            href="{{ route('penjualan.credit-notes.index') }}"
                            class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition"
                            title="Reset Filter"
                        >
                            Reset
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    {{-- Tabel Data --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="py-3 px-4">Dokumen</th>
                        <th class="py-3 px-4">Jenis</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Customer & Invoice</th>
                        <th class="py-3 px-4 text-right">Nilai Total</th>
                        <th class="py-3 px-4">Alokasi Finansial</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($creditNotes as $cn)
                        @php
                            $typeBadge = match ($cn->type) {
                                \App\Models\CreditNote::TYPE_CANCELLATION => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'dot' => 'bg-rose-500', 'label' => 'Pembatalan Invoice'],
                                \App\Models\CreditNote::TYPE_RETURN => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'dot' => 'bg-amber-500', 'label' => 'Retur Barang'],
                                \App\Models\CreditNote::TYPE_CORRECTION => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'dot' => 'bg-blue-500', 'label' => 'Koreksi Sebagian'],
                                default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400', 'label' => $cn->type],
                            };

                            $statusBadge = match ($cn->status) {
                                \App\Models\CreditNote::STATUS_ISSUED => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'dot' => 'bg-emerald-500', 'label' => 'Terbit (Issued)'],
                                default => ['bg' => 'bg-gray-50', 'text' => 'text-gray-700', 'border' => 'border-gray-200', 'dot' => 'bg-gray-400', 'label' => 'Draf'],
                            };
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            {{-- Dokumen --}}
                            <td class="py-3 px-4 font-medium">
                                <a href="{{ route('penjualan.credit-notes.show', $cn->id) }}" class="font-extrabold text-[#15803d] hover:underline block text-[13px]">
                                    {{ $cn->credit_note_number }}
                                </a>
                                <span class="text-xs text-gray-400 block mt-0.5">
                                    {{ $cn->credit_date ? \Carbon\Carbon::parse($cn->credit_date)->translatedFormat('d M Y') : '-' }}
                                </span>
                            </td>

                            {{-- Jenis --}}
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $typeBadge['bg'] }} {{ $typeBadge['text'] }} {{ $typeBadge['border'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $typeBadge['dot'] }}"></span>
                                    {{ $typeBadge['label'] }}
                                </span>
                            </td>

                            {{-- Status --}}
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }} {{ $statusBadge['border'] }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                    {{ $statusBadge['label'] }}
                                </span>
                            </td>

                            {{-- Customer & Invoice --}}
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900 text-[13px]">
                                    {{ $cn->customer->name ?? '-' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5">
                                    <span>Inv:</span>
                                    <span class="font-mono font-medium text-gray-700">{{ $cn->invoice->invoice_number ?? '-' }}</span>
                                    @if ($cn->customerReturn)
                                        <span class="text-gray-300">•</span>
                                        <span class="text-amber-700">Retur: {{ $cn->customerReturn->return_number }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Total Nilai --}}
                            <td class="py-3 px-4 text-right">
                                <span class="font-extrabold text-gray-900 text-sm">
                                    Rp {{ number_format((float) $cn->total, 0, ',', '.') }}
                                </span>
                                @if ((float) $cn->tax_amount > 0)
                                    <span class="text-[11px] text-gray-400 block mt-0.5">
                                        PPN: Rp {{ number_format((float) $cn->tax_amount, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>

                            {{-- Alokasi Finansial --}}
                            <td class="py-3 px-4">
                                @if ($cn->isIssued())
                                    <div class="text-xs space-y-0.5">
                                        @if ((float) $cn->applied_to_ar > 0)
                                            <div class="flex items-center justify-between text-gray-600 gap-2">
                                                <span>Potong Piutang:</span>
                                                <span class="font-semibold text-gray-800">Rp {{ number_format((float) $cn->applied_to_ar, 0, ',', '.') }}</span>
                                            </div>
                                        @endif
                                        @if ((float) $cn->applied_to_deposit > 0)
                                            <div class="flex items-center justify-between text-blue-700 gap-2 font-medium">
                                                <span>Deposit Cust:</span>
                                                <span class="font-bold">Rp {{ number_format((float) $cn->applied_to_deposit, 0, ',', '.') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Dihitung saat terbit</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <a
                                        href="{{ route('penjualan.credit-notes.show', $cn->id) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Detail Nota Kredit"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <a
                                        href="{{ route('penjualan.credit-notes.pdf', $cn->id) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-gray-500">
                                <div class="max-w-sm mx-auto space-y-3">
                                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="font-semibold text-gray-700">Tidak ada dokumen Nota Kredit ditemukan</p>
                                    <p class="text-xs text-gray-400">Gunakan tombol "Buat Nota Kredit" untuk memulai pengajuan draf baru.</p>
                                    <div class="pt-2">
                                        <a
                                            href="{{ route('penjualan.credit-notes.create') }}"
                                            class="inline-flex items-center gap-2 px-3 py-1.5 bg-[#15803d] text-white text-xs font-semibold rounded-lg hover:bg-[#166534] transition"
                                        >
                                            Buat Draf Pertama
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($creditNotes->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $creditNotes->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
