@php
    // Filter accounts based on display options
    $filteredSalesRevenue = $filterAccounts($data['sales_revenue']['accounts']);
    $filteredCogs = $filterAccounts($data['cogs']['accounts']);
    $filteredOperatingExpenses = $filterAccounts($data['operating_expenses']['accounts']);
    $filteredOtherIncome = $filterAccounts($data['other_income']['accounts']);
    $filteredOtherExpense = $filterAccounts($data['other_expense']['accounts']);
    $filteredTaxExpense = $filterAccounts($data['tax_expense']['accounts']);
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm min-w-[800px]">
        <thead class="border-b-2 border-gray-300 dark:border-gray-600">
            <tr>
                <th class="text-left py-2 px-3 font-medium w-32 min-w-[120px]">Kode Akun</th>
                <th class="text-left py-2 px-3 font-medium min-w-[200px]">Nama Akun</th>
                <th class="text-right py-2 px-3 font-medium w-24 min-w-[80px] hidden sm:table-cell">Jumlah Transaksi</th>
                <th class="text-right py-2 px-3 font-medium w-40 min-w-[140px]">Jumlah (Rp)</th>
                <th class="text-right py-2 px-3 font-medium w-24 min-w-[80px]">% dari Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            {{-- 1. PENDAPATAN USAHA (SALES REVENUE) --}}
            <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t-2 border-emerald-600">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-emerald-900 dark:text-emerald-300 tracking-wide uppercase text-xs">PENDAPATAN USAHA (SALES REVENUE)</span>
                </td>
            </tr>
            @if(!$show_only_totals)
                @forelse($filteredSalesRevenue as $account)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                            {{ $account['code'] }}
                        </td>
                        <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                            {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                        </td>
                        <td class="text-right py-1.5 px-3 text-gray-600 hidden sm:table-cell">{{ $account['entries_count'] }}</td>
                        <td class="text-right py-1.5 px-3">
                            <button 
                                wire:click="showAccountDetails({{ $account['id'] }})"
                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                            >
                                {{ number_format($account['balance'], 0, ',', '.') }}
                            </button>
                        </td>
                        <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                            {{ number_format($account['percentage_of_revenue'], 1) }}%
                        </td>
                    </tr>
                @empty
                    @if(!$show_only_totals)
                        <tr>
                            <td colspan="5" class="py-1.5 px-3 text-center text-gray-500 text-sm italic">Tidak ada data pendapatan</td>
                        </tr>
                    @endif
                @endforelse
            @endif
            <tr class="bg-emerald-50 dark:bg-emerald-950/40 font-bold border-t border-emerald-300">
                <td colspan="2" class="py-2 px-3 text-emerald-900 dark:text-emerald-200">TOTAL PENDAPATAN USAHA</td>
                <td class="text-right py-2 px-3 text-emerald-900 dark:text-emerald-200 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-emerald-900 dark:text-emerald-200">{{ number_format($data['sales_revenue']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-emerald-900 dark:text-emerald-200">100.0%</td>
            </tr>

            {{-- 2. HARGA POKOK PENJUALAN (COGS) --}}
            <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t-2 border-gray-400 mt-2">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-gray-900 dark:text-gray-100 tracking-wide uppercase text-xs">HARGA POKOK PENJUALAN (COGS)</span>
                </td>
            </tr>
            @if(!$show_only_totals)
                @forelse($filteredCogs as $account)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                            {{ $account['code'] }}
                        </td>
                        <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                            {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                        </td>
                        <td class="text-right py-1.5 px-3 text-gray-600 hidden sm:table-cell">{{ $account['entries_count'] }}</td>
                        <td class="text-right py-1.5 px-3">
                            <button 
                                wire:click="showAccountDetails({{ $account['id'] }})"
                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                            >
                                {{ number_format($account['balance'], 0, ',', '.') }}
                            </button>
                        </td>
                        <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                            {{ number_format($account['percentage_of_revenue'], 1) }}%
                        </td>
                    </tr>
                @empty
                    @if(!$show_only_totals)
                        <tr>
                            <td colspan="5" class="py-1.5 px-3 text-center text-gray-500 text-sm italic">Tidak ada data COGS</td>
                        </tr>
                    @endif
                @endforelse
            @endif
            <tr class="bg-gray-100 dark:bg-gray-800/80 font-bold border-t border-gray-300">
                <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-200">TOTAL HARGA POKOK PENJUALAN</td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200">{{ number_format($data['cogs']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['cogs']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- LABA KOTOR (GROSS PROFIT) --}}
            <tr class="bg-emerald-50 dark:bg-emerald-900/30 font-bold text-sm border-y-2 border-emerald-500">
                <td colspan="2" class="py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-emerald-900' : 'text-red-800' }} dark:text-emerald-200">
                    LABA KOTOR (GROSS PROFIT)
                </td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200 hidden sm:table-cell"></td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ number_format($data['gross_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ number_format($data['gross_profit_margin'], 1) }}%
                </td>
            </tr>

            {{-- 3. BEBAN OPERASIONAL (OPERATING EXPENSES) --}}
            <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t-2 border-gray-400 mt-2">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-gray-900 dark:text-gray-100 tracking-wide uppercase text-xs">BEBAN OPERASIONAL (OPERATING EXPENSES)</span>
                </td>
            </tr>
            @if(!$show_only_totals)
                @forelse($filteredOperatingExpenses as $account)
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                            {{ $account['code'] }}
                        </td>
                        <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                            {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                        </td>
                        <td class="text-right py-1.5 px-3 text-gray-600 hidden sm:table-cell">{{ $account['entries_count'] }}</td>
                        <td class="text-right py-1.5 px-3">
                            <button 
                                wire:click="showAccountDetails({{ $account['id'] }})"
                                class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                            >
                                {{ number_format($account['balance'], 0, ',', '.') }}
                            </button>
                        </td>
                        <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                            {{ number_format($account['percentage_of_revenue'], 1) }}%
                        </td>
                    </tr>
                @empty
                    @if(!$show_only_totals)
                        <tr>
                            <td colspan="5" class="py-1.5 px-3 text-center text-gray-500 text-sm italic">Tidak ada beban operasional</td>
                        </tr>
                    @endif
                @endforelse
            @endif
            <tr class="bg-gray-100 dark:bg-gray-800/80 font-bold border-t border-gray-300">
                <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-200">TOTAL BEBAN OPERASIONAL</td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200">{{ number_format($data['operating_expenses']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-200">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['operating_expenses']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- LABA OPERASIONAL (OPERATING PROFIT) --}}
            <tr class="bg-emerald-50/80 dark:bg-emerald-900/20 font-bold text-sm border-y-2 border-emerald-400">
                <td colspan="2" class="py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-emerald-900' : 'text-red-800' }} dark:text-emerald-200">
                    LABA OPERASIONAL (OPERATING PROFIT)
                </td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200 hidden sm:table-cell"></td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ number_format($data['operating_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ number_format($data['operating_profit_margin'], 1) }}%
                </td>
            </tr>

            {{-- 4. PENDAPATAN/BEBAN LAIN-LAIN (OTHER INCOME/EXPENSE) --}}
            @if($data['other_income']['total'] > 0 || $data['other_expense']['total'] > 0)
                {{-- Other Income --}}
                @if($data['other_income']['total'] > 0)
                    <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t-2 border-gray-300">
                        <td colspan="5" class="py-2.5 px-3">
                            <span class="text-gray-900 dark:text-gray-100 tracking-wide uppercase text-xs">PENDAPATAN LAIN-LAIN (OTHER INCOME)</span>
                        </td>
                    </tr>
                    @if(!$show_only_totals)
                        @foreach($filteredOtherIncome as $account)
                            <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                                    {{ $account['code'] }}
                                </td>
                                <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                                    {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                                </td>
                                <td class="text-right py-1.5 px-3 text-gray-600">{{ $account['entries_count'] }}</td>
                                <td class="text-right py-1.5 px-3">
                                    <button 
                                        wire:click="showAccountDetails({{ $account['id'] }})"
                                        class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                                    >
                                        {{ number_format($account['balance'], 0, ',', '.') }}
                                    </button>
                                </td>
                                <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                                    {{ number_format($account['percentage_of_revenue'], 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr class="bg-gray-50 dark:bg-gray-800/60 font-semibold border-t border-gray-300">
                        <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-300">TOTAL PENDAPATAN LAIN-LAIN</td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300 hidden sm:table-cell"></td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">{{ number_format($data['other_income']['total'], 0, ',', '.') }}</td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">
                            {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['other_income']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                        </td>
                    </tr>
                @endif

                {{-- Other Expense --}}
                @if($data['other_expense']['total'] > 0)
                    <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t-2 border-gray-300">
                        <td colspan="5" class="py-2.5 px-3">
                            <span class="text-gray-900 dark:text-gray-100 tracking-wide uppercase text-xs">BEBAN LAIN-LAIN (OTHER EXPENSES)</span>
                        </td>
                    </tr>
                    @if(!$show_only_totals)
                        @foreach($filteredOtherExpense as $account)
                            <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                                    {{ $account['code'] }}
                                </td>
                                <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                                    {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                                </td>
                                <td class="text-right py-1.5 px-3 text-gray-600 hidden sm:table-cell">{{ $account['entries_count'] }}</td>
                                <td class="text-right py-1.5 px-3">
                                    <button 
                                        wire:click="showAccountDetails({{ $account['id'] }})"
                                        class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                                    >
                                        {{ number_format($account['balance'], 0, ',', '.') }}
                                    </button>
                                </td>
                                <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                                    {{ number_format($account['percentage_of_revenue'], 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr class="bg-gray-50 dark:bg-gray-800/60 font-semibold border-t border-gray-300">
                        <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-300">TOTAL BEBAN LAIN-LAIN</td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300 hidden sm:table-cell"></td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">{{ number_format($data['other_expense']['total'], 0, ',', '.') }}</td>
                        <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">
                            {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['other_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                        </td>
                    </tr>
                @endif
            @endif

            {{-- LABA SEBELUM PAJAK (PROFIT BEFORE TAX) --}}
            <tr class="bg-emerald-50 dark:bg-emerald-900/30 font-bold text-sm border-y-2 border-emerald-500">
                <td colspan="3" class="py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-emerald-900' : 'text-red-800' }} dark:text-emerald-200">
                    LABA SEBELUM PAJAK (PROFIT BEFORE TAX)
                </td>
                <td class="text-right py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ number_format($data['profit_before_tax'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-emerald-800' : 'text-red-700' }} dark:text-emerald-200">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['profit_before_tax'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- 5. PAJAK PENGHASILAN (TAX EXPENSE) --}}
            @if($data['tax_expense']['total'] > 0)
                <tr class="bg-gray-100 dark:bg-gray-800 font-bold border-t border-gray-300">
                    <td colspan="5" class="py-2.5 px-3">
                        <span class="text-gray-900 dark:text-gray-100 tracking-wide uppercase text-xs">PAJAK PENGHASILAN (TAX EXPENSE)</span>
                    </td>
                </tr>
                @if(!$show_only_totals)
                    @foreach($filteredTaxExpense as $account)
                        <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="py-1.5 px-3 font-mono text-xs {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-8' : '' }}">
                                {{ $account['code'] }}
                            </td>
                            <td class="py-1.5 px-3 {{ isset($account['parent_id']) && $account['parent_id'] ? 'pl-6' : 'font-medium' }}">
                                {{ isset($account['parent_id']) && $account['parent_id'] ? '└─ ' : '' }}{{ $account['name'] }}
                            </td>
                            <td class="text-right py-1.5 px-3 text-gray-600 hidden sm:table-cell">{{ $account['entries_count'] }}</td>
                            <td class="text-right py-1.5 px-3">
                                <button 
                                    wire:click="showAccountDetails({{ $account['id'] }})"
                                    class="text-blue-600 hover:text-blue-800 hover:underline cursor-pointer"
                                >
                                    {{ number_format($account['balance'], 0, ',', '.') }}
                                </button>
                            </td>
                            <td class="text-right py-1.5 px-3 text-xs text-gray-600">
                                {{ number_format($account['percentage_of_revenue'], 1) }}%
                            </td>
                        </tr>
                    @endforeach
                @endif
                <tr class="bg-gray-50 dark:bg-gray-800/60 font-semibold border-t border-gray-300">
                    <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-300">TOTAL PAJAK PENGHASILAN</td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300 hidden sm:table-cell"></td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">{{ number_format($data['tax_expense']['total'], 0, ',', '.') }}</td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">
                        {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['tax_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                    </td>
                </tr>
            @endif

            {{-- LABA BERSIH (NET PROFIT) --}}
            <tr class="bg-emerald-100 dark:bg-emerald-900/50 font-extrabold text-base border-t-2 border-b-4 border-emerald-600">
                <td colspan="2" class="py-4 px-3 {{ $data['is_profit'] ? 'text-emerald-950' : 'text-red-950' }} dark:text-emerald-100">
                    LABA BERSIH (NET PROFIT)
                </td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-emerald-950' : 'text-red-950' }} dark:text-emerald-100 hidden sm:table-cell"></td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-emerald-950' : 'text-red-950' }} dark:text-emerald-100">
                    {{ number_format($data['net_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-emerald-950' : 'text-red-950' }} dark:text-emerald-100">
                    {{ number_format($data['net_profit_margin'], 1) }}%
                </td>
            </tr>argin'], 1) }}%
                </td>
            </tr>

            {{-- Balance Check --}}
            <tr class="bg-gray-50 dark:bg-gray-800 border-t">
                <td colspan="5" class="py-3 px-3 text-center text-sm">
                    <span class="font-medium">
                        @if($data['is_profit'])
                            ✅ Status: <span class="text-blue-600 dark:text-blue-400 font-bold">LABA</span>
                        @else
                            ⚠️ Status: <span class="text-orange-600 dark:text-orange-400 font-bold">RUGI</span>
                        @endif
                    </span>
                    <span class="mx-2">|</span>
                    <span class="text-gray-600 dark:text-gray-400">
                        Periode: {{ \Carbon\Carbon::parse($data['period']['start_date'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($data['period']['end_date'])->format('d/m/Y') }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</div>
