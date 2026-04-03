<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Piutang Usaha', 'url' => \App\Filament\Resources\AccountReceivableResource::getUrl()],
            ['label' => 'Invoice Penjualan', 'url' => \App\Filament\Resources\SalesInvoiceResource::getUrl()],
            ['label' => 'Penjualan Lainnya', 'url' => \App\Filament\Resources\OtherSaleResource::getUrl()],
        ];
    @endphp

    <style>
        .txn-hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .txn-hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .txn-hub-note { color:#4b5563; font-size:.875rem; }
        .txn-hub-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .txn-hub-link { display:block; border:1px solid #bfdbfe; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#eff6ff,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .txn-hub-link:hover { border-color:#60a5fa; background:linear-gradient(135deg,#dbeafe,#eff6ff); }
    </style>

    <div class="space-y-4" id="finance-sales-hub">
        <section class="txn-hub-card">
            <div class="txn-hub-title">Pusat Keuangan Penjualan</div>
            <div class="txn-hub-note">Modul keuangan penjualan dipusatkan di sini agar group terkait tetap ringkas.</div>
            <div class="txn-hub-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="txn-hub-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>