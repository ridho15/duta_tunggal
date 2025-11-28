@if(is_array($invoices) && count($invoices) > 0)

<!-- Payment Mode Selection -->
<div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
    <label class="block text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Mode Pembayaran:</label>
    <div class="flex space-x-4">
        <label class="flex items-center">
            <input type="radio" name="payment_mode" value="full" class="payment-mode-radio text-blue-600 border-blue-300 focus:ring-blue-500" checked>
            <span class="ml-2 text-sm text-blue-900 dark:text-blue-100">Pembayaran Penuh (Total Invoice)</span>
        </label>
        <label class="flex items-center">
            <input type="radio" name="payment_mode" value="partial" class="payment-mode-radio text-blue-600 border-blue-300 focus:ring-blue-500">
            <span class="ml-2 text-sm text-blue-900 dark:text-blue-100">Pembayaran Sebagian (Isi Manual)</span>
        </label>
    </div>
    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
        <strong>Penuh:</strong> Total pembayaran = jumlah total invoice yang dicentang<br>
        <strong>Sebagian:</strong> Total pembayaran = jumlah yang diisi di kolom Receipt
    </p>
</div>

<style>
/* Style for auto-calculated fields */
.auto-calculated-field input {
    background-color: #f9fafb !important;
    color: #374151 !important;
    font-weight: 600;
    border-color: #d1d5db !important;
}

.dark .auto-calculated-field input {
    background-color: #374151 !important;
    color: #f3f4f6 !important;
    border-color: #4b5563 !important;
}

/* Responsive table styles */
.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Ensure input fields have proper min-width */
.receipt-input,
.balance-input,
.adjustment-select,
.adjustment-desc-input {
    min-width: 140px !important;
}

.adjustment-select {
    min-width: 180px !important;
}

/* Table cell padding consistency */
th, td {
    white-space: nowrap;
}

