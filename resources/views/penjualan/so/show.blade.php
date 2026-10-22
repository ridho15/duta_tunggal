@extends('layouts.app')

@section('title', $saleOrder->so_number . ' – Detail Pesanan Penjualan')

@section('content')
<div class="max-w-[1080px] mx-auto w-full flex flex-col gap-5">
    <!-- Header Section -->
    <div class="flex flex-wrap justify-between items-end gap-3.5 pb-5 border-b border-gray-200">
        <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-2 text-xs sm:text-sm text-gray-500">
                <a href="{{ route('penjualan.so.index') }}" class="hover:text-emerald-700">Pesanan Penjualan</a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-900">{{ $saleOrder->so_number }}</span>
            </div>

            @php
                $statusBadgeStyle = match($saleOrder->status) {
                    'request_approve' => 'text-amber-800 bg-amber-50 border-amber-300',
                    'approved', 'confirmed' => 'text-emerald-800 bg-emerald-50 border-emerald-300',
                    'partially_delivered' => 'text-blue-800 bg-blue-50 border-blue-300',
                    'received' => 'text-indigo-800 bg-indigo-50 border-indigo-300',
                    'completed' => 'text-emerald-900 bg-emerald-100 border-emerald-400',
                    'reject' => 'text-red-800 bg-red-50 border-red-300',
                    default => 'text-gray-700 bg-gray-100 border-gray-200',
                };
            @endphp

            <div class="flex items-center gap-2.5 mt-1">
                <h1 class="text-2xl lg:text-[28px] font-extrabold text-gray-900 m-0">{{ $saleOrder->so_number }}</h1>
                <span class="text-[11px] font-bold tracking-wider uppercase border rounded-md px-2.5 py-0.5 {{ $statusBadgeStyle }}">
                    {{ \App\Models\SaleOrder::statusLabel($saleOrder->status) }}
                </span>
            </div>

            <div class="text-xs sm:text-sm text-gray-500">
                <span class="font-semibold text-gray-700">{{ $saleOrder->customer?->name }}</span>
                · {{ $saleOrder->tipe_pengiriman }}
                · {{ $saleOrder->order_date ? $saleOrder->order_date->translatedFormat('d F Y') : '-' }}
                · Cabang <span class="font-semibold text-gray-700">{{ $saleOrder->cabang?->nama ?? '-' }}</span>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('pdf-stream', ['type' => 'sale-order', 'id' => $saleOrder->id]) }}" target="_blank" class="px-4 py-2 border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold rounded-xl inline-flex items-center gap-1.5 transition-colors">
                <svg class="w-4 h-4 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Cetak SO
            </a>

            @if(in_array($saleOrder->status, ['draft', 'request_approve']))
                <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="px-4 py-2 border border-red-200 hover:bg-red-50 text-red-700 text-sm font-semibold rounded-xl transition-colors">
                    Tolak SO
                </button>
            @endif
        </div>
    </div>

    <!-- Stepper Progres Pesanan (6 Langkah) -->
    <section class="bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 flex flex-col gap-4 shadow-sm">
        <ol class="list-none m-0 p-0 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 border-b border-gray-100 pb-4">
            @foreach($stepper as $stepKey => $step)
                @php
                    $borderColor = $step['done'] ? 'border-emerald-600' : ($step['active'] ? 'border-gray-900' : 'border-gray-200');
                    $textColor = $step['done'] ? 'text-emerald-900 font-bold' : ($step['active'] ? 'text-gray-900 font-bold' : 'text-gray-400');
                @endphp
                <li class="border-t-3 {{ $borderColor }} pt-2 flex flex-col">
                    <div class="text-xs sm:text-[13px] {{ $textColor }}">
                        {{ $step['done'] ? '✓ ' : '' }}{{ $step['title'] }}
                    </div>
                    <div class="text-[11px] text-gray-400 mt-0.5">
                        {{ $step['info'] }}
                    </div>
                </li>
            @endforeach
        </ol>

        <!-- Dynamic Action Banner ("Langkah Berikutnya") -->
        @if($nextStep)
            <div class="flex flex-wrap items-center justify-between gap-4 bg-emerald-50/80 border border-emerald-200 rounded-xl p-4 sm:p-5">
                <div class="flex-1 min-w-[280px]">
                    <div class="text-[11px] text-emerald-800 font-extrabold tracking-wider uppercase">LANGKAH BERIKUTNYA</div>
                    <div class="text-base font-bold text-gray-900 mt-0.5">{{ $nextStep['title'] }}</div>
                    <div class="text-xs sm:text-sm text-gray-600 mt-0.5">{{ $nextStep['description'] }}</div>
                </div>

                @if(!empty($nextStep['is_approve']))
                    @if($canApprove)
                        <button type="button" onclick="document.getElementById('approveModal').classList.remove('hidden')" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm px-5 py-2.5 rounded-xl min-h-[44px] shadow-sm transition-colors cursor-pointer">
                            Setujui SO
                        </button>
                    @else
                        <div class="text-xs font-semibold text-amber-800 bg-amber-100 border border-amber-300 rounded-lg px-3 py-2">
                            {{ $approveDenyReason ?? 'Menunggu persetujuan atasan' }}
                        </div>
                    @endif
                @elseif(!empty($nextStep['action_url']))
                    <a href="{{ $nextStep['action_url'] }}" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold text-sm px-5 py-2.5 rounded-xl min-h-[44px] inline-flex items-center shadow-sm transition-colors">
                        {{ $nextStep['action_label'] }}
                    </a>
                @endif
            </div>
        @endif
    </section>

    <!-- Grid Detail: Barang & Dokumen Terkait -->
    <div class="flex flex-wrap lg:flex-nowrap gap-5 items-start">
        <!-- Kolom Kiri: Rincian Barang -->
        <section class="w-full lg:flex-1 bg-white border border-gray-200 rounded-2xl p-5 sm:p-6 shadow-sm min-w-0">
            <h2 class="m-0 text-base font-bold text-gray-900 mb-3.5">Barang yang Dipesan</h2>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 text-xs border-b border-gray-200">
                            <th class="pb-2.5 px-2 font-semibold">Produk</th>
                            <th class="pb-2.5 px-2 font-semibold text-right">Qty SO</th>
                            <th class="pb-2.5 px-2 font-semibold text-right">Sudah DO</th>
                            <th class="pb-2.5 px-2 font-semibold text-right">Sisa</th>
                            <th class="pb-2.5 px-2 font-semibold text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($saleOrder->saleOrderItem as $item)
                            @php
                                $prog = $itemProgress[$item->id] ?? null;
                                $delivered = $prog ? (float) $prog['delivered'] : (float) $item->delivered_quantity;
                                $available = $prog ? (float) $prog['available'] : max(0, $item->quantity - $delivered);
                            @endphp
                            <tr>
                                <td class="py-3 px-2">
                                    <div class="font-bold text-gray-900">{{ $item->product?->name }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        {{ $item->product?->sku }} · Rp {{ number_format($item->unit_price, 0, ',', '.') }} / {{ $item->product?->uom?->abbreviation ?? 'PCS' }}
                                    </div>
                                </td>
                                <td class="py-3 px-2 text-right font-medium text-gray-800">
                                    {{ round($item->quantity) }}
                                </td>
                                <td class="py-3 px-2 text-right font-medium text-gray-800">
                                    {{ round($delivered) }}
                                </td>
                                <td class="py-3 px-2 text-right font-bold {{ $available <= 0 ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ round($available) }}{{ $available <= 0 ? ' ✓' : '' }}
                                </td>
                                <td class="py-3 px-2 text-right font-semibold text-gray-900">
                                    Rp {{ number_format($item->subtotal ?: ($item->quantity * $item->unit_price), 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Total Ringkasan -->
            <div class="flex flex-col items-end gap-1.5 pt-4 border-t border-gray-100 text-sm mt-3">
                <div class="flex justify-between w-64 text-gray-600">
                    <span>Subtotal barang</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format($saleOrder->total_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between w-64 text-gray-600">
                    <span>Ongkos kirim</span>
                    <span class="font-semibold text-gray-900">Rp {{ number_format($saleOrder->shipping_cost, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between w-64 text-base font-extrabold text-gray-900 pt-2 border-t border-gray-200">
                    <span>Total</span>
                    <span class="text-emerald-700">Rp {{ number_format($saleOrder->grandTotal(), 0, ',', '.') }}</span>
                </div>
            </div>
        </section>

        <!-- Kolom Kanan: Dokumen Terkait -->
        <aside class="w-full lg:w-[320px] shrink-0 flex flex-col gap-4">
            <!-- Card Dokumen Terkait -->
            <section class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-col gap-3">
                <h2 class="m-0 text-base font-bold text-gray-900 border-b border-gray-100 pb-2.5">Dokumen Terkait</h2>

                <div class="flex flex-col gap-2.5">
                    @forelse($saleOrder->deliveryOrder as $do)
                        <div class="border border-gray-200 hover:border-emerald-600 rounded-xl p-3 bg-gray-50/50 transition-colors">
                            <div class="flex justify-between items-center">
                                <a href="{{ route('penjualan.do.show', $do->id) }}" class="font-bold text-sm text-gray-900 hover:text-emerald-700 transition">
                                    {{ $do->do_number }}
                                </a>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 uppercase">
                                    {{ \App\Models\DeliveryOrder::statusLabel($do->status) }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Gudang: <span class="font-medium text-gray-700">{{ $do->warehouse?->name ?? '-' }}</span>
                            </div>

                            @if($do->suratJalan->isNotEmpty())
                                <div class="mt-2 pt-1.5 border-t border-gray-200 text-xs text-emerald-800 font-semibold flex items-center gap-1">
                                    <span>SJ: {{ $do->suratJalan->pluck('sj_number')->implode(', ') }}</span>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="border border-dashed border-gray-300 rounded-xl p-4 text-center text-xs text-gray-400">
                            Belum ada Delivery Order yang dibuat.
                        </div>
                    @endforelse
                </div>
            </section>

            <!-- Card Informasi Customer -->
            <section class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm flex flex-col gap-2.5 text-xs text-gray-600">
                <h3 class="font-bold text-gray-900 text-sm border-b border-gray-100 pb-2">Info Customer</h3>
                <div><span class="font-semibold text-gray-800">Nama:</span> {{ $saleOrder->customer?->name }}</div>
                <div><span class="font-semibold text-gray-800">Telepon:</span> {{ $saleOrder->customer?->telephone ?? '-' }}</div>
                <div><span class="font-semibold text-gray-800">Tipe Bayar:</span> {{ $saleOrder->customer?->tipe_pembayaran ?? 'Tunai' }} (Tempo: {{ $saleOrder->tempo_pembayaran }} hari)</div>
                <div><span class="font-semibold text-gray-800">Alamat:</span> {{ $saleOrder->shipped_to ?? $saleOrder->customer?->address ?? '-' }}</div>
            </section>
        </aside>
    </div>
</div>

<!-- Modal Approve SO -->
<div id="approveModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl flex flex-col gap-4">
        <h3 class="text-lg font-bold text-gray-900 m-0">Setujui Sales Order {{ $saleOrder->so_number }}</h3>
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menyetujui pesanan penjualan ini? Setelah disetujui, SO dapat diproses untuk penerbitan Delivery Order (DO).
        </p>

        <form method="POST" action="{{ route('penjualan.so.approve', $saleOrder->id) }}" class="flex flex-col gap-3">
            @csrf
            @if($saleOrder->isCod())
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-900">
                    <span class="font-bold">Pesanan COD:</span> Memerlukan verifikasi saldo titip customer.
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-gray-700">Catatan Kasir / Pengecualian</label>
                    <input type="text" name="notes" placeholder="Opsional..." class="border border-gray-300 rounded-xl px-3 py-2 text-sm">
                </div>
            @endif

            <div class="flex justify-end gap-2 mt-2">
                <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-sm font-bold shadow-sm cursor-pointer">
                    Ya, Setujui SO
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reject SO -->
<div id="rejectModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-xs p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl flex flex-col gap-4">
        <h3 class="text-lg font-bold text-red-900 m-0">Tolak Sales Order {{ $saleOrder->so_number }}</h3>
        <p class="text-sm text-gray-600">
            Masukkan alasan penolakan agar tim sales dapat merevisi pesanan penjualan ini.
        </p>

        <form method="POST" action="{{ route('penjualan.so.reject', $saleOrder->id) }}" class="flex flex-col gap-3">
            @csrf
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-700">Alasan Penolakan <span class="text-red-500">*</span></label>
                <textarea name="reason" rows="3" required placeholder="Contoh: Batas harga produk di bawah ketentuan cabang..." class="border border-gray-300 rounded-xl p-3 text-sm focus:outline-none focus:border-red-500"></textarea>
            </div>

            <div class="flex justify-end gap-2 mt-2">
                <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-4 py-2 border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-bold shadow-sm cursor-pointer">
                    Tolak SO
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
