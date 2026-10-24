@extends('layouts.app', ['title' => 'Terima Pembayaran Customer'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <span>Penjualan</span>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Terima Pembayaran</span>
            </nav>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Terima Pembayaran Customer</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Pencatatan kas & bank penerimaan piutang penjualan, bukti transfer, dan penerbitan kwitansi sah.
            </p>
        </div>

        <div>
            <a
                href="{{ route('penjualan.receipts.create') }}"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#166534] text-white px-4 py-2.5 rounded-xl font-semibold text-xs sm:text-sm shadow-sm transition active:scale-[0.98]"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                </svg>
                <span>Catat Penerimaan Baru</span>
            </a>
        </div>
    </div>


    {{-- KPI Cards Summary --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Total Penerimaan</span>
            <div class="text-xl font-black text-gray-900 mt-1">
                Rp {{ number_format($totalReceived, 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Akumulasi penerimaan sah</span>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Penerimaan Bulan Ini</span>
            <div class="text-xl font-black text-[#15803d] mt-1">
                Rp {{ number_format($thisMonthReceived, 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Periode {{ now()->isoFormat('MMMM Y') }}</span>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Piutang Tertagih (AR)</span>
            <div class="text-xl font-black text-blue-900 mt-1">
                Rp {{ number_format($totalArPaid, 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Total saldo AR terbayar</span>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-sm">
            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Sisa Piutang Berjalan</span>
            <div class="text-xl font-black text-amber-900 mt-1">
                Rp {{ number_format($totalArRemaining, 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-gray-500 mt-0.5 block">Menunggu pembayaran</span>
        </div>
    </div>

    {{-- Filter Status Tabs --}}
    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none text-xs font-semibold">
        @php
            $tabClass = function(bool $active) {
                return $active
                    ? 'px-3.5 py-1.5 rounded-full bg-[#15803d] text-white flex items-center gap-2 shadow-sm'
                    : 'px-3.5 py-1.5 rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition';
            };
        @endphp

        <a href="{{ route('penjualan.receipts.index', ['tab' => 'semua', 'q' => $search, 'cabang_id' => $cabangId, 'payment_method' => $paymentMethod]) }}"
           class="{{ $tabClass($tab === 'semua') }}">
            <span>Semua</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'semua' ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $counts['semua'] }}</span>
        </a>

        <a href="{{ route('penjualan.receipts.index', ['tab' => 'paid', 'q' => $search, 'cabang_id' => $cabangId, 'payment_method' => $paymentMethod]) }}"
           class="{{ $tabClass($tab === 'paid') }}">
            <span>Lunas Penuh</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'paid' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">{{ $counts['paid'] }}</span>
        </a>

        <a href="{{ route('penjualan.receipts.index', ['tab' => 'partial', 'q' => $search, 'cabang_id' => $cabangId, 'payment_method' => $paymentMethod]) }}"
           class="{{ $tabClass($tab === 'partial') }}">
            <span>Sebagian (Partial)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'partial' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">{{ $counts['partial'] }}</span>
        </a>

        <a href="{{ route('penjualan.receipts.index', ['tab' => 'draft', 'q' => $search, 'cabang_id' => $cabangId, 'payment_method' => $paymentMethod]) }}"
           class="{{ $tabClass($tab === 'draft') }}">
            <span>Draft</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'draft' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-800' }}">{{ $counts['draft'] }}</span>
        </a>

        <a href="{{ route('penjualan.receipts.index', ['tab' => 'cancelled', 'q' => $search, 'cabang_id' => $cabangId, 'payment_method' => $paymentMethod]) }}"
           class="{{ $tabClass($tab === 'cancelled') }}">
            <span>Dibatalkan</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'cancelled' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800' }}">{{ $counts['cancelled'] }}</span>
        </a>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-white border border-gray-100 rounded-2xl p-3 shadow-sm">
        <form method="GET" action="{{ route('penjualan.receipts.index') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" stroke-width="2"></circle>
                        <path d="M21 21l-4.35-4.35" stroke-width="2" stroke-linecap="round"></path>
                    </svg>
                </div>
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Cari no. kwitansi, nama customer, invoice, referensi..."
                    class="w-full pl-9 pr-3 py-2 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                >
            </div>

            <div class="w-full sm:w-48">
                <x-searchable-select name="cabang_id" :options="collect($cabangs)->map(fn ($c) => ['id' => $c->id, 'label' => $c->nama])->values()" :value="$cabangId" placeholder="Semua Cabang" size="sm" :auto-submit="true" />
            </div>

            <div class="w-full sm:w-44">
                <select name="payment_method" class="w-full py-2 px-3 text-xs border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d]">
                    <option value="">Semua Metode</option>
                    <option value="Cash" {{ $paymentMethod === 'Cash' ? 'selected' : '' }}>Tunai (Cash)</option>
                    <option value="Transfer" {{ $paymentMethod === 'Transfer' ? 'selected' : '' }}>Transfer Bank</option>
                    <option value="Giro" {{ $paymentMethod === 'Giro' ? 'selected' : '' }}>Giro</option>
                    <option value="Cheque" {{ $paymentMethod === 'Cheque' ? 'selected' : '' }}>Cek</option>
                    <option value="Deposit" {{ $paymentMethod === 'Deposit' ? 'selected' : '' }}>Deposit Pelanggan</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-xl transition">
                    Filter
                </button>
                @if($search || $cabangId || $paymentMethod || $tab !== 'semua')
                    <a href="{{ route('penjualan.receipts.index') }}" class="px-3 py-2 text-gray-500 hover:text-gray-800 text-xs font-medium">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Receipts Table --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 border-b border-gray-100 text-gray-500 font-semibold uppercase tracking-wider text-[11px]">
                    <tr>
                        <th class="py-3 px-4">No. Kwitansi</th>
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Customer</th>
                        <th class="py-3 px-4">Metode & Akun Kas/Bank</th>
                        <th class="py-3 px-4">Invoice Terbayar</th>
                        <th class="py-3 px-4 text-right">Total Penerimaan</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                    @forelse($receipts as $receipt)
                        @php
                            $st = strtolower((string)$receipt->status);
                            $badgeClass = match($st) {
                                'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                default => 'bg-gray-50 text-gray-700 border-gray-200',
                            };
                            $dotClass = match($st) {
                                'paid' => 'bg-emerald-500',
                                'partial' => 'bg-amber-500',
                                'cancelled' => 'bg-rose-500',
                                default => 'bg-gray-400',
                            };
                            $badgeLabel = match($st) {
                                'paid' => 'Lunas',
                                'partial' => 'Sebagian',
                                'cancelled' => 'Dibatalkan',
                                default => ucfirst($receipt->status ?? 'Draft'),
                            };
                            $kwNumber = 'KW-' . str_pad((string)$receipt->id, 6, '0', STR_PAD_LEFT);
                        @endphp
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3.5 px-4">
                                <a href="{{ route('penjualan.receipts.show', $receipt->id) }}" class="font-bold text-[#15803d] hover:underline flex items-center gap-1.5">
                                    <span>{{ $kwNumber }}</span>
                                    @if($receipt->proof_path)
                                        <span title="Bukti transfer terunggah" class="inline-flex p-0.5 rounded text-blue-600 bg-blue-50">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                            </svg>
                                        </span>
                                    @endif
                                </a>
                                <span class="text-[10px] text-gray-400 block mt-0.5">{{ $receipt->cabang?->nama ?? '-' }}</span>
                            </td>

                            <td class="py-3.5 px-4 text-gray-600 whitespace-nowrap">
                                {{ $receipt->payment_date ? \Carbon\Carbon::parse($receipt->payment_date)->format('d/m/Y') : '-' }}
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">{{ $receipt->customer?->name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400">{{ $receipt->customer?->phone ?? '-' }}</div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-gray-900 flex items-center gap-1.5">
                                    <span>{{ $receipt->payment_method }}</span>
                                    @if($receipt->payment_reference)
                                        <span class="text-[11px] text-gray-500 font-normal">({{ $receipt->payment_reference }})</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-500 font-mono">
                                    {{ $receipt->coa ? "({$receipt->coa->code}) {$receipt->coa->name}" : '-' }}
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @forelse($receipt->customerReceiptItem as $item)
                                        @if($item->invoice)
                                            <a href="{{ route('penjualan.invoices.show', $item->invoice->id) }}" class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 text-[10px] font-mono transition">
                                                {{ $item->invoice->invoice_number }}
                                            </a>
                                        @endif
                                    @empty
                                        <span class="text-gray-400 italic text-[11px]">Tidak ada rincian item</span>
                                    @endforelse
                                </div>
                            </td>

                            <td class="py-3.5 px-4 text-right">
                                <div class="font-black text-gray-900 text-sm">
                                    Rp {{ number_format((float)$receipt->total_payment, 0, ',', '.') }}
                                </div>
                                @if((float)$receipt->overpayment_amount > 0)
                                    <span class="inline-flex items-center text-[10px] font-semibold text-amber-700 bg-amber-50 px-1.5 py-0.2 rounded border border-amber-200 mt-0.5" title="Kelebihan dicatat sebagai deposit">
                                        +Deposit Rp {{ number_format((float)$receipt->overpayment_amount, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-semibold {{ $badgeClass }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $dotClass }}"></span>
                                    {{ $badgeLabel }}
                                </span>
                            </td>

                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a
                                        href="{{ route('penjualan.receipts.show', $receipt->id) }}"
                                        class="px-2.5 py-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-semibold text-[11px] transition inline-flex items-center gap-1 shadow-xs"
                                        title="Lihat Detail Kwitansi"
                                    >
                                        <span>Detail</span>
                                    </a>

                                    <a
                                        href="{{ route('penjualan.receipts.pdf', $receipt->id) }}"
                                        target="_blank"
                                        class="p-1.5 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 hover:text-gray-900 transition shadow-xs"
                                        title="Cetak Kwitansi PDF"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                                            <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 mb-3">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-sm font-bold text-gray-900">Belum ada penerimaan pembayaran</span>
                                    <p class="text-xs text-gray-500 max-w-sm mt-1">
                                        Belum ada data penerimaan kwitansi yang cocok dengan filter. Klik tombol di bawah untuk mencatat pelunasan invoice piutang.
                                    </p>
                                    <a
                                        href="{{ route('penjualan.receipts.create') }}"
                                        class="mt-4 px-4 py-2 bg-[#15803d] hover:bg-[#14532d] text-white text-xs font-bold rounded-xl transition shadow-xs"
                                    >
                                        + Buat Penerimaan Baru
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($receipts->hasPages())
            <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                {{ $receipts->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
