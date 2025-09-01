@if(count($invoices) > 0)
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
                           onchange="updateSelectedVendorInvoices()"
                           {{ in_array($invoice['id'], $selectedInvoices) ? 'checked' : '' }}>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ $invoice['invoice_number'] }}
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                    Rp. {{ number_format($invoice['total'], 0, ',', '.') }}
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <input type="text" 
                           class="vendor-receipt-input w-20 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                           placeholder="Rp"
                           value="{{ $invoice['receipt'] }}"
                           onchange="updateVendorReceiptAmount({{ $invoice['id'] }}, this.value)">
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <input type="text" 
                           class="vendor-balance-input w-20 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                           placeholder="Rp"
                           value="{{ $invoice['balance'] }}"
                           onchange="updateVendorBalance({{ $invoice['id'] }}, this.value)">
                </td>
                <td class="px-4 py-4 whitespace-nowrap">
                    <select class="vendor-adjustment-select w-40 px-2 py-1 text-sm border border-gray-300 rounded-md focus:border-primary-500 focus:ring-primary-500"
                            onchange="updateVendorAdjustmentBalance({{ $invoice['id'] }}, this.value)">
                        <option value="">Pilih Salah Satu COA</option>
                        @php
                        $chartOfAccounts = \App\Models\ChartOfAccount::all();
                        @endphp
                        @foreach($chartOfAccounts as $coa)
                        <option value="{{ $coa->id }}">{{ $coa->code }} - {{ $coa->name }}</option>
                        @endforeach
                    </select>
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
function toggleAllVendorInvoices(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateSelectedVendorInvoices();
}

function updateSelectedVendorInvoices() {
    const selectedIds = [];
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
    checkboxes.forEach(checkbox => {
        selectedIds.push(parseInt(checkbox.value));
    });
    
    // Update the hidden field value using Livewire
    const hiddenField = document.querySelector('[name="selected_invoices"]');
    if (hiddenField) {
        hiddenField.value = JSON.stringify(selectedIds);
        hiddenField.dispatchEvent(new Event('input'));
    }
    
    // Calculate total payment
    calculateVendorTotalPayment();
}

function updateVendorReceiptAmount(invoiceId, amount) {
    // Handle receipt amount changes
    calculateVendorTotalPayment();
}

function updateVendorBalance(invoiceId, balance) {
    // Handle balance changes
    calculateVendorTotalPayment();
}

function updateVendorAdjustmentBalance(invoiceId, coaId) {
    // Handle adjustment COA selection
}

function updateVendorAdjustmentDescription(invoiceId, description) {
    // Handle adjustment description changes
}

function calculateVendorTotalPayment() {
    let total = 0;
    const checkboxes = document.querySelectorAll('.vendor-invoice-checkbox:checked');
    
    checkboxes.forEach(checkbox => {
        const row = checkbox.closest('tr');
        const receiptInput = row.querySelector('.vendor-receipt-input');
        const receiptAmount = parseFloat(receiptInput.value.replace(/[^0-9]/g, '') || 0);
        total += receiptAmount;
    });
    
    // Update total payment field
    const totalPaymentField = document.querySelector('[name="total_payment"]');
    if (totalPaymentField) {
        totalPaymentField.value = total;
        totalPaymentField.dispatchEvent(new Event('input'));
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedVendorInvoices();
});
</script>

@else
<div class="text-center py-8 text-gray-500">
    <p>Silakan pilih vendor terlebih dahulu untuk melihat invoice</p>
</div>
@endif
