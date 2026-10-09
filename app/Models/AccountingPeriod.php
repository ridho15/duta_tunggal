<?php

namespace App\Models;

use App\Exceptions\ClosedPeriodException;
use App\Models\Scopes\CabangScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'closed_by',
        'closed_at',
        'cabang_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'closed_at' => 'datetime',
    ];

    public function scopeCoveringDate(Builder $query, string|Carbon $date): Builder
    {
        $targetDate = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query
            ->whereDate('period_start', '<=', $targetDate)
            ->whereDate('period_end', '>=', $targetDate);
    }

    public function scopeForCabang(Builder $query, ?int $cabangId): Builder
    {
        return $query->where('cabang_id', $cabangId);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withDefault();
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }

    public static function ensureDateIsOpen(string|Carbon $date, ?int $cabangId): void
    {
        $period = static::withoutGlobalScope(CabangScope::class)
            ->forCabang($cabangId)
            ->coveringDate($date)
            ->first();

        if ($period && $period->status === 'closed') {
            $formattedDate = $date instanceof Carbon ? $date->toDateString() : $date;
            throw new ClosedPeriodException("Periode {$formattedDate} sudah ditutup.");
        }
    }
}
