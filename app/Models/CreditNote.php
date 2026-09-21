<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nota Kredit (T5): koreksi resmi atas invoice terbit — pembatalan penuh, retur, atau koreksi sebagian. Yang `issued` FINAL (D36).
 */
class CreditNote extends Model
{
    use LogsGlobalActivity, SoftDeletes;

    public const TYPE_CANCELLATION = 'pembatalan';

    public const TYPE_RETURN = 'retur';

    public const TYPE_CORRECTION = 'koreksi';

    public const TYPE_LABELS = [
        self::TYPE_CANCELLATION => 'Pembatalan Invoice',
        self::TYPE_RETURN => 'Retur Barang',
        self::TYPE_CORRECTION => 'Koreksi Sebagian',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_ISSUED => 'Terbit',
    ];

    protected $fillable = [
        'credit_note_number', 'type', 'status', 'invoice_id', 'customer_id', 'cabang_id', 'customer_return_id', 'replacement_invoice_id',
        'credit_date', 'reason', 'subtotal', 'other_fee_amount', 'tax_amount', 'total', 'applied_to_ar', 'applied_to_deposit',
        'tax_document_number', 'currency_id', 'exchange_rate', 'created_by', 'issued_by', 'issued_at',
    ];

    protected $casts = [
        'credit_date' => 'date',
        'issued_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'other_fee_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'applied_to_ar' => 'decimal:2',
        'applied_to_deposit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CabangScope);   // sama seperti Invoice/Retur: pengguna cabang hanya melihat cabangnya
    }

    public function items()
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withDefault();
    }

    public function customerReturn()
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }
}
