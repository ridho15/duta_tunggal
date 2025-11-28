<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsGlobalActivity;

    protected $fillable = [
        'name',
        'purchase_date',
        'usage_date',
        'purchase_cost',
        'salvage_value',
        'useful_life_years',
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

    // Calculate depreciation
    public function calculateDepreciation()
    {
        // Rumus: (Biaya Aset - Nilai Sisa) / Umur Manfaat
        $depreciableAmount = $this->purchase_cost - $this->salvage_value;
        $this->annual_depreciation = $depreciableAmount / $this->useful_life_years;
        $this->monthly_depreciation = $this->annual_depreciation / 12;
        $this->book_value = $this->purchase_cost - $this->accumulated_depreciation;
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
}