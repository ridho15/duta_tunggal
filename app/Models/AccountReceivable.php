<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class AccountReceivable extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;
    protected $table = 'account_receivables';
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'total',
        'paid',
        'remaining',
        'status', //Lunas / Belum Lunas
        'created_by',
        'cabang_id',
        'currency_id',
        'exchange_rate',
        'total_original',
        'paid_original',
        'remaining_original',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'remaining' => 'decimal:2',
        'exchange_rate' => 'float',
        'total_original' => 'float',
        'paid_original' => 'float',
        'remaining_original' => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id')->withDefault();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withDefault();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id')->withDefault();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function ageingSchedule()
    {
        return $this->morphOne(AgeingSchedule::class, 'from_model')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }

    public function getStatusAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'lunas', 'paid' => PaymentStatus::PAID->value,
            'belum lunas', 'unpaid' => PaymentStatus::UNPAID->value,
            default => $value,
        };
    }

    public function setStatusAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['status'] = null;
            return;
        }

        $normalized = strtolower(trim((string) $value));

        $this->attributes['status'] = match ($normalized) {
            'lunas', 'paid' => PaymentStatus::PAID->value,
            'belum lunas', 'unpaid' => PaymentStatus::UNPAID->value,
            default => $value,
        };
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($accountReceivable) {
            // Hapus ageing schedule ketika account receivable dihapus
            if ($accountReceivable->ageingSchedule) {
                $accountReceivable->ageingSchedule->delete();
            }
        });

        static::updated(function ($accountReceivable) {
            if ($accountReceivable->wasChanged('paid') && ! $accountReceivable->wasChanged('remaining')) {
                $expectedRemaining = (float) $accountReceivable->total - (float) $accountReceivable->paid;

                if ((float) $accountReceivable->remaining !== $expectedRemaining) {
                    $isPaid = $expectedRemaining <= 1.00;
                    $rate = (float) ($accountReceivable->exchange_rate ?: 1);
                    $rate = $rate > 0 ? $rate : 1.0;

                    $accountReceivable->forceFill([
                        'remaining' => $isPaid ? 0 : max(0, $expectedRemaining),
                        'remaining_original' => $isPaid ? 0 : round(max(0, $expectedRemaining) / $rate, 4),
                        'status' => $isPaid ? PaymentStatus::PAID->value : PaymentStatus::UNPAID->value,
                    ])->saveQuietly();

                    if ($isPaid) {
                        $accountReceivable->invoice?->update(['status' => 'paid']);
                        AgeingSchedule::where('from_model_type', AccountReceivable::class)
                            ->where('from_model_id', $accountReceivable->id)
                            ->delete();
                    }

                    return;
                }
            }

            // Hapus ageing schedule ketika account receivable lunas
            if ($accountReceivable->status === PaymentStatus::PAID->value && $accountReceivable->wasChanged('status')) {
                AgeingSchedule::where('from_model_type', AccountReceivable::class)
                    ->where('from_model_id', $accountReceivable->id)
                    ->delete();
            }
        });
    }

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);
    }
}
