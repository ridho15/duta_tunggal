<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Invoice;
use App\Models\Deposit;
use App\Models\PaymentRequest;
use Filament\Notifications\Notification;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        // Validate deposit availability if payment method is 'Deposit'
        if (isset($data['payment_method']) && $data['payment_method'] === 'Deposit') {
            $supplierId = $data['supplier_id'];

            // Check if supplier has available deposits
            $availableDeposits = Deposit::where('from_model_type', 'App\Models\Supplier')
                ->where('from_model_id', $supplierId)
                ->where('status', 'active')
                ->where('remaining_amount', '>', 0)
                ->get();

            if ($availableDeposits->isEmpty()) {
                Notification::make()
                    ->title('Deposit Tidak Tersedia')
                    ->body('Supplier tidak memiliki deposit yang tersedia untuk pembayaran. Silakan pilih metode pembayaran lain atau buat deposit terlebih dahulu.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
                return;
            }

            // Calculate total payment amount
            $totalPaymentAmount = $data['total_payment'] ?? 0;

            $totalAvailableDeposit = $availableDeposits->sum('remaining_amount');
            if ($totalAvailableDeposit < $totalPaymentAmount) {
                Notification::make()
                    ->title('Saldo Deposit Tidak Mencukupi')
                    ->body("Saldo deposit supplier tidak mencukupi. Saldo tersedia: Rp " . number_format($totalAvailableDeposit, 0, ',', '.') . ", dibutuhkan: Rp " . number_format($totalPaymentAmount, 0, ',', '.'))
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
                return;
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Determine payment status based on total payment vs total invoice amounts
        $totalPayment = $data['total_payment'] ?? 0;

        if ($totalPayment > 0) {
            // If we have selected_invoices, calculate based on remaining amounts
            if (!empty($data['selected_invoices'])) {
                $selectedInvoices = is_array($data['selected_invoices'])
                    ? $data['selected_invoices']
                    : json_decode($data['selected_invoices'], true);

                if (is_array($selectedInvoices)) {
                    $totalInvoiceAmount = Invoice::whereIn('id', $selectedInvoices)
                        ->with('accountPayable')
                        ->get()
                        ->sum(function ($invoice) {
                            return $invoice->accountPayable->remaining ?? $invoice->total;
                        });

                    // Debug logging
                    \Illuminate\Support\Facades\Log::info('VendorPayment Status Determination (Page Level)', [
                        'selected_invoices' => $selectedInvoices,
                        'total_invoice_amount' => $totalInvoiceAmount,
                        'total_payment' => $totalPayment,
                    ]);

                    // If payment covers all remaining amounts, mark as paid
                    if ($totalPayment >= $totalInvoiceAmount - 0.01) { // Allow small rounding difference
                        $data['status'] = 'Paid';
                    } else {
                        $data['status'] = 'Partial';
                    }
                } else {
                    // Fallback: if selected_invoices is not valid array, assume partial payment
                    $data['status'] = 'Partial';
                }
            } else {
                // No selected_invoices, but has payment amount - assume partial
                $data['status'] = 'Partial';
            }
        } else {
            // No payment amount
            $data['status'] = 'Draft';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Update linked PaymentRequest: set status to 'paid' and link vendor_payment_id
        $paymentRequestId = $this->record->payment_request_id;
        if ($paymentRequestId) {
            PaymentRequest::where('id', $paymentRequestId)->update([
                'status' => PaymentRequest::STATUS_PAID,
                'vendor_payment_id' => $this->record->id,
            ]);
        }
    }
}
