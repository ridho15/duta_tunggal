<x-filament-panels::page>
@php
    $accentColor = '#ca8a04';
    $accentLight = '#fde047';
    $iconBg      = '#fefce8';
    $heroFrom    = '#fef9c3';
    $heroTo      = '#fde68a';
    $sections = [
        [
            'title' => 'Jurnal & Kendali Akuntansi',
            'items' => [
                ['label' => 'Journal Entries',            'url' => \App\Filament\Resources\JournalEntryResource::getUrl(),                        'icon' => 'book-open',          'desc' => 'Input & kelola jurnal akuntansi', 'class' => \App\Filament\Resources\JournalEntryResource::class],
                ['label' => 'Journal Entries (Grouped)',  'url' => \App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::getUrl(),'icon' => 'table-cells',        'desc' => 'Tampilan jurnal terkelompok per transaksi', 'class' => \App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::class],
                ['label' => 'AR & AP Management',         'url' => \App\Filament\Pages\ArApManagementPage::getUrl(),                              'icon' => 'arrows-right-left',  'desc' => 'Monitor piutang & hutang secara terpadu', 'class' => \App\Filament\Pages\ArApManagementPage::class],
                ['label' => 'Rekonsiliasi Bank',          'url' => \App\Filament\Resources\BankReconciliationResource::getUrl(),                  'icon' => 'building-library',   'desc' => 'Cocokkan mutasi bank dengan pembukuan', 'class' => \App\Filament\Resources\BankReconciliationResource::class],
            ],
        ],
        [
            'title' => 'Schedule & Voucher',
            'items' => [
                ['label' => 'Ageing Schedule',    'url' => \App\Filament\Resources\AgeingScheduleResource::getUrl(),  'icon' => 'clock',   'desc' => 'Analisis umur piutang & hutang', 'class' => \App\Filament\Resources\AgeingScheduleResource::class],
                ['label' => 'Pengajuan Voucher',  'url' => \App\Filament\Resources\VoucherRequestResource::getUrl(), 'icon' => 'ticket',   'desc' => 'Ajukan voucher kas/biaya untuk approval', 'class' => \App\Filament\Resources\VoucherRequestResource::class],
            ],
        ],
        [
            'title' => 'Laporan Keuangan',
            'items' => [
                ['label' => 'Neraca (Balance Sheet)',       'url' => \App\Filament\Resources\Reports\BalanceSheetResource::getUrl(),  'icon' => 'chart-bar',             'desc' => 'Posisi aset, liabilitas & ekuitas', 'class' => \App\Filament\Resources\Reports\BalanceSheetResource::class],
                ['label' => 'Laporan Laba Rugi (P&L)',      'url' => \App\Filament\Resources\Reports\ProfitAndLossResource::getUrl(), 'icon' => 'arrow-trending-up',     'desc' => 'Kinerja pendapatan & beban operasional', 'class' => \App\Filament\Resources\Reports\ProfitAndLossResource::class],
                ['label' => 'Neraca Saldo (Trial Balance)', 'url' => \App\Filament\Pages\TrialBalancePage::getUrl(),                  'icon' => 'table-cells',           'desc' => 'Saldo debet/kredit per akun', 'class' => \App\Filament\Pages\TrialBalancePage::class],
                ['label' => 'Buku Besar',                   'url' => \App\Filament\Pages\BukuBesarPage::getUrl(),                     'icon' => 'book-open',             'desc' => 'Rincian mutasi per akun COA', 'class' => \App\Filament\Pages\BukuBesarPage::class],
                ['label' => 'Laporan Arus Kas',             'url' => \App\Filament\Resources\Reports\CashFlowResource::getUrl(),     'icon' => 'banknotes',             'desc' => 'Aliran dana masuk & keluar perusahaan', 'class' => \App\Filament\Resources\Reports\CashFlowResource::class],
                ['label' => 'HPP / Cost of Goods Sold',     'url' => \App\Filament\Resources\Reports\HppResource::getUrl(),          'icon' => 'calculator',            'desc' => 'Hitung biaya pokok penjualan', 'class' => \App\Filament\Resources\Reports\HppResource::class],
                ['label' => 'Cost of Goods Manufacturing',  'url' => \App\Filament\Pages\CostOfGoodsManufacturingPage::getUrl(),      'icon' => 'cog-6-tooth',           'desc' => 'Biaya produksi barang selesai', 'class' => \App\Filament\Pages\CostOfGoodsManufacturingPage::class],
                ['label' => 'Aging Report (AR/AP)',         'url' => \App\Filament\Resources\Reports\AgeingReportResource::getUrl(), 'icon' => 'clock',                 'desc' => 'Analisis umur piutang & hutang', 'class' => \App\Filament\Resources\Reports\AgeingReportResource::class],
                ['label' => 'Profit per Divisi',            'url' => \App\Filament\Pages\ProfitLossMultiDivisionPage::getUrl(),       'icon' => 'chart-pie',             'desc' => 'Laba rugi per unit bisnis/divisi', 'class' => \App\Filament\Pages\ProfitLossMultiDivisionPage::class],
                ['label' => 'Drill Down Financial Report',  'url' => \App\Filament\Pages\DrillDownFinancialReportPage::getUrl(),      'icon' => 'magnifying-glass-circle','desc' => 'Analisis mendalam per pos keuangan', 'class' => \App\Filament\Pages\DrillDownFinancialReportPage::class],
                ['label' => 'Financial Statement',          'url' => \App\Filament\Pages\FinancialStatementPage::getUrl(),            'icon' => 'document-chart-bar',    'desc' => 'Laporan keuangan gabungan lengkap', 'class' => \App\Filament\Pages\FinancialStatementPage::class],
                ['label' => 'ALK Grafik',                   'url' => \App\Filament\Pages\AlkGraficPage::getUrl(),                     'icon' => 'presentation-chart-line','desc' => 'Visualisasi analisis laporan keuangan', 'class' => \App\Filament\Pages\AlkGraficPage::class],
                ['label' => 'Journal Consolidation',        'url' => \App\Filament\Pages\JournalConsolidationPage::getUrl(),          'icon' => 'server-stack',          'desc' => 'Konsolidasi jurnal lintas divisi', 'class' => \App\Filament\Pages\JournalConsolidationPage::class],
            ],
        ],
        [
            'title' => 'Manajemen Aset',
            'items' => [
                ['label' => 'Aset Tetap',    'url' => \App\Filament\Resources\AssetResource::getUrl(),         'icon' => 'building-office-2',  'desc' => 'Master aset dan nilai buku', 'class' => \App\Filament\Resources\AssetResource::class],
                ['label' => 'Transfer Aset', 'url' => \App\Filament\Resources\AssetTransferResource::getUrl(), 'icon' => 'arrow-right-circle', 'desc' => 'Perpindahan aset antar cabang', 'class' => \App\Filament\Resources\AssetTransferResource::class],
                ['label' => 'Disposal Aset', 'url' => \App\Filament\Resources\AssetDisposalResource::getUrl(), 'icon' => 'archive-box-x-mark', 'desc' => 'Penghapusan aset tetap', 'class' => \App\Filament\Resources\AssetDisposalResource::class],
            ],
        ],
    ];

    $filteredSections = [];
    foreach ($sections as $section) {
        $filteredItems = [];
        foreach ($section['items'] as $item) {
            $class = $item['class'] ?? null;
            if ($class) {
                if (is_subclass_of($class, \Filament\Resources\Resource::class) && !$class::canViewAny()) {
                    continue;
                }
                if (is_subclass_of($class, \Filament\Pages\Page::class) && !$class::canAccess()) {
                    continue;
                }
            }
            $filteredItems[] = $item;
        }
        if (!empty($filteredItems)) {
            $section['items'] = $filteredItems;
            $filteredSections[] = $section;
        }
    }
    $sections = $filteredSections;
    $totalItems = collect($sections)->sum(fn($s) => count($s['items']));
