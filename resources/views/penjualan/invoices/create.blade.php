@extends('layouts.app', ['title' => 'Buat Faktur Penjualan'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div>
        <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
            <a href="{{ route('penjualan.invoices.index') }}" class="hover:text-gray-600 transition">Invoice Penjualan</a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-700 font-semibold">Buat Baru</span>
        </nav>
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Terbitkan Faktur Penjualan</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            Pilih Pesanan Penjualan (SO), centang Surat Jalan resmi yang telah terbit, dan tentukan jatuh tempo pembayaran.
        </p>
    </div>

    @if(empty($groupedSJs))
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center space-y-3">
            <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2"></path>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">Belum Ada Surat Jalan yang Siap Difakturkan</h3>
            <p class="text-xs text-gray-600 max-w-md mx-auto">
                Faktur Penjualan hanya dapat dibuat dari Surat Jalan berstatus <span class="font-bold">Terbit</span> yang belum pernah difakturkan pada invoice aktif lain.
            </p>
            <div class="pt-2">
                <a href="{{ route('penjualan.sj.index') }}" class="inline-flex items-center gap-2 bg-[#15803d] text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-[#14532d] transition">
                    Lihat Daftar Surat Jalan
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('penjualan.invoices.store') }}" id="invoiceForm" class="space-y-6">
            @csrf

            <input type="hidden" name="cabang_id" value="{{ $activeCabang->id ?? 1 }}">

            {{-- SECTION 1: Pilih SO & Customer --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">1</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Pilih Pesanan Penjualan (SO)</h2>
                        <p class="text-xs text-gray-400">Invoice menagih Surat Jalan yang berasal dari satu Customer & SO yang sama.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Pilih Transaksi SO <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="soSelect"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold text-gray-800 focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] bg-white"
                            onchange="selectSO(this.value)"
                        >
                            <option value="">-- Pilih Pesanan Penjualan --</option>
                            @foreach($groupedSJs as $soData)
                                <option value="{{ $soData['so_id'] }}" {{ (string)$soId === (string)$soData['so_id'] ? 'selected' : '' }}>
                                    {{ $soData['so_number'] }} · {{ $soData['customer']->name ?? 'Customer' }} ({{ count($soData['surat_jalans']) }} SJ Terbit)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="customerInfoBox" class="p-3 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between text-xs">
                        <div>
                            <span class="text-gray-400 block text-[11px]">Customer & Termin Pembayaran:</span>
                            <span id="custName" class="font-bold text-gray-800 text-sm block">-</span>
                            <span id="custTerms" class="text-gray-500 text-[11px] block mt-0.5">Pilih SO terlebih dahulu</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 2: Centang Surat Jalan --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">2</span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Centang Surat Jalan yang Ditagihkan</h2>
                            <p class="text-xs text-gray-400">Harga dan nominal tagihan ditarik otomatis dari master SO.</p>
                        </div>
                    </div>
                    <span id="sjCountBadge" class="text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg">
                        0 SJ dipilih
                    </span>
                </div>

                {{-- Container SJ Cards --}}
                <div id="sjListContainer" class="space-y-3">
                    @foreach($groupedSJs as $soData)
                        <div class="so-sj-group space-y-3" id="so-sj-group-{{ $soData['so_id'] }}" style="display: none;">
                            @foreach($soData['surat_jalans'] as $sj)
                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 hover:border-emerald-500 hover:bg-emerald-50/20 cursor-pointer transition has-checked:border-emerald-600 has-checked:bg-emerald-50/40"
                                >
                                    <input
                                        type="checkbox"
                                        name="surat_jalan_ids[]"
                                        value="{{ $sj->id }}"
                                        class="sj-checkbox w-5 h-5 mt-0.5 rounded text-[#15803d] focus:ring-[#15803d]"
                                        onchange="updateSelectedCount()"
                                    >
                                    <div class="flex-1 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="font-extrabold text-gray-900 text-sm">{{ $sj->sj_number }}</span>
                                                <span class="text-xs font-semibold px-2 py-0.5 bg-emerald-50 text-emerald-800 rounded border border-emerald-200">
                                                    Status: Terbit
                                                </span>
                                            </div>
                                            <span class="text-xs text-gray-400">{{ $sj->issued_at ? $sj->issued_at->format('d M Y') : '' }}</span>
                                        </div>

                                        {{-- DOs & Items Summary --}}
                                        <div class="bg-white rounded-lg p-2.5 border border-gray-100 text-xs divide-y divide-gray-50">
                                            @foreach($sj->deliveryOrder as $do)
                                                @foreach($do->deliveryOrderItem as $item)
                                                    <div class="flex justify-between py-1">
                                                        <span class="text-gray-700 font-medium">
                                                            {{ $item->product->name ?? 'Produk' }}
                                                            <span class="text-gray-400 text-[11px]">({{ $do->do_number }} · {{ $do->warehouse->name ?? 'Gudang' }})</span>
                                                        </span>
                                                        <span class="font-bold text-gray-900">
                                                            {{ (int)$item->quantity }} {{ $item->product->uom->abbreviation ?? 'pcs' }}
                                                        </span>
                                                    </div>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    <div id="noSoSelectedHint" class="py-8 text-center text-gray-400 text-xs">
                        Silakan pilih Pesanan Penjualan (SO) di atas untuk menampilkan Surat Jalan terkait.
                    </div>
                </div>
            </div>

            {{-- SECTION 3: Ketentuan Pembayaran & Biaya --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">3</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Ketentuan Jatuh Tempo & Ongkos Kirim</h2>
                        <p class="text-xs text-gray-400">Atur tanggal jatuh tempo dan biaya pengiriman yang ditagihkan.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Tanggal Faktur <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="invoice_date"
                            value="{{ date('Y-m-d') }}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Tanggal Jatuh Tempo <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="due_date"
                            id="dueDateInput"
                            value="{{ date('Y-m-d', strtotime('+30 days')) }}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Ongkos Kirim (Rp)
                        </label>
                        <input
                            type="number"
                            name="other_fee"
                            id="otherFeeInput"
                            value="0"
                            min="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                        >
                    </div>

                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Catatan Faktur</label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Catatan rekening transfer atau keterangan khusus untuk customer..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm text-gray-800 focus:outline-none focus:border-[#15803d]"
                        ></textarea>
                    </div>
                </div>
            </div>

            {{-- Submit Actions Bar --}}
            <div class="flex items-center justify-between bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
                <a href="{{ route('penjualan.invoices.index') }}" class="text-xs font-bold text-gray-500 hover:text-gray-800 px-3 py-2">
                    Batal
                </a>

                <button
                    type="submit"
                    id="submitBtn"
                    disabled
                    class="bg-[#15803d] hover:bg-[#14532d] disabled:bg-gray-300 disabled:cursor-not-allowed text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-sm transition flex items-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Terbitkan Faktur Penjualan</span>
                </button>
            </div>
        </form>
    @endif

</div>

@push('scripts')
<script>
    const soDataMap = @json($groupedSJs);

    function selectSO(soId) {
        document.querySelectorAll('.so-sj-group').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.sj-checkbox').forEach(cb => cb.checked = false);

        const hint = document.getElementById('noSoSelectedHint');
        const custName = document.getElementById('custName');
        const custTerms = document.getElementById('custTerms');
        const otherFeeInput = document.getElementById('otherFeeInput');

        if (!soId || !soDataMap[soId]) {
            if (hint) hint.style.display = 'block';
            custName.innerText = '-';
            custTerms.innerText = 'Pilih SO terlebih dahulu';
            updateSelectedCount();
            return;
        }

        if (hint) hint.style.display = 'none';

        const data = soDataMap[soId];
        custName.innerText = data.customer ? data.customer.name : 'Customer';
        custTerms.innerText = `Tipe: ${data.customer ? (data.customer.tipe_pembayaran || 'Kredit') : 'Kredit'} · Pajak: ${data.tipe_pajak}`;

        if (otherFeeInput) {
            otherFeeInput.value = data.shipping_cost || 0;
        }

        const targetGroup = document.getElementById(`so-sj-group-${soId}`);
        if (targetGroup) {
            targetGroup.style.display = 'block';
            targetGroup.querySelectorAll('.sj-checkbox').forEach(cb => cb.checked = true);
        }

        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.sj-checkbox:checked').length;
        const badge = document.getElementById('sjCountBadge');
        const btn = document.getElementById('submitBtn');

        if (badge) {
            badge.innerText = `${checked} SJ dipilih`;
            if (checked > 0) {
                badge.className = 'text-xs font-bold px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-lg';
            } else {
                badge.className = 'text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg';
            }
        }

        if (btn) {
            btn.disabled = checked === 0;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const initialSo = document.getElementById('soSelect').value;
        if (initialSo) {
            selectSO(initialSo);
        }
    });
</script>
@endpush
@endsection
