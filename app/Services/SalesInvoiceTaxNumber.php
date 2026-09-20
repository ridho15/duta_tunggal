<?php

namespace App\Services;

use App\Models\Invoice;
use App\Rules\TaxInvoiceNumber;
use Illuminate\Support\Facades\Validator;

/**
 * Mengisi/mengubah No. Faktur Pajak pada invoice penjualan — termasuk invoice yang sudah terbit (mis. otomatis dari DO,
 * bertatus `unpaid`, sehingga tidak bisa diedit lewat form). Kolom ini NON-keuangan: tidak menyentuh jumlah, tanggal, maupun
 * jurnal (InvoiceObserver hanya memposting ulang bila subtotal/total/ppn_rate/invoice_date/other_fee berubah).
 * Perubahan tercatat di activity_log (Invoice memakai LogsGlobalActivity).
 */
class SalesInvoiceTaxNumber
{
    /** Invoice yang boleh diisi lewat aksi khusus: sudah terbit (bukan draft — draft memakai form) dan tidak dibatalkan. */
    public static function canSetOn(Invoice $invoice): bool
    {
        $status = strtolower((string) $invoice->status);

        return ! in_array($status, [Invoice::STATUS_DRAFT, 'canceled', 'cancelled'], true);
    }

    /** Invoice ber-PPN, sudah terbit, tanpa nomor faktur pajak → perlu ditindaklanjuti. */
    public static function isMissing(Invoice $invoice): bool
    {
        return self::canSetOn($invoice)
            && blank($invoice->tax_invoice_number)
            && (float) $invoice->ppn_amount > 0;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function set(Invoice $invoice, ?string $number): Invoice
    {
        $validated = Validator::make(
            ['tax_invoice_number' => $number],
            ['tax_invoice_number' => ['nullable', 'string', 'max:50', new TaxInvoiceNumber($invoice->getKey())]],
        )->validate();

        $invoice->tax_invoice_number = TaxInvoiceNumber::normalize($validated['tax_invoice_number'] ?? null);
        $invoice->save();

        return $invoice;
    }
}
