<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Page Header --}}
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl shadow-md p-6 text-white border border-primary-400/20 hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Journal Entries - Grouped View
                    </h1>
                    <p class="text-primary-100 mt-1">View and analyze journal entries organized by parent Chart of Accounts</p>
                </div>
                <div class="hidden md:block">
                    <svg class="w-24 h-24 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Filter Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow duration-200">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-600 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Options
                </h3>
            </div>
            <div class="p-6">
                <form wire:submit.prevent="applyFilters">
                    {{ $this->form }}

                    <div class="mt-6 flex justify-end gap-3">
                        <x-filament::button type="button" color="gray" wire:click="$set('data', [])">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset
                        </x-filament::button>
                        <x-filament::button type="submit" color="primary">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Apply Filters
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Summary Statistics --}}
        @if (!empty($summary))
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl shadow-md p-6 border border-blue-200 dark:border-blue-700 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-2">Total Entries</div>
                        <div class="text-3xl font-bold text-blue-900 dark:text-blue-100">
                            {{ number_format($summary['total_entries']) }}
                        </div>
                    </div>
                    <div class="bg-blue-500 rounded-full p-3 ml-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl shadow-md p-6 border border-green-200 dark:border-green-700 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-green-600 dark:text-green-400 uppercase tracking-wide mb-2">Total Debit</div>
                        <div class="text-3xl font-bold text-green-900 dark:text-green-100">
                            <span class="text-xl">Rp</span> {{ number_format($summary['total_debit'], 0) }}
                        </div>
                    </div>
                    <div class="bg-green-500 rounded-full p-3 ml-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl shadow-md p-6 border border-red-200 dark:border-red-700 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wide mb-2">Total Credit</div>
                        <div class="text-3xl font-bold text-red-900 dark:text-red-100">
                            <span class="text-xl">Rp</span> {{ number_format($summary['total_credit'], 0) }}
                        </div>
                    </div>
                    <div class="bg-red-500 rounded-full p-3 ml-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl shadow-md p-6 border border-purple-200 dark:border-purple-700 hover:shadow-lg transition-shadow duration-200">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-purple-600 dark:text-purple-400 uppercase tracking-wide mb-2">Balance Status</div>
                        <div class="mt-2">
                            @if ($summary['is_balanced'])
                                <div class="flex items-center gap-2">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-lg font-bold text-green-700 dark:text-green-400">Balanced</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-lg font-bold text-red-700 dark:text-red-400">Unbalanced</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-purple-500 rounded-full p-3 ml-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Grouped Journal Entries with Alpine.js Dropdowns --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow duration-200">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-700 dark:to-gray-600 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Journal Entries by Parent COA
                    </h3>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ count($groupedData) }} parent accounts
                    </span>
                </div>
            </div>

            {{-- Table Headers --}}
            <div class="hidden lg:block bg-gray-100 dark:bg-gray-900 px-6 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-12 gap-4 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                    <div class="col-span-1"></div>
                    <div class="col-span-1">Code</div>
                    <div class="col-span-4">Account Name</div>
                    <div class="col-span-2 text-right">Debit</div>
                    <div class="col-span-2 text-right">Credit</div>
                    <div class="col-span-2 text-right">Balance</div>
                </div>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse ($groupedData as $index => $parent)
                    <div x-data="{ open: false }" class="transition-all duration-200 hover:bg-gradient-to-r hover:from-primary-50 hover:to-transparent dark:hover:from-primary-900/10">
                        {{-- Parent COA Row --}}
                        <div
                            @click="open = !open"
                            class="px-6 py-4 cursor-pointer"
                        >
                            <div class="flex items-center space-x-4">
                                {{-- Dropdown Icon --}}
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center transition-transform duration-200" :class="{ 'rotate-90': open }">
                                        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>

                                {{-- COA Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                                        <div class="md:col-span-1">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono font-bold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                                {{ $parent['code'] }}
                                            </span>
                                        </div>

                                        <div class="md:col-span-5">
                                            <div class="font-bold text-gray-900 dark:text-white text-base md:text-lg">
                                                {{ $parent['name'] }}
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                    {{ $parent['type'] }}
                                                </span>
                                                @if (!empty($parent['children']))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                        </svg>
                                                        {{ count($parent['children']) }} child{{ count($parent['children']) > 1 ? 'ren' : '' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="md:col-span-2">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Debit</div>
                                            <div class="text-sm font-bold text-green-700 dark:text-green-400 flex items-center gap-1">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                <span class="truncate">Rp {{ number_format($parent['total_debit'], 0) }}</span>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Credit</div>
                                            <div class="text-sm font-bold text-red-700 dark:text-red-400 flex items-center gap-1">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                </svg>
                                                <span class="truncate">Rp {{ number_format($parent['total_credit'], 0) }}</span>
                                            </div>
                                        </div>

                                        <div class="md:col-span-2">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Balance</div>
                                            <div class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-bold {{ $parent['balance'] >= 0 ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300' }}">
                                                <svg class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                                </svg>
                                                <span class="truncate">Rp {{ number_format($parent['balance'], 0) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Children COAs (Collapsed Content) --}}
                        <div
                            x-show="open"
                            x-collapse
                            class="bg-gradient-to-b from-gray-50 to-white dark:from-gray-900/50 dark:to-gray-800 border-t border-gray-200 dark:border-gray-700"
                        >
                            @if (!empty($parent['children']))
                                <div class="px-6 py-4 space-y-3">
                                    @foreach ($parent['children'] as $childIndex => $child)
                                        <div x-data="{ childOpen: false }" class="border-l-4 border-primary-400 bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200">
                                            {{-- Child COA Header --}}
                                            <div
                                                @click="childOpen = !childOpen"
                                                class="px-5 py-3 cursor-pointer hover:bg-gradient-to-r hover:from-primary-50 hover:to-transparent dark:hover:from-primary-900/10 transition-colors duration-200"
                                            >
                                                <div class="flex items-center space-x-3">
                                                    {{-- Dropdown Icon --}}
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center transition-transform duration-200" :class="{ 'rotate-90': childOpen }">
                                                            <svg class="w-4 h-4 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    {{-- Child COA Info --}}
                                                    <div class="flex-1 min-w-0">
                                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                                            <div class="md:col-span-1">
                                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-mono font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                                    {{ $child['code'] }}
                                                                </span>
                                                            </div>

                                                            <div class="md:col-span-5">
                                                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                                                    {{ $child['name'] }}
                                                                </div>
                                                            </div>

                                                            <div class="md:col-span-2">
                                                                <div class="text-xs text-green-700 dark:text-green-400 font-semibold">
                                                                    Rp {{ number_format($child['total_debit'], 0) }}
                                                                </div>
                                                            </div>

                                                            <div class="md:col-span-2">
                                                                <div class="text-xs text-red-700 dark:text-red-400 font-semibold">
                                                                    Rp {{ number_format($child['total_credit'], 0) }}
                                                                </div>
                                                            </div>

                                                            <div class="md:col-span-2">
                                                                <div class="inline-flex items-center px-2 py-1 rounded text-xs font-bold {{ $child['balance'] >= 0 ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300' }}">
                                                                    Rp {{ number_format($child['balance'], 0) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            {{-- Child Entries (Journal Entry Details) --}}
                                            <div
                                                x-show="childOpen"
                                                x-collapse
                                                class="bg-gradient-to-b from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 border-t border-gray-200 dark:border-gray-700"
                                            >
                                                @if (!empty($child['entries']))
                                                    <div class="p-4">
                                                        <div class="overflow-x-auto rounded-lg shadow-inner">
                                                            <table class="w-full text-xs">
                                                                <thead class="bg-gradient-to-r from-gray-200 to-gray-100 dark:from-gray-800 dark:to-gray-700">
                                                                    <tr>
                                                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">
                                                                            <div class="flex items-center gap-1">
                                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                                </svg>
                                                                                Date
                                                                            </div>
                                                                        </th>
                                                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Reference</th>
                                                                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Description</th>
                                                                        <th class="px-4 py-3 text-right font-semibold text-green-700 dark:text-green-400">Debit</th>
                                                                        <th class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-400">Credit</th>
                                                                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Type</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                                                    @foreach ($child['entries'] as $entry)
                                                                        <tr class="hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-colors">
                                                                            <td class="px-4 py-3 whitespace-nowrap">
                                                                                <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold">
                                                                                    {{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}
                                                                                </span>
                                                                            </td>
                                                                            <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">{{ $entry['reference'] ?? '-' }}</td>
                                                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $entry['description'] ?? '-' }}</td>
                                                                            <td class="px-4 py-3 text-right font-semibold text-green-700 dark:text-green-400">
                                                                                {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0) : '-' }}
                                                                            </td>
                                                                            <td class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-400">
                                                                                {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0) : '-' }}
                                                                            </td>
                                                                            <td class="px-4 py-3 text-center">
                                                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                                                                    {{ $entry['journal_type'] === 'sales' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : '' }}
                                                                                    {{ $entry['journal_type'] === 'purchase' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                                                                                    {{ $entry['journal_type'] === 'depreciation' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : '' }}
                                                                                    {{ $entry['journal_type'] === 'manual' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' : '' }}
                                                                                    {{ !in_array($entry['journal_type'], ['sales', 'purchase', 'depreciation', 'manual']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                                                                ">
                                                                                    {{ ucfirst($entry['journal_type'] ?? 'N/A') }}
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Parent Direct Entries (if any) --}}
                            @if (!empty($parent['entries']))
                                <div class="px-6 py-4 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-800 border-t border-gray-200 dark:border-gray-700">
                                    <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Direct Entries
                                    </h4>
                                    <div class="overflow-x-auto rounded-lg shadow-inner">
                                        <table class="w-full text-xs">
                                            <thead class="bg-gradient-to-r from-gray-200 to-gray-100 dark:from-gray-800 dark:to-gray-700">
                                                <tr>
                                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Date</th>
                                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Reference</th>
                                                    <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">Description</th>
                                                    <th class="px-4 py-3 text-right font-semibold text-green-700 dark:text-green-400">Debit</th>
                                                    <th class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-400">Credit</th>
                                                    <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                                @foreach ($parent['entries'] as $entry)
                                                    <tr class="hover:bg-primary-50 dark:hover:bg-primary-900/10 transition-colors">
                                                        <td class="px-4 py-3 whitespace-nowrap">
                                                            <span class="inline-flex items-center px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-semibold">
                                                                {{ \Carbon\Carbon::parse($entry['date'])->format('d M Y') }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">{{ $entry['reference'] ?? '-' }}</td>
                                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $entry['description'] ?? '-' }}</td>
                                                        <td class="px-4 py-3 text-right font-semibold text-green-700 dark:text-green-400">
                                                            {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0) : '-' }}
                                                        </td>
                                                        <td class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-400">
                                                            {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0) : '-' }}
                                                        </td>
                                                        <td class="px-4 py-3 text-center">
                                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                                                                {{ $entry['journal_type'] === 'sales' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : '' }}
                                                                {{ $entry['journal_type'] === 'purchase' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : '' }}
                                                                {{ $entry['journal_type'] === 'depreciation' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' : '' }}
                                                                {{ $entry['journal_type'] === 'manual' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' : '' }}
                                                                {{ !in_array($entry['journal_type'], ['sales', 'purchase', 'depreciation', 'manual']) ? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' : '' }}
                                                            ">
                                                                {{ ucfirst($entry['journal_type'] ?? 'N/A') }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-16 text-center">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No journal entries found</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                            Try adjusting your filters or date range to see journal entries.
                        </p>
                        <x-filament::button wire:click="$set('data', [])" color="primary" size="sm">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset Filters
                        </x-filament::button>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }

        /* Custom scrollbar */
        .overflow-x-auto::-webkit-scrollbar {
            height: 8px;
        }

        .overflow-x-auto::-webkit-scrollbar-track {
            background: rgb(243 244 246);
        }

        .dark .overflow-x-auto::-webkit-scrollbar-track {
            background: rgb(31 41 55);
        }

        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: rgb(156 163 175);
            border-radius: 4px;
        }

        .overflow-x-auto::-webkit-scrollbar-thumb:hover {
            background: rgb(107 114 128);
        }

        /* Smooth transitions */
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 200ms;
        }

        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }

            [x-data] {
                display: block !important;
            }
        }
    </style>
</x-filament-panels::page>
