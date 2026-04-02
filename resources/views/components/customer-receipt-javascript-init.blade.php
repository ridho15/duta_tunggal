<script>
// Main function to update selected invoices and total payment
function updateSelectedInvoices(preferredCheckbox = null) {
    if (!preferredCheckbox && window.__customerReceiptPreferredInvoiceCheckbox) {
        preferredCheckbox = window.__customerReceiptPreferredInvoiceCheckbox;
    }

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

    syncInvoiceSelectionFields(selectedIds, invoiceReceipts);

    setTimeout(() => {
        syncCabangFromSelectedInvoices(checkboxes, preferredCheckbox);
    }, 250);
    
    // Update total payment field with focus preservation
    updateTotalPaymentField(totalPaymentAmount, true);
}

function syncInvoiceSelectionFields(selectedIds, invoiceReceipts) {
    const selectedInvoicesValue = JSON.stringify(selectedIds);
    const invoiceReceiptsValue = JSON.stringify(invoiceReceipts);

    const fieldConfigs = [
        {
            value: selectedInvoicesValue,
            selectors: [
                '#data\\.selected_invoices',
                'input[wire\\:model="data.selected_invoices"]',
                'input[wire\\:model\.live="data.selected_invoices"]',
                'input[name="selected_invoices"]',
            ],
        },
        {
            value: invoiceReceiptsValue,
            selectors: [
                '#data\\.invoice_receipts',
                'input[wire\\:model="data.invoice_receipts"]',
                'input[wire\\:model\.live="data.invoice_receipts"]',
                'input[name="invoice_receipts"]',
            ],
        },
    ];

    fieldConfigs.forEach(({ value, selectors }) => {
        for (const selector of selectors) {
            const field = document.querySelector(selector);

            if (!field) {
                continue;
            }

            field.value = value;
            ['input', 'change', 'blur'].forEach((eventType) => {
                field.dispatchEvent(new Event(eventType, { bubbles: true }));
            });

            break;
        }
    });

    const selectedInvoicesField = document.querySelector('#data\\.selected_invoices');
    let componentRoot = selectedInvoicesField;

    while (componentRoot) {
        if (componentRoot.hasAttribute && componentRoot.hasAttribute('wire:id')) {
            break;
        }

        componentRoot = componentRoot.parentElement;
    }

    const rootComponentId = componentRoot ? componentRoot.getAttribute('wire:id') : null;
    const rootComponent = rootComponentId && window.Livewire?.find ? window.Livewire.find(rootComponentId) : null;

    if (rootComponent && typeof rootComponent.set === 'function') {
        rootComponent.set('data.selected_invoices', selectedIds);
        rootComponent.set('data.invoice_receipts', invoiceReceipts);
    }
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
    // Dots are thousand separators in Indonesian format (e.g. "1.000.000")
    // Strip them first so parseFloat does not stop at the second dot
    const cleanValue = inputValue.toString().replace(/\./g, '').replace(/[^\d]/g, '');
    return parseInt(cleanValue, 10) || 0;
}

