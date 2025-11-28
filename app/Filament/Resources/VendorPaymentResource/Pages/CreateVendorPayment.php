<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Http\Controllers\HelperController;
use App\Models\Invoice;
use App\Models\AccountPayable;
use App\Models\PurchaseOrder;
use App\Models\VendorPaymentDetail;
use App\Services\LedgerPostingService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    public $selected_invoices = [];
    public $invoice_receipts = [];

    protected $listeners = [
        'updateVendorInvoiceData' => 'handleInvoiceDataUpdate'
    ];

    public function handleInvoiceDataUpdate($selectedInvoices, $invoiceReceipts)
    {
        // Update Livewire public properties (source of truth for fallbacks)
        $this->selected_invoices = is_string($selectedInvoices)
            ? (json_decode($selectedInvoices, true) ?? [])
            : ($selectedInvoices ?? []);

        $this->invoice_receipts = is_string($invoiceReceipts)
            ? (json_decode($invoiceReceipts, true) ?? [])
            : ($invoiceReceipts ?? []);

        // Mirror into internal $data used by Filament form
        $this->data['selected_invoices'] = $this->selected_invoices;
        $this->data['invoice_receipts'] = $this->invoice_receipts;

        // DON'T override total_payment here - let user input control the total
        // $totalPayment = collect($invoiceReceipts)->sum('payment_amount');
        // $this->data['total_payment'] = $totalPayment;

        // Debug: confirm event payload is received in Livewire before submit
        Log::info('handleInvoiceDataUpdate', [
            'selected_invoices' => $this->selected_invoices,
            'invoice_receipts_count' => is_array($this->invoice_receipts) ? count($this->invoice_receipts) : 'non_array'
        ]);

        // Dispatch event for any listeners
        $this->dispatch('invoiceDataUpdated');
    }

    public function toggleInvoiceSelection($invoiceId)
    {
        Log::info("toggleInvoiceSelection called", ['invoiceId' => $invoiceId, 'current_selected' => $this->data['selected_invoices'] ?? []]);

        // Initialize selected_invoices as array if not set
        if (!isset($this->data['selected_invoices']) || !is_array($this->data['selected_invoices'])) {
            $this->data['selected_invoices'] = [];
        }

        // Toggle the invoice selection
        if (in_array($invoiceId, $this->data['selected_invoices'])) {
            // Remove from selection
            $this->data['selected_invoices'] = array_diff($this->data['selected_invoices'], [$invoiceId]);
            Log::info("Invoice {$invoiceId} removed from selection", ['selected' => $this->data['selected_invoices']]);
        } else {
            // Add to selection
            $this->data['selected_invoices'][] = $invoiceId;
            Log::info("Invoice {$invoiceId} added to selection", ['selected' => $this->data['selected_invoices']]);
        }

        // Update the form state
        $this->selected_invoices = $this->data['selected_invoices'];

        // Recalculate total payment based on selected invoices
        $this->updateTotalPayment();
    }

    public function updateTotalPayment()
    {
        Log::info('updateTotalPayment called', ['selected_invoices' => $this->data['selected_invoices'] ?? []]);

        if (empty($this->data['selected_invoices'])) {
            $this->data['total_payment'] = 0;
            Log::info('Total payment set to 0 - no invoices selected');
            return;
        }

        // Get selected invoices and calculate total remaining amount
        $invoices = Invoice::whereIn('id', $this->data['selected_invoices'])
            ->with('accountPayable')
            ->get();

        $totalPayment = $invoices->sum(function ($invoice) {
            return $invoice->accountPayable->remaining ?? $invoice->total;
        });

        $this->data['total_payment'] = $totalPayment;
        Log::info('Total payment recalculated', ['total' => $totalPayment, 'selected_invoices' => $this->data['selected_invoices']]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Debug: Log all incoming data
        Log::info('mutateFormDataBeforeCreate received data', [
            'all_data' => $data,
            'selected_invoices' => $data['selected_invoices'] ?? 'NOT_SET',
            'invoice_receipts' => $data['invoice_receipts'] ?? 'NOT_SET',
            'supplier_id' => $data['supplier_id'] ?? 'NOT_SET',
            'total_payment' => $data['total_payment'] ?? 'NOT_SET'
        ]);

        $data['status'] = 'Draft';

        // Fallback: if form fields are empty, use Livewire public properties
        // This covers cases where JS updates component properties but hidden fields don't sync
        if ((empty($data['selected_invoices']) || $data['selected_invoices'] === '[]') && !empty($this->selected_invoices)) {
            $data['selected_invoices'] = $this->selected_invoices;
        }
        if ((empty($data['invoice_receipts']) || $data['invoice_receipts'] === '[]') && !empty($this->invoice_receipts)) {
            $data['invoice_receipts'] = $this->invoice_receipts;
        }

        // Handle data from JavaScript via hidden fields
        if (!empty($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
        }

        if (!empty($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
        }

        // Handle backward compatibility for single invoice
        // Removed: invoice_id is no longer used, focus on selected_invoices for multiple invoices

        // Ensure proper data types for storage
        if (!empty($data['selected_invoices']) && is_array($data['selected_invoices'])) {
            // Convert string IDs to integers for consistency
            $data['selected_invoices'] = array_map('intval', $data['selected_invoices']);
        }

        if (!empty($data['invoice_receipts']) && is_array($data['invoice_receipts'])) {
            // Ensure numeric fields are properly typed
            foreach ($data['invoice_receipts'] as &$receipt) {
                if (isset($receipt['invoice_id'])) {
                    $receipt['invoice_id'] = (int) $receipt['invoice_id'];
                }
                if (isset($receipt['payment_amount'])) {
                    $receipt['payment_amount'] = (float) $receipt['payment_amount'];
                }
                if (isset($receipt['adjustment_amount'])) {
                    $receipt['adjustment_amount'] = (float) $receipt['adjustment_amount'];
                }
                if (isset($receipt['balance_amount'])) {
                    $receipt['balance_amount'] = (float) $receipt['balance_amount'];
                }
            }
        }

        // Debug: log normalized values after fallbacks/parsing
        Log::info('mutateFormDataBeforeCreate normalized', [
            'selected_invoices' => $data['selected_invoices'] ?? null,
            'invoice_receipts_count' => isset($data['invoice_receipts']) && is_array($data['invoice_receipts']) ? count($data['invoice_receipts']) : 'null_or_non_array',
        ]);

        // Validate: Ensure total_payment is properly typed
        if (isset($data['total_payment'])) {
            $data['total_payment'] = (float) $data['total_payment'];
        }

        // Normalize import tax amounts
        foreach (['ppn_import_amount', 'pph22_amount', 'bea_masuk_amount'] as $field) {
            $rawValue = $data[$field] ?? 0;
            if (is_string($rawValue)) {
                $rawValue = HelperController::parseIndonesianMoney($rawValue);
            }
            $data[$field] = (float) ($rawValue ?? 0);
        }

        // Determine if selected invoices reference import purchase orders
        $hasImportInvoice = false;
        $invoiceCollection = collect();

        if (!empty($data['selected_invoices']) && is_array($data['selected_invoices'])) {
            $invoiceCollection = Invoice::whereIn('id', $data['selected_invoices'])->get();
        } elseif (!empty($data['invoice_id'])) {
            $invoiceCollection = Invoice::where('id', $data['invoice_id'])->get();
        }

        foreach ($invoiceCollection as $invoice) {
            if ($invoice->from_model_type === PurchaseOrder::class) {
                $purchaseOrder = $invoice->fromModel;
                if ($purchaseOrder && $purchaseOrder->is_import) {
                    $hasImportInvoice = true;
                    break;
                }
            }
        }

        $hasImportAmounts = ($data['ppn_import_amount'] ?? 0) > 0
            || ($data['pph22_amount'] ?? 0) > 0
            || ($data['bea_masuk_amount'] ?? 0) > 0;

        $data['is_import_payment'] = $hasImportInvoice || $hasImportAmounts || !empty($data['is_import_payment']);

        if (!$data['is_import_payment']) {
            $data['ppn_import_amount'] = 0;
            $data['pph22_amount'] = 0;
            $data['bea_masuk_amount'] = 0;
        }

        // dd('mutateFormDataBeforeCreate final data', $data);
        return $data;
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $record = $this->record;
            $completionTolerance = 0.01; // unified tolerance for Paid status
            $baseUserTotal = $record->total_payment - ($record->payment_adjustment ?? 0);

            Log::info('VendorPayment afterCreate (tx) started', [
                'payment_id' => $record->id,
                'user_total_payment' => $record->total_payment,
                'base_user_total' => $baseUserTotal,
                'selected_invoices' => $record->selected_invoices,
                'invoice_receipts' => $record->invoice_receipts,
            ]);

            $invoiceReceipts = $record->invoice_receipts ?? [];
            $createdInvoiceIds = [];

            if (!empty($invoiceReceipts) && is_array($invoiceReceipts)) {
                Log::info('Processing invoice receipts (manual mode)', ['count' => count($invoiceReceipts)]);
                foreach ($invoiceReceipts as $receipt) {
                    if (!isset($receipt['invoice_id'])) {
                        continue;
                    }
                    $invoiceId = (int) $receipt['invoice_id'];
                    $paymentAmount = (float) ($receipt['payment_amount'] ?? 0);
                    $adjustmentAmount = (float) ($receipt['adjustment_amount'] ?? 0);
                    $adjustmentDesc = (string) ($receipt['adjustment_description'] ?? '');
                    $ap = AccountPayable::where('invoice_id', $invoiceId)->first();
                    if (!$ap) {
                        Log::warning('AP missing for manual receipt', ['invoice_id' => $invoiceId]);
                        continue;
                    }
                    $remaining = (float) $ap->remaining;
                    // Hitungan total reduksi hutang = pembayaran + adjustment (diskon). Clamp agar tidak melebihi remaining.
                    $rawTotalReduction = $paymentAmount + $adjustmentAmount;
                    $clampedTotalReduction = max(0, min($rawTotalReduction, $remaining));
                    // Pembayaran kas (amount) tidak boleh melebihi clamped reduksi.
                    $actualPayment = max(0, min($paymentAmount, $clampedTotalReduction));
                    $effectiveAdjustment = max(0, $clampedTotalReduction - $actualPayment);
                    $actualPayment = round($actualPayment, 2);
                    $effectiveAdjustment = round($effectiveAdjustment, 2);
                    if ($actualPayment <= 0 && $effectiveAdjustment <= 0) {
                        continue; // Tidak ada efek
                    }
                    $detailNotes = trim('Manual payment' . ($effectiveAdjustment > 0 || $adjustmentDesc !== '' ? (' | Adj: ' . number_format($effectiveAdjustment, 0, ',', '.') . ' ' . $adjustmentDesc) : ''));
                    $newBalance = max(0, $remaining - ($actualPayment + $effectiveAdjustment));
                    $record->vendorPaymentDetail()->create([
                        'invoice_id' => $invoiceId,
                        'amount' => $actualPayment,
                        'adjustment_amount' => $effectiveAdjustment,
                        'balance_amount' => round($newBalance, 2),
                        'notes' => $detailNotes,
                        'method' => $record->payment_method ?? 'Cash',
                        'coa_id' => $record->coa_id,
                        'payment_date' => $record->payment_date,
                    ]);
                    $createdInvoiceIds[] = $invoiceId;
                }
            } elseif (!empty($record->selected_invoices)) {
                $selectedInvoiceIds = is_array($record->selected_invoices)
                    ? $record->selected_invoices
                    : json_decode($record->selected_invoices, true) ?? [];
                $selectedInvoiceIds = array_values(array_unique(array_filter(array_map('intval', $selectedInvoiceIds))));
                if (!empty($selectedInvoiceIds)) {
                    $invoices = Invoice::whereIn('id', $selectedInvoiceIds)->get();
                    // Preload APs to avoid N+1
                    $aps = AccountPayable::whereIn('invoice_id', $selectedInvoiceIds)->get()->keyBy('invoice_id');
                    $totalRemaining = $invoices->sum(function ($inv) use ($aps) {
                        $ap = $aps[$inv->id] ?? null;
                        return $ap ? ($ap->remaining) : $inv->total;
                    });
                    $remainingToAllocate = max(0, min($baseUserTotal, $totalRemaining));
                    $lastIndex = count($invoices) - 1;
                    foreach ($invoices as $idx => $invoice) {
                        $ap = $aps[$invoice->id] ?? null;
                        $invoiceRemaining = $ap ? $ap->remaining : $invoice->total;
                        if ($invoiceRemaining <= 0 || $remainingToAllocate <= 0) {
                            continue;
                        }
                        if ($idx === $lastIndex) {
                            $paymentForThisInvoice = round(min($invoiceRemaining, $remainingToAllocate), 2);
                        } else {
                            $proportion = $totalRemaining > 0 ? ($invoiceRemaining / $totalRemaining) : 0;
                            $rawAllocation = $baseUserTotal * $proportion;
                            $paymentForThisInvoice = min($invoiceRemaining, $rawAllocation);
                            $paymentForThisInvoice = round($paymentForThisInvoice, 2);
                            $remainingToAllocate -= $paymentForThisInvoice;
                        }
                        if ($paymentForThisInvoice <= 0) {
                            continue;
                        }
                        $newBalance = max(0, $invoiceRemaining - $paymentForThisInvoice);
                        $record->vendorPaymentDetail()->create([
                            'invoice_id' => $invoice->id,
                            'amount' => $paymentForThisInvoice,
                            'adjustment_amount' => 0,
                            'balance_amount' => round($newBalance, 2),
                            'method' => $record->payment_method ?? 'Cash',
                            'coa_id' => $record->coa_id,
                            'payment_date' => $record->payment_date,
                        ]);
                        $createdInvoiceIds[] = $invoice->id;
                    }
                }
            } else {
                Log::warning('No invoice data found (tx)', ['payment_id' => $record->id]);
            }

            // Summaries & AP synchronization
            $involvedInvoices = !empty($createdInvoiceIds) ? $createdInvoiceIds : [];
            $involvedInvoices = array_values(array_unique(array_filter(array_map('intval', $involvedInvoices))));
            $remainingSum = 0;
            if (!empty($involvedInvoices)) {
                $apsSync = AccountPayable::whereIn('invoice_id', $involvedInvoices)->get();
                foreach ($apsSync as $ap) {
                    $totals = \App\Models\VendorPaymentDetail::selectRaw('COALESCE(SUM(amount),0) as sum_amount, COALESCE(SUM(adjustment_amount),0) as sum_adjustment')
                        ->where('invoice_id', $ap->invoice_id)
                        ->first();
                    $totalPaidForInvoice = (float) $totals->sum_amount; // Arus kas
                    $totalAdjustmentForInvoice = (float) $totals->sum_adjustment; // Diskon/penyesuaian
                    $newPaid = min($totalPaidForInvoice, $ap->total);
                    $totalReduction = min($totalPaidForInvoice + $totalAdjustmentForInvoice, $ap->total);
                    $newRemaining = max(0, $ap->total - $totalReduction);
                    $remainingSum += $newRemaining;
                    $ap->paid = $newPaid;
                    $ap->remaining = $newRemaining;
                    $ap->status = $newRemaining <= $completionTolerance ? 'Lunas' : 'Belum Lunas';
                    $ap->save();
                    if ($ap->invoice) {
                        $ap->invoice->status = $newRemaining <= $completionTolerance ? 'paid' : ($newPaid > 0 ? 'partially_paid' : $ap->invoice->status);
                        $ap->invoice->save();
                    }
                    if ($ap->ageingSchedule && $newRemaining <= $completionTolerance) {
                        $ap->ageingSchedule->delete();
                    }
                }
                $record->status = ($remainingSum <= $completionTolerance) ? 'Paid' : 'Partial';
            } else {
                $record->status = $record->status ?? 'Partial';
            }
            $record->save();

            // Post journal entries once
            $ledgerService = new LedgerPostingService();
            if (!$record->journalEntries()->exists()) {
                $ledgerService->postVendorPayment($record);
            }
        });
    }
}
