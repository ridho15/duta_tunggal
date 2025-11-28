@if(count($invoices) > 0)

<style>
/* Style for auto-calculated fields in vendor payment */
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
</style>

<div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <input type="checkbox" 
                           id="select-all-vendor"
                           class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                           onchange="toggleAllVendorInvoices(this)">
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Invoice</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sisa</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penyesuaian Sisa</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Keterangan Penyesuaian</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($invoices as $invoice)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-4 whitespace-nowrap">
                    <input type="checkbox" 
                           class="vendor-invoice-checkbox rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                           value="{{ $invoice['id'] }}"
                           data-remaining="{{ $invoice['remaining'] ?? $invoice['total'] }}"
                           onchange="handleVendorCheckboxChange(this); updateSelectedVendorInvoices()"
                           {{ in_array($invoice['id'], $selectedInvoices) ? 'checked' : '' }}>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ $invoice['invoice_number'] }}
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                    Rp. {{ number_format($invoice['total'], 0, ',', '.') }}
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <input type="number" 
                           class="vendor-receipt-input w-20 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                           placeholder="0"
                           min="0"
                           max="{{ $invoice['remaining'] ?? $invoice['total'] }}"
                           data-invoice-id="{{ $invoice['id'] }}"
                           data-remaining="{{ $invoice['remaining'] ?? $invoice['total'] }}"
                           value="{{ $invoice['receipt'] }}"
                           oninput="updateVendorReceiptAmount({{ $invoice['id'] }}, this.value)">
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <input type="number" 
                           class="vendor-balance-input w-20 px-2 py-1 text-sm border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                           placeholder="0"
                           readonly
                           data-invoice-id="{{ $invoice['id'] }}"
                           value="{{ $invoice['balance'] }}">
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <select class="vendor-adjustment-select w-40 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                            onchange="updateVendorAdjustmentBalance({{ $invoice['id'] }}, this.value)"
                            data-invoice-id="{{ $invoice['id'] }}">
                        <option value="">Pilih Salah Satu COA</option>
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
                           class="vendor-adjustment-desc-input w-32 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                           placeholder=""
                           value="{{ $invoice['adjustment_description'] }}"
                           onchange="updateVendorAdjustmentDescription({{ $invoice['id'] }}, this.value)">
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<script>
// Format Rupiah function for vendor payment
function formatVendorRupiah(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount).replace('IDR', 'Rp').trim();
}

function toggleAllVendorInvoices(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
        
        // If checking all, auto-fill receipt amounts with remaining amounts
        if (selectAllCheckbox.checked) {
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.vendor-receipt-input');
            const remaining = parseFloat(checkbox.dataset.remaining || 0);
            receiptInput.value = remaining;
            updateVendorReceiptAmount(checkbox.value, remaining);
        } else {
            // If unchecking all, clear receipt amounts
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.vendor-receipt-input');
            receiptInput.value = '';
            updateVendorReceiptAmount(checkbox.value, 0);
        }
    });
    updateSelectedVendorInvoices();
}

function updateSelectedVendorInvoices() {
    const selectedIds = [];
    const invoiceReceipts = {};
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
    
    checkboxes.forEach(checkbox => {
        const invoiceId = parseInt(checkbox.value);
        selectedIds.push(invoiceId);
        
        // Get receipt amount for this invoice
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        const receiptAmount = parseFloat(receiptInput.value || 0);
        invoiceReceipts[invoiceId] = receiptAmount;
    });
    
    console.log('Selected vendor invoices:', selectedIds);
    console.log('Vendor invoice receipts:', invoiceReceipts);
    
    // Update the hidden fields
    const selectedInvoicesField = document.querySelector('[name="selected_invoices"]');
    if (selectedInvoicesField) {
        selectedInvoicesField.value = JSON.stringify(selectedIds);
        selectedInvoicesField.dispatchEvent(new Event('input'));
    }
    
    const invoiceReceiptsField = document.querySelector('[name="invoice_receipts"]');
    if (invoiceReceiptsField) {
        invoiceReceiptsField.value = JSON.stringify(invoiceReceipts);
        invoiceReceiptsField.dispatchEvent(new Event('input'));
    }
    
    // Calculate total payment
    calculateVendorTotalPayment();
}

function updateVendorReceiptAmount(invoiceId, amount) {
    const numericAmount = parseFloat(amount) || 0;
    const checkbox = document.querySelector(`.vendor-invoice-checkbox[value="${invoiceId}"]`);
    const row = checkbox.closest('tr');
    const receiptInput = row.querySelector('.vendor-receipt-input');
    const balanceInput = row.querySelector('.vendor-balance-input');
    const remaining = parseFloat(checkbox.dataset.remaining || 0);
    
    // Validate amount doesn't exceed remaining
    if (numericAmount > remaining) {
        alert(`Pembayaran tidak boleh melebihi sisa tagihan: Rp. ${remaining.toLocaleString('id-ID')}`);
        receiptInput.value = remaining;
        numericAmount = remaining;
    }
    
    // Calculate and update balance (remaining - receipt)
    const balance = remaining - numericAmount;
    balanceInput.value = balance;
    
    // Auto-check checkbox if amount > 0, uncheck if amount = 0
    if (numericAmount > 0 && !checkbox.checked) {
        checkbox.checked = true;
        updateSelectedVendorInvoices();
    } else if (numericAmount === 0 && checkbox.checked) {
        checkbox.checked = false;
        updateSelectedVendorInvoices();
    } else {
        calculateVendorTotalPayment();
    }
    
    // Auto-fill adjustment COA if there's a balance
    if (balance > 0) {
        setTimeout(() => {
            autoFillVendorAdjustmentCOA();
        }, 100);
    }
}

