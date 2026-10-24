<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Duta Tunggal ERP</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               background: #F6F7F8; color: #111827; padding: 16px;
               font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .card { width: 100%; max-width: 480px; background: #fff; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; }
        .brand { font-size: 13px; font-weight: 800; letter-spacing: .5px; color: #111827; margin-bottom: 18px; }
        .brand span { font-size: 9px; font-weight: 700; color: #15803D; border: 1px solid #BBF7D0; border-radius: 6px; padding: 1px 5px; margin-left: 4px; }
        .pill { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;
                border-radius: 6px; padding: 3px 8px; margin-bottom: 10px; }
        .pill.danger { background: #FEF2F2; border: 1px solid #FCA5A5; color: #B91C1C; }
        .pill.warning { background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E; }
        h1 { font-size: 22px; font-weight: 800; margin: 0 0 8px; }
        p { font-size: 14px; line-height: 1.55; color: #374151; margin: 0 0 8px; }
        .code { font-family: ui-monospace, Menlo, monospace; font-size: 15px; font-weight: 700; background: #F9FAFB;
                border: 1px dashed #D1D5DB; border-radius: 10px; padding: 10px 12px; margin: 14px 0 4px; user-select: all; }
        .muted { font-size: 12px; color: #6B7280; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        a.btn { display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-radius: 10px;
                font-size: 14px; font-weight: 600; text-decoration: none; }
        a.primary { background: #15803D; color: #fff; }
        a.secondary { background: #fff; color: #374151; border: 1px solid #D1D5DB; }
    </style>
</head>
<body>
    <main class="card" data-error-code="@yield('error_code')">
        <div class="brand">DUTA TUNGGAL<span>ERP</span></div>
        @yield('content')
        <div class="actions">
            <a class="btn primary" href="{{ route('dashboard') }}">Kembali ke Beranda</a>
            <a class="btn secondary" href="javascript:history.back()">Halaman sebelumnya</a>
        </div>
    </main>
</body>
</html>
