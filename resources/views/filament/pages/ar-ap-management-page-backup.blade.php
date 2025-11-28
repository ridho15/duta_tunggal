<x-filament-panels::page>
    <div class="space-y-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable' }} Management
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {{ $this->activeTab === 'ar' ? 'Manage customer invoices and payments' : 'Manage supplier invoices and payments' }}
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg px-4 py-2 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700">
                        <div class="flex items-center space-x-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Live Data</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation - Enhanced Design -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900/50 dark:to-gray-800/50 px-1 py-1">
                <nav class="flex space-x-1" aria-label="Tabs">
                    <a href="?tab=ar" 
                       class="group relative flex-1 flex items-center justify-center px-6 py-4 text-sm font-medium rounded-xl transition-all duration-300 {{ $this->activeTab === 'ar' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-lg ring-1 ring-indigo-200 dark:ring-indigo-700/50' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50' }}">
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg {{ $this->activeTab === 'ar' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-700' }} transition-colors duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 {{ $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Account Receivable</div>
                                <div class="text-xs opacity-75">Customer payments</div>
                            </div>
                            @php 
                                try {
                                    $arCount = \App\Models\AccountReceivable::where('status', 'Belum Lunas')->count(); 
                                } catch (\Exception $e) {
                                    $arCount = 0;
                                }
                            @endphp
                            @if($arCount > 0)
                                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[20px] h-5 flex items-center justify-center">{{ $arCount }}</span>
                            @endif
                        </div>
                    </a>
                    <a href="?tab=ap" 
                       class="group relative flex-1 flex items-center justify-center px-6 py-4 text-sm font-medium rounded-xl transition-all duration-300 {{ $this->activeTab === 'ap' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-lg ring-1 ring-indigo-200 dark:ring-indigo-700/50' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-white/50 dark:hover:bg-gray-700/50' }}">
                        <div class="flex items-center space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg {{ $this->activeTab === 'ap' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-gray-100 dark:bg-gray-700' }} transition-colors duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 {{ $this->activeTab === 'ap' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Account Payable</div>
                                <div class="text-xs opacity-75">Supplier payments</div>
                            </div>
                            @php 
                                try {
                                    $apCount = \App\Models\AccountPayable::where('status', 'Belum Lunas')->count(); 
                                } catch (\Exception $e) {
                                    $apCount = 0;
                                }
                            @endphp
                            @if($apCount > 0)
                                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[20px] h-5 flex items-center justify-center">{{ $apCount }}</span>
                            @endif
                        </div>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Enhanced Header Cards with AR/AP Summary -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
                    $entityColor = $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400';
                    $entityBgColor = $this->activeTab === 'ar' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-blue-50 dark:bg-blue-900/20';
                } catch (\Exception $e) {
                    $stats = (object) ['total_amount' => 0, 'paid_amount' => 0, 'outstanding_amount' => 0, 'total_count' => 0, 'outstanding_count' => 0];
                    $overdue = 0;
                    $entityType = $this->activeTab === 'ar' ? 'Receivable' : 'Payable';
                    $entityColor = $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400';
                    $entityBgColor = $this->activeTab === 'ar' ? 'bg-green-50 dark:bg-green-900/20' : 'bg-blue-50 dark:bg-blue-900/20';
                }
            @endphp
            
            <!-- Total Amount Card -->
            <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 hover:shadow-xl dark:hover:shadow-gray-700/30 hover:ring-gray-300 dark:hover:ring-gray-600 transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <div class="absolute inset-0 {{ $entityBgColor }} opacity-5"></div>
                <div class="relative p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-12 h-12 {{ $entityBgColor }} rounded-xl transition-transform duration-300 group-hover:scale-110">
                                    <svg class="w-6 h-6 {{ $entityColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total {{ $entityType }}</p>
                                    <p class="text-2xl font-bold {{ $entityColor }} mt-1">Rp {{ number_format($stats->total_amount ?? 0) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                {{ $stats->total_count ?? 0 }} invoices
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paid Amount Card -->
            <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 hover:shadow-xl dark:hover:shadow-gray-700/30 hover:ring-gray-300 dark:hover:ring-gray-600 transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <div class="absolute inset-0 bg-green-50 dark:bg-green-900/20 opacity-5"></div>
                <div class="relative p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl transition-transform duration-300 group-hover:scale-110">
                                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Paid Amount</p>
                                    <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">Rp {{ number_format($stats->paid_amount ?? 0) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ ($stats->total_count ?? 0) - ($stats->outstanding_count ?? 0) }} completed
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Card -->
            <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 hover:shadow-xl dark:hover:shadow-gray-700/30 hover:ring-gray-300 dark:hover:ring-gray-600 transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <div class="absolute inset-0 bg-yellow-50 dark:bg-yellow-900/20 opacity-5"></div>
                <div class="relative p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl transition-transform duration-300 group-hover:scale-110">
                                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding</p>
                                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">Rp {{ number_format($stats->outstanding_amount ?? 0) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $stats->outstanding_count ?? 0 }} pending
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overdue Card -->
            <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 hover:shadow-xl dark:hover:shadow-gray-700/30 hover:ring-red-300 dark:hover:ring-red-600 transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                <div class="absolute inset-0 bg-red-50 dark:bg-red-900/20 opacity-5"></div>
                <div class="relative p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex items-center justify-center w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-xl transition-transform duration-300 group-hover:scale-110">
                                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Overdue</p>
                                    <p class="text-2xl font-bold text-red-600 dark:text-red-400 mt-1">Rp {{ number_format($overdue) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                Past due amount
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white dark:bg-gray-800 shadow-sm dark:shadow-gray-700/20 rounded-xl ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <a href="?tab=ar" 
                       class="group border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-all duration-300
                              {{ $this->activeTab === 'ar' ? 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-400' : '' }}">
                        <span class="flex items-center">
                            <span class="text-lg mr-2 transition-transform duration-300 group-hover:scale-110">📈</span>
                            Account Receivable
                        </span>
                        @php 
                            try {
                                $arCount = \App\Models\AccountReceivable::where('status', 'Belum Lunas')->count(); 
                            } catch (\Exception $e) {
                                $arCount = 0;
                            }
                        @endphp
                        @if($arCount > 0)
                            <span class="bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300 ml-2 py-1 px-2 rounded-full text-xs font-semibold shadow-sm">{{ $arCount }}</span>
                        @endif
                    </a>
                    <a href="?tab=ap" 
                       class="group border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-all duration-300
                              {{ $this->activeTab === 'ap' ? 'border-indigo-500 dark:border-indigo-400 text-indigo-600 dark:text-indigo-400' : '' }}">
                        <span class="flex items-center">
                            <span class="text-lg mr-2 transition-transform duration-300 group-hover:scale-110">📉</span>
                            Account Payable
                        </span>
                        @php 
                            try {
                                $apCount = \App\Models\AccountPayable::where('status', 'Belum Lunas')->count(); 
                            } catch (\Exception $e) {
                                $apCount = 0;
                            }
                        @endphp
                        @if($apCount > 0)
                            <span class="bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300 ml-2 py-1 px-2 rounded-full text-xs font-semibold shadow-sm">{{ $apCount }}</span>
                        @endif
                    </a>
                </nav>
            </div>

        <!-- Enhanced Main Table Section -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <!-- Table Header -->
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900/50 dark:to-gray-800/50 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                            <div class="w-8 h-8 {{ $this->activeTab === 'ar' ? 'bg-green-100 dark:bg-green-900/30' : 'bg-blue-100 dark:bg-blue-900/30' }} rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 {{ $this->activeTab === 'ar' ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $this->activeTab === 'ar' ? 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6' : 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6' }}"></path>
                                </svg>
                            </div>
                            {{ $this->activeTab === 'ar' ? 'Account Receivable' : 'Account Payable' }} Details
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 ml-11">
                            {{ $this->activeTab === 'ar' ? 'Manage customer invoices and payment tracking' : 'Manage supplier invoices and payment tracking' }}
                        </p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="bg-white dark:bg-gray-700 rounded-lg px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-gray-600">
                            Real-time data
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Table Content -->
            <div class="p-6">
                {{ $this->table }}
            </div>
        </div>

        <!-- Enhanced Quick Actions -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg dark:shadow-gray-700/20 ring-1 ring-gray-200 dark:ring-gray-700 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900/50 dark:to-gray-800/50 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
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
                    <button class="group relative bg-gradient-to-r from-indigo-600 to-indigo-700 hover:from-indigo-700 hover:to-indigo-800 dark:from-indigo-500 dark:to-indigo-600 dark:hover:from-indigo-600 dark:hover:to-indigo-700 text-white rounded-xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden"
                            onclick="window.location.href='{{ url()->current() }}?sync=all'">
                        <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                        <div class="relative flex items-center justify-center space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-white/20 rounded-lg transition-transform duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 transition-transform duration-300 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Sync All Records</div>
                                <div class="text-xs opacity-90">Update data from source</div>
                            </div>
                        </div>
                    </button>
                    
                    <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'customer-receipts' : 'vendor-payments') . '.create') }}" 
                       class="group relative bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 border-2 border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500 text-gray-700 dark:text-gray-200 rounded-xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                        <div class="relative flex items-center justify-center space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-gray-100 dark:bg-gray-600 rounded-lg transition-transform duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Add {{ $this->activeTab === 'ar' ? 'Customer' : 'Vendor' }} Payment</div>
                                <div class="text-xs opacity-75">Create new payment record</div>
                            </div>
                        </div>
                    </a>
                    
                    <a href="{{ route('filament.admin.resources.' . ($this->activeTab === 'ar' ? 'account-receivables' : 'account-payables') . '.index', ['tableFilters[overdue][isActive]' => true]) }}" 
                       class="group relative bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 border-2 border-red-300 dark:border-red-600 hover:border-red-400 dark:hover:border-red-500 text-red-700 dark:text-red-300 rounded-xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1 overflow-hidden">
                        <div class="relative flex items-center justify-center space-x-3">
                            <div class="flex items-center justify-center w-10 h-10 bg-red-100 dark:bg-red-900/50 rounded-lg transition-transform duration-300 group-hover:scale-110">
                                <svg class="w-5 h-5 transition-transform duration-300 group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.99-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">View Overdue Items</div>
                                <div class="text-xs opacity-75">Check past due invoices</div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
