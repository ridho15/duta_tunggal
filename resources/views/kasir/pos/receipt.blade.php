@extends('layouts.app', ['title' => 'Struk Kasir: ' . $order->order_number])

@section('content')
<div class="space-y-6">

    {{-- Non-Printed Action Bar --}}
    <div class="print:hidden bg-white border border-gray-200 rounded-2xl p-4 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-1">
                <a href="{{ route('kasir.pos.index') }}" class="hover:text-emerald-700">Kasir POS</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-700 font-semibold">Struk Belanja</span>
            </nav>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-extrabold text-gray-900 tracking-tight">Struk Transaksi {{ $order->order_number }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                    Selesai
                </span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Transaksi telah dicatat, stok gudang dipotong, dan jurnal akuntansi telah terposting otomatis.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('kasir.pos.index') }}"
                class="inline-flex items-center gap-1.5 px-3.5 py-2.5 border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 rounded-xl text-xs font-bold transition shadow-xs"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path d="M12 4v16m8-8H4" stroke-width="2" stroke-linecap="round"></path>
                </svg>
                <span>Transaksi Baru (POS)</span>
            </a>

            <button
                type="button"
                onclick="window.print()"
                class="inline-flex items-center gap-2 bg-[#15803d] hover:bg-[#14532d] text-white px-4 py-2.5 rounded-xl font-bold text-xs shadow-sm transition"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Cetak Struk (Print)</span>
            </button>
        </div>
    </div>

    {{-- Printable Thermal Receipt Container --}}
    <div class="flex justify-center">
        <div class="receipt-box w-full max-w-[380px] bg-white border border-gray-300 rounded-2xl p-6 shadow-md print:shadow-none print:border-none print:p-0 print:m-0 font-mono text-[12px] text-gray-900">

            {{-- Store Header --}}
            <div class="text-center pb-3 border-b border-dashed border-gray-400 space-y-1">
                <div class="font-extrabold text-base tracking-wider">DUTA TUNGGAL</div>
                <div class="text-[11px] font-semibold text-gray-700">Distributor & Ritel Bahan Bangunan</div>
                <div class="text-[10px] text-gray-500 leading-tight">
                    {{ $order->cabang?->alamat ?? 'Jl. Raya Utama Duta Tunggal' }}<br>
                    Telp: {{ $order->cabang?->telepon ?? '(021) 555-0199' }}
                </div>
            </div>

            {{-- Transaction Meta Info --}}
            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-1 text-[11px]">
                <div class="flex justify-between">
                    <span>No. Struk:</span>
                    <strong class="font-bold">{{ $order->order_number }}</strong>
                </div>
                <div class="flex justify-between">
                    <span>Tanggal:</span>
                    <span>{{ $order->order_date ? $order->order_date->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Kasir:</span>
                    <span>{{ $order->cashier?->name ?? 'Kasir' }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Pelanggan:</span>
                    <span>{{ $order->customer_name }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Gudang:</span>
                    <span>{{ $order->warehouse?->name ?? 'Gudang Utama' }}</span>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-2 text-[11px]">
                @foreach($order->items as $item)
                    <div>
                        <div class="font-bold text-gray-900 leading-tight">{{ $item->product?->name ?? 'Produk' }}</div>
                        <div class="flex justify-between text-gray-600 mt-0.5">
                            <span>
                                {{ number_format($item->quantity, 0) }} {{ $item->product?->uom?->name ?? 'Pcs' }} x Rp {{ number_format($item->unit_price, 0, ',', '.') }}
                                @if($item->discount_amount > 0)
                                    <span class="text-rose-600">(-{{ number_format($item->discount_amount, 0, ',', '.') }})</span>
                                @endif
                            </span>
                            <span class="font-bold text-gray-900">
                                Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Totals Summary --}}
            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-1 text-[11px]">
                <div class="flex justify-between text-gray-600">
                    <span>Subtotal:</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($order->discount_amount > 0)
                    <div class="flex justify-between text-rose-600">
                        <span>Diskon:</span>
                        <span>- Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if($order->tax_amount > 0)
                    <div class="flex justify-between text-gray-600">
                        <span>PPN (11%):</span>
                        <span>Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm font-extrabold text-gray-900 pt-1 border-t border-dotted border-gray-300">
                    <span>TOTAL:</span>
                    <span>Rp {{ number_format($order->grand_total, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-gray-700 pt-1">
                    <span>Metode:</span>
                    <span class="uppercase font-bold">{{ $order->payment_method }}</span>
                </div>
                <div class="flex justify-between text-gray-700">
                    <span>Bayar:</span>
                    <span>Rp {{ number_format($order->payment_amount, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-gray-900">
                    <span>Kembalian:</span>
                    <span>Rp {{ number_format($order->change_amount, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- Footer Note --}}
            <div class="pt-3 text-center text-[10px] text-gray-500 space-y-1">
                <div class="font-bold text-gray-700">Terima kasih atas kunjungan Anda!</div>
                <div>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan tanpa nota asli.</div>
                <div class="text-[9px] text-gray-400 pt-1">Duta Tunggal ERP • {{ date('Y') }}</div>
            </div>

        </div>
    </div>

</div>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .receipt-box, .receipt-box * {
        visibility: visible;
    }
    .receipt-box {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        max-width: 80mm !important;
        margin: 0 !important;
        padding: 5mm !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    nav, aside, header, footer, .print\\:hidden {
        display: none !important;
    }
}
</style>
@endsection
