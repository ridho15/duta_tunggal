@extends('layouts.app', ['title' => 'Detail Kwitansi ' . ('KW-' . str_pad((string)$customerReceipt->id, 6, '0', STR_PAD_LEFT))])

@section('content')
@php
    $kwNumber = 'KW-' . str_pad((string)$customerReceipt->id, 6, '0', STR_PAD_LEFT);
    $st = strtolower((string)$customerReceipt->status);
    $badgeClass = match($st) {
        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
        'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-slate-50 text-slate-700 border-slate-200',
    };
    $badgeLabel = match($st) {
        'paid' => 'Lunas Penuh',
        'partial' => 'Sebagian (Partial)',
        'cancelled' => 'Dibatalkan',
        default => ucfirst($customerReceipt->status ?? 'Draft'),
    };
@endphp

<div x-data="{ showCancelModal: false }" class="space-y-6 max-w-6xl mx-auto">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.receipts.index') }}" class="hover:text-gray-600 transition">Terima Pembayaran</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $kwNumber }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">{{ $kwNumber }}</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-bold {{ $badgeClass }}">
                    {{ $badgeLabel }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Penerimaan sah atas tagihan piutang dari {{ $customerReceipt->customer?->name ?? 'Pelanggan' }}.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.receipts.pdf', $customerReceipt->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                    <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                </svg>
                <span>Cetak Kwitansi (PDF)</span>
            </a>

            @if($canCancel)
                <button
                    type="button"
                    @click="showCancelModal = true"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Batalkan Penerimaan</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Alert Notifikasi --}}
    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-3 shadow-xs">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div class="flex-1 font-medium">{{ session('error') }}</div>
        </div>
    @endif

    @if($st === 'cancelled')
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-900 text-xs shadow-xs space-y-1">
            <div class="font-extrabold flex items-center gap-2 text-sm">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg>
                <span>Penerimaan ini telah Dibatalkan Secara Resmi</span>
            </div>
            <p>
                Dibatalkan pada: <span class="font-bold">{{ $customerReceipt->cancelled_at ? \Carbon\Carbon::parse($customerReceipt->cancelled_at)->format('d/m/Y H:i') : '-' }}</span>
                @if($customerReceipt->cancelledBy)
                    oleh <span class="font-bold">{{ $customerReceipt->cancelledBy->name }}</span>
                @endif
            </p>
            @if($customerReceipt->cancel_reason)
                <p class="pt-1 text-rose-800 italic">
                    Alasan: "{{ $customerReceipt->cancel_reason }}"
                </p>
            @endif
        </div>
    @endif

    {{-- Kwitansi Information Card --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-xs">
            {{-- Kolom 1: Pelanggan --}}
            <div class="space-y-1">
                <span class="text-gray-400 font-bold uppercase tracking-wider text-[10px]">Telah Terima Dari:</span>
                <div class="text-sm font-extrabold text-gray-900">{{ $customerReceipt->customer?->name ?? '-' }}</div>
                <div class="text-gray-500">{{ $customerReceipt->customer?->phone ?? '-' }}</div>
                <div class="text-gray-400 text-[11px]">{{ $customerReceipt->customer?->address ?? '' }}</div>
            </div>

            {{-- Kolom 2: Tanggal & Cabang --}}
            <div class="space-y-1">
                <span class="text-gray-400 font-bold uppercase tracking-wider text-[10px]">Tanggal & Lokasi:</span>
                <div class="font-bold text-gray-900">
                    {{ $customerReceipt->payment_date ? \Carbon\Carbon::parse($customerReceipt->payment_date)->isoFormat('dddd, D MMMM Y') : '-' }}
                </div>
                <div class="text-gray-600">Cabang: <span class="font-semibold">{{ $customerReceipt->cabang?->nama ?? '-' }}</span></div>
                <div class="text-gray-400 text-[11px]">Dibuat oleh: {{ $customerReceipt->createdBy?->name ?? '-' }}</div>
            </div>

            {{-- Kolom 3: Metode & Akun Kas/Bank --}}
            <div class="space-y-1">
                <span class="text-gray-400 font-bold uppercase tracking-wider text-[10px]">Metode Pembayaran:</span>
                <div class="font-extrabold text-gray-900 text-sm flex items-center gap-1.5">
                    <span>{{ $customerReceipt->payment_method }}</span>
                    @if($customerReceipt->bank_name)
                        <span class="text-gray-500 font-normal">({{ $customerReceipt->bank_name }})</span>
                    @endif
                </div>
                <div class="font-mono text-gray-600 text-[11px]">
                    {{ $customerReceipt->coa ? "({$customerReceipt->coa->code}) {$customerReceipt->coa->name}" : '-' }}
                </div>
                @if($customerReceipt->payment_reference)
                    <div class="text-[11px] text-gray-500">
                        Ref: <span class="font-bold text-gray-800 font-mono">{{ $customerReceipt->payment_reference }}</span>
                    </div>
                @endif
            </div>

            {{-- Kolom 4: Bukti & Catatan --}}
            <div class="space-y-1">
                <span class="text-gray-400 font-bold uppercase tracking-wider text-[10px]">Bukti & Keterangan:</span>
                @if($customerReceipt->proof_path)
                    <div>
                        <a
                            href="{{ route('customer-receipts.proof', $customerReceipt->id) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg font-bold text-[11px] transition border border-blue-200"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <span>Lihat Bukti Transfer</span>
                        </a>
                    </div>
                @else
                    <span class="text-gray-400 italic block">Tidak ada slip/bukti</span>
                @endif

                @if($customerReceipt->notes)
                    <div class="text-gray-600 text-[11px] pt-1">
                        "{{ $customerReceipt->notes }}"
                    </div>
                @endif
            </div>
        </div>

        {{-- Box Jumlah Uang & Terbilang --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-gray-100">
            <div class="p-4 bg-emerald-50/70 border border-emerald-200 rounded-xl md:col-span-1">
                <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Total Nominal Diterima</span>
                <div class="text-2xl font-black text-[#15803d] mt-1">
                    Rp {{ number_format((float)$customerReceipt->total_payment, 0, ',', '.') }}
                </div>
            </div>

            <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl md:col-span-2 flex flex-col justify-center">
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Terbilang</span>
                <div class="text-xs font-semibold text-gray-800 italic mt-1">
                    "{{ $doc['amount_words'] ?? '-' }}"
                </div>
            </div>
        </div>

        {{-- Kelebihan Bayar Sebagai Deposit (Bila Ada) --}}
        @if((float)$customerReceipt->overpayment_amount > 0 && $customerReceipt->deposit)
            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center font-black">
                        +
                    </div>
                    <div>
                        <span class="font-bold text-amber-900 block">Kelebihan Bayar Dicatat Sebagai Deposit Pelanggan</span>
                        <span class="text-amber-800 text-[11px]">
                            Nomor Deposit: <span class="font-bold font-mono">{{ $customerReceipt->deposit->deposit_number }}</span> —
                            Saldo: Rp {{ number_format((float)$customerReceipt->deposit->amount, 0, ',', '.') }}
                        </span>
                    </div>
                </div>
                <div class="text-amber-900 font-extrabold text-sm">
                    Rp {{ number_format((float)$customerReceipt->overpayment_amount, 0, ',', '.') }}
                </div>
            </div>
        @endif
    </div>

    {{-- Kartu Tabel Alokasi Invoice Piutang --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
        <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
            <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span>Rincian Alokasi Pelunasan Invoice</span>
        </h2>

        <div class="border border-gray-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-4">No. Invoice Tagihan</th>
                            <th class="py-3 px-4">Tanggal Invoice</th>
                            <th class="py-3 px-4">Status Invoice</th>
                            <th class="py-3 px-4 text-right">Total Tagihan</th>
                            <th class="py-3 px-4 text-right">Sisa Piutang Saat Ini</th>
                            <th class="py-3 px-4 text-right">Nominal Dibayar (Kwitansi Ini)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse($customerReceipt->customerReceiptItem as $item)
                            @php
                                $inv = $item->invoice;
                                $ar = $inv?->accountReceivable;
                            @endphp
                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-3 px-4">
                                    @if($inv)
                                        <a href="{{ route('penjualan.invoices.show', $inv->id) }}" class="font-bold text-[#15803d] hover:underline">
                                            {{ $inv->invoice_number }}
                                        </a>
                                    @else
                                        <span class="font-mono text-gray-500">Invoice #{{ $item->invoice_id }}</span>
                                    @endif
                                </td>

                                <td class="py-3 px-4 text-gray-600">
                                    {{ $inv?->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}
                                </td>

                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $inv?->status === 'paid' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">
                                        {{ $inv?->status ?? '-' }}
                                    </span>
                                </td>

                                <td class="py-3 px-4 text-right text-gray-900 font-semibold">
                                    Rp {{ number_format((float)($inv?->total ?? 0), 0, ',', '.') }}
                                </td>

                                <td class="py-3 px-4 text-right font-bold text-amber-900">
                                    Rp {{ number_format((float)($ar?->remaining ?? 0), 0, ',', '.') }}
                                </td>

                                <td class="py-3 px-4 text-right font-black text-[#15803d] text-sm">
                                    Rp {{ number_format((float)$item->amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-gray-400 italic">
                                    Tidak ada rincian item invoice
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50/80 font-bold text-gray-900 border-t border-gray-200">
                        <tr>
                            <td colspan="5" class="py-3 px-4 text-right uppercase text-[11px] text-gray-500">
                                Total Alokasi:
                            </td>
                            <td class="py-3 px-4 text-right font-black text-[#15803d] text-sm">
                                Rp {{ number_format((float)$customerReceipt->customerReceiptItem->sum('amount'), 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Kartu Jurnal Akuntansi --}}
    @if($customerReceipt->journalEntries->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span>Entri Buku Besar & Jurnal Otomatis (Ledger)</span>
            </h2>

            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">Tanggal</th>
                                <th class="py-2.5 px-3">No. Jurnal</th>
                                <th class="py-2.5 px-3">Kode & Nama Akun</th>
                                <th class="py-2.5 px-3">Keterangan</th>
                                <th class="py-2.5 px-3 text-right">Debit (Rp)</th>
                                <th class="py-2.5 px-3 text-right">Kredit (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                            @foreach($customerReceipt->journalEntries as $entry)
                                <tr class="hover:bg-gray-50/70 transition {{ $entry->is_reversal ? 'bg-rose-50/30' : '' }}">
                                    <td class="py-2.5 px-3 text-gray-500 whitespace-nowrap">
                                        {{ $entry->transaction_date ? \Carbon\Carbon::parse($entry->transaction_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono text-gray-800 font-semibold">
                                        {{ $entry->entry_number ?? '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 font-mono">
                                        <span class="font-bold text-gray-900">{{ $entry->coa?->code }}</span> - {{ $entry->coa?->name }}
                                    </td>
                                    <td class="py-2.5 px-3 text-gray-600">
                                        {{ $entry->description ?? '-' }}
                                        @if($entry->is_reversal)
                                            <span class="inline-flex px-1.5 py-0.2 rounded text-[9px] bg-rose-100 text-rose-800 font-bold ml-1">Pembalik</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold {{ (float)$entry->debit > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ (float)$entry->debit > 0 ? number_format((float)$entry->debit, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-semibold {{ (float)$entry->credit > 0 ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ (float)$entry->credit > 0 ? number_format((float)$entry->credit, 0, ',', '.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Pembatalan Penerimaan --}}
    @if($canCancel)
        <div
            x-show="showCancelModal"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4"
        >
            <div
                @click.away="showCancelModal = false"
                class="bg-white rounded-2xl max-w-lg w-full p-6 space-y-4 shadow-xl border border-gray-100"
            >
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2 text-rose-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h3 class="font-extrabold text-base text-gray-900">Batalkan Penerimaan Customer</h3>
                    </div>
                    <button type="button" @click="showCancelModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="text-xs text-gray-600 space-y-2">
                    <p>
                        Pembatalan penerimaan <span class="font-bold text-gray-900">{{ $kwNumber }}</span> merupakan koreksi resmi:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-gray-700">
                        <li>Jurnal penerimaan akan dibalik secara cermin (reversal entry).</li>
                        <li>Sisa piutang pada seluruh invoice terkait akan dikembalikan ke status belum lunas.</li>
                        <li>Kwitansi ini akan ditandai <span class="font-bold text-rose-700">Dibatalkan</span> dan tidak dapat diubah kembali.</li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('penjualan.receipts.cancel', $customerReceipt->id) }}" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold text-gray-700">
                            Alasan Pembatalan <span class="text-red-500">* (Minimal 10 karakter)</span>
                        </label>
                        <textarea
                            name="reason"
                            rows="3"
                            required
                            minlength="10"
                            placeholder="Jelaskan alasan resmi pembatalan kwitansi ini (misal: transfer batal, salah alokasi invoice)..."
                            class="w-full text-xs p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showCancelModal = false"
                            class="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl transition"
                        >
                            Tutup
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-2 text-xs font-extrabold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition"
                        >
                            Konfirmasi Batalkan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
@endsection
