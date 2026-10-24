@extends('layouts.app', ['title' => 'Buat Surat Jalan'])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div>
        <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
            <a href="{{ route('penjualan.sj.index') }}" class="hover:text-gray-600 transition">Surat Jalan</a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-700 font-semibold">Buat Baru</span>
        </nav>
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Terbitkan Surat Jalan</h1>
        <p class="text-xs text-gray-500 mt-0.5">
            Pilih Pesanan Penjualan (SO), centang Delivery Order yang siap dikirim, dan atur armada logistik.
        </p>
    </div>

    @if(empty($groupedDOs))
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center space-y-3">
            <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-full flex items-center justify-center mx-auto">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2"></path>
                </svg>
            </div>
            <h3 class="text-base font-bold text-gray-900">Belum Ada Delivery Order yang Siap Dikirim</h3>
            <p class="text-xs text-gray-600 max-w-md mx-auto">
                Surat Jalan hanya dapat diterbitkan untuk Delivery Order berstatus <span class="font-bold">approved</span> yang belum pernah dibuatkan Surat Jalan aktif.
            </p>
            <div class="pt-2">
                <a href="{{ route('penjualan.so.index') }}" class="inline-flex items-center gap-2 bg-[#15803d] text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-[#14532d] transition">
                    Lihat Pesanan Penjualan
                </a>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('penjualan.sj.store') }}" id="sjForm" class="space-y-6">
            @csrf

            <input type="hidden" name="cabang_id" value="{{ $activeCabang->id ?? 1 }}">

            {{-- SECTION 1: Pilih SO & Customer --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">1</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Pilih Pesanan Penjualan (SO)</h2>
                        <p class="text-xs text-gray-400">Surat Jalan menggabungkan DO-DO yang berasal dari Customer & SO yang sama.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Pilih Transaksi SO <span class="text-red-500">*</span>
                        </label>
                        <select data-searchable
                            id="soSelect"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm font-semibold text-gray-800 focus:outline-none focus:border-[#15803d] focus:ring-1 focus:ring-[#15803d] bg-white"
                            onchange="selectSO(this.value)"
                        >
                            <option value="">-- Pilih Pesanan Penjualan --</option>
                            @foreach($groupedDOs as $soData)
                                <option value="{{ $soData['so_id'] }}" {{ (string)$soId === (string)$soData['so_id'] ? 'selected' : '' }}>
                                    {{ $soData['so_number'] }} · {{ $soData['customer']->name ?? 'Customer' }} ({{ count($soData['delivery_orders']) }} DO Siap)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="customerInfoBox" class="p-3 bg-gray-50 border border-gray-200 rounded-xl flex items-center justify-between text-xs">
                        <div>
                            <span class="text-gray-400 block text-[11px]">Customer & Alamat Kirim:</span>
                            <span id="custName" class="font-bold text-gray-800 text-sm block">-</span>
                            <span id="custAddress" class="text-gray-500 text-[11px] block mt-0.5">Pilih SO terlebih dahulu</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- SECTION 2: Centang Delivery Order --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">2</span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Centang Delivery Order yang Dikirim</h2>
                            <p class="text-xs text-gray-400">Dapat memilih lebih dari 1 DO untuk pengiriman kolektif dari beberapa gudang.</p>
                        </div>
                    </div>
                    <span id="doCountBadge" class="text-xs font-bold px-2.5 py-1 bg-gray-100 text-gray-600 rounded-lg">
                        0 DO dipilih
                    </span>
                </div>

                {{-- Container DO Cards --}}
                <div id="doListContainer" class="space-y-3">
                    @foreach($groupedDOs as $soData)
                        <div class="so-do-group space-y-3" id="so-group-{{ $soData['so_id'] }}" style="display: none;">
                            @foreach($soData['delivery_orders'] as $do)
                                <label
                                    class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 hover:border-emerald-500 hover:bg-emerald-50/20 cursor-pointer transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40"
                                >
                                    <input
                                        type="checkbox"
                                        name="delivery_order_ids[]"
                                        value="{{ $do->id }}"
                                        class="do-checkbox w-5 h-5 mt-0.5 rounded text-[#15803d] focus:ring-[#15803d]"
                                        onchange="updateSelectedCount()"
                                    >
                                    <div class="flex-1 space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="font-extrabold text-gray-900 text-sm">{{ $do->do_number }}</span>
                                                <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-700 rounded border border-gray-200">
                                                    Gudang: {{ $do->warehouse->name ?? '-' }}
                                                </span>
                                            </div>
                                            <span class="text-xs text-gray-400">{{ $do->created_at ? $do->created_at->format('d M Y') : '' }}</span>
                                        </div>

                                        {{-- Items Breakdown --}}
                                        <div class="bg-white rounded-lg p-2.5 border border-gray-100 text-xs divide-y divide-gray-50">
                                            @foreach($do->deliveryOrderItem as $item)
                                                <div class="flex justify-between py-1">
                                                    <span class="text-gray-700 font-medium">
                                                        {{ $item->product->name ?? 'Produk' }}
                                                        <span class="text-gray-400 text-[11px]">({{ $item->product->sku ?? '-' }})</span>
                                                    </span>
                                                    <span class="font-bold text-gray-900">
                                                        {{ (int)$item->quantity }} {{ $item->product->uom->abbreviation ?? 'pcs' }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endforeach
                    <div id="noSoSelectedHint" class="py-8 text-center text-gray-400 text-xs">
                        Silakan pilih Pesanan Penjualan (SO) di atas untuk menampilkan Delivery Order terkait.
                    </div>
                </div>
            </div>

            {{-- SECTION 3: Informasi Armada Logistik --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex items-center gap-3 pb-3 border-b border-gray-100">
                    <span class="w-7 h-7 rounded-full bg-emerald-100 text-[#15803d] font-black text-xs flex items-center justify-center">3</span>
                    <div>
                        <h2 class="text-base font-bold text-gray-900">Pengaturan Armada & Kurir</h2>
                        <p class="text-xs text-gray-400">Pilih kurir internal atau masukkan nomor resi ekspedisi resmi.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Tanggal Terbit --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Tanggal Surat Jalan <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            name="issued_at"
                            value="{{ date('Y-m-d') }}"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                        >
                    </div>

                    {{-- Metode Pengiriman --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Metode Pengiriman <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center gap-2 p-2 border border-gray-200 rounded-xl cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 text-xs font-semibold">
                                <input type="radio" name="delivery_method" value="internal" checked onchange="toggleDeliveryMethod('internal')" class="text-[#15803d] focus:ring-[#15803d]">
                                <span>Kurir Internal</span>
                            </label>
                            <label class="flex items-center gap-2 p-2 border border-gray-200 rounded-xl cursor-pointer has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/40 text-xs font-semibold">
                                <input type="radio" name="delivery_method" value="ekspedisi" onchange="toggleDeliveryMethod('ekspedisi')" class="text-[#15803d] focus:ring-[#15803d]">
                                <span>Ekspedisi / Kargo</span>
                            </label>
                        </div>
                    </div>

                    {{-- Internal Fields --}}
                    <div id="internalGroup" class="contents">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Driver / Supir</label>
                            <x-searchable-select name="driver_id" :options="collect($drivers)->map(fn ($d) => ['id' => $d->id, 'label' => $d->name . ' (' . ($d->phone ?? '-') . ')'])->values()" placeholder="-- Pilih Driver (Opsional) --" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Kendaraan Armada</label>
                            <x-searchable-select name="vehicle_id" :options="collect($vehicles)->map(fn ($v) => ['id' => $v->id, 'label' => $v->plate . ' · ' . $v->type])->values()" placeholder="-- Pilih Kendaraan (Opsional) --" />
                        </div>
                    </div>

                    {{-- Ekspedisi Fields --}}
                    <div id="ekspedisiGroup" class="contents" style="display: none;">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nama Ekspedisi</label>
                            <input
                                type="text"
                                name="expedition_name"
                                placeholder="Contoh: JNE Trucking / Dakota Cargo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nomor Resi / AWB</label>
                            <input
                                type="text"
                                name="tracking_number"
                                placeholder="Nomor Resi Pelacakan"
                                class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm font-medium text-gray-800 focus:outline-none focus:border-[#15803d]"
                            >
                        </div>
                    </div>

                    {{-- Catatan --}}
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Catatan Pengiriman</label>
                        <textarea
                            name="notes"
                            rows="2"
                            placeholder="Catatan tambahan untuk tim logistik atau penerima barang..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-xl text-sm text-gray-800 focus:outline-none focus:border-[#15803d]"
                        ></textarea>
                    </div>
                </div>
            </div>

            {{-- Submit Actions Bar --}}
            <div class="flex items-center justify-between bg-white border border-gray-200 rounded-2xl p-4 shadow-xs">
                <a href="{{ route('penjualan.sj.index') }}" class="text-xs font-bold text-gray-500 hover:text-gray-800 px-3 py-2">
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
                    <span>Terbitkan Surat Jalan Resmi</span>
                </button>
            </div>
        </form>
    @endif

</div>

@push('scripts')
<script>
    const soDataMap = @json($groupedDOs);

    function selectSO(soId) {
        // Sembunyikan semua group DO
        document.querySelectorAll('.so-do-group').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.do-checkbox').forEach(cb => cb.checked = false);

        const hint = document.getElementById('noSoSelectedHint');
        const custName = document.getElementById('custName');
        const custAddress = document.getElementById('custAddress');

        if (!soId || !soDataMap[soId]) {
            if (hint) hint.style.display = 'block';
            custName.innerText = '-';
            custAddress.innerText = 'Pilih SO terlebih dahulu';
            updateSelectedCount();
            return;
        }

        if (hint) hint.style.display = 'none';

        const data = soDataMap[soId];
        custName.innerText = data.customer ? data.customer.name : 'Customer';
        custAddress.innerText = data.customer ? (data.customer.address || 'Alamat sesuai master customer') : '-';

        // Tampilkan grup DO untuk SO ini
        const targetGroup = document.getElementById(`so-group-${soId}`);
        if (targetGroup) {
            targetGroup.style.display = 'block';
            // Auto check all DOs for this SO for convenience
            targetGroup.querySelectorAll('.do-checkbox').forEach(cb => cb.checked = true);
        }

        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checked = document.querySelectorAll('.do-checkbox:checked').length;
        const badge = document.getElementById('doCountBadge');
        const btn = document.getElementById('submitBtn');

        if (badge) {
            badge.innerText = `${checked} DO dipilih`;
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

    function toggleDeliveryMethod(method) {
        const internalEl = document.getElementById('internalGroup');
        const ekspedisiEl = document.getElementById('ekspedisiGroup');

        if (method === 'internal') {
            internalEl.style.display = 'contents';
            ekspedisiEl.style.display = 'none';
        } else {
            internalEl.style.display = 'none';
            ekspedisiEl.style.display = 'contents';
        }
    }

    // Auto trigger on initial load if soId passed in query
    document.addEventListener('DOMContentLoaded', function () {
        const initialSo = document.getElementById('soSelect').value;
        if (initialSo) {
            selectSO(initialSo);
        }
    });
</script>
@endpush
@endsection