function updateVendorBalance(invoiceId, balance) {
    // This function is no longer needed as balance is calculated automatically
    calculateVendorTotalPayment();
}

function updateVendorAdjustmentBalance(invoiceId, coaId) {
    // Handle adjustment COA selection
    console.log('Vendor Adjustment COA selected for invoice', invoiceId, ':', coaId);
}

function autoFillVendorAdjustmentCOA() {
    // Get the main COA selection from the form - try multiple selectors
    let mainCoaField = document.querySelector('#main-coa-field') || 
                      document.querySelector('[name="coa_id"]') ||
                      document.querySelector('select[name="coa_id"]') ||
                      document.querySelector('[data-field-name="coa_id"] select');
    
    if (!mainCoaField || !mainCoaField.value) {
        console.log('Vendor: Main COA field not found or empty');
        return;
    }
    
    const mainCoaId = mainCoaField.value;
    console.log('Vendor: Auto-filling adjustment COA with:', mainCoaId);
    
    // Auto-fill adjustment COA for invoices with remaining balance
    const adjustmentSelects = document.querySelectorAll('.vendor-adjustment-select');
    adjustmentSelects.forEach(select => {
        const row = select.closest('tr');
        const checkbox = row.querySelector('.vendor-invoice-checkbox');
        const balanceInput = row.querySelector('.vendor-balance-input');
        const balance = parseFloat(balanceInput.value || 0);
        
        // If invoice is selected and has remaining balance, auto-select the same COA
        if (checkbox.checked && balance > 0) {
            select.value = mainCoaId;
            // Trigger change event
            select.dispatchEvent(new Event('change'));
        }
    });
}

// Auto-fill receipt amount when checkbox is checked
function handleVendorCheckboxChange(checkbox) {
    if (checkbox.checked) {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        
        // Only auto-fill if receipt input is empty
        if (!receiptInput.value || parseFloat(receiptInput.value) === 0) {
            receiptInput.value = remaining;
            updateVendorReceiptAmount(checkbox.value, remaining);
        }
    } else {
        // Clear receipt amount when unchecked
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        receiptInput.value = '';
        updateVendorReceiptAmount(checkbox.value, 0);
    }
}

function calculateVendorTotalPayment() {
    let total = 0;
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        const receiptAmount = parseFloat(receiptInput.value || 0);
        total += receiptAmount;
    });
    
    // Update total payment field in the main form
    const totalPaymentField = document.querySelector('[name="total_payment"]');
    if (totalPaymentField) {
        totalPaymentField.value = total;
        totalPaymentField.dispatchEvent(new Event('input'));
        
        // Force update Filament field display
        const filamentField = totalPaymentField.closest('[data-field]');
        if (filamentField) {
            const displayInput = filamentField.querySelector('input[type="text"]');
            if (displayInput && displayInput !== totalPaymentField) {
                displayInput.value = formatVendorRupiah(total);
            }
        }
        
        // Update any formatted display fields
        const totalDisplayFields = document.querySelectorAll('[data-field="total_payment"] input');
        totalDisplayFields.forEach(field => {
            if (field !== totalPaymentField) {
                field.value = formatVendorRupiah(total);
            }
        });
    }
    
    console.log('Vendor total payment calculated:', formatVendorRupiah(total));
}

function updateVendorAdjustmentDescription(invoiceId, description) {
    // Handle adjustment description changes
}

// Initialize on page load with enhanced event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add change event listeners to all checkboxes
    document.querySelectorAll('.vendor-invoice-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            handleVendorCheckboxChange(this);
            updateSelectedVendorInvoices();
            
            // Auto-fill adjustment COA when invoice is selected
            setTimeout(() => {
                autoFillVendorAdjustmentCOA();
            }, 100);
        });
    });
    
    // Add change event listener to main COA field - try multiple selectors
    const mainCoaFieldSelectors = [
        '#main-coa-field',
        '[name="coa_id"]',
        'select[name="coa_id"]',
        '[data-field-name="coa_id"] select'
    ];
    
    let mainCoaField = null;
    for (const selector of mainCoaFieldSelectors) {
        mainCoaField = document.querySelector(selector);
        if (mainCoaField) {
            console.log('Found vendor main COA field with selector:', selector);
            break;
        }
    }
    
    if (mainCoaField) {
        mainCoaField.addEventListener('change', function() {
            console.log('Vendor main COA field changed:', this.value);
            autoFillVendorAdjustmentCOA();
        });
    } else {
        console.log('Vendor main COA field not found, using MutationObserver');
        // Fallback: observe DOM changes to detect COA field
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    for (const selector of mainCoaFieldSelectors) {
                        const field = document.querySelector(selector);
                        if (field && !field.hasAttribute('data-vendor-listener-added')) {
                            field.setAttribute('data-vendor-listener-added', 'true');
                            field.addEventListener('change', function() {
                                console.log('Vendor main COA field changed (via observer):', this.value);
                                autoFillVendorAdjustmentCOA();
                            });
                            console.log('Added listener to vendor COA field via observer');
                            break;
                        }
                    }
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
    
    // Initialize calculations and ensure selected invoices are updated
    updateSelectedVendorInvoices();
    
    // Trigger calculation for all checked invoices on page load
    document.querySelectorAll('.vendor-invoice-checkbox:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        if (receiptInput.value) {
            updateVendorReceiptAmount(checkbox.value, receiptInput.value);
        }
    });
    
    // Auto-fill adjustment COA on page load
    setTimeout(() => {
        autoFillVendorAdjustmentCOA();
    }, 500);
});
</script>

@else
<div class="text-center py-8 text-gray-500">
    <p>{{ $message ?? 'Silakan pilih supplier terlebih dahulu untuk melihat invoice' }}</p>
</div>
@endif
