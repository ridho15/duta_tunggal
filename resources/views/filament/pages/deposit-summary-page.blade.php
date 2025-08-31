<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            @php
                $totalDeposits = \App\Models\Deposit::sum('amount');
                $totalUsed = \App\Models\Deposit::sum('used_amount');
                $totalRemaining = \App\Models\Deposit::sum('remaining_amount');
                $customerDeposits = \App\Models\Deposit::where('from_model_type', 'App\Models\Customer')->sum('remaining_amount');
                $supplierDeposits = \App\Models\Deposit::where('from_model_type', 'App\Models\Supplier')->sum('remaining_amount');
            @endphp
            
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Deposits</dt>
                                <dd class="text-lg font-medium text-gray-900">Rp {{ number_format($totalDeposits) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Used</dt>
                                <dd class="text-lg font-medium text-gray-900">Rp {{ number_format($totalUsed) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Available Balance</dt>
                                <dd class="text-lg font-medium text-green-600">Rp {{ number_format($totalRemaining) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Active Entities</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ \App\Models\Deposit::distinct('from_model_id', 'from_model_type')->count() }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Breakdown -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Customer Deposits</h3>
                    <div class="text-2xl font-bold text-green-600">Rp {{ number_format($customerDeposits) }}</div>
                    <p class="text-sm text-gray-500">Total available from customers</p>
                </div>
            </div>

            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Supplier Deposits</h3>
                    <div class="text-2xl font-bold text-blue-600">Rp {{ number_format($supplierDeposits) }}</div>
                    <p class="text-sm text-gray-500">Total available from suppliers</p>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="overflow-hidden bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Deposit Details by Entity</h3>
                <p class="text-sm text-gray-500">Summary of all deposits grouped by customer and supplier</p>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>
    </div>
</x-filament-panels::page>
