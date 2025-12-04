<x-filament-panels::page>
    <div class="custom-space-y">
        {{-- Page Header --}}
        <div class="custom-header-bg custom-rounded custom-shadow custom-p-6 custom-text-white custom-border custom-hover-shadow">
            <div class="custom-flex-between">
                <div>
                    <h1 class="custom-title">
                        <svg class="custom-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        Journal Entries - Grouped View
                    </h1>
                    <p class="custom-subtitle">View and analyze journal entries organized by parent Chart of Accounts</p>
                </div>
                <div class="custom-hidden-md">
                    <svg class="custom-large-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- Simple Content --}}
        <div class="custom-card custom-rounded custom-shadow custom-border custom-hover-shadow">
            <div class="custom-p-6">
                <h3 class="custom-card-title">Journal Entries Grouped Data</h3>
                <p class="custom-card-text">This page displays journal entries grouped by parent COA.</p>

                @if($groupedData)
                    <div class="custom-mt-4">
                        <p class="custom-text-sm-gray">Data loaded successfully. Found {{ count($groupedData) }} groups.</p>
                    </div>
                @else
                    <div class="custom-mt-4">
                        <p class="custom-text-sm-gray">No data available.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Summary Statistics --}}
        @if (!empty($summary))
        <div class="custom-grid-4">
            <div class="custom-stat-card custom-stat-neutral custom-rounded custom-shadow custom-border custom-hover-shadow">
                <div class="custom-stat-content">
                    <div class="custom-stat-label">Total Entries</div>
                    <div class="custom-stat-value">
                        {{ number_format($summary['total_entries']) }}
                    </div>
                </div>
                <div class="custom-stat-icon custom-bg-neutral">
                    <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>

            <div class="custom-stat-card custom-stat-neutral custom-rounded custom-shadow custom-border custom-hover-shadow">
                <div class="custom-stat-content">
                    <div class="custom-stat-label">Total Debit</div>
                    <div class="custom-stat-value">
                        <span class="custom-currency">Rp</span> {{ number_format($summary['total_debit'], 0) }}
                    </div>
                </div>
                <div class="custom-stat-icon custom-bg-neutral">
                    <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
            </div>

            <div class="custom-stat-card custom-stat-neutral custom-rounded custom-shadow custom-border custom-hover-shadow">
                <div class="custom-stat-content">
                    <div class="custom-stat-label">Total Credit</div>
                    <div class="custom-stat-value">
                        <span class="custom-currency">Rp</span> {{ number_format($summary['total_credit'], 0) }}
                    </div>
                </div>
                <div class="custom-stat-icon custom-bg-neutral">
                    <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </div>
            </div>

            <div class="custom-stat-card custom-stat-neutral custom-rounded custom-shadow custom-border custom-hover-shadow">
                <div class="custom-stat-content">
                    <div class="custom-stat-label">Balance Status</div>
                    <div class="custom-mt-2">
                        @if ($summary['is_balanced'])
                            <div class="custom-flex-gap">
                                <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="custom-text-lg-bold custom-text-neutral">Balanced</span>
                            </div>
                        @else
                            <div class="custom-flex-gap">
                                <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="custom-text-lg-bold custom-text-neutral">Unbalanced</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="custom-stat-icon custom-bg-neutral">
                    <svg class="custom-icon-sm custom-text-neutral" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                    </svg>
                </div>
            </div>
        </div>
        @endif

        {{-- Grouped Journal Entries with Alpine.js Dropdowns --}}
        <div class="custom-card custom-rounded custom-shadow custom-border custom-hover-shadow">
            <div class="custom-card-header">
                <div class="custom-flex-between">
                    <h3 class="custom-card-title custom-flex-gap">
                        <svg class="custom-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Journal Entries by Parent COA
                    </h3>
                    <span class="custom-text-sm-gray">
                        {{ count($groupedData) }} parent accounts
                    </span>
                </div>
            </div>

            {{-- Table Headers --}}
            <div class="custom-hidden-lg custom-table-section-header">
                <div class="custom-grid-12 custom-table-header-text">
                    <div class="custom-col-1"></div>
                    <div class="custom-col-1">Code</div>
                    <div class="custom-col-4">Account Name</div>
                    <div class="custom-col-2 custom-text-right">Debit</div>
                    <div class="custom-col-2 custom-text-right">Credit</div>
                    <div class="custom-col-2 custom-text-right">Balance</div>
                </div>
            </div>

            <div class="custom-divide">
                @forelse ($groupedData as $index => $parent)
                    <div x-data="{ open: false }" class="custom-transition custom-hover-bg">
                        {{-- Parent COA Row --}}
                        <div
                            @click="open = !open"
                            class="custom-p-4 custom-cursor-pointer"
                        >
                            <div class="custom-flex-space">
                                {{-- Dropdown Icon --}}
                                <div class="custom-flex-shrink">
                                    <div class="custom-dropdown-icon" :class="{ 'custom-rotate-90': open }">
                                        <svg class="custom-icon-sm custom-text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>

                                {{-- COA Info --}}
                                <div class="custom-flex-1">
                                    <div class="custom-grid-responsive">
                                        <div class="custom-col-md-1">
                                            <span class="custom-code-badge">
                                                {{ $parent['code'] }}
                                            </span>
                                        </div>

                                        <div class="custom-col-md-5">
                                            <div class="custom-account-name">
                                                {{ $parent['name'] }}
                                            </div>
                                            <div class="custom-flex-wrap custom-mt-1">
                                                <span class="custom-type-badge custom-bg-blue">
                                                    {{ $parent['type'] }}
                                                </span>
                                                @if (!empty($parent['children']))
                                                    <span class="custom-type-badge custom-bg-purple">
                                                        <svg class="custom-icon-xs custom-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                        </svg>
                                                        {{ count($parent['children']) }} child{{ count($parent['children']) > 1 ? 'ren' : '' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="custom-col-md-2">
                                            <div class="custom-text-xs-gray custom-mb-1">Debit</div>
                                            <div class="custom-text-sm-bold custom-text-green custom-flex-gap">
                                                <svg class="custom-icon-xs custom-flex-shrink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                <span class="custom-truncate">Rp {{ number_format($parent['total_debit'], 0) }}</span>
                                            </div>
                                        </div>

                                        <div class="custom-col-md-2">
                                            <div class="custom-text-xs-gray custom-mb-1">Credit</div>
                                            <div class="custom-text-sm-bold custom-text-red custom-flex-gap">
                                                <svg class="custom-icon-xs custom-flex-shrink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                                                </svg>
                                                <span class="custom-truncate">Rp {{ number_format($parent['total_credit'], 0) }}</span>
                                            </div>
                                        </div>

                                        <div class="custom-col-md-2">
                                            <div class="custom-text-xs-gray custom-mb-1">Balance</div>
                                            <div class="custom-balance-badge {{ $parent['balance'] >= 0 ? 'custom-bg-blue' : 'custom-bg-orange' }}">
                                                <svg class="custom-icon-xs custom-mr-1 custom-flex-shrink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                                </svg>
                                                <span class="custom-truncate">Rp {{ number_format($parent['balance'], 0) }}</span>
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
                            class="custom-bg-gradient custom-border-t"
                        >
                            @if (!empty($parent['children']))
                                <div class="custom-px-6 custom-py-4 custom-space-y">
                                    @foreach ($parent['children'] as $childIndex => $child)
                                        <div x-data="{ childOpen: false }" class="custom-child-card">
                                            {{-- Child COA Header --}}
                                            <div
                                                @click="childOpen = !childOpen"
                                                class="custom-child-header"
                                            >
                                                <div class="custom-flex-space">
                                                    {{-- Dropdown Icon --}}
                                                    <div class="custom-flex-shrink">
                                                        <div class="custom-dropdown-icon-sm" :class="{ 'custom-rotate-90': childOpen }">
                                                            <svg class="custom-icon-xs custom-text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    {{-- Child COA Info --}}
                                                    <div class="custom-flex-1">
                                                        <div class="custom-grid-responsive">
                                                            <div class="custom-col-md-1">
                                                                <span class="custom-code-badge-sm">
                                                                    {{ $child['code'] }}
                                                                </span>
                                                            </div>

                                                            <div class="custom-col-md-5">
                                                                <div class="custom-child-name">
                                                                    {{ $child['name'] }}
                                                                </div>
                                                            </div>

                                                            <div class="custom-col-md-2">
                                                                <div class="custom-text-xs-green">
                                                                    Rp {{ number_format($child['total_debit'], 0) }}
                                                                </div>
                                                            </div>

                                                            <div class="custom-col-md-2">
                                                                <div class="custom-text-xs-red">
                                                                    Rp {{ number_format($child['total_credit'], 0) }}
                                                                </div>
                                                            </div>

                                                            <div class="custom-col-md-2">
                                                                <div class="custom-balance-badge-sm {{ $child['balance'] >= 0 ? 'custom-bg-blue' : 'custom-bg-orange' }}">
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
                                                class="custom-bg-gradient-2 custom-border-t"
                                            >
                                                @if (!empty($child['entries']))
                                                    <div class="custom-p-4">
                                                        <div class="custom-overflow-x custom-rounded custom-shadow-inner">
                                                            <table class="custom-table">
                                                                <thead class="custom-table-header">
                                                                    <tr>
                                                                        <th class="custom-th">
                                                                            <div class="custom-flex-gap">
                                                                                <svg class="custom-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                                </svg>
                                                                                Date
                                                                            </div>
                                                                        </th>
                                                                        <th class="custom-th">Reference</th>
                                                                        <th class="custom-th">Description</th>
                                                                        <th class="custom-th custom-text-right custom-text-green">Debit</th>
                                                                        <th class="custom-th custom-text-right custom-text-red">Credit</th>
                                                                        <th class="custom-th custom-text-center">Type</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody class="custom-table-body">
                                                                    @foreach ($child['entries'] as $entry)
                                                                        <tr class="custom-table-row custom-cursor-pointer" onclick="window.location.href='{{ \App\Filament\Resources\JournalEntryResource::getUrl('view', ['record' => $entry['id']]) }}'">
                                                                            <td class="custom-td custom-whitespace-nowrap">
                                                                                <span class="custom-date-badge">
                                                                                    {{ \Carbon\Carbon::parse($entry['created_at'] ?? $entry['date'])->setTimezone(config('app.timezone'))->format('d M Y H:i') }}
                                                                                </span>
                                                                            </td>
                                                                            <td class="custom-td custom-font-mono">{{ $entry['reference'] ?? '-' }}</td>
                                                                            <td class="custom-td">{{ $entry['description'] ?? '-' }}</td>
                                                                            <td class="custom-td custom-text-right custom-font-bold custom-text-green">
                                                                                {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0) : '-' }}
                                                                            </td>
                                                                            <td class="custom-td custom-text-right custom-font-bold custom-text-red">
                                                                                {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0) : '-' }}
                                                                            </td>
                                                                            <td class="custom-td custom-text-center">
                                                                                <span class="custom-type-badge-sm
                                                                                    {{ $entry['journal_type'] === 'sales' ? 'custom-bg-green' : '' }}
                                                                                    {{ $entry['journal_type'] === 'purchase' ? 'custom-bg-yellow' : '' }}
                                                                                    {{ $entry['journal_type'] === 'depreciation' ? 'custom-bg-blue' : '' }}
                                                                                    {{ $entry['journal_type'] === 'manual' ? 'custom-bg-purple' : '' }}
                                                                                    {{ !in_array($entry['journal_type'], ['sales', 'purchase', 'depreciation', 'manual']) ? 'custom-bg-gray' : '' }}
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
                                <div class="custom-px-6 custom-py-4 custom-bg-gradient-3 custom-border-t">
                                    <h4 class="custom-h4">
                                        <svg class="custom-icon custom-text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        Direct Entries
                                    </h4>
                                    <div class="custom-overflow-x custom-rounded custom-shadow-inner">
                                        <table class="custom-table">
                                            <thead class="custom-table-header">
                                                <tr>
                                                    <th class="custom-th">Date</th>
                                                    <th class="custom-th">Reference</th>
                                                    <th class="custom-th">Description</th>
                                                    <th class="custom-th custom-text-right custom-text-green">Debit</th>
                                                    <th class="custom-th custom-text-right custom-text-red">Credit</th>
                                                    <th class="custom-th custom-text-center">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody class="custom-table-body">
                                                @foreach ($parent['entries'] as $entry)
                                                    <tr class="custom-table-row custom-cursor-pointer" onclick="window.location.href='{{ \App\Filament\Resources\JournalEntryResource::getUrl('view', ['record' => $entry['id']]) }}'">
                                                        <td class="custom-td custom-whitespace-nowrap">
                                                            <span class="custom-date-badge">
                                                                {{ \Carbon\Carbon::parse($entry['created_at'] ?? $entry['date'])->setTimezone(config('app.timezone'))->format('d M Y H:i') }}
                                                            </span>
                                                        </td>
                                                        <td class="custom-td custom-font-mono">{{ $entry['reference'] ?? '-' }}</td>
                                                        <td class="custom-td">{{ $entry['description'] ?? '-' }}</td>
                                                        <td class="custom-td custom-text-right custom-font-bold custom-text-green">
                                                            {{ $entry['debit'] > 0 ? 'Rp ' . number_format($entry['debit'], 0) : '-' }}
                                                        </td>
                                                        <td class="custom-td custom-text-right custom-font-bold custom-text-red">
                                                            {{ $entry['credit'] > 0 ? 'Rp ' . number_format($entry['credit'], 0) : '-' }}
                                                        </td>
                                                        <td class="custom-td custom-text-center">
                                                            <span class="custom-type-badge-sm
                                                                {{ $entry['journal_type'] === 'sales' ? 'custom-bg-green' : '' }}
                                                                {{ $entry['journal_type'] === 'purchase' ? 'custom-bg-yellow' : '' }}
                                                                {{ $entry['journal_type'] === 'depreciation' ? 'custom-bg-blue' : '' }}
                                                                {{ $entry['journal_type'] === 'manual' ? 'custom-bg-purple' : '' }}
                                                                {{ !in_array($entry['journal_type'], ['sales', 'purchase', 'depreciation', 'manual']) ? 'custom-bg-gray' : '' }}
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
                    <div class="custom-px-6 custom-py-16 custom-text-center">
                        <div class="custom-empty-icon">
                            <svg class="custom-icon-xl custom-text-gray" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h3 class="custom-h3">No journal entries found</h3>
                        <p class="custom-empty-text">
                            Try adjusting your filters or date range to see journal entries.
                        </p>
                        <x-filament::button wire:click="$set('data', [])" color="primary" size="sm">
                            <svg class="custom-icon-xs custom-mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        /* Layout and Spacing */
        .custom-space-y { margin-bottom: 1.5rem; }
        .custom-space-y > * + * { margin-top: 1.5rem; }

        /* Header Styles */
        .custom-header-bg {
            background: linear-gradient(to right, #1e40af, #1e3a8a);
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            color: white;
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: box-shadow 0.2s ease;
        }
        .custom-header-bg:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }

        .custom-flex-between { display: flex; justify-content: space-between; align-items: center; }
        .custom-title {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .custom-subtitle { color: rgba(255, 255, 255, 0.8); margin-top: 0.25rem; }
        .custom-hidden-md { display: none; }
        @media (min-width: 768px) { .custom-hidden-md { display: block; } }
        .custom-large-icon { width: 6rem; height: 6rem; opacity: 0.2; }

        /* Card Styles */
        .custom-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .custom-card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .custom-card-header {
            background: linear-gradient(to right, #f9fafb, #f3f4f6);
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .custom-card-title {
            font-size: 1.125rem;
            font-weight: bold;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .custom-card-text { color: #6b7280; margin-top: 0.5rem; }

        /* Statistics Cards */
        .custom-grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .custom-stat-card {
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            transition: box-shadow 0.2s ease;
        }
        .custom-stat-card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .custom-stat-blue { background: linear-gradient(to bottom right, #dbeafe, #bfdbfe); border-color: #93c5fd; }
        .custom-stat-green { background: linear-gradient(to bottom right, #dcfce7, #bbf7d0); border-color: #86efac; }
        .custom-stat-red { background: linear-gradient(to bottom right, #fee2e2, #fecaca); border-color: #fca5a5; }
        .custom-stat-purple { background: linear-gradient(to bottom right, #faf5ff, #f3e8ff); border-color: #c4b5fd; }
        .custom-stat-neutral { background: linear-gradient(to bottom right, #f3f4f6, #e5e7eb); border-color: #d1d5db; }
        .custom-stat-content { flex: 1; }
        .custom-stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        .custom-stat-value { font-size: 2.25rem; font-weight: bold; }
        .custom-stat-icon { margin-left: 1rem; }
        .custom-bg-blue { background: #3b82f6; }
        .custom-bg-green { background: #10b981; }
        .custom-bg-red { background: #ef4444; }
        .custom-bg-purple { background: #8b5cf6; }
        .custom-bg-neutral { background: #d1d5db; }

        /* Table Styles */
        .custom-hidden-lg { display: none; }
        @media (min-width: 1024px) { .custom-hidden-lg { display: block; } }
        .custom-table-section-header {
            background: #f3f4f6;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .custom-grid-12 {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #374151;
        }
        .custom-col-1 { grid-column: span 1; }
        .custom-col-4 { grid-column: span 4; }
        .custom-col-2 { grid-column: span 2; }
        .custom-text-right { text-align: right; }

        /* Content Areas */
        .custom-divide > * + * { border-top: 1px solid #e5e7eb; }
        .custom-transition { transition: all 0.2s ease; }
        .custom-hover-bg:hover { background: linear-gradient(to right, rgba(59, 130, 246, 0.05), transparent); }
        .custom-p-4 { padding: 1rem; }
        .custom-cursor-pointer { cursor: pointer; }
        .custom-flex-space { display: flex; align-items: center; gap: 1rem; }
        .custom-flex-shrink { flex-shrink: 0; }
        .custom-dropdown-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        .custom-rotate-90 { transform: rotate(90deg); }
        .custom-flex-1 { flex: 1; }
        .custom-grid-responsive { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; align-items: center; }
        .custom-col-md-1 { grid-column: span 1; }
        .custom-col-md-5 { grid-column: span 5; }
        .custom-col-md-2 { grid-column: span 2; }

        /* Badges and Labels */
        .custom-code-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: bold;
            font-family: ui-monospace, monospace;
            background: #f3f4f6;
            color: #1f2937;
        }
        .custom-account-name { font-weight: bold; color: #111827; font-size: 1.125rem; }
        @media (min-width: 768px) { .custom-account-name { font-size: 1.25rem; } }
        .custom-flex-wrap { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
        .custom-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            background: #dbeafe;
            color: #1e40af;
        }
        .custom-bg-blue { background: #dbeafe; color: #1e40af; }
        .custom-bg-purple { background: #faf5ff; color: #7c3aed; }
        .custom-text-xs-gray { font-size: 0.75rem; color: #6b7280; margin-bottom: 0.25rem; }
        .custom-text-sm-bold { font-size: 0.875rem; font-weight: bold; }
        .custom-flex-gap { display: flex; align-items: center; gap: 0.5rem; }
        .custom-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .custom-balance-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: bold;
        }
        .custom-bg-orange { background: #fed7aa; color: #9a3412; }

        /* Child Elements */
        .custom-bg-gradient {
            background: linear-gradient(to bottom, #f9fafb, white);
            border-top: 1px solid #e5e7eb;
        }
        .custom-px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .custom-py-4 { padding-top: 1rem; padding-bottom: 1rem; }
        .custom-space-y > * + * { margin-top: 0.75rem; }
        .custom-child-card {
            border-left: 4px solid #3b82f6;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }
        .custom-child-card:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .custom-child-header {
            padding: 1.25rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .custom-child-header:hover { background: linear-gradient(to right, rgba(59, 130, 246, 0.05), transparent); }
        .custom-dropdown-icon-sm {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            background: #dbeafe;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
        }
        .custom-child-name { font-size: 0.875rem; font-weight: 600; color: #111827; }
        .custom-text-xs-green { font-size: 0.75rem; color: #047857; font-weight: 600; }
        .custom-text-xs-red { font-size: 0.75rem; color: #dc2626; font-weight: 600; }
        .custom-balance-badge-sm {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: bold;
        }

        /* Table Elements */
        .custom-bg-gradient-2 {
            background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
            border-top: 1px solid #e5e7eb;
        }
        .custom-p-4 { padding: 1rem; }
        .custom-overflow-x { overflow-x: auto; border-radius: 0.5rem; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06); }
        .custom-table { display: table; width: 100%; font-size: 0.75rem; border-collapse: collapse; }
        .custom-table-header { background: linear-gradient(to right, #e5e7eb, #d1d5db); }
        .custom-th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        .custom-text-right { text-align: right; }
        .custom-text-center { text-align: center; }
        .custom-text-green { color: #047857; }
        .custom-text-red { color: #dc2626; }
        .custom-table-body { background: white; }
        .custom-table-row:hover { background: rgba(59, 130, 246, 0.05); transition: background 0.2s ease; }
        .custom-td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .custom-whitespace-nowrap { white-space: nowrap; }
        .custom-date-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            background: #f3f4f6;
            color: #374151;
            font-weight: 600;
        }
        .custom-font-mono { font-family: ui-monospace, monospace; }
        .custom-font-bold { font-weight: bold; }
        .custom-type-badge-sm {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .custom-bg-green { background: #dcfce7; color: #166534; }
        .custom-bg-yellow { background: #fef3c7; color: #92400e; }
        .custom-bg-blue { background: #dbeafe; color: #1e40af; }
        .custom-bg-purple { background: #faf5ff; color: #7c3aed; }
        .custom-bg-gray { background: #f3f4f6; color: #374151; }

        /* Force table elements to behave like tables in case global/reset styles interfere */
        .custom-table thead { display: table-header-group; }
        .custom-table tbody { display: table-row-group; }
        .custom-table tr { display: table-row; }
        .custom-table th, .custom-table td { display: table-cell; vertical-align: middle; }

        /* Restore header background for inner tables (thead uses class custom-table-header in table markup) */
        .custom-table thead.custom-table-header { background: linear-gradient(to right, #e5e7eb, #d1d5db); }

        /* Parent Direct Entries */
        .custom-bg-gradient-3 {
            background: linear-gradient(to bottom, #f3f4f6, #f9fafb);
            border-top: 1px solid #e5e7eb;
        }
        .custom-h4 {
            font-size: 0.875rem;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Empty State */
        .custom-text-center { text-align: center; }
        .custom-px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
        .custom-py-16 { padding-top: 4rem; padding-bottom: 4rem; }
        .custom-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 5rem;
            height: 5rem;
            border-radius: 50%;
            background: #f3f4f6;
            margin-bottom: 1rem;
        }
        .custom-icon-xl { width: 2.5rem; height: 2.5rem; color: #9ca3af; }
        .custom-h3 { font-size: 1.125rem; font-weight: 600; color: #111827; margin-bottom: 0.5rem; }
        .custom-empty-text { font-size: 0.875rem; color: #6b7280; margin-bottom: 1.5rem; }

        /* Icons and Colors */
        .custom-icon { width: 2rem; height: 2rem; }
        .custom-icon-sm { width: 1.25rem; height: 1.25rem; }
        .custom-icon-xs { width: 1rem; height: 1rem; }
        .custom-text-primary { color: #3b82f6; }
        .custom-text-white { color: white; }
        .custom-text-gray { color: #9ca3af; }
        .custom-text-green { color: #10b981; }
        .custom-text-red { color: #ef4444; }
        .custom-text-neutral { color: #374151; }
        .custom-bg-orange { background: #fed7aa; color: #9a3412; }

        /* Utility Classes */
        .custom-rounded { border-radius: 0.75rem; }
        .custom-shadow { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .custom-border { border: 1px solid #e5e7eb; }
        .custom-hover-shadow:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .custom-p-6 { padding: 1.5rem; }
        .custom-mt-4 { margin-top: 1rem; }
        .custom-mt-2 { margin-top: 0.5rem; }
        .custom-mt-1 { margin-top: 0.25rem; }
        .custom-mb-1 { margin-bottom: 0.25rem; }
        .custom-mr-1 { margin-right: 0.25rem; }
        .custom-text-sm-gray { font-size: 0.875rem; color: #6b7280; }
        .custom-text-lg-bold { font-size: 1.125rem; font-weight: bold; }
        .custom-currency { font-size: 1rem; }
        .custom-code-badge-sm {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: ui-monospace, monospace;
            background: #f3f4f6;
            color: #374151;
        }

        /* Alpine.js */
        [x-cloak] { display: none !important; }

        /* Custom scrollbar */
        .custom-overflow-x::-webkit-scrollbar { height: 8px; }
        .custom-overflow-x::-webkit-scrollbar-track { background: #f9fafb; }
        .custom-overflow-x::-webkit-scrollbar-thumb {
            background: #9ca3af;
            border-radius: 4px;
        }
        .custom-overflow-x::-webkit-scrollbar-thumb:hover { background: #6b7280; }

        /* Print styles */
        @media print {
            .no-print { display: none !important; }
            [x-data] { display: block !important; }
        }
    </style>
</x-filament-panels::page>
