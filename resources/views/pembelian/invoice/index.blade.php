@extends('layouts.app')

@section('title', 'Faktur Pembelian (Purchase Invoice) – Duta Tunggal ERP')

@section('content')
<div class="space-y-6" x-data="{ 
    deleteModalOpen: false,
    deleteActionUrl: '',
    deleteInvoiceNumber: '',
    cancelModalOpen: false,
    cancelActionUrl: '',
    cancelInvoiceNumber: '',
    confirmDelete(url, number) {
        this.deleteActionUrl = url;
        this.deleteInvoiceNumber = number;
        this.deleteModalOpen = true;
    },
    confirmCancel(url, number) {
        this.cancelActionUrl = url;
        this.cancelInvoiceNumber = number;
        this.cancelModalOpen = true;
    }
}">
    {{-- Header Halaman --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <span>Pembelian</span>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold">Faktur Pembelian (AP)</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Faktur Pembelian (Purchase Invoice)</h1>
            <p class="text-sm text-gray-500 mt-0.5">Kelola tagihan dari pemasok, verifikasi 3-Way Matching (PO &amp; GRN), dan pantau hutang dagang (Accounts Payable).</p>
        </div>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('pembelian.invoices.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-sm font-semibold rounded-lg shadow-sm transition active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:ring-offset-2"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                + Buat Faktur Baru
            </a>
        </div>
    </div>

    {{-- 4 KPI Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Tagihan Pembelian --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total Tagihan (Aktif)</span>
                <span class="p-2 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900">Rp {{ number_format($kpi['total_amount'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Akumulasi seluruh faktur pembelian aktif</span>
        </div>

        {{-- Hutang Belum Dibayar --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Belum Dibayar (Unpaid)</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-xl font-bold text-amber-700">Rp {{ number_format($kpi['unpaid_amount'] ?? 0, 0, ',', '.') }}</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">{{ $counts['unpaid'] ?? 0 }} faktur menunggu pembayaran</span>
        </div>

        {{-- Jatuh Tempo (Overdue) --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Jatuh Tempo (Overdue)</span>
                <span class="p-2 bg-red-50 text-red-600 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-red-600">{{ $kpi['overdue_count'] ?? 0 }}</span>
                <span class="text-xs font-semibold text-red-700 bg-red-100 px-1.5 py-0.5 rounded">Perhatian</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Lewat tempo &amp; belum dilunasi</span>
        </div>

        {{-- Faktur Lunas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm hover:border-gray-300 transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Faktur Lunas (Paid)</span>
                <span class="p-2 bg-emerald-50 text-[#15803d] rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold text-gray-900">{{ $kpi['paid_count'] ?? 0 }}</span>
                <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">Lunas</span>
            </div>
            <span class="text-xs text-gray-500 mt-1 block">Telah dibayar penuh via Kas/Bank</span>
        </div>
    </div>

    {{-- Filter Tabs & Pencarian --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Tabs Status --}}
        <div class="flex items-center gap-1 p-2 border-b border-gray-100 overflow-x-auto bg-gray-50/50">
            @php
                $tabs = [
                    'semua' => ['label' => 'Semua Faktur', 'count' => $counts['semua'] ?? 0],
                    'draft' => ['label' => 'Draf', 'count' => $counts['draft'] ?? 0],
                    'unpaid' => ['label' => 'Menunggu Pembayaran', 'count' => $counts['unpaid'] ?? 0],
                    'overdue' => ['label' => 'Jatuh Tempo', 'count' => $counts['overdue'] ?? 0],
                    'paid' => ['label' => 'Lunas', 'count' => $counts['paid'] ?? 0],
                    'cancelled' => ['label' => 'Dibatalkan', 'count' => $counts['cancelled'] ?? 0],
                ];
            @endphp
            @foreach ($tabs as $key => $tab)
                <a
                    href="{{ request()->fullUrlWithQuery(['tab' => $key, 'page' => 1]) }}"
                    class="px-3.5 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap flex items-center gap-1.5 {{ $activeTab === $key ? 'bg-white text-[#15803d] shadow-sm font-bold border border-gray-200' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100' }}"
                >
                    <span>{{ $tab['label'] }}</span>
                    <span class="text-[11px] px-1.5 py-0.5 rounded-full {{ $activeTab === $key ? 'bg-[#15803d]/10 text-[#15803d] font-bold' : 'bg-gray-200 text-gray-600' }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Baris Filter Form --}}
        <form method="GET" action="{{ route('pembelian.invoices.index') }}" class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 border-b border-gray-100 bg-white">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

            {{-- Input Pencarian --}}
            <div class="relative lg:col-span-2">
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari no. faktur, no. surat jalan, atau supplier..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition"
                />
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            {{-- Filter Supplier --}}
            <div>
                <select name="supplier_id" class="w-full py-2 px-3 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition">
                    <option value="">Semua Pemasok</option>
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}" {{ (string)$supplierFilter === (string)$sup->id ? 'selected' : '' }}>
                            {{ $sup->perusahaan ? $sup->perusahaan . ' (' . $sup->name . ')' : $sup->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Cabang --}}
            @if (count($cabangs) > 1)
                <div>
                    <select name="cabang_id" class="w-full py-2 px-3 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition">
                        <option value="">Semua Cabang</option>
                        @foreach ($cabangs as $cabang)
                            <option value="{{ $cabang->id }}" {{ (string)$cabangFilter === (string)$cabang->id ? 'selected' : '' }}>
                                {{ $cabang->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <input type="date" name="date_from" value="{{ $dateFrom }}" placeholder="Dari Tgl" class="w-full py-2 px-3 border border-gray-200 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#15803d] focus:border-transparent transition" />
                </div>
            @endif

            {{-- Tombol Aksi Filter --}}
            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="flex-1 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98] text-center"
                >
                    Terapkan
                </button>
                @if ($search || $supplierFilter || $cabangFilter || $dateFrom || $dateTo || $activeTab !== 'semua')
                    <a
                        href="{{ route('pembelian.invoices.index') }}"
                        class="p-2 border border-gray-200 hover:bg-gray-100 text-gray-600 rounded-lg transition"
                        title="Reset Filter"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </a>
                @endif
            </div>
        </form>

        {{-- Tabel Faktur Pembelian --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                        <th class="py-3 px-4">No. Faktur</th>
                        <th class="py-3 px-4">Pemasok</th>
                        <th class="py-3 px-4">No. Faktur Vendor / Pajak</th>
                        <th class="py-3 px-4">Tgl. Faktur &amp; Tempo</th>
                        <th class="py-3 px-4">Cabang</th>
                        <th class="py-3 px-4 text-right">Total Tagihan</th>
                        <th class="py-3 px-4 text-right">Sisa Hutang</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse ($invoices as $inv)
                        @php
                            $status = strtolower((string)$inv->status);
                            $badgeClass = match ($status) {
                                'draft' => 'bg-gray-100 text-gray-700 border-gray-200',
                                'sent' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'partially_paid' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'paid' => 'bg-emerald-50 text-[#15803d] border-emerald-200',
                                'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-gray-100 text-gray-700 border-gray-200',
                            };
                            $remaining = $inv->getRemainingAmount();
                        @endphp
                        <tr class="hover:bg-gray-50/75 transition">
                            <td class="py-3 px-4 font-semibold">
                                <a href="{{ route('pembelian.invoices.show', $inv) }}" class="text-[#15803d] hover:underline font-bold">
                                    {{ $inv->invoice_number }}
                                </a>
                                @if (!empty($inv->purchase_order_ids) && count($inv->purchase_order_ids) > 0)
                                    <span class="block text-[10px] text-gray-400 mt-0.5">
                                        PO #{{ implode(', #', $inv->purchase_order_ids) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ $inv->supplier?->perusahaan ?? $inv->supplier_name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-500">{{ $inv->supplier?->name ?? '' }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-800">{{ $inv->supplier_invoice_number ?? '-' }}</div>
                                @if ($inv->tax_invoice_number)
                                    <div class="text-[10px] text-gray-400">FP: {{ $inv->tax_invoice_number }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-medium text-gray-900">{{ $inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}</div>
                                <div class="text-[11px] {{ $inv->isOverdue() ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                                    Jatuh Tempo: {{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '-' }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-xs text-gray-600">{{ $inv->cabang?->name ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">
                                Rp {{ number_format((float)$inv->total, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-bold {{ $remaining > 0 ? 'text-amber-700' : 'text-[#15803d]' }}">
                                Rp {{ number_format((float)$remaining, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border {{ $badgeClass }}">
                                    {{ $inv->status_label }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    {{-- Lihat Detail --}}
                                    <a
                                        href="{{ route('pembelian.invoices.show', $inv) }}"
                                        class="p-1.5 text-gray-500 hover:text-[#15803d] hover:bg-emerald-50 rounded transition"
                                        title="Lihat Detail"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>

                                    {{-- Cetak PDF --}}
                                    <a
                                        href="{{ route('pembelian.invoices.pdf', $inv) }}"
                                        target="_blank"
                                        class="p-1.5 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded transition"
                                        title="Cetak PDF"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>

                                    {{-- Edit (Khusus Draft) --}}
                                    @if ($status === 'draft')
                                        <a
                                            href="{{ route('pembelian.invoices.edit', $inv) }}"
                                            class="p-1.5 text-gray-500 hover:text-amber-600 hover:bg-amber-50 rounded transition"
                                            title="Edit Faktur Draft"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>

                                        {{-- Hapus (Khusus Draft) --}}
                                        <button
                                            type="button"
                                            @click="confirmDelete('{{ route('pembelian.invoices.destroy', $inv) }}', '{{ $inv->invoice_number }}')"
                                            class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded transition"
                                            title="Hapus Faktur Draft"
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
                            <td colspan="9" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center gap-3">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                                    </svg>
                                    <div class="text-sm font-semibold text-gray-500">Tidak ada Faktur Pembelian yang ditemukan</div>
                                    <p class="text-xs text-gray-400 max-w-sm">Coba sesuaikan kata kunci pencarian atau ubah tab filter di atas.</p>
                                    <a href="{{ route('pembelian.invoices.create') }}" class="mt-2 inline-flex items-center gap-1.5 text-xs text-white bg-[#15803d] hover:bg-[#14532d] px-3.5 py-1.5 rounded-lg font-semibold transition">
                                        + Buat Faktur Pembelian Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginasi Terpusat Berbahasa Indonesia --}}
        @if ($invoices->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Konfirmasi Hapus Draft --}}
    <x-confirmation-modal
        id="delete-invoice-modal"
        name="deleteModalOpen"
        title="Hapus Faktur Pembelian Draft"
        variant="danger"
        confirm-text="Ya, Hapus Faktur"
        cancel-text="Batal"
        x-bind:action="deleteActionUrl"
        method="POST"
    >
        @method('DELETE')
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus Faktur Pembelian draft <strong class="text-gray-900" x-text="deleteInvoiceNumber"></strong>?
        </p>
        <p class="text-xs text-red-600 mt-2">
            Penerimaan barang (GRN) yang tertagih pada faktur ini akan kembali dapat ditagihkan. Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>
</div>
@endsection
