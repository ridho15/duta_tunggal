<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header Cards with AR/AP Summary -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            @php
                if ($this->activeTab === 'ar') {
                    $stats = \App\Models\AccountReceivable::selectRaw('
                        SUM(total) as total_amount,
                        SUM(paid) as paid_amount,
                        SUM(remaining) as outstanding_amount,
                        COUNT(*) as total_count,
                        COUNT(CASE WHEN status = "Belum Lunas" THEN 1 END) as outstanding_count
                    ')->first();
                    $overdue = \App\Models\AccountReceivable::whereHas('invoice', function ($query) {
                        $query->where('due_date', '<', now());
                    })->where('status', 'Belum Lunas')->sum('remaining');
                } else {
                    $stats = \App\Models\AccountPayable::selectRaw('
                        SUM(total) as total_amount,
                        SUM(paid) as paid_amount,
                        SUM(remaining) as outstanding_amount,
                        COUNT(*) as total_count,
                        COUNT(CASE WHEN status = "Belum Lunas" THEN 1 END) as outstanding_count
                    ')->first();
                    $overdue = \App\Models\AccountPayable::whereHas('invoice', function ($query) {
                        $query->where('due_date', '<', now());
                    })->where('status', 'Belum Lunas')->sum('remaining');
                }
                
                $entityType = $this->activeTab === 'ar' ? 'Receivable' : 'Payable';
                $entityIcon = $this->activeTab === 'ar' ? '📈' : '📉';
                $entityColor = $this->activeTab === 'ar' ? 'text-green-600' : 'text-blue-600';
            @endphp
            
            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 text-2xl">{{ $entityIcon }}</div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total {{ $entityType }}</dt>
                                <dd class="text-lg font-medium {{ $entityColor }}">Rp {{ number_format($stats->total_amount ?? 0) }}</dd>
                                <dd class="text-xs text-gray-400">{{ $stats->total_count ?? 0 }} invoices</dd>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Paid Amount</dt>
                                <dd class="text-lg font-medium text-green-600">Rp {{ number_format($stats->paid_amount ?? 0) }}</dd>
                                <dd class="text-xs text-gray-400">{{ ($stats->total_count ?? 0) - ($stats->outstanding_count ?? 0) }} completed</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden bg-white rounded-lg shadow">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Outstanding</dt>
                                <dd class="text-lg font-medium text-yellow-600">Rp {{ number_format($stats->outstanding_amount ?? 0) }}</dd>
                                <dd class="text-xs text-gray-400">{{ $stats->outstanding_count ?? 0 }} pending</dd>
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Overdue</dt>
                                <dd class="text-lg font-medium text-red-600">Rp {{ number_format($overdue) }}</dd>
                                <dd class="text-xs text-gray-400">Past due amount</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white shadow rounded-lg">
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <a href="?tab=ar" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm 
                              {{ $this->activeTab === 'ar' ? 'border-indigo-500 text-indigo-600' : '' }}">
                        📈 Account Receivable
                        @php $arCount = \App\Models\AccountReceivable::where('status', 'Belum Lunas')->count(); @endphp
                        @if($arCount > 0)
                            <span class="bg-red-100 text-red-800 ml-2 py-0.5 px-2 rounded-full text-xs">{{ $arCount }}</span>
                        @endif
                    </a>
                    <a href="?tab=ap" 
                       class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm
                              {{ $this->activeTab === 'ap' ? 'border-indigo-500 text-indigo-600' : '' }}">
                        📉 Account Payable  
                        @php $apCount = \App\Models\AccountPayable::where('status', 'Belum Lunas')->count(); @endphp
                        @if($apCount > 0)
                            <span class="bg-red-100 text-red-800 ml-2 py-0.5 px-2 rounded-full text-xs">{{ $apCount }}</span>
                        @endif
                    </a>
                </nav>
            </div>

            <!-- Main Table -->
            <div class="p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable' }} Details
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $this->activeTab === 'ar' ? 'Manage customer invoices and payments' : 'Manage supplier invoices and payments' }}
                    </p>
                </div>
                
                {{ $this->table }}
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white shadow rounded-lg p-6">
            <h4 class="text-md font-medium text-gray-900 mb-4">Quick Actions</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <button onclick="Livewire.dispatch('syncAllRecords')" 
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sync All Records
                </button>
                
                <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'customer-receipts' : 'vendor-payments') . '.create') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add {{ $this->activeTab === 'ar' ? 'Customer Payment' : 'Vendor Payment' }}
                </a>
                
                <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'account-receivables' : 'account-payables') . '.index', ['tableFilters[overdue][isActive]' => true]) }}" 
                   class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    View Overdue Items
                </a>
            </div>
        </div>
    </div>
</x-filament-panels::page>
