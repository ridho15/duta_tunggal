@extends('layouts.app', ['title' => 'Surat Jalan ' . $suratJalan->sj_number])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.sj.index') }}" class="hover:text-gray-600 transition">Surat Jalan</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $suratJalan->sj_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $suratJalan->sj_number }}</h1>
                @if($suratJalan->status === \App\Models\SuratJalan::STATUS_ISSUED)
                    <span class="text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1">
                        TERBIT · RESMI
                    </span>
                @elseif($suratJalan->status === \App\Models\SuratJalan::STATUS_DRAFT)
                    <span class="text-xs font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                        DRAFT
                    </span>
                @else
                    <span class="text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-1">
                        DIBATALKAN
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                {{ $doc['delivery']['destination_name'] ?? 'Customer' }} · Diterbitkan {{ $suratJalan->issued_at ? $suratJalan->issued_at->format('d F Y') : '-' }} · Cabang {{ $suratJalan->cabang->nama ?? 'Pusat' }}
            </p>
        </div>

        {{-- Top Action Buttons --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.sj.pdf', $suratJalan->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                    <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                </svg>
                <span>Cetak SJ (PDF)</span>
            </a>

            @if($suratJalan->status === \App\Models\SuratJalan::STATUS_ISSUED && $primarySO)
                <a
                    href="{{ route('penjualan.invoices.create', ['so_id' => $primarySO->id, 'sj_id' => $suratJalan->id]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="2"></path>
                    </svg>
                    <span>Buat Faktur Penjualan</span>
                </a>

                <button
                    type="button"
                    onclick="openCancelModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs transition"
                >
                    Batalkan SJ
                </button>
            @endif
        </div>
    </div>

    {{-- Progress Stepper 6 Langkah --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-xs">
            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>1. SO Dibuat</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    {{ $primarySO?->so_number ?? '-' }}
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>2. Diapprove</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    Disetujui
                </div>
            </div>

            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>3. DO Multi-Gudang</span>
                </div>
                <div class="text-[11px] text-gray-400">
                    {{ $suratJalan->deliveryOrder->count() }} DO Dibuat
                </div>
            </div>

            <div class="space-y-1 border-l-2 md:border-l-0 pl-2 md:pl-0 border-[#15803d]">
                <div class="font-black text-gray-900 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#15803d]"></span> <span>4. Surat Jalan</span>
                </div>
                <div class="text-[11px] font-semibold text-emerald-700">
                    {{ $suratJalan->status_label }}
                </div>
            </div>

            <div class="space-y-1 opacity-70">
                <div class="font-semibold text-gray-500">
                    5. Pengiriman
                </div>
                <div class="text-[11px] text-gray-400">
                    {{ $doc['delivery']['sender_name'] ?? 'Kurir' }}
                </div>
            </div>

            <div class="space-y-1 opacity-50">
                <div class="font-semibold text-gray-500">
                    6. Faktur Penjualan
                </div>
                <div class="text-[11px] text-gray-400">
                    Belum Diterbitkan
                </div>
            </div>
        </div>
    </div>

    {{-- Informasi Pengiriman & Dokumen Terkait --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- Detail Kurir & Armada --}}
        <div class="md:col-span-2 bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 pb-2 border-b border-gray-100 flex items-center justify-between">
                <span>Informasi Pengiriman & Armada</span>
                <span class="text-xs font-semibold px-2 py-0.5 bg-gray-100 text-gray-700 rounded">
                    {{ $doc['delivery']['method_label'] ?? 'Internal' }}
                </span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Penerima & Alamat:</span>
                    <span class="font-bold text-gray-800 text-sm block">{{ $doc['delivery']['destination_name'] ?? '-' }}</span>
                    <span class="text-gray-600 block">{{ $doc['delivery']['destination_address'] ?? '-' }}</span>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Kurir / Driver:</span>
                    <span class="font-bold text-gray-800 text-sm block">{{ $doc['delivery']['sender_name'] ?? '-' }}</span>
                    <span class="text-gray-600 block">Armada: {{ $doc['delivery']['vehicle'] ?? '-' }}</span>
                    @if(!empty($doc['delivery']['tracking_number']))
                        <span class="text-emerald-700 font-mono font-bold block">Resi: {{ $doc['delivery']['tracking_number'] }}</span>
                    @endif
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Penandatangan Dokumen:</span>
                    <span class="font-semibold text-gray-800 block">{{ $suratJalan->signed_by ?? '-' }}</span>
                </div>

                <div class="space-y-1">
                    <span class="text-gray-400 block text-[11px]">Cabang Asal:</span>
                    <span class="font-semibold text-gray-800 block">{{ $suratJalan->cabang->nama ?? 'Pusat' }}</span>
                </div>
            </div>
        </div>

        {{-- Dokumen Terkait --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-4">
            <h2 class="text-sm font-bold text-gray-900 pb-2 border-b border-gray-100">
                Dokumen Terhubung
            </h2>

            <div class="space-y-2 text-xs">
                @if($primarySO)
                    <div class="p-2.5 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Pesanan Penjualan</span>
                            <span class="font-bold text-gray-800">{{ $primarySO->so_number }}</span>
                        </div>
                        <a href="{{ route('penjualan.so.show', $primarySO->id) }}" class="text-[#15803d] font-bold hover:underline">
                            Lihat SO
                        </a>
                    </div>
                @endif

                @foreach($suratJalansDeliveryOrders = $suratJalan->deliveryOrder as $do)
                    <div class="p-2.5 rounded-xl border border-gray-100 bg-gray-50 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] text-gray-400 block uppercase font-bold">Delivery Order</span>
                            <span class="font-bold text-gray-800">{{ $do->do_number }}</span>
                            <span class="text-[10px] text-gray-500 block">{{ $do->warehouse->name ?? '-' }}</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                            {{ $do->status }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Rincian Barang Serah Terima (Tanpa Harga) --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-gray-900">Rincian Barang Serah Terima</h2>
                <p class="text-xs text-gray-400">Daftar item konsolidasi yang dikirimkan kepada customer.</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg">
                Dokumen Serah Terima Resmi
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3 w-12 text-center">No</th>
                        <th class="px-4 py-3">Kode & Nama Produk</th>
                        <th class="px-4 py-3">Gudang Pengambilan</th>
                        <th class="px-4 py-3 text-right">Qty Kirim</th>
                        <th class="px-4 py-3">Satuan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs">
                    @php $rowNo = 1; @endphp
                    @foreach($doc['groups'] as $group)
                        <tr class="bg-gray-50/50">
                            <td colspan="5" class="px-5 py-2 font-bold text-gray-700 text-[11px]">
                                DO: {{ $group['do_number'] ?? '-' }}
                                @if(!empty($group['sales_orders']))
                                    · SO: {{ implode(', ', $group['sales_orders']) }}
                                @endif
                            </td>
                        </tr>
                        @foreach($group['items'] as $item)
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-5 py-3 text-center text-gray-400 font-medium">{{ $rowNo++ }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-bold text-gray-800 block">{{ $item['name'] }}</span>
                                    <span class="text-gray-400 text-[11px] font-mono">{{ $item['sku'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $item['warehouse'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-black text-gray-900 text-sm">{{ $item['quantity'] }}</td>
                                <td class="px-4 py-3 text-gray-500 font-semibold">{{ $item['unit'] }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Pembatalan Surat Jalan --}}
<div id="cancelModal" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
        <div class="flex items-center gap-3 text-red-600">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2"></path>
                </svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900">Batalkan Surat Jalan?</h3>
                <p class="text-xs text-gray-500">Delivery Order terkait akan dibebaskan kembali.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('penjualan.sj.cancel', $suratJalan->id) }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">
                    Alasan Pembatalan <span class="text-red-500">*</span>
                </label>
                <textarea
                    name="cancel_reason"
                    required
                    minlength="5"
                    rows="3"
                    placeholder="Masukkan alasan pembatalan (minimal 5 karakter)..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-xl text-xs text-gray-800 focus:outline-none focus:border-red-500"
                ></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button
                    type="button"
                    onclick="closeCancelModal()"
                    class="px-4 py-2 rounded-xl border border-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-50"
                >
                    Tutup
                </button>
                <button
                    type="submit"
                    class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold shadow-sm"
                >
                    Konfirmasi Batalkan
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openCancelModal() {
        document.getElementById('cancelModal').classList.remove('hidden');
    }
    function closeCancelModal() {
        document.getElementById('cancelModal').classList.add('hidden');
    }
</script>
@endpush
@endsection
