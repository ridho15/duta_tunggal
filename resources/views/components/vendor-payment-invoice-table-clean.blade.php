@php
    // Helper function to safely check if invoice is selected
    $getSelectedInvoicesArray = function ($selectedInvoices) {
        if (is_array($selectedInvoices)) {
            return $selectedInvoices;
        }
        if (is_string($selectedInvoices)) {
            return json_decode($selectedInvoices, true) ?? [];
        }
        return [];
    };
    $selectedArray = $getSelectedInvoicesArray($selectedInvoices);
@endphp

@if (count($invoices) > 0)

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

        /* Fixed width table with horizontal scroll */
        .vendor-invoice-table-container {
            width: 100%;
            max-width: 1200px;
            /* Fixed maximum width */
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            margin: 0 auto;
            /* Center the container */
        }

        .vendor-invoice-table {
            width: 100%;
            min-width: 1000px;
            /* Minimum width to trigger horizontal scroll */
            white-space: nowrap;
        }

        .vendor-invoice-table th,
        .vendor-invoice-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .vendor-invoice-table th {
            background-color: #f9fafb;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .vendor-invoice-table tbody tr:hover {
            background-color: #f9fafb;
        }

        /* Column specific widths */
        .col-checkbox {
            width: 60px;
            min-width: 60px;
        }

        .col-invoice {
            width: 120px;
            min-width: 120px;
        }

        .col-total {
            width: 130px;
            min-width: 130px;
        }

        .col-receipt {
            width: 120px;
            min-width: 120px;
        }

        .col-sisa {
            width: 120px;
            min-width: 120px;
        }

        .col-adjustment {
            width: 140px;
            min-width: 140px;
        }

        .col-description {
            width: 180px;
            min-width: 180px;
        }

        /* Input styling */
        .vendor-receipt-input,
        .vendor-adjustment-input,
        .vendor-balance-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .vendor-adjustment-desc-input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 12px;
            box-sizing: border-box;
        }

        .vendor-balance-input {
            background-color: #f9fafb;
            color: #6b7280;
        }
    </style>

    <div class="vendor-invoice-table-container">
        <table class="vendor-invoice-table">
            <thead>
                <tr>
                    <th class="col-checkbox">
                        <input type="checkbox" id="select-all-vendor"
                            class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            onchange="toggleAllVendorInvoices(this)">
                    </th>
                    <th class="col-invoice">Invoice</th>
                    <th class="col-total">Total Invoice</th>
                    <th class="col-receipt">Pembayaran</th>
                    <th class="col-sisa">Sisa</th>
                    <th class="col-adjustment">Penyesuaian Sisa</th>
                    <th class="col-description">Keterangan Penyesuaian</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td class="col-checkbox">
                            <input type="checkbox"
                                class="vendor-invoice-checkbox rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                value="{{ $invoice['id'] }}"
                                data-remaining="{{ $invoice['remaining'] ?? $invoice['total'] }}"
                                wire:click="toggleInvoiceSelection({{ $invoice['id'] }})"
                                wire:checked="in_array({{ $invoice['id'] }}, $get('selected_invoices') ?? [])"
                                onchange="console.log('Checkbox onchange triggered for invoice:', this.value); handleVendorCheckboxChange(this); updateSelectedVendorInvoices()"
                                {{ in_array($invoice['id'], $selectedArray) ? 'checked' : '' }}>
                        </td>
                        <td class="col-invoice">
                            <div class="text-sm font-medium text-gray-900">
                                {{ $invoice['invoice_number'] }}
                            </div>
                        </td>
                        <td class="col-total">
                            <div class="text-sm text-gray-900">
                                Rp. {{ number_format($invoice['total'], 0, ',', '.') }}
                            </div>
                        </td>
                        <td class="col-receipt">
                            <input type="number" class="vendor-receipt-input"
                                value="{{ in_array($invoice['id'], $selectedArray) ? $invoice['remaining'] ?? $invoice['total'] : '' }}"
                                step="0.01" min="0" max="{{ $invoice['remaining'] ?? $invoice['total'] }}"
                                onchange="updateVendorReceiptAmount('{{ $invoice['id'] }}', this.value)"
                                oninput="updateVendorReceiptAmount('{{ $invoice['id'] }}', this.value)">
                        </td>
                        <td class="col-sisa">
                            <input type="number" class="vendor-balance-input"
                                value="{{ in_array($invoice['id'], $selectedArray) ? 0 : $invoice['remaining'] ?? $invoice['total'] }}"
                                readonly>
                        </td>
                        <td class="col-adjustment">
                            <input type="number" class="vendor-adjustment-input" step="0.01" min="0"
                                placeholder="0">
                        </td>
                        <td class="col-description">
                            <input type="text" class="vendor-adjustment-desc-input" placeholder="Keterangan..."
                                maxlength="100">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-8 text-gray-500">
        <p>{{ $message ?? 'Silakan pilih supplier terlebih dahulu untuk melihat invoice' }}</p>
    </div>
