<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statement - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #0f172a; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; margin: 0; font-size: 1rem; font-weight: 700; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .pt-btn-print { background: #0f766e; color: #fff; }
        .pt-btn-close { background: #475569; color: #fff; }
        .report-wrap { max-width: 1160px; margin: 0 auto; padding: 1.5rem; }
        .fs-header { background: linear-gradient(135deg,#0f172a,#0f766e); color: #fff; border-radius: 18px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 16px 36px rgba(15, 118, 110, .22); }
        .fs-company { font-size: 1.55rem; font-weight: 800; letter-spacing: .02em; }
        .fs-title { margin-top: .25rem; font-size: 1.15rem; font-weight: 700; opacity: .95; }
        .fs-period { margin-top: .35rem; font-size: .92rem; opacity: .8; }
        .fs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .fs-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1.15rem 1.25rem; box-shadow: 0 4px 14px rgba(15, 23, 42, .05); }
        .fs-card-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .35rem; }
        .fs-card-value { font-size: 1.4rem; font-weight: 800; }
        .fs-card-value.green { color: #059669; }
        .fs-card-value.blue { color: #2563eb; }
        .fs-card-value.amber { color: #d97706; }
        .fs-card-value.red { color: #dc2626; }
        .fs-card-value.slate { color: #0f172a; }
        .fs-section { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, .06); margin-bottom: 1.5rem; }
        .fs-section-header { padding: 1rem 1.5rem; color: #fff; font-size: 1rem; font-weight: 800; letter-spacing: .03em; }
        .fs-section-header.pl { background: linear-gradient(135deg,#166534,#16a34a); }
        .fs-section-header.bs { background: linear-gradient(135deg,#1d4ed8,#0f766e); }
        .fs-subheader { padding: .75rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; color: #334155; font-size: .82rem; font-weight: 800; text-transform: uppercase; letter-spacing: .06em; }
        .fs-row { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .65rem 1.5rem; border-bottom: 1px solid #f1f5f9; font-size: .92rem; }
        .fs-row:hover { background: #f8fafc; }
        .fs-row-label { flex: 1; color: #334155; }
        .fs-row-code { font-family: monospace; color: #94a3b8; font-size: .8rem; margin-right: .5rem; }
        .fs-row-amount { font-family: monospace; font-weight: 700; white-space: nowrap; }
        .fs-row-amount.negative { color: #dc2626; }
        .fs-total { display: flex; justify-content: space-between; gap: 1rem; align-items: center; padding: .85rem 1.5rem; font-weight: 800; }
        .fs-total.soft { background: #eff6ff; color: #1e3a8a; border-top: 1px solid #bfdbfe; }
        .fs-total.strong { background: linear-gradient(135deg,#0f172a,#1e3a8a); color: #fff; }
        .fs-total.success { background: #dcfce7; color: #166534; border-top: 1px solid #86efac; }
        .fs-total.warn { background: #fee2e2; color: #991b1b; border-top: 1px solid #fca5a5; }
        .fs-two-col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .fs-panel { border-right: 1px solid #e2e8f0; }
        .fs-panel:last-child { border-right: 0; }
        .fs-panel-title { padding: .9rem 1.5rem; background: #f8fafc; color: #0f172a; font-size: .84rem; font-weight: 900; text-transform: uppercase; letter-spacing: .08em; border-bottom: 1px solid #e2e8f0; }
        .fs-note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 1rem 1.25rem; font-size: .85rem; color: #92400e; }
        .fs-empty { padding: .8rem 1.5rem; color: #94a3b8; font-style: italic; }
        @media (max-width: 900px) {
            .fs-two-col { grid-template-columns: 1fr; }
            .fs-panel { border-right: 0; border-bottom: 1px solid #e2e8f0; }
            .fs-panel:last-child { border-bottom: 0; }
        }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none !important; }
            .fs-section, .fs-card, .fs-header { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <h1>Financial Statement</h1>
        <a class="pt-btn pt-btn-print" href="{{ route('reports.financial-statement.excel', array_filter(['start_date' => $report['start_date'], 'end_date' => $report['end_date'], 'cabang_id' => request('cabang_id'), 'statement_type' => $report['statement_type']], fn ($value) => $value !== null && $value !== '' && $value !== [])) }}">Download Excel</a>
        <a class="pt-btn pt-btn-print" href="{{ route('reports.financial-statement.pdf', array_filter(['start_date' => $report['start_date'], 'end_date' => $report['end_date'], 'cabang_id' => request('cabang_id'), 'statement_type' => $report['statement_type']], fn ($value) => $value !== null && $value !== '' && $value !== [])) }}">Download PDF</a>
        <button class="pt-btn pt-btn-print" onclick="window.print()">Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">Tutup</button>
    </div>

    <div class="report-wrap">
        <div class="fs-header">
            <div class="fs-company">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="fs-title">LAPORAN FINANCIAL STATEMENT</div>
            <div class="fs-period">
                Periode {{ \Carbon\Carbon::parse($report['start_date'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['end_date'])->isoFormat('D MMMM GGGG') }}
                @if(!empty($report['branch_name']))
                    &nbsp;&bull;&nbsp; Cabang {{ $report['branch_name'] }}
                @endif
            </div>
        </div>

        <div class="fs-grid">
            @if(!empty($report['pl']))
                <div class="fs-card">
                    <div class="fs-card-label">Pendapatan</div>
                    <div class="fs-card-value green">Rp {{ number_format($report['pl']['revenue'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-card">
                    <div class="fs-card-label">Laba Kotor</div>
                    <div class="fs-card-value {{ ($report['pl']['gross_profit'] ?? 0) >= 0 ? 'blue' : 'red' }}">Rp {{ number_format($report['pl']['gross_profit'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-card">
                    <div class="fs-card-label">Laba Bersih</div>
                    <div class="fs-card-value {{ ($report['pl']['net_profit'] ?? 0) >= 0 ? 'green' : 'red' }}">Rp {{ number_format($report['pl']['net_profit'] ?? 0, 0, ',', '.') }}</div>
                </div>
            @endif
            @if(!empty($report['bs']))
                <div class="fs-card">
                    <div class="fs-card-label">Total Aset</div>
                    <div class="fs-card-value slate">Rp {{ number_format($report['bs']['total_assets'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-card">
                    <div class="fs-card-label">Liabilitas + Ekuitas</div>
                    <div class="fs-card-value amber">Rp {{ number_format($report['bs']['total_liabilities_and_equity'] ?? (($report['bs']['total_liabilities'] ?? 0) + ($report['bs']['total_equity'] ?? 0)), 0, ',', '.') }}</div>
                </div>
                <div class="fs-card">
                    <div class="fs-card-label">Status Neraca</div>
                    <div class="fs-card-value {{ ($report['bs']['is_balanced'] ?? false) ? 'green' : 'red' }}">{{ ($report['bs']['is_balanced'] ?? false) ? 'Seimbang' : 'Tidak Seimbang' }}</div>
                </div>
            @endif
            @if(!empty($report['cogm']))
                <div class="fs-card">
                    <div class="fs-card-label">Harga Pokok Produksi</div>
                    <div class="fs-card-value amber">Rp {{ number_format($report['cogm']['cogm'] ?? 0, 0, ',', '.') }}</div>
                </div>
            @endif
        </div>

        @if(!empty($report['pl']))
            @php
                $pl = $report['pl'];
                $formatAmount = function ($amount, bool $negative = false) {
                    $formatted = 'Rp ' . number_format(abs((float) $amount), 0, ',', '.');

                    return $negative ? '(' . $formatted . ')' : $formatted;
                };
            @endphp
            <section class="fs-section">
                <div class="fs-section-header pl">Laporan Laba Rugi</div>

                <div class="fs-subheader">Pendapatan Usaha</div>
                @forelse($pl['sales_revenue_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount">{{ $formatAmount($account['balance']) }}</div>
                    </div>
                @empty
                    <div class="fs-empty">Tidak ada pendapatan pada periode ini.</div>
                @endforelse
                <div class="fs-total soft">
                    <span>Total Pendapatan</span>
                    <span>{{ $formatAmount($pl['revenue']) }}</span>
                </div>

                <div class="fs-subheader">Harga Pokok Penjualan</div>
                @forelse($pl['cogs_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount negative">{{ $formatAmount($account['balance'], true) }}</div>
                    </div>
                @empty
                    <div class="fs-empty">Tidak ada akun HPP pada periode ini.</div>
                @endforelse
                <div class="fs-total soft">
                    <span>Total HPP</span>
                    <span>{{ $formatAmount($pl['cogs'], true) }}</span>
                </div>

                <div class="fs-total success">
                    <span>Laba Kotor</span>
                    <span>{{ $formatAmount($pl['gross_profit'], $pl['gross_profit'] < 0) }}</span>
                </div>

                <div class="fs-subheader">Beban Operasional</div>
                @forelse($pl['operating_expense_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount negative">{{ $formatAmount($account['balance'], true) }}</div>
                    </div>
                @empty
                    <div class="fs-empty">Tidak ada beban operasional pada periode ini.</div>
                @endforelse
                <div class="fs-total soft">
                    <span>Laba Usaha</span>
                    <span>{{ $formatAmount($pl['operating_profit'], $pl['operating_profit'] < 0) }}</span>
                </div>

                <div class="fs-subheader">Pendapatan / Beban Lain-lain</div>
                @foreach($pl['other_income_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount">{{ $formatAmount($account['balance']) }}</div>
                    </div>
                @endforeach
                @foreach($pl['other_expense_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount negative">{{ $formatAmount($account['balance'], true) }}</div>
                    </div>
                @endforeach
                @if(empty($pl['other_income_accounts']) && empty($pl['other_expense_accounts']))
                    <div class="fs-empty">Tidak ada pendapatan atau beban lain-lain pada periode ini.</div>
                @endif
                <div class="fs-total soft">
                    <span>Laba Sebelum Pajak</span>
                    <span>{{ $formatAmount($pl['profit_before_tax'], $pl['profit_before_tax'] < 0) }}</span>
                </div>

                <div class="fs-subheader">Pajak</div>
                @forelse($pl['tax_accounts'] as $account)
                    <div class="fs-row">
                        <div class="fs-row-label"><span class="fs-row-code">{{ $account['code'] }}</span>{{ $account['name'] }}</div>
                        <div class="fs-row-amount negative">{{ $formatAmount($account['balance'], true) }}</div>
                    </div>
                @empty
                    <div class="fs-empty">Tidak ada beban pajak pada periode ini.</div>
                @endforelse

                <div class="fs-total strong">
                    <span>{{ $pl['net_profit'] >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span>
                    <span>{{ $formatAmount($pl['net_profit'], $pl['net_profit'] < 0) }}</span>
                </div>
            </section>
        @endif

        @if(!empty($report['bs']))
            @php
                $bs = $report['bs'];
                $formatBalance = function ($amount, bool $negative = false) {
                    $formatted = 'Rp ' . number_format(abs((float) $amount), 0, ',', '.');

                    return $negative ? '(' . $formatted . ')' : $formatted;
                };
                $leftSections = [
                    ['title' => 'Aset Lancar', 'key' => 'current_assets', 'negative' => false],
                    ['title' => 'Aset Tetap', 'key' => 'fixed_assets', 'negative' => false],
                    ['title' => 'Contra Asset', 'key' => 'contra_assets', 'negative' => true],
                ];
                $rightSections = [
                    ['title' => 'Liabilitas Jangka Pendek', 'key' => 'current_liabilities', 'negative' => false],
                    ['title' => 'Liabilitas Jangka Panjang', 'key' => 'long_term_liabilities', 'negative' => false],
                    ['title' => 'Ekuitas', 'key' => 'equity', 'negative' => false],
                ];
            @endphp
            <section class="fs-section">
                <div class="fs-section-header bs">Neraca / Balance Sheet</div>
                <div class="fs-two-col">
                    <div class="fs-panel">
                        <div class="fs-panel-title">Aset</div>
                        @foreach($leftSections as $section)
                            <div class="fs-subheader">{{ $section['title'] }}</div>
                            @forelse(($bs[$section['key']]['accounts'] ?? []) as $account)
                                <div class="fs-row">
                                    <div class="fs-row-label"><span class="fs-row-code">{{ $account->code }}</span>{{ $account->name }}</div>
                                    <div class="fs-row-amount {{ $section['negative'] ? 'negative' : '' }}">{{ $formatBalance($account->balance ?? 0, $section['negative']) }}</div>
                                </div>
                            @empty
                                <div class="fs-empty">Tidak ada akun pada section ini.</div>
                            @endforelse
                            <div class="fs-total soft">
                                <span>Total {{ $section['title'] }}</span>
                                <span>{{ $formatBalance($bs[$section['key']]['total'] ?? 0, $section['negative']) }}</span>
                            </div>
                        @endforeach
                        <div class="fs-total strong">
                            <span>Total Aset</span>
                            <span>{{ $formatBalance($bs['total_assets'] ?? 0) }}</span>
                        </div>
                    </div>

                    <div class="fs-panel">
                        <div class="fs-panel-title">Liabilitas dan Ekuitas</div>
                        @foreach($rightSections as $section)
                            <div class="fs-subheader">{{ $section['title'] }}</div>
                            @forelse(($bs[$section['key']]['accounts'] ?? []) as $account)
                                <div class="fs-row">
                                    <div class="fs-row-label"><span class="fs-row-code">{{ $account->code }}</span>{{ $account->name }}</div>
                                    <div class="fs-row-amount">{{ $formatBalance($account->balance ?? 0) }}</div>
                                </div>
                            @empty
                                <div class="fs-empty">Tidak ada akun pada section ini.</div>
                            @endforelse
                            <div class="fs-total soft">
                                <span>Total {{ $section['title'] }}</span>
                                <span>{{ $formatBalance($bs[$section['key']]['total'] ?? 0) }}</span>
                            </div>
                        @endforeach

                        @if(($bs['retained_earnings'] ?? 0) != 0)
                            <div class="fs-row">
                                <div class="fs-row-label">Laba Ditahan</div>
                                <div class="fs-row-amount">{{ $formatBalance($bs['retained_earnings']) }}</div>
                            </div>
                        @endif

                        <div class="fs-total strong">
                            <span>Total Liabilitas + Ekuitas</span>
                            <span>{{ $formatBalance($bs['total_liabilities_and_equity'] ?? (($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0))) }}</span>
                        </div>
                    </div>
                </div>

                <div class="fs-total {{ ($bs['is_balanced'] ?? false) ? 'success' : 'warn' }}">
                    <span>{{ ($bs['is_balanced'] ?? false) ? 'Neraca Seimbang' : 'Neraca Tidak Seimbang' }}</span>
                    <span>
                        @if($bs['is_balanced'] ?? false)
                            Selisih Rp 0
                        @else
                            Selisih {{ $formatBalance($bs['difference'] ?? 0, ($bs['difference'] ?? 0) < 0) }}
                        @endif
                    </span>
                </div>
            </section>
        @endif

        @if(!empty($report['cogm']))
            @php $cogm = $report['cogm']; @endphp
            <section class="fs-section">
                <div class="fs-section-header pl" style="background: linear-gradient(135deg,#92400e,#d97706);">Cost of Goods Manufactured</div>

                <div class="fs-row">
                    <div class="fs-row-label">Persediaan Bahan Baku Awal</div>
                    <div class="fs-row-amount">Rp {{ number_format($cogm['raw_materials']['opening'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-row">
                    <div class="fs-row-label">Pembelian Bahan Baku</div>
                    <div class="fs-row-amount">Rp {{ number_format($cogm['raw_materials']['purchases'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-row">
                    <div class="fs-row-label">Bahan Baku Tersedia</div>
                    <div class="fs-row-amount">Rp {{ number_format($cogm['raw_materials']['available'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-row">
                    <div class="fs-row-label">Persediaan Bahan Baku Akhir</div>
                    <div class="fs-row-amount negative">(Rp {{ number_format($cogm['raw_materials']['closing'] ?? 0, 0, ',', '.') }})</div>
                </div>
                <div class="fs-total soft">
                    <span>Bahan Baku Digunakan</span>
                    <span>Rp {{ number_format($cogm['raw_materials']['used'] ?? 0, 0, ',', '.') }}</span>
                </div>

                <div class="fs-row">
                    <div class="fs-row-label">Tenaga Kerja Langsung</div>
                    <div class="fs-row-amount">Rp {{ number_format($cogm['direct_labor'] ?? 0, 0, ',', '.') }}</div>
                </div>

                <div class="fs-subheader">Biaya Overhead Pabrik</div>
                @forelse($cogm['overhead']['items'] ?? [] as $item)
                    <div class="fs-row">
                        <div class="fs-row-label">{{ $item['label'] }}</div>
                        <div class="fs-row-amount">Rp {{ number_format($item['amount'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="fs-empty">Tidak ada komponen overhead pada periode ini.</div>
                @endforelse
                <div class="fs-total soft">
                    <span>Total Overhead</span>
                    <span>Rp {{ number_format($cogm['overhead']['total'] ?? 0, 0, ',', '.') }}</span>
                </div>

                <div class="fs-total success">
                    <span>Total Biaya Produksi</span>
                    <span>Rp {{ number_format($cogm['production_cost'] ?? 0, 0, ',', '.') }}</span>
                </div>

                <div class="fs-row">
                    <div class="fs-row-label">Persediaan Barang Dalam Proses Awal</div>
                    <div class="fs-row-amount">Rp {{ number_format($cogm['wip']['opening'] ?? 0, 0, ',', '.') }}</div>
                </div>
                <div class="fs-row">
                    <div class="fs-row-label">Persediaan Barang Dalam Proses Akhir</div>
                    <div class="fs-row-amount negative">(Rp {{ number_format($cogm['wip']['closing'] ?? 0, 0, ',', '.') }})</div>
                </div>

                <div class="fs-total strong">
                    <span>Harga Pokok Produksi</span>
                    <span>Rp {{ number_format($cogm['cogm'] ?? 0, 0, ',', '.') }}</span>
                </div>
            </section>
        @endif

        <div class="fs-note">
            Financial Statement ini mengambil data dari service laporan utama, sehingga angka preview mengikuti perhitungan yang sama dengan Laba Rugi dan Neraca di modul finance.
        </div>
    </div>
</body>
</html>