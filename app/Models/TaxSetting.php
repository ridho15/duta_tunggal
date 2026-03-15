<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxSetting extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'tax_settings';

    protected $fillable = [
        'name',
        'rate',
        'effective_date',
        'status',
        'type', //PPN, PPH, Custom
    ];

    /**
     * Get the active tax rate for a given type (PPN/PPH/CUSTOM)
     *
     * @param string $type
     * @return float
     */
    public static function activeRate(string $type = 'PPN'): float
    {
        return (float) static::where('status', true)
            ->where('effective_date', '<=', now())
            ->where('type', $type)
            ->orderByDesc('effective_date')
            ->value('rate') ?? 0.0;
    }
}