/* Responsive behavior for small screens */
@media (max-width: 768px) {
    .table-container {
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
    
    .receipt-input,
    .balance-input {
        min-width: 120px !important;
    }
    
    .adjustment-select {
        min-width: 160px !important;
    }
    
    .adjustment-desc-input {
        min-width: 120px !important;
    }
}
</style>

<!-- Responsive table container with horizontal scroll -->
<div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden bg-white dark:bg-gray-800">
    <div class="table-container overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" style="min-width: 1200px;">
            <colgroup>
                <col style="width: 60px;"> <!-- Checkbox -->
                <col style="width: 150px;"> <!-- Invoice Number -->
                <col style="width: 140px;"> <!-- Customer -->
                <col style="width: 130px;"> <!-- Total Invoice -->
                <col style="width: 160px;"> <!-- Receipt (wider) -->
                <col style="width: 160px;"> <!-- Sisa (wider) -->
                <col style="width: 200px;"> <!-- Penyesuaian Sisa -->
                <col style="width: 180px;"> <!-- Keterangan -->
            </colgroup>
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <input type="checkbox" 
                               id="select-all"
                               class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:focus:ring-blue-600">
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Invoice</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Invoice</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Receipt</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Sisa</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Penyesuaian Sisa</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Keterangan Penyesuaian</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($invoices as $invoice)
                <tr>
                    <td class="px-4 py-4 whitespace-nowrap text-center">
                        <input type="checkbox" 
                               class="invoice-checkbox rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:focus:ring-blue-600"
                               value="{{ $invoice['id'] }}"
                               data-remaining="{{ $invoice['remaining'] }}"
                               {{ in_array($invoice['id'], $selectedInvoices) ? 'checked' : '' }}>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                        {{ $invoice['invoice_number'] }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                        {{ $invoice['customer_name'] ?? '' }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                        Rp. {{ number_format($invoice['total'], 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" 
                               class="receipt-input block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                               placeholder="0"
                               min="0"
                               max="{{ $invoice['remaining'] }}"
                               data-invoice-id="{{ $invoice['id'] }}"
                               data-remaining="{{ $invoice['remaining'] }}"
                               value="{{ $invoice['receipt'] }}"
                               style="min-width: 140px;">
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="number" 
                               class="balance-input block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 sm:text-sm cursor-not-allowed"
                               placeholder="0"
                               readonly
                               data-invoice-id="{{ $invoice['id'] }}"
                               value="{{ $invoice['balance'] }}"
                               style="min-width: 140px;">
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                        <select class="adjustment-select block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                                data-invoice-id="{{ $invoice['id'] }}"
                                style="min-width: 180px;">
                            <option value="" class="text-gray-500 dark:text-gray-400">Pilih Salah Satu COA</option>
                            @php
                            $chartOfAccounts = \App\Models\ChartOfAccount::all();
                            @endphp
                            @foreach($chartOfAccounts as $coa)
                            <option value="{{ $coa->id }}">{{ $coa->code }} - {{ $coa->name }}</option>
                            @endforeach
                        </select>
                        <small class="text-xs text-gray-500 mt-1 block">Auto-terisi saat COA utama dipilih</small>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="text" 
                               class="adjustment-desc-input block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                               placeholder=""
                               value="{{ $invoice['adjustment_description'] }}"
                               data-invoice-id="{{ $invoice['id'] }}"
                               style="min-width: 160px;">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Summary Information -->
<div class="mt-4 p-4 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg">
    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Ringkasan Pembayaran</h4>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
            <span class="text-gray-600 dark:text-gray-400">Total Invoice:</span>
            <div class="font-semibold text-gray-900 dark:text-white" id="summary-total-invoice">
                @php
                $totalInvoice = array_sum(array_column($invoices, 'total'));
                echo 'Rp ' . number_format($totalInvoice, 0, ',', '.');
                @endphp
            </div>
        </div>
        <div>
            <span class="text-gray-600 dark:text-gray-400">Total Sisa Pembayaran:</span>
            <div class="font-semibold text-red-600 dark:text-red-400" id="summary-total-remaining">
                @php
                $totalRemaining = array_sum(array_column($invoices, 'remaining'));
                echo 'Rp ' . number_format($totalRemaining, 0, ',', '.');
                @endphp
            </div>
        </div>
        <div>
            <span class="text-gray-600 dark:text-gray-400">Total Sudah Dibayar:</span>
            <div class="font-semibold text-green-600 dark:text-green-400" id="summary-total-paid">
                @php
                $totalPaid = $totalInvoice - $totalRemaining;
                echo 'Rp ' . number_format($totalPaid, 0, ',', '.');
                @endphp
            </div>
        </div>
    </div>
    
    <!-- Individual Invoice Status -->
    <div class="mt-4 border-t border-gray-200 dark:border-gray-600 pt-3">
        <h5 class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Status Per Invoice:</h5>
        <div class="space-y-1">
            @foreach($invoices as $invoice)
            <div class="flex justify-between items-center text-xs">
                <span class="text-gray-600 dark:text-gray-400">{{ $invoice['invoice_number'] }}:</span>
                <div class="flex space-x-4">
                    <span class="text-gray-900 dark:text-white">Total: Rp {{ number_format($invoice['total'], 0, ',', '.') }}</span>
                    <span class="text-red-600 dark:text-red-400">Sisa: Rp {{ number_format($invoice['remaining'], 0, ',', '.') }}</span>
                    <span class="px-2 py-1 rounded text-xs {{ $invoice['remaining'] <= 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
                        {{ $invoice['remaining'] <= 0 ? 'Lunas' : 'Belum Lunas' }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
// Format Rupiah function
function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount).replace('IDR', 'Rp').trim();
}

function toggleAllInvoices(selectAllCheckbox) {
    console.log('🔄 [TABLE] Toggle all invoices:', selectAllCheckbox.checked);
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    const paymentMode = document.querySelector('.payment-mode-radio:checked')?.value || 'full';
    
    console.log('Current payment mode:', paymentMode);
    console.log('Processing', checkboxes.length, 'checkboxes');
    
    checkboxes.forEach((checkbox, index) => {
        checkbox.checked = selectAllCheckbox.checked;
        
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        
        if (selectAllCheckbox.checked) {
            if (paymentMode === 'full') {
                receiptInput.value = remaining;
                console.log(`Set checkbox ${index} (invoice ${checkbox.value}) receipt to ${remaining}`);
            } else {
                // In partial mode, don't auto-fill, let user decide
                console.log(`Checkbox ${index} checked but partial mode - not auto-filling`);
            }
        } else {
            receiptInput.value = '';
            console.log(`Cleared checkbox ${index} receipt`);
        }
        
        // Trigger events
        ['input', 'change'].forEach(eventType => {
            receiptInput.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
    });
    
    // Update using main function
    if (window.updateSelectedInvoicesMain) {
        console.log('Using main updateSelectedInvoices function');
        window.updateSelectedInvoicesMain();
    } else {
        console.log('Using fallback updateSelectedInvoices function');
        updateSelectedInvoices();
    }
}

function updateSelectedInvoices() {
    console.log('🔧 [TABLE] updateSelectedInvoices called');
    
    // Check if the main function from init file exists and use it instead
    if (window.updateSelectedInvoicesMain && typeof window.updateSelectedInvoicesMain === 'function') {
        console.log('↗️ Delegating to main updateSelectedInvoices function');
        return window.updateSelectedInvoicesMain();
    }
    
    // Fallback implementation if main function not available
    console.log('⚠️ Using fallback updateSelectedInvoices implementation');
    const selectedIds = [];
    const invoiceReceipts = {};
    let totalPaymentAmount = 0;
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    
    console.log('Processing', checkboxes.length, 'checked invoices');
    
    checkboxes.forEach(checkbox => {
        const invoiceId = parseInt(checkbox.value);
        selectedIds.push(invoiceId);
        
        // Get receipt amount for this invoice
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const receiptValue = receiptInput.value || '0';
        const receiptAmount = parseFloat(receiptValue.replace(/[^\d.-]/g, '')) || 0;
        invoiceReceipts[invoiceId] = receiptAmount;
        totalPaymentAmount += receiptAmount;
        
        console.log(`Invoice ${invoiceId}: receipt="${receiptValue}" → amount=${receiptAmount}`);
    });
    
    console.log('Total payment calculated:', totalPaymentAmount);
    
    // Update hidden fields
    const selectedInvoicesField = document.querySelector('[name="selected_invoices"]');
    if (selectedInvoicesField) {
        selectedInvoicesField.value = JSON.stringify(selectedIds);
        selectedInvoicesField.dispatchEvent(new Event('input', { bubbles: true }));
        selectedInvoicesField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✅ Updated selected_invoices field');
    }
    
    const invoiceReceiptsField = document.querySelector('[name="invoice_receipts"]');
    if (invoiceReceiptsField) {
        invoiceReceiptsField.value = JSON.stringify(invoiceReceipts);
        invoiceReceiptsField.dispatchEvent(new Event('input', { bubbles: true }));
        invoiceReceiptsField.dispatchEvent(new Event('change', { bubbles: true }));
        console.log('✅ Updated invoice_receipts field');
    }
    
    // Update total payment field
    updateTotalPaymentField(totalPaymentAmount);
}

function updateTotalPaymentField(totalAmount) {
    // Try multiple approaches to find the total payment field
    let totalPaymentField = null;
    
    // Method 1: Try safe selectors
    const safeSelectors = [
        '[name="total_payment"]',
        'input[name="total_payment"]',
        'input[type="number"][name="total_payment"]'
    ];
    
    for (const selector of safeSelectors) {
        try {
            totalPaymentField = document.querySelector(selector);
            if (totalPaymentField) break;
        } catch (e) {
            // Silent fail, try next selector
        }
    }
    
    // Method 2: Find by attribute search if safe selectors fail
    if (!totalPaymentField) {
        const allInputs = document.querySelectorAll('input');
        for (const input of allInputs) {
            if (input.name === 'total_payment' || 
                input.getAttribute('wire:model') === 'total_payment' ||
                input.getAttribute('wire:model.defer') === 'total_payment') {
                totalPaymentField = input;
                break;
            }
        }
    }
    
    if (totalPaymentField) {
        // Temporarily enable field for update
        const wasDisabled = totalPaymentField.disabled;
        const wasReadOnly = totalPaymentField.readOnly;
        
        totalPaymentField.disabled = false;
        totalPaymentField.readOnly = false;
        totalPaymentField.value = totalAmount;
        
        // Trigger multiple events to ensure Filament reactivity
        ['input', 'change', 'blur', 'keyup'].forEach(eventType => {
            totalPaymentField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        
        // Force focus and blur to trigger Filament reactivity
        totalPaymentField.focus();
        setTimeout(() => {
            totalPaymentField.blur();
            totalPaymentField.disabled = wasDisabled;
            totalPaymentField.readOnly = wasReadOnly;
        }, 50);
        
        // Try Livewire update if available
        try {
            if (window.Livewire && window.Livewire.all && window.Livewire.all().length > 0) {
                const component = window.Livewire.all()[0];
                if (component.set) {
                    component.set('total_payment', totalAmount);
                }
            }
        } catch (e) {
            // Silent fail
        }
    }
    
    // Call global calculation function if available
    if (typeof window.calculateTotalPayment === 'function') {
        window.calculateTotalPayment();
    }
}

function updateReceiptAmount(invoiceId, amount) {
    const numericAmount = parseFloat(amount) || 0;
    const checkbox = document.querySelector(`.invoice-checkbox[value="${invoiceId}"]`);
    const row = checkbox.closest('tr');
    const receiptInput = row.querySelector('.receipt-input');
    const balanceInput = row.querySelector('.balance-input');
    const remaining = parseFloat(checkbox.dataset.remaining || 0);
    
    // Validate amount doesn't exceed remaining
    if (numericAmount > remaining) {
        alert(`Pembayaran tidak boleh melebihi sisa tagihan: Rp. ${remaining.toLocaleString('id-ID')}`);
        receiptInput.value = remaining;
        amount = remaining;
    }
    
    // Calculate and update balance (remaining - receipt)
    const balance = remaining - numericAmount;
    balanceInput.value = balance;
    
    // Auto-check checkbox if amount > 0, uncheck if amount = 0
    if (numericAmount > 0 && !checkbox.checked) {
        checkbox.checked = true;
        updateSelectedInvoices();
    } else if (numericAmount === 0 && checkbox.checked) {
        checkbox.checked = false;
        updateSelectedInvoices();
    } else {
        if (typeof window.calculateTotalPayment === 'function') {
            window.calculateTotalPayment();
        }
    }
    
    // Auto-fill adjustment COA if there's a balance
    if (balance > 0) {
        setTimeout(() => {
            autoFillAdjustmentCOA();
        }, 100);
    }
}

function updateBalance(invoiceId, balance) {
    // This function is no longer needed as balance is calculated automatically
    if (typeof window.calculateTotalPayment === 'function') {
        window.calculateTotalPayment();
    }
}

function updateAdjustmentBalance(invoiceId, coaId) {
    // Handle adjustment COA selection
}

function autoFillAdjustmentCOA() {
    // Get the main COA selection from the form - try multiple selectors
    let mainCoaField = document.querySelector('#main-coa-field') || 
                      document.querySelector('[name="coa_id"]') ||
                      document.querySelector('select[name="coa_id"]') ||
                      document.querySelector('[data-field-name="coa_id"] select');
    
    if (!mainCoaField || !mainCoaField.value) {
        return;
    }
    
    const mainCoaId = mainCoaField.value;
    
    // Auto-fill adjustment COA for invoices with remaining balance
    const adjustmentSelects = document.querySelectorAll('.adjustment-select');
    adjustmentSelects.forEach(select => {
        const row = select.closest('tr');
        const checkbox = row.querySelector('.invoice-checkbox');
        const balanceInput = row.querySelector('.balance-input');
        const balance = parseFloat(balanceInput.value || 0);
        
        // If invoice is selected and has remaining balance, auto-select the same COA
        if (checkbox.checked && balance > 0) {
            select.value = mainCoaId;
            // Trigger change event
            select.dispatchEvent(new Event('change'));
        }
    });
}

function handlePaymentModeChange(mode) {
    console.log('🎛️ [TABLE] Payment mode changed to:', mode);
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    
    if (mode === 'full') {
        // Pembayaran Penuh: Auto-fill dengan remaining amount untuk yang dicentang
        console.log('Full payment mode - auto-filling checked invoices with remaining amounts');
        checkboxes.forEach(checkbox => {
            if (checkbox.checked) {
                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.receipt-input');
                const remaining = parseFloat(checkbox.dataset.remaining || 0);
                
                console.log(`Setting invoice ${checkbox.value} receipt to ${remaining}`);
                receiptInput.value = remaining;
                
                // Trigger input events
                ['input', 'change'].forEach(eventType => {
                    receiptInput.dispatchEvent(new Event(eventType, { bubbles: true }));
                });
            }
        });
    } else if (mode === 'partial') {
        // Pembayaran Sebagian: Biarkan user mengisi manual, jangan clear yang sudah ada
        console.log('Partial payment mode - keeping current receipt values');
        // Tidak perlu melakukan apa-apa, user bisa isi manual
    }
    
    // Update total payment calculation using main function
    if (window.updateSelectedInvoicesMain) {
        window.updateSelectedInvoicesMain();
    } else {
        updateSelectedInvoices();
    }
}

function updateAdjustmentDescription(invoiceId, description) {
    // Handle adjustment description changes
}

// calculateTotalPayment function is now handled by the separate JavaScript init component
// This ensures no conflicts and a single source of truth for calculations

// Auto-fill receipt amount when checkbox is checked
function handleCheckboxChange(checkbox) {
    console.log('handleCheckboxChange');
    if (checkbox.checked) {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        
        // Only auto-fill if receipt input is empty
        if (!receiptInput.value || parseFloat(receiptInput.value) === 0) {
            receiptInput.value = remaining;
            updateReceiptAmount(checkbox.value, remaining);
        }
    } else {
        // Clear receipt amount when unchecked
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        receiptInput.value = '';
        updateReceiptAmount(checkbox.value, 0);
    }
}

// Add event listeners when page loads or when ViewField is rendered
function initializeEventListeners() {
    // Check if already initialized to avoid duplicates
    if (window.invoiceTableListenersInitialized) {
        return;
    }
    
    // Check if invoice table exists
    const invoiceTable = document.querySelector('.invoice-checkbox');
    if (!invoiceTable) {
        return false;
    }
    
    // Mark as initialized
    window.invoiceTableListenersInitialized = true;
    
    // Add event listeners for invoice checkboxes
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    
    checkboxes.forEach((checkbox) => {
        if (!checkbox.hasAttribute('data-events-attached')) {
            checkbox.setAttribute('data-events-attached', 'true');
            
            checkbox.addEventListener('change', function() {
                updateSelectedInvoices();
            });
        }
    });
    
    // Add event listeners for receipt inputs
    const receiptInputs = document.querySelectorAll('.receipt-input');
    
    receiptInputs.forEach((input) => {
        if (!input.hasAttribute('data-events-attached')) {
            input.setAttribute('data-events-attached', 'true');
            
            input.addEventListener('input', function(e) {
                const invoiceId = this.getAttribute('data-invoice-id');
                updateReceiptAmount(invoiceId, this.value);
            });
            
            input.addEventListener('change', function(e) {
                const invoiceId = this.getAttribute('data-invoice-id');
                updateReceiptAmount(invoiceId, this.value);
            });
            
            input.addEventListener('blur', function(e) {
                const invoiceId = this.getAttribute('data-invoice-id');
                updateReceiptAmount(invoiceId, this.value);
            });
        }
    });
    
    // Add event listeners for adjustment selects
    document.querySelectorAll('.adjustment-select').forEach(select => {
        select.addEventListener('change', function() {
            const invoiceId = this.getAttribute('data-invoice-id');
            updateAdjustmentBalance(invoiceId, this.value);
        });
    });
    
    // Add event listeners for adjustment description inputs
    document.querySelectorAll('.adjustment-desc-input').forEach(input => {
        input.addEventListener('change', function() {
            const invoiceId = this.getAttribute('data-invoice-id');
            updateAdjustmentDescription(invoiceId, this.value);
        });
    });
    
    // Add event listeners for select-all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox && !selectAllCheckbox.hasAttribute('data-events-attached')) {
        selectAllCheckbox.setAttribute('data-events-attached', 'true');
        selectAllCheckbox.addEventListener('change', function() {
            toggleAllInvoices(this);
        });
    }
    
    return true;
}

// Initialize with retry mechanism
setTimeout(function() {
    const success = initializeEventListeners();
    if (!success) {
        setTimeout(function() {
            initializeEventListeners();
        }, 1000);
    }
}, 500);
</script>

@else
<div class="text-center py-8 text-gray-500 dark:text-gray-400">
    <p>{{ $message ?? 'Silakan pilih customer terlebih dahulu untuk melihat invoice' }}</p>
</div>
@endif
