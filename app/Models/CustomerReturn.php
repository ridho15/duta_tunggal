<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturn extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'customer_returns';

    const STATUS_PENDING      = 'pending';
    const STATUS_RECEIVED     = 'received';
    const STATUS_QC_INSPECTION = 'qc_inspection';
    const STATUS_APPROVED     = 'approved';
    const STATUS_REJECTED     = 'rejected';
    const STATUS_COMPLETED    = 'completed';

    const STATUS_LABELS = [
        self::STATUS_PENDING       => 'Menunggu',
        self::STATUS_RECEIVED      => 'Diterima',
        self::STATUS_QC_INSPECTION => 'Inspeksi QC',
        self::STATUS_APPROVED      => 'Disetujui',
        self::STATUS_REJECTED      => 'Ditolak',
        self::STATUS_COMPLETED     => 'Selesai',
    ];

    const STATUS_COLORS = [
        self::STATUS_PENDING       => 'warning',
        self::STATUS_RECEIVED      => 'info',
        self::STATUS_QC_INSPECTION => 'primary',
        self::STATUS_APPROVED      => 'success',
        self::STATUS_REJECTED      => 'danger',
        self::STATUS_COMPLETED     => 'success',
    ];

    protected $fillable = [
        'return_number',
        'invoice_id',
        'customer_id',
        'cabang_id',
        'warehouse_id',
        'return_date',
        'reason',
        'status',
        'received_by',
        'received_at',
        'qc_inspected_by',
        'qc_inspected_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'stock_restored_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'return_date'        => 'date',
        'received_at'        => 'datetime',
        'qc_inspected_at'    => 'datetime',
        'approved_at'        => 'datetime',
        'rejected_at'        => 'datetime',
        'stock_restored_at'  => 'datetime',
        'completed_at'       => 'datetime',
    ];

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function customerReturnItems()
    {
        return $this->hasMany(CustomerReturnItem::class, 'customer_return_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'from_model');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by')->withDefault();
    }

    public function qcInspectedBy()
    {
        return $this->belongsTo(User::class, 'qc_inspected_by')->withDefault();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withDefault();
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by')->withDefault();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /**
     * Generate a unique sequential return number.
     * Format: CR-YYYY-NNNN (e.g. CR-2026-0001)
     */
    public static function generateReturnNumber(): string
    {
        $year   = now()->format('Y');
        $prefix = "CR-{$year}-";

        // Bypass CabangScope so the sequence is global across all branches
        $latest = static::withTrashed()
            ->withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
            ->where('return_number', 'like', $prefix . '%')
            ->orderByDesc('return_number')
            ->value('return_number');

        if ($latest) {
            $lastSeq = (int) substr($latest, -4);
            $nextSeq = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Global scope
    // ------------------------------------------------------------------

    protected static function booted(): void
    {
        static::addGlobalScope(new CabangScope);
    }
}
