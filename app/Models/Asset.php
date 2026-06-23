<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use App\Traits\CascadesJournalEntries;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity, CascadesJournalEntries;

    protected $fillable = [
        'code',
        'name',
        'purchase_date',
        'usage_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
        'depreciation_method',
        'asset_coa_id',
        'accumulated_depreciation_coa_id',
        'depreciation_expense_coa_id',
        'annual_depreciation',
        'monthly_depreciation',
        'accumulated_depreciation',
        'book_value',
        'status',
        'notes',
        'product_id',
        'purchase_order_id',
        'purchase_order_item_id',
        'cabang_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'usage_date' => 'date',
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'annual_depreciation' => 'decimal:2',
        'monthly_depreciation' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::creating(function ($asset) {
            if (empty($asset->code)) {
                $asset->code = static::generateAssetCode();
            }
        });
    }

    // Relationships
    public function assetCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'asset_coa_id');
    }

    public function accumulatedDepreciationCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_coa_id');
    }

    public function depreciationExpenseCoa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_coa_id');
    }

    public function depreciationEntries()
    {
        return $this->hasMany(AssetDepreciation::class);
    }

    public function disposals()
    {
        return $this->hasMany(AssetDisposal::class);
    }

    public function latestDisposal()
    {
        return $this->hasOne(AssetDisposal::class)->latestOfMany();
    }

    public function transfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function latestTransfer()
    {
        return $this->hasOne(AssetTransfer::class)->latestOfMany();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withDefault();
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function cabang()
    {
        return $this->belongsTo(\App\Models\Cabang::class, 'cabang_id')->withDefault();
    }

    // Calculated Properties
    public function getDepreciableAmountAttribute()
    {
        return $this->purchase_cost - $this->salvage_value;
    }

    public function getRemainingLifeMonthsAttribute()
    {
        $totalMonths = $this->useful_life_years * 12;
        $monthsUsed = Carbon::parse($this->usage_date)->diffInMonths(now());
        return max(0, $totalMonths - $monthsUsed);
    }

    public function getDepreciationPercentageAttribute()
    {
        if ($this->purchase_cost == 0) return 0;
        return ($this->accumulated_depreciation / $this->purchase_cost) * 100;
    }

    public function depreciationAmounts(): array
    {
        $purchaseCost = (float) ($this->purchase_cost ?? 0);
        $salvageValue = (float) ($this->salvage_value ?? 0);
        $usefulLifeYears = (float) ($this->useful_life_years ?? 0);
        $accumulatedDepreciation = (float) ($this->accumulated_depreciation ?? 0);

        $depreciableAmount = max(0, $purchaseCost - $salvageValue);

        if ($usefulLifeYears <= 0) {
            return [
                'annual_depreciation' => 0.0,
                'monthly_depreciation' => 0.0,
                'book_value' => $purchaseCost - $accumulatedDepreciation,
            ];
        }

        $annualDepreciation = match ($this->depreciation_method) {
            'declining_balance' => min($purchaseCost * ((1 / $usefulLifeYears) * 2), $depreciableAmount),
            'sum_of_years_digits' => $depreciableAmount * ($usefulLifeYears / (($usefulLifeYears * ($usefulLifeYears + 1)) / 2)),
            'units_of_production' => $depreciableAmount / $usefulLifeYears,
            default => $depreciableAmount / $usefulLifeYears,
        };

        return [
            'annual_depreciation' => round($annualDepreciation, 2),
            'monthly_depreciation' => round($annualDepreciation / 12, 2),
            'book_value' => round($purchaseCost - $accumulatedDepreciation, 2),
        ];
    }

    // Calculate depreciation
    public function calculateDepreciation()
    {
        $amounts = $this->depreciationAmounts();
        $this->annual_depreciation = $amounts['annual_depreciation'];
        $this->monthly_depreciation = $amounts['monthly_depreciation'];
        $this->book_value = $amounts['book_value'];
        $this->save();
    }

    // Update accumulated depreciation
    public function updateAccumulatedDepreciation()
    {
        // Only sum active (non-reversed) depreciation entries
        $totalDepreciation = $this->depreciationEntries()
            ->where('status', '!=', 'reversed')
            ->sum('amount');
        $this->accumulated_depreciation = $totalDepreciation;
        $this->book_value = $this->purchase_cost - $this->accumulated_depreciation;
        
        // Check if fully depreciated
        if ($this->book_value <= $this->salvage_value) {
            $this->status = 'fully_depreciated';
        }
        
        $this->save();
    }

    // Check if asset has posted journal entries
    public function hasPostedJournals(): bool
    {
        return \App\Models\JournalEntry::where('source_type', 'App\Models\Asset')
            ->where('source_id', $this->id)
            ->exists();
    }

    // Generate unique asset code
    public static function generateAssetCode(): string
    {
        $lastAsset = static::orderBy('id', 'desc')->first();
        $nextNumber = $lastAsset ? $lastAsset->id + 1 : 1;
        return 'AST-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}