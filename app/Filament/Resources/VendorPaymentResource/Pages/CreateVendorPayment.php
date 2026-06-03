<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Invoice;
use App\Models\Deposit;
use App\Models\PaymentRequest;
use App\Models\VendorPayment;
use App\Helpers\MoneyHelper;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            $totalPaymentAmount = MoneyHelper::safeParse($data['total_payment'] ?? 0);

            $totalAvailableDeposit = $availableDeposits->sum('remaining_amount');
            if ($totalAvailableDeposit < $totalPaymentAmount) {
                Notification::make()
                    ->title('Saldo Deposit Tidak Mencukupi')
                    ->body("Saldo deposit supplier tidak mencukupi. Saldo tersedia: " . \App\Helpers\MoneyHelper::rupiah($totalAvailableDeposit) . ", dibutuhkan: " . \App\Helpers\MoneyHelper::rupiah($totalPaymentAmount))
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
        $totalPayment = MoneyHelper::safeParse($data['total_payment'] ?? 0);
        $data['total_payment'] = $totalPayment;

        if ($totalPayment > 0) {
            // If we have selected_invoices, calculate based on remaining amounts
            if (!empty($data['selected_invoices'])) {
                $selectedInvoices = is_array($data['selected_invoices'])
                    ? $data['selected_invoices']
                    : json_decode($data['selected_invoices'], true);

                if (is_array($selectedInvoices)) {
                    $invoices = Invoice::whereIn('id', $selectedInvoices)
                        ->with('accountPayable')
                        ->get();
                    $totalInvoiceAmount = VendorPaymentResource::calculateSelectedInvoiceTotal($invoices);

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
        try {
            $paymentRequestId = $this->record->payment_request_id;
            if (!$paymentRequestId) {
                return;
            }

            $pr = PaymentRequest::find($paymentRequestId);
            if (!$pr) {
                return;
            }

            $paidSoFar = VendorPayment::where('payment_request_id', $paymentRequestId)
                ->get()
                ->sum(function (VendorPayment $payment) {
                    $selectedInvoices = is_string($payment->selected_invoices)
                        ? json_decode($payment->selected_invoices, true)
                        : $payment->selected_invoices;

                    $invoice = is_array($selectedInvoices) && !empty($selectedInvoices)
                        ? Invoice::find((int) reset($selectedInvoices))
                        : null;

                    return $invoice
                        ? PurchaseInvoiceResource::invoiceAmountToIdr($invoice, $payment->total_payment)
                        : MoneyHelper::safeParse($payment->total_payment ?? 0);
                });
            $requestTotal = MoneyHelper::safeParse($pr->total_amount ?? 0);

            if ($paidSoFar >= ($requestTotal - 0.01)) {
                $newStatus = PaymentRequest::STATUS_PAID;
            } elseif ($paidSoFar > 0) {
                $newStatus = PaymentRequest::STATUS_PARTIAL;
            } else {
                $newStatus = PaymentRequest::STATUS_APPROVED;
            }

            $pr->update([
                'status' => $newStatus,
                'vendor_payment_id' => $this->record->id,
            ]);
        } catch (Throwable $exception) {
            Log::error('CreateVendorPayment afterCreate failed', [
                'vendor_payment_id' => $this->record?->id,
                'payment_request_id' => $this->record?->payment_request_id,
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::warning(
                'Pembayaran Vendor Tersimpan Dengan Catatan',
                $exception,
                'Pembayaran vendor berhasil disimpan, tetapi sinkronisasi payment request belum berhasil diperbarui. Silakan periksa status payment request terkait.'
            );
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('CreateVendorPayment handleRecordCreation failed', [
                'supplier_id' => $data['supplier_id'] ?? null,
                'payment_request_id' => $data['payment_request_id'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            ProcurementFailureNotifier::danger(
                'Gagal Membuat Pembayaran Vendor',
                $exception,
                'Pembayaran vendor belum berhasil dibuat. Periksa kembali invoice, nominal pembayaran, dan akun yang dipilih lalu coba lagi.'
            );

            throw $exception;
        }
    }
}
