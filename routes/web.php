<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Livewire\Settings\Profile;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Appearance;

// Temporary root health check to avoid Filament-side rendering errors from
// causing the PHP server to return an empty reply. This keeps the site
// reachable while we debug Filament report views. Commented out now so the
// application can serve the normal root again.
/*
Route::get('/', function () {
    return response('<h1>Duta Tunggal ERP — development server</h1><p>OK</p>', 200)
        ->header('Content-Type', 'text/html; charset=utf-8');
});
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
    
    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

// Add home route for authenticated users
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        // Redirect authenticated users to the Filament admin dashboard by default
        // Filament registers the dashboard page as 'my-dashboard' by default
        return redirect()->route('filament.admin.pages.my-dashboard');
    })->name('home');
});

// Authentication is handled by the Filament panel under /admin. We avoid
// defining app-level compatibility redirects or custom logout endpoints
// here so Filament's own routes remain authoritative. If you need public
// auth routes in the future, re-introduce them in `routes/auth.php`.

// Provide a small compatibility route named 'login' so framework helpers
// and third-party packages that call route('login') can redirect guests
// to the Filament admin sign-in page. This route simply redirects to
// the Filament admin base path which serves the login UI.
Route::get('/login', function () {
    return redirect('/admin');
})->name('login');

// Provide a logout route for testing compatibility
Route::post('/logout', function () {
    Auth::logout();
    return redirect('/admin');
})->name('logout');

// Let Filament register its own /admin dashboard route. Previously we
// added a compatibility redirect here but it accidentally redirected to
// the route name itself which caused a redirect loop. Removing this
// custom route ensures the panel provider's routes are authoritative.

Route::get('testing', function () {
    $objects = [
        new App\Models\User(),
        new App\Models\Invoice(),
        new App\Models\User(),
        new App\Models\Customer(),
        new App\Models\Invoice(),
    ];

    // Ambil nama class dari setiap object
    $classNames = array_map(fn($obj) => get_class($obj), $objects);

    // Hitung kemunculan setiap class
    $classCounts = array_count_values($classNames);

    // Ambil class yang muncul lebih dari 1 kali (duplikat)
    $duplicates = array_filter($classCounts, fn($count) => $count > 1);

    // Hasil
    print_r(array_keys($duplicates));
});

// (dev route removed) If you need a dev-only PDF endpoint to verify rendering,
// re-add a local-only route here that calls the report service and Pdf::loadView.

// Temporary dev-only route: render exports.cash-flow with dummy data to test PDF encoding
// Dummy PDF test route removed. To test again, re-add a dev-only route that passes
// a minimal $report array to the `exports.cash-flow` view and uses Pdf::loadView().

// Diagnostic route (local only) to help find malformed UTF-8 sources when rendering PDF
Route::get('dev/reports/cash-flow/diag', function () {
    if (app()->environment() !== 'local') {
        abort(404);
    }

    $results = [];

    // 1) Render dummy data to ensure template itself is OK
    $dummy = [
        'period' => ['start' => '2025-10-01', 'end' => '2025-10-31'],
        'opening_balance' => 0,
        'net_change' => 0,
        'closing_balance' => 0,
        'sections' => [],
    ];
    $htmlDummy = view('exports.cash-flow', ['report' => $dummy, 'selectedBranches' => []])->render();
    $results['dummy_render_utf8'] = mb_check_encoding($htmlDummy, 'UTF-8');
    $results['dummy_length'] = strlen($htmlDummy);

    // 2) Try render with real report data (if possible) and catch exceptions
    try {
        $report = app(App\Services\Reports\CashFlowReportService::class)->generate(null, null, ['branches' => []]);
        $htmlReal = view('exports.cash-flow', ['report' => $report, 'selectedBranches' => []])->render();
        $results['real_render_utf8'] = mb_check_encoding($htmlReal, 'UTF-8');
        $results['real_length'] = strlen($htmlReal);
    } catch (Throwable $e) {
        $results['real_render_exception'] = $e->getMessage();
        $results['real_render_trace'] = $e->getTraceAsString();
        $htmlReal = $e->getMessage();
        $results['real_render_utf8'] = mb_check_encoding($htmlReal, 'UTF-8');
    }

    // 3) If any html is not valid UTF-8, include a hex snippet of the first 200 bytes
    foreach (['dummy' => $htmlDummy, 'real' => ($htmlReal ?? '')] as $k => $h) {
        if (!mb_check_encoding($h, 'UTF-8')) {
            $results["{$k}_first_bytes_hex"] = substr(chunk_split(bin2hex(substr($h, 0, 200)), 2, ' '), 0, 400);
            $results["{$k}_sample_ascii"] = preg_replace('/[\x80-\xFF]/', '?', substr($h, 0, 200));
        }
    }

    // 4) Scan blade view files for BOM or invalid encoding
    $badViews = [];
    $viewFiles = collect(glob(resource_path('views') . '/**/*'))->filter()->values()->all();
    // Better to use a recursive iterator
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $path = $file->getPathname();
            $contents = file_get_contents($path);
            // check BOM
            $starts = substr($contents, 0, 3);
            if ($starts === "\xEF\xBB\xBF") {
                $badViews[] = ['file' => $path, 'issue' => 'bom'];
                continue;
            }
            if (!mb_check_encoding($contents, 'UTF-8')) {
                $badViews[] = ['file' => $path, 'issue' => 'not-utf8', 'sample_hex' => substr(chunk_split(bin2hex(substr($contents, 0, 200)), 2, ' '), 0, 400)];
            }
        }
    }
    $results['view_files_issues'] = $badViews;

    // 5) Scan compiled views
    $badCompiled = [];
    if (is_dir(storage_path('framework/views'))) {
        $it2 = new DirectoryIterator(storage_path('framework/views'));
        foreach ($it2 as $f) {
            if ($f->isFile()) {
                $path = $f->getPathname();
                $contents = file_get_contents($path);
                if (!mb_check_encoding($contents, 'UTF-8')) {
                    $badCompiled[] = ['file' => $path, 'issue' => 'not-utf8', 'sample_hex' => substr(chunk_split(bin2hex(substr($contents, 0, 200)), 2, ' '), 0, 400)];
                }
            }
        }
    }
    $results['compiled_view_issues'] = $badCompiled;

    // 6) Check for presence of base64 images or binary data in the rendered HTML (which could break parsing)
    $checkHtml = $htmlReal ?? $htmlDummy;
    $results['contains_base64_image'] = preg_match('/data:image\/(png|jpeg|jpg|svg\+xml);base64,/', $checkHtml) === 1;

    return response()->json($results);
});

