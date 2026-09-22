<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALK Grafik - {{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @php
        $isEmbedded = request()->boolean('embedded');
        $exportParams = array_filter([
            'start_date' => $report['start_date'],
            'end_date' => $report['end_date'],
            'cabang_id' => request('cabang_id'),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
        $summaryCards = [
            ['label' => 'Pendapatan', 'value' => data_get($report, 'summary.revenue', 0), 'hint' => 'Pendapatan periode berjalan.', 'accent' => 'green'],
            ['label' => 'Beban', 'value' => data_get($report, 'summary.expense', 0), 'hint' => 'Beban pada periode analisis.', 'accent' => 'red'],
            ['label' => 'Laba Bersih', 'value' => data_get($report, 'summary.net_profit', 0), 'hint' => 'Kinerja laba bersih akhir periode.', 'accent' => data_get($report, 'summary.net_profit', 0) >= 0 ? 'blue' : 'red'],
            ['label' => 'Total Aset', 'value' => data_get($report, 'summary.total_assets', 0), 'hint' => 'Posisi aset pada akhir periode.', 'accent' => 'slate'],
            ['label' => 'Total Liabilitas', 'value' => data_get($report, 'summary.total_liabilities', 0), 'hint' => 'Kewajiban tercatat di neraca.', 'accent' => 'amber'],
            ['label' => 'Total Ekuitas', 'value' => data_get($report, 'summary.total_equity', 0), 'hint' => 'Ekuitas setelah akumulasi laba.', 'accent' => 'teal'],
        ];
        $ratioCards = [
            ['label' => 'Current Ratio', 'value' => data_get($report, 'ratios.current_ratio'), 'unit' => 'x', 'hint' => 'Likuiditas jangka pendek.', 'ok' => fn ($value) => $value >= 1.5],
            ['label' => 'Debt to Equity', 'value' => data_get($report, 'ratios.debt_to_equity'), 'unit' => 'x', 'hint' => 'Keseimbangan struktur modal.', 'ok' => fn ($value) => $value <= 1],
            ['label' => 'ROA', 'value' => data_get($report, 'ratios.roa'), 'unit' => '%', 'hint' => 'Efisiensi aset menghasilkan laba.', 'ok' => fn ($value) => $value > 0],
            ['label' => 'ROE', 'value' => data_get($report, 'ratios.roe'), 'unit' => '%', 'hint' => 'Pengembalian ke pemilik modal.', 'ok' => fn ($value) => $value > 0],
            ['label' => 'Profit Margin', 'value' => data_get($report, 'ratios.profit_margin'), 'unit' => '%', 'hint' => 'Margin laba bersih terhadap penjualan.', 'ok' => fn ($value) => $value > 0],
        ];
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        body.embedded { background: transparent; }
        .toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #0f172a; color: #fff; position: sticky; top: 0; z-index: 100; }
        .toolbar h1 { flex: 1; margin: 0; font-size: 1rem; font-weight: 700; }
        .btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; text-decoration: none; font-size: .85rem; font-weight: 600; cursor: pointer; }
        .btn-primary { background: #0f766e; color: #fff; }
        .btn-muted { background: #475569; color: #fff; }
        .report-wrap { max-width: 1180px; margin: 0 auto; padding: 1.5rem; }
        .report-wrap.embedded { padding: 1rem; }
        .hero { background: linear-gradient(135deg,#0f172a,#0f766e); color: #fff; border-radius: 18px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 16px 36px rgba(15, 118, 110, .2); }
        .hero-company { font-size: 1.55rem; font-weight: 800; letter-spacing: .02em; }
        .hero-title { margin-top: .25rem; font-size: 1.2rem; font-weight: 700; opacity: .95; }
        .hero-period { margin-top: .35rem; font-size: .92rem; color: rgba(255, 255, 255, .82); }
        .eyebrow { display: inline-flex; align-items: center; border-radius: 999px; background: rgba(255, 255, 255, .15); padding: .3rem .75rem; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .9rem; }
        .hero-meta { margin-top: 1rem; display: flex; flex-wrap: wrap; gap: .65rem; }
        .hero-chip { display: inline-flex; align-items: center; border-radius: 999px; background: rgba(255, 255, 255, .12); padding: .35rem .75rem; font-size: .8rem; color: rgba(255, 255, 255, .88); }
        .grid { display: grid; gap: 1rem; }
        .summary-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 1.5rem; }
        .ratio-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.1rem 1.25rem; box-shadow: 0 6px 20px rgba(15, 23, 42, .05); }
        .summary-card { position: relative; overflow: hidden; }
        .summary-card::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 6px; border-radius: 16px 0 0 16px; background: #0f766e; }
        .summary-card.green::before { background: #059669; }
        .summary-card.red::before { background: #dc2626; }
        .summary-card.blue::before { background: #2563eb; }
        .summary-card.slate::before { background: #334155; }
        .summary-card.amber::before { background: #d97706; }
        .summary-card.teal::before { background: #0f766e; }
        .card-label { font-size: .76rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
        .card-value { margin-top: .35rem; font-size: 1.45rem; font-weight: 800; }
        .card-subtext { margin-top: .35rem; font-size: .82rem; color: #64748b; }
        .section { background: #fff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; box-shadow: 0 8px 24px rgba(15, 23, 42, .06); margin-bottom: 1.5rem; }
        .section-header { padding: 1rem 1.5rem; background: #0f172a; color: #fff; }
        .section-header h3 { margin: 0; font-size: 1rem; }
        .section-header p { margin: .25rem 0 0; font-size: .85rem; color: #cbd5e1; }
        .section-body { padding: 1.25rem 1.5rem; }
        .chart-grid { display: grid; gap: 1rem; grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr); }
        .chart-panel { border: 1px solid #e2e8f0; border-radius: 16px; padding: 1rem; background: #fff; }
        .chart-panel h4 { margin: 0 0 .75rem; font-size: .95rem; }
        .chart-stage { position: relative; height: 360px; }
        .chart-stage.compact { height: 320px; }
        .trend-table { width: 100%; border-collapse: collapse; }
        .trend-table th, .trend-table td { padding: .85rem 1rem; border-bottom: 1px solid #e2e8f0; font-size: .9rem; }
        .trend-table th { background: #f8fafc; text-align: left; color: #475569; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
        .trend-table td.num, .trend-table th.num { text-align: right; }
        .balance-note { margin-top: 1rem; border-radius: 12px; padding: .85rem 1rem; font-size: .85rem; }
        .balance-note.ok { background: #dcfce7; color: #166534; }
        .balance-note.warn { background: #fee2e2; color: #991b1b; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: .25rem .7rem; font-size: .75rem; font-weight: 700; }
        .badge.ok { background: #dcfce7; color: #166534; }
        .badge.warn { background: #fee2e2; color: #991b1b; }
        @media (max-width: 900px) {
            .toolbar { flex-wrap: wrap; }
            .chart-grid { grid-template-columns: 1fr; }
            .chart-stage, .chart-stage.compact { height: 280px; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .card, .section, .hero { box-shadow: none; }
        }
    </style>
</head>
<body class="{{ $isEmbedded ? 'embedded' : '' }}">
    @unless($isEmbedded)
        <div class="toolbar">
            <h1>ALK Grafik</h1>
            <a class="btn btn-primary" href="{{ route('reports.alk-grafik.excel', $exportParams) }}">Download Excel</a>
            <a class="btn btn-primary" href="{{ route('reports.alk-grafik.pdf', $exportParams) }}">Download PDF</a>
            <button class="btn btn-primary" onclick="window.print()">Cetak / PDF</button>
            <button class="btn btn-muted" onclick="window.close()">Tutup</button>
        </div>
    @endunless

    <div class="report-wrap {{ $isEmbedded ? 'embedded' : '' }}">
        <div class="hero">
            <span class="eyebrow">Analisis Laporan Keuangan</span>
            <div class="hero-company">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <div class="hero-title">Laporan ALK Grafik</div>
            <div class="hero-period">
                Periode {{ \Carbon\Carbon::parse($report['start_date'])->isoFormat('D MMMM GGGG') }}
                s/d {{ \Carbon\Carbon::parse($report['end_date'])->isoFormat('D MMMM GGGG') }}
            </div>
            <div class="hero-meta">
                <span class="hero-chip">Cabang {{ $report['branch_name'] ?? 'Semua Cabang' }}</span>
                <span class="hero-chip">{{ data_get($report, 'summary.is_balanced') ? 'Neraca seimbang' : 'Neraca belum seimbang' }}</span>
                <span class="hero-chip">Workbook Excel berisi summary, rasio, komposisi, dan tren</span>
            </div>
        </div>

        <div class="grid summary-grid">
            @foreach($summaryCards as $card)
                <div class="card summary-card {{ $card['accent'] }}">
                    <div class="card-label">{{ $card['label'] }}</div>
                    <div class="card-value">Rp {{ number_format($card['value'], 0, ',', '.') }}</div>
                    <div class="card-subtext">{{ $card['hint'] }}</div>
                </div>
            @endforeach
        </div>

        <section class="section">
            <div class="section-header">
                <h3>Rasio Keuangan Utama</h3>
                <p>Likuiditas, solvabilitas, dan profitabilitas memakai payload yang sama dengan admin, PDF, dan workbook Excel.</p>
            </div>
            <div class="section-body">
                <div class="grid ratio-grid">
                    @foreach($ratioCards as $ratio)
                        @php
                            $value = $ratio['value'];
                            $hasValue = $value !== null;
                            $statusOk = $hasValue && $ratio['ok']($value);
                        @endphp
                        <div class="card">
                            <div class="card-label">{{ $ratio['label'] }}</div>
                            <div class="card-value">{{ $hasValue ? number_format($value, 2) . $ratio['unit'] : 'N/A' }}</div>
                            <div class="card-subtext">{{ $ratio['hint'] }}</div>
                            @if($hasValue)
                                <div style="margin-top:.75rem;">
                                    <span class="badge {{ $statusOk ? 'ok' : 'warn' }}">{{ $statusOk ? 'Baik' : 'Perlu perhatian' }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="balance-note {{ data_get($report, 'summary.is_balanced') ? 'ok' : 'warn' }}">
                    @if(data_get($report, 'summary.is_balanced'))
                        Neraca dalam kondisi seimbang. Struktur aset, liabilitas, dan ekuitas dapat dipakai sebagai basis analisis lanjutan.
                    @else
                        Neraca belum seimbang dengan selisih Rp {{ number_format(abs((float) data_get($report, 'summary.difference', 0)), 0, ',', '.') }}.
                    @endif
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h3>Visualisasi Tren dan Komposisi</h3>
                <p>Chart difokuskan untuk preview interaktif, sementara PDF dan Excel membawa ringkasan numerik yang sama.</p>
            </div>
            <div class="section-body">
                <div class="chart-grid">
                    <div class="chart-panel">
                        <h4>Tren Pendapatan, Beban, dan Laba</h4>
                        <div class="chart-stage">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-panel">
                        <h4>Komposisi Neraca</h4>
                        <div class="chart-stage compact">
                            <canvas id="compositionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-header">
                <h3>Detail Tren Bulanan</h3>
                <p>Rincian bulanan untuk verifikasi angka laporan terhadap periode yang dipilih.</p>
            </div>
            <div class="section-body" style="padding:0;">
                <table class="trend-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th class="num">Pendapatan</th>
                            <th class="num">Beban</th>
                            <th class="num">Laba Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['trend'] as $row)
                            <tr>
                                <td>{{ $row['month'] }}</td>
                                <td class="num">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($row['expense'], 0, ',', '.') }}</td>
                                <td class="num">Rp {{ number_format($row['profit'], 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Tidak ada data tren pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        const trend = @json($report['trend']);

        const trendChart = document.getElementById('trendChart');
        if (trendChart && Array.isArray(trend)) {
            new Chart(trendChart.getContext('2d'), {
                data: {
                    labels: trend.map(item => item.month),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Pendapatan',
                            data: trend.map(item => item.revenue),
                            backgroundColor: 'rgba(16, 185, 129, 0.75)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            type: 'bar',
                            label: 'Beban',
                            data: trend.map(item => item.expense),
                            backgroundColor: 'rgba(239, 68, 68, 0.75)',
                            borderColor: 'rgba(239, 68, 68, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            type: 'line',
                            label: 'Laba Bersih',
                            data: trend.map(item => item.profit),
                            borderColor: 'rgba(59, 130, 246, 1)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            borderWidth: 2.5,
                            pointRadius: 4,
                            tension: 0.35,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: value => 'Rp ' + Number(value).toLocaleString('id-ID'),
                            },
                        },
                    },
                },
            });
        }

        const compositionChart = document.getElementById('compositionChart');
        if (compositionChart) {
            new Chart(compositionChart.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Liabilitas', 'Ekuitas'],
                    datasets: [{
                        data: [@json(data_get($report, 'summary.total_liabilities', 0)), @json(data_get($report, 'summary.total_equity', 0))],
                        backgroundColor: ['rgba(239, 68, 68, 0.8)', 'rgba(16, 185, 129, 0.8)'],
                        borderColor: ['rgba(239, 68, 68, 1)', 'rgba(16, 185, 129, 1)'],
                        borderWidth: 2,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { position: 'bottom' },
                    },
                },
            });
        }
    </script>
</body>
</html>