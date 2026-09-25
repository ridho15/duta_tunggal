@if (is_array($invoices) && count($invoices) > 0)

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
        .balance-input {
            min-width: 140px !important;
        }

        /* Table cell padding consistency */
        th,
        td {
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
                </colgroup>
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            <input type="checkbox" id="select-all"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:focus:ring-blue-600">
                        </th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Invoice</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Customer</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Total Invoice</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Receipt</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Sisa</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <input type="checkbox"
                                    class="invoice-checkbox rounded border-gray-300 dark:border-gray-600 text-blue-600 dark:text-blue-500 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-800 dark:focus:ring-blue-600"
                                    value="{{ $invoice['id'] }}" data-remaining="{{ $invoice['remaining'] }}"
                                    data-cabang-id="{{ $invoice['cabang_id'] ?? '' }}"
                                    {{ in_array($invoice['id'], $selectedInvoices) ? 'checked' : '' }}
                                    onchange="handleInvoiceCheckboxChange(this)">
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
                                <input type="text"
                                    class="receipt-input block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                                    placeholder="0" inputmode="numeric" autocomplete="off"
                                    data-invoice-id="{{ $invoice['id'] }}" data-remaining="{{ $invoice['remaining'] }}"
                                    value="{{ ! empty($invoice['receipt']) ? number_format((float) $invoice['receipt'], 0, ',', '.') : '' }}"
                                    oninput="handleReceiptInputChange(this, 'input')"
                                    onchange="handleReceiptInputChange(this, 'change')"
                                    onblur="handleReceiptInputChange(this, 'blur')" style="min-width: 140px;">
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap">
                                <input type="text"
                                    class="balance-input block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-50 dark:bg-gray-600 text-gray-500 dark:text-gray-400 sm:text-sm cursor-not-allowed"
                                    placeholder="0" readonly data-invoice-id="{{ $invoice['id'] }}"
                                    value="{{ $invoice['balance'] === '' || $invoice['balance'] === null ? '' : number_format((float) $invoice['balance'], 0, ',', '.') }}" style="min-width: 140px;">
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
                @foreach ($invoices as $invoice)
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-600 dark:text-gray-400">{{ $invoice['invoice_number'] }}:</span>
                        <div class="flex space-x-4">
                            <span class="text-gray-900 dark:text-white">Total: Rp
                                {{ number_format($invoice['total'], 0, ',', '.') }}</span>
                            <span class="text-red-600 dark:text-red-400">Sisa: Rp
                                {{ number_format($invoice['remaining'], 0, ',', '.') }}</span>
                            <span
                                class="px-2 py-1 rounded text-xs {{ $invoice['remaining'] <= 0 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' }}">
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
            console.log('Processing', checkboxes.length, 'checkboxes');

            checkboxes.forEach((checkbox, index) => {
                checkbox.checked = selectAllCheckbox.checked;

                const row = checkbox.closest('tr');
                const receiptInput = row.querySelector('.receipt-input');
                const remaining = parseFloat(checkbox.dataset.remaining || 0);

                if (selectAllCheckbox.checked) {
                    if (!receiptInput.value || parseFloat(String(receiptInput.value).replace(/[^\d.-]/g, '')) === 0) {
                        receiptInput.value = formatRupiahAmount(remaining);
                    }
                    updateReceiptAmount(checkbox.value, remaining);
                } else {
                    receiptInput.value = '';
                    updateReceiptAmount(checkbox.value, 0);
                }
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
                const receiptAmount = parseReceiptValue(receiptValue);
                invoiceReceipts[invoiceId] = receiptAmount;
                totalPaymentAmount += receiptAmount;

                console.log(`Invoice ${invoiceId}: receipt="${receiptValue}" → amount=${receiptAmount}`);
            });

            console.log('Total payment calculated:', totalPaymentAmount);

            // Update hidden fields
            const selectedInvoicesField = document.querySelector('[name="selected_invoices"]');
            if (selectedInvoicesField) {
                selectedInvoicesField.value = JSON.stringify(selectedIds);
                selectedInvoicesField.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                selectedInvoicesField.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
                console.log('✅ Updated selected_invoices field');
            }

            const invoiceReceiptsField = document.querySelector('[name="invoice_receipts"]');
            if (invoiceReceiptsField) {
                invoiceReceiptsField.value = JSON.stringify(invoiceReceipts);
                invoiceReceiptsField.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                invoiceReceiptsField.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
                console.log('✅ Updated invoice_receipts field');
            }

            // Update total payment field
            updateTotalPaymentField(totalPaymentAmount);
        }

        function updateTotalPaymentField(totalAmount) {
            const formattedTotal = Math.round(parseFloat(totalAmount) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

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
                totalPaymentField.value = formattedTotal;

                // Trigger multiple events to ensure Filament reactivity
                ['input', 'change', 'blur', 'keyup'].forEach(eventType => {
                    totalPaymentField.dispatchEvent(new Event(eventType, {
                        bubbles: true
                    }));
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
                            component.set('total_payment', formattedTotal);
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
            let numericAmount = parseFloat(String(amount).replace(/[^\d.-]/g, '')) || 0;
            const checkbox = document.querySelector(`.invoice-checkbox[value="${invoiceId}"]`);
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.receipt-input');
            const balanceInput = row.querySelector('.balance-input');
            const remaining = parseFloat(checkbox.dataset.remaining || 0);

            // Nominal di atas sisa tagihan TIDAK dipotong dan tidak memakai alert: tampilkan penjelasan
            // di bawah kolom. Server menolak kelebihan, atau mencatatnya sebagai Deposit Customer bila
            // opsi tersebut dinyalakan pada form.
            showReceiptOverpaymentNote(receiptInput, numericAmount, remaining);

            // Calculate and update balance (remaining - receipt); tidak pernah negatif
            const balance = Math.max(0, remaining - numericAmount);
            balanceInput.value = formatRupiahAmount(balance);

            // Auto-check checkbox if amount > 0, uncheck if amount = 0
            if (numericAmount > 0 && !checkbox.checked) {
                checkbox.checked = true;
            } else if (numericAmount === 0 && checkbox.checked) {
                checkbox.checked = false;
            }

            updateSelectedInvoices();
        }

        function showReceiptOverpaymentNote(receiptInput, amount, remaining) {
            let note = receiptInput.parentElement.querySelector('.receipt-overpay-note');
            const excess = Math.round(amount - remaining);

            if (excess > 1) {
                if (!note) {
                    note = document.createElement('div');
                    note.className = 'receipt-overpay-note text-xs text-red-600 dark:text-red-400 mt-1';
                    receiptInput.parentElement.appendChild(note);
                }
                note.textContent = 'Melebihi sisa tagihan Rp ' + Math.round(remaining).toLocaleString('id-ID')
                    + ' (kelebihan Rp ' + excess.toLocaleString('id-ID') + '). Akan ditolak kecuali opsi "Catat kelebihan sebagai Deposit Customer" dinyalakan.';
            } else if (note) {
                note.remove();
            }
        }

        function updateBalance(invoiceId, balance) {
            // This function is no longer needed as balance is calculated automatically
            if (typeof window.calculateTotalPayment === 'function') {
                window.calculateTotalPayment();
            }
        }

        // Kolom adjustment sisa dihapus (Fase 5A): fitur tidak pernah menghapus piutang di server
        // dan hanya menyesatkan. Bila dibutuhkan, dibuat sebagai dokumen write-off piutang ber-approval.

        // calculateTotalPayment function is now handled by the separate JavaScript init component
        // This ensures no conflicts and a single source of truth for calculations

        function handleCheckboxChange(checkbox) {
            console.log('handleCheckboxChange');
            const row = checkbox.closest('tr');
            const receiptInput = row.querySelector('.receipt-input');

            if (checkbox.checked) {
                const remaining = parseFloat(checkbox.dataset.remaining || 0);

                if (!receiptInput.value || parseFloat(String(receiptInput.value).replace(/[^\d.-]/g, '')) === 0) {
                    receiptInput.value = formatRupiahAmount(remaining);
                }

                updateReceiptAmount(checkbox.value, remaining);
            } else {
                // Clear receipt amount when unchecked
                receiptInput.value = '';
                updateReceiptAmount(checkbox.value, 0);
            }
        }

        // Add event listeners when page loads or when ViewField is rendered
        function initializeEventListeners() {
            if (
                window.updateSelectedInvoicesMain &&
                typeof window.handleInvoiceCheckboxChange === 'function' &&
                typeof window.handleReceiptInputChange === 'function'
            ) {
                return true;
            }

            // Check if invoice table exists
            const invoiceTable = document.querySelector('.invoice-checkbox');
            if (!invoiceTable) {
                return false;
            }

            document.querySelectorAll('.receipt-input').forEach(input => {
                const parsed = parseReceiptValue(input.value);
                input.value = input.value ? formatRupiahAmount(parsed) : '';
            });

            document.querySelectorAll('.balance-input').forEach(input => {
                const parsed = parseReceiptValue(input.value);
                input.value = input.value === '' ? '' : formatRupiahAmount(parsed);
            });

            // Add event listeners for invoice checkboxes
            const checkboxes = document.querySelectorAll('.invoice-checkbox');

            checkboxes.forEach((checkbox) => {
                if (!checkbox.hasAttribute('data-events-attached')) {
                    checkbox.setAttribute('data-events-attached', 'true');

                    checkbox.addEventListener('change', function() {
                        handleCheckboxChange(this);
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
                        this.value = this.value ? formatRupiahAmount(parseReceiptValue(this.value)) : '';
                        updateReceiptAmount(invoiceId, this.value);
                    });

                    input.addEventListener('change', function(e) {
                        const invoiceId = this.getAttribute('data-invoice-id');
                        this.value = this.value ? formatRupiahAmount(parseReceiptValue(this.value)) : '';
                        updateReceiptAmount(invoiceId, this.value);
                    });

                    input.addEventListener('blur', function(e) {
                        const invoiceId = this.getAttribute('data-invoice-id');
                        this.value = this.value ? formatRupiahAmount(parseReceiptValue(this.value)) : '';
                        updateReceiptAmount(invoiceId, this.value);
                    });
                }
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

        window.addEventListener('refreshInvoiceTable', function () {
            setTimeout(function() {
                initializeEventListeners();
            }, 150);
        });

        document.addEventListener('livewire:navigated', function () {
            setTimeout(function() {
                initializeEventListeners();
            }, 150);
        });
    </script>
@else
    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
        <p>{{ $message ?? 'Silakan pilih customer terlebih dahulu untuk melihat invoice' }}</p>
    </div>
@endif
