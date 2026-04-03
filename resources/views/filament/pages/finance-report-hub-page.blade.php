<x-filament-panels::page>
    @php
        $sections = [
            [
                'title' => 'Laporan Utama',
                'items' => [
                    ['label' => 'Neraca (Balance Sheet)', 'url' => \App\Filament\Resources\Reports\BalanceSheetResource::getUrl()],
                    ['label' => 'Laporan Laba Rugi (P&L)', 'url' => \App\Filament\Resources\Reports\ProfitAndLossResource::getUrl()],
                    ['label' => 'Neraca Saldo (Trial Balance)', 'url' => \App\Filament\Pages\TrialBalancePage::getUrl()],
                    ['label' => 'Buku Besar', 'url' => \App\Filament\Pages\BukuBesarPage::getUrl()],
                    ['label' => 'Laporan Arus Kas', 'url' => \App\Filament\Resources\Reports\CashFlowResource::getUrl()],
                ],
            ],
            [
                'title' => 'Analisis & Pendukung',
                'items' => [
                    ['label' => 'HPP / Cost of Goods Sold', 'url' => \App\Filament\Resources\Reports\HppResource::getUrl()],
                    ['label' => 'Cost of Goods Manufacturing', 'url' => \App\Filament\Pages\CostOfGoodsManufacturingPage::getUrl()],
                    ['label' => 'Aging Report (AR/AP)', 'url' => \App\Filament\Resources\Reports\AgeingReportResource::getUrl()],
                    ['label' => 'Profit per Divisi', 'url' => \App\Filament\Pages\ProfitLossMultiDivisionPage::getUrl()],
                    ['label' => 'Drill Down Financial Report', 'url' => \App\Filament\Pages\DrillDownFinancialReportPage::getUrl()],
                    ['label' => 'Financial Statement', 'url' => \App\Filament\Pages\FinancialStatementPage::getUrl()],
                    ['label' => 'ALK Grafik', 'url' => \App\Filament\Pages\AlkGraficPage::getUrl()],
                    ['label' => 'Journal Consolidation', 'url' => \App\Filament\Pages\JournalConsolidationPage::getUrl()],
                ],
            ],
        ];
    @endphp

    <style>
        .report-hub-grid { display:grid; gap:1rem; }
        .report-hub-section { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .report-hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .report-hub-list { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); }
        .report-hub-card { display:block; border:1px solid #dbeafe; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#eff6ff,#f8fafc); color:#1f2937; text-decoration:none; font-weight:600; }
        .report-hub-card:hover { border-color:#60a5fa; background:linear-gradient(135deg,#dbeafe,#eff6ff); }
        .report-hub-note { color:#4b5563; font-size:.875rem; }
    </style>

    <div class="space-y-4" id="finance-report-hub">
        <div class="report-hub-section">
            <div class="report-hub-title">Pusat Laporan Keuangan</div>
            <div class="report-hub-note">Menu laporan keuangan dikonsolidasikan di sini agar sidebar tetap ringkas, sementara semua route laporan lama tetap bisa diakses.</div>
        </div>

        <div class="report-hub-grid">
            @foreach ($sections as $section)
                <section class="report-hub-section">
                    <h2 class="report-hub-title">{{ $section['title'] }}</h2>
                    <div class="report-hub-list">
                        @foreach ($section['items'] as $item)
                            <a href="{{ $item['url'] }}" class="report-hub-card">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>