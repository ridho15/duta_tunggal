<script>
console.log('=== ROBUST INVOICE SELECTION HANDLER ===');

// Global variables to store invoice data
window.customerReceiptInvoiceData = {
    selectedInvoices: [],
    invoiceReceipts: {},
    totalPayment: 0
};

// Function to update global data and form fields
function updateInvoiceSelectionData() {
    const selectedIds = [];
    const invoiceReceipts = {};
    let totalAmount = 0;
    
    // Get all checked invoices
    const checkboxes = document.querySelectorAll('.invoice-checkbox:checked');
    console.log('Found checked invoices:', checkboxes.length);
    
    checkboxes.forEach(checkbox => {
        const invoiceId = parseInt(checkbox.value);
        selectedIds.push(invoiceId);
        
        // Get receipt amount for this invoice
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.receipt-input');
        const receiptAmount = parseFloat(receiptInput.value || 0);
        invoiceReceipts[invoiceId] = receiptAmount;
        totalAmount += receiptAmount;
    });
    
    // Update global data
    window.customerReceiptInvoiceData.selectedInvoices = selectedIds;
    window.customerReceiptInvoiceData.invoiceReceipts = invoiceReceipts;
    window.customerReceiptInvoiceData.totalPayment = totalAmount;
    
    console.log('Updated global invoice data:', window.customerReceiptInvoiceData);
    
    // Update form fields with multiple methods
    updateFormFields();
    
    // Update total payment field
    updateTotalPaymentField(totalAmount);
}

// Function to update form fields
function updateFormFields() {
    const data = window.customerReceiptInvoiceData;
    
    // Method 1: Find and update hidden/text fields using safer selectors
    const selectors = [
        '[name="selected_invoices"]',
        'input[name="selected_invoices"]'
    ];
    
    let selectedInvoicesField = null;
    for (const selector of selectors) {
        try {
            selectedInvoicesField = document.querySelector(selector);
            if (selectedInvoicesField) break;
        } catch (e) {
            console.log('Selector failed:', selector, e.message);
        }
    }
    
    // Alternative: Find by attribute search
    if (!selectedInvoicesField) {
        const allInputs = document.querySelectorAll('input, textarea');
        for (const input of allInputs) {
            if (input.name === 'selected_invoices' || 
                input.getAttribute('wire:model') === 'selected_invoices' ||
                input.getAttribute('wire:model.defer') === 'selected_invoices') {
                selectedInvoicesField = input;
                break;
            }
        }
    }
    
    if (selectedInvoicesField) {
        selectedInvoicesField.value = JSON.stringify(data.selectedInvoices);
        // Dispatch events
        ['input', 'change', 'blur'].forEach(eventType => {
            selectedInvoicesField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        console.log('Updated selected_invoices field:', selectedInvoicesField.value);
    }
    
    // Same for invoice_receipts using safer selectors
    const receiptSelectors = [
        '[name="invoice_receipts"]',
        'input[name="invoice_receipts"]'
    ];
    
    let invoiceReceiptsField = null;
    for (const selector of receiptSelectors) {
        try {
            invoiceReceiptsField = document.querySelector(selector);
            if (invoiceReceiptsField) break;
        } catch (e) {
            console.log('Selector failed:', selector, e.message);
        }
    }
    
    // Alternative: Find by attribute search
    if (!invoiceReceiptsField) {
        const allInputs = document.querySelectorAll('input, textarea');
        for (const input of allInputs) {
            if (input.name === 'invoice_receipts' || 
                input.getAttribute('wire:model') === 'invoice_receipts' ||
                input.getAttribute('wire:model.defer') === 'invoice_receipts') {
                invoiceReceiptsField = input;
                break;
            }
        }
    }
    
    if (invoiceReceiptsField) {
        invoiceReceiptsField.value = JSON.stringify(data.invoiceReceipts);
        // Dispatch events
        ['input', 'change', 'blur'].forEach(eventType => {
            invoiceReceiptsField.dispatchEvent(new Event(eventType, { bubbles: true }));
        });
        console.log('Updated invoice_receipts field:', invoiceReceiptsField.value);
    }
    
    // Method 2: Try Livewire direct update
    if (window.Livewire && window.Livewire.all && window.Livewire.all().length > 0) {
        try {
            const component = window.Livewire.all()[0];
            if (component.set) {
                component.set('selected_invoices', data.selectedInvoices);
                component.set('invoice_receipts', data.invoiceReceipts);
                console.log('Livewire component updated');
            }
        } catch (e) {
            console.log('Livewire update failed:', e.message);
        }
    }
}

// Function to update total payment field
function updateTotalPaymentField(total) {
    const totalField = document.querySelector('[name="total_payment"]');
    if (totalField) {
        totalField.value = total;
        totalField.dispatchEvent(new Event('input', { bubbles: true }));
        console.log('Updated total_payment field:', total);
    }
}

// Enhanced event listeners
function setupEnhancedEventListeners() {
    console.log('Setting up enhanced event listeners...');
    
    // Setup checkbox listeners
    document.addEventListener('change', function(event) {
        if (event.target.classList.contains('invoice-checkbox')) {
            console.log('Invoice checkbox changed:', event.target.value, event.target.checked);
            updateInvoiceSelectionData();
        }
    });
    
    // Setup receipt input listeners
    document.addEventListener('input', function(event) {
        if (event.target.classList.contains('receipt-input')) {
            console.log('Receipt input changed:', event.target.getAttribute('data-invoice-id'), event.target.value);
            updateInvoiceSelectionData();
        }
    });
    
    // Setup form submission intercept
    document.addEventListener('submit', function(event) {
        console.log('=== FORM SUBMISSION INTERCEPTED ===');
        
        // Force update one final time
        updateInvoiceSelectionData();
        
        // Add data to form manually if needed
        const form = event.target;
        if (form) {
            const formData = new FormData(form);
            formData.set('selected_invoices', JSON.stringify(window.customerReceiptInvoiceData.selectedInvoices));
            formData.set('invoice_receipts', JSON.stringify(window.customerReceiptInvoiceData.invoiceReceipts));
            
            console.log('Form data prepared:', {
                selected_invoices: JSON.stringify(window.customerReceiptInvoiceData.selectedInvoices),
                invoice_receipts: JSON.stringify(window.customerReceiptInvoiceData.invoiceReceipts)
            });
        }
    });
    
    console.log('Enhanced event listeners setup complete');
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupEnhancedEventListeners);
} else {
    setupEnhancedEventListeners();
}

// Also setup after a delay to catch dynamic content
setTimeout(setupEnhancedEventListeners, 1000);
setTimeout(setupEnhancedEventListeners, 3000);

console.log('Robust invoice selection handler loaded');
</script>
