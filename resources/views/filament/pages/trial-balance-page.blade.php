<x-filament-panels::page>

{{-- ══════════════════════════════════════════════════════════
     PRINT STYLES
     ══════════════════════════════════════════════════════════ --}}
<style>
/* ── Print-only: hide the Filament chrome ── */
@media print {
    .fi-topbar,
    .fi-sidebar,
    .fi-page-header,
    .no-print,
    button { display: none !important; }

    body { margin: 0; padding: 16px; font-size: 11px; }

    .tb-report-wrapper { padding: 0; }

    .tb-table th,
    .tb-table td { font-size: 10px; padding: 3px 6px; }
}

/* ── Screen styles ── */
.tb-report-wrapper {
    font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    padding: 2rem;
    margin-top: 1rem;
}

/* Company / Report Header */
.tb-header {
    text-align: left;
    margin-bottom: 1.25rem;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 1rem;
}

.tb-company-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: #111827;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.tb-report-title {
    font-size: 1.6rem;
    font-weight: 900;
    color: #e24c23;         /* red-orange matching the image */
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-top: .15rem;
}

.tb-period-line {
    font-size: .85rem;
    color: #6b7280;
    margin-top: .25rem;
}

/* Table */
.tb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
}

.tb-table thead tr {
    background: #0e9488;   /* teal / cyan matching the image */
    color: #fff;
}

.tb-table thead th {
    padding: 10px 10px;
    text-align: center;
    font-weight: 700;
    white-space: nowrap;
    border: 1px solid #0d7a70;
}

.tb-table thead th:first-child { text-align: left; }
.tb-table thead th:nth-child(2) { text-align: left; }

/* Body rows */
.tb-table tbody tr {
    border-bottom: 1px solid #e5e7eb;
}

.tb-table tbody tr:hover { background: #f0fdfa; }

.tb-table tbody td {
    padding: 6px 10px;
    color: #374151;
    border-left: 1px solid #f3f4f6;
    border-right: 1px solid #f3f4f6;
}

/* Parent / summary rows */
.tb-row-parent {
    background: #f8fafc !important;
    font-weight: 700;
    color: #111827 !important;
}

.tb-row-parent td { border-bottom: 1px solid #d1d5db !important; }

/* Grand total row */
.tb-row-grand-total {
    background: #0e9488 !important;
    color: #fff !important;
    font-weight: 900;
}

.tb-row-grand-total td {
    border-top: 2px solid #0d7a70;
    color: #fff;
    padding: 8px 10px;
}

/* Numeric columns – right aligned */
.tb-num { text-align: right !important; }

/* Badge for normal balance */
.tb-badge {
    display: inline-block;
    min-width: 1.6rem;
    text-align: center;
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: .72rem;
    font-weight: 700;
}

.tb-badge-d { background: #dbeafe; color: #1d4ed8; }
.tb-badge-c { background: #fce7f3; color: #9d174d; }

/* Indent for child rows */
.tb-child-indent { padding-left: 1.75rem !important; }

/* Footer */
.tb-footer {
    text-align: center;
    margin-top: 1.5rem;
    font-size: .72rem;
    color: #9ca3af;
    border-top: 1px solid #e5e7eb;
    padding-top: .75rem;
    font-style: italic;
}

/* Notes banner */
.tb-notes {
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    border-radius: 10px;
    padding: .75rem 1rem;
    font-size: .8rem;
    color: #065f46;
    margin-bottom: 1rem;
}
</style>

<div class="space-y-4">
    {{-- ── Usage note (screen only) ── --}}
    <div class="tb-notes no-print">
        <strong>&#128161; Cara Penggunaan:</strong>
        Pilih rentang tanggal (dan opsional cabang), lalu klik
        <strong>Tampilkan Laporan</strong> untuk menampilkan Neraca Saldo.
    </div>

    {{-- ── Filter Form ── --}}
    {{ $this->form }}

    {{-- ═══════════════════════════════════════════════
         REPORT OUTPUT  (only shown after Preview click)
         ═══════════════════════════════════════════════ --}}
    @if($this->showPreview)
        @php
            $report  = $this->getTrialBalanceData();
            $rows    = $report['rows'];
            $totals  = $report['grand_totals'];
            $period  = $report['period'];

            $startFmt = \Carbon\Carbon::parse($period['start_date'])->format('d-F-Y');
            $endFmt   = \Carbon\Carbon::parse($period['end_date'])->format('d-F-Y');

            // Translate month names to English-style (already English from Carbon default)
            $startFmt = \Carbon\Carbon::parse($period['start_date'])
                            ->locale('en')->isoFormat('DD-MMMM-YYYY');
            $endFmt   = \Carbon\Carbon::parse($period['end_date'])
                            ->locale('en')->isoFormat('DD-MMMM-YYYY');

            function tbFmt($val) {
                return number_format(abs((float)$val), 2, ',', '.');
            }
        @endphp

        <div class="tb-report-wrapper" id="trial-balance-report">

            {{-- ── Report Header ── --}}
            <div class="tb-header">
                <div class="tb-company-name">PT. DUTA TUNGGAL</div>
                <div class="tb-report-title">TRIAL BALANCE REPORT</div>
                <div class="tb-period-line">
                    From {{ $startFmt }} to {{ $endFmt }}
                    @if($this->cabang_id)
                        &nbsp;|&nbsp; {{ \App\Models\Cabang::find($this->cabang_id)?->nama ?? '' }}
                    @endif
                </div>
            </div>

            {{-- ── Data Table ── --}}
            <div style="overflow-x: auto;">
                <table class="tb-table" id="tb-data-table">
                    <thead>
                        <tr>
                            <th style="width:110px">Account No</th>
                            <th>Account Name</th>
                            <th style="width:80px">Normal<br>Balance</th>
                            <th style="width:120px">Account Type</th>
                            <th style="width:145px">Beginning Balance</th>
                            <th style="width:145px">Debit</th>
                            <th style="width:145px">Credit</th>
                            <th style="width:145px">Ending Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr class="{{ $row->is_parent ? 'tb-row-parent' : '' }}"
                                data-row-type="{{ $row->is_parent ? 'parent' : 'child' }}"
                                data-account-code="{{ $row->code }}">
                                {{-- Account No --}}
                                <td style="{{ $row->is_parent ? '' : 'padding-left:1.75rem' }}">
                                    @if($row->is_parent)
                                        <span style="margin-right:.25rem">-</span>
                                    @endif
                                    {{ $row->code }}
                                </td>

                                {{-- Account Name --}}
                                <td class="{{ $row->is_parent ? '' : 'tb-child-indent' }}">
                                    {{ $row->name }}
                                </td>

                                {{-- Normal Balance badge --}}
                                <td class="tb-num">
                                    <span class="tb-badge {{ $row->normal_balance === 'D' ? 'tb-badge-d' : 'tb-badge-c' }}">
                                        {{ $row->normal_balance }}
                                    </span>
                                </td>

                                {{-- Account Type --}}
                                <td style="text-align:center; text-transform:uppercase; font-size:.72rem">
                                    {{ strtoupper($row->type) }}
                                </td>

                                {{-- Beginning Balance --}}
                                <td class="tb-num">
                                    @if(abs($row->beginning_balance) > 0.001)
                                        {{ tbFmt($row->beginning_balance) }}
                                    @else
                                        <span style="color:#d1d5db">0.00</span>
                                    @endif
                                </td>

                                {{-- Debit --}}
                                <td class="tb-num">
                                    @if($row->period_debit > 0.001)
                                        {{ tbFmt($row->period_debit) }}
                                    @else
                                        <span style="color:#d1d5db">0.00</span>
                                    @endif
                                </td>

                                {{-- Credit --}}
                                <td class="tb-num">
                                    @if($row->period_credit > 0.001)
                                        {{ tbFmt($row->period_credit) }}
                                    @else
                                        <span style="color:#d1d5db">0.00</span>
                                    @endif
                                </td>

                                {{-- Ending Balance --}}
                                <td class="tb-num" style="{{ abs($row->ending_balance) > 0.001 ? 'font-weight:600' : '' }}">
                                    @if(abs($row->ending_balance) > 0.001)
                                        @if($row->ending_balance < 0)
                                            <span style="color:#dc2626">({{ tbFmt($row->ending_balance) }})</span>
                                        @else
                                            {{ tbFmt($row->ending_balance) }}
                                        @endif
                                    @else
                                        <span style="color:#d1d5db">0.00</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align:center; padding:2rem; color:#9ca3af">
                                    Tidak ada data untuk ditampilkan.
                                </td>
                            </tr>
                        @endforelse

                        {{-- ── Grand Total Row ── --}}
                        <tr class="tb-row-grand-total" id="tb-grand-total-row">
                            <td colspan="4" style="font-weight:900; text-align:right; padding-right:1rem">
                                TOTAL
                            </td>
                            <td class="tb-num" id="tb-total-beginning">
                                {{ tbFmt($totals['beginning_balance']) }}
                            </td>
                            <td class="tb-num" id="tb-total-debit">
                                {{ tbFmt($totals['period_debit']) }}
                            </td>
                            <td class="tb-num" id="tb-total-credit">
                                {{ tbFmt($totals['period_credit']) }}
                            </td>
                            <td class="tb-num" id="tb-total-ending">
                                {{ tbFmt($totals['ending_balance']) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- ── Footer ── --}}
            <div class="tb-footer">
                Designed by : Key Accounting Software
            </div>

        </div>{{-- end .tb-report-wrapper --}}

    @endif {{-- showPreview --}}

</div>

</x-filament-panels::page>
<script>
window.addEventListener('open-report-preview', event => {
    const url = event.detail?.url ?? event.detail?.[0]?.url;

    if (url) {
        window.open(url, '_blank', 'noopener');
    }
});
</script>
