<script>
// Main function to update selected invoices and total payment
function updateSelectedInvoices() {
    const selectedIds = [];
    const invoiceReceipts = {};
    let totalPaymentAmount = 0;
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    
    checkboxes.forEach((checkbox, index) => {
        const invoiceId = parseInt(checkbox.value);
        selectedIds.push(invoiceId);
        
        // Get receipt amount for this invoice
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const receiptValue = receiptInput.value || '0';
        // Parse and ensure integer value
        const receiptAmount = parseReceiptValue(receiptValue);
        
        invoiceReceipts[invoiceId] = receiptAmount;
        totalPaymentAmount += receiptAmount;
    });
    
    // Update readonly fields
    updateField('selected_invoices', JSON.stringify(selectedIds));
    updateField('invoice_receipts', JSON.stringify(invoiceReceipts));
    
    // Update total payment field with focus preservation
    updateTotalPaymentField(totalPaymentAmount, true);
}

// Debounced version for input events
let updateTimeout = null;
function updateSelectedInvoicesDebounced() {
    if (updateTimeout) {
        clearTimeout(updateTimeout);
    }
    updateTimeout = setTimeout(() => {
        updateSelectedInvoices();
    }, 300); // 300ms delay
}

// Helper function to format number as integer (no decimals)
function formatAsInteger(value) {
    const numValue = parseFloat(value) || 0;
    return Math.round(numValue).toString();
}

// Helper function to parse receipt input value
function parseReceiptValue(inputValue) {
    if (!inputValue) return 0;
    // Remove any non-numeric characters except decimal point
    const cleanValue = inputValue.toString().replace(/[^\d.-]/g, '');
    const numValue = parseFloat(cleanValue) || 0;
    return Math.round(numValue); // Return as integer
}