@endphp

@include('filament.pages.partials.hub-styles')

<div id="accounting-hub" style="--hub-c1:{{ $accentColor }};--hub-border:{{ $accentLight }};">

    {{-- Hero --}}
    <div class="hubv2-hero" style="background:linear-gradient(135deg,{{ $heroFrom }},{{ $heroTo }});">
        <div class="hubv2-hero-icon" style="color:{{ $accentColor }};">
            <x-heroicon-o-calculator class="w-9 h-9" />
        </div>
        <div class="hubv2-hero-body">
            <div class="hubv2-hero-badge">Modul ERP &middot; Akuntansi Keuangan</div>
            <h1 class="hubv2-hero-title">Akuntansi</h1>
            <p class="hubv2-hero-subtitle">Kelola jurnal, rekonsiliasi, laporan keuangan, dan manajemen aset langsung dari satu halaman.</p>
        </div>
        <div class="hubv2-hero-meta">
            <span class="hubv2-hero-meta-num">{{ $totalItems }}</span>
            <span class="hubv2-hero-meta-lbl">Modul</span>
        </div>
    </div>

    {{-- Sections --}}
    @foreach($sections as $section)
    <div class="hubv2-sh">
        <span class="hubv2-sh-dot"></span>
        <span class="hubv2-sh-name">{{ $section['title'] }}</span>
        <span class="hubv2-sh-rule"></span>
    </div>
    <div class="hubv2-grid">
        @foreach($section['items'] as $item)
        <a href="{{ $item['url'] }}" class="hubv2-card" data-hub-card>
            <div class="hubv2-ci" style="background:{{ $iconBg }};color:{{ $accentColor }};">
                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="w-5 h-5" />
            </div>
            <div class="hubv2-cb">
                <span class="hubv2-cl">{{ $item['label'] }}</span>
                <span class="hubv2-cd">{{ $item['desc'] }}</span>
            </div>
            <div class="hubv2-ca">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </div>
        </a>
        @endforeach
    </div>
    @endforeach

</div>
</x-filament-panels::page>