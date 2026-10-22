@extends('layouts.app', ['title' => 'Perintah Kirim ' . $deliveryOrder->do_number])

@section('content')
<div class="space-y-6">

    {{-- Breadcrumbs & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('penjualan.do.index') }}" class="hover:text-gray-600 transition">Perintah Kirim (DO)</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">{{ $deliveryOrder->do_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">{{ $deliveryOrder->do_number }}</h1>
                @if($deliveryOrder->returned_at || $deliveryOrder->status === 'returned')
                    <span class="text-xs font-bold text-red-700 bg-red-50 border border-red-200 rounded-lg px-2.5 py-1">
                        DIRETUR
                    </span>
                @elseif($deliveryOrder->status === 'confirmed')
                    <span class="text-xs font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1">
                        SIAP SURAT JALAN
                    </span>
                @elseif($deliveryOrder->status === 'approved')
                    <span class="text-xs font-bold text-blue-800 bg-blue-50 border border-blue-200 rounded-lg px-2.5 py-1">
                        SIAP DICEK GUDANG
                    </span>
                @elseif(in_array($deliveryOrder->status, \App\Models\DeliveryOrder::DELIVERED_STATUSES) || $deliveryOrder->hasIssuedSuratJalan())
                    <span class="text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-1">
                        TERKIRIM
                    </span>
                @elseif($deliveryOrder->status === 'closed')
                    <span class="text-xs font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                        DIBATALKAN / DITUTUP
                    </span>
                @else
                    <span class="text-xs font-bold text-gray-700 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1">
                        {{ \App\Models\DeliveryOrder::statusLabel($deliveryOrder->status) }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Gudang: <span class="font-semibold text-gray-700">{{ $deliveryOrder->warehouse?->name ?? 'Gudang Pusat' }}</span> · 
                Cabang: <span class="font-semibold text-gray-700">{{ $deliveryOrder->cabang?->nama ?? '-' }}</span> · 
                Dibuat: {{ $deliveryOrder->created_at->format('d F Y, H:i') }}
            </p>
        </div>

        {{-- Top Action Buttons --}}
        <div class="flex items-center gap-2">
            <a
                href="{{ route('penjualan.do.pdf', $deliveryOrder->id) }}"
                target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs shadow-xs transition"
            >
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" stroke-width="1.8"></path>
                    <rect x="6" y="14" width="12" height="8" rx="1" stroke-width="1.8"></rect>
                </svg>
                <span>Cetak DO (PDF)</span>
            </a>

            @if($canCreateSj)
                @php
                    $primarySo = $deliveryOrder->salesOrders->first();
                @endphp
                <a
                    href="{{ route('penjualan.sj.create', ['so_id' => $primarySo?->id, 'do_id' => $deliveryOrder->id]) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#15803d] hover:bg-[#14532d] text-white font-bold text-xs shadow-xs transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 4v16m8-8H4" stroke-width="2.2" stroke-linecap="round"></path>
                    </svg>
                    <span>Terbitkan Surat Jalan</span>
                </a>
            @endif

            @if($canCancel)
                <button
                    type="button"
                    onclick="openCancelModal()"
                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-red-200 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs transition"
                >
                    Batalkan DO
                </button>
            @endif
        </div>
    </div>

    {{-- Alert Status Khusus --}}
    @if($deliveryOrder->status === 'approved' && empty($deliveryOrder->returned_at))
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke-width="2"></circle>
                <path d="M12 16v-4M12 8h.01" stroke-width="2" stroke-linecap="round"></path>
            </svg>
            <div class="text-xs text-blue-900">
                <div class="font-bold">Menunggu Pengecekan Fisik Gudang</div>
                <div class="mt-0.5 text-blue-800">
                    Perintah kirim ini siap dicek oleh Checker Gudang di modul <a href="{{ route('gudang.kiriman.check', $deliveryOrder->id) }}" class="underline font-bold">Siapkan & Kirim</a>. Setelah seluruh item dicentang dan dikonfirmasi, Surat Jalan dapat diterbitkan.
                </div>
            </div>
        </div>
    @elseif($deliveryOrder->status === 'confirmed' && ! $deliveryOrder->hasIssuedSuratJalan())
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3">
            <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
            <div class="text-xs text-amber-900">
                <div class="font-bold">Pengecekan Fisik Selesai — Siap Terbitkan Surat Jalan</div>
                <div class="mt-0.5 text-amber-800">
                    Barang telah diverifikasi oleh petugas gudang dan siap diterbitkan Surat Jalan resmi untuk pengiriman ke customer.
                </div>
            </div>
        </div>
    @endif

    {{-- Progress Stepper 5 Langkah --}}
    <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 text-xs">
            {{-- Langkah 1: SO --}}
            @php $primarySo = $deliveryOrder->salesOrders->first(); @endphp
            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>1. Pesanan (SO)</span>
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $primarySo?->so_number ?? '-' }}
                </div>
            </div>

            {{-- Langkah 2: Alokasi DO --}}
            <div class="space-y-1">
                <div class="font-bold text-[#15803d] flex items-center gap-1.5">
                    <span>✓</span> <span>2. Alokasi DO</span>
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $deliveryOrder->warehouse?->name ?? 'Gudang' }}
                </div>
            </div>

            {{-- Langkah 3: Cek Fisik Gudang --}}
            @php
                $isCheckComplete = in_array($deliveryOrder->status, ['confirmed', 'sent', 'received', 'completed']) || $deliveryOrder->hasIssuedSuratJalan();
            @endphp
            <div class="space-y-1">
                <div class="font-bold {{ $isCheckComplete ? 'text-[#15803d]' : ($deliveryOrder->status === 'approved' ? 'text-blue-600' : 'text-gray-400') }} flex items-center gap-1.5">
                    <span>{{ $isCheckComplete ? '✓' : '•' }}</span> <span>3. Cek Checker</span>
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $isCheckComplete ? 'Selesai Dicek' : ($deliveryOrder->status === 'approved' ? 'Sedang Dicek' : 'Menunggu') }}
                </div>
            </div>

            {{-- Langkah 4: Surat Jalan --}}
            @php
                $hasSj = $deliveryOrder->hasIssuedSuratJalan();
                $firstSj = $deliveryOrder->suratJalan->first();
            @endphp
            <div class="space-y-1">
                <div class="font-bold {{ $hasSj ? 'text-[#15803d]' : ($deliveryOrder->status === 'confirmed' ? 'text-amber-600' : 'text-gray-400') }} flex items-center gap-1.5">
                    <span>{{ $hasSj ? '✓' : '•' }}</span> <span>4. Surat Jalan</span>
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $hasSj ? ($firstSj->sj_number ?? 'Terbit') : ($deliveryOrder->status === 'confirmed' ? 'Siap Diterbitkan' : 'Belum Terbit') }}
                </div>
            </div>

            {{-- Langkah 5: Selesai / Terkirim --}}
            @php
                $isDone = in_array($deliveryOrder->status, ['sent', 'received', 'completed']);
            @endphp
            <div class="space-y-1">
                <div class="font-bold {{ $isDone ? 'text-[#15803d]' : 'text-gray-400' }} flex items-center gap-1.5">
                    <span>{{ $isDone ? '✓' : '•' }}</span> <span>5. Terkirim</span>
                </div>
                <div class="text-[11px] text-gray-500">
                    {{ $isDone ? 'Barang Diterima' : 'Dalam Proses' }}
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Informasi DO & Customer --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- Kartu Info Pengiriman --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Informasi Dokumen</h3>

            <div class="space-y-2 text-xs">
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Nomor DO</span>
                    <span class="font-bold text-gray-900">{{ $deliveryOrder->do_number }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Tanggal Pengiriman</span>
                    <span class="font-medium text-gray-800">
                        {{ $deliveryOrder->delivery_date ? \Carbon\Carbon::parse($deliveryOrder->delivery_date)->format('d F Y') : '-' }}
                    </span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Asal Gudang</span>
                    <span class="font-medium text-gray-800">{{ $deliveryOrder->warehouse?->name ?? 'Gudang Pusat' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Cabang</span>
                    <span class="font-medium text-gray-800">{{ $deliveryOrder->cabang?->nama ?? '-' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Dibuat Oleh</span>
                    <span class="font-medium text-gray-800">{{ $deliveryOrder->createdBy?->name ?? 'Sistem' }}</span>
                </div>
                @if($deliveryOrder->notes)
                    <div class="py-1">
                        <span class="text-gray-500 block mb-0.5">Catatan</span>
                        <span class="font-medium text-gray-700 bg-gray-50 rounded-lg p-2 block">{{ $deliveryOrder->notes }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Kartu Info Pesanan & Pelanggan --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Rujukan Pesanan & Pelanggan</h3>

            <div class="space-y-2 text-xs">
                @if($primarySo)
                    <div class="flex justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Nomor SO</span>
                        <a href="{{ route('penjualan.so.show', $primarySo->id) }}" class="font-bold text-[#15803d] hover:underline">
                            {{ $primarySo->so_number }}
                        </a>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Customer</span>
                        <span class="font-bold text-gray-900">{{ $primarySo->customer?->name ?? 'Umum' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Kode Customer</span>
                        <span class="font-mono text-gray-700">{{ $primarySo->customer?->code ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-gray-100">
                        <span class="text-gray-500">Telepon</span>
                        <span class="text-gray-700">{{ $primarySo->customer?->phone ?? '-' }}</span>
                    </div>
                    <div class="py-1">
                        <span class="text-gray-500 block mb-0.5">Alamat Pengiriman</span>
                        <span class="text-gray-700 font-medium">
                            {{ $primarySo->shipping_address ?? ($primarySo->customer?->address ?? 'Ambil Sendiri / Alamat Utama') }}
                        </span>
                    </div>
                @else
                    <div class="text-gray-400 italic py-4 text-center">
                        Tidak terikat pada satu Surat Pesanan (SO).
                    </div>
                @endif
            </div>
        </div>

        {{-- Kartu Dokumen Terkait & Retur --}}
        <div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-xs space-y-3">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Dokumen Terkait</h3>

            <div class="space-y-2 text-xs">
                {{-- Surat Jalan Terkait --}}
                <div class="py-1 border-b border-gray-100">
                    <span class="text-gray-500 block mb-1">Surat Jalan (SJ)</span>
                    @if($deliveryOrder->suratJalan->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach($deliveryOrder->suratJalan as $sj)
                                <div class="flex items-center justify-between bg-gray-50 p-2 rounded-xl border border-gray-200">
                                    <div>
                                        <a href="{{ route('penjualan.sj.show', $sj->id) }}" class="font-bold text-[#15803d] hover:underline">
                                            {{ $sj->sj_number }}
                                        </a>
                                        <div class="text-[10px] text-gray-400">
                                            {{ $sj->issued_at ? $sj->issued_at->format('d M Y') : 'Draft' }}
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $sj->status === 'issued' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $sj->status === 'issued' ? 'Terbit' : ucfirst($sj->status) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span class="text-gray-400 italic">Belum ada Surat Jalan yang terbit</span>
                    @endif
                </div>

                {{-- Status Retur / Pengganti --}}
                @if($deliveryOrder->replacementFor)
                    <div class="py-1 border-b border-gray-100">
                        <span class="text-gray-500 block mb-0.5">Pengganti dari DO Asal</span>
                        <a href="{{ route('penjualan.do.show', $deliveryOrder->replacementFor->id) }}" class="font-bold text-blue-600 hover:underline">
                            {{ $deliveryOrder->replacementFor->do_number }}
                        </a>
                    </div>
                @endif

                @if($deliveryOrder->replacements->isNotEmpty())
                    <div class="py-1">
                        <span class="text-gray-500 block mb-0.5">DO Pengganti Hasil Retur</span>
                        @foreach($deliveryOrder->replacements as $rep)
                            <a href="{{ route('penjualan.do.show', $rep->id) }}" class="font-bold text-[#15803d] hover:underline block">
                                {{ $rep->do_number }}
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- Nilai Estimasi --}}
                <div class="pt-2">
                    <span class="text-gray-500 block mb-0.5">Total Nilai Barang (DPP + Pajak)</span>
                    <span class="text-base font-extrabold text-gray-900">
                        Rp {{ number_format($breakdown['total'] ?? 0, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    {{-- Tabel Rincian Barang yang Dikirim --}}
    <div class="bg-white border border-gray-200 rounded-2xl shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900 tracking-tight">Rincian Barang yang Dikeluarkan</h3>
            <span class="text-xs font-semibold text-gray-500">
                {{ $deliveryOrder->deliveryOrderItem->count() }} Item Barang
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-gray-50/75 border-b border-gray-200 text-gray-500 font-bold uppercase tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">No</th>
                        <th class="py-3 px-4">Kode & Nama Produk</th>
                        <th class="py-3 px-4 text-right">Kuantitas Kirim</th>
                        <th class="py-3 px-4">Satuan</th>
                        <th class="py-3 px-4">Gudang / Sumber</th>
                        <th class="py-3 px-4 text-center">Status Cek Fisik Gudang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($deliveryOrder->deliveryOrderItem as $idx => $item)
                        @php
                            $isItemChecked = (bool) $item->itemCheck;
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="py-3.5 px-4 text-center text-gray-400 font-medium">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-gray-900">
                                    {{ $item->product?->name ?? 'Produk #' . $item->product_id }}
                                </div>
                                <div class="text-[11px] text-gray-400 font-mono mt-0.5">
                                    SKU: {{ $item->product?->sku ?? '-' }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-right font-extrabold text-sm text-gray-900">
                                {{ number_format($item->quantity, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-gray-600 font-medium">
                                {{ $item->product?->uom?->name ?? ($item->product?->uom?->abbreviation ?? 'pcs') }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($item->warehouseSources->isNotEmpty())
                                    <div class="space-y-0.5">
                                        @foreach($item->warehouseSources as $src)
                                            <div class="text-[11px] text-gray-600">
                                                <span class="font-medium">{{ $src->warehouse?->name ?? 'Gudang' }}</span>: {{ number_format($src->quantity, 0, ',', '.') }}
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-gray-700 font-medium">{{ $deliveryOrder->warehouse?->name ?? 'Gudang Utama' }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                @if($isItemChecked)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="m4.5 12.75 6 6 9-13.5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                        <span>Sudah Dicek</span>
                                    </span>
                                    <div class="text-[9px] text-gray-400 mt-0.5">
                                        Oleh: {{ $item->itemCheck->checker?->name ?? 'Checker' }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600">
                                        <span>Belum Dicek</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-400">
                                Tidak ada item barang tercatat di Perintah Kirim ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Modal Pembatalan DO --}}
@if($canCancel)
    <div id="cancelModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <h3 class="text-base font-bold text-gray-900">Batalkan Perintah Kirim (DO)</h3>
                <button type="button" onclick="closeCancelModal()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
            </div>

            <p class="text-xs text-gray-600 leading-relaxed">
                Apakah Anda yakin ingin membatalkan <strong class="text-gray-900">{{ $deliveryOrder->do_number }}</strong>? Alokasi stok persediaan akan otomatis dikembalikan ke gudang dan sisa kuantiti SO akan kembali terbuka.
            </p>

            <form method="POST" action="{{ route('penjualan.do.cancel', $deliveryOrder->id) }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Alasan Pembatalan</label>
                    <textarea
                        name="reason"
                        rows="3"
                        required
                        placeholder="Contoh: Permintaan pembatalan dari customer / salah pilih gudang..."
                        class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        onclick="closeCancelModal()"
                        class="px-4 py-2 border border-gray-200 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-50 transition"
                    >
                        Tutup
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-xs transition"
                    >
                        Ya, Batalkan DO
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openCancelModal() {
            document.getElementById('cancelModal').classList.remove('hidden');
        }
        function closeCancelModal() {
            document.getElementById('cancelModal').classList.add('hidden');
        }
    </script>
@endif
@endsection
