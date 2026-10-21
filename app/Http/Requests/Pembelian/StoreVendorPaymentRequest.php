<?php

namespace App\Http\Requests\Pembelian;

use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVendorPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'cabang_id' => ['nullable', 'integer', 'exists:cabangs,id'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:Bank Transfer,Cash,Giro,Cheque,Deposit'],
            'coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'target_bank_account' => ['nullable', 'string', 'max:255'],
            'transfer_reference_number' => ['nullable', 'string', 'max:255'],
            'proof_file' => ['nullable', 'file', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'selected_invoices' => ['required', 'array', 'min:1'],
            'selected_invoices.*' => ['required', 'integer', 'exists:invoices,id'],
            'allocations' => ['required', 'array'],
            'action' => ['nullable', 'string', 'in:save_draft,save_and_post'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier / vendor wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak ditemukan dalam sistem.',
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'payment_date.date' => 'Format tanggal pembayaran tidak valid.',
            'payment_method.required' => 'Metode pembayaran wajib dipilih.',
            'payment_method.in' => 'Metode pembayaran harus salah satu dari: Bank Transfer, Cash, Giro, Cheque, atau Deposit.',
            'coa_id.required' => 'Akun Kas / Bank pengeluar dana wajib dipilih.',
            'coa_id.exists' => 'Akun Kas / Bank tidak valid.',
            'proof_file.mimes' => 'Bukti pembayaran harus berupa gambar (JPG, JPEG, PNG) atau dokumen PDF.',
            'proof_file.max' => 'Ukuran file bukti pembayaran maksimal 5 MB.',
            'selected_invoices.required' => 'Pilih minimal satu tagihan / faktur pembelian yang akan dibayar.',
            'selected_invoices.min' => 'Pilih minimal satu tagihan / faktur pembelian yang akan dibayar.',
            'allocations.required' => 'Rincian alokasi pembayaran per faktur wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $supplierId = (int) $this->input('supplier_id');
            $selectedInvoices = (array) $this->input('selected_invoices', []);
            $rawAllocations = (array) $this->input('allocations', []);

            if (empty($selectedInvoices)) {
                return;
            }

            $invoices = Invoice::with('accountPayable')
                ->whereIn('id', $selectedInvoices)
                ->get()
                ->keyBy('id');

            $totalPayment = 0.0;

            foreach ($selectedInvoices as $invId) {
                $invoice = $invoices->get($invId);
                if (!$invoice) {
                    $validator->errors()->add("selected_invoices", "Invoice ID {$invId} tidak ditemukan.");
                    continue;
                }

                if ((int) $invoice->supplier_id !== $supplierId) {
                    $validator->errors()->add("selected_invoices", "Faktur {$invoice->invoice_number} bukan milik vendor yang dipilih.");
                    continue;
                }

                $rawAmount = $rawAllocations[$invId] ?? 0;
                $allocatedAmount = round((float) MoneyHelper::safeParse($rawAmount), 2);

                if ($allocatedAmount <= 0) {
                    $validator->errors()->add("allocations.{$invId}", "Alokasi pembayaran untuk faktur {$invoice->invoice_number} harus lebih dari 0.");
                    continue;
                }

                $remainingDebt = (float) ($invoice->accountPayable?->remaining ?? $invoice->total);
                if ($allocatedAmount > ($remainingDebt + 1.00)) {
                    $validator->errors()->add(
                        "allocations.{$invId}",
                        "Nominal pembayaran faktur {$invoice->invoice_number} (" . MoneyHelper::rupiah($allocatedAmount) . ") melebihi sisa hutang (" . MoneyHelper::rupiah($remainingDebt) . ")."
                    );
                }

                $totalPayment += $allocatedAmount;
            }

            if ($totalPayment <= 0 && !$validator->errors()->has('allocations')) {
                $validator->errors()->add('allocations', 'Total nominal pembayaran tidak boleh kosong atau nol.');
            }
        });
    }
}
