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

    // Quality Control Accessors
    public function getQualityControlAttribute()
    {
        return $this->purchaseOrderItem->qualityControl ?? null;
    }

    public function getHasPassedQcAttribute()
    {
        $qc = $this->quality_control;
        return $qc && $qc->passed_quantity > 0 && $qc->status == true;
    }

    public function getQcStatusAttribute()
    {
        $qc = $this->quality_control;
        if (!$qc) {
            return 'Tidak ada QC';
        }
        
        if ($qc->status == true || $qc->status == 1) {
            return 'Sudah diproses';
        } else {
            return 'Belum diproses';
        }
    }

    // Calculate depreciation
    public function calculateDepreciation()
    {
        $depreciableAmount = $this->purchase_cost - $this->salvage_value;
        $annualDepreciation = 0;
        
        switch ($this->depreciation_method) {
            case 'straight_line':
                // Metode Garis Lurus: (Biaya Aset - Nilai Sisa) / Umur Manfaat
                $annualDepreciation = $depreciableAmount / $this->useful_life_years;
                break;
                
            case 'declining_balance':
                // Metode Saldo Menurun Ganda: 2 × (1 ÷ masa manfaat) × nilai buku awal
                $depreciationRate = (1 / $this->useful_life_years) * 2; // 2x tarif garis lurus
                $annualDepreciation = $this->purchase_cost * $depreciationRate;
                
                // Pastikan tidak melebihi nilai yang dapat disusutkan
                $maxDepreciable = $this->purchase_cost - $this->salvage_value;
                $annualDepreciation = min($annualDepreciation, $maxDepreciable);
                break;
                
            case 'sum_of_years_digits':
                // Metode Jumlah Digit Tahun: (Biaya disusutkan) × (sisa masa manfaat ÷ jumlah digit tahun)
                // Untuk model, hitung untuk tahun pertama (sisa masa manfaat = umur manfaat)
                $sumOfYears = ($this->useful_life_years * ($this->useful_life_years + 1)) / 2; // n(n+1)/2
                $remainingYears = $this->useful_life_years; // Tahun pertama
                $annualDepreciation = $depreciableAmount * ($remainingYears / $sumOfYears);
                break;
                
            case 'units_of_production':
                // Metode Unit Produksi: akan diimplementasi nanti
                $annualDepreciation = $depreciableAmount / $this->useful_life_years; // placeholder
                break;
                
            default:
                $annualDepreciation = $depreciableAmount / $this->useful_life_years;
                break;
        }
        
        $this->annual_depreciation = $annualDepreciation;
        $this->monthly_depreciation = $annualDepreciation / 12;
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