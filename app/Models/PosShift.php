<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pos_shifts';

    protected $fillable = [
        'shift_number',
        'user_id',
        'cabang_id',
        'warehouse_id',
        'opened_at',
        'closed_at',
        'starting_cash',
        'cash_sales_total',
        'non_cash_sales_total',
        'total_sales',
        'total_transactions',
        'cash_actual',
        'cash_difference',
        'status',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'starting_cash' => 'decimal:2',
        'cash_sales_total' => 'decimal:2',
        'non_cash_sales_total' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'cash_actual' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function orders()
    {
        return $this->hasMany(PosOrder::class, 'pos_shift_id');
    }

    public function scopeActive($query, ?int $userId = null)
    {
        $query->where('status', 'open');
        if ($userId) {
            $query->where('user_id', $userId);
        }
        return $query;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function getExpectedCashAttribute(): float
    {
        return (float) $this->starting_cash + (float) $this->cash_sales_total;
    }
}
