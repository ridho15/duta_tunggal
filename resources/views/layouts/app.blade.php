<!doctype html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'ERP' }} – Duta Tunggal ERP</title>

    {{-- Typography: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Tailwind & App Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Alpine.js for lightweight UI interactivity (dropdowns, tabs, modals) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>

    @stack('styles')
</head>
<body class="bg-[#f6f7f8] text-[#111827] min-h-full font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        {{-- Sidebar Navigasi --}}
        @include('layouts.partials.sidebar')

        {{-- Area Konten Utama --}}
        <div class="lg:pl-60 flex-1 flex flex-col min-w-0">
            {{-- Topbar Header --}}
            @include('layouts.partials.topbar')

            {{-- Main Shell --}}
            <main class="flex-1 w-full max-w-[1180px] mx-auto px-4 sm:px-6 md:px-7 py-6 md:py-8 flex flex-col gap-5">
                {{-- Flash Notifications --}}
                @include('layouts.partials.flash')

                {{-- Page Content --}}
                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
