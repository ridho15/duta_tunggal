<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Financial Statement Export</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3, p { margin: 0; }
        .header { margin-bottom: 18px; }
        .title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .subtitle { font-size: 12px; color: #374151; margin-bottom: 2px; }
        .summary { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
        .summary td { border: 1px solid #d1d5db; padding: 8px; }
        .summary-label { background: #f3f4f6; font-weight: 700; width: 22%; }
        .section { margin-top: 18px; }
        .section-title { font-size: 15px; font-weight: 700; padding: 8px 10px; color: #fff; background: #0f766e; }
        .sub-title { font-size: 12px; font-weight: 700; padding: 6px 10px; background: #ecfeff; border: 1px solid #bae6fd; border-top: 0; }
        table.report { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.report td, table.report th { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        table.report th { background: #f9fafb; text-align: left; }
        .amount { text-align: right; white-space: nowrap; }
        .negative { color: #b91c1c; }
        .total-row td { background: #eff6ff; font-weight: 700; }
        .grand-row td { background: #e0f2fe; font-weight: 800; }
        .two-col { width: 100%; border-collapse: collapse; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding-right: 10px; }
        .two-col > tbody > tr > td:last-child { padding-right: 0; padding-left: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ config('app.name', 'PT Duta Tunggal') }}</div>
        <div class="subtitle">Financial Statement</div>
        <div class="subtitle">Periode {{ \Carbon\Carbon::parse($report['start_date'])->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($report['end_date'])->format('d/m/Y') }}</div>
        @if(!empty($report['branch_name']))
            <div class="subtitle">Cabang: {{ $report['branch_name'] }}</div>
        @endif
    </div>

    <table class="summary">
        <tr>
            <td class="summary-label">Statement Type</td>
            <td>{{ strtoupper($report['statement_type']) }}</td>
            <td class="summary-label">Periode</td>
            <td>{{ $report['period_label'] }}</td>
        </tr>
        <tr>
            <td class="summary-label">Pendapatan</td>
            <td>Rp {{ number_format($report['pl']['revenue'] ?? 0, 0, ',', '.') }}</td>
            <td class="summary-label">Total Aset</td>
            <td>Rp {{ number_format($report['bs']['total_assets'] ?? 0, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="summary-label">Laba Bersih</td>
            <td>Rp {{ number_format($report['pl']['net_profit'] ?? 0, 0, ',', '.') }}</td>
            <td class="summary-label">Status Neraca</td>
            <td>{{ ($report['bs']['is_balanced'] ?? false) ? 'Seimbang' : 'Tidak Seimbang' }}</td>
        </tr>
    </table>

    @if(!empty($report['pl']))
        @php $pl = $report['pl']; @endphp
        <div class="section">
            <div class="section-title">Laporan Laba Rugi</div>

            <div class="sub-title">Pendapatan Usaha</div>
            <table class="report">
                <thead>
                    <tr><th>Kode</th><th>Akun</th><th class="amount">Saldo</th></tr>
                </thead>
                <tbody>
                    @forelse($pl['sales_revenue_accounts'] as $account)
                        <tr>
                            <td>{{ $account['code'] }}</td>
                            <td>{{ $account['name'] }}</td>
                            <td class="amount">Rp {{ number_format($account['balance'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Tidak ada pendapatan pada periode ini.</td></tr>
                    @endforelse
                    <tr class="total-row"><td colspan="2">Total Pendapatan</td><td class="amount">Rp {{ number_format($pl['revenue'], 0, ',', '.') }}</td></tr>
                </tbody>
            </table>

            <div class="sub-title">Harga Pokok Penjualan dan Beban</div>
            <table class="report">
                <thead>
                    <tr><th>Kode</th><th>Akun</th><th class="amount">Saldo</th></tr>
                </thead>
                <tbody>
                    @foreach($pl['cogs_accounts'] as $account)
                        <tr>
                            <td>{{ $account['code'] }}</td>
                            <td>{{ $account['name'] }}</td>
                            <td class="amount negative">(Rp {{ number_format($account['balance'], 0, ',', '.') }})</td>
                        </tr>
                    @endforeach
                    @foreach($pl['operating_expense_accounts'] as $account)
                        <tr>
                            <td>{{ $account['code'] }}</td>
                            <td>{{ $account['name'] }}</td>
                            <td class="amount negative">(Rp {{ number_format($account['balance'], 0, ',', '.') }})</td>
                        </tr>
                    @endforeach
                    @if(empty($pl['cogs_accounts']) && empty($pl['operating_expense_accounts']))
                        <tr><td colspan="3">Tidak ada HPP atau beban operasional pada periode ini.</td></tr>
                    @endif
                    <tr class="total-row"><td colspan="2">Total HPP</td><td class="amount negative">(Rp {{ number_format($pl['cogs'], 0, ',', '.') }})</td></tr>
                    <tr class="total-row"><td colspan="2">Total OPEX</td><td class="amount negative">(Rp {{ number_format($pl['opex'], 0, ',', '.') }})</td></tr>
                    <tr class="grand-row"><td colspan="2">Laba Bersih</td><td class="amount">Rp {{ number_format($pl['net_profit'], 0, ',', '.') }}</td></tr>
                </tbody>
            </table>
        </div>
    @endif

    @if(!empty($report['bs']))
        @php
            $bs = $report['bs'];
            $assetSections = [
                ['title' => 'Aset Lancar', 'key' => 'current_assets', 'negative' => false],
                ['title' => 'Aset Tetap', 'key' => 'fixed_assets', 'negative' => false],
                ['title' => 'Contra Asset', 'key' => 'contra_assets', 'negative' => true],
            ];
            $liabilitySections = [
                ['title' => 'Liabilitas Jangka Pendek', 'key' => 'current_liabilities'],
                ['title' => 'Liabilitas Jangka Panjang', 'key' => 'long_term_liabilities'],
                ['title' => 'Ekuitas', 'key' => 'equity'],
            ];
        @endphp
        <div class="section">
            <div class="section-title">Neraca / Balance Sheet</div>
            <table class="two-col">
                <tbody>
                    <tr>
                        <td>
                            @foreach($assetSections as $section)
                                <div class="sub-title">{{ $section['title'] }}</div>
                                <table class="report">
                                    <thead>
                                        <tr><th>Kode</th><th>Akun</th><th class="amount">Saldo</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($bs[$section['key']]['accounts'] ?? []) as $account)
                                            <tr>
                                                <td>{{ $account->code }}</td>
                                                <td>{{ $account->name }}</td>
                                                <td class="amount {{ $section['negative'] ? 'negative' : '' }}">
                                                    @if($section['negative'])
                                                        (Rp {{ number_format($account->balance ?? 0, 0, ',', '.') }})
                                                    @else
                                                        Rp {{ number_format($account->balance ?? 0, 0, ',', '.') }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3">Tidak ada akun pada section ini.</td></tr>
                                        @endforelse
                                        <tr class="total-row"><td colspan="2">Total {{ $section['title'] }}</td><td class="amount">Rp {{ number_format($bs[$section['key']]['total'] ?? 0, 0, ',', '.') }}</td></tr>
                                    </tbody>
                                </table>
                            @endforeach
                            <table class="report"><tbody><tr class="grand-row"><td colspan="2">Total Aset</td><td class="amount">Rp {{ number_format($bs['total_assets'] ?? 0, 0, ',', '.') }}</td></tr></tbody></table>
                        </td>
                        <td>
                            @foreach($liabilitySections as $section)
                                <div class="sub-title">{{ $section['title'] }}</div>
                                <table class="report">
                                    <thead>
                                        <tr><th>Kode</th><th>Akun</th><th class="amount">Saldo</th></tr>
                                    </thead>
                                    <tbody>
                                        @forelse(($bs[$section['key']]['accounts'] ?? []) as $account)
                                            <tr>
                                                <td>{{ $account->code }}</td>
                                                <td>{{ $account->name }}</td>
                                                <td class="amount">Rp {{ number_format($account->balance ?? 0, 0, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="3">Tidak ada akun pada section ini.</td></tr>
                                        @endforelse
                                        <tr class="total-row"><td colspan="2">Total {{ $section['title'] }}</td><td class="amount">Rp {{ number_format($bs[$section['key']]['total'] ?? 0, 0, ',', '.') }}</td></tr>
                                    </tbody>
                                </table>
                            @endforeach
                            @if(($bs['retained_earnings'] ?? 0) != 0)
                                <table class="report"><tbody><tr><td colspan="2">Laba Ditahan</td><td class="amount">Rp {{ number_format($bs['retained_earnings'], 0, ',', '.') }}</td></tr></tbody></table>
                            @endif
                            <table class="report"><tbody><tr class="grand-row"><td colspan="2">Total Liabilitas + Ekuitas</td><td class="amount">Rp {{ number_format($bs['total_liabilities_and_equity'] ?? (($bs['total_liabilities'] ?? 0) + ($bs['total_equity'] ?? 0)), 0, ',', '.') }}</td></tr></tbody></table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>