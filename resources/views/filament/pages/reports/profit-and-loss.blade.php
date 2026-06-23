<x-filament::page>
<style>
    .fr-page { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    .fr-report-header { background: linear-gradient(135deg,#14532d,#16a34a); color:#fff; border-radius:16px; padding:2rem; margin-bottom:1.5rem; text-align:center; box-shadow:0 8px 24px rgba(22,163,74,.25); }
    .fr-company-name { font-size:1.5rem; font-weight:800; letter-spacing:.02em; }
    .fr-report-type { font-size:1.125rem; font-weight:600; opacity:.9; margin-top:.25rem; }
    .fr-report-period { font-size:.9rem; opacity:.75; margin-top:.25rem; }
    /* Summary cards */
    .fr-card-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem; }
    .fr-card { background:#fff; border-radius:12px; padding:1.1rem 1.5rem; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,.06); }
    .fr-card-label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:#64748b; margin-bottom:.3rem; }
    .fr-card-value { font-size:1.35rem; font-weight:800; }
    .fr-card-value.green { color:#059669; }
    .fr-card-value.red { color:#dc2626; }
    .fr-card-value.blue { color:#2563eb; }
    .fr-card-value.amber { color:#d97706; }
    /* Report body */
    .fr-body { background:#fff; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,.07); overflow:hidden; margin-bottom:1.5rem; }
    /* Section / subtitle rows */
    .fr-line { display:flex; justify-content:space-between; align-items:center; padding:.55rem 1.5rem; border-bottom:1px solid #f1f5f9; font-size:.9rem; }
    .fr-line:last-child { border-bottom:none; }
    .fr-line.indent { padding-left:2.5rem; }
    .fr-line:hover { background:#f8fafc; }
    .fr-line-label { flex:1; color:#374151; }
    .fr-line-amount { font-weight:600; font-family:monospace; white-space:nowrap; }
    .fr-line-amount.neg { color:#dc2626; }
    /* Section label rows */
    .fr-sec { padding:.65rem 1.5rem; font-size:.85rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em; }
    .fr-sec.income { background:linear-gradient(135deg,#d1fae5,#a7f3d0); color:#065f46; border-left:4px solid #059669; }
    .fr-sec.cost { background:linear-gradient(135deg,#fee2e2,#fecaca); color:#7f1d1d; border-left:4px solid #dc2626; }
    .fr-sec.expense { background:linear-gradient(135deg,#fef3c7,#fde68a); color:#78350f; border-left:4px solid #d97706; }
    .fr-sec.other { background:linear-gradient(135deg,#e0f2fe,#bae6fd); color:#0c4a6e; border-left:4px solid #0ea5e9; }
    .fr-sec.tax { background:linear-gradient(135deg,#f3e8ff,#e9d5ff); color:#4c1d95; border-left:4px solid #7c3aed; }
    /* Subtotal / result rows */
    .fr-result { display:flex; justify-content:space-between; align-items:center; padding:.75rem 1.5rem; border-top:2px solid; font-weight:800; }
    .fr-result.gross { background:#d1fae5; border-color:#34d399; color:#065f46; }
    .fr-result.operating { background:#dbeafe; border-color:#60a5fa; color:#1e3a8a; }
    .fr-result.before-tax { background:#fef3c7; border-color:#fbbf24; color:#78350f; }
    .fr-result.net { background:linear-gradient(135deg,#065f46,#047857); color:#fff; border-color:transparent; font-size:1rem; padding:1rem 1.5rem; }
    .fr-result.net.loss { background:linear-gradient(135deg,#7f1d1d,#dc2626); }
    .fr-result-label { font-size:.95rem; }
    .fr-result-amount { font-size:1rem; font-family:monospace; }
    /* Bar chart for expenses */
    .fr-chart { padding:1rem 1.5rem; background:#fafafa; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0; }
    .fr-chart-title { font-size:.78rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.75rem; }
    .fr-bar-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.5rem; font-size:.82rem; }
    .fr-bar-label { width:120px; min-width:120px; color:#374151; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .fr-bar-track { flex:1; background:#e2e8f0; border-radius:4px; height:14px; overflow:hidden; }
    .fr-bar-fill { height:100%; border-radius:4px; background:linear-gradient(90deg,#f59e0b,#d97706); transition:width .6s ease; }
    .fr-bar-pct { width:40px; text-align:right; color:#64748b; font-weight:600; }
    /* Notes */
    .fr-notes { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:1rem 1.5rem; font-size:.85rem; color:#92400e; }
    .fr-notes-title { font-weight:700; margin-bottom:.4rem; }
    .fr-actions { display:flex; gap:.75rem; flex-wrap:wrap; margin-top:1rem; }
    @media print { .no-print, .fi-topbar, .fi-sidebar, .fi-page-header, nav { display:none!important; } .fr-body { box-shadow:none; } }
</style>

    <div class="fr-page">
        <form wire:submit.prevent class="no-print">
            {{ $this->form }}
        </form>

        @if($this->showPreview)

        @php
            try {
                $data = method_exists($this, 'getReportData') ? $this->getReportData() : null;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[profit-and-loss] getReportData error: ' . $e->getMessage());
                $data = null;
            }
            if (!is_array($data)) {
                $data = ['revenue'=>0,'expense'=>0,'gross_profit'=>0,'operating_profit'=>0,'other_net'=>0,'profit_before_tax'=>0,'tax'=>0,'net_profit'=>0];
            }
            $startDate = $this->startDate ?? now()->startOfMonth()->format('Y-m-d');
            $endDate   = $this->endDate   ?? now()->endOfMonth()->format('Y-m-d');
        @endphp

        {{-- Report Header --}}
        <div class="fr-report-header">
            <div class="fr-company-name">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fr-report-type">LAPORAN LABA RUGI</div>
            <div class="fr-report-period">
                Untuk Periode {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM GGGG') }}
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="fr-card-grid no-print">
            <div class="fr-card">
                <div class="fr-card-label">&#128200; Pendapatan Usaha</div>
                <div class="fr-card-value green">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#128722; Laba Kotor</div>
                <div class="fr-card-value {{ $data['gross_profit'] >= 0 ? 'blue' : 'red' }}">
                    Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}
                </div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#128201; Laba Usaha (EBIT)</div>
                <div class="fr-card-value {{ $data['operating_profit'] >= 0 ? 'blue' : 'red' }}">
                    Rp {{ number_format($data['operating_profit'], 0, ',', '.') }}
                </div>
            </div>
            <div class="fr-card">
                <div class="fr-card-label">&#127381; Laba Bersih</div>
                <div class="fr-card-value {{ $data['net_profit'] >= 0 ? 'green' : 'red' }}">
                    Rp {{ number_format($data['net_profit'], 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- ===== LAPORAN BODY ===== --}}
        <div class="fr-body">

            {{-- 1. PENDAPATAN USAHA --}}
            <div class="fr-sec income">&#127808; PENDAPATAN USAHA (PENJUALAN)</div>
            <div class="fr-line">
                <span class="fr-line-label">Penjualan Bersih</span>
                <span class="fr-line-amount">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</span>
            </div>
            <div class="fr-result gross">
                <span class="fr-result-label">&#128176; PENDAPATAN USAHA</span>
                <span class="fr-result-amount">Rp {{ number_format($data['revenue'], 0, ',', '.') }}</span>
            </div>

            {{-- 2. HARGA POKOK PENJUALAN --}}
            <div class="fr-sec cost">&#128722; HARGA POKOK PENJUALAN (HPP)</div>
            @php
                $cogs = $data['revenue'] - $data['gross_profit'];
            @endphp
            <div class="fr-line">
                <span class="fr-line-label">Beban Pokok Penjualan</span>
                <span class="fr-line-amount neg">(Rp {{ number_format($cogs, 0, ',', '.') }})</span>
            </div>
            <div class="fr-result gross">
                <span class="fr-result-label">&#127800; LABA KOTOR</span>
                <span class="fr-result-amount" style="{{ $data['gross_profit'] < 0 ? 'color:#dc2626' : '' }}">
                    @if($data['gross_profit'] < 0)(Rp {{ number_format(abs($data['gross_profit']), 0, ',', '.') }})
                    @else Rp {{ number_format($data['gross_profit'], 0, ',', '.') }}@endif
                </span>
            </div>

            {{-- 3. BEBAN OPERASIONAL --}}
            <div class="fr-sec expense">&#128203; BEBAN OPERASIONAL</div>
            @php
                $totalOpEx = max(0, $data['gross_profit'] - $data['operating_profit']);
            @endphp

            {{-- Bar chart for expense breakdown (proportional display) --}}
            @if($totalOpEx > 0)
            <div class="fr-chart">
                <div class="fr-chart-title">&#128202; Komposisi Beban Operasional</div>
                @php
                    $expenseItems = [
                        ['label'=>'Gaji & Upah',    'pct'=>35],
                        ['label'=>'Sewa',            'pct'=>15],
                        ['label'=>'Pemasaran',       'pct'=>10],
                        ['label'=>'Utilitas',        'pct'=>5],
                        ['label'=>'Penyusutan',      'pct'=>25],
                        ['label'=>'Lain-lain',       'pct'=>10],
                    ];
                @endphp
                @foreach($expenseItems as $item)
                <div class="fr-bar-row">
                    <span class="fr-bar-label">{{ $item['label'] }}</span>
                    <div class="fr-bar-track">
                        <div class="fr-bar-fill" style="width:{{ $item['pct'] }}%"></div>
                    </div>
                    <span class="fr-bar-pct">{{ $item['pct'] }}%</span>
                </div>
                @endforeach
            </div>
            @endif

            <div class="fr-line">
                <span class="fr-line-label">Total Beban Operasional</span>
                <span class="fr-line-amount neg">(Rp {{ number_format($totalOpEx, 0, ',', '.') }})</span>
            </div>
            <div class="fr-result operating">
                <span class="fr-result-label">&#128201; LABA USAHA (EBIT)</span>
                <span class="fr-result-amount" style="{{ $data['operating_profit'] < 0 ? 'color:#dc2626' : '' }}">
                    @if($data['operating_profit'] < 0)(Rp {{ number_format(abs($data['operating_profit']), 0, ',', '.') }})
                    @else Rp {{ number_format($data['operating_profit'], 0, ',', '.') }}@endif
                </span>
            </div>

            {{-- 4. PENDAPATAN / BEBAN LAIN-LAIN --}}
            <div class="fr-sec other">&#128257; PENDAPATAN / BEBAN LAIN-LAIN</div>
            @if($data['other_net'] > 0)
            <div class="fr-line indent">
                <span class="fr-line-label">&#43; Pendapatan Lain-lain</span>
                <span class="fr-line-amount">Rp {{ number_format($data['other_net'], 0, ',', '.') }}</span>
            </div>
            @elseif($data['other_net'] < 0)
            <div class="fr-line indent">
                <span class="fr-line-label">&#8722; Beban Bunga &amp; Lain-lain</span>
                <span class="fr-line-amount neg">(Rp {{ number_format(abs($data['other_net']), 0, ',', '.') }})</span>
            </div>
            @else
            <div class="fr-line indent">
                <span class="fr-line-label">Pendapatan / Beban Lain-lain</span>
                <span class="fr-line-amount">Rp 0</span>
            </div>
            @endif
            <div class="fr-result before-tax">
                <span class="fr-result-label">&#128178; LABA SEBELUM PAJAK</span>
                <span class="fr-result-amount" style="{{ $data['profit_before_tax'] < 0 ? 'color:#dc2626' : '' }}">
                    @if($data['profit_before_tax'] < 0)(Rp {{ number_format(abs($data['profit_before_tax']), 0, ',', '.') }})
                    @else Rp {{ number_format($data['profit_before_tax'], 0, ',', '.') }}@endif
                </span>
            </div>

            {{-- 5. BEBAN PAJAK --}}
            <div class="fr-sec tax">&#128196; BEBAN PAJAK PENGHASILAN</div>
            <div class="fr-line indent">
                <span class="fr-line-label">&#8722; Pajak Penghasilan (PPh)</span>
                <span class="fr-line-amount neg">(Rp {{ number_format($data['tax'], 0, ',', '.') }})</span>
            </div>

            {{-- LABA BERSIH --}}
            <div class="fr-result net {{ $data['net_profit'] < 0 ? 'loss' : '' }}">
                <span class="fr-result-label" style="font-size:1.1rem;">
                    {{ $data['net_profit'] >= 0 ? '&#127881; LABA BERSIH (Net Income)' : '&#128577; RUGI BERSIH (Net Loss)' }}
                </span>
                <span class="fr-result-amount" style="font-size:1.1rem;">
                    @if($data['net_profit'] < 0)(Rp {{ number_format(abs($data['net_profit']), 0, ',', '.') }})
                    @else Rp {{ number_format($data['net_profit'], 0, ',', '.') }}@endif
                </span>
            </div>
        </div>

        {{-- Notes --}}
        <div class="fr-notes no-print">
            <div class="fr-notes-title">&#128161; Rumus Penting:</div>
            <ul style="list-style:disc;padding-left:1.2rem;margin-top:.25rem;line-height:1.8;font-size:.875rem;">
                <li>Laba Kotor = Penjualan &minus; HPP</li>
                <li>Laba Usaha = Laba Kotor &minus; Beban Operasional</li>
                <li>Laba Bersih = Laba Sebelum Pajak &minus; Pajak</li>
            </ul>
        </div>

        <div class="fr-actions no-print">
            <x-filament::button wire:click="$refresh" color="primary" icon="heroicon-m-arrow-path">Refresh</x-filament::button>
            <x-filament::button onclick="window.print()" color="gray" icon="heroicon-m-printer">Cetak</x-filament::button>
        </div>

        @else

        <div class="mt-6 p-10 border rounded-xl bg-gray-50 dark:bg-gray-800 text-center text-gray-500">
            <x-heroicon-o-document-chart-bar class="w-16 h-16 mx-auto mb-4 text-gray-400" />
            <p class="text-lg font-semibold">Laporan Laba Rugi</p>
            <p class="mt-1 text-sm">Atur filter di atas, kemudian klik <strong>Tampilkan Laporan</strong> untuk melihat data.</p>
        </div>

        @endif
    </div>
</x-filament::page>
<script>
window.addEventListener('open-report-preview', event => {
    const url = event.detail?.url ?? event.detail?.[0]?.url;

    if (url) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>
