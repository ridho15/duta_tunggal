<x-filament-panels::page>
    @php
        $sections = [
            [
                'title' => 'Jurnal & Kendali Akuntansi',
                'items' => [
                    ['label' => 'Journal Entries', 'url' => \App\Filament\Resources\JournalEntryResource::getUrl()],
                    ['label' => 'Journal Entries (Grouped)', 'url' => \App\Filament\Resources\JournalEntryResource\Pages\GroupedJournalEntries::getUrl()],
                    ['label' => 'AR & AP Management', 'url' => \App\Filament\Pages\ArApManagementPage::getUrl()],
                    ['label' => 'Rekonsiliasi Bank', 'url' => \App\Filament\Resources\BankReconciliationResource::getUrl()],
                ],
            ],
            [
                'title' => 'Schedule & Voucher',
                'items' => [
                    ['label' => 'Ageing Schedule', 'url' => \App\Filament\Resources\AgeingScheduleResource::getUrl()],
                    ['label' => 'Pengajuan Voucher', 'url' => \App\Filament\Resources\VoucherRequestResource::getUrl()],
                ],
            ],
        ];
    @endphp

    <style>
        .hub-grid { display:grid; gap:1rem; }
        .hub-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; padding:1.25rem; box-shadow:0 6px 20px rgba(15,23,42,.05); }
        .hub-title { font-size:1.125rem; font-weight:700; color:#111827; margin-bottom:.75rem; }
        .hub-note { color:#4b5563; font-size:.875rem; }
        .hub-list { display:grid; gap:.75rem; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); margin-top:.75rem; }
        .hub-link { display:block; border:1px solid #fde68a; border-radius:14px; padding:1rem; background:linear-gradient(135deg,#fef3c7,#fffbeb); color:#1f2937; text-decoration:none; font-weight:600; }
        .hub-link:hover { border-color:#f59e0b; background:linear-gradient(135deg,#fde68a,#fef3c7); }
    </style>

    <div class="space-y-4" id="accounting-hub">
        <section class="hub-card">
            <div class="hub-title">Pusat Akuntansi</div>
            <div class="hub-note">Menu akuntansi dipusatkan di halaman ini agar group Finance - Akuntansi tetap ringkas, sementara URL lama tetap dapat diakses.</div>
        </section>

        <div class="hub-grid">
            @foreach ($sections as $section)
                <section class="hub-card">
                    <h2 class="hub-title">{{ $section['title'] }}</h2>
                    <div class="hub-list">
                        @foreach ($section['items'] as $item)
                            <a href="{{ $item['url'] }}" class="hub-link">{{ $item['label'] }}</a>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>