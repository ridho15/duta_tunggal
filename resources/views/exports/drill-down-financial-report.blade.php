<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drill Down Financial Report Export</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; vertical-align: top; }
        th { background: #0f172a; color: #fff; text-align: left; }
        .title { font-size: 18px; font-weight: 800; margin-bottom: 4px; }
        .subtitle { font-size: 12px; color: #475569; margin-bottom: 16px; }
        .section-row { background: #f8fafc; font-weight: 700; }
        .total-row { background: #eff6ff; font-weight: 800; }
        .right { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="title">Drill Down Financial Report</div>
    <div class="subtitle">
        Periode {{ $report['period'] ?? '-' }}
        | Cabang: {{ data_get($report, 'filters.cabang_id') ? 'Tersaring' : 'Semua cabang' }}
        @if(!empty($report['filters']['account_type']))
            | Tipe Akun: {{ $report['filters']['account_type'] }}
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode Akun</th>
                <th>Nama Akun</th>
                <th>Tipe</th>
                <th class="right">Debit</th>
                <th class="right">Kredit</th>
                <th class="right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['grouped'] ?? [] as $group)
                <tr class="section-row">
                    <td>{{ $group['coa']?->code ?? '-' }}</td>
                    <td colspan="5">{{ $group['coa']?->name ?? '-' }}</td>
                </tr>
                @foreach($group['lines'] as $line)
                    <tr>
                        <td>{{ $line->date }}</td>
                        <td>{{ $line->reference ?: '-' }}</td>
                        <td>{{ $line->description ?: '-' }}</td>
                        <td class="right">{{ $line->debit > 0 ? number_format($line->debit, 0, ',', '.') : '-' }}</td>
                        <td class="right">{{ $line->credit > 0 ? number_format($line->credit, 0, ',', '.') : '-' }}</td>
                        <td class="right">{{ number_format($group['balance'] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3">Subtotal {{ $group['coa']?->name ?? '-' }}</td>
                    <td class="right">{{ number_format($group['total_debit'] ?? 0, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($group['total_credit'] ?? 0, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format(abs($group['balance'] ?? 0), 0, ',', '.') }}{{ ($group['balance'] ?? 0) < 0 ? ' (K)' : '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
