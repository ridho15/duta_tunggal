@extends('layouts.app', ['title' => 'Catat Penerimaan Customer'])

@section('content')
<div
    x-data="customerReceiptForm({
        initialCustomerId: '{{ $preselectedCustomerId ?? '' }}',
        initialInvoiceId: '{{ $preselectedInvoiceId ?? '' }}',
        initialInvoices: {{ json_encode($initialInvoices) }},
        initialDepositBalance: {{ $customerDepositBalance ?? 0 }},
        coasByMethod: {{ json_encode($coasByMethod) }},
        defaultCoaByMethod: {{ json_encode($defaultCoaByMethod) }},
        invoicesUrl: '{{ url('penjualan/terima-pembayaran/customer-invoices') }}'
    })"
    class="space-y-6 max-w-6xl mx-auto"
>

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.receipts.index') }}" class="hover:text-gray-600 transition">Terima Pembayaran</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Formulir Penerimaan</span>
            </nav>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Catat Penerimaan Customer</h1>
            <p class="text-xs text-gray-500 mt-0.5">
                Alokasi pembayaran uang masuk ke invoice piutang dagang, penerbitan kwitansi sah, dan pencatatan deposit.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.receipts.index') }}"
                class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                Kembali
            </a>
        </div>
    </div>

    {{-- Alert Galat Validasi --}}
    @if($errors->any())
        <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-xs shadow-xs">
            <div class="font-bold flex items-center gap-1.5 mb-1 text-sm">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>Mohon periksa kesalahan input berikut:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-red-700">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('penjualan.receipts.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        {{-- Kartu 1: Pelanggan & Informasi Pembayaran --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-5">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center text-xs font-black">1</span>
                <span>Pilih Pelanggan & Parameter Pembayaran</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                {{-- Pilih Customer --}}
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700">
                        Customer (Pelanggan) <span class="text-red-500">*</span>
                    </label>
                    <select data-searchable
                        name="customer_id"
                        x-model="customerId"
                        @change="onCustomerChange"
                        required
                        class="w-full text-xs font-semibold py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white"
                    >
                        <option value="">-- Pilih Customer yang memiliki Piutang --</option>
                        @if($customersWithDebt->isNotEmpty())
                            <optgroup label="Pelanggan dengan Tagihan Belum Lunas">
                                @foreach($customersWithDebt as $c)
                                    <option value="{{ $c->id }}" {{ (string)old('customer_id', $preselectedCustomerId) === (string)$c->id ? 'selected' : '' }}>
                                        {{ $c->name }} (Telp: {{ $c->phone ?? '-' }})
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        <optgroup label="Semua Pelanggan">
                            @foreach($allCustomers as $c)
                                <option value="{{ $c->id }}" {{ (string)old('customer_id', $preselectedCustomerId) === (string)$c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>

                {{-- Tanggal Pembayaran --}}
                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-gray-700">
                        Tanggal Pembayaran <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        name="payment_date"
                        value="{{ old('payment_date', now()->toDateString()) }}"
                        required
                        class="w-full text-xs py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>
            </div>

            {{-- Kartu Info Saldo Deposit Customer --}}
            <div x-show="customerId && depositBalance > 0" x-cloak class="p-3.5 bg-blue-50 border border-blue-200 rounded-xl text-xs flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <span class="font-bold text-blue-900">Pelanggan ini memiliki saldo Deposit:</span>
                        <span class="font-black text-blue-900 ml-1" x-text="formatRupiah(depositBalance)"></span>
                    </div>
                </div>
                <button
                    type="button"
                    @click="setPaymentMethod('Deposit')"
                    class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-[11px] shadow-xs transition"
                >
                    Gunakan Deposit
                </button>
            </div>

            {{-- Metode Pembayaran Selector --}}
            <div class="space-y-2 pt-2">
                <label class="block text-xs font-bold text-gray-700">
                    Metode Pembayaran <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                    <template x-for="m in ['Cash', 'Transfer', 'Giro', 'Cheque', 'Deposit']" :key="m">
                        <button
                            type="button"
                            @click="setPaymentMethod(m)"
                            :class="paymentMethod === m ? 'border-[#15803d] bg-emerald-50/75 text-[#15803d] ring-2 ring-[#15803d]/30 font-bold' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 font-medium'"
                            class="p-3 border rounded-xl text-center text-xs transition flex flex-col items-center justify-center gap-1.5 shadow-xs"
                        >
                            <span x-text="methodLabel(m)"></span>
                        </button>
                    </template>
                </div>
                <input type="hidden" name="payment_method" :value="paymentMethod">
            </div>

            {{-- Rekening Kas/Bank, Referensi & Bukti Transfer --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2">
                {{-- Akun COA Penerima --}}
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700">
                        Akun Kas / Bank Penerima <span class="text-red-500">*</span>
                    </label>
                    <select data-searchable
                        name="coa_id"
                        x-ref="coaSelect"
                        x-model="selectedCoaId"
                        required
                        class="w-full text-xs py-2.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d] bg-white font-mono"
                    >
                        @foreach(($coasByMethod['Cash'] ?? []) as $id => $label)
                            <option value="{{ $id }}" {{ (string)($defaultCoaByMethod['Cash'] ?? '') === (string)$id ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <span class="text-[11px] text-gray-400 block">Akun disaring otomatis sesuai metode pembayaran terpilih.</span>
                </div>

                {{-- Nomor Referensi --}}
                <div class="space-y-1.5" x-show="requiresReference" x-cloak>
                    <label class="block text-xs font-bold text-gray-700">
                        <span x-text="referenceLabel"></span> <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        name="payment_reference"
                        x-model="paymentReference"
                        :required="requiresReference"
                        :disabled="!requiresReference"
                        placeholder="Contoh: TRF-BCA-98124 / NO-GIRO"
                        class="w-full text-xs py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Nama Bank Pengirim/Tujuan --}}
                <div class="space-y-1.5" x-show="requiresReference" x-cloak>
                    <label class="block text-xs font-bold text-gray-700">
                        Nama Bank
                    </label>
                    <input
                        type="text"
                        name="bank_name"
                        value="{{ old('bank_name') }}"
                        :disabled="!requiresReference"
                        placeholder="Contoh: BCA, Mandiri, BRI"
                        class="w-full text-xs py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >
                </div>

                {{-- Upload Bukti Transfer --}}
                <div class="space-y-1.5 md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700">
                        Unggah Bukti Transfer / Slip (Opsional)
                    </label>
                    <input
                        type="file"
                        name="proof"
                        accept="image/jpeg,image/png,image/jpg,application/pdf"
                        class="w-full text-xs py-1.5 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200"
                    >
                    <span class="text-[11px] text-gray-400 block">Format didukung: JPG, PNG, PDF (Maks. 5MB). Tersimpan aman di storage privat.</span>
                </div>

                {{-- Catatan Kwitansi --}}
                <div class="space-y-1.5 md:col-span-3">
                    <label class="block text-xs font-bold text-gray-700">
                        Catatan / Keterangan Kwitansi
                    </label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="Catatan tambahan untuk dokumen kwitansi..."
                        class="w-full text-xs py-2 px-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                    >{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Kartu 2: Tabel Alokasi Invoice Tagihan --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-gray-100 pb-3">
                <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center text-xs font-black">2</span>
                    <span>Pilih Invoice Tagihan & Alokasi Nominal</span>
                </h2>

                <div class="flex items-center gap-2" x-show="invoices.length > 0">
                    <button
                        type="button"
                        @click="selectAllInvoices(true)"
                        class="text-[11px] font-bold text-[#15803d] hover:underline"
                    >
                        Pilih & Bayar Penuh Semua
                    </button>
                    <span class="text-gray-300">|</span>
                    <button
                        type="button"
                        @click="selectAllInvoices(false)"
                        class="text-[11px] font-semibold text-gray-500 hover:underline"
                    >
                        Batalkan Pilihan
                    </button>
                </div>
            </div>

            {{-- Loading State --}}
            <div x-show="isLoading" class="py-12 text-center text-gray-400 text-xs">
                <div class="inline-block animate-spin w-6 h-6 border-2 border-emerald-600 border-t-transparent rounded-full mb-2"></div>
                <p>Memuat daftar invoice piutang pelanggan...</p>
            </div>

            {{-- Empty Customer State --}}
            <div x-show="!customerId && !isLoading" class="py-10 text-center text-gray-400 text-xs">
                <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span>Silakan pilih customer di atas untuk melihat invoice yang belum lunas.</span>
            </div>

            {{-- Empty Invoices for Selected Customer --}}
            <div x-show="customerId && invoices.length === 0 && !isLoading" class="py-10 text-center text-gray-500 text-xs">
                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <span class="font-bold text-gray-800 text-sm block">Tidak ada tagihan aktif</span>
                <span class="text-gray-400 mt-0.5 block">Customer ini tidak memiliki invoice dengan sisa piutang dagang.</span>
            </div>

            {{-- Tabel Invoices --}}
            <div x-show="invoices.length > 0 && !isLoading" class="border border-gray-200 rounded-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3 w-10 text-center">
                                    <input
                                        type="checkbox"
                                        @change="toggleAll($event.target.checked)"
                                        :checked="isAllSelected"
                                        class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                                    >
                                </th>
                                <th class="py-2.5 px-3">No. Invoice & Cabang</th>
                                <th class="py-2.5 px-3">Tanggal / Jatuh Tempo</th>
                                <th class="py-2.5 px-3 text-right">Total Tagihan</th>
                                <th class="py-2.5 px-3 text-right">Sudah Dibayar</th>
                                <th class="py-2.5 px-3 text-right">Sisa Tagihan</th>
                                <th class="py-2.5 px-3 text-right w-44">Nominal Diterima (Rp)</th>
                                <th class="py-2.5 px-3 text-center w-24">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                            <template x-for="(inv, idx) in invoices" :key="inv.id">
                                <tr :class="selectedInvoices.includes(inv.id) ? 'bg-emerald-50/40' : 'hover:bg-gray-50/60'" class="transition">
                                    <td class="py-3 px-3 text-center">
                                        <input
                                            type="checkbox"
                                            :value="inv.id"
                                            name="selected_invoices[]"
                                            x-model="selectedInvoices"
                                            @change="onToggleInvoice(inv.id)"
                                            class="rounded border-gray-300 text-[#15803d] focus:ring-[#15803d]"
                                        >
                                    </td>

                                    <td class="py-3 px-3">
                                        <div class="font-bold text-gray-900" x-text="inv.invoice_number"></div>
                                        <div class="text-[10px] text-gray-400" x-text="inv.cabang_nama"></div>
                                    </td>

                                    <td class="py-3 px-3">
                                        <div class="text-gray-700" x-text="inv.invoice_date"></div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-[11px] text-gray-400" x-text="'Jatuh Tempo: ' + inv.due_date"></span>
                                            <span x-show="inv.is_overdue" class="text-[9px] font-bold text-red-700 bg-red-50 border border-red-200 px-1 py-0.2 rounded">
                                                Lewat
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-right text-gray-900 font-semibold" x-text="inv.total_formatted"></td>
                                    <td class="py-3 px-3 text-right text-gray-500" x-text="inv.paid_formatted"></td>
                                    <td class="py-3 px-3 text-right font-bold text-amber-900" x-text="inv.remaining_formatted"></td>

                                    <td class="py-3 px-3 text-right">
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-[10px] font-semibold text-gray-400 pointer-events-none">Rp</span>
                                            <input
                                                type="text"
                                                :value="getFormattedAmount(inv.id)"
                                                @input="handleInvoiceAmountInput(inv.id, $event.target.value)"
                                                :disabled="!selectedInvoices.includes(inv.id)"
                                                placeholder="0"
                                                class="w-full text-right text-xs py-1.5 pl-8 pr-2.5 border rounded-lg font-mono font-bold focus:ring-2 focus:ring-[#15803d] focus:border-[#15803d]"
                                                :class="selectedInvoices.includes(inv.id) ? 'bg-white border-gray-300 text-gray-900' : 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed'"
                                            >
                                            <input
                                                type="hidden"
                                                :name="'invoice_receipts[' + inv.id + ']'"
                                                :value="invoiceAmounts[inv.id] || 0"
                                            >
                                        </div>
                                    </td>

                                    <td class="py-3 px-3 text-center">
                                        <button
                                            type="button"
                                            @click="payFull(inv.id)"
                                            class="px-2 py-1 bg-gray-100 hover:bg-emerald-100 text-gray-700 hover:text-emerald-800 rounded font-semibold text-[10px] transition border border-gray-200"
                                        >
                                            Bayar Penuh
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Kartu 3: Ringkasan Total & Submit --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2 border-b border-gray-100 pb-3">
                <span class="w-6 h-6 rounded-lg bg-emerald-100 text-[#15803d] flex items-center justify-center text-xs font-black">3</span>
                <span>Ringkasan Alokasi Pembayaran</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <span class="text-gray-400 block text-[11px] font-semibold">Total Sisa Tagihan Terpilih:</span>
                    <div class="text-lg font-black text-gray-900 mt-0.5" x-text="formatRupiah(totalSelectedRemaining)"></div>
                    <span class="text-[10px] text-gray-500" x-text="selectedInvoices.length + ' invoice dipilih'"></span>
                </div>

                <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                    <span class="text-emerald-700 block text-[11px] font-semibold">Total Pembayaran Dialokasikan:</span>
                    <div class="text-xl font-black text-[#15803d] mt-0.5" x-text="formatRupiah(totalAllocated)"></div>
                    <span class="text-[10px] text-emerald-800">Nominal yang memotong piutang</span>
                </div>

                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200" :class="totalOverpayment > 0 ? 'block' : 'opacity-60'">
                    <span class="text-amber-700 block text-[11px] font-semibold">Kelebihan Bayar:</span>
                    <div class="text-lg font-black text-amber-900 mt-0.5" x-text="formatRupiah(totalOverpayment)"></div>
                    <span class="text-[10px] text-amber-800" x-text="totalOverpayment > 0 ? 'Melebihi sisa tagihan invoice' : 'Tidak ada kelebihan'"></span>
                </div>
            </div>

            {{-- Opsi Kelebihan Bayar Sebagai Deposit --}}
            <div x-show="totalOverpayment > 0" x-cloak class="p-4 bg-amber-50 border border-amber-300 rounded-xl space-y-2">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        name="overpayment_as_deposit"
                        value="1"
                        x-model="overpaymentAsDeposit"
                        class="rounded border-amber-400 text-[#15803d] focus:ring-[#15803d] mt-0.5"
                    >
                    <div class="text-xs">
                        <span class="font-bold text-amber-900 block">
                            Catat kelebihan bayar (<span x-text="formatRupiah(totalOverpayment)"></span>) sebagai Deposit Customer
                        </span>
                        <span class="text-amber-800 text-[11px]">
                            Kelebihan dana akan dicatat ke akun Deposit Pelanggan (2160.04) dan dapat digunakan untuk pembayaran tagihan berikutnya.
                        </span>
                    </div>
                </label>
                <div x-show="!overpaymentAsDeposit" class="text-[11px] text-red-600 font-bold pl-7">
                    Perhatian: Transaksi akan ditolak oleh sistem jika nominal melebihi tagihan tanpa mencentang opsi Deposit.
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 border-t border-gray-100">
                <a
                    href="{{ route('penjualan.receipts.index') }}"
                    class="px-5 py-2.5 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs transition w-full sm:w-auto text-center"
                >
                    Batalkan
                </a>

                <button
                    type="submit"
                    :disabled="selectedInvoices.length === 0 || totalAllocated <= 0 || (totalOverpayment > 0 && !overpaymentAsDeposit)"
                    class="px-6 py-3 rounded-xl bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-black text-sm shadow-sm transition flex items-center justify-center gap-2 w-full sm:w-auto"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span>Simpan & Proses Penerimaan</span>
                    <span x-show="totalAllocated > 0" x-text="'(' + formatRupiah(totalAllocated + (overpaymentAsDeposit ? totalOverpayment : 0)) + ')'"></span>
                </button>
            </div>
        </div>

    </form>
</div>

<script>
function customerReceiptForm(config) {
    return {
        customerId: config.initialCustomerId || '',
        initialInvoiceId: config.initialInvoiceId || '',
        invoices: config.initialInvoices || [],
        depositBalance: config.initialDepositBalance || 0,
        coasByMethod: config.coasByMethod || {},
        defaultCoaByMethod: config.defaultCoaByMethod || {},
        invoicesUrl: config.invoicesUrl,

        paymentMethod: 'Cash',
        selectedCoaId: '',
        paymentReference: '',
        selectedInvoices: [],
        invoiceAmounts: {},
        overpaymentAsDeposit: false,
        isLoading: false,

        init() {
            this.setPaymentMethod('Cash');

            // Jika preselected invoices tersedia, pilih invoice
            if (this.invoices.length > 0) {
                if (this.initialInvoiceId) {
                    const targetId = parseInt(this.initialInvoiceId);
                    const match = this.invoices.find(inv => inv.id === targetId);
                    if (match) {
                        this.selectedInvoices = [targetId];
                        this.invoiceAmounts[targetId] = match.remaining;
                    }
                } else {
                    // Pilih invoice pertama secara default
                    const first = this.invoices[0];
                    this.selectedInvoices = [first.id];
                    this.invoiceAmounts[first.id] = first.remaining;
                }
            }
        },

        get availableCoas() {
            return this.coasByMethod[this.paymentMethod] || {};
        },

        get requiresReference() {
            return ['Transfer', 'Giro', 'Cheque'].includes(this.paymentMethod);
        },

        get referenceLabel() {
            if (this.paymentMethod === 'Transfer') return 'No. Referensi Transfer';
            if (this.paymentMethod === 'Giro') return 'No. Giro';
            if (this.paymentMethod === 'Cheque') return 'No. Cek';
            return 'No. Referensi';
        },

        methodLabel(method) {
            const labels = {
                'Cash': 'Tunai (Cash)',
                'Transfer': 'Transfer Bank',
                'Giro': 'Giro',
                'Cheque': 'Cek',
                'Deposit': 'Deposit Pelanggan'
            };
            return labels[method] || method;
        },

        updateCoaOptions() {
            const select = this.$refs.coaSelect;
            if (!select) return;
            const coas = this.coasByMethod[this.paymentMethod] || {};
            select.innerHTML = '';
            const defId = this.defaultCoaByMethod[this.paymentMethod] || Object.keys(coas)[0] || '';
            for (const [id, label] of Object.entries(coas)) {
                const opt = document.createElement('option');
                opt.value = id;
                opt.textContent = label;
                if (String(id) === String(defId)) {
                    opt.selected = true;
                }
                select.appendChild(opt);
            }
            this.selectedCoaId = defId;
            select.value = defId;
        },

        setPaymentMethod(method) {
            this.paymentMethod = method;
            this.updateCoaOptions();
        },

        async onCustomerChange() {
            if (!this.customerId) {
                this.invoices = [];
                this.selectedInvoices = [];
                this.invoiceAmounts = {};
                this.depositBalance = 0;
                return;
            }

            this.isLoading = true;
            try {
                const res = await fetch(`${this.invoicesUrl}/${this.customerId}`);
                const data = await res.json();
                this.invoices = data.invoices || [];
                this.depositBalance = data.deposit_balance || 0;
                this.selectedInvoices = [];
                this.invoiceAmounts = {};

                // Default: centang dan isi nominal invoice pertama
                if (this.invoices.length > 0) {
                    const first = this.invoices[0];
                    this.selectedInvoices = [first.id];
                    this.invoiceAmounts[first.id] = first.remaining;
                }
            } catch (e) {
                console.error('Failed to load customer invoices', e);
            } finally {
                this.isLoading = false;
            }
        },

        onToggleInvoice(invId) {
            const inv = this.invoices.find(i => i.id === invId);
            if (!inv) return;

            if (this.selectedInvoices.includes(invId)) {
                if (!this.invoiceAmounts[invId] || this.invoiceAmounts[invId] <= 0) {
                    this.invoiceAmounts[invId] = inv.remaining;
                }
            } else {
                delete this.invoiceAmounts[invId];
            }
        },

        getFormattedAmount(invId) {
            const val = this.invoiceAmounts[invId];
            return val > 0 ? Number(val).toLocaleString('id-ID') : '';
        },

        handleInvoiceAmountInput(invId, rawVal) {
            const cleanDigits = String(rawVal).replace(/\D/g, '');
            const numVal = parseFloat(cleanDigits) || 0;
            this.invoiceAmounts[invId] = numVal;
            if (!this.selectedInvoices.includes(invId)) {
                this.selectedInvoices.push(invId);
            }
        },

        onAmountInput(invId) {
            if (!this.selectedInvoices.includes(invId)) {
                this.selectedInvoices.push(invId);
            }
        },

        payFull(invId) {
            const inv = this.invoices.find(i => i.id === invId);
            if (!inv) return;

            if (!this.selectedInvoices.includes(invId)) {
                this.selectedInvoices.push(invId);
            }
            this.invoiceAmounts[invId] = inv.remaining;
        },

        selectAllInvoices(select) {
            if (select) {
                this.selectedInvoices = this.invoices.map(i => i.id);
                this.invoices.forEach(i => {
                    this.invoiceAmounts[i.id] = i.remaining;
                });
            } else {
                this.selectedInvoices = [];
                this.invoiceAmounts = {};
            }
        },

        toggleAll(checked) {
            this.selectAllInvoices(checked);
        },

        get isAllSelected() {
            return this.invoices.length > 0 && this.selectedInvoices.length === this.invoices.length;
        },

        get totalSelectedRemaining() {
            return this.invoices
                .filter(i => this.selectedInvoices.includes(i.id))
                .reduce((sum, i) => sum + (parseFloat(i.remaining) || 0), 0);
        },

        get totalAllocated() {
            return this.selectedInvoices.reduce((sum, id) => {
                const amt = parseFloat(this.invoiceAmounts[id]) || 0;
                const inv = this.invoices.find(i => i.id === id);
                const rem = inv ? parseFloat(inv.remaining) : amt;
                return sum + Math.min(amt, rem);
            }, 0);
        },

        get totalOverpayment() {
            return this.selectedInvoices.reduce((sum, id) => {
                const amt = parseFloat(this.invoiceAmounts[id]) || 0;
                const inv = this.invoices.find(i => i.id === id);
                const rem = inv ? parseFloat(inv.remaining) : amt;
                return sum + Math.max(0, amt - rem);
            }, 0);
        },

        formatRupiah(num) {
            const n = parseFloat(num) || 0;
            return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }
    };
}
</script>
@endsection