function formatRupiahAmount(value) {
    const numericValue = parseFloat(value) || 0;
    return Math.round(numericValue).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function syncCabangFromSelectedInvoices(checkboxes = document.querySelectorAll('.invoice-checkbox:checked'), preferredCheckbox = null) {
    const selectedCheckboxes = Array.from(checkboxes);

    if (selectedCheckboxes.length === 0) {
        return;
    }

    const preferredCabangId = preferredCheckbox && preferredCheckbox.checked
        ? preferredCheckbox.dataset.cabangId
        : null;

    const cabangId = preferredCabangId || selectedCheckboxes
        .map((checkbox) => checkbox.dataset.cabangId)
        .find((value) => value !== undefined && value !== null && value !== '');

    if (!cabangId) {
        return;
    }

    const normalizedCabangId = cabangId.toString();

    updateCabangFieldValue(normalizedCabangId);
    setLivewireFieldValue('cabang_id', normalizedCabangId);
}

function updateCabangFieldValue(value) {
    const selectors = [
        '#data\\.cabang_id',
        '[id="data.cabang_id"]',
        'select[name="data.cabang_id"]',
        'select[name="cabang_id"]',
        '[wire\\:model="data.cabang_id"]',
        '[wire\\:model\.live="data.cabang_id"]',
        '[data-field="cabang_id"] select',
        '[data-field="cabang_id"] [x-ref="input"]',
    ];

    let cabangField = null;

    for (const selector of selectors) {
        try {
            cabangField = document.querySelector(selector);
            if (cabangField) {
                break;
            }
        } catch (error) {
            // Continue searching.
        }
    }

    if (!cabangField) {
        updateField('cabang_id', value);
        return false;
    }

    if (cabangField.tagName === 'SELECT') {
        const matchingOption = Array.from(cabangField.options).find((option) => option.value === value);

        if (matchingOption) {
            cabangField.value = value;
            matchingOption.selected = true;
        } else {
            cabangField.value = value;
        }

        ['input', 'change', 'blur'].forEach((eventType) => {
            cabangField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });

        const choicesContainer = cabangField.closest('.choices');
        if (choicesContainer) {
            const selectedLabel = choicesContainer.querySelector('.choices__inner .choices__item--selectable');

            if (selectedLabel && matchingOption) {
                selectedLabel.textContent = matchingOption.textContent;
                selectedLabel.dataset.value = matchingOption.value;
            }
        }
    }

    updateField('cabang_id', value);

    return true;
}

function setLivewireFieldValue(fieldName, value) {
    const selectors = [
        `[data-field="${fieldName}"]`,
        `#data\\.${fieldName}`,
        `[name="data.${fieldName}"]`,
        `select[name="data.${fieldName}"]`,
        `input[name="data.${fieldName}"]`,
        `input[name="${fieldName}"]`,
        `input[type="hidden"][id="data.${fieldName}"]`,
        `[wire\\:model="data.${fieldName}"]`,
        `[wire\\:model\.live="data.${fieldName}"]`,
        `[wire\\:model\.defer="data.${fieldName}"]`,
        `input[wire\\:model.live="data.${fieldName}"]`,
        `select[wire\\:model.live="data.${fieldName}"]`,
    ];

    let field = null;

    for (const selector of selectors) {
        try {
            field = document.querySelector(selector);
            if (field) {
                break;
            }
        } catch (error) {
            // Continue searching.
        }
    }

    if (!field) {
        return false;
    }

    if ('value' in field) {
        field.value = value;

        ['input', 'change', 'blur'].forEach((eventType) => {
            field.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
    }

    let componentRoot = field;

    while (componentRoot) {
        if (componentRoot.hasAttribute && componentRoot.hasAttribute('wire:id')) {
            break;
        }

        componentRoot = componentRoot.parentElement;
    }

    const componentId = componentRoot ? componentRoot.getAttribute('wire:id') : null;
    const component = componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;

    if (component && typeof component.set === 'function') {
        component.set(`data.${fieldName}`, value);
        return true;
    }

    if (field._x_model && typeof field._x_model.set === 'function') {
        field._x_model.set(value);
        return true;
    }

    return false;
}

function normalizeReceiptInputValue(input) {
    const rawValue = input.value;

    if (!rawValue) {
        return 0;
    }

    const parsedValue = parseReceiptValue(rawValue);
    const formattedValue = formatRupiahAmount(parsedValue);

    if (input.value !== formattedValue) {
        input.value = formattedValue;
    }

    return parsedValue;
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
        if (field._x_model && typeof field._x_model.set === 'function') {
            field._x_model.set(value);
        }

        if (field.tagName === 'SELECT') {
            const matchingOption = Array.from(field.options).find((option) => option.value === value);

            if (matchingOption) {
                field.value = matchingOption.value;
                matchingOption.selected = true;

                Array.from(field.options).forEach((option) => {
                    if (option !== matchingOption) {
                        option.selected = false;
                    }
                });
            } else {
                field.value = value;
            }
        } else {
            field.value = value;
        }
        
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

        try {
            if (window.Livewire) {
                const livewireFieldName = fieldName.startsWith('data.') ? fieldName : `data.${fieldName}`;
                let componentRoot = field;

                while (componentRoot) {
                    if (componentRoot.hasAttribute && componentRoot.hasAttribute('wire:id')) {
                        break;
                    }

                    componentRoot = componentRoot.parentElement;
                }

                const componentId = componentRoot ? componentRoot.getAttribute('wire:id') : null;
                const component = componentId && window.Livewire.find ? window.Livewire.find(componentId) : (window.Livewire.all && window.Livewire.all().length > 0 ? window.Livewire.all()[0] : null);
                const valueCandidates = [
                    () => component && component.set && component.set(livewireFieldName, value),
                    () => component && component.$wire && component.$wire.set && component.$wire.set(livewireFieldName, value),
                    () => component && component.$wire && component.$wire.$set && component.$wire.$set(livewireFieldName, value),
                    () => component && component.$set && component.$set(livewireFieldName, value),
                ];

                for (const update of valueCandidates) {
                    try {
                        const result = update();
                        if (result !== undefined) {
                            break;
                        }
                    } catch (candidateError) {
                        // Try the next Livewire setter shape.
                    }
                }
            }
        } catch (e) {
            // Livewire backup update failed
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
        const formattedTotal = formatRupiahAmount(totalAmount);
        totalPaymentField.value = formattedTotal;
        
        // Trigger comprehensive events for Filament/Livewire reactivity
        const events = ['input', 'change'];
        events.forEach(eventType => {
            totalPaymentField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        
        // Additional Filament-specific events
        totalPaymentField.dispatchEvent(new CustomEvent('wire:model', { 
            bubbles: true, 
            detail: { value: formattedTotal } 
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
                        component.set('data.total_payment', formattedTotal);
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
    window.__customerReceiptPreferredInvoiceCheckbox = checkbox;

    const row = checkbox.closest('tr');
    const receiptInput = row.querySelector('.receipt-input');
    
    if (checkbox.checked) {
        const remaining = parseFloat(checkbox.dataset.remaining || 0);

        if (!receiptInput.value || parseReceiptValue(receiptInput.value) === 0) {
            receiptInput.value = formatRupiahAmount(remaining);
        }
    } else {
        // Clear receipt amount when unchecked
        receiptInput.value = '';
    }
    
    // Trigger receipt input events
    ['input', 'change'].forEach(eventType => {
        receiptInput.dispatchEvent(new Event(eventType, { bubbles: true }));
    });
    
    // Update selected invoices and total
    updateSelectedInvoices(checkbox);
}

// Function to handle receipt input changes
function handleReceiptInputChange(input, eventType) {
    const invoiceId = input.getAttribute('data-invoice-id');
    let cleanValue = normalizeReceiptInputValue(input);
    
    const checkbox = document.querySelector(`.invoice-checkbox[value="${invoiceId}"]`);
    
    if (checkbox) {
        const remaining = parseFloat(checkbox.dataset.remaining || 0);
        const remainingInteger = Math.round(remaining);
        
        // Validate amount doesn't exceed remaining
        if (cleanValue > remainingInteger) {
            alert(`Pembayaran tidak boleh melebihi sisa tagihan: Rp. ${remainingInteger.toLocaleString('id-ID')}`);
            input.value = formatRupiahAmount(remainingInteger);
            cleanValue = remainingInteger;
        }
        
        // Auto-check/uncheck checkbox based on amount
        if (cleanValue > 0 && !checkbox.checked) {
            checkbox.checked = true;
        } else if (cleanValue === 0 && checkbox.checked) {
            checkbox.checked = false;
        }

        if (checkbox.checked) {
            window.__customerReceiptPreferredInvoiceCheckbox = checkbox;
        }
    }
    
    // Use debounced update for input events, immediate update for change/blur
    if (eventType === 'input') {
        updateSelectedInvoicesDebounced();
    } else {
        updateSelectedInvoices(checkbox);
    }
}

// Initialize event listeners
function initializeCustomerReceiptEvents() {
    // Check if invoice checkboxes exist
    const checkboxes = document.querySelectorAll('.invoice-checkbox');
    if (checkboxes.length === 0) {
        return false;
    }

    document.querySelectorAll('.receipt-input').forEach(input => {
        input.value = input.value ? formatRupiahAmount(parseReceiptValue(input.value)) : '';
    });

    document.querySelectorAll('.balance-input').forEach(input => {
        input.value = input.value === '' ? '' : formatRupiahAmount(parseReceiptValue(input.value));
    });
    
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
                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.receipt-input');
                const remaining = parseFloat(checkbox.dataset.remaining || 0);

                if (this.checked) {
                    receiptInput.value = formatRupiahAmount(remaining);
                } else {
                    receiptInput.value = '';
                }

                handleInvoiceCheckboxChange(checkbox);
            });
        });
    }
    
    return true;
}

function scheduleCustomerReceiptEventInit(delay = 100) {
    setTimeout(() => {
        initializeCustomerReceiptEvents();
    }, delay);
}

function initializeCustomerReceiptDelegatedEvents() {
    if (window.__customerReceiptDelegatedEventsAttached) {
        return;
    }

    window.__customerReceiptDelegatedEventsAttached = true;

    document.addEventListener('change', function (event) {
        const target = event.target;

        if (target && target.classList && target.classList.contains('invoice-checkbox')) {
            handleInvoiceCheckboxChange(target);
            return;
        }

        if (target && target.classList && target.classList.contains('receipt-input')) {
            handleReceiptInputChange(target, 'change');
        }
    });

    document.addEventListener('input', function (event) {
        const target = event.target;

        if (target && target.classList && target.classList.contains('receipt-input')) {
            handleReceiptInputChange(target, 'input');
        }
    });
}

// Make functions globally available
window.updateSelectedInvoices = updateSelectedInvoices;
window.updateSelectedInvoicesMain = updateSelectedInvoices;
window.parseReceiptValue = parseReceiptValue;
window.formatRupiahAmount = formatRupiahAmount;
window.handleInvoiceCheckboxChange = handleInvoiceCheckboxChange;
window.handleReceiptInputChange = handleReceiptInputChange;
window.initializeCustomerReceiptEvents = initializeCustomerReceiptEvents;
window.initializeCustomerReceiptDelegatedEvents = initializeCustomerReceiptDelegatedEvents;

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
        initializeCustomerReceiptDelegatedEvents();
        scheduleCustomerReceiptEventInit(500);
    });
} else {
    initializeCustomerReceiptDelegatedEvents();
    scheduleCustomerReceiptEventInit(500);
}

window.addEventListener('refreshInvoiceTable', function () {
    scheduleCustomerReceiptEventInit(150);
});

document.addEventListener('livewire:navigated', function () {
    scheduleCustomerReceiptEventInit(150);
});

document.addEventListener('livewire:update', function () {
    scheduleCustomerReceiptEventInit(150);
});
</script>
