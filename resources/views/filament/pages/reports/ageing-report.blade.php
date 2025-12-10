<x-filament::page>
    <div>
        <form wire:submit.prevent>
            {{ $this->form }}
        </form>

        <div class="mt-6 space-y-6">
            <!-- Aging Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-green-800">Current (0-30 days)</div>
                    <div class="text-2xl font-bold text-green-900 mt-1">{{ $this->getAgingSummary($report_type === 'payables' ? 'payables' : 'receivables', 'Current') }}</div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-yellow-800">31-60 days</div>
                    <div class="text-2xl font-bold text-yellow-900 mt-1">{{ $this->getAgingSummary($report_type === 'payables' ? 'payables' : 'receivables', '31–60') }}</div>
                </div>

                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-orange-800">61-90 days</div>
                    <div class="text-2xl font-bold text-orange-900 mt-1">{{ $this->getAgingSummary($report_type === 'payables' ? 'payables' : 'receivables', '61–90') }}</div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="text-sm font-medium text-red-800">>90 days</div>
                    <div class="text-2xl font-bold text-red-900 mt-1">{{ $this->getAgingSummary($report_type === 'payables' ? 'payables' : 'receivables', '>90') }}</div>
                </div>
            </div>

            <!-- Cash Flow Impact -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-blue-900 mb-4">Cash Flow Impact</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-blue-700">Expected Cash Inflow (30 days)</span>
                            <span class="font-semibold text-blue-900">{{ $this->calculateExpectedCashFlow('receivables', 30) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-blue-700">Expected Cash Outflow (30 days)</span>
                            <span class="font-semibold text-blue-900">{{ $this->calculateExpectedCashFlow('payables', 30) }}</span>
                        </div>
                        <div class="border-t border-blue-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-blue-700">Net Cash Flow</span>
                                <span class="font-bold text-blue-900">
                                    Rp {{ number_format($this->calculateExpectedCashFlow('receivables', 30) - $this->calculateExpectedCashFlow('payables', 30), 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-red-900 mb-4">Risk Assessment</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-red-700">Overdue Receivables</span>
                            <span class="font-semibold text-red-900">
                                @php
                                    $query = \App\Models\AccountReceivable::whereHas('invoice', function($q) {
                                        $q->where('due_date', '<', now());
                                    });
                                    if ($cabang_id) {
                                        $query->where('cabang_id', $cabang_id);
                                    }
                                    $overdueAR = $query->sum('remaining');
                                @endphp
                                Rp {{ number_format($overdueAR, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-red-700">Overdue Payables</span>
                            <span class="font-semibold text-red-900">
                                @php
                                    $query = \App\Models\AccountPayable::whereHas('invoice', function($q) {
                                        $q->where('due_date', '<', now());
                                    });
                                    if ($cabang_id) {
                                        $query->whereHas('invoice', function($q) {
                                            $q->where('cabang_id', $cabang_id);
                                        });
                                    }
                                    $overdueAP = $query->sum('remaining');
                                @endphp
                                Rp {{ number_format($overdueAP, 0, ',', '.') }}
                            </span>
                        </div>
                        <div class="border-t border-red-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-sm font-medium text-red-700">Working Capital Gap</span>
                                <span class="font-bold text-red-900">
                                    Rp {{ number_format($overdueAR - $overdueAP, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Aging Table -->
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Detailed Aging Report -
                        @if($report_type === 'receivables')
                            Account Receivables
                        @elseif($report_type === 'payables')
                            Account Payables
                        @else
                            Both AR & AP
                        @endif
                    </h3>
                </div>

                <div class="p-6">
                    {{ $this->table }}
                </div>
            </div>
        </div>
    </div>
</x-filament::page>