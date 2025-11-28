<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Tab Navigation -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="p-1 bg-gray-50 dark:bg-gray-900/50">
                <nav class="flex space-x-1" aria-label="Tabs">
                    <a href="?tab=ar" 
                       class="flex-1 flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 {{ $this->activeTab === 'ar' ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $this->activeTab === 'ar' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-600' }}">
                                <svg class="w-4 h-4 {{ $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Account Receivable</div>
                                <div class="text-xs opacity-75">Customer invoices</div>
                            </div>
                            @php 
                                try {
                                    $arCount = \App\Models\AccountReceivable::where('status', 'Belum Lunas')->count(); 
                                } catch (\Exception $e) {
                                    $arCount = 0;
                                }
                            @endphp
                            @if($arCount > 0)
                                <span class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $arCount }}</span>
                            @endif
                        </div>
                    </a>
                    <a href="?tab=ap" 
                       class="flex-1 flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg transition-all duration-200 {{ $this->activeTab === 'ap' ? 'bg-white dark:bg-gray-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center {{ $this->activeTab === 'ap' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-100 dark:bg-gray-600' }}">
                                <svg class="w-4 h-4 {{ $this->activeTab === 'ap' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Account Payable</div>
                                <div class="text-xs opacity-75">Supplier invoices</div>
                            </div>
                            @php 
                                try {
                                    $apCount = \App\Models\AccountPayable::where('status', 'Belum Lunas')->count(); 
                                } catch (\Exception $e) {
                                    $apCount = 0;
                                }
                            @endphp
                            @if($apCount > 0)
                                <span class="ml-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">{{ $apCount }}</span>
                            @endif
                        </div>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                try {
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
                        $entityType = 'Receivable';
                        $entityColor = 'text-green-600 dark:text-green-400';
                        $entityBg = 'bg-green-100 dark:bg-green-900/30';
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
                        $entityType = 'Payable';
                        $entityColor = 'text-blue-600 dark:text-blue-400';
                        $entityBg = 'bg-blue-100 dark:bg-blue-900/30';
                    }
                } catch (\Exception $e) {
                    $stats = (object) ['total_amount' => 0, 'paid_amount' => 0, 'outstanding_amount' => 0, 'total_count' => 0, 'outstanding_count' => 0];
                    $overdue = 0;
                    $entityType = $this->activeTab === 'ar' ? 'Receivable' : 'Payable';
                    $entityColor = $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400';
                    $entityBg = $this->activeTab === 'ar' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-blue-100 dark:bg-blue-900/30';
                }
            @endphp
            
            <!-- Total Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 {{ $entityBg }} rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 {{ $entityColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total {{ $entityType }}</p>
                        <p class="text-2xl font-bold {{ $entityColor }}">Rp {{ number_format($stats->total_amount ?? 0) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $stats->total_count ?? 0 }} invoices</p>
                    </div>
                </div>
            </div>

            <!-- Paid Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid Amount</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">Rp {{ number_format($stats->paid_amount ?? 0) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ ($stats->total_count ?? 0) - ($stats->outstanding_count ?? 0) }} completed</p>
                    </div>
                </div>
            </div>

            <!-- Outstanding Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding</p>
                        <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">Rp {{ number_format($stats->outstanding_amount ?? 0) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ $stats->outstanding_count ?? 0 }} pending</p>
                    </div>
                </div>
            </div>

            <!-- Overdue Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 p-6 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-4 flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">Rp {{ number_format($overdue) }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Past due amount</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <div class="w-8 h-8 {{ $entityBg }} rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-4 h-4 {{ $entityColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $this->activeTab === 'ar' ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' }}"></path>
                        </svg>
                    </div>
                    {{ $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable' }} Details
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 ml-11">
                    {{ $this->activeTab === 'ar' ? 'Manage customer invoices and payments' : 'Manage supplier invoices and payments' }}
                </p>
            </div>
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                    <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg flex items-center justify-center mr-3">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    Quick Actions
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Sync Button -->
                    <button class="group flex items-center p-4 bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 text-white rounded-lg transition-all duration-200 transform hover:-translate-y-1 hover:shadow-lg"
                            onclick="window.location.href='{{ url()->current() }}?sync=all'">
                        <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center mr-3 group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-5 h-5 group-hover:rotate-180 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold">Sync All Records</div>
                            <div class="text-sm opacity-90">Update data from source</div>
                        </div>
                    </button>
                    
                    <!-- Add Payment Button -->
                    <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'customer-receipts' : 'vendor-payments') . '.create') }}" 
                       class="group flex items-center p-4 bg-white dark:bg-gray-700 border-2 border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 text-gray-700 dark:text-gray-200 rounded-lg transition-all duration-200 transform hover:-translate-y-1 hover:shadow-lg">
                        <div class="w-10 h-10 bg-gray-100 dark:bg-gray-600 rounded-lg flex items-center justify-center mr-3 group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold">Add {{ $this->activeTab === 'ar' ? 'Customer' : 'Vendor' }} Payment</div>
                            <div class="text-sm opacity-75">Create new payment record</div>
                        </div>
                    </a>
                    
                    <!-- View Overdue Button -->
                    <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'account-receivables' : 'account-payables') . '.index', ['tableFilters[overdue][isActive]' => true]) }}" 
                       class="group flex items-center p-4 bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-600 hover:border-red-400 dark:hover:border-red-500 text-red-700 dark:text-red-300 rounded-lg transition-all duration-200 transform hover:-translate-y-1 hover:shadow-lg">
                        <div class="w-10 h-10 bg-red-100 dark:bg-red-900/50 rounded-lg flex items-center justify-center mr-3 group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-semibold">View Overdue Items</div>
                            <div class="text-sm opacity-75">Check past due invoices</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
