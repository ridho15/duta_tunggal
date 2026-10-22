@extends('layouts.app', ['title' => 'Detail Manifes Jadwal ' . $deliverySchedule->schedule_number])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.schedules.index') }}" class="hover:text-gray-600">Jadwal Kirim & Armada</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $deliverySchedule->schedule_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $deliverySchedule->schedule_number }}</h1>
                @php
                    $badgeColor = match($deliverySchedule->status) {
                        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'on_the_way' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'failed' => 'bg-rose-50 text-rose-700 border-rose-200',
                        'cancelled' => 'bg-gray-100 text-gray-600 border-gray-200',
                        default => 'bg-gray-50 text-gray-700 border-gray-200'
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $badgeColor }}">
                    <span class="w-2 h-2 rounded-full {{ $deliverySchedule->status === 'on_the_way' ? 'bg-blue-600 animate-pulse' : ($deliverySchedule->status === 'delivered' ? 'bg-emerald-600' : ($deliverySchedule->status === 'pending' ? 'bg-amber-500' : 'bg-gray-400')) }}"></span>
                    {{ $deliverySchedule->status_label }}
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Rencana pengiriman cabang {{ $deliverySchedule->cabang->nama ?? '-' }} pada {{ optional($deliverySchedule->scheduled_date)->format('d F Y, H:i') }} WIB.
            </p>
        </div>

        {{-- Workflow Actions --}}
        <div class="flex items-center flex-wrap gap-2">
            {{-- Cetak PDF --}}
            <a
                href="{{ route('penjualan.schedules.pdf', $deliverySchedule->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 px-3.5 py-2 rounded-xl font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="2"></path>
                    <path d="M6 14h12v8H6z" stroke-width="2"></path>
                </svg>
                <span>Cetak Surat Tugas Driver</span>
            </a>

            @if($deliverySchedule->status === 'pending')
                {{-- Mulai Kirim (Berangkat) --}}
                <form method="POST" action="{{ route('penjualan.schedules.start', $deliverySchedule->id) }}" class="inline" onsubmit="return confirm('Mulai pengiriman armada? DO terkait akan ditandai terkirim dan stok fisik dikeluarkan dari gudang.');">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <polygon points="5 3 19 12 5 21 5 3" fill="currentColor"></polygon>
                        </svg>
                        <span>Mulai Pengiriman</span>
                    </button>
                </form>

                {{-- Edit --}}
                <a
                    href="{{ route('penjualan.schedules.edit', $deliverySchedule->id) }}"
                    class="inline-flex items-center gap-2 bg-white border border-gray-200 text-blue-600 hover:bg-blue-50 px-3 py-2 rounded-xl font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke-width="2"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke-width="2"></path>
                    </svg>
                    <span>Edit</span>
                </a>

                {{-- Batal --}}
                <button
                    type="button"
                    onclick="openCancelModal('{{ $deliverySchedule->id }}', '{{ $deliverySchedule->schedule_number }}')"
                    class="inline-flex items-center gap-2 bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 px-3 py-2 rounded-xl font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07" stroke-width="2"></line>
                    </svg>
                    <span>Batal</span>
                </button>
            @elseif($deliverySchedule->status === 'on_the_way')
                {{-- Selesaikan Kirim --}}
                <form method="POST" action="{{ route('penjualan.schedules.complete', $deliverySchedule->id) }}" class="inline" onsubmit="return confirm('Tandai jadwal selesai? Seluruh DO terkait akan ditandai selesai dan jurnal akuntansi dibukukan otomatis.');">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl font-bold text-xs shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="m5 13 4 4L19 7" stroke-width="2.5" stroke-linecap="round"></path>
                        </svg>
                        <span>Tandai Selesai & Diterima</span>
                    </button>
                </form>

                {{-- Tandai Gagal --}}
                <button
                    type="button"
                    onclick="openFailModal('{{ $deliverySchedule->id }}', '{{ $deliverySchedule->schedule_number }}')"
                    class="inline-flex items-center gap-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 px-3.5 py-2 rounded-xl font-bold text-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="m18 6-12 12M6 6l12 12" stroke-width="2.2" stroke-linecap="round"></path>
                    </svg>
                    <span>Tandai Pengiriman Gagal</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Main Layout: Left 2 Cols, Right 1 Col --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left 2 Columns: Manifes Muatan & Rincian Pengiriman --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Manifes Pengiriman --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                            Manifes Muatan Dokumen ({{ $relatedDOs->count() }} DO, {{ $relatedSJs->count() }} Surat Jalan)
                        </h2>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            Daftar tujuan pengantaran customer beserta rincian item barang di masing-masing pesanan.
                        </p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse($relatedDOs as $index => $do)
                        @php
                            $so = $do->salesOrders->first();
                            $customer = $so?->customer;
                        @endphp
                        <div class="p-4 space-y-3 hover:bg-gray-50/40 transition">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-gray-900 text-white text-[11px] font-extrabold flex items-center justify-center">
                                        {{ $index + 1 }}
                                    </span>
                                    <div>
                                        <a href="{{ route('penjualan.do.show', $do->id) }}" class="text-xs font-bold text-gray-900 hover:text-[#15803d]">
                                            DO: {{ $do->do_number }}
                                        </a>
                                        @if($so)
                                            <span class="text-xs text-gray-400"> (SO: {{ $so->so_number }})</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                        {{ $do->status_label ?? ucfirst($do->status) }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">
                                        {{ optional($do->delivery_date)->format('d/m/Y H:i') }}
                                    </span>
                                </div>
                            </div>

                            {{-- Informasi Customer & Alamat --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-gray-50/70 p-3 rounded-xl border border-gray-100">
                                <div>
                                    <span class="text-[11px] text-gray-400 block">Penerima / Toko:</span>
                                    <span class="font-bold text-gray-900">{{ $customer->name ?? 'Pelanggan Umum' }}</span>
                                    <span class="text-gray-500 block text-[11px]">{{ $customer->phone ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-[11px] text-gray-400 block">Alamat Pengantaran:</span>
                                    <span class="text-gray-700 text-[11px]">{{ $customer->address ?? '-' }}</span>
                                </div>
                            </div>

                            @php
                                $items = $do->deliveryOrderItem ?? $do->items;
                            @endphp
                            @if($items && $items->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-gray-50 text-gray-500 font-semibold text-[10px] uppercase">
                                            <tr>
                                                <th class="px-3 py-1.5">Produk</th>
                                                <th class="px-3 py-1.5 text-right">Qty Kirim</th>
                                                <th class="px-3 py-1.5 text-right">Satuan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 text-[11px]">
                                            @foreach($items as $item)
                                                <tr>
                                                    <td class="px-3 py-1.5 font-medium text-gray-800">
                                                        {{ $item->product->name ?? 'Item #' . $item->product_id }}
                                                        <span class="text-gray-400 text-[10px]">({{ $item->product->code ?? '-' }})</span>
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right font-bold text-gray-900">
                                                        {{ number_format((float) ($item->quantity ?? $item->qty ?? 0)) }}
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right text-gray-500">
                                                        {{ $item->product->uom->abbreviation ?? $item->product->unit ?? 'pcs' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-gray-400">
                            Tidak ada Delivery Order yang tertaut langsung ke jadwal ini.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Surat Jalan Terkait --}}
            @if($relatedSJs->count() > 0)
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
                    <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                        Dokumen Surat Jalan Resmi Tertaut
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($relatedSJs as $sj)
                            <div class="p-3 border border-gray-200 rounded-xl flex items-center justify-between hover:bg-gray-50 transition">
                                <div>
                                    <a href="{{ route('penjualan.sj.show', $sj->id) }}" class="font-bold text-xs text-[#15803d] hover:underline">
                                        {{ $sj->sj_number }}
                                    </a>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        Status: <span class="font-semibold text-gray-700">{{ $sj->status_label }}</span>
                                    </div>
                                </div>
                                <a
                                    href="{{ route('penjualan.sj.pdf', $sj->id) }}"
                                    target="_blank"
                                    class="p-1.5 text-gray-500 hover:text-emerald-700 hover:bg-emerald-50 rounded-lg transition"
                                    title="Cetak Surat Jalan"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="2"></path>
                                        <path d="M6 14h12v8H6z" stroke-width="2"></path>
                                    </svg>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Catatan Pengiriman --}}
            @if($deliverySchedule->notes)
                <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-2">
                    <h2 class="text-sm font-extrabold text-gray-900 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                        Catatan & Riwayat Status
                    </h2>
                    <div class="text-xs text-gray-700 bg-gray-50 p-3 rounded-xl border border-gray-100 whitespace-pre-line font-mono">
                        {{ $deliverySchedule->notes }}
                    </div>
                </div>
            @endif
        </div>

        {{-- Right 1 Column: Armada, Driver, & Metadata --}}
        <div class="space-y-6">

            {{-- Alokasi Driver & Armada --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                <h2 class="text-sm font-extrabold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                    Alokasi Armada & Pengemudi
                </h2>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Moda Pengiriman</span>
                        <span class="font-bold text-gray-900">{{ $deliverySchedule->delivery_method_label }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Driver / Pengemudi</span>
                        <span class="font-bold text-gray-900">{{ $deliverySchedule->senderName() }}</span>
                        @if($deliverySchedule->driver?->phone)
                            <div class="text-[11px] text-gray-500 mt-0.5">Telp: {{ $deliverySchedule->driver->phone }}</div>
                        @endif
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Kendaraan / No. Polisi</span>
                        <span class="font-bold text-gray-900">{{ $deliverySchedule->vehicleLabel() }}</span>
                        @if($deliverySchedule->vehicle?->capacity)
                            <div class="text-[11px] text-gray-500 mt-0.5">Kapasitas: {{ $deliverySchedule->vehicle->capacity }}</div>
                        @endif
                    </div>

                    @if($deliverySchedule->tracking_number)
                        <div>
                            <span class="text-gray-400 block text-[11px]">Nomor Resi / AWB</span>
                            <span class="font-mono font-bold text-gray-900">{{ $deliverySchedule->tracking_number }}</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Metadata Dokumen --}}
            <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
                <h2 class="text-sm font-extrabold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                    Informasi Dokumen
                </h2>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-gray-400 block text-[11px]">Cabang Pengiriman</span>
                        <span class="font-bold text-gray-900">{{ $deliverySchedule->cabang->nama ?? '-' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Jadwal Berangkat</span>
                        <span class="font-bold text-gray-900">{{ optional($deliverySchedule->scheduled_date)->format('d F Y, H:i') }} WIB</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Dibuat Oleh</span>
                        <span class="font-semibold text-gray-900">{{ $deliverySchedule->createdBy->name ?? 'Sistem' }}</span>
                    </div>

                    <div>
                        <span class="text-gray-400 block text-[11px]">Waktu Pembuatan</span>
                        <span class="text-gray-600">{{ $deliverySchedule->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                @if(in_array($deliverySchedule->status, ['pending', 'cancelled']))
                    <div class="pt-3 border-t border-gray-100">
                        <form method="POST" action="{{ route('penjualan.schedules.destroy', $deliverySchedule->id) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal pengiriman ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-full text-center px-4 py-2 border border-rose-200 hover:bg-rose-50 text-rose-600 rounded-xl text-xs font-bold transition">
                                Hapus Jadwal Ini
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Pengiriman Gagal --}}
<div id="failModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-base">Tandai Pengiriman Gagal</h3>
            <button type="button" onclick="closeFailModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>

        <form id="failForm" method="POST" action="">
            @csrf
            <div class="space-y-3">
                <p class="text-xs text-gray-600">
                    Jadwal <strong id="failScheduleNumber" class="text-gray-900"></strong> akan ditandai gagal. Seluruh DO terkait yang sedang dikirim akan menjadi <em>delivery_failed</em> dan stok fisik akan dikembalikan ke gudang secara otomatis.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Kegagalan <span class="text-red-500">*</span></label>
                    <textarea
                        name="reason"
                        rows="3"
                        required
                        minlength="5"
                        placeholder="Contoh: Toko penerima tutup, jalan terputus, atau armada mengalami kendala mesin..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500"
                    ></textarea>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="closeFailModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm transition">
                    Konfirmasi Gagal
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Pembatalan Jadwal --}}
<div id="cancelModal" class="fixed inset-0 z-50 hidden bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="font-extrabold text-gray-900 text-base">Batalkan Jadwal Pengiriman</h3>
            <button type="button" onclick="closeCancelModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="m18 6-12 12M6 6l12 12" stroke-width="2"></path>
                </svg>
            </button>
        </div>

        <form id="cancelForm" method="POST" action="">
            @csrf
            <div class="space-y-3">
                <p class="text-xs text-gray-600">
                    Batalkan jadwal <strong id="cancelScheduleNumber" class="text-gray-900"></strong>. Muatan SJ dan DO akan dibebaskan sehingga dapat dijadwalkan kembali.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Pembatalan</label>
                    <input
                        type="text"
                        name="cancel_reason"
                        placeholder="Contoh: Perubahan rute atau driver berhalangan..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-gray-500"
                    >
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 mt-5">
                <button type="button" onclick="closeCancelModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
                    Tutup
                </button>
                <button type="submit" class="px-4 py-2 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold shadow-sm transition">
                    Batalkan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openFailModal(scheduleId, scheduleNum) {
    document.getElementById('failScheduleNumber').innerText = scheduleNum;
    document.getElementById('failForm').action = '/penjualan/jadwal-kirim/' + scheduleId + '/fail';
    document.getElementById('failModal').classList.remove('hidden');
}

function closeFailModal() {
    document.getElementById('failModal').classList.add('hidden');
}

function openCancelModal(scheduleId, scheduleNum) {
    document.getElementById('cancelScheduleNumber').innerText = scheduleNum;
    document.getElementById('cancelForm').action = '/penjualan/jadwal-kirim/' + scheduleId + '/cancel';
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>
@endsection
