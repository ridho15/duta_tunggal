<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pos_orders';

    protected $fillable = [
        'order_number',
        'pos_shift_id',
        'cabang_id',
        'warehouse_id',
        'customer_id',
        'customer_name',
        'user_id',
        'order_date',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'grand_total',
        'payment_method',
        'payment_amount',
        'change_amount',
        'coa_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function posShift()
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->withDefault();
    }

    public function items()
    {
        return $this->hasMany(PosOrderItem::class, 'pos_order_id');
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'from_model');
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
