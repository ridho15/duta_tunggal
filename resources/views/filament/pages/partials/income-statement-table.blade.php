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
            <tr class="bg-gradient-to-r from-green-100 to-green-50 dark:from-green-900/30 dark:to-green-900/20 font-semibold border-t-2 border-green-300">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-green-700 dark:text-green-400">💰 PENDAPATAN USAHA (SALES REVENUE)</span>
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
            <tr class="bg-green-200 dark:bg-green-900/40 font-bold border-t border-green-300">
                <td colspan="2" class="py-2 px-3 text-green-800 dark:text-green-300">TOTAL PENDAPATAN USAHA</td>
                <td class="text-right py-2 px-3 text-green-800 dark:text-green-300 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-green-800 dark:text-green-300">{{ number_format($data['sales_revenue']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-green-800 dark:text-green-300">100.0%</td>
            </tr>

            {{-- 2. HARGA POKOK PENJUALAN (COGS) --}}
            <tr class="bg-gradient-to-r from-red-100 to-red-50 dark:from-red-900/30 dark:to-red-900/20 font-semibold border-t-2 border-red-300 mt-2">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-red-700 dark:text-red-400">📦 HARGA POKOK PENJUALAN (COGS)</span>
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
            <tr class="bg-red-200 dark:bg-red-900/40 font-bold border-t border-red-300">
                <td colspan="2" class="py-2 px-3 text-red-800 dark:text-red-300">TOTAL HARGA POKOK PENJUALAN</td>
                <td class="text-right py-2 px-3 text-red-800 dark:text-red-300 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-red-800 dark:text-red-300">{{ number_format($data['cogs']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-red-800 dark:text-red-300">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['cogs']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- LABA KOTOR (GROSS PROFIT) --}}
            <tr class="bg-gradient-to-r {{ $data['gross_profit'] >= 0 ? 'from-blue-200 to-blue-100' : 'from-red-200 to-red-100' }} dark:from-blue-900 dark:to-blue-800 font-bold text-base border-y-2 border-blue-400">
                <td colspan="2" class="py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-blue-800' : 'text-red-800' }} dark:text-blue-200">
                    ⚡ LABA KOTOR (GROSS PROFIT)
                </td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-blue-700' : 'text-red-700' }} dark:text-blue-200 hidden sm:table-cell"></td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-blue-700' : 'text-red-700' }} dark:text-blue-200">
                    {{ number_format($data['gross_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['gross_profit'] >= 0 ? 'text-blue-700' : 'text-red-700' }} dark:text-blue-200">
                    {{ number_format($data['gross_profit_margin'], 1) }}%
                </td>
            </tr>

            {{-- 3. BEBAN OPERASIONAL (OPERATING EXPENSES) --}}
            <tr class="bg-gradient-to-r from-orange-100 to-orange-50 dark:from-orange-900/30 dark:to-orange-900/20 font-semibold border-t-2 border-orange-300">
                <td colspan="5" class="py-2.5 px-3">
                    <span class="text-orange-700 dark:text-orange-400">💼 BEBAN OPERASIONAL (OPERATING EXPENSES)</span>
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
            <tr class="bg-orange-200 dark:bg-orange-900/40 font-bold border-t border-orange-300">
                <td colspan="2" class="py-2 px-3 text-orange-800 dark:text-orange-300">TOTAL BEBAN OPERASIONAL</td>
                <td class="text-right py-2 px-3 text-orange-800 dark:text-orange-300 hidden sm:table-cell"></td>
                <td class="text-right py-2 px-3 text-orange-800 dark:text-orange-300">{{ number_format($data['operating_expenses']['total'], 0, ',', '.') }}</td>
                <td class="text-right py-2 px-3 text-orange-800 dark:text-orange-300">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['operating_expenses']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- LABA OPERASIONAL (OPERATING PROFIT) --}}
            <tr class="bg-gradient-to-r {{ $data['operating_profit'] >= 0 ? 'from-indigo-200 to-indigo-100' : 'from-red-200 to-red-100' }} dark:from-indigo-900 dark:to-indigo-800 font-bold text-base border-y-2 border-indigo-400">
                <td colspan="2" class="py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-indigo-800' : 'text-red-800' }} dark:text-indigo-200">
                    📊 LABA OPERASIONAL (OPERATING PROFIT)
                </td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-indigo-700' : 'text-red-700' }} dark:text-indigo-200 hidden sm:table-cell"></td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-indigo-700' : 'text-red-700' }} dark:text-indigo-200">
                    {{ number_format($data['operating_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['operating_profit'] >= 0 ? 'text-indigo-700' : 'text-red-700' }} dark:text-indigo-200">
                    {{ number_format($data['operating_profit_margin'], 1) }}%
                </td>
            </tr>

            {{-- 4. PENDAPATAN/BEBAN LAIN-LAIN (OTHER INCOME/EXPENSE) --}}
            @if($data['other_income']['total'] > 0 || $data['other_expense']['total'] > 0)
                {{-- Other Income --}}
                @if($data['other_income']['total'] > 0)
                    <tr class="bg-gradient-to-r from-purple-100 to-purple-50 dark:from-purple-900/30 dark:to-purple-900/20 font-semibold border-t-2 border-purple-300">
                        <td colspan="5" class="py-2.5 px-3">
                            <span class="text-purple-700 dark:text-purple-400">✨ PENDAPATAN LAIN-LAIN (OTHER INCOME)</span>
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
                    <tr class="bg-purple-200 dark:bg-purple-900/40 font-semibold border-t border-purple-300">
                        <td colspan="2" class="py-2 px-3 text-purple-800 dark:text-purple-300">TOTAL PENDAPATAN LAIN-LAIN</td>
                        <td class="text-right py-2 px-3 text-purple-800 dark:text-purple-300 hidden sm:table-cell"></td>
                        <td class="text-right py-2 px-3 text-purple-800 dark:text-purple-300">{{ number_format($data['other_income']['total'], 0, ',', '.') }}</td>
                        <td class="text-right py-2 px-3 text-purple-800 dark:text-purple-300">
                            {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['other_income']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                        </td>
                    </tr>
                @endif

                {{-- Other Expense --}}
                @if($data['other_expense']['total'] > 0)
                    <tr class="bg-gradient-to-r from-pink-100 to-pink-50 dark:from-pink-900/30 dark:to-pink-900/20 font-semibold border-t border-pink-300">
                        <td colspan="5" class="py-2.5 px-3">
                            <span class="text-pink-700 dark:text-pink-400">⚠️ BEBAN LAIN-LAIN (OTHER EXPENSES)</span>
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
                    <tr class="bg-pink-200 dark:bg-pink-900/40 font-semibold border-t border-pink-300">
                        <td colspan="2" class="py-2 px-3 text-pink-800 dark:text-pink-300">TOTAL BEBAN LAIN-LAIN</td>
                        <td class="text-right py-2 px-3 text-pink-800 dark:text-pink-300 hidden sm:table-cell"></td>
                        <td class="text-right py-2 px-3 text-pink-800 dark:text-pink-300">{{ number_format($data['other_expense']['total'], 0, ',', '.') }}</td>
                        <td class="text-right py-2 px-3 text-pink-800 dark:text-pink-300">
                            {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['other_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                        </td>
                    </tr>
                @endif
            @endif

            {{-- LABA SEBELUM PAJAK (PROFIT BEFORE TAX) --}}
            <tr class="bg-gradient-to-r {{ $data['profit_before_tax'] >= 0 ? 'from-teal-200 to-teal-100' : 'from-red-200 to-red-100' }} dark:from-teal-900 dark:to-teal-800 font-bold text-base border-y-2 border-teal-400">
                <td colspan="3" class="py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-teal-800' : 'text-red-800' }} dark:text-teal-200">
                    📄 LABA SEBELUM PAJAK (PROFIT BEFORE TAX)
                </td>
                <td class="text-right py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-teal-700' : 'text-red-700' }} dark:text-teal-200">
                    {{ number_format($data['profit_before_tax'], 0, ',', '.') }}
                </td>
                <td class="text-right py-3 px-3 {{ $data['profit_before_tax'] >= 0 ? 'text-teal-700' : 'text-red-700' }} dark:text-teal-200">
                    {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['profit_before_tax'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                </td>
            </tr>

            {{-- 5. PAJAK PENGHASILAN (TAX EXPENSE) --}}
            @if($data['tax_expense']['total'] > 0)
                <tr class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-900/30 dark:to-gray-900/20 font-semibold border-t border-gray-300">
                    <td colspan="5" class="py-2.5 px-3">
                        <span class="text-gray-700 dark:text-gray-400">🏛️ PAJAK PENGHASILAN (TAX EXPENSE)</span>
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
                <tr class="bg-gray-200 dark:bg-gray-900/40 font-semibold border-t border-gray-300">
                    <td colspan="2" class="py-2 px-3 text-gray-800 dark:text-gray-300">TOTAL PAJAK PENGHASILAN</td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300 hidden sm:table-cell"></td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">{{ number_format($data['tax_expense']['total'], 0, ',', '.') }}</td>
                    <td class="text-right py-2 px-3 text-gray-800 dark:text-gray-300">
                        {{ $data['sales_revenue']['total'] > 0 ? number_format(($data['tax_expense']['total'] / $data['sales_revenue']['total']) * 100, 1) : '0.0' }}%
                    </td>
                </tr>
            @endif

            {{-- LABA BERSIH (NET PROFIT) --}}
            <tr class="bg-gradient-to-r {{ $data['is_profit'] ? 'from-blue-300 to-blue-200' : 'from-orange-300 to-orange-200' }} dark:from-blue-800 dark:to-blue-700 font-bold text-lg border-y-4 {{ $data['is_profit'] ? 'border-blue-500' : 'border-orange-500' }}">
                <td colspan="2" class="py-4 px-3 {{ $data['is_profit'] ? 'text-blue-900' : 'text-orange-900' }} dark:text-blue-100">
                    🏆 LABA BERSIH (NET PROFIT)
                </td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-blue-900' : 'text-orange-900' }} dark:text-blue-100 hidden sm:table-cell"></td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-blue-900' : 'text-orange-900' }} dark:text-blue-100">
                    {{ number_format($data['net_profit'], 0, ',', '.') }}
                </td>
                <td class="text-right py-4 px-3 {{ $data['is_profit'] ? 'text-blue-900' : 'text-orange-900' }} dark:text-blue-100">
                    {{ number_format($data['net_profit_margin'], 1) }}%
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
