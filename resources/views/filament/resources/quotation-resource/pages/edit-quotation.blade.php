<x-filament-panels::page>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div id="quotation-next-app" data-record-id="{{ $record->id }}">
        {{-- Loading Skeleton while React mounts --}}
        <div class="space-y-6 animate-pulse">
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-xs space-y-4">
                <div class="h-5 bg-gray-200 rounded w-1/4"></div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                    <div class="h-10 bg-gray-100 rounded-lg"></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-xs space-y-4">
                <div class="h-5 bg-gray-200 rounded w-1/3"></div>
                <div class="h-40 bg-gray-100 rounded-xl"></div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
