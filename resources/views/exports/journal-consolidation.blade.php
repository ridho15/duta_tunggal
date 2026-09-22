<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Journal Consolidation</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1, h2, h3 { margin: 0; }
        .header { margin-bottom: 16px; }
        .meta { margin: 8px 0 16px; color: #4b5563; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 8px; margin-bottom: 16px; }
        .cards td { border: 1px solid #d1d5db; padding: 10px; border-radius: 8px; }
        .label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .value { font-size: 15px; font-weight: 700; margin-top: 4px; }
        table.report { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        table.report th, table.report td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        table.report th { background: #f3f4f6; text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .section-title { margin: 18px 0 8px; font-size: 14px; font-weight: 700; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Journal Consolidation</h1>
        <div class="meta">
            Periode {{ $report['period'] ?? '-' }}
            @if(!empty($selectedBranches))
                | Cabang: {{ implode(', ', $selectedBranches) }}
            @endif
            @if(!empty(data_get($report, 'filters.journal_type')))
                | Jenis jurnal: {{ data_get($report, 'filters.journal_type') }}
            @endif
            | Mode: {{ data_get($report, 'filters.group_by_branch') ? 'Per Cabang' : 'Konsolidasi' }}
        </div>
    </div>

    <table class="cards">
        <tr>
            <td>
                <div class="label">Total Entri</div>
                <div class="value">{{ number_format($report['count'] ?? 0) }}</div>
            </td>
            <td>
                <div class="label">Total Debit</div>
                <div class="value">Rp {{ number_format($report['total_debit'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Total Kredit</div>
                <div class="value">Rp {{ number_format($report['total_credit'] ?? 0, 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Status</div>
                <div class="value">{{ ($report['balanced'] ?? false) ? 'Seimbang' : 'Tidak Seimbang' }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Ringkasan per Akun</div>
    <table class="report">
        <thead>
            <tr>
                <th>Kode Akun</th>
                <th>Nama Akun</th>
                <th>Tipe</th>
                <th class="num">Debit</th>
                <th class="num">Kredit</th>
                <th class="num">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['coa_summary'] ?? [] as $row)
                <tr>
                    <td>{{ data_get($row, 'coa.code', '-') }}</td>
                    <td>{{ data_get($row, 'coa.name', '-') }}</td>
                    <td>{{ data_get($row, 'coa.type', '-') }}</td>
                    <td class="num">Rp {{ number_format($row['total_debit'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format($row['total_credit'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num">Rp {{ number_format(abs($row['balance'] ?? 0), 0, ',', '.') }}{{ ($row['balance'] ?? 0) < 0 ? ' (K)' : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="muted">Tidak ada data akun untuk filter yang dipilih.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @foreach($report['grouped'] ?? [] as $group)
        <div class="section-title">{{ $group['cabang_name'] }} <span class="muted">({{ $group['count'] ?? count($group['entries'] ?? []) }} entri)</span></div>
        <table class="report">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Referensi</th>
                    <th>Akun</th>
                    <th>Keterangan</th>
                    <th>Tipe</th>
                    <th class="num">Debit</th>
                    <th class="num">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($group['entries'] ?? [] as $entry)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                        <td>{{ $entry->reference }}</td>
                        <td>{{ optional($entry->coa)->code }} {{ optional($entry->coa)->name }}</td>
                        <td>{{ $entry->description }}</td>
                        <td>{{ $entry->journal_type }}</td>
                        <td class="num">{{ $entry->debit > 0 ? 'Rp ' . number_format($entry->debit, 0, ',', '.') : '' }}</td>
                        <td class="num">{{ $entry->credit > 0 ? 'Rp ' . number_format($entry->credit, 0, ',', '.') : '' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">Tidak ada jurnal dalam kelompok ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach
</body>
</html>
