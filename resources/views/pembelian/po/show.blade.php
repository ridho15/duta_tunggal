@extends('layouts.app')

@section('title', 'Detail Pesanan Pembelian ' . $purchaseOrder->po_number . ' – Duta Tunggal ERP')

@section('content')
<div 
    class="space-y-6 max-w-7xl mx-auto"
    x-data="{
        activeTab: 'items',
        approveModalOpen: false,
        requestCloseModalOpen: false,
        closeModalOpen: false,
        deleteModalOpen: false
    }"
>
    {{-- Header & Status Stepper --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">
                <a href="{{ route('pembelian.po.index') }}" class="hover:text-blue-600 transition">Pesanan Pembelian</a>
                <span class="text-gray-300">/</span>
                <span class="text-blue-600 font-bold">{{ $purchaseOrder->po_number }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ $purchaseOrder->po_number }}</h1>
                @php
                    $statusBadge = match($purchaseOrder->status) {
                        'draft' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Draf'],
                        'request_approve' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-800', 'label' => 'Menunggu Approval'],
                        'approved' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'Disetujui'],
                        'partially_received' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-800', 'label' => 'Diterima Sebagian'],
                        'completed' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-800', 'label' => 'Selesai'],
                        'closed' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Ditutup'],
                        'request_close' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-800', 'label' => 'Request Close'],
                        default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => ucfirst($purchaseOrder->status)],
                    };
                @endphp
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }}">
                    {{ $statusBadge['label'] }}
                </span>
                @if ($purchaseOrder->is_asset)
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-indigo-100 text-indigo-700">Aset</span>
                @endif
                @if ($purchaseOrder->is_import)
                    <span class="px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">Impor</span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-1">
                Dibuat oleh <span class="font-semibold text-gray-700">{{ $purchaseOrder->createdBy->name ?? 'Sistem' }}</span> pada {{ optional($purchaseOrder->created_at)->format('d/m/Y H:i') }}.
            </p>
        </div>

        {{-- Action Buttons --}}
        <div class="flex items-center gap-2 flex-wrap">
            <a
                href="{{ route('pembelian.po.index') }}"
                class="px-3.5 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm transition active:scale-[0.98]"
            >
                Kembali
            </a>

            @if ($purchaseOrder->status === 'draft')
                <a
                    href="{{ route('pembelian.po.edit', $purchaseOrder) }}"
                    class="px-3.5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Ubah Draf
                </a>

                <button
                    type="button"
                    @click="approveModalOpen = true"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Setujui PO
                </button>

                <button
                    type="button"
                    @click="deleteModalOpen = true"
                    class="px-3.5 py-2 bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 text-xs font-semibold rounded-lg transition"
                >
                    Hapus Draf
                </button>
            @endif

            @if ($purchaseOrder->status === 'request_approve')
                <button
                    type="button"
                    @click="approveModalOpen = true"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Setujui PO
                </button>
            @endif

            @if (in_array($purchaseOrder->status, ['approved', 'partially_received']))
                <a
                    href="{{ route('gudang.penerimaan.create', ['purchase_order_id' => $purchaseOrder->id]) }}"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] inline-flex items-center gap-1.5"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                    + Terima Barang (GRN)
                </a>
                <button
                    type="button"
                    @click="requestCloseModalOpen = true"
                    class="px-3.5 py-2 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-xs font-semibold rounded-lg transition"
                >
                    Ajukan Penutupan
                </button>
                <button
                    type="button"
                    @click="closeModalOpen = true"
                    class="px-3.5 py-2 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 text-xs font-semibold rounded-lg transition"
                >
                    Tutup PO (Close)
                </button>
            @endif

            <a
                href="{{ route('pembelian.po.pdf', $purchaseOrder) }}"
                target="_blank"
                class="px-3.5 py-2 bg-gray-900 hover:bg-black text-white text-xs font-bold rounded-lg shadow-sm transition active:scale-[0.98] inline-flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                Cetak PDF
            </a>
        </div>
    </div>

    {{-- Visual 5-Step Order Stepper --}}
    @php
        $currentStep = match($purchaseOrder->status) {
            'draft' => 1,
            'request_approve' => 1,
            'approved' => 2,
            'partially_received' => 3,
            'completed' => 5,
            'closed' => 5,
            default => 2,
        };
    @endphp
    <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-4">Alur Pemrosesan Pesanan (Workflow)</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            {{-- Step 1 --}}
            <div class="flex items-center gap-2.5 p-2.5 rounded-lg {{ $currentStep >= 1 ? 'bg-blue-50/70 border border-blue-200' : 'bg-gray-50 text-gray-400' }}">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs {{ $currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                    1
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">Draf Dibuat</div>
                    <div class="text-[10px] text-gray-500">{{ optional($purchaseOrder->order_date)->format('d/m/Y') }}</div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="flex items-center gap-2.5 p-2.5 rounded-lg {{ $currentStep >= 2 ? 'bg-blue-50/70 border border-blue-200' : 'bg-gray-50 text-gray-400' }}">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs {{ $currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                    2
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">Disetujui</div>
                    <div class="text-[10px] text-gray-500">
                        {{ $purchaseOrder->date_approved ? \Carbon\Carbon::parse($purchaseOrder->date_approved)->format('d/m/Y') : ($purchaseOrder->status === 'request_approve' ? 'Menunggu' : '-') }}
                    </div>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="flex items-center gap-2.5 p-2.5 rounded-lg {{ $currentStep >= 3 ? 'bg-blue-50/70 border border-blue-200' : 'bg-gray-50 text-gray-400' }}">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs {{ $currentStep >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                    3
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">Penerimaan Gudang</div>
                    <div class="text-[10px] text-gray-500">{{ $fulfillment['status_label'] ?? 'Belum' }}</div>
                </div>
            </div>

            {{-- Step 4 --}}
            <div class="flex items-center gap-2.5 p-2.5 rounded-lg {{ $purchaseOrder->invoice->count() > 0 ? 'bg-blue-50/70 border border-blue-200' : 'bg-gray-50 text-gray-400' }}">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs {{ $purchaseOrder->invoice->count() > 0 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                    4
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">Tagihan Supplier</div>
                    <div class="text-[10px] text-gray-500">{{ $purchaseOrder->invoice->count() > 0 ? $purchaseOrder->invoice->count() . ' Tagihan' : '-' }}</div>
                </div>
            </div>

            {{-- Step 5 --}}
            <div class="flex items-center gap-2.5 p-2.5 rounded-lg {{ in_array($purchaseOrder->status, ['completed', 'closed']) ? 'bg-emerald-50/70 border border-emerald-200' : 'bg-gray-50 text-gray-400' }}">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs {{ in_array($purchaseOrder->status, ['completed', 'closed']) ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                    5
                </div>
                <div class="text-xs">
                    <div class="font-bold text-gray-900">{{ $purchaseOrder->status === 'closed' ? 'PO Ditutup' : 'PO Selesai' }}</div>
                    <div class="text-[10px] text-gray-500">{{ $purchaseOrder->closed_at ? \Carbon\Carbon::parse($purchaseOrder->closed_at)->format('d/m/Y') : '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Detail Overview Card (3 Columns) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Card Info Supplier --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">Informasi Supplier</h3>
            <div class="space-y-1.5 text-sm">
                <div class="font-bold text-gray-900 text-base">{{ $purchaseOrder->supplier->perusahaan ?? '-' }}</div>
                <div class="text-gray-600 text-xs flex items-center gap-1.5">
                    <span class="text-gray-400">U.p:</span>
                    <span class="font-medium">{{ $purchaseOrder->supplier->kontak_person ?? '-' }}</span>
                </div>
                <div class="text-gray-600 text-xs flex items-center gap-1.5">
                    <span class="text-gray-400">Telp:</span>
                    <span>{{ $purchaseOrder->supplier->phone ?? $purchaseOrder->supplier->telepon ?? '-' }}</span>
                </div>
                <div class="text-gray-600 text-xs flex items-center gap-1.5">
                    <span class="text-gray-400">Email:</span>
                    <span>{{ $purchaseOrder->supplier->email ?? '-' }}</span>
                </div>
                <div class="text-gray-500 text-xs pt-1 border-t border-gray-50 leading-relaxed">
                    {{ $purchaseOrder->supplier->address ?? '-' }}
                </div>
            </div>
        </div>

        {{-- Card Info Pengiriman & Termin --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">Logistik & Syarat Pembayaran</h3>
            <div class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-400">Cabang:</span>
                    <span class="font-semibold text-gray-800">{{ $purchaseOrder->cabang->nama ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Gudang Tujuan:</span>
                    <span class="font-semibold text-gray-800">{{ $purchaseOrder->warehouse->name ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Tanggal Order:</span>
                    <span class="font-semibold text-gray-800">{{ optional($purchaseOrder->order_date)->format('d/m/Y') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Estimasi Tiba:</span>
                    <span class="font-semibold text-gray-800">{{ optional($purchaseOrder->expected_date)->format('d/m/Y') ?? '-' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Termin Pembayaran:</span>
                    <span class="font-bold text-gray-900 uppercase">{{ str_replace('_', ' ', $purchaseOrder->top_type ?? 'COD') }}</span>
                </div>
                @if ($purchaseOrder->tempo_hutang > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-400">Tempo Hutang:</span>
                        <span class="font-semibold text-gray-800">{{ $purchaseOrder->tempo_hutang }} Hari</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Card Ringkasan Finansial & Pemenuhan --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 border-b border-gray-100 pb-2">Status Pemenuhan & Nilai PO</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between items-baseline">
                    <span class="text-gray-400 text-xs">Grand Total PO:</span>
                    <div class="text-right">
                        <span class="font-extrabold text-xl text-blue-600">
                            Rp {{ number_format((float)$purchaseOrder->total_amount, 2, ',', '.') }}
                        </span>
                        @if ($purchaseOrder->purchaseOrderCurrency->count() > 0 && $purchaseOrder->purchaseOrderCurrency->first()->currency->code !== 'IDR')
                            @php $curr = $purchaseOrder->purchaseOrderCurrency->first(); @endphp
                            <div class="text-[11px] text-gray-500">
                                Kurs: 1 {{ $curr->currency->code }} = Rp {{ number_format($curr->nominal, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="pt-2 border-t border-gray-100">
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                        <span>Pemenuhan Gudang:</span>
                        <span class="font-bold text-gray-800">{{ $fulfillment['status_label'] ?? 'Belum Diterima' }}</span>
                    </div>
                    @php
                        $pct = $fulfillment['total_ordered'] > 0
                            ? min(100, round(($fulfillment['total_accepted'] / $fulfillment['total_ordered']) * 100))
                            : 0;
                    @endphp
                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: {{ $pct }}%"></div>
                    </div>
                    <div class="flex justify-between text-[11px] text-gray-400 mt-1">
                        <span>{{ $pct }}% Diterima Lengkap</span>
                        <span>{{ round($fulfillment['total_accepted']) }} / {{ round($fulfillment['total_ordered']) }} Unit</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs Navigasi Detail & Dokumen Terkait --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex border-b border-gray-200 text-xs font-bold uppercase tracking-wider text-gray-500 px-4 pt-2">
            <button
                type="button"
                @click="activeTab = 'items'"
                :class="activeTab === 'items' ? 'border-blue-600 text-blue-600 font-bold border-b-2' : 'border-transparent hover:text-gray-700'"
                class="py-3 px-4 border-b-2 transition"
            >
                Rincian Barang Pesanan ({{ $purchaseOrder->purchaseOrderItem->count() }})
            </button>
            <button
                type="button"
                @click="activeTab = 'receipts'"
                :class="activeTab === 'receipts' ? 'border-blue-600 text-blue-600 font-bold border-b-2' : 'border-transparent hover:text-gray-700'"
                class="py-3 px-4 border-b-2 transition flex items-center gap-1.5"
            >
                <span>Penerimaan Gudang</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-600">{{ $purchaseOrder->purchaseReceipt->count() }}</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'qc'"
                :class="activeTab === 'qc' ? 'border-blue-600 text-blue-600 font-bold border-b-2' : 'border-transparent hover:text-gray-700'"
                class="py-3 px-4 border-b-2 transition flex items-center gap-1.5"
            >
                <span>Inspeksi QC</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-600">{{ $purchaseOrder->qualityControls->count() }}</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'invoices'"
                :class="activeTab === 'invoices' ? 'border-blue-600 text-blue-600 font-bold border-b-2' : 'border-transparent hover:text-gray-700'"
                class="py-3 px-4 border-b-2 transition flex items-center gap-1.5"
            >
                <span>Tagihan Supplier</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-100 text-gray-600">{{ $purchaseOrder->invoice->count() }}</span>
            </button>
        </div>

        {{-- Tab 1: Rincian Barang --}}
        <div x-show="activeTab === 'items'" class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="py-3 px-4 w-10 text-center">#</th>
                        <th class="py-3 px-4">Produk & SKU</th>
                        <th class="py-3 px-4">Satuan</th>
                        <th class="py-3 px-4 text-right">Qty Pesan</th>
                        <th class="py-3 px-4 text-right">Qty Diterima</th>
                        <th class="py-3 px-4 text-right">Sisa Belum Diterima</th>
                        <th class="py-3 px-4 text-right">Harga Satuan</th>
                        <th class="py-3 px-4 text-right">Diskon</th>
                        <th class="py-3 px-4 text-center">Pajak</th>
                        <th class="py-3 px-4 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($purchaseOrder->purchaseOrderItem as $idx => $item)
                        @php
                            $sub = \App\Http\Controllers\HelperController::hitungSubtotal(
                                (float) $item->quantity,
                                (float) $item->unit_price,
                                (float) $item->discount,
                                (float) $item->tax,
                                $item->tipe_pajak
                            );
                            $received = (float) $item->purchaseReceiptItem->sum('qty_received');
                            $remaining = max(0, (float)$item->quantity - $received);
                        @endphp
                        <tr class="hover:bg-blue-50/20 transition">
                            <td class="py-3 px-4 text-center text-xs text-gray-400 font-mono">{{ $idx + 1 }}</td>
                            <td class="py-3 px-4 font-semibold text-gray-900">
                                <div>{{ $item->product->name ?? 'Produk #' . $item->product_id }}</div>
                                <div class="text-xs text-gray-400 font-normal">SKU: {{ $item->product->sku ?? '-' }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs font-medium">{{ $item->product->uom?->name ?? 'Pcs' }}</td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">{{ number_format((float)$item->quantity, 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right font-medium {{ $received > 0 ? 'text-emerald-600' : 'text-gray-400' }}">
                                {{ number_format($received, 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right font-medium {{ $remaining > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                                {{ number_format($remaining, 2, ',', '.') }}
                            </td>
                            <td class="py-3 px-4 text-right">Rp {{ number_format((float)$item->unit_price, 2, ',', '.') }}</td>
                            <td class="py-3 px-4 text-right">{{ $item->discount > 0 ? $item->discount . '%' : '-' }}</td>
                            <td class="py-3 px-4 text-center text-xs">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700">
                                    {{ ucfirst($item->tipe_pajak ?? 'none') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900 whitespace-nowrap">
                                Rp {{ number_format((float)$sub, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Tab 2: Penerimaan Gudang --}}
        <div x-show="activeTab === 'receipts'" class="p-4 space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                <div class="text-xs text-gray-500 font-semibold">Riwayat Surat Jalan Masuk & Penerimaan Fisik Gudang</div>
                @if (in_array($purchaseOrder->status, ['approved', 'partially_received']))
                    <a
                        href="{{ route('gudang.penerimaan.create', ['purchase_order_id' => $purchaseOrder->id]) }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-lg transition"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>+ Catat Penerimaan Baru</span>
                    </a>
                @endif
            </div>

            @if ($purchaseOrder->purchaseReceipt->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">No. Penerimaan (SJ Masuk)</th>
                                <th class="py-2.5 px-3">Tanggal Terima</th>
                                <th class="py-2.5 px-3">No. SJ Vendor</th>
                                <th class="py-2.5 px-3">Penerima Gudang</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($purchaseOrder->purchaseReceipt as $receipt)
                                <tr class="hover:bg-blue-50/20 transition">
                                    <td class="py-2.5 px-3 font-semibold text-gray-900">
                                        <a href="{{ route('gudang.penerimaan.show', $receipt) }}" class="text-blue-600 hover:underline">
                                            {{ $receipt->receipt_number ?? ('GRN-' . $receipt->id) }}
                                        </a>
                                    </td>
                                    <td class="py-2.5 px-3">{{ optional($receipt->receipt_date)->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2.5 px-3 font-mono text-xs">{{ $receipt->supplier_delivery_note_number ?: '-' }}</td>
                                    <td class="py-2.5 px-3">{{ $receipt->receivedBy->name ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        @if ($receipt->status === 'completed')
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                                Selesai Masuk Stok
                                            </span>
                                        @elseif ($receipt->status === 'partial')
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                Sebagian Diterima
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                                Draf
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right">
                                        <a href="{{ route('gudang.penerimaan.show', $receipt) }}" class="text-xs text-blue-600 hover:underline font-semibold">
                                            Detail &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <div class="text-sm font-semibold text-gray-600">Belum Ada Riwayat Penerimaan Gudang</div>
                    <div class="text-xs text-gray-400 mt-0.5">Barang pesanan belum diterima di gudang atau belum dibuatkan dokumen Goods Receipt.</div>
                    @if (in_array($purchaseOrder->status, ['approved', 'partially_received']))
                        <div class="mt-3">
                            <a
                                href="{{ route('gudang.penerimaan.create', ['purchase_order_id' => $purchaseOrder->id]) }}"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                <span>+ Catat Penerimaan Barang Sekarang</span>
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Tab 3: QC Inspection --}}
        <div x-show="activeTab === 'qc'" class="p-4">
            @if ($purchaseOrder->qualityControls->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">No. Dokumen QC</th>
                                <th class="py-2.5 px-3">Tanggal QC</th>
                                <th class="py-2.5 px-3">Petugas Pemeriksa</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($purchaseOrder->qualityControls as $qc)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-2.5 px-3 font-semibold text-gray-900">{{ $qc->qc_number ?? ('QC-' . $qc->id) }}</td>
                                    <td class="py-2.5 px-3">{{ optional($qc->inspection_date)->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2.5 px-3">{{ $qc->inspector->name ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                            {{ ucfirst($qc->status ?? 'Completed') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm font-semibold text-gray-600">Belum Ada Pemeriksaan Mutu (QC)</div>
                    <div class="text-xs text-gray-400 mt-0.5">Pemeriksaan kualitas akan tercatat saat barang tiba dan diinspeksi oleh tim QC.</div>
                </div>
            @endif
        </div>

        {{-- Tab 4: Invoices --}}
        <div x-show="activeTab === 'invoices'" class="p-4">
            @if ($purchaseOrder->invoice->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-xs uppercase font-bold text-gray-500 border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-3">No. Tagihan Pembelian</th>
                                <th class="py-2.5 px-3">Tanggal Tagihan</th>
                                <th class="py-2.5 px-3">Jatuh Tempo</th>
                                <th class="py-2.5 px-3 text-right">Total Nilai Tagihan</th>
                                <th class="py-2.5 px-3 text-center">Status Pembayaran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($purchaseOrder->invoice as $inv)
                                <tr class="hover:bg-gray-50/50">
                                    <td class="py-2.5 px-3 font-semibold text-gray-900">{{ $inv->invoice_number }}</td>
                                    <td class="py-2.5 px-3">{{ optional($inv->invoice_date)->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2.5 px-3">{{ optional($inv->due_date)->format('d/m/Y') ?? '-' }}</td>
                                    <td class="py-2.5 px-3 text-right font-bold text-gray-900">
                                        Rp {{ number_format((float)$inv->total_amount, 2, ',', '.') }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            {{ ucfirst($inv->payment_status ?? 'Unpaid') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-8 text-center text-gray-400">
                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 14l6-6m0 0l-6-6m6 6H3" />
                    </svg>
                    <div class="text-sm font-semibold text-gray-600">Belum Ada Tagihan Pembelian</div>
                    <div class="text-xs text-gray-400 mt-0.5">Tagihan supplier (Purchase Invoice) belum diterbitkan untuk pesanan ini.</div>
                </div>
            @endif
        </div>
    </div>

    {{-- Catatan & Alasan Penutupan --}}
    @if ($purchaseOrder->note)
        <div class="bg-amber-50/70 rounded-xl border border-amber-200/80 p-4 text-xs text-amber-900 leading-relaxed">
            <span class="font-bold">Catatan Vendor / Instruksi Pengiriman:</span>
            <div class="mt-1 text-gray-700 whitespace-pre-line">{{ $purchaseOrder->note }}</div>
        </div>
    @endif

    @if ($purchaseOrder->close_reason)
        <div class="bg-rose-50/70 rounded-xl border border-rose-200/80 p-4 text-xs text-rose-900 leading-relaxed">
            <span class="font-bold">Alasan Penutupan PO:</span>
            <div class="mt-1 text-gray-700 whitespace-pre-line">{{ $purchaseOrder->close_reason }}</div>
            @if ($purchaseOrder->closedBy)
                <div class="text-[11px] text-gray-400 mt-2">Ditutup oleh: {{ $purchaseOrder->closedBy->name }} ({{ optional($purchaseOrder->closed_at)->format('d/m/Y H:i') }})</div>
            @endif
        </div>
    @endif

    {{-- Modal Konfirmasi Setujui PO --}}
    <div
        x-show="approveModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="approveModalOpen = false"
    >
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 border border-gray-100" @click.away="approveModalOpen = false">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Setujui PO ini?</h3>
                    <p class="text-xs text-gray-500">{{ $purchaseOrder->po_number }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed">
                Setelah disetujui, PO ini berstatus resmi dan siap menerima pengiriman barang dari supplier di gudang.
            </p>
            <form action="{{ route('pembelian.po.approve', $purchaseOrder) }}" method="POST" class="pt-2 flex justify-end gap-2">
                @csrf
                <button type="button" @click="approveModalOpen = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm transition">
                    Ya, Setujui Sekarang
                </button>
            </form>
        </div>
    </div>

    {{-- Modal Ajukan Penutupan (Request Close) --}}
    <div
        x-show="requestCloseModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="requestCloseModalOpen = false"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-gray-100" @click.away="requestCloseModalOpen = false">
            <h3 class="text-base font-bold text-gray-900">Ajukan Penutupan PO</h3>
            <p class="text-xs text-gray-500">Ajukan penutupan transaksi jika sisa pesanan barang tidak akan dikirim lagi oleh vendor.</p>
            <form action="{{ route('pembelian.po.request-close', $purchaseOrder) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alasan Pengajuan <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" required class="w-full p-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" placeholder="Contoh: Stok barang di supplier discontinue, sisa PO disepakati batal..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="requestCloseModalOpen = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-sm">
                        Kirim Pengajuan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Tutup PO Langsung (Close) --}}
    <div
        x-show="closeModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="closeModalOpen = false"
    >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-gray-100" @click.away="closeModalOpen = false">
            <h3 class="text-base font-bold text-gray-900">Tutup Pesanan Pembelian Resmi</h3>
            <p class="text-xs text-gray-500">PO yang ditutup tidak lagi dapat menerima pengiriman barang di gudang. Masukkan alasan penutupan:</p>
            <form action="{{ route('pembelian.po.close', $purchaseOrder) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Alasan Penutupan <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" required class="w-full p-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500" placeholder="Alasan resmi penutupan PO..."></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeModalOpen = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm">
                        Tutup PO Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Hapus Draf --}}
    <div
        x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
        @keydown.escape.window="deleteModalOpen = false"
    >
        <div class="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4 border border-gray-100" @click.away="deleteModalOpen = false">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-red-50 text-red-600 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-900">Hapus Draf PO?</h3>
                    <p class="text-xs text-gray-500">{{ $purchaseOrder->po_number }}</p>
                </div>
            </div>
            <p class="text-xs text-gray-600 leading-relaxed">
                Apakah Anda yakin ingin menghapus dokumen draf ini? Tindakan ini tidak dapat dibatalkan.
            </p>
            <form action="{{ route('pembelian.po.destroy', $purchaseOrder) }}" method="POST" class="pt-2 flex justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" @click="deleteModalOpen = false" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold shadow-sm">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