@endif

<script>
    function toggleAllVendorInvoices(checkbox) {
        const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            handleVendorCheckboxChange(cb);
        });
        updateSelectedVendorInvoices();
    }

    function handleVendorCheckboxChange(checkbox) {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        const balanceInput = row.querySelector('.vendor-balance-input');
        const adjustmentInput = row.querySelector('.vendor-adjustment-input');
        const descInput = row.querySelector('.vendor-adjustment-desc-input');

        if (checkbox.checked) {
            const remaining = parseFloat(checkbox.dataset.remaining) || 0;
            receiptInput.value = remaining;
            balanceInput.value = 0;
            adjustmentInput.disabled = false;
            descInput.disabled = false;
        } else {
            receiptInput.value = '';
            balanceInput.value = checkbox.dataset.remaining || '';
            adjustmentInput.value = '';
            adjustmentInput.disabled = true;
            descInput.value = '';
            descInput.disabled = true;
        }

        updateTotalPayment();
    }

    function updateVendorReceiptAmount(invoiceId, amount) {
        const checkbox = document.querySelector(`.vendor-invoice-checkbox[value="${invoiceId}"]`);
        if (checkbox) {
            const row = checkbox.closest('tr');
            const balanceInput = row.querySelector('.vendor-balance-input');
            const remaining = parseFloat(checkbox.dataset.remaining) || 0;
            const payment = parseFloat(amount) || 0;
            balanceInput.value = Math.max(0, remaining - payment);
        }
        updateTotalPayment();
    }

    function updateSelectedVendorInvoices() {
        try {
            console.log('updateSelectedVendorInvoices called');

            const selectedCheckboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
            const selectedIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));

            console.log('Found selected checkboxes:', selectedCheckboxes.length, 'IDs:', selectedIds);

            // Collect invoice receipts data
            const invoiceReceipts = [];
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.vendor-receipt-input');
                const adjustmentInput = row.querySelector('.vendor-adjustment-input');
                const balanceInput = row.querySelector('.vendor-balance-input');
                // Fix selector to match the input element's class in the DOM
                const adjustmentDescInput = row.querySelector('.vendor-adjustment-desc-input');

                const invoiceId = parseInt(checkbox.value);
                const receiptAmount = parseFloat(receiptInput.value) || 0;
                const adjustmentAmount = parseFloat(adjustmentInput.value) || 0;
                const balanceAmount = parseFloat(balanceInput.value) || 0;
                const adjustmentDescription = (adjustmentDescInput?.value ?? '');

                invoiceReceipts.push({
                    invoice_id: invoiceId,
                    payment_amount: receiptAmount,
                    adjustment_amount: adjustmentAmount,
                    balance_amount: balanceAmount,
                    adjustment_description: adjustmentDescription
                });
            });

            console.log('Collected invoice receipts:', invoiceReceipts);

            // Debug logging
            console.log('JavaScript updateSelectedVendorInvoices:', {
                selectedIds: selectedIds,
                invoiceReceipts: invoiceReceipts
            });

            // Try multiple methods to update Filament form state
            let updateSuccess = false;

            // Method 1: Using Livewire directly
            if (window.Livewire) {
                const componentId = document.querySelector('[wire\\:id]')?.getAttribute('wire:id');
                if (componentId) {
                    window.Livewire.find(componentId).set('selected_invoices', selectedIds);
                    window.Livewire.find(componentId).set('invoice_receipts', invoiceReceipts);
                    console.log('Data sent via Livewire.find()');
                    updateSuccess = true;
                } else {
                    console.error('Component ID not found');
                }
            } else {
                console.error('window.Livewire not available');
            }

            // Method 2: Using $wire (Alpine.js)
            if (window.$wire) {
                window.$wire.set('selected_invoices', selectedIds);
                window.$wire.set('invoice_receipts', invoiceReceipts);
                console.log('Data sent via $wire.set()');
                updateSuccess = true;
            } else {
                console.error('window.$wire not available');
            }

            // Method 3: Dispatch custom event
            window.dispatchEvent(new CustomEvent('invoice-selection-changed', {
                detail: {
                    selectedIds,
                    invoiceReceipts
                }
            }));
            console.log('Custom event dispatched');

            // Also emit an event to the Livewire page as a reliable fallback
            if (window.Livewire && typeof window.Livewire.emit === 'function') {
                window.Livewire.emit('updateVendorInvoiceData', selectedIds, invoiceReceipts);
                console.log('Data sent via Livewire.emit(updateVendorInvoiceData)');
                updateSuccess = true;
            }

            if (updateSuccess) {
                console.log('Form state update attempted successfully');
            } else {
                console.error('All form state update methods failed');
            }

        } catch (error) {
            console.error('Error in updateSelectedVendorInvoices:', error);
        }

        updateTotalPayment();
    }

    function updateTotalPayment() {
        const selectedCheckboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
        let total = 0;

        // Also update invoice_receipts when payment amounts change
        const invoiceReceipts = [];
        selectedCheckboxes.forEach(checkbox => {
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.vendor-receipt-input');
            const adjustmentInput = row.querySelector('.vendor-adjustment-input');
            const balanceInput = row.querySelector('.vendor-balance-input');
            // Fix selector to match the input element's class in the DOM
            const adjustmentDescInput = row.querySelector('.vendor-adjustment-desc-input');

            const invoiceId = parseInt(checkbox.value);
            const receiptAmount = parseFloat(receiptInput.value) || 0;
            const adjustmentAmount = parseFloat(adjustmentInput.value) || 0;
            const balanceAmount = parseFloat(balanceInput.value) || 0;
            const adjustmentDescription = (adjustmentDescInput?.value ?? '');

            total += receiptAmount + adjustmentAmount;

            invoiceReceipts.push({
                invoice_id: invoiceId,
                payment_amount: receiptAmount,
                adjustment_amount: adjustmentAmount,
                balance_amount: balanceAmount,
                adjustment_description: adjustmentDescription
            });
        });

        // Update Filament form state
        if (window.$wire) {
            window.$wire.set('total_payment', total);
            window.$wire.set('invoice_receipts', invoiceReceipts);
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM loaded, initializing vendor payment table...');

        // Check if checkboxes exist
        const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox');
        console.log('Found checkboxes:', checkboxes.length);

        // Add click event listeners as fallback
        checkboxes.forEach((checkbox, index) => {
            console.log(
                `Checkbox ${index}: value=${checkbox.value}, checked=${checkbox.checked}, disabled=${checkbox.disabled}`
                );

            // Add click event listener as fallback
            checkbox.addEventListener('click', function() {
                try {
                    console.log('Checkbox clicked via event listener:', this.value);
                    setTimeout(() => {
                        handleVendorCheckboxChange(this);
                        updateSelectedVendorInvoices();
                    }, 10);
                } catch (error) {
                    console.error('Error in click event listener:', error);
                }
            });
        });

        updateSelectedVendorInvoices();
        updateTotalPayment();

        // Listen for custom invoice selection events
        window.addEventListener('invoice-selection-changed', function(event) {
            console.log('Custom event received:', event.detail);
        });

        // Add form submission listener to ensure data is sent
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                try {
                    console.log('Form submitted, ensuring data is sent...');

                    // Force update selected invoices before submission
                    updateSelectedVendorInvoices();

                    // Also try to update via Livewire directly
                    const selectedCheckboxes = document.querySelectorAll(
                        '.vendor-invoice-checkbox:checked');
                    const selectedIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value));

                    console.log('Form submit - selected IDs:', selectedIds);

                    // Try Livewire update
                    if (window.Livewire) {
                        const componentId = document.querySelector('[wire\\:id]')?.getAttribute(
                            'wire:id');
                        if (componentId) {
                            window.Livewire.find(componentId).set('selected_invoices', selectedIds);
                            console.log('Data sent via Livewire on form submit');
                        }
                    }

                    // Small delay to ensure Livewire processes the data
                    setTimeout(() => {
                        console.log('Submitting form after data update');
                    }, 100);
                } catch (error) {
                    console.error('Error in form submit listener:', error);
                }
            });
        }

        // Check if Livewire and $wire are available
        setTimeout(() => {
            console.log('Environment check:', {
                livewire: !!window.Livewire,
                wire: !!window.$wire,
                functions: {
                    updateSelectedVendorInvoices: typeof updateSelectedVendorInvoices ===
                        'function',
                    handleVendorCheckboxChange: typeof handleVendorCheckboxChange ===
                        'function',
                    updateTotalPayment: typeof updateTotalPayment === 'function'
                }
            });
        }, 1000);
    });
</script>
