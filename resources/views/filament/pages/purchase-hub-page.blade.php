<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Permintaan Pembelian', 'url' => \App\Filament\Resources\OrderRequestResource::getUrl()],
            ['label' => 'Pesanan Pembelian', 'url' => \App\Filament\Resources\PurchaseOrderResource::getUrl()],
            ['label' => 'Kontrol Kualitas Pembelian', 'url' => \App\Filament\Resources\QualityControlPurchaseResource::getUrl()],
            ['label' => 'Penerimaan Pembelian', 'url' => \App\Filament\Resources\PurchaseReceiptResource::getUrl()],
            ['label' => 'Retur Pembelian', 'url' => \App\Filament\Resources\PurchaseReturnResource::getUrl()],
        ];
    @endphp

    <style>
        .purchase-hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .purchase-hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .purchase-hub-note { color:#4b5563; font-size:.875rem; }
        .purchase-hub-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .purchase-hub-link { display:block; border:1px solid #fed7aa; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#fff7ed,#fffbeb); color:#1f2937; text-decoration:none; font-weight:600; }
        .purchase-hub-link:hover { border-color:#fb923c; background:linear-gradient(135deg,#ffedd5,#fff7ed); }
    </style>

    <div class="space-y-4" id="purchase-hub">
        <section class="purchase-hub-card">
            <div class="purchase-hub-title">Pusat Pembelian</div>
            <div class="purchase-hub-note">Modul pembelian dikonsolidasikan di sini agar sidebar lebih singkat tanpa memutus route yang sudah ada.</div>
            <div class="purchase-hub-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="purchase-hub-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>