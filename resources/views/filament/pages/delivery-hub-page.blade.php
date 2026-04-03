<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Perintah Pengiriman', 'url' => \App\Filament\Resources\DeliveryOrderResource::getUrl()],
            ['label' => 'Penjadwalan Pengiriman', 'url' => \App\Filament\Resources\DeliveryScheduleResource::getUrl()],
            ['label' => 'Surat Jalan', 'url' => \App\Filament\Resources\SuratJalanResource::getUrl()],
        ];
    @endphp

    <style>
        .delivery-hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .delivery-hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .delivery-hub-note { color:#4b5563; font-size:.875rem; }
        .delivery-hub-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .delivery-hub-link { display:block; border:1px solid #bfdbfe; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#eff6ff,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .delivery-hub-link:hover { border-color:#60a5fa; background:linear-gradient(135deg,#dbeafe,#eff6ff); }
    </style>

    <div class="space-y-4" id="delivery-hub">
        <section class="delivery-hub-card">
            <div class="delivery-hub-title">Pusat Pengiriman</div>
            <div class="delivery-hub-note">Modul pengiriman dipusatkan di sini agar navigasi lebih rapih dan konsisten.</div>
            <div class="delivery-hub-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="delivery-hub-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>