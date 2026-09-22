<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    use HasFactory, LogsGlobalActivity;

    protected $table = 'customer_return_items';

    const DECISION_REPAIR  = 'repair';
    const DECISION_REPLACE = 'replace';
    const DECISION_REJECT  = 'reject';
    /** T5 (D38): barang kembali ke stok (seperti Penggantian) dan sisi uang ditutup Nota Kredit tipe retur. */
    const DECISION_CREDIT  = 'credit';

    const DECISION_LABELS = [
        self::DECISION_REPAIR  => 'Perbaikan',
        self::DECISION_REPLACE => 'Penggantian',
        self::DECISION_REJECT  => 'Klaim Ditolak',
        self::DECISION_CREDIT  => 'Refund / Nota Kredit',
    ];

    /**
     * Opsi keputusan pada form retur: "Refund / Nota Kredit" hanya ditawarkan bila Nota Kredit diaktifkan
     * (flag mati = pilihan lama persis); keputusan yang sudah tersimpan tetap tampil labelnya.
     *
     * @return array<string, string>
     */
    public static function decisionOptions(): array
    {
        return \App\Services\CreditNoteService::enabled()
            ? self::DECISION_LABELS
            : array_diff_key(self::DECISION_LABELS, [self::DECISION_CREDIT => true]);
    }

    const QC_RESULT_PASS = 'pass';
    const QC_RESULT_FAIL = 'fail';

    protected $fillable = [
        'customer_return_id',
        'product_id',
        'invoice_item_id',
        'quantity',
        'problem_description',
        'qc_result',
        'qc_notes',
        'decision',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function customerReturn()
    {
        return $this->belongsTo(CustomerReturn::class, 'customer_return_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class, 'invoice_item_id')->withDefault();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function getDecisionLabelAttribute(): string
    {
        return self::DECISION_LABELS[$this->decision] ?? '-';
    }
}
