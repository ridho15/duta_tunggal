<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profit Loss Multi Division - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        .print-toolbar { display: flex; align-items: center; gap: .75rem; padding: .75rem 1.5rem; background: #0f172a; color: #fff; position: sticky; top: 0; z-index: 100; }
        .print-toolbar h1 { flex: 1; margin: 0; font-size: 1rem; font-weight: 700; }
        .pt-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border-radius: 8px; border: none; font-size: .85rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .pt-btn-print { background: #0f766e; color: #fff; }
        .pt-btn-close { background: #475569; color: #fff; }
        .report-wrap { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        @media print {
            body { background: #fff; }
            .print-toolbar { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <h1>Profit Loss Multi Division</h1>
        <a class="pt-btn pt-btn-print" href="{{ route('reports.profit-loss-multi-division.excel', array_filter(['startDate' => $report['period']['start'] ?? null, 'endDate' => $report['period']['end'] ?? null, 'cabangIds' => array_column($report['divisions'] ?? [], 'id')], fn ($value) => $value !== null && $value !== '' && $value !== [])) }}">Download Excel</a>
        <button class="pt-btn pt-btn-print" onclick="window.print()">Cetak / PDF</button>
        <button class="pt-btn pt-btn-close" onclick="window.close()">Tutup</button>
    </div>

    <div class="report-wrap">
        @include('reports.partials.profit-loss-multi-division-report', ['report' => $report])
    </div>
</body>
</html>