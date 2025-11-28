<script>
// Clean Customer Receipt JavaScript - Focused on core functionality
// Function to update selected invoices and total payment
function updateSelectedInvoices() {
    const selectedIds = [];
    const invoiceReceipts = {};
    let totalPaymentAmount = 0;
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    
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
    });
    
    // Update hidden fields
    updateHiddenField('selected_invoices', JSON.stringify(selectedIds));
    updateHiddenField('invoice_receipts', JSON.stringify(invoiceReceipts));
    
    // Update total payment field
    updateTotalPaymentField(totalPaymentAmount);
}

// Helper function to update hidden fields reliably
function updateHiddenField(fieldName, value) {
    const selectors = [
        `[name="${fieldName}"]`,
        `input[name="${fieldName}"]`,
        `#${fieldName}`
    ];
    
    let field = null;
    
    // Try safe selectors first
    for (const selector of selectors) {
        try {
            field = document.querySelector(selector);
            if (field) break;
        } catch (e) {
            // Silent fail, try next selector
        }
    }
    
    // Fallback: search by attribute
    if (!field) {
        const allInputs = document.querySelectorAll('input, textarea');
        for (const input of allInputs) {
            if (input.name === fieldName || 
                input.getAttribute('wire:model') === fieldName ||
                input.getAttribute('wire:model.defer') === fieldName) {
                field = input;
                break;
            }
        }
    }
    
    if (field) {
        field.value = value;
        
        // Trigger events for Filament reactivity
        ['input', 'change', 'blur'].forEach(eventType => {
            field.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
    }
}

// Function to update total payment field
function updateTotalPaymentField(totalAmount) {
    const selectors = [
        '[name="total_payment"]',
        'input[name="total_payment"]',
        'input[type="number"][name="total_payment"]'
    ];
    
    let totalPaymentField = null;
    
    // Try safe selectors
    for (const selector of selectors) {
        try {
            totalPaymentField = document.querySelector(selector);
            if (totalPaymentField) break;
        } catch (e) {
            // Silent fail
        }
    }
    
    // Fallback: search by attribute
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
        const wasReadOnly = totalPaymentField.readOnly;
        const wasDisabled = totalPaymentField.disabled;
        
        totalPaymentField.readOnly = false;
        totalPaymentField.disabled = false;
        totalPaymentField.value = totalAmount;
        
        // Trigger events for Filament reactivity
        ['input', 'change', 'blur', 'keyup'].forEach(eventType => {
            totalPaymentField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        
        // Focus and blur to ensure Filament reactivity
        totalPaymentField.focus();
        setTimeout(() => {
            totalPaymentField.blur();
            totalPaymentField.readOnly = wasReadOnly;
            totalPaymentField.disabled = wasDisabled;
        }, 50);
        
        // Try Livewire update
        try {
            if (window.Livewire) {
                if (window.Livewire.dispatch) {
                    window.Livewire.dispatch('updateTotalPayment', { total: totalAmount });
                } else if (window.Livewire.emit) {
                    window.Livewire.emit('updateTotalPayment', totalAmount);
                } else if (window.Livewire.all && window.Livewire.all().length > 0) {
                    const component = window.Livewire.all()[0];
                    if (component.set) {
                        component.set('total_payment', totalAmount);
                    }
                }
            }
        } catch (e) {
            // Silent fail
        }
    }
}

// Function to handle checkbox changes
function handleInvoiceCheckboxChange(checkbox) {
    if (checkbox.checked) {
        // Auto-fill receipt amount when checked
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        
        if (!receiptInput.value || parseFloat(receiptInput.value) === 0) {
            receiptInput.value = remaining;
        }
    } else {
        // Clear receipt amount when unchecked
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        receiptInput.value = '';
    }
    
    updateSelectedInvoices();
}

// Function to handle receipt input changes
function handleReceiptInputChange(input) {
    const invoiceId = input.getAttribute('data-invoice-id');
    const amount = parseFloat(input.value) || 0;
    const checkbox = document.querySelector(`.invoice-checkbox[value="${invoiceId}"]`);
    const remaining = parseFloat(checkbox.dataset.remaining || 0);
    
    // Validate amount doesn't exceed remaining
    if (amount > remaining) {
        alert(`Pembayaran tidak boleh melebihi sisa tagihan: Rp. ${remaining.toLocaleString('id-ID')}`);
        input.value = remaining;
    }
    
    // Auto-check/uncheck checkbox based on amount
    if (amount > 0 && !checkbox.checked) {
        checkbox.checked = true;
    } else if (amount === 0 && checkbox.checked) {
        checkbox.checked = false;
    }
    
    updateSelectedInvoices();
}

// Initialize event listeners
function initializeCustomerReceiptEvents() {
    // Prevent duplicate initialization
    if (window.customerReceiptEventsInitialized) {
        return;
    }
    
    // Check if invoice table exists
    const invoiceTable = document.querySelector('.invoice-checkbox');
    if (!invoiceTable) {
        return false;
    }
    
    window.customerReceiptEventsInitialized = true;
    
    // Add event listeners for invoice checkboxes
    document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
        if (!checkbox.hasAttribute('data-events-attached')) {
            checkbox.setAttribute('data-events-attached', 'true');
            checkbox.addEventListener('change', function() {
                handleInvoiceCheckboxChange(this);
            });
        }
    });
    
    // Add event listeners for receipt inputs
    document.querySelectorAll('.receipt-input').forEach(input => {
        if (!input.hasAttribute('data-events-attached')) {
            input.setAttribute('data-events-attached', 'true');
            
            ['input', 'change', 'blur'].forEach(eventType => {
                input.addEventListener(eventType, function() {
                    handleReceiptInputChange(this);
                });
            });
        }
    });
    
    // Add event listener for select-all checkbox
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox && !selectAllCheckbox.hasAttribute('data-events-attached')) {
        selectAllCheckbox.setAttribute('data-events-attached', 'true');
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.invoice-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
                handleInvoiceCheckboxChange(checkbox);
            });
        });
    }
    
    return true;
}

// Global functions for compatibility
window.updateSelectedInvoices = updateSelectedInvoices;
window.calculateTotalPayment = updateSelectedInvoices; // Alias for backward compatibility

// Initialize with retry mechanism
setTimeout(function() {
    const success = initializeCustomerReceiptEvents();
    if (!success) {
        setTimeout(function() {
            initializeCustomerReceiptEvents();
        }, 1000);
    }
}, 500);

// Also initialize on DOM ready if still loading
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initializeCustomerReceiptEvents, 500);
    });
}
</script>
