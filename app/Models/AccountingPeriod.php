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

    /**
     * Tolak posting/ubah/hapus jurnal pada tanggal yang berada di periode tertutup.
     * Periode "semua cabang" (cabang_id NULL) yang ditutup mengunci jurnal SEMUA cabang,
     * selain periode milik cabang jurnal itu sendiri.
     */
    public static function ensureDateIsOpen(string|Carbon $date, ?int $cabangId): void
    {
        $period = static::closedPeriodCovering($date, $cabangId);

        if ($period) {
            $formattedDate = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();
            $scope = $period->cabang_id === null ? 'semua cabang' : 'cabang ini';
            throw new ClosedPeriodException(
                "Periode {$formattedDate} sudah ditutup ({$scope}, {$period->period_start->toDateString()} s/d {$period->period_end->toDateString()}). "
                . 'Gunakan tanggal pada periode yang masih terbuka atau minta akuntansi membuka kembali periode tersebut.'
            );
        }
    }

    /**
     * Periode tertutup yang mencakup tanggal untuk cabang tertentu ATAU periode semua cabang.
     */
    public static function closedPeriodCovering(string|Carbon $date, ?int $cabangId): ?self
    {
        return static::withoutGlobalScope(CabangScope::class)
            ->where(function (Builder $q) use ($cabangId) {
                $q->whereNull('cabang_id');
                if ($cabangId !== null) {
                    $q->orWhere('cabang_id', $cabangId);
                }
            })
            ->coveringDate($date)
            ->where('status', 'closed')
            ->first();
    }
}
