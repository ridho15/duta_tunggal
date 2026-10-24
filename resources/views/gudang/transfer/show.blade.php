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
                <span class="text-emerald-700 font-bold font-mono">{{ $transfer->transfer_number }}</span>
            </nav>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight font-mono">
                    {{ $transfer->transfer_number }}
                </h1>
                {{-- Status Badge --}}
                @if (in_array($transfer->status, ['Approved', 'completed']))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Disetujui / Selesai
                    </span>
                @elseif (in_array($transfer->status, ['Request', 'Pending']))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Menunggu Persetujuan
                    </span>
                @elseif (in_array($transfer->status, ['Reject', 'cancelled']))
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                        Ditolak
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                        Draft
                    </span>
                @endif
            </div>
        </div>

        {{-- Top Actions --}}
        <div class="flex items-center gap-2">
            <a href="{{ route('gudang.transfer.index') }}"
               class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                ← Kembali
            </a>

            @if ($transfer->status === 'Draft')
                <a href="{{ route('gudang.transfer.edit', $transfer) }}"
                   class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                    Edit Draft
                </a>

                <button type="button"
                        @click="$dispatch('open-modal-request-transfer')"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Ajukan Transfer</span>
                </button>

                <button type="button"
                        @click="$dispatch('open-modal-delete-transfer')"
                        class="px-3 py-2 text-xs font-semibold text-rose-600 bg-white border border-rose-200 rounded-lg hover:bg-rose-50 transition">
                    Hapus
                </button>
            @endif

            @if (in_array($transfer->status, ['Request', 'Pending']))
                <button type="button"
                        onclick="openRejectModal()"
                        class="px-3.5 py-2 text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded-lg hover:bg-rose-100 transition shadow-2xs">
                    Tolak
                </button>

                <button type="button"
                        @click="$dispatch('open-modal-approve-transfer')"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-[#15803d] hover:bg-[#14532d] rounded-lg transition shadow-2xs">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Setujui & Mutasi Stok</span>
                </button>
            @endif

            @if (in_array($transfer->status, ['Approved', 'completed']))
                <a href="{{ route('gudang.transfer.pdf', $transfer) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-2xs">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10z" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Cetak Surat Jalan (PDF)</span>
                </a>
            @endif
        </div>
    </div>


    {{-- Banner Status Approved --}}
    @if (in_array($transfer->status, ['Approved', 'completed']))
        <div class="p-4 rounded-xl bg-emerald-50/80 border border-emerald-200 text-emerald-900 text-xs flex items-start gap-3">
            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
            <div class="space-y-1">
                <h4 class="font-bold">Transfer Stok Telah Disetujui & Selesai</h4>
                <p class="text-emerald-800 text-[11.5px]">
                    Kuantitas barang telah otomatis dikeluarkan dari <strong>{{ $transfer->fromWarehouse?->name }}</strong> dan dimasukkan ke <strong>{{ $transfer->toWarehouse?->name }}</strong> pada buku mutasi stok.
                </p>
            </div>
        </div>
    @endif

    {{-- Kartu Info Rangkuman Dokumen --}}
    <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-2xs space-y-4">
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
                <span class="font-bold text-emerald-700 text-sm">
                    {{ number_format($transfer->stockTransferItem->sum('quantity'), 0, ',', '.') }} Unit
                </span>
                <span class="text-[11px] text-gray-400 block">({{ $transfer->stockTransferItem->count() }} Varian Barang)</span>
            </div>
        </div>
    </div>

    {{-- Tabel Rincian Item Barang --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                Daftar Barang yang Dipindahkan ({{ $transfer->stockTransferItem->count() }})
            </h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-gray-500 uppercase tracking-wider text-[11px] font-semibold border-b border-gray-200">
                        <th class="py-3 px-4 w-12 text-center">No</th>
                        <th class="py-3 px-4">Nama Produk / SKU</th>
                        <th class="py-3 px-4 text-center">Satuan</th>
                        <th class="py-3 px-4">Rak Asal</th>
                        <th class="py-3 px-4">Rak Tujuan</th>
                        <th class="py-3 px-4 text-right">Kuantitas Pindah</th>
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
                            <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700 text-sm">
                                {{ number_format($item->quantity, 0, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                @if ($item->product)
                                    <a href="{{ route('gudang.stok.card', $item->product) }}"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200 hover:bg-emerald-100 transition">
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
                    <tr class="bg-gray-50/80 font-bold text-gray-900 border-t border-gray-200">
                        <td colspan="5" class="py-3 px-4 text-right uppercase tracking-wider text-[11px]">
                            Total Kuantitas:
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-emerald-800 text-sm">
                            {{ number_format($transfer->stockTransferItem->sum('quantity'), 0, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Modal Penolakan Transfer --}}
<div id="rejectModal" class="fixed inset-0 z-50 bg-gray-900/40 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
            <h3 class="text-sm font-bold text-gray-900">Tolak Transfer Stok</h3>
            <button type="button" onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <form action="{{ route('gudang.transfer.reject', $transfer) }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-gray-700 mb-1">Alasan Penolakan</label>
                <textarea name="reason"
                          rows="3"
                          placeholder="Tuliskan alasan penolakan transfer stok..."
                          class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-rose-500 text-gray-800"></textarea>
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeRejectModal()" class="px-3.5 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">
                    Batal
                </button>
                <button type="submit" class="px-3.5 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg transition shadow-2xs">
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

    {{-- Modal Konfirmasi Hapus Transfer --}}
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
            Apakah Anda yakin ingin menghapus draft transfer stok <strong>{{ $transfer->transfer_number }}</strong>? Tindakan ini tidak dapat dibatalkan.
        </p>
    </x-confirmation-modal>

    {{-- Modal Konfirmasi Setujui Transfer --}}
    <x-confirmation-modal
        name="approve-transfer"
        title="Setujui Transfer Stok Antar Gudang"
        variant="success"
        confirm-text="Ya, Setujui &amp; Mutasi Stok"
        cancel-text="Batal"
        action="{{ route('gudang.transfer.approve', $transfer) }}"
        method="POST"
    >
        <p class="text-sm text-gray-600">
            Setujui transfer stok <strong>{{ $transfer->transfer_number }}</strong>?
        </p>
        <p class="text-xs text-[#15803d] mt-2 font-medium">
            Stok barang di gudang asal akan langsung berkurang dan dialokasikan/ditambahkan ke gudang tujuan sesuai surat jalan transfer.
        </p>
    </x-confirmation-modal>
@endsection
