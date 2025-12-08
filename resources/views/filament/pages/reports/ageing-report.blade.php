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

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer/Supplier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Outstanding</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aging Bucket</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                $records = collect();
                                if ($report_type === 'receivables' || $report_type === 'both') {
                                    $arQuery = \App\Models\AccountReceivable::with(['customer', 'invoice', 'ageingSchedule', 'cabang'])
                                        ->where('remaining', '>', 0);
                                    if ($cabang_id) {
                                        $arQuery->where('cabang_id', $cabang_id);
                                    }
                                    $records = $records->merge($arQuery->get());
                                }

                                if ($report_type === 'payables' || $report_type === 'both') {
                                    $apQuery = \App\Models\AccountPayable::with(['supplier', 'invoice', 'ageingSchedule'])
                                        ->where('remaining', '>', 0);
                                    if ($cabang_id) {
                                        $apQuery->whereHas('invoice', function($q) {
                                            $q->where('cabang_id', $cabang_id);
                                        });
                                    }
                                    $records = $records->merge($apQuery->get());
                                }
                            @endphp

                            @foreach($records as $record)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    @if($record instanceof \App\Models\AccountReceivable)
                                        {{ $record->customer->name ?? '-' }}
                                    @else
                                        {{ $record->supplier->name ?? '-' }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->invoice->no_invoice ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->invoice->invoice_date ? \Carbon\Carbon::parse($record->invoice->invoice_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $record->invoice->due_date ? \Carbon\Carbon::parse($record->invoice->due_date)->format('d/m/Y') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @php
                                        $ageingSchedule = $record->ageingSchedule;
                                        $daysOutstanding = 0;
                                        if ($ageingSchedule && $ageingSchedule->days_outstanding) {
                                            $daysOutstanding = $ageingSchedule->days_outstanding;
                                        } elseif ($record->invoice && $record->invoice->invoice_date) {
                                            $invoiceDate = \Carbon\Carbon::parse($record->invoice->invoice_date);
                                            $daysOutstanding = $invoiceDate->diffInDays(now(), false);
                                        }
                                    @endphp
                                    {{ $daysOutstanding }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    Rp {{ number_format($record->remaining, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $bucket = 'Current';
                                        if ($ageingSchedule && $ageingSchedule->bucket) {
                                            $bucket = $ageingSchedule->bucket;
                                        } elseif ($daysOutstanding > 0) {
                                            if ($daysOutstanding <= 30) $bucket = 'Current';
                                            elseif ($daysOutstanding <= 60) $bucket = '31–60';
                                            elseif ($daysOutstanding <= 90) $bucket = '61–90';
                                            else $bucket = '>90';
                                        }
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($bucket === 'Current') bg-green-100 text-green-800
                                        @elseif($bucket === '31–60') bg-yellow-100 text-yellow-800
                                        @elseif($bucket === '61–90') bg-orange-100 text-orange-800
                                        @else bg-red-100 text-red-800 @endif">
                                        {{ $bucket }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if($record->status === 'Lunas') bg-green-100 text-green-800
                                        @else bg-yellow-100 text-yellow-800 @endif">
                                        {{ $record->status }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-filament::page>