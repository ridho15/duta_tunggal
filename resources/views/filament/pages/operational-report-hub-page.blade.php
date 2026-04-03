<x-filament-panels::page>
    @php
        $items = [
            ['label' => 'Laporan Penjualan', 'url' => \App\Filament\Pages\SalesReportPage::getUrl()],
            ['label' => 'Laporan Pembelian', 'url' => \App\Filament\Pages\PurchaseReportPage::getUrl()],
        ];
    @endphp

    <style>
        .operational-hub-wrap { display:grid; gap:1rem; }
        .operational-hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .operational-hub-title { font-size:1.125rem; font-weight:700; color:#111827; }
        .operational-hub-note { color:#4b5563; font-size:.875rem; margin-top:.5rem; }
        .operational-hub-grid { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .operational-hub-link { display:block; border:1px solid #d1fae5; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#ecfdf5,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .operational-hub-link:hover { border-color:#34d399; background:linear-gradient(135deg,#d1fae5,#ecfdf5); }
    </style>

    <div class="operational-hub-wrap" id="operational-report-hub">
        <section class="operational-hub-card">
            <div class="operational-hub-title">Pusat Laporan Operasional</div>
            <div class="operational-hub-note">Laporan operasional yang sering dipakai digabung di satu parent menu agar navigasi lebih singkat.</div>
            <div class="operational-hub-grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="operational-hub-link">{{ $item['label'] }}</a>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>