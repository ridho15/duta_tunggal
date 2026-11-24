@extends('layouts.app')

@section('title', 'Detail Transfer Stok ' . $transfer->transfer_number . ' – ERP Duta Tunggal')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs font-medium text-gray-500 uppercase tracking-wider mb-1.5">
                <a href="{{ route('dashboard') }}" class="hover:text-gray-700">Dashboard</a>
                <span class="text-gray-300">/</span>
                <a href="{{ route('gudang.transfer.index') }}" class="hover:text-gray-700">Pindah Stok</a>
                <span class="text-gray-300">/</span>
                <span class="text-[#15803d] font-bold font-mono">{{ $transfer->transfer_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">
                    {{ $transfer->transfer_number }}
                </h1>
                {{-- Status Badge --}}
                @php
                    $badge = match ($transfer->status) {
                        'Approved' => 'bg-sky-50 text-sky-700 border-sky-200',
                        'Shipped' => 'bg-amber-50 text-amber-800 border-amber-200',
                        'Completed', 'completed' => 'bg-[#f0fdf4] text-[#15803d] border-[#86efac]',
                        'Request', 'Pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                        'Reject', 'Cancelled', 'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                        default => 'bg-gray-100 text-gray-700 border-gray-200',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $badge }}">
                    <span class="w-2 h-2 rounded-full bg-current opacity-70"></span>
                    {{ $transfer->statusLabel() }}
                </span>
            </div>
        </div>

        {{-- Top Actions --}}
        <div class="flex items-center gap-2" x-data>
            <a href="{{ route('gudang.transfer.index') }}"
               class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                ← Kembali
            </a>

            @if ($transfer->status === 'Draft')
                <a href="{{ route('gudang.transfer.edit', $transfer) }}"
                   class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    Edit Draft
                </a>

                <button type="button"
                        @click="$dispatch('open-modal-request-transfer')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-xl transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Ajukan Transfer</span>
                </button>

                <button type="button"
                        @click="$dispatch('open-modal-delete-transfer')"
                        class="px-3 py-2 text-xs font-semibold text-rose-600 bg-white border border-rose-200 rounded-xl hover:bg-rose-50 transition shadow-sm">
                    Hapus
                </button>
            @endif

            @if (in_array($transfer->status, ['Request', 'Pending']))
                <button type="button"
                        onclick="openRejectModal()"
                        class="px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition shadow-sm">
                    Tolak
                </button>

                <button type="button"
                        @click="$dispatch('open-modal-approve-transfer')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl transition shadow-sm active:scale-[0.98]">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Setujui Mutasi</span>
                </button>
            @endif

            @if (in_array(strtolower($transfer->status), ['reject', 'cancelled']))
                <form action="{{ route('gudang.transfer.revert-draft', $transfer) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="px-3.5 py-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition shadow-sm">
                        Kembalikan ke Draft
                    </button>
                </form>

                <button type="button"
                        @click="$dispatch('open-modal-delete-transfer')"
                        class="px-3 py-2 text-xs font-semibold text-rose-600 bg-white border border-rose-200 rounded-xl hover:bg-rose-50 transition shadow-sm">
                    Hapus
                </button>
            @endif

            @if (in_array($transfer->status, ['Approved', 'Shipped']) && $transfer->hasRemainingToShip())
                <button type="button" @click="$dispatch('open-modal-ship-transfer')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl transition shadow-sm active:scale-[0.98]">
                    <span>Kirim Barang</span>
                </button>
                <button type="button" @click="$dispatch('open-modal-close-remaining')"
                        class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    Tutup Sisa
                </button>
            @endif

            @if (in_array($transfer->status, ['Approved', 'Shipped', 'Completed', 'completed']))
                <a href="{{ route('gudang.transfer.pdf', $transfer) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    <svg class="w-4 h-4 text-[#15803d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Cetak Surat Jalan (PDF)</span>
                </a>

                @if (auth()->user() && (\App\Support\MenuAccess::isSuper(auth()->user()) || (method_exists(auth()->user(), 'canManageAllCabang') && auth()->user()->canManageAllCabang())))
                    <button type="button"
                            @click="$dispatch('open-modal-void-transfer')"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition shadow-sm active:scale-[0.98]">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>Void Transfer Stok (Super Admin)</span>
                    </button>
                @endif
            @endif
        </div>
    </div>


    {{-- Tahapan mutasi: Buat → Disetujui → Dikirim (gudang asal) → Diterima (gudang tujuan) --}}
    @php
        $steps = ['Dibuat', 'Disetujui', 'Dikirim', 'Diterima'];
        $stepNow = match ($transfer->status) { 'Request', 'Pending' => 1, 'Approved' => 2, 'Shipped' => 3, 'Completed', 'completed' => 4, default => 1 };
        $pendingShipments = $transfer->shipments->whereNull('received_at');
    @endphp
    @if (! in_array($transfer->status, ['Reject', 'Cancelled', 'cancelled']))
        <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm">
            <ol class="grid grid-cols-4 gap-2 text-[11px] font-semibold">
                @foreach ($steps as $i => $label)
                    <li class="flex flex-col gap-1">
                        <span class="h-1.5 rounded-full {{ $i < $stepNow ? 'bg-[#15803d]' : 'bg-gray-200' }}"></span>
                        <span class="{{ $i < $stepNow ? 'text-[#15803d]' : 'text-gray-400' }}">{{ $label }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs text-gray-600">
                @if ($transfer->status === 'Approved')
                    Stok di <strong>{{ $transfer->fromWarehouse?->name }}</strong> sudah <strong>dicadangkan</strong> (belum berpindah). Gudang asal menekan <strong>Kirim Barang</strong> sesuai qty yang benar-benar naik kendaraan.
                @elseif ($transfer->status === 'Shipped')
                    @if ($pendingShipments->isNotEmpty()) Ada {{ $pendingShipments->count() }} pengiriman dalam perjalanan — gudang tujuan menekan <strong>Terima</strong> saat barang tiba. @endif
                    @if ($transfer->hasRemainingToShip()) Masih ada sisa yang belum dikirim (bisa dikirim lagi atau <strong>Tutup Sisa</strong>). @endif
                @elseif (in_array($transfer->status, ['Completed', 'completed']))
                    Semua barang yang dikirim sudah diterima di <strong>{{ $transfer->toWarehouse?->name }}</strong>.
                    @if ($transfer->remaining_closed_at) Sisa ditutup: {{ $transfer->remaining_close_reason }}. @endif
                @else
                    Mutasi menunggu persetujuan.
                @endif
            </p>
        </div>
    @endif

    {{-- Kartu Info Rangkuman Dokumen --}}
    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm space-y-4">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Informasi Pindah Stok</h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 text-xs">
            {{-- Tanggal --}}
            <div>
                <span class="text-gray-400 block mb-1">Tanggal Transfer</span>
                <span class="font-bold text-gray-900 text-sm">
                    {{ \Carbon\Carbon::parse($transfer->transfer_date)->translatedFormat('d F Y') }}
                </span>
            </div>

            {{-- Gudang Asal --}}
            <div>
                <span class="text-gray-400 block mb-1">Gudang Asal (Keluar)</span>
                <div class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                    <span>{{ $transfer->fromWarehouse?->name ?? '-' }}</span>
                </div>
                <span class="text-[11px] text-gray-400">Kode: {{ $transfer->fromWarehouse?->kode ?? '-' }}</span>
            </div>

            {{-- Gudang Tujuan --}}
            <div>
                <span class="text-gray-400 block mb-1">Gudang Tujuan (Masuk)</span>
                <div class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>{{ $transfer->toWarehouse?->name ?? '-' }}</span>
                </div>
                <span class="text-[11px] text-gray-400">Kode: {{ $transfer->toWarehouse?->kode ?? '-' }}</span>
            </div>

            {{-- Total Qty --}}
            <div>
                <span class="text-gray-400 block mb-1">Total Kuantitas Pindah</span>
                <span class="font-bold font-mono text-[#15803d] text-sm">
                    {{ number_format($transfer->stockTransferItem->sum('quantity'), 0, ',', '.') }} Unit
                </span>
                <span class="text-[11px] text-gray-400 block">({{ $transfer->stockTransferItem->count() }} Varian Barang)</span>
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Item Barang --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                Daftar Barang yang Dipindahkan ({{ $transfer->stockTransferItem->count() }})
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-500 uppercase tracking-wider text-[11px] font-semibold border-b border-gray-100">
                        <th class="py-3 px-4 w-12 text-center">No</th>
                        <th class="py-3 px-4">Nama Produk / SKU</th>
                        <th class="py-3 px-4 text-center">Satuan</th>
                        <th class="py-3 px-4">Rak Asal</th>
                        <th class="py-3 px-4">Rak Tujuan</th>
                        <th class="py-3 px-4 text-right">Qty Mutasi</th>
                        <th class="py-3 px-4 text-right">Dikirim</th>
                        <th class="py-3 px-4 text-right">Diterima</th>
                        <th class="py-3 px-4 text-right">Sisa</th>
                        <th class="py-3 px-4 text-center">Kartu Stok</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @foreach ($transfer->stockTransferItem as $idx => $item)
                        <tr class="hover:bg-gray-50/60 transition">
                            <td class="py-3 px-4 text-center font-mono text-gray-400">
                                {{ $idx + 1 }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-bold text-gray-900">{{ $item->product?->name ?? '-' }}</div>
                                <div class="text-[11px] text-gray-400 font-mono">
                                    SKU: {{ $item->product?->sku ?? '-' }}
                                    @if ($item->product?->barcode)
                                        | Barcode: {{ $item->product->barcode }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center text-gray-600">
                                {{ $item->product?->uom?->name ?? 'Pcs' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $item->fromRak?->name ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $item->toRak?->name ?? '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-gray-900 text-sm">
                                {{ number_format($item->quantity, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono">{{ number_format($item->shippedQuantity(), 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono text-[#15803d] font-semibold">{{ number_format($item->receivedQuantity(), 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-mono {{ $item->remainingToShip() > 0 ? 'text-amber-700 font-semibold' : 'text-gray-400' }}">{{ number_format($item->remainingToShip(), 0, ',', '.') }}</td>
                            <td class="py-3 px-4 text-center">
                                @if ($item->product)
                                    <a href="{{ route('gudang.stok.card', $item->product) }}"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#15803d] hover:text-emerald-900 bg-emerald-50 px-2.5 py-1 rounded-xl border border-emerald-200 hover:bg-emerald-100 transition shadow-sm">
                                        <span>Buku Mutasi</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                        </svg>
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50/80 font-bold text-gray-900 border-t border-gray-100">
                        <td colspan="5" class="py-3 px-4 text-right uppercase tracking-wider text-[11px]">
                            Total Kuantitas:
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-gray-900 text-sm">
                            {{ number_format($transfer->stockTransferItem->sum('quantity'), 0, ',', '.') }}
                        </td>
                        <td class="py-3 px-4 text-right font-mono">{{ number_format($transfer->stockTransferItem->sum(fn ($i) => $i->shippedQuantity()), 0, ',', '.') }}</td>
                        <td class="py-3 px-4 text-right font-mono text-[#15803d]">{{ number_format($transfer->stockTransferItem->sum(fn ($i) => $i->receivedQuantity()), 0, ',', '.') }}</td>
                        <td class="py-3 px-4 text-right font-mono">{{ number_format($transfer->stockTransferItem->sum(fn ($i) => $i->remainingToShip()), 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Daftar pengiriman (gudang asal) & penerimaan (gudang tujuan) --}}
    @if ($transfer->shipments->isNotEmpty())
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" x-data>
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-100">
                <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">Pengiriman ({{ $transfer->shipments->count() }})</h3>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($transfer->shipments as $shipment)
                    <div class="px-5 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs">
                        <div>
                            <div class="font-bold font-mono text-gray-900">{{ $shipment->shipment_number }}</div>
                            <div class="text-gray-500">Dikirim {{ $shipment->shipped_at?->translatedFormat('d M Y H:i') }} oleh {{ $shipment->shipper?->name ?? '-' }}
                                @if ($shipment->notes) · {{ $shipment->notes }} @endif</div>
                            <div class="text-gray-700 mt-1">
                                @foreach ($shipment->items as $line)
                                    <span class="inline-block mr-3">{{ $line->transferItem?->product?->name ?? '-' }}: <strong>{{ number_format($line->quantity, 0, ',', '.') }}</strong></span>
                                @endforeach
                            </div>
                        </div>
                        @if ($shipment->received_at)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#f0fdf4] text-[#15803d] border border-[#86efac] font-semibold whitespace-nowrap">
                                Diterima {{ $shipment->received_at->translatedFormat('d M H:i') }} · {{ $shipment->receiver?->name ?? '-' }}
                            </span>
                        @else
                            <button type="button"
                                    @click="$dispatch('open-modal-receive-shipment', { action: @js(route('gudang.transfer.receive', $shipment)), number: @js($shipment->shipment_number) })"
                                    class="px-3.5 py-2 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#14532d] rounded-xl shadow-sm whitespace-nowrap">
                                Terima di Gudang Tujuan
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Modal Penolakan Transfer --}}
<div id="rejectModal" class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Tolak Transfer Stok</h3>
            <button type="button" onclick="closeRejectModal()" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>
        <form action="{{ route('gudang.transfer.reject', $transfer) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Penolakan</label>
                <textarea name="reason"
                          rows="3"
                          placeholder="Tuliskan alasan penolakan transfer stok..."
                          class="w-full px-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-rose-500 focus:border-rose-500 text-gray-800"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeRejectModal()" class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl transition">
                    Batal
                </button>
                <button type="submit" class="px-3.5 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition shadow-sm active:scale-[0.98]">
                    Konfirmasi Tolak
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openRejectModal() {
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
</script>
    {{-- Modal Konfirmasi Ajukan Transfer --}}
    <x-confirmation-modal
        name="request-transfer"
        title="Ajukan Transfer Stok"
        variant="warning"
        confirm-text="Ya, Ajukan Transfer"
        cancel-text="Batal"
        action="{{ route('gudang.transfer.request', $transfer) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin mengajukan transfer stok <strong>{{ $transfer->transfer_number }}</strong> untuk persetujuan supervisor/manajer gudang?
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Hapus Transfer (Draft, Reject, Cancelled) --}}
    @if (in_array(strtolower((string) $transfer->status), ['draft', 'reject', 'cancelled']))
    <x-confirmation-modal
        name="delete-transfer"
        title="Hapus Dokumen Transfer Stok"
        variant="danger"
        confirm-text="Ya, Hapus Dokumen"
        cancel-text="Batal"
        action="{{ route('gudang.transfer.destroy', $transfer) }}"
        method="DELETE"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin menghapus transfer stok <strong>{{ $transfer->transfer_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>
    @endif

    {{-- Modal Konfirmasi Setujui Transfer --}}
    <x-confirmation-modal
        name="approve-transfer"
        title="Setujui Transfer Stok Antar Gudang"
        variant="success"
        confirm-text="Ya, Setujui"
        cancel-text="Batal"
        action="{{ route('gudang.transfer.approve', $transfer) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Setujui transfer stok <strong>{{ $transfer->transfer_number }}</strong>?
        </p>
        <p class="text-xs text-[#15803d] mt-2 font-medium">
            Stok di gudang asal akan <strong>dicadangkan</strong> (belum berpindah). Stok baru keluar saat gudang asal menekan Kirim Barang, dan masuk ke gudang tujuan saat diterima.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Void Transfer (Super Admin) --}}
    <x-confirmation-modal
        name="void-transfer"
        title="Void Transfer Stok Antar Gudang (Super Admin)"
        variant="danger"
        confirm-text="Ya, Void Transfer"
        cancel-text="Batal"
        action="{{ route('gudang.transfer.void', $transfer) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Apakah Anda yakin ingin melakukan <strong>Void</strong> pada transfer stok <strong>{{ $transfer->transfer_number }}</strong>?
        </p>
        <div class="mt-2 p-3 bg-rose-50 border border-rose-200 rounded-xl text-left">
            <p class="text-xs text-rose-800 font-semibold">Tindakan ini akan membalikkan mutasi fisik secara otomatis:</p>
            <ul class="text-[11px] text-rose-700 list-disc list-inside mt-1 space-y-0.5">
                <li>Stok barang di gudang tujuan akan dikurangi kembali.</li>
                <li>Stok barang di gudang asal akan dipulihkan kembali.</li>
                <li>Status dokumen transfer akan berubah menjadi Dibatalkan (Cancelled).</li>
            </ul>
        </div>
        <div class="mt-3 text-left">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Void (Wajib Diisi):</label>
            <input type="text" name="cancel_reason" required placeholder="Contoh: Muatan rusak di jalan / salah gudang tujuan" class="w-full text-xs rounded-xl border border-gray-300 px-3 py-2 focus:ring-rose-500 focus:border-rose-500">
        </div>
    </x-confirmation-modal>
    {{-- Modal KIRIM BARANG (gudang asal): qty yang benar-benar naik kendaraan, boleh sebagian --}}
    @if (in_array($transfer->status, ['Approved', 'Shipped']) && $transfer->hasRemainingToShip())
    <x-confirmation-modal name="ship-transfer" title="Kirim Barang dari Gudang Asal" variant="success"
        confirm-text="Kirim" cancel-text="Batal" action="{{ route('gudang.transfer.ship', $transfer) }}" method="POST">
        <p class="text-xs text-gray-600 text-left">Isi qty yang <strong>benar-benar naik kendaraan</strong>. Barang yang tidak ikut dikirim isi 0 — sisanya bisa dikirim berikutnya atau ditutup.</p>
        <div class="mt-3 space-y-2 text-left">
            @foreach ($transfer->stockTransferItem as $item)
                @if ($item->remainingToShip() > 0)
                    <label class="flex items-center justify-between gap-3 text-xs">
                        <span class="flex-1"><span class="font-semibold text-gray-900">{{ $item->product?->name }}</span>
                            <span class="block text-[11px] text-gray-500">Sisa {{ number_format($item->remainingToShip(), 0, ',', '.') }} {{ $item->product?->uom?->name ?? '' }}</span></span>
                        <input type="number" name="quantities[{{ $item->id }}]" min="0" max="{{ $item->remainingToShip() }}" step="1" value="{{ (float) $item->remainingToShip() }}"
                               class="w-24 text-right text-xs rounded-xl border border-gray-300 px-2 py-1.5 focus:ring-[#15803d] focus:border-[#15803d]">
                    </label>
                @endif
            @endforeach
            <input type="text" name="notes" maxlength="500" placeholder="Catatan (mis. kendaraan / driver) — opsional"
                   class="w-full text-xs rounded-xl border border-gray-300 px-3 py-2 focus:ring-[#15803d] focus:border-[#15803d]">
        </div>
    </x-confirmation-modal>

    <x-confirmation-modal name="close-remaining" title="Tutup Sisa Mutasi" variant="warning"
        confirm-text="Ya, Tutup Sisa" cancel-text="Batal" action="{{ route('gudang.transfer.close-remaining', $transfer) }}" method="POST">
        <p class="text-sm text-gray-600 text-left">Qty yang belum dikirim akan <strong>dibatalkan</strong> dan cadangan stok di gudang asal dilepas.@if ($transfer->shipments->isEmpty()) Karena belum ada yang dikirim, mutasi ini menjadi <strong>Dibatalkan</strong>.@endif</p>
        <input type="text" name="reason" required minlength="5" maxlength="500" placeholder="Alasan, mis. kendaraan tidak muat"
               class="mt-3 w-full text-xs rounded-xl border border-gray-300 px-3 py-2 focus:ring-amber-500 focus:border-amber-500">
    </x-confirmation-modal>
    @endif

    {{-- Modal TERIMA (gudang tujuan) --}}
    <x-confirmation-modal name="receive-shipment" title="Terima Barang di Gudang Tujuan" variant="success"
        confirm-text="Ya, Barang Diterima" cancel-text="Batal" method="POST">
        <p class="text-sm text-gray-600">Konfirmasi seluruh barang pada pengiriman <strong x-text="data.number"></strong> sudah tiba di <strong>{{ $transfer->toWarehouse?->name }}</strong>?</p>
        <p class="text-xs text-[#15803d] mt-2 font-medium">Stok gudang tujuan langsung bertambah.</p>
    </x-confirmation-modal>
@endsection
