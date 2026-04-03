<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Utang Usaha', 'url' => \App\Filament\Resources\AccountPayableResource::getUrl()],
            ['label' => 'Invoice Pembelian', 'url' => \App\Filament\Resources\PurchaseInvoiceResource::getUrl()],
        ];
    @endphp

    <style>
        .txn-purchase-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .txn-purchase-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .txn-purchase-note { color:#4b5563; font-size:.875rem; }
        .txn-purchase-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .txn-purchase-link { display:block; border:1px solid #fed7aa; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#fff7ed,#fffbeb); color:#1f2937; text-decoration:none; font-weight:600; }
        .txn-purchase-link:hover { border-color:#fb923c; background:linear-gradient(135deg,#ffedd5,#fff7ed); }
    </style>

    <div class="space-y-4" id="finance-purchase-hub">
        <section class="txn-purchase-card">
            <div class="txn-purchase-title">Pusat Keuangan Pembelian</div>
            <div class="txn-purchase-note">Modul keuangan pembelian dipusatkan di sini agar sidebar lebih rapih.</div>
            <div class="txn-purchase-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="txn-purchase-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>