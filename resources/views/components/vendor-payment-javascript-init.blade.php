{{-- Vendor Payment JavaScript for invoice selection and payment calculation --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        let lastUpdateTime = 0;
        const DEBOUNCE_DELAY = 300; // ms
        let debounceTimeout = null;
        
        window.updateTotalPaymentField = function(preserveFocus = false) {
            const currentTime = Date.now();
            
            // Clear existing timeout
            if (debounceTimeout) {
                clearTimeout(debounceTimeout);
            }
            
            // Debounce the update
            debounceTimeout = setTimeout(() => {
                try {
                    const totalPaymentInput = document.querySelector('input[wire\\:model="data.total_payment"]');
                    if (!totalPaymentInput) return;

                    // Store focus state
                    const activeElement = document.activeElement;
                    const shouldPreserveFocus = preserveFocus && 
                        activeElement && 
                        (activeElement.matches('input[type="number"]') || activeElement.matches('input[type="text"]'));
                    
                    let selectionStart = null;
                    let selectionEnd = null;
                    if (shouldPreserveFocus && activeElement.setSelectionRange) {
                        selectionStart = activeElement.selectionStart;
                        selectionEnd = activeElement.selectionEnd;
                    }

                    // Get data from Livewire component or calculate from DOM
                    let totalPayment = 0;
                    const livewireComponent = window.Livewire?.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                    
                    if (livewireComponent && livewireComponent.get('data.invoice_receipts')) {
                        const invoiceReceipts = livewireComponent.get('data.invoice_receipts');
                        if (Array.isArray(invoiceReceipts)) {
                            totalPayment = invoiceReceipts.reduce((sum, receipt) => {
                                const amount = parseFloat(receipt.payment_amount) || 0;
                                return sum + amount;
                            }, 0);
                        }
                    } else {
                        // Fallback: calculate from DOM
                        document.querySelectorAll('.vendor-invoice-checkbox:checked').forEach(checkbox => {
                            const row = checkbox.closest('tr');
                            const receiptInput = row.querySelector('.vendor-receipt-input');
                            const receiptAmount = parseFloat(receiptInput.value || 0);
                            totalPayment += receiptAmount;
                        });
                    }

                    const formattedTotal = totalPayment.toLocaleString('id-ID', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });

                    if (totalPaymentInput.value !== formattedTotal) {
                        totalPaymentInput.value = formattedTotal;
                        totalPaymentInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    // Restore focus and cursor position
                    if (shouldPreserveFocus && document.body.contains(activeElement)) {
                        activeElement.focus();
                        if (activeElement.setSelectionRange && selectionStart !== null) {
                            activeElement.setSelectionRange(selectionStart, selectionEnd);
                        }
                    }

                    lastUpdateTime = currentTime;
                } catch (error) {
                    console.error('Error updating total payment field:', error);
                }
            }, DEBOUNCE_DELAY);
        };

        // Handle payment input changes with event type awareness
        window.handlePaymentInputChange = function(element, event) {
            const eventType = event?.type || 'unknown';
            
            // Skip updates for keyboard input events to prevent cursor jumping
            if (eventType === 'input' || eventType === 'keydown' || eventType === 'keyup') {
                const timeSinceLastUpdate = Date.now() - lastUpdateTime;
                if (timeSinceLastUpdate < DEBOUNCE_DELAY) {
                    return; // Skip if we just updated recently
                }
            }
            
            updateTotalPaymentField(true);
        };

        // Set up mutation observer for Livewire updates and re-initialize event listeners
        const observer = new MutationObserver(function(mutations) {
            let shouldUpdate = false;
            let shouldReinitialize = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Check if invoice table was added/updated
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            if (node.classList && node.classList.contains('vendor-invoice-table-container') ||
                                node.querySelector && node.querySelector('.vendor-invoice-checkbox')) {
                                shouldReinitialize = true;
                            }
                        }
                    });
                    shouldUpdate = true;
                } else if (mutation.type === 'attributes' && 
                     (mutation.attributeName === 'wire:model' || mutation.attributeName === 'value')) {
                    shouldUpdate = true;
                }
            });
            
            if (shouldReinitialize) {
                console.log('Reinitializing vendor payment event listeners...');
                initializeVendorEventListeners();
            } else if (shouldUpdate) {
                updateTotalPaymentField(false);
            }
        });

        // Function to initialize event listeners
        window.initializeVendorEventListeners = function() {
            // Remove existing listeners to prevent duplicates
            document.querySelectorAll('.vendor-invoice-checkbox').forEach(checkbox => {
                checkbox.removeEventListener('change', checkbox._vendorChangeHandler);
            });
            
            // Add new event listeners
            document.querySelectorAll('.vendor-invoice-checkbox').forEach(checkbox => {
                checkbox._vendorChangeHandler = function() {
                    handleVendorCheckboxChange(this);
                    updateSelectedVendorInvoices();
                    
                    // Auto-fill adjustment COA when invoice is selected
                    setTimeout(() => {
                        autoFillVendorAdjustmentCOA();
                    }, 100);
                };
                checkbox.addEventListener('change', checkbox._vendorChangeHandler);
            });
            
            // Initialize receipt inputs
            document.querySelectorAll('.vendor-receipt-input').forEach(input => {
                input.removeEventListener('input', input._vendorInputHandler);
                input.removeEventListener('change', input._vendorChangeHandler);
                
                input._vendorInputHandler = function() {
                    const invoiceId = this.closest('tr').querySelector('.vendor-invoice-checkbox').value;
                    updateVendorReceiptAmount(invoiceId, this.value);
                };
                input._vendorChangeHandler = input._vendorInputHandler;
                
                input.addEventListener('input', input._vendorInputHandler);
                input.addEventListener('change', input._vendorChangeHandler);
            });
            
            console.log('Vendor payment event listeners initialized');
        };

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['wire:model', 'value']
        });

        // Livewire event listeners
        if (window.Livewire) {
            Livewire.on('invoiceDataUpdated', function() {
                updateTotalPaymentField(false);
            });
            
            Livewire.on('paymentCalculated', function() {
                updateTotalPaymentField(false);
            });
        }

        // Add vendor-specific functions for checkbox handling
        window.handleVendorCheckboxChange = function(checkbox) {
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
        };

        window.updateSelectedVendorInvoices = function() {
            const selectedIds = [];
            const invoiceReceipts = [];
            
            document.querySelectorAll('.vendor-invoice-checkbox:checked').forEach(checkbox => {
                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.vendor-receipt-input');
                const adjustmentInput = row.querySelector('.vendor-adjustment-input');
                const adjustmentDescInput = row.querySelector('.vendor-adjustment-desc-input');
                const balanceInput = row.querySelector('.vendor-balance-input');
                
                const invoiceId = checkbox.value;
                const paymentAmount = parseFloat(receiptInput.value || 0);
                const adjustmentAmount = parseFloat(adjustmentInput?.value || 0);
                const adjustmentDescription = adjustmentDescInput?.value || '';
                const balanceAmount = parseFloat(balanceInput?.value || 0);
                
                selectedIds.push(invoiceId);
                invoiceReceipts.push({
                    invoice_id: invoiceId,
                    payment_amount: paymentAmount,
                    adjustment_amount: adjustmentAmount,
                    adjustment_description: adjustmentDescription,
                    balance_amount: balanceAmount
                });
            });
            
            console.log('Selected vendor invoices:', selectedIds);
            console.log('Vendor invoice receipts:', invoiceReceipts);
            
            // Update hidden fields with JSON data for form submission
            const selectedInvoicesSelectors = [
                'input[wire\\:model="data.selected_invoices"]',
                'input[wire\\:model="selected_invoices"]',
                'input[name="selected_invoices"]',
                '[data-field="selected_invoices"] input'
            ];
            
            let selectedInvoicesField = null;
            for (const selector of selectedInvoicesSelectors) {
                selectedInvoicesField = document.querySelector(selector);
                if (selectedInvoicesField) {
                    console.log('Found selected_invoices field with selector:', selector);
                    break;
                }
            }
            
            if (selectedInvoicesField) {
                selectedInvoicesField.value = JSON.stringify(selectedIds);
                selectedInvoicesField.dispatchEvent(new Event('input', { bubbles: true }));
                selectedInvoicesField.dispatchEvent(new Event('change', { bubbles: true }));
                console.log('Updated selected_invoices field:', selectedInvoicesField.value);
            } else {
                console.warn('Selected invoices field not found with any selector');
            }
            
            const invoiceReceiptsSelectors = [
                'input[wire\\:model="data.invoice_receipts"]',
                'input[wire\\:model="invoice_receipts"]',
                'input[name="invoice_receipts"]',
                '[data-field="invoice_receipts"] input'
            ];
            
            let invoiceReceiptsField = null;
            for (const selector of invoiceReceiptsSelectors) {
                invoiceReceiptsField = document.querySelector(selector);
                if (invoiceReceiptsField) {
                    console.log('Found invoice_receipts field with selector:', selector);
                    break;
                }
            }
            
            if (invoiceReceiptsField) {
                invoiceReceiptsField.value = JSON.stringify(invoiceReceipts);
                invoiceReceiptsField.dispatchEvent(new Event('input', { bubbles: true }));
                invoiceReceiptsField.dispatchEvent(new Event('change', { bubbles: true }));
                console.log('Updated invoice_receipts field:', invoiceReceiptsField.value);
            } else {
                console.warn('Invoice receipts field not found with any selector');
            }
            
            // Also notify Livewire via event so server-side fallbacks can use it
            try {
                if (window.Livewire && typeof window.Livewire.emit === 'function') {
                    window.Livewire.emit('updateVendorInvoiceData', JSON.stringify(selectedIds), JSON.stringify(invoiceReceipts));
                    console.log('Emitted updateVendorInvoiceData event');
                }
            } catch (e) {
                console.warn('Failed to emit updateVendorInvoiceData', e);
            }

            // Optionally try Livewire method call as additional update
            if (window.Livewire) {
                const livewireComponent = window.Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                
                if (livewireComponent) {
                    try {
                        if (typeof livewireComponent.set === 'function') {
                            livewireComponent.set('data.selected_invoices', JSON.stringify(selectedIds));
                            livewireComponent.set('data.invoice_receipts', JSON.stringify(invoiceReceipts));
                            
                            console.log('Data also set to Livewire component');
                        }
                    } catch (error) {
                        console.warn('Could not update Livewire component directly:', error);
                    }
                }
            }
            
            // Calculate total payment
            calculateVendorTotalPayment();
        };

        window.updateVendorReceiptAmount = function(invoiceId, amount) {
            let numericAmount = parseFloat(amount) || 0;
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
                // Invoice sudah tercentang dan nilai berubah: segarkan invoiceReceipts
                updateSelectedVendorInvoices();
            }
            
            // Auto-fill adjustment COA if there's a balance
            if (balance > 0) {
                autoFillVendorAdjustmentCOA();
            }
        };

        window.calculateVendorTotalPayment = function() {
            let total = 0;
            const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
            
            checkboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.vendor-receipt-input');
                const receiptAmount = parseFloat(receiptInput.value || 0);
                total += receiptAmount;
            });
            
            console.log('Calculated total payment:', total);
            
            // Update total payment field in the main form - try multiple selectors
            const totalPaymentSelectors = [
                'input[wire\\:model="data.total_payment"]',
                'input[name="total_payment"]',
                '[data-field="total_payment"] input'
            ];
            
            let totalPaymentField = null;
            for (const selector of totalPaymentSelectors) {
                totalPaymentField = document.querySelector(selector);
                if (totalPaymentField) {
                    console.log('Found total payment field with selector:', selector);
                    break;
                }
            }
            
            if (totalPaymentField) {
                totalPaymentField.value = total;
                totalPaymentField.dispatchEvent(new Event('input', { bubbles: true }));
                totalPaymentField.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Also update Livewire component directly if available
                if (window.Livewire) {
                    const livewireComponent = window.Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                    if (livewireComponent && typeof livewireComponent.set === 'function') {
                        try {
                            livewireComponent.set('data.total_payment', total);
                            console.log('Updated Livewire total_payment:', total);
                        } catch (error) {
                            console.warn('Could not update Livewire total_payment:', error);
                        }
                    }
                }
                
                console.log('Total payment field updated to:', total);
            } else {
                console.warn('Total payment field not found with any selector');
            }
            
            console.log('Vendor total payment calculated:', formatVendorRupiah(total));
        };

        window.formatVendorRupiah = function(amount) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(amount);
        };

        window.autoFillVendorAdjustmentCOA = function() {
            // Auto-fill adjustment COA based on main COA selection
            const mainCoaField = document.querySelector('[name="coa_id"] select') || 
                                document.querySelector('[name="coa_id"]') ||
                                document.querySelector('#main-coa-field');
            
            if (mainCoaField && mainCoaField.value) {
                const adjustmentSelects = document.querySelectorAll('.vendor-adjustment-coa-select');
                adjustmentSelects.forEach(select => {
                    if (!select.value) {
                        select.value = mainCoaField.value;
                        select.dispatchEvent(new Event('change'));
                    }
                });
            }
        };

        window.toggleAllVendorInvoices = function(selectAllCheckbox) {
            const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
                handleVendorCheckboxChange(checkbox);
            });
            updateSelectedVendorInvoices();
        };

        // Initialize event listeners for existing checkboxes
        initializeVendorEventListeners();

        // Add change event listener to main COA field - try multiple selectors
        const mainCoaFieldSelectors = [
            '#main-coa-field',
            '[name="coa_id"] select',
            '[name="coa_id"]',
            '[data-field="coa_id"] select'
        ];
        
        mainCoaFieldSelectors.forEach(selector => {
            const field = document.querySelector(selector);
            if (field) {
                field.addEventListener('change', function() {
                    setTimeout(() => {
                        autoFillVendorAdjustmentCOA();
                    }, 100);
                });
            }
        });

        // Auto-fill adjustment COA on page load
        setTimeout(() => {
            autoFillVendorAdjustmentCOA();
            initializeVendorEventListeners(); // Re-initialize after DOM is fully loaded
        }, 1000);

        // Initial calculation
        setTimeout(() => {
            updateTotalPaymentField(false);
        }, 500);
        
        console.log('Vendor Payment JavaScript initialized successfully');
    });
</script>

<style>
    /* Vendor Payment specific styles */
    .vendor-payment-form {
        position: relative;
    }
    
    .vendor-payment-form input[type="number"]:focus,
    .vendor-payment-form input[type="text"]:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    .vendor-payment-readonly {
        background-color: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
    }
    
    .vendor-payment-json {
        font-family: 'Fira Code', 'Monaco', 'Consolas', monospace;
        font-size: 12px;
        line-height: 1.4;
        background-color: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 8px;
        white-space: pre-wrap;
        word-break: break-all;
        max-height: 120px;
        overflow-y: auto;
    }
</style>