// Dev UI: PDF export test page
Route::get('dev/pdf-test', function () {
    if (app()->environment() !== 'local') {
        abort(404);
    }
    return view('dev.pdf-test');
});

// Dev: generate PDF (type=dummy|real) for testing
Route::get('dev/pdf-test/generate', function () {
    if (app()->environment() !== 'local') {
        abort(404);
    }

    $type = request()->query('type', 'dummy');
    $filename = 'dev-pdf-' . $type . '-' . now()->format('Ymd_His');

    if ($type === 'real') {
        $report = app(App\Services\Reports\CashFlowReportService::class)->generate(null, null, ['branches' => []]);
    } else {
        // dummy data
        $report = [
            'period' => ['start' => now()->startOfMonth()->toDateString(), 'end' => now()->endOfMonth()->toDateString()],
            'opening_balance' => 1000000,
            'net_change' => 500000,
            'closing_balance' => 1500000,
            'sections' => [
                ['label' => 'Sample Section', 'total' => 1500000, 'items' => [
                    ['label' => 'Item A', 'amount' => 1000000, 'metadata' => ['sources' => ['S1'], 'detail' => [], 'breakdown' => []]],
                    ['label' => 'Item B', 'amount' => 500000, 'metadata' => ['sources' => ['S2'], 'detail' => [], 'breakdown' => []]],
                ]],
            ],
        ];
    }

    // sanitize recursively
    $sanitize = function ($v) use (&$sanitize) {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $val) {
                $out[$k] = $sanitize($val);
            }
            return $out;
        }
        if (is_object($v)) {
            $arr = (array)$v;
            $out = [];
            foreach ($arr as $k => $val) {
                $out[$k] = $sanitize($val);
            }
            return $out;
        }
        if (is_string($v)) {
            $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
            $res = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($res === false) {
                $res = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
            }
            return $res;
        }
        return $v;
    };

    $sanitized = $sanitize($report);

    return Barryvdh\DomPDF\Facade\Pdf::loadView('exports.cash-flow', ['report' => $sanitized, 'selectedBranches' => []])
        ->setOptions(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true])
        ->setPaper('a4', 'portrait')
        ->download($filename . '.pdf');
});

// Local-only route to serve temporary exported files (used by Filament/Livewire JSON flow)
Route::get('exports/download/{filename}', function ($filename) {
    if (app()->environment() !== 'local') {
        abort(404);
    }

    $path = storage_path('app/exports/' . basename($filename));
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->download($path, $filename)->deleteFileAfterSend(true);
})->name('exports.download');
