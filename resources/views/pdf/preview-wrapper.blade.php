<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - Preview</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 dark:bg-slate-900 min-h-screen">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        {{-- Header bar --}}
        <div class="rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 mb-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
                        {{ $title }}
                    </h1>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                        Preview dokumen. Klik tombol download untuk menyimpan file PDF.
                    </p>
                </div>
                <a href="{{ $downloadUrl }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-600">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download PDF
                </a>
            </div>
        </div>

        {{-- PDF iframe --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow dark:border-slate-700 dark:bg-slate-800">
            <iframe
                src="{{ $streamUrl }}"
                title="{{ $title }} Preview"
                class="block h-[calc(100vh-220px)] w-full border-0 bg-slate-100 dark:bg-slate-900"
            ></iframe>
        </div>
    </div>
</body>
</html>