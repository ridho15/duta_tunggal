<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal Consolidation - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f7fb;
            color: #0f172a;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            @page { size: A4 landscape; margin: 10mm; }
            .section, .detail-group { box-shadow: none; }
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #dbe4f0;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .toolbar-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }
        .toolbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            border: 0;
            border-radius: 10px;
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
        }
        .btn-print { background: linear-gradient(135deg, #0f766e, #14b8a6); }
        .btn-close { background: linear-gradient(135deg, #475569, #334155); }
        .page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .hero {
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            color: #fff;
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 16px 36px rgba(29, 78, 216, 0.22);
            margin-bottom: 18px;
        }
        .hero-kicker {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            opacity: 0.8;
            margin-bottom: 6px;
        }
        .hero-title {
            margin: 0;
            font-size: 30px;
            font-weight: 900;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .hero-subtitle {
            margin-top: 8px;
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.5;
        }
        .meta-grid,
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .meta-card,
        .summary-card {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 16px;
            padding: 16px 18px;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
        }
        .meta-label,
        .summary-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
            margin-bottom: 6px;
        }
        .meta-value,
        .summary-value {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
        }
        .summary-card.teal { border-left: 4px solid #14b8a6; }
        .summary-card.green { border-left: 4px solid #16a34a; }
        .summary-card.red { border-left: 4px solid #dc2626; }
        .summary-card.blue { border-left: 4px solid #2563eb; }
        .summary-value.green { color: #15803d; }
        .summary-value.red { color: #b91c1c; }
        .summary-value.blue { color: #1d4ed8; }
        .summary-value.teal { color: #0f766e; }
        .summary-status.ok { color: #15803d; }
        .summary-status.fail { color: #b91c1c; }
        .section,
        .detail-group {
            background: #fff;
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            margin-bottom: 18px;
        }
        .section-header,
        .detail-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            background: linear-gradient(135deg, #e0f2fe, #f8fafc);
            border-bottom: 1px solid #dbe4f0;
        }
        .section-title,
        .detail-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .section-subtitle,
        .detail-meta {
            font-size: 12px;
            color: #475569;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead tr {
            background: #0f172a;
            color: #fff;
        }
        th,
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        th {
            text-align: left;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        td.text-right,
        th.text-right {
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            white-space: nowrap;
        }
        tbody tr:hover td {
            background: #f8fafc;
        }
        .total-row td {
            background: #eff6ff;
            font-weight: 800;
            color: #1e3a8a;
            border-top: 2px solid #93c5fd;
        }
        .detail-group[open] .detail-summary {
            background: linear-gradient(135deg, #dbeafe, #eff6ff);
        }
        .entry-reference {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
        }
        .entry-description {
            color: #475569;
            max-width: 380px;
        }
        .entry-reversal {
            background: #fff7ed;
        }
        .note-box {
            background: #fefce8;
            border: 1px solid #fde68a;
            color: #854d0e;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-size: 13px;
            line-height: 1.5;
        }
        .note-box strong {
            display: block;
            margin-bottom: 4px;
        }
        .footer {
            padding: 8px 2px 20px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <div class="toolbar-title">Journal Consolidation</div>
        <div class="toolbar-actions">
            <button class="btn btn-print" onclick="window.print()">Cetak / PDF</button>
            <button class="btn btn-close" onclick="window.close()">Tutup</button>
        </div>
    </div>

    <div class="page">
        <div class="hero">
            <div class="hero-kicker">{{ config('app.name', 'PT Duta Tunggal') }}</div>
            <h1 class="hero-title">Journal Consolidation</h1>
            <div class="hero-subtitle">
                Periode {{ $report['period'] }}
                @if($selectedBranches->isNotEmpty())
                    <br>Cabang: {{ $selectedBranches->pluck('nama')->join(', ') }}
                @else
                    <br>Cabang: Semua cabang dalam scope akses
                @endif
                <br>Tampilan: {{ $report['filters']['group_by_branch'] ? 'Dikelompokkan per cabang' : 'Konsolidasi seluruh cabang' }}
                @if(!empty($report['filters']['journal_type']))
                    <br>Jenis jurnal: {{ $report['filters']['journal_type'] }}
                @endif
            </div>
        </div>

        <div class="meta-grid no-print">
            <div class="meta-card">
                <div class="meta-label">Tanggal Mulai</div>
                <div class="meta-value">{{ $report['filters']['start_date'] }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Tanggal Akhir</div>
                <div class="meta-value">{{ $report['filters']['end_date'] }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Filter Cabang</div>
                <div class="meta-value">{{ $selectedBranches->isNotEmpty() ? $selectedBranches->count() . ' cabang' : 'Semua cabang' }}</div>
            </div>
            <div class="meta-card">
                <div class="meta-label">Mode</div>
                <div class="meta-value">{{ $report['filters']['group_by_branch'] ? 'Per Cabang' : 'Konsolidasi' }}</div>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card teal">
                <div class="summary-label">Total Entri</div>
                <div class="summary-value teal">{{ number_format($report['count']) }}</div>
            </div>
            <div class="summary-card green">
                <div class="summary-label">Total Debit</div>
                <div class="summary-value green">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card red">
                <div class="summary-label">Total Kredit</div>
                <div class="summary-value red">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</div>
            </div>
            <div class="summary-card blue">
                <div class="summary-label">Status</div>
                <div class="summary-value blue summary-status {{ $report['balanced'] ? 'ok' : 'fail' }}">
                    {{ $report['balanced'] ? 'Seimbang' : 'Tidak Seimbang' }}
                </div>
            </div>
        </div>

        @if(! $report['balanced'])
            <div class="note-box">
                <strong>Perhatian</strong>
                Total debit dan kredit tidak seimbang. Selisih saat ini adalah Rp {{ number_format(abs($report['difference']), 0, ',', '.') }}.
            </div>
        @endif

        <section class="section">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Ringkasan per Akun</h2>
                    <div class="section-subtitle">Agregasi saldo debit, kredit, dan selisih untuk seluruh akun dalam periode laporan.</div>
                </div>
                <div class="section-subtitle">{{ count($report['coa_summary']) }} akun</div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th>Tipe</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Kredit</th>
                            <th class="text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['coa_summary'] as $row)
                            <tr>
                                <td>{{ $row['coa']?->code ?? '-' }}</td>
                                <td>{{ $row['coa']?->name ?? '-' }}</td>
                                <td>{{ $row['coa']?->type ?? '-' }}</td>
                                <td class="text-right">Rp {{ number_format($row['total_debit'], 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format($row['total_credit'], 0, ',', '.') }}</td>
                                <td class="text-right">Rp {{ number_format(abs($row['balance']), 0, ',', '.') }}{{ $row['balance'] < 0 ? ' (K)' : '' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">Tidak ada jurnal untuk filter yang dipilih.</td>
                            </tr>
                        @endforelse
                        <tr class="total-row">
                            <td colspan="3">Total Konsolidasi</td>
                            <td class="text-right">Rp {{ number_format($report['total_debit'], 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($report['total_credit'], 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format(abs($report['difference']), 0, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @foreach($report['grouped'] as $group)
            <details class="detail-group" open>
                <summary class="detail-summary">
                    <div>
                        <h3 class="detail-title">{{ $group['cabang_name'] }}</h3>
                        <div class="detail-meta">{{ number_format($group['count']) }} entri</div>
                    </div>
                    <div class="detail-meta">
                        Debit Rp {{ number_format($group['total_debit'], 0, ',', '.') }} |
                        Kredit Rp {{ number_format($group['total_credit'], 0, ',', '.') }} |
                        Selisih Rp {{ number_format(abs($group['balance']), 0, ',', '.') }}
                    </div>
                </summary>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Referensi</th>
                                <th>Akun</th>
                                <th>Keterangan</th>
                                <th>Tipe</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($group['entries'] as $entry)
                                <tr class="{{ $entry->is_reversal ? 'entry-reversal' : '' }}">
                                    <td>{{ optional($entry->date)->format('d/m/Y') }}</td>
                                    <td class="entry-reference">
                                        {{ $entry->reference }}
                                        @if($entry->is_reversal)
                                            [REV]
                                        @endif
                                    </td>
                                    <td>{{ $entry->coa?->code }} {{ $entry->coa?->name }}</td>
                                    <td class="entry-description">{{ $entry->description }}</td>
                                    <td>{{ $entry->journal_type }}</td>
                                    <td class="text-right">{{ (float) $entry->debit > 0 ? 'Rp ' . number_format((float) $entry->debit, 0, ',', '.') : '' }}</td>
                                    <td class="text-right">{{ (float) $entry->credit > 0 ? 'Rp ' . number_format((float) $entry->credit, 0, ',', '.') : '' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">Tidak ada detail jurnal.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach

        <div class="footer">
            Journal consolidation preview generated from shared report service.
        </div>
    </div>
</body>
</html>