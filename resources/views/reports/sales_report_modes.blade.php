<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $start_date }} s/d {{ $end_date }}</title>
    <style>
        @page { size: A4 landscape; margin: 1cm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #333; }
        h1 { font-size: 15px; margin: 0; }
        .meta { font-size: 9px; color: #555; margin: 2px 0 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #eef2f7; font-weight: bold; }
        .num { text-align: right; }
        .summary { margin-top: 12px; width: 60%; }
        .summary td { border: 1px solid #ddd; }
        .summary td:first-child { background: #f7f7f7; width: 45%; }
        .footnote { margin-top: 8px; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <h1>{{ strtoupper($title) }}</h1>
    <div class="meta">
        Periode: {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
        &nbsp;|&nbsp; Dicetak: {{ now()->format('d/m/Y H:i') }} oleh {{ auth()->user()->name ?? 'System' }}
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        <td class="{{ str_starts_with((string) $cell, 'Rp') || str_starts_with((string) $cell, '-Rp') ? 'num' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" style="text-align:center;color:#888;">Tidak ada data pada periode dan filter yang dipilih.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        @foreach ($summary_lines as $label => $value)
            <tr>
                <td>{{ $label }}</td>
                <td class="num">{{ $value }}</td>
            </tr>
        @endforeach
    </table>

    @if (!empty($footnote))
        <div class="footnote">{{ $footnote }}</div>
    @endif
</body>
</html>
