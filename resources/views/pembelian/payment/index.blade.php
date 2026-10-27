@extends('layouts.app')

@section('title', 'Pembayaran Hutang Vendor – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    deleteModalOpen: false,
    deleteActionUrl: '',
    deletePaymentNumber: '',
    confirmDelete(url, number) {
        this.deleteActionUrl = url;
        this.deletePaymentNumber = number;
        this.deleteModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Pembelian</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Pembayaran Hutang (AP Settlement)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Pembayaran Hutang Vendor</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola pelunasan tagihan faktur pembelian kepada pemasok melalui akun Kas &amp; Bank, otomatisasi saldo hutang (AP), dan jurnal Buku Besar.</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.payments.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#15803d] hover:bg-[#166534] text-white text-xs sm:text-sm font-semibold rounded-xl shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:ring-offset-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Catat Pembayaran Baru
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Pembayaran Aktif --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Pembayaran (Aktif)</span>
                <span class="p-2 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($kpi['total_paid']) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Akumulasi pengeluaran kas/bank yang telah diposting</p>
        </div>

        {{-- Dibayar Bulan Ini --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Dibayar Bulan Ini</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($kpi['this_month']) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Periode {{ now()->translatedFormat('F Y') }}</p>
        </div>

        {{-- Draft / Belum Diposting --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Draft / Menunggu Posting</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ number_format($kpi['draft_count']) }}</span>
                <span class="text-xs text-gray-500">Dokumen</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Belum memotong saldo hutang atau mencatat jurnal</p>
        </div>

        {{-- Total Sisa Hutang Dagang --}}
        <div class="bg-white rounded-2xl border border-gray-100 p-4 shadow-sm hover:border-gray-200 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Sisa Hutang (AP)</span>
                <span class="p-2 bg-purple-50 text-purple-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">{{ \App\Helpers\MoneyHelper::rupiah($kpi['total_ap_remaining']) }}</span>
            </div>
            <p class="text-[11px] text-gray-400 mt-1">Kewajiban hutang dagang perusahaan yang masih outstanding</p>
        </div>
    </div>

    {{-- Main Container Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        {{-- Status Filter Tabs --}}
        <div class="border-b border-gray-200 px-4 sm:px-6 pt-3 bg-gray-50/50">
            <div class="flex items-center gap-2 overflow-x-auto pb-3 text-xs sm:text-sm font-semibold scrollbar-none">
                @php
                    $tabs = [
                        'semua' => ['label' => 'Semua Status', 'count' => $counts['semua']],
                        'paid' => ['label' => 'Lunas', 'count' => $counts['paid']],
                        'partial' => ['label' => 'Sebagian', 'count' => $counts['partial']],
                        'draft' => ['label' => 'Draft', 'count' => $counts['draft']],
                        'cancelled' => ['label' => 'Dibatalkan', 'count' => $counts['cancelled']],
                    ];
                @endphp

                @foreach ($tabs as $key => $tabInfo)
                    @php
                        $isActive = ($tab === $key);
                    @endphp
                    <a
                        href="{{ request()->fullUrlWithQuery(['tab' => $key, 'page' => 1]) }}"
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition {{ $isActive ? 'bg-[#15803d] text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                    >
                        <span>{{ $tabInfo['label'] }}</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-gray-200/80 text-gray-600' }}">
                            {{ $tabInfo['count'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Filter & Search Form --}}
        <div class="p-4 sm:p-6 border-b border-gray-200 bg-white">
            <form method="GET" action="{{ route('pembelian.payments.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input type="hidden" name="tab" value="{{ $tab }}">

                {{-- Keyword Search --}}
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Cari Pembayaran</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Cari No. VP, Ref Transfer, atau Supplier..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                        >
                    </div>
                </div>

                {{-- Supplier Filter --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Supplier / Vendor</label>
                    <x-searchable-select name="supplier_id" :options="collect($suppliers)->map(fn ($s) => ['id' => $s->id, 'label' => $s->perusahaan])->values()" :value="$supplierId" placeholder="-- Semua Supplier --" size="xs" :auto-submit="true" />
                </div>

                {{-- Payment Method Filter --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Metode Bayar</label>
                    <select
                        name="payment_method"
                        class="w-full px-3 py-1.5 text-xs bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#15803d] focus:border-[#15803d] transition"
                    >
                        <option value="">-- Semua Metode --</option>
                        @foreach (['Bank Transfer', 'Cash', 'Giro', 'Cheque', 'Deposit'] as $m)
                            <option value="{{ $m }}" {{ $paymentMethod === $m ? 'selected' : '' }}>
                                {{ $m }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Action Filter Buttons --}}
                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </button>
                    <a
                        href="{{ route('pembelian.payments.index', ['tab' => $tab]) }}"
                        class="inline-flex items-center justify-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium rounded-lg transition"
                        title="Reset Filter"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Data Table --}}
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3">No. Pembayaran</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier / Vendor</th>
                        <th class="px-4 py-3">Faktur Terkait</th>
                        <th class="px-4 py-3">Metode &amp; Akun</th>
                        <th class="px-4 py-3 text-right">Total Bayar</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white text-gray-800">
                    @forelse ($payments as $payment)
                        <tr class="hover:bg-gray-50/60 transition group">
                            {{-- No. Pembayaran --}}
                            <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                <a href="{{ route('pembelian.payments.show', $payment->id) }}" class="text-[#15803d] hover:text-[#14532d] hover:underline flex items-center gap-1.5 font-bold">
                                    <span>{{ $payment->payment_number }}</span>
                                </a>
                                @if ($payment->transfer_reference_number)
                                    <div class="text-[11px] text-gray-400 font-normal">
                                        Ref: {{ $payment->transfer_reference_number }}
                                    </div>
                                @endif
                            </td>

                            {{-- Tanggal --}}
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">
                                <div>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y') : '-' }}</div>
                                <div class="text-[10.5px] text-gray-400">{{ $payment->created_at ? $payment->created_at->format('H:i') : '' }} WIB</div>
                            </td>

                            {{-- Supplier --}}
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900">{{ $payment->supplier?->perusahaan ?? 'Vendor Tidak Ditemukan' }}</div>
                                @if ($payment->target_bank_account)
                                    <div class="text-[11px] text-gray-500 truncate max-w-xs" title="{{ $payment->target_bank_account }}">
                                        Rek: {{ $payment->target_bank_account }}
                                    </div>
                                @endif
                            </td>

                            {{-- Faktur Terkait --}}
                            <td class="px-4 py-3">
                                @php
                                    $invoices = $payment->vendorPaymentDetail
                                        ->map(fn($d) => $d->invoice)
                                        ->filter(fn($inv) => $inv && !empty($inv->id));
                                @endphp
                                @if ($invoices->isNotEmpty())
                                    <div class="flex flex-wrap gap-1 max-w-xs">
                                        @foreach ($invoices->take(2) as $inv)
                                            <a href="{{ route('pembelian.invoices.show', $inv->id) }}" class="inline-block px-1.5 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-[10px] font-medium transition" title="Lihat Faktur">
                                                {{ $inv->invoice_number }}
                                            </a>
                                        @endforeach
                                        @if ($invoices->count() > 2)
                                            <span class="text-[10px] text-gray-400 self-center">+{{ $invoices->count() - 2 }} lainnya</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
                            </td>

                            {{-- Metode & Akun Kas/Bank --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="font-semibold text-gray-900">{{ $payment->payment_method }}</div>
                                <div class="text-[11px] text-gray-500">
                                    {{ $payment->coa ? "({$payment->coa->code}) {$payment->coa->name}" : '-' }}
                                </div>
                            </td>

                            {{-- Total Bayar --}}
                            <td class="px-4 py-3 text-right font-bold text-gray-900 whitespace-nowrap">
                                {{ \App\Helpers\MoneyHelper::rupiah($payment->total_payment) }}
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @php
                                    $st = strtolower((string)$payment->status);
                                    $badgeClass = match($st) {
                                        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'draft' => 'bg-gray-50 text-gray-700 border-gray-200',
                                        'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                        default => 'bg-gray-50 text-gray-700 border-gray-200',
                                    };
                                    $dotClass = match($st) {
                                        'paid' => 'bg-emerald-500',
                                        'partial' => 'bg-amber-500',
                                        'draft' => 'bg-gray-400',
                                        'cancelled' => 'bg-rose-500',
                                        default => 'bg-gray-400',
                                    };
                                    $label = match($st) {
                                        'paid' => 'Lunas',
                                        'partial' => 'Sebagian',
                                        'draft' => 'Draft',
                                        'cancelled' => 'Dibatalkan',
                                        default => ucfirst($payment->status ?? '-'),
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                    {{ $label }}
                                </span>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Lihat Detail --}}
                                    <a
                                        href="{{ route('pembelian.payments.show', $payment->id) }}"
                                        class="p-1.5 text-gray-600 hover:text-[#15803d] hover:bg-emerald-50 rounded-lg transition"
                                        title="Lihat Detail Pembayaran"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Cetak PDF --}}
                                    <a
                                        href="{{ route('pembelian.payments.pdf', $payment->id) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                        title="Cetak Voucher PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    {{-- Edit (Khusus Draft) --}}
                                    @if ($st === 'draft')
                                        <a
                                            href="{{ route('pembelian.payments.edit', $payment->id) }}"
                                            class="p-1.5 text-gray-600 hover:text-amber-700 hover:bg-amber-50 rounded-lg transition"
                                            title="Edit Pembayaran Draft"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        {{-- Hapus (Khusus Draft) --}}
                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('pembelian.payments.destroy', $payment->id) }}', '{{ $payment->payment_number }}')"
                                            class="p-1.5 text-gray-600 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition"
                                            title="Hapus Pembayaran Draft"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <div class="p-3 bg-gray-100 rounded-full text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <rect x="3" y="6" width="18" height="14" rx="2" stroke-width="2"></rect>
                                            <path d="M3 10h18M16 15h2" stroke-width="2" stroke-linecap="round"></path>
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-700 text-sm">Tidak ada data pembayaran hutang yang ditemukan</p>
                                    <p class="text-xs text-gray-400 max-w-sm">Gunakan tombol di atas untuk mencatat pembayaran hutang vendor baru atau sesuaikan filter pencarian Anda.</p>
                                    <a
                                        href="{{ route('pembelian.payments.create') }}"
                                        class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition"
                                    >
                                        Catat Pembayaran Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($payments->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                {{ $payments->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus Draft --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div
                x-show="deleteModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                @click="deleteModalOpen = false"
            ></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div
                x-show="deleteModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
            >
                <form :action="deleteActionUrl" method="POST">
                    @csrf
                    @method('DELETE')

                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start gap-4">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10 text-rose-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:text-left">
                                <h3 class="text-base leading-6 font-bold text-gray-900" id="modal-title">
                                    Hapus Draft Pembayaran
                                </h3>
                                <div class="mt-2 text-xs text-gray-500 leading-relaxed">
                                    Apakah Anda yakin ingin menghapus draft pembayaran <span class="font-bold text-gray-900" x-text="deletePaymentNumber"></span>? Tindakan ini tidak dapat dibatalkan.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-xs font-bold text-white hover:bg-rose-700 focus:outline-none transition"
                        >
                            Hapus Pembayaran
                        </button>
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none transition"
                        >
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
