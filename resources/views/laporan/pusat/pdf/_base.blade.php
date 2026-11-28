{{-- Kerangka PDF laporan: $title, $subtitle, $printedAt, $printedBy; isi lewat @section('body') --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 22px 24px 30px; }
        body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9px; color: #111827; }
        .head { border-bottom: 2px solid #15803d; padding-bottom: 6px; margin-bottom: 8px; }
        .brand { font-size: 9px; font-weight: bold; color: #15803d; letter-spacing: .3px; }
        h1 { font-size: 15px; margin: 2px 0 0; }
        .sub { color: #4b5563; margin-top: 2px; }
        .kpis { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        .kpis td { border: 1px solid #e5e7eb; padding: 5px 7px; width: 25%; }
        .kpis .l { color: #4b5563; font-size: 8px; }
        .kpis .v { font-size: 12px; font-weight: bold; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #f3f4f6; text-align: left; font-size: 8px; padding: 5px 6px; border-bottom: 1px solid #d1d5db; }
        table.t td { padding: 4px 6px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
        table.t tr { page-break-inside: avoid; }
        .r { text-align: right; } .b { font-weight: bold; } .muted { color: #6b7280; } .mono { font-family: "DejaVu Sans Mono", monospace; }
        .warn { color: #92400e; } .bad { color: #b91c1c; }
        .foot { position: fixed; bottom: -18px; left: 0; right: 0; font-size: 7.5px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="head">
        <div class="brand">DUTA TUNGGAL · PUSAT LAPORAN</div>
        <h1>{{ $title }}</h1>
        <div class="sub">{{ $subtitle }}</div>
    </div>
    @yield('body')
    <div class="foot">Dicetak {{ $printedAt->translatedFormat('d M Y H:i') }} oleh {{ $printedBy ?? '-' }}</div>
</body>
</html>
