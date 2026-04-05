<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManufacturingOrder extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $guarded = ['id'];
    protected $table = 'manufacturing_orders';
    protected $fillable = [
        'mo_number',
        'production_plan_id',
        'quantity',
        'status', // draft, in_progress, completed
        'start_date',
        'end_date',
        'items',
        'cabang_id'
    ];

    public function warehouseConfirmations()
    {
        return $this->morphMany(\App\Models\WarehouseConfirmation::class, 'confirmable');
    }

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'quantity' => 'decimal:2',
        'items' => 'array',
    ];

    protected function items(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (is_array($value) && ! empty($value)) {
                    return $value;
                }

                return $this->resolveMaterialItemsFallback();
            },
        );
    }

    public function production()
    {
        return $this->hasOne(Production::class, 'manufacturing_order_id')->withDefault();
    }

    public function productions()
    {
        return $this->hasMany(Production::class, 'manufacturing_order_id');
    }

    public function productionPlan()
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id')->withDefault();
    }

    public function resolveMaterialItemsFallback(): array
    {
        if (! $this->production_plan_id) {
            return [];
        }

        $this->loadMissing([
            'productionPlan.billOfMaterial.items.product',
            'productionPlan.billOfMaterial.cabang',
            'productionPlan.saleOrder',
            'productionPlan.warehouse',
        ]);

        $productionPlan = $this->productionPlan;

        if (! $productionPlan->exists || ! $productionPlan->billOfMaterial->exists) {
            return [];
        }

        $materialIssue = MaterialIssue::where('production_plan_id', $productionPlan->id)
            ->where('status', 'completed')
            ->with('items.product')
            ->first();

        if ($materialIssue) {
            return $materialIssue->items->map(function ($issueItem) {
                return [
                    'product_id' => $issueItem->product_id,
                    'uom_id' => $issueItem->uom_id,
                    'quantity' => $issueItem->quantity,
                    'notes' => null,
                ];
            })->values()->all();
        }

        return $productionPlan->billOfMaterial->items->map(function ($bomItem) use ($productionPlan) {
            return [
                'product_id' => $bomItem->product_id,
                'uom_id' => $bomItem->uom_id,
                'quantity' => $bomItem->quantity * $productionPlan->quantity,
                'notes' => null,
            ];
        })->values()->all();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    /**
     * Get material issues for this manufacturing order's production plan
     */
    public function materialIssues()
    {
        return $this->hasManyThrough(MaterialIssue::class, ProductionPlan::class, 'id', 'production_plan_id', 'production_plan_id', 'id');
    }

    /**
     * Get completed material issues for this manufacturing order's production plan
     */
    public function completedMaterialIssues()
    {
        return $this->materialIssues()->where('material_issues.status', 'completed');
    }

    public function productionStartBlockingMessage(): ?string
    {
        $plan = $this->productionPlan;
        if (! $plan || ! $plan->exists || ! $plan->billOfMaterial || ! $plan->billOfMaterial->exists) {
            return 'Bill of Material untuk Production Plan belum tersedia.';
        }

        $materialIssues = $this->materialIssues()
            ->with('items', 'warehouseConfirmations')
            ->get();

        if ($materialIssues->isEmpty()) {
            return 'Material Issue belum dibuat. Pengambilan bahan baku dan konfirmasi gudang harus selesai sebelum produksi dimulai.';
        }

        $incompleteIssue = $materialIssues->first(function (MaterialIssue $materialIssue) {
            return $materialIssue->status !== MaterialIssue::STATUS_COMPLETED
                || ! $materialIssue->hasConfirmedWarehouseConfirmation();
        });

        if ($incompleteIssue) {
            return sprintf(
                'Material Issue %s masih berstatus %s atau konfirmasi gudang belum selesai.',
                $incompleteIssue->issue_number ?? ('#' . $incompleteIssue->id),
                $incompleteIssue->status ?? '-'
            );
        }

        if (! $this->areAllMaterialsIssued()) {
            return 'Jumlah pengambilan bahan baku yang sudah selesai belum memenuhi kebutuhan BOM.';
        }

        return null;
    }

    public function canStartProduction(): bool
    {
        return $this->productionStartBlockingMessage() === null;
    }

    /**
     * Check if all required materials are fully issued for this manufacturing order
     */
    public function areAllMaterialsIssued(): bool
    {
        $plan = $this->productionPlan;
        if (!$plan || !$plan->billOfMaterial) {
            return false;
        }

        foreach ($plan->billOfMaterial->items as $item) {
            $requiredQuantity = $item->quantity * $plan->quantity;

            // Get issued quantity from completed material issues
            $issuedQuantity = $this->completedMaterialIssues()
                ->whereHas('items', function ($query) use ($item) {
                    $query->where('product_id', $item->product_id);
                })
                ->with('items')
                ->get()
                ->sum(function ($issue) use ($item) {
                    return $issue->items->where('product_id', $item->product_id)->sum('quantity');
                });

            if ($issuedQuantity < $requiredQuantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::addGlobalScope(new CabangScope);

        static::deleting(function ($manufacturingOrder) {
            // Cascade delete related productions
            $manufacturingOrder->productions()->each(function ($production) {
                $production->delete();
            });

            // Cascade delete related journal entries
            $manufacturingOrder->journalEntries()->delete();
        });
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
