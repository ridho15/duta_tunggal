<x-filament-panels::page>
    @php
        $sections = [
            [
                'title' => 'Transaksi Gudang',
                'items' => [
                    ['label' => 'Stock Transfer', 'url' => \App\Filament\Resources\StockTransferResource::getUrl()],
                    ['label' => 'Stock Adjustment', 'url' => \App\Filament\Resources\StockAdjustmentResource::getUrl()],
                    ['label' => 'Stock Opname', 'url' => \App\Filament\Resources\StockOpnameResource::getUrl()],
                    ['label' => 'Return Product', 'url' => \App\Filament\Resources\ReturnProductResource::getUrl()],
                ],
            ],
            [
                'title' => 'Monitoring & Konfirmasi',
                'items' => [
                    ['label' => 'Inventory Stock', 'url' => \App\Filament\Resources\InventoryStockResource::getUrl()],
                    ['label' => 'Stock Movement', 'url' => \App\Filament\Resources\StockMovementResource::getUrl()],
                    ['label' => 'Konfirmasi Gudang', 'url' => \App\Filament\Resources\WarehouseConfirmationResource::getUrl()],
                ],
            ],
        ];
    @endphp

    <style>
        .wh-grid { display:grid; gap:1rem; }
        .wh-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .wh-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .wh-note { color:#4b5563; font-size:.875rem; }
        .wh-list { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .wh-link { display:block; border:1px solid #bbf7d0; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#ecfdf5,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .wh-link:hover { border-color:#34d399; background:linear-gradient(135deg,#d1fae5,#ecfdf5); }
    </style>

    <div class="space-y-4" id="warehouse-hub">
        <section class="wh-card">
            <div class="wh-title">Pusat Gudang</div>
            <div class="wh-note">Menu gudang yang padat digabung di sini agar sidebar lebih pendek, tanpa mengubah route modul gudang yang sudah dipakai.</div>
        </section>

        <div class="wh-grid">
            @foreach ($sections as $section)
                <section class="wh-card">
                    <h2 class="wh-title">{{ $section['title'] }}</h2>
                    <div class="wh-list">
                        @foreach ($section['items'] as $item)
                            <a href="{{ $item['url'] }}" class="wh-link">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>