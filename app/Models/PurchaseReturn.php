<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'purchase_returns';
    protected $fillable = [
        'purchase_receipt_id',
        'quality_control_id',
        'failed_qc_action',
        'return_date',
        'nota_retur',
        'created_by',
        'notes',
        'status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejected_by',
        'rejected_at',
        'rejection_notes',
        'credit_note_number',
        'credit_note_date',
        'credit_note_amount',
        'refund_amount',
        'refund_date',
        'refund_method',
        'replacement_po_id',
        'replacement_date',
        'replacement_notes',
        'supplier_response',
        'credit_note_received',
        'case_closed_date',
        'tracking_notes',
        'delivery_note',
        'shipping_details',
        'physical_return_date',
        'cabang_id'
    ];

    /** Resolution options for QC-based returns */
    const QC_ACTION_REDUCE_STOCK      = 'reduce_stock';
    const QC_ACTION_WAIT_NEXT_DELIVERY = 'wait_next_delivery';
    const QC_ACTION_MERGE_NEXT_ORDER  = 'merge_next_order';

    public static function qcActionOptions(): array
    {
        return [
            self::QC_ACTION_REDUCE_STOCK       => 'Kurangi Qty PO (Sesuaikan Pesanan)',
            self::QC_ACTION_WAIT_NEXT_DELIVERY  => 'Tunggu Pengiriman Berikutnya (Supplier Kirim Ulang)',
            self::QC_ACTION_MERGE_NEXT_ORDER   => 'Gabung ke PO Berikutnya (Bawa Harga Asli)',
        ];
    }

    /** Whether this return originated from a QC inspection (not a manual receipt return) */
    public function isQcReturn(): bool
    {
        return !is_null($this->quality_control_id);
    }

    public function purchaseReceipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id')->withDefault();
    }

    public function qualityControl()
    {
        return $this->belongsTo(\App\Models\QualityControl::class, 'quality_control_id')->withDefault();
    }

    public function replacementPurchaseOrder()
    {
        return $this->belongsTo(\App\Models\PurchaseOrder::class, 'replacement_po_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by')->withDefault();
    }

    public function purchaseReturnItem()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
