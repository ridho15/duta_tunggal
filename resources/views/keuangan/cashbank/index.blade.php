@extends('layouts.app')

@section('title', 'Kas & Bank – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    deleteModalOpen: false,
    deleteActionUrl: '',
    deleteItemNumber: '',
    deleteItemType: 'transaksi',
    confirmDelete(url, number, type = 'transaksi') {
        this.deleteActionUrl = url;
        this.deleteItemNumber = number;
        this.deleteItemType = type;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Keuangan</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Kas &amp; Bank</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Kas &amp; Bank</h1>
            <p class="text-sm text-gray-500 mt-0.5">Monitoring saldo rekening kas &amp; bank secara real-time, pencatatan mutasi operasional, dan transfer internal antar rekening.</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a
                href="{{ route('keuangan.cashbank.transfer.create') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 bg-white hover:bg-gray-50 border border-gray-200 text-gray-700 text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                </svg>
                Transfer Antar Kas/Bank
            </a>
            <a
                href="{{ route('keuangan.cashbank.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d]/30"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Catat Transaksi Baru
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Kas & Setara Kas --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Kas &amp; Setara Kas</span>
                <span class="p-2 bg-emerald-50 text-[#15803d] rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalLikuiditas) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Saldo likuiditas gabungan seluruh kas &amp; bank</p>
        </div>

        {{-- Saldo Rekening Bank --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Saldo Rekening Bank</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalSaldoBank) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Total saldo di seluruh rekening bank operasional</p>
        </div>

        {{-- Saldo Kas Operasional --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Saldo Kas Tunai</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalSaldoKas) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Total saldo kas kecil, kas kasir, &amp; kas besar</p>
        </div>

        {{-- Mutasi Bulan Berjalan --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Mutasi Bulan Ini</span>
                <span class="p-2 bg-purple-50 text-purple-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 space-y-1">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-emerald-700 font-semibold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                        Masuk:
                    </span>
                    <span class="font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalMasukBulanIni) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-rose-700 font-semibold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                        Keluar:
                    </span>
                    <span class="font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($totalKeluarBulanIni) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Grid Kartu Rekening Kas & Bank --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 sm:p-5">
        <div class="flex items-center justify-between mb-3.5">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Daftar Rekening Kas &amp; Bank</h2>
                <span class="text-xs text-gray-400">({{ $cashBankAccounts->count() }} Akun)</span>
            </div>
            @if ($accountId)
                <a href="{{ route('keuangan.cashbank.index', array_merge(request()->except('account_id', 'page'))) }}" class="text-xs font-semibold text-emerald-700 hover:underline flex items-center gap-1">
                    <span>Lihat Semua Rekening</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </a>
            @endif
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 max-h-72 overflow-y-auto pr-1">
            @foreach ($cashBankAccounts as $acc)
                @php
                    $isSelected = ($accountId == $acc->id);
                    $isBank = str_starts_with($acc->coa?->code ?? '', '1112');
                @endphp
                <a
                    href="{{ route('keuangan.cashbank.index', array_merge(request()->except('page'), ['account_id' => $isSelected ? null : $acc->id])) }}"
                    class="group block p-3 rounded-lg border text-left transition relative {{ $isSelected ? 'border-emerald-600 bg-emerald-50/50 shadow-sm ring-1 ring-emerald-600' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/70' }}"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="p-1.5 rounded-md flex-shrink-0 {{ $isBank ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">
                                @if ($isBank)
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                @endif
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-xs font-bold text-gray-900 truncate group-hover:text-emerald-700">{{ $acc->name }}</h3>
                                <p class="text-[10.5px] text-gray-500 truncate font-mono">{{ $acc->coa?->code }} &bull; {{ $acc->account_number ?? $acc->bank_name ?? 'Tunai' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2.5 pt-2 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-[10px] uppercase font-semibold text-gray-400">Saldo:</span>
                        <span class="text-xs font-bold {{ $acc->current_balance < 0 ? 'text-rose-600' : 'text-gray-900' }}">
                            {{ \App\Helpers\MoneyHelper::rupiah($acc->current_balance) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Main Container Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Status / Type Filter Tabs --}}
        <div class="border-b border-gray-100 px-4 sm:px-6 pt-3 bg-gray-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-3 text-xs sm:text-sm font-semibold scrollbar-none">
                @php
                    $tabs = [
                        'semua' => ['label' => 'Semua Transaksi', 'count' => $counts['semua']],
                        'masuk' => ['label' => 'Kas/Bank Masuk (In)', 'count' => $counts['masuk']],
                        'keluar' => ['label' => 'Kas/Bank Keluar (Out)', 'count' => $counts['keluar']],
                        'transfer' => ['label' => 'Transfer Antar Rekening', 'count' => $counts['transfer']],
                    ];
                @endphp

                @foreach ($tabs as $key => $meta)
                    @php
                        $isActive = ($tab === $key);
                    @endphp
                    <a
                        href="{{ route('keuangan.cashbank.index', array_merge(request()->except('page'), ['tab' => $key])) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition {{ $isActive ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <span>{{ $meta['label'] }}</span>
                        <span class="text-xs px-1.5 py-0.2 rounded-full {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                            {{ number_format($meta['count']) }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Filter & Search Bar --}}
        <div class="p-4 sm:px-6 border-b border-gray-100 bg-white">
            <form method="GET" action="{{ route('keuangan.cashbank.index') }}" class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($accountId)
                    <input type="hidden" name="account_id" value="{{ $accountId }}">
                @endif

                {{-- Grup Kiri: Pencarian Kata Kunci --}}
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Cari nomor bukti, pihak terkait, referensi, atau keterangan..."
                        class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 text-gray-900 placeholder-gray-400"
                    >
                </div>

                {{-- Grup Kanan: Rentang Tanggal Terpadu & Tombol Aksi (Anti-Overlap) --}}
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 shrink-0">
                    <div class="inline-flex items-center bg-gray-50 border border-gray-300 rounded-lg px-2 py-0.5 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition">
                        <input
                            type="date"
                            name="start_date"
                            value="{{ $startDate }}"
                            class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Dari Tanggal"
                        >
                        <span class="text-xs text-gray-400 px-1 font-medium">s/d</span>
                        <input
                            type="date"
                            name="end_date"
                            value="{{ $endDate }}"
                            class="bg-transparent border-0 text-xs py-1.5 px-1 focus:ring-0 focus:outline-none text-gray-700 min-w-[130px]"
                            title="Sampai Tanggal"
                        >
                    </div>

                    <div class="flex items-center gap-1.5">
                        <button
                            type="submit"
                            class="px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                            title="Terapkan Filter"
                        >
                            Filter
                        </button>
                        @if ($search || $startDate || $endDate || $accountId)
                            <a
                                href="{{ route('keuangan.cashbank.index', ['tab' => $tab]) }}"
                                class="p-2 text-gray-500 hover:text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition"
                                title="Reset Filter"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- Content Table --}}
        @if ($tab === 'transfer')
            {{-- TABEL TRANSFER KAS / BANK --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700">
                    <thead class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-4">No. Transfer</th>
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4">Dari Rekening</th>
                            <th class="py-3 px-4">Ke Rekening</th>
                            <th class="py-3 px-4 text-right">Nominal Transfer</th>
                            <th class="py-3 px-4 text-right">Biaya Admin</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transfers as $trf)
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3 px-4 font-mono font-bold text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('keuangan.cashbank.transfer.show', $trf) }}" class="text-emerald-700 hover:underline">
                                        {{ $trf->number }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-gray-600">
                                    {{ \Carbon\Carbon::parse($trf->date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900">{{ $trf->fromCoa?->name ?? '-' }}</div>
                                    <div class="text-[10.5px] text-gray-400 font-mono">{{ $trf->fromCoa?->code }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900">{{ $trf->toCoa?->name ?? '-' }}</div>
                                    <div class="text-[10.5px] text-gray-400 font-mono">{{ $trf->toCoa?->code }}</div>
                                </td>
                                <td class="py-3 px-4 text-right font-bold text-gray-900 whitespace-nowrap">
                                    {{ \App\Helpers\MoneyHelper::rupiah($trf->amount) }}
                                </td>
                                <td class="py-3 px-4 text-right text-gray-600 whitespace-nowrap">
                                    {{ $trf->other_costs > 0 ? \App\Helpers\MoneyHelper::rupiah($trf->other_costs) : '-' }}
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ ucfirst($trf->status ?? 'posted') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a
                                            href="{{ route('keuangan.cashbank.transfer.show', $trf) }}"
                                            class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                            title="Lihat Detail Transfer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a
                                            href="{{ route('keuangan.cashbank.transfer.pdf', $trf) }}"
                                            target="_blank"
                                            class="p-1.5 text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                            title="Cetak Bukti Transfer (PDF)"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('keuangan.cashbank.transfer.destroy', $trf) }}', '{{ $trf->number }}', 'transfer')"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Batalkan Transfer"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        <p class="text-sm font-semibold text-gray-600">Belum ada riwayat transfer internal</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Klik tombol "Transfer Antar Kas/Bank" untuk memindahkan dana antar rekening.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transfers && $transfers->hasPages())
                <div class="px-4 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $transfers->links() }}
                </div>
            @endif
        @else
            {{-- TABEL TRANSAKSI KAS / BANK (MASUK / KELUAR) --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-700">
                    <thead class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-3 px-4">No. Bukti</th>
                            <th class="py-3 px-4">Tanggal</th>
                            <th class="py-3 px-4 text-center">Tipe</th>
                            <th class="py-3 px-4">Rekening Kas/Bank</th>
                            <th class="py-3 px-4">Akun Lawan / Rincian</th>
                            <th class="py-3 px-4">Pihak Terkait / Ket.</th>
                            <th class="py-3 px-4 text-right">Nominal</th>
                            <th class="py-3 px-4 text-center">Jurnal</th>
                            <th class="py-3 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($transactions as $trx)
                            @php
                                $isIn = in_array($trx->type, ['cash_in', 'bank_in']);
                                $typeLabels = [
                                    'cash_in' => ['label' => 'Kas Masuk', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
                                    'bank_in' => ['label' => 'Bank Masuk', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500'],
                                    'cash_out' => ['label' => 'Kas Keluar', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200', 'dot' => 'bg-amber-500'],
                                    'bank_out' => ['label' => 'Bank Keluar', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500'],
                                ];
                                $badge = $typeLabels[$trx->type] ?? ['label' => $trx->type, 'badge' => 'bg-gray-50 text-gray-700 border-gray-200', 'dot' => 'bg-gray-400'];
                            @endphp
                            <tr class="hover:bg-gray-50/60 transition">
                                <td class="py-3 px-4 font-mono font-bold text-gray-900 whitespace-nowrap">
                                    <a href="{{ route('keuangan.cashbank.show', $trx) }}" class="text-emerald-700 hover:underline">
                                        {{ $trx->number }}
                                    </a>
                                </td>
                                <td class="py-3 px-4 whitespace-nowrap text-gray-600">
                                    {{ \Carbon\Carbon::parse($trx->date)->translatedFormat('d M Y') }}
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $badge['badge'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $badge['dot'] }}"></span>
                                        {{ $badge['label'] }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-gray-900">{{ $trx->accountCoa?->name ?? '-' }}</div>
                                    <div class="text-[10.5px] text-gray-400 font-mono">{{ $trx->accountCoa?->code }}</div>
                                </td>
                                <td class="py-3 px-4 max-w-xs">
                                    @if ($trx->transactionDetails->isNotEmpty())
                                        <div class="font-semibold text-gray-900 flex items-center gap-1">
                                            <span>Breakdown ({{ $trx->transactionDetails->count() }} Akun)</span>
                                        </div>
                                        <div class="text-[10.5px] text-gray-500 truncate">
                                            {{ $trx->transactionDetails->pluck('chartOfAccount.name')->filter()->join(', ') }}
                                        </div>
                                    @else
                                        <div class="font-semibold text-gray-900">{{ $trx->offsetCoa?->name ?? '-' }}</div>
                                        <div class="text-[10.5px] text-gray-400 font-mono">{{ $trx->offsetCoa?->code }}</div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 max-w-xs">
                                    @if ($trx->counterparty)
                                        <div class="font-semibold text-gray-900 truncate">{{ $trx->counterparty }}</div>
                                    @endif
                                    <div class="text-[11px] text-gray-500 truncate">{{ $trx->description ?? '-' }}</div>
                                </td>
                                <td class="py-3 px-4 text-right font-bold whitespace-nowrap {{ $isIn ? 'text-emerald-600' : 'text-gray-900' }}">
                                    {{ $isIn ? '+' : '-' }} {{ \App\Helpers\MoneyHelper::rupiah($trx->amount) }}
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if ($trx->journalEntries->isNotEmpty())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Dibukukan
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Pending
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a
                                            href="{{ route('keuangan.cashbank.show', $trx) }}"
                                            class="p-1.5 text-gray-600 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                            title="Lihat Detail Transaksi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        <a
                                            href="{{ route('keuangan.cashbank.pdf', $trx) }}"
                                            target="_blank"
                                            class="p-1.5 text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                            title="Cetak Bukti Voucher (PDF)"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                        </a>
                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('keuangan.cashbank.destroy', $trx) }}', '{{ $trx->number }}', 'transaksi')"
                                            class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Transaksi"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        <p class="text-sm font-semibold text-gray-600">Tidak ada transaksi ditemukan</p>
                                        <p class="text-xs text-gray-400 mt-0.5">Gunakan tombol "Catat Transaksi Baru" untuk membukukan mutasi kas/bank.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($transactions && $transactions->hasPages())
                <div class="px-4 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/50">
                    {{ $transactions->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus / Batal --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4"
    >
        <div
            @click.away="deleteModalOpen = false"
            class="bg-white rounded-xl max-w-md w-full p-6 shadow-xl border border-gray-100 transition-all transform"
        >
            <div class="flex items-center gap-3 text-rose-600 mb-3">
                <div class="p-2.5 bg-rose-50 rounded-full">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900">Batalkan / Hapus Transaksi</h3>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed mb-4">
                Apakah Anda yakin ingin membatalkan dan menghapus <span x-text="deleteItemType"></span> dengan nomor <span class="font-bold text-gray-900 font-mono" x-text="deleteItemNumber"></span>?
                <br><br>
                <span class="text-rose-600 font-semibold">Perhatian:</span> Seluruh jurnal akuntansi ganda yang telah dibukukan oleh transaksi ini akan otomatis dihapus dan saldo kas/bank akan dikembalikan ke posisi semula.
            </p>
            <form :action="deleteActionUrl" method="POST" class="flex items-center justify-end gap-2.5">
                @csrf
                @method('DELETE')
                <button
                    type="button"
                    @click="deleteModalOpen = false"
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    Ya, Hapus &amp; Rollback Jurnal
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
