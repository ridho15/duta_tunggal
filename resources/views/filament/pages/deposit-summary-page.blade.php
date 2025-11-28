<x-filament-panels::page>
    <style>
        :root {
            --widget-bg-blue: #2563eb;
            --widget-bg-red: #dc2626;
            --widget-bg-green: #16a34a;
            --widget-bg-purple: #7c3aed;
            --card-bg: #fff;
        }
        .dark {
            --widget-bg-blue: #3b82f6;
            --widget-bg-red: #ef4444;
            --widget-bg-green: #22c55e;
            --widget-bg-purple: #a78bfa;
            --card-bg: #18181b;
        }
    </style>
    <div class="space-y-6">
        <!-- Summary Stats Cards -->
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
            @php
                $totalDeposits = \App\Models\Deposit::sum('amount');
                $totalUsed = \App\Models\Deposit::sum('used_amount');
                $totalRemaining = \App\Models\Deposit::sum('remaining_amount');
                $customerDeposits = \App\Models\Deposit::where('from_model_type', 'App\Models\Customer')->sum('remaining_amount');
                $supplierDeposits = \App\Models\Deposit::where('from_model_type', 'App\Models\Supplier')->sum('remaining_amount');
                $activeEntities = \App\Models\Deposit::distinct('from_model_id', 'from_model_type')->count();
            @endphp

            <!-- Total Deposits Card -->
            <div class="relative overflow-hidden rounded-xl p-6 text-white shadow-lg" style="background-color: var(--widget-bg-blue);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-blue);">Total Deposits</p>
                        <p class="text-2xl font-bold text-white">Rp {{ number_format($totalDeposits, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-full p-3" style="background-color: var(--widget-bg-blue);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-20" style="background-color: var(--widget-bg-blue);"></div>
            </div>

            <!-- Total Used Card -->
            <div class="relative overflow-hidden rounded-xl p-6 text-white shadow-lg" style="background-color: var(--widget-bg-red);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-red);">Total Used</p>
                        <p class="text-2xl font-bold text-white">Rp {{ number_format($totalUsed, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-full p-3" style="background-color: var(--widget-bg-red);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-20" style="background-color: var(--widget-bg-red);"></div>
            </div>

            <!-- Available Balance Card -->
            <div class="relative overflow-hidden rounded-xl p-6 text-white shadow-lg" style="background-color: var(--widget-bg-green);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-green);">Available Balance</p>
                        <p class="text-2xl font-bold text-white">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</p>
                    </div>
                    <div class="rounded-full p-3" style="background-color: var(--widget-bg-green);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-20" style="background-color: var(--widget-bg-green);"></div>
            </div>

            <!-- Active Entities Card -->
            <div class="relative overflow-hidden rounded-xl p-6 text-white shadow-lg" style="background-color: var(--widget-bg-purple);">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium" style="color: var(--text-purple);">Active Entities</p>
                        <p class="text-2xl font-bold text-white">{{ $activeEntities }}</p>
                        <p class="text-xs" style="color: var(--text-purple-secondary);">Customers & Suppliers</p>
                    </div>
                    <div class="rounded-full p-3" style="background-color: var(--widget-bg-purple);">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full opacity-20" style="background-color: var(--widget-bg-purple);"></div>
            </div>
        </div>

        <!-- Balance Breakdown -->
        <div class="grid gap-6 md:grid-cols-2">
            <!-- Customer Deposits -->
            <div class="rounded-xl p-6 shadow-sm ring-1" style="background-color: var(--card-bg); border-color: var(--bg-gray);">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold" style="color: var(--text-gray-dark);">Customer Deposits</h3>
                        <p class="text-sm mt-1" style="color: var(--text-gray);">Available balance from customers</p>
                    </div>
                    <div class="rounded-full p-3" style="background-color: var(--bg-green-light);">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-green-main);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-3xl font-bold" style="color: var(--text-green-accent);">Rp {{ number_format($customerDeposits, 0, ',', '.') }}</p>
                    <div class="mt-2 flex items-center text-sm">
                        <span style="color: var(--text-gray);">of total</span>
                        <span class="ml-1 font-medium" style="color: var(--text-gray-dark);">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span style="color: var(--text-gray-secondary);">Utilization Rate</span>
                        <span class="font-medium">{{ $totalRemaining > 0 ? round(($customerDeposits / $totalRemaining) * 100, 1) : 0 }}%</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full" style="background-color: var(--bg-gray);">
                        <div class="h-2 rounded-full" style="background-color: var(--bg-green-bar); width: {{ $totalRemaining > 0 ? ($customerDeposits / $totalRemaining) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>

            <!-- Supplier Deposits -->
            <div class="rounded-xl p-6 shadow-sm ring-1 ring-gray-200" style="background-color: var(--card-bg);">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Supplier Deposits</h3>
                        <p class="text-sm text-gray-500 mt-1">Available balance from suppliers</p>
                    </div>
                    <div class="rounded-full bg-blue-50 p-3">
                        <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-3xl font-bold text-blue-600">Rp {{ number_format($supplierDeposits, 0, ',', '.') }}</p>
                    <div class="mt-2 flex items-center text-sm">
                        <span class="text-gray-500">of total</span>
                        <span class="ml-1 font-medium text-gray-900">Rp {{ number_format($totalRemaining, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Utilization Rate</span>
                        <span class="font-medium">{{ $totalRemaining > 0 ? round(($supplierDeposits / $totalRemaining) * 100, 1) : 0 }}%</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-gray-200">
                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ $totalRemaining > 0 ? ($supplierDeposits / $totalRemaining) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Main Table -->
    <div class="rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden" style="background-color: var(--card-bg);">
            <div class="border-b border-gray-200 bg-gray-50/50 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Deposit Details by Entity</h3>
                        <p class="text-sm text-gray-600 mt-1">Comprehensive summary of all deposits grouped by customer and supplier</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                            {{ $activeEntities }} Active Entities
                        </span>
                    </div>
                </div>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
