<x-filament::page>
<style>
/* ─── Reset & base ──────────────────────────────────────────────── */
.plmd { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; font-size: .87rem; }

/* ─── Report header ─────────────────────────────────────────────── */
.plmd-header { text-align: left; margin-bottom: 1.25rem; border-bottom: 2px solid #1e3a8a; padding-bottom: .75rem; }
.plmd-company { color: #c0392b; font-size: 1rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
.plmd-title   { font-size: 1.3rem; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: .04em; margin-top: .1rem; }
.plmd-period  { font-size: .82rem; font-weight: 700; color: #334155; margin-top: .2rem; }

/* ─── Table wrapper ─────────────────────────────────────────────── */
.plmd-wrap   { overflow-x: auto; margin-bottom: 1.5rem; }
.plmd-table  { width: 100%; border-collapse: collapse; min-width: 720px; }

/* ─── Column widths ─────────────────────────────────────────────── */
.plmd-col-code { width: 110px; min-width: 110px; }
.plmd-col-name { min-width: 220px; }
.plmd-col-bal  { width: 110px; min-width: 110px; text-align: right; }
.plmd-col-vtc  { width: 72px; min-width: 72px; text-align: right; }

/* ─── Header rows ───────────────────────────────────────────────── */
.plmd-th-main {
    background: #2b5fa5;
    color: #fff;
    font-weight: 700;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: .55rem .75rem;
    border: 1px solid #1e3a8a;
    text-align: center;
    white-space: nowrap;
}
.plmd-th-div {
    background: #3b82f6;
    color: #fff;
    font-weight: 700;
    font-size: .78rem;
    text-transform: uppercase;
    letter-spacing: .03em;
    padding: .5rem .6rem;
    border: 1px solid #1e3a8a;
    text-align: center;
}
.plmd-th-sub {
    background: #60a5fa;
    color: #1e3a8a;
    font-weight: 700;
    font-size: .75rem;
    padding: .4rem .6rem;
    border: 1px solid #93c5fd;
    text-align: center;
}

/* ─── Section header rows (group labels) ────────────────────────── */
.plmd-sec {
    background: #dbeafe;
    color: #1e3a8a;
    font-weight: 800;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.plmd-sec td { padding: .5rem .75rem; border-bottom: 1px solid #93c5fd; border-top: 2px solid #3b82f6; }

/* ─── Account rows ──────────────────────────────────────────────── */
.plmd-account td { padding: .42rem .75rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.plmd-account:hover td { background: #f8fafc; }
.plmd-indent-1  { padding-left: 1.5rem !important; }
.plmd-indent-2  { padding-left: 2.5rem !important; }
.plmd-indent-3  { padding-left: 3.5rem !important; }

/* ─── Subtotal rows ─────────────────────────────────────────────── */
.plmd-subtotal td {
    background: #bfdbfe;
    color: #1e3a8a;
    font-weight: 700;
    padding: .48rem .75rem;
    border-top: 1px solid #93c5fd;
    border-bottom: 1px solid #93c5fd;
}

/* ─── Total Revenue row ─────────────────────────────────────────── */
.plmd-total-revenue td {
    background: #1e40af;
    color: #fff;
    font-weight: 800;
    padding: .55rem .75rem;
    border-top: 2px solid #1e3a8a;
    border-bottom: 2px solid #1e3a8a;
}

/* ─── Total COGS row ────────────────────────────────────────────── */
.plmd-total-cogs td {
    background: #fee2e2;
    color: #7f1d1d;
    font-weight: 700;
    padding: .48rem .75rem;
    border-top: 1px solid #fca5a5;
    border-bottom: 1px solid #fca5a5;
}

/* ─── Gross Profit row (highlighted cyan) ───────────────────────── */
.plmd-gross-profit td {
    background: #06b6d4;
    color: #fff;
    font-weight: 900;
    font-size: .9rem;
    padding: .6rem .75rem;
    border-top: 2px solid #0891b2;
    border-bottom: 2px solid #0891b2;
}

/* ─── Operating Profit row ──────────────────────────────────────── */
.plmd-op-profit td {
    background: #1d4ed8;
    color: #fff;
    font-weight: 800;
    padding: .55rem .75rem;
    border-top: 2px solid #1e3a8a;
}

/* ─── Net Profit row ────────────────────────────────────────────── */
.plmd-net-profit td {
    background: linear-gradient(90deg, #065f46, #047857);
    color: #fff;
    font-weight: 900;
    font-size: .95rem;
    padding: .65rem .75rem;
    border-top: 3px solid #047857;
}
.plmd-net-profit.loss td {
    background: linear-gradient(90deg, #7f1d1d, #dc2626) !important;
    border-color: #dc2626 !important;
}

/* ─── Number cells ──────────────────────────────────────────────── */
.plmd-num { text-align: right; font-family: ui-monospace, monospace; white-space: nowrap; }
.plmd-vtc { text-align: right; color: #64748b; font-size: .78rem; white-space: nowrap; }
.plmd-neg { color: #dc2626; }
.plmd-num-white { color: #fff !important; text-align: right; font-family: ui-monospace, monospace; }
.plmd-vtc-white { color: rgba(255,255,255,.7) !important; text-align: right; font-size: .78rem; }
.plmd-vtc-dark  { color: #1e40af !important; text-align: right; font-size: .78rem; }

/* ─── Spacer ────────────────────────────────────────────────────── */
.plmd-spacer td { height: 6px; background: #f8fafc; border: none; }

/* ─── Print ─────────────────────────────────────────────────────── */
@media print {
    .no-print, .fi-topbar, .fi-sidebar, .fi-page-header, nav { display: none !important; }
    .plmd-wrap { overflow: visible; }
    .plmd-table { font-size: .75rem; }
}
</style>

<div class="plmd">

    {{-- ── Filter form ─────────────────────────────────────────────── --}}
    <form wire:submit.prevent class="no-print">
        {{ $this->form }}
    </form>

    @if($this->showReport)
    @php
        try {
            $report = $this->getReportData();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[profit-loss-multi-division] ' . $e->getMessage());
            $report = null;
        }

        $divisions      = $report['divisions']        ?? [];
        $revenueRows    = $report['revenue_rows']      ?? [];
        $totalRevenue   = $report['total_revenue']     ?? [];
        $cogsRows       = $report['cogs_rows']         ?? [];
        $totalCogs      = $report['total_cogs']        ?? [];
        $grossProfit    = $report['gross_profit']      ?? [];
        $opexSections   = $report['opex_sections']     ?? [];
        $totalOpex      = $report['total_opex']        ?? [];
        $opProfit       = $report['operating_profit']  ?? [];
        $otherRows      = $report['other_rows']        ?? [];
        $totalOther     = $report['total_other']       ?? [];
        $netProfit      = $report['net_profit']        ?? [];
        $vtc            = $report['vtc']               ?? [];
        $startDate      = $report['period']['start']   ?? now()->startOfYear()->format('Y-m-d');
        $endDate        = $report['period']['end']     ?? now()->endOfYear()->format('Y-m-d');
        $divIds         = array_column($divisions, 'id');
        $divMap         = array_column($divisions, null, 'id'); // id => division object

        /**
         * Format a numeric amount with comma thousands separator.
         */
        $fmt = fn($v) => number_format((float)$v, 2, '.', ',');

        /**
         * Compute row vtc% given balances and revenue vector.
         */
        $vtcRow = function(array $balances, array $rev) use ($divIds): array {
            $out = [];
            foreach ($divIds as $d) {
                $b = $rev[$d] ?? 0;
                $out[$d] = $b != 0 ? round((($balances[$d] ?? 0) / $b) * 100, 2) : 0.0;
            }
            return $out;
        };

        $formatDate = fn($d) => \Carbon\Carbon::parse($d)->format('d-F-Y');
    @endphp

    @if(!$report)
        <div class="fi-notification fi-notification-error">
            Gagal memuat data laporan. Silakan cek log untuk detail error.
        </div>
    @else

    {{-- ── Report header ──────────────────────────────────────────── --}}
    <div class="plmd-header no-print-border">
        <div class="plmd-company">PT. Duta Tunggal</div>
        <div class="plmd-title">Profit Loss Multiple By Division</div>
        <div class="plmd-period">
            As Of : {{ $formatDate($startDate) }} to {{ $formatDate($endDate) }}
        </div>
    </div>

    {{-- ── Main table ─────────────────────────────────────────────── --}}
    <div class="plmd-wrap">
    <table class="plmd-table" id="plmd-table">
        {{-- ===== TABLE HEAD ===== --}}
        <thead>
        {{-- Row 1: merged headers --}}
        <tr>
            <th class="plmd-th-main plmd-col-code" rowspan="2">AccountNo</th>
            <th class="plmd-th-main plmd-col-name" rowspan="2">AccountName</th>
            @foreach($divisions as $div)
            <th class="plmd-th-div" colspan="2">{{ strtoupper($div['nama'] ?? $div['kode']) }}</th>
            @endforeach
        </tr>
        {{-- Row 2: Balance / Vtc% sub-headers --}}
        <tr>
            @foreach($divisions as $div)
            <th class="plmd-th-sub plmd-col-bal">Balance</th>
            <th class="plmd-th-sub plmd-col-vtc">Vtc%</th>
            @endforeach
        </tr>
        </thead>

        {{-- ===== TABLE BODY ===== --}}
        <tbody>

        {{-- ─── REVENUE ROWS ────────────────────────────────────────── --}}
        @foreach($revenueRows as $row)
            @if($row['type'] === 'section_header')
            <tr class="plmd-sec">
                <td></td>
                <td style="padding-left: {{ ($row['level'] ?? 0) * 1.25 }}rem">{{ $row['name'] }}</td>
                @foreach($divIds as $d)
                <td class="plmd-num" colspan="2"></td>
                @endforeach
            </tr>

            @elseif($row['type'] === 'account')
            <tr class="plmd-account">
                <td class="plmd-col-code" style="color:#64748b; font-size:.8rem;">{{ $row['code'] }}</td>
                <td style="padding-left: {{ (($row['level'] ?? 1) * 1.25) }}rem">{{ $row['name'] }}</td>
                @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                @foreach($divIds as $d)
                <td class="plmd-num plmd-col-bal {{ ($row['balances'][$d] ?? 0) < 0 ? 'plmd-neg' : '' }}">
                    {{ $fmt($row['balances'][$d] ?? 0) }}
                </td>
                <td class="plmd-vtc plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>

            @elseif(in_array($row['type'], ['subtotal']))
            <tr class="plmd-subtotal">
                <td></td>
                <td>{{ $row['name'] }}</td>
                @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                @foreach($divIds as $d)
                <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                <td class="plmd-vtc-dark plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>

            @elseif($row['type'] === 'total_revenue')
            <tr class="plmd-total-revenue">
                <td></td>
                <td><strong>{{ $row['name'] }}</strong></td>
                @foreach($divIds as $d)
                <td class="plmd-num-white plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                <td class="plmd-vtc-white plmd-col-vtc">{{ number_format($vtc['revenue'][$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>
            @endif
        @endforeach

        {{-- spacer --}}
        <tr class="plmd-spacer"><td colspan="{{ 2 + count($divisions) * 2 }}"></td></tr>

        {{-- ─── COGS ROWS ───────────────────────────────────────────── --}}
        @foreach($cogsRows as $row)
            @if($row['type'] === 'section_header')
            <tr class="plmd-sec" style="background:#fee2e2; color:#7f1d1d; border-top-color:#fca5a5;">
                <td></td>
                <td>{{ $row['name'] }}</td>
                @foreach($divIds as $d)<td colspan="2"></td>@endforeach
            </tr>

            @elseif($row['type'] === 'account')
            <tr class="plmd-account">
                <td class="plmd-col-code" style="color:#64748b; font-size:.8rem;">{{ $row['code'] }}</td>
                <td style="padding-left: {{ (($row['level'] ?? 1) * 1.25) }}rem">{{ $row['name'] }}</td>
                @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                @foreach($divIds as $d)
                <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                <td class="plmd-vtc plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>

            @elseif($row['type'] === 'subtotal')
            <tr class="plmd-subtotal" style="background:#fecaca; color:#7f1d1d;">
                <td></td>
                <td>{{ $row['name'] }}</td>
                @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                @foreach($divIds as $d)
                <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                <td class="plmd-vtc plmd-col-vtc" style="color:#7f1d1d;">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>

            @elseif($row['type'] === 'total_cogs')
            <tr class="plmd-total-cogs">
                <td></td>
                <td><strong>{{ $row['name'] }}</strong></td>
                @foreach($divIds as $d)
                <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                <td class="plmd-vtc plmd-col-vtc" style="color:#7f1d1d;">{{ number_format($vtc['cogs'][$d] ?? 0, 2) }}</td>
                @endforeach
            </tr>
            @endif
        @endforeach

        {{-- If no COGS data, show placeholder row --}}
        @if(empty($cogsRows))
        <tr class="plmd-total-cogs">
            <td></td>
            <td><strong>Total Cost Of Goods Sold</strong></td>
            @foreach($divIds as $d)
            <td class="plmd-num plmd-col-bal">0.00</td>
            <td class="plmd-vtc plmd-col-vtc" style="color:#7f1d1d;">0.00</td>
            @endforeach
        </tr>
        @endif

        {{-- ─── GROSS PROFIT ────────────────────────────────────────── --}}
        <tr class="plmd-gross-profit" id="plmd-gross-profit">
            <td></td>
            <td><strong>Gross Profit</strong></td>
            @foreach($divIds as $d)
            <td class="plmd-num-white plmd-col-bal">{{ $fmt($grossProfit[$d] ?? 0) }}</td>
            <td class="plmd-vtc-white plmd-col-vtc">{{ number_format($vtc['gross_profit'][$d] ?? 0, 2) }}</td>
            @endforeach
        </tr>

        {{-- spacer --}}
        <tr class="plmd-spacer"><td colspan="{{ 2 + count($divisions) * 2 }}"></td></tr>

        {{-- ─── OPERATING EXPENSES ─────────────────────────────────── --}}
        @foreach($opexSections as $section)
            @foreach($section['rows'] as $row)
                @if($row['type'] === 'section_header')
                <tr class="plmd-sec">
                    <td></td>
                    <td style="padding-left: {{ ($row['level'] ?? 0) * 1.25 }}rem">{{ $row['name'] }}</td>
                    @foreach($divIds as $d)<td colspan="2"></td>@endforeach
                </tr>

                @elseif($row['type'] === 'account')
                <tr class="plmd-account">
                    <td class="plmd-col-code" style="color:#64748b; font-size:.8rem;">{{ $row['code'] }}</td>
                    <td style="padding-left: {{ (($row['level'] ?? 1) * 1.25) }}rem">{{ $row['name'] }}</td>
                    @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                    @foreach($divIds as $d)
                    <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                    <td class="plmd-vtc plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                    @endforeach
                </tr>

                @elseif($row['type'] === 'subtotal')
                <tr class="plmd-subtotal">
                    <td></td>
                    <td>{{ $row['name'] }}</td>
                    @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
                    @foreach($divIds as $d)
                    <td class="plmd-num plmd-col-bal">{{ $fmt($row['balances'][$d] ?? 0) }}</td>
                    <td class="plmd-vtc-dark plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
                    @endforeach
                </tr>
                @endif
            @endforeach
        @endforeach

        {{-- Total Operating Expenses --}}
        <tr class="plmd-subtotal" style="background:#c7d2fe; color:#1e1b4b; border-top: 2px solid #6366f1;">
            <td></td>
            <td><strong>Total Operating Expenses</strong></td>
            @foreach($divIds as $d)
            <td class="plmd-num plmd-col-bal">{{ $fmt($totalOpex[$d] ?? 0) }}</td>
            <td class="plmd-vtc-dark plmd-col-vtc">{{ number_format($vtc['total_opex'][$d] ?? 0, 2) }}</td>
            @endforeach
        </tr>

        {{-- Operating Profit --}}
        <tr class="plmd-op-profit">
            <td></td>
            <td><strong>Operating Profit (EBIT)</strong></td>
            @foreach($divIds as $d)
            <td class="plmd-num-white plmd-col-bal">{{ $fmt($opProfit[$d] ?? 0) }}</td>
            <td class="plmd-vtc-white plmd-col-vtc">{{ number_format($vtc['operating_profit'][$d] ?? 0, 2) }}</td>
            @endforeach
        </tr>

        {{-- ─── OTHER INCOME / EXPENSE ────────────────────────────── --}}
        @if(!empty($otherRows))
        <tr class="plmd-spacer"><td colspan="{{ 2 + count($divisions) * 2 }}"></td></tr>
        <tr class="plmd-sec" style="background:#e0f2fe; color:#0c4a6e; border-top-color: #38bdf8;">
            <td></td>
            <td>Pendapatan / Beban Lain-Lain</td>
            @foreach($divIds as $d)<td colspan="2"></td>@endforeach
        </tr>
        @foreach($otherRows as $row)
        <tr class="plmd-account">
            <td class="plmd-col-code" style="color:#64748b; font-size:.8rem;">{{ $row['code'] }}</td>
            <td style="padding-left:1.5rem">{{ $row['name'] }}</td>
            @php $rowVtc = $vtcRow($row['balances'] ?? [], $totalRevenue); @endphp
            @foreach($divIds as $d)
            <td class="plmd-num plmd-col-bal {{ ($row['balances'][$d] ?? 0) < 0 ? 'plmd-neg' : '' }}">
                {{ $fmt($row['balances'][$d] ?? 0) }}
            </td>
            <td class="plmd-vtc plmd-col-vtc">{{ number_format($rowVtc[$d] ?? 0, 2) }}</td>
            @endforeach
        </tr>
        @endforeach
        @endif

        {{-- spacer --}}
        <tr class="plmd-spacer"><td colspan="{{ 2 + count($divisions) * 2 }}"></td></tr>

        {{-- ─── NET PROFIT ──────────────────────────────────────────── --}}
        @php $isLoss = collect($netProfit)->min() < 0; @endphp
        <tr class="plmd-net-profit {{ $isLoss ? 'loss' : '' }}" id="plmd-net-profit">
            <td></td>
            <td><strong>{{ $isLoss ? 'Net Loss' : 'Net Profit' }}</strong></td>
            @foreach($divIds as $d)
            <td class="plmd-num-white plmd-col-bal">{{ $fmt($netProfit[$d] ?? 0) }}</td>
            <td class="plmd-vtc-white plmd-col-vtc">{{ number_format($vtc['net_profit'][$d] ?? 0, 2) }}</td>
            @endforeach
        </tr>

        </tbody>
    </table>
    </div>{{-- .plmd-wrap --}}

    {{-- ── Footer note ─────────────────────────────────────────────── --}}
    <div style="font-size:.75rem; color:#94a3b8; margin-top:.5rem; margin-bottom:1.5rem;" class="no-print">
        Vtc% = Vertical Analysis (% terhadap Total Revenue).
        Dicetak: {{ now()->format('d M Y H:i') }}
    </div>

    @endif {{-- @if(!$report) --}}
    @endif {{-- @if($this->showReport) --}}

</div>
</x-filament::page>
