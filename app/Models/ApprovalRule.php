<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * Aturan persetujuan (T3.1): untuk jenis dokumen + rentang nilai, peran mana yang boleh menyetujui.
 * Rentang: nilai > above_amount (null = dari 0) DAN nilai <= up_to_amount (null = tanpa batas atas).
 */
class ApprovalRule extends Model
{
    use LogsGlobalActivity;

    public const TYPE_QUOTATION = 'quotation';

    public const TYPE_SALE_ORDER = 'sale_order';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPES = [
        self::TYPE_QUOTATION => 'Quotation',
        self::TYPE_SALE_ORDER => 'Sales Order',
        self::TYPE_CREDIT_NOTE => 'Nota Kredit',
    ];

    protected $fillable = ['document_type', 'label', 'above_amount', 'up_to_amount', 'roles', 'approver_label', 'is_active', 'notes', 'created_by', 'updated_by'];

    protected $casts = ['roles' => 'array', 'is_active' => 'boolean', 'above_amount' => 'decimal:2', 'up_to_amount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (ApprovalRule $rule) {
            $rule->updated_by = \Illuminate\Support\Facades\Auth::id() ?? $rule->updated_by;
            if (! $rule->exists) {
                $rule->created_by = \Illuminate\Support\Facades\Auth::id();
            }
        });
    }

    public function matches(float $amount): bool
    {
        if ($this->above_amount !== null && ! ($amount > (float) $this->above_amount)) {
            return false;
        }

        return $this->up_to_amount === null || $amount <= (float) $this->up_to_amount;
    }

    /** Aturan aktif yang berlaku untuk dokumen bernilai $amount; bila beberapa cocok, yang batas bawahnya paling tinggi (paling spesifik). */
    public static function forDocument(string $type, float $amount): ?self
    {
        return static::query()->where('document_type', $type)->where('is_active', true)->get()
            ->filter(fn (self $rule) => $rule->matches($amount))
            ->sortByDesc(fn (self $rule) => (float) ($rule->above_amount ?? -1))
            ->first();
    }
}
