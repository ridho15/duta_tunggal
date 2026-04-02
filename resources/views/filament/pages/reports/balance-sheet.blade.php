<x-filament::page>
<style>
    /* ── Finance Report – Shared Styles ── */
    .fr-page { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    /* Report header */
    .fr-report-header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);
        color: #fff;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        text-align: center;
        box-shadow: 0 8px 24px rgba(37,99,235,.25);
    }
    .fr-company-name { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; }
    .fr-report-type { font-size: 1.125rem; font-weight: 600; opacity: .9; margin-top: .25rem; }
    .fr-report-period { font-size: .9rem; opacity: .75; margin-top: .25rem; }
    /* Summary cards */
    .fr-card-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .fr-card { background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .fr-card-label { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .35rem; }
    .fr-card-value { font-size: 1.5rem; font-weight: 800; }
    .fr-card-value.green { color: #059669; }
    .fr-card-value.amber { color: #d97706; }
    .fr-card-value.blue { color: #2563eb; }
    .fr-card-value.red { color: #dc2626; }
    /* Report body */
    .fr-body { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,.07); overflow: hidden; margin-bottom: 1.5rem; }
    /* Section header */
    .fr-section-hdr { display: flex; align-items: center; gap: .75rem; padding: .9rem 1.5rem; font-size: 1rem; font-weight: 700; color: #fff; letter-spacing: .03em; }
    .fr-section-hdr.green { background: linear-gradient(135deg,#059669,#10b981); }
    .fr-section-hdr.amber { background: linear-gradient(135deg,#d97706,#f59e0b); }
    .fr-section-hdr.blue { background: linear-gradient(135deg,#1d4ed8,#3b82f6); }
    .fr-section-hdr.orange { background: linear-gradient(135deg,#c2410c,#f97316); }
    /* Sub-section header */
    .fr-sub-hdr { padding: .6rem 1.5rem; font-size: .85rem; font-weight: 700; color: #374151; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; letter-spacing: .01em; }
    /* Account rows */
    .fr-row { display: flex; justify-content: space-between; align-items: center; padding: .55rem 1.5rem .55rem 2.5rem; border-bottom: 1px solid #f1f5f9; font-size: .9rem; }
    .fr-row:last-child { border-bottom: none; }
    .fr-row:hover { background: #f8fafc; }
    .fr-row-code { color: #94a3b8; font-size: .78rem; margin-right: .4rem; font-family: monospace; }
    .fr-row-name { flex: 1; color: #374151; }
    .fr-row-amount { font-weight: 600; font-family: 'SF Mono',monospace; font-size: .9rem; white-space: nowrap; }
    .fr-row-amount.negative { color: #dc2626; }
    /* Subtotal row */
    .fr-subtotal { display: flex; justify-content: space-between; align-items: center; padding: .65rem 1.5rem; background: #f1f5f9; border-top: 2px solid #94a3b8; }
    .fr-subtotal-label { font-weight: 700; font-size: .875rem; color: #374151; }
    .fr-subtotal-amount { font-weight: 700; font-size: .9rem; font-family: monospace; }
    /* Grand total row */
    .fr-total { display: flex; justify-content: space-between; align-items: center; padding: .85rem 1.5rem; background: linear-gradient(135deg,#1e3a5f,#1e40af); color: #fff; }
    .fr-total-label { font-weight: 800; font-size: 1rem; letter-spacing: .02em; }
    .fr-total-amount { font-weight: 800; font-size: 1.05rem; font-family: monospace; }
    /* Balance check */
    .fr-balance-check { margin: 0; padding: .75rem 1.5rem; font-weight: 700; font-size: .875rem; text-align: center; }
    .fr-balance-check.ok { background: #dcfce7; color: #166534; }
    .fr-balance-check.fail { background: #fee2e2; color: #991b1b; }
    /* Notes */
    .fr-notes { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1rem 1.5rem; font-size: .85rem; color: #92400e; }
    .fr-notes-title { font-weight: 700; margin-bottom: .4rem; }
    /* Action buttons */
    .fr-actions { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 1rem; }
    /* Print */
    @media print {
        .no-print, .fi-topbar, .fi-sidebar, .fi-page-header, nav { display:none!important; }
        .fr-body { box-shadow: none; border: 1px solid #ccc; }
    }
    /* Dark mode compat – mengikuti dark mode WEBSITE (class .dark pada <html>), bukan OS */
    .dark .fr-card { background: #1e293b; border-color: #334155; }
    .dark .fr-card-label { color: #94a3b8; }
    .dark .fr-body { background: #1e293b; border-color: #334155; }
    .dark .fr-row { color: #e2e8f0; border-color: #334155; }
    .dark .fr-row:hover { background: #273548; }
    .dark .fr-row-name { color: #e2e8f0; }
    .dark .fr-row-code { color: #64748b; }
    .dark .fr-sub-hdr { background: #273548; color: #e2e8f0; border-color: #334155; }
    .dark .fr-subtotal { background: #1e293b; border-top-color: #475569; }
    .dark .fr-subtotal-label { color: #e2e8f0; }
    .dark .fr-subtotal-amount { color: #e2e8f0; }
    .dark .fr-notes { background: #1c1917; border-color: #78350f; color: #fde68a; }
    .dark .fr-balance-check.ok { background: #14532d; color: #bbf7d0; }
    .dark .fr-balance-check.fail { background: #7f1d1d; color: #fecaca; }
</style>

    <div class="fr-page">
        <form wire:submit.prevent class="no-print">
            {{ $this->form }}
        </form>

        @if($this->showPreview)

        @php
            $data = $this->getReportData();
            $asOfDate = $this->as_of_date ?? now()->format('Y-m-d');
        @endphp

        {{-- Report Header --}}
        <div class="fr-report-header">
            <div class="fr-company-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fr-report-type">LAPORAN POSISI KEUANGAN (NERACA)</div>
            <div class="fr-report-period">Per {{ \Carbon\Carbon::parse($asOfDate)->isoFormat('D MMMM GGGG') }}</div>
        </div>

        {{-- Summary Cards --}}
        @if(!$data['balanced'])
        <div class="fr-notes" style="border-color:#fca5a5;background:#fef2f2;color:#991b1b;margin-bottom:1rem;">
            <div class="fr-notes-title">&#9888; Neraca Tidak Seimbang</div>
            <p>{{ $data['balance_warning'] }}</p>
            <p style="margin-top:.35rem;font-weight:700;">Selisih aktual: Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}</p>
        </div>
        @endif

        <div class="fr-card-grid no-print">
            <div class="fr-card">
                <div class="fr-card-label">&#127968; Total Aset</div>
                <div class="fr-card-value green">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#128196; Total Kewajiban</div>
                <div class="fr-card-value amber">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#127981; Total Ekuitas</div>
                <div class="fr-card-value blue">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#9878; Status Neraca</div>
                @if($data['balanced'])
                    <div class="fr-card-value green" style="font-size:1.1rem;">&#10003; Seimbang</div>
                @else
                    <div class="fr-card-value red" style="font-size:1.1rem;">&#10007; Tidak Seimbang</div>
                @endif
            </div>
            @if(!$data['balanced'])
            <div class="fr-card">
                <div class="fr-card-label">Selisih Neraca</div>
                <div class="fr-card-value red">Rp {{ number_format(abs($data['difference']), 0, ',', '.') }}</div>
            </div>
            @endif
        </div>

        {{-- ===== NERACA BODY ===== --}}
        <div class="fr-body">
            {{-- ASET --}}
            <div class="fr-section-hdr green">&#127968; ASET</div>
            @foreach($data['assets'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name">
                            <span class="fr-row-code">{{ $row['coa']->code }}</span>
                            {{ $row['coa']->name }}
                        </span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="fr-total">
                <span class="fr-total-label">TOTAL ASET</span>
                <span class="fr-total-amount">Rp {{ number_format($data['asset_total'], 0, ',', '.') }}</span>
            </div>

            {{-- LIABILITAS --}}
            <div class="fr-section-hdr amber">&#128196; LIABILITAS (KEWAJIBAN)</div>
            @foreach($data['liabilities'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name">
                            <span class="fr-row-code">{{ $row['coa']->code }}</span>
                            {{ $row['coa']->name }}
                        </span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="fr-total" style="background:linear-gradient(135deg,#92400e,#d97706);">
                <span class="fr-total-label">TOTAL LIABILITAS</span>
                <span class="fr-total-amount">Rp {{ number_format($data['liab_total'], 0, ',', '.') }}</span>
            </div>

            {{-- EKUITAS --}}
            <div class="fr-section-hdr blue">&#127981; EKUITAS (MODAL)</div>
            @foreach($data['equity'] as $group)
                <div class="fr-sub-hdr">{{ $group['parent'] }}</div>
                @foreach($group['items'] as $row)
                    @php $bal = $row['balance']; @endphp
                    <div class="fr-row">
                        <span class="fr-row-name">
                            <span class="fr-row-code">{{ $row['coa']->code }}</span>
                            {{ $row['coa']->name }}
                        </span>
                        <span class="fr-row-amount {{ $bal < 0 ? 'negative' : '' }}">
                            @if($bal < 0)(Rp {{ number_format(abs($bal), 0, ',', '.') }})@else Rp {{ number_format($bal, 0, ',', '.') }}@endif
                        </span>
                    </div>
                @endforeach
                <div class="fr-subtotal">
                    <span class="fr-subtotal-label">Subtotal {{ $group['parent'] }}</span>
                    <span class="fr-subtotal-amount">Rp {{ number_format($group['subtotal'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            @if($data['retained_earnings'] != 0)
                <div class="fr-row">
                    <span class="fr-row-name"><span class="fr-row-code">3400</span>Laba Ditahan</span>
                    <span class="fr-row-amount {{ $data['retained_earnings'] < 0 ? 'negative' : '' }}">
                        @if($data['retained_earnings'] < 0)(Rp {{ number_format(abs($data['retained_earnings']), 0, ',', '.') }})
                        @else Rp {{ number_format($data['retained_earnings'], 0, ',', '.') }}@endif
                    </span>
                </div>
            @endif
            @if(isset($data['current_earnings']) && $data['current_earnings'] != 0)
                <div class="fr-row">
                    <span class="fr-row-name"><span class="fr-row-code">3500</span>Laba Periode Berjalan</span>
                    <span class="fr-row-amount {{ $data['current_earnings'] < 0 ? 'negative' : '' }}">
                        @if($data['current_earnings'] < 0)(Rp {{ number_format(abs($data['current_earnings']), 0, ',', '.') }})
                        @else Rp {{ number_format($data['current_earnings'], 0, ',', '.') }}@endif
                    </span>
                </div>
            @endif
            <div class="fr-total" style="background:linear-gradient(135deg,#1e40af,#3b82f6);">
                <span class="fr-total-label">TOTAL EKUITAS</span>
                <span class="fr-total-amount">Rp {{ number_format($data['equity_total'], 0, ',', '.') }}</span>
            </div>

            {{-- GRAND TOTAL --}}
            <div class="fr-total" style="background:linear-gradient(135deg,#0f172a,#1e293b);font-size:1.1rem;padding:1rem 1.5rem;">
                <span class="fr-total-label" style="font-size:1.1rem;">TOTAL LIABILITAS &amp; EKUITAS</span>
                <span class="fr-total-amount" style="font-size:1.1rem;">Rp {{ number_format($data['liab_total'] + $data['equity_total'], 0, ',', '.') }}</span>
            </div>

            {{-- Balance Check --}}
            <div class="fr-balance-check {{ $data['balanced'] ? 'ok' : 'fail' }}">
                @if($data['balanced'])
                    &#10003; Neraca Seimbang — Total Aset = Total Liabilitas + Ekuitas
                @else
                    &#9888; Neraca Tidak Seimbang — Selisih: Rp {{ number_format(abs($data['asset_total'] - ($data['liab_total'] + $data['equity_total'])), 0, ',', '.') }}
                @endif
            </div>
        </div>

        @if($data['has_unbalanced_entries'])
        <div class="fr-notes" style="border-color:#fca5a5;background:#fef2f2;color:#991b1b;">
            <div class="fr-notes-title">&#9888; Peringatan: Terdapat Jurnal Tidak Seimbang</div>
            <p>Ada {{ count($data['unbalanced_entries']) }} jurnal dengan total debit ≠ total kredit. Harap verifikasi data jurnal.</p>
        </div>
        @endif

        {{-- Notes --}}
        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Catatan:</div>
            <ul style="list-style:disc;padding-left:1.2rem;margin-top:.25rem;line-height:1.7;">
                <li>Akumulasi penyusutan ditampilkan sebagai pengurang Aset Tetap</li>
                <li>Total Aset = Total Liabilitas + Total Ekuitas</li>
                <li>Data diambil dari Buku Besar (General Ledger) per tanggal yang dipilih</li>
            </ul>
        </div>

        {{-- Actions --}}
        <div class="fr-actions no-print">
            <x-filament::button wire:click="$refresh" color="primary" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
            <x-filament::button onclick="window.print()" color="gray" icon="heroicon-m-printer">Cetak</x-filament::button>
        </div>

        @else

        <div class="mt-6 p-10 border rounded-xl bg-gray-50 dark:bg-gray-800 text-center text-gray-500">
            <x-heroicon-o-document-chart-bar class="w-16 h-16 mx-auto mb-4 text-gray-400" />
            <p class="text-lg font-semibold">Laporan Posisi Keuangan (Neraca)</p>
            <p class="mt-1 text-sm">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
        </div>

        @endif
    </div>
</x-filament::page>
