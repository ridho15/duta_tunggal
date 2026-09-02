<x-filament-panels::page>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        window.__ORDER_REQUEST_INITIAL_DATA__ = @js($initialDependencies);
    </script>

    <div id="order-request-next-app">
        {{-- Loading Skeleton while React mounts --}}
        <div class="space-y-6 animate-pulse">
            <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                <div class="h-5 bg-gray-200 dark:bg-gray-800 rounded w-1/4"></div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 dark:bg-gray-800/60 rounded-lg"></div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-900 p-6 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs space-y-4">
                <div class="h-5 bg-gray-200 dark:bg-gray-800 rounded w-1/3"></div>
                <div class="h-40 bg-gray-100 dark:bg-gray-800/60 rounded-xl"></div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