// Helper function to update form fields reliably
function updateField(fieldName, value) {
    const selectors = [
        `[name="${fieldName}"]`,
        `input[name="${fieldName}"]`,
        `#${fieldName}`,
        `[wire\\:model="${fieldName}"]`,
        `[wire\\:model="data.${fieldName}"]`,
        `input[wire\\:model="${fieldName}"]`,
        `input[wire\\:model="data.${fieldName}"]`,
        `[data-field="${fieldName}"]`
    ];
    
    let field = null;
    let foundSelector = '';
    
    // Try safe selectors first
    for (const selector of selectors) {
        try {
            field = document.querySelector(selector);
            if (field) {
                foundSelector = selector;
                break;
            }
        } catch (e) {
            // Selector failed, continue
        }
    }
    
    // Enhanced fallback: search by all attributes
    if (!field) {
        const allInputs = document.querySelectorAll('input, textarea, select');
        
        for (const input of allInputs) {
            // Get all wire model variations
            const wireModel = input.getAttribute('wire:model') || 
                            input.getAttribute('wire:model.defer') || 
                            input.getAttribute('wire:model.lazy') ||
                            input.getAttribute('wire:model.live');
            const inputName = input.name;
            const inputId = input.id;
            
            // Check various possible matches
            const matches = [
                inputName === fieldName,
                inputName === `data.${fieldName}`,
                inputName === `data[${fieldName}]`,
                wireModel === fieldName,
                wireModel === `data.${fieldName}`,
                inputId === fieldName,
                inputId === `data.${fieldName}`,
                inputId === `data_${fieldName}`,
                // Check if it's a Filament field with data-field attribute
                input.hasAttribute('data-field') && input.getAttribute('data-field') === fieldName,
                input.classList.contains(`field-${fieldName}`),
                // Check if wire:model contains the field name
                wireModel && wireModel.includes(fieldName)
            ];
            
            if (matches.some(match => match)) {
                field = input;
                foundSelector = `enhanced search (name: ${inputName}, wireModel: ${wireModel}, id: ${inputId})`;
                break;
            }
        }
    }
    
    if (field) {
        // Store original states
        const wasReadOnly = field.readOnly;
        
        // Temporarily enable field for update (in case it's readonly)
        field.readOnly = false;
        
        // Update the field
        field.value = value;
        
        // Trigger comprehensive events for Filament reactivity
        const events = ['input', 'change', 'blur', 'keyup'];
        events.forEach(eventType => {
            const event = new Event(eventType, { bubbles: true });
            field.dispatchEvent(event);
        });
        
        // Additional custom events for Filament
        try {
            field.dispatchEvent(new CustomEvent('wire:model', { 
                bubbles: true, 
                detail: { value: value } 
            }));
        } catch (e) {
            // Custom event failed
        }
        
        // Verify the update
        setTimeout(() => {
            if (field.value !== value) {
                // Try to set again
                field.value = value;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            // Restore original readonly state
            field.readOnly = wasReadOnly;
        }, 100);
    }
}

// Function to update total payment field with enhanced detection
function updateTotalPaymentField(totalAmount, preserveFocus = true) {
    // Store currently focused element
    const currentFocus = preserveFocus ? document.activeElement : null;
    
    const selectors = [
        '#data\\.total_payment',
        '[id="data.total_payment"]',
        '[name="total_payment"]',
        'input[name="total_payment"]',
        '[wire\\:model="data.total_payment"]',
        '[wire\\:model\\.defer="data.total_payment"]',
        'input[type="number"]#data\\.total_payment'
    ];
    
    let totalPaymentField = null;
    
    // Try each selector
    for (const selector of selectors) {
        try {
            totalPaymentField = document.querySelector(selector);
            if (totalPaymentField) {
                break;
            }
        } catch (e) {
            // Selector failed
        }
    }
    
    // Enhanced fallback search
    if (!totalPaymentField) {
        const allInputs = document.querySelectorAll('input[type="number"]');
        
        for (const input of allInputs) {
            const wireModel = input.getAttribute('wire:model') || input.getAttribute('wire:model.defer');
            const inputId = input.id;
            const inputName = input.name;
            
            if (inputId === 'data.total_payment' ||
                inputName === 'total_payment' || 
                wireModel === 'data.total_payment' ||
                wireModel === 'total_payment' ||
                inputId.includes('total_payment')) {
                totalPaymentField = input;
                break;
            }
        }
    }
    
    if (totalPaymentField) {
        // Store original states
        const wasReadOnly = totalPaymentField.readOnly;
        const wasDisabled = totalPaymentField.disabled;
        
        // Temporarily enable field for update
        totalPaymentField.readOnly = false;
        totalPaymentField.disabled = false;
        
        // Set the value
        const oldValue = totalPaymentField.value;
        totalPaymentField.value = totalAmount;
        
        // Trigger comprehensive events for Filament/Livewire reactivity
        const events = ['input', 'change'];
        events.forEach(eventType => {
            totalPaymentField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        
        // Additional Filament-specific events
        totalPaymentField.dispatchEvent(new CustomEvent('wire:model', { 
            bubbles: true, 
            detail: { value: totalAmount } 
        }));
        
        // Restore original states immediately
        totalPaymentField.readOnly = wasReadOnly;
        totalPaymentField.disabled = wasDisabled;
        
        // Restore focus to original element if specified
        if (preserveFocus && currentFocus && currentFocus !== totalPaymentField) {
            setTimeout(() => {
                try {
                    if (currentFocus.tagName && currentFocus.focus) {
                        currentFocus.focus();
                        // Restore cursor position if it's an input
                        if (currentFocus.tagName === 'INPUT' && currentFocus.type === 'text') {
                            const length = currentFocus.value.length;
                            currentFocus.setSelectionRange(length, length);
                        }
                    }
                } catch (e) {
                    // Focus restoration failed
                }
            }, 50);
        }
        
        // Try Livewire updates as backup
        try {
            if (window.Livewire) {
                if (window.Livewire.dispatch) {
                    window.Livewire.dispatch('updateTotalPayment', { total: totalAmount });
                }
                
                if (window.Livewire.all && window.Livewire.all().length > 0) {
                    const component = window.Livewire.all()[0];
                    if (component.set) {
                        component.set('data.total_payment', totalAmount);
                    }
                }
            }
        } catch (e) {
            // Livewire update failed
        }
    }
}

// Function to handle checkbox changes
function handleInvoiceCheckboxChange(checkbox) {
    const row = checkbox.closest('tr');
    const receiptInput = row.querySelector('.receipt-input');
    const remaining = parseFloat(checkbox.dataset.remaining || 0);
    
    if (checkbox.checked) {
        // Check payment mode
        const paymentMode = document.querySelector('.payment-mode-radio:checked')?.value || 'full';
        
        if (paymentMode === 'full') {
            // For full payment, auto-fill with remaining amount as integer
            if (!receiptInput.value || parseFloat(receiptInput.value) === 0) {
                receiptInput.value = formatAsInteger(remaining);
            }
        }
        // For partial payment, don't auto-fill
    } else {
        // Clear receipt amount when unchecked
        receiptInput.value = '';
    }
    
    // Trigger receipt input events
    ['input', 'change'].forEach(eventType => {
        receiptInput.dispatchEvent(new Event(eventType, { bubbles: true }));
    });
    
    // Update selected invoices and total
    updateSelectedInvoices();
}

// Function to handle receipt input changes
function handleReceiptInputChange(input, eventType) {
    const invoiceId = input.getAttribute('data-invoice-id');
    const rawValue = input.value;
    
    // Store cursor position
    const cursorPosition = input.selectionStart;
    
    // Parse the value but don't format immediately if user is typing
    let cleanValue = parseReceiptValue(rawValue);
    
    // Only format/update the input value on blur or when value is finalized
    if (eventType === 'blur' || eventType === 'change') {
        // Update input with clean integer value only on blur/change
        const formattedValue = cleanValue > 0 ? cleanValue.toString() : '';
        if (input.value !== formattedValue) {
            input.value = formattedValue;
        }
    }
    
    const checkbox = document.querySelector(`.invoice-checkbox[value="${invoiceId}"]`);
    
    if (checkbox) {
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        const remainingInteger = Math.round(remaining);
        
        // Validate amount doesn't exceed remaining
        if (cleanValue > remainingInteger) {
            alert(`Pembayaran tidak boleh melebihi sisa tagihan: Rp. ${remainingInteger.toLocaleString('id-ID')}`);
            input.value = remainingInteger.toString();
            cleanValue = remainingInteger;
        }
        
        // Auto-check/uncheck checkbox based on amount
        if (cleanValue > 0 && !checkbox.checked) {
            checkbox.checked = true;
        } else if (cleanValue === 0 && checkbox.checked) {
            checkbox.checked = false;
        }
    }
    
    // Use debounced update for input events, immediate update for change/blur
    if (eventType === 'input') {
        updateSelectedInvoicesDebounced();
    } else {
        updateSelectedInvoices();
    }
    
    // Restore cursor position for input events (not blur/change)
    if (eventType === 'input' && cursorPosition !== null) {
        setTimeout(() => {
            try {
                input.setSelectionRange(cursorPosition, cursorPosition);
            } catch (e) {
                // Cursor position restoration failed
            }
        }, 10);
    }
}

// Initialize event listeners
function initializeCustomerReceiptEvents() {
    // Prevent duplicate initialization
    if (window.customerReceiptEventsInitialized) {
        return true;
    }
    
    // Check if invoice checkboxes exist
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    if (checkboxes.length === 0) {
        return false;
    }
    
    window.customerReceiptEventsInitialized = true;
    
    // Add event listeners for invoice checkboxes
    checkboxes.forEach((checkbox, index) => {
        if (!checkbox.hasAttribute('data-events-attached')) {
            checkbox.setAttribute('data-events-attached', 'true');
            
            checkbox.addEventListener('change', function(e) {
                handleInvoiceCheckboxChange(this);
            });
            
            checkbox.addEventListener('click', function(e) {
                // Click handler
            });
        }
    });
    
    // Add event listeners for receipt inputs
    const receiptInputs = document.querySelectorAll('.receipt-input');
    
    receiptInputs.forEach((input, index) => {
        if (!input.hasAttribute('data-events-attached')) {
            input.setAttribute('data-events-attached', 'true');
            
            // Handle input event (while typing)
            input.addEventListener('input', function() {
                handleReceiptInputChange(this, 'input');
            });
            
            // Handle change event (when value is committed)
            input.addEventListener('change', function() {
                handleReceiptInputChange(this, 'change');
            });
            
            // Handle blur event (when focus leaves field)
            input.addEventListener('blur', function() {
                handleReceiptInputChange(this, 'blur');
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
    
    // Add event listeners for payment mode radios
    const paymentModeRadios = document.querySelectorAll('.payment-mode-radio');
    
    paymentModeRadios.forEach(radio => {
        if (!radio.hasAttribute('data-events-attached')) {
            radio.setAttribute('data-events-attached', 'true');
            radio.addEventListener('change', function() {
                handlePaymentModeChange(this.value);
            });
        }
    });
    
    return true;
}

// Handle payment mode changes
function handlePaymentModeChange(mode) {
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    
    if (mode === 'full') {
        // Auto-fill checked invoices with remaining amounts as integers
        checkboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.receipt-input');
            const remaining = parseFloat(checkbox.dataset.remaining || 0);
            
            receiptInput.value = formatAsInteger(remaining);
        });
    }
    // For partial mode, don't change existing values
    
    updateSelectedInvoices();
}

// Make functions globally available
window.updateSelectedInvoices = updateSelectedInvoices;
window.updateSelectedInvoicesMain = updateSelectedInvoices;
window.handleInvoiceCheckboxChange = handleInvoiceCheckboxChange;
window.handleReceiptInputChange = handleReceiptInputChange;
window.initializeCustomerReceiptEvents = initializeCustomerReceiptEvents;

// Initialize with retry mechanism
function tryInitialize() {
    const success = initializeCustomerReceiptEvents();
    if (!success) {
        setTimeout(tryInitialize, 1000);
    }
}

// Start initialization
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(tryInitialize, 500);
    });
} else {
    setTimeout(tryInitialize, 500);
}
</script>
