<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Production extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'productions';
    protected $fillable = [
        'production_number',
        'manufacturing_order_id',
        'quantity_produced',
        'production_date',
        'status' // draft, finished
    ];

    protected $casts = [
        'production_date' => 'date',
        'quantity_produced' => 'decimal:2',
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class, 'manufacturing_order_id')->withDefault();
    }

    public function qualityControl()
    {
        return $this->morphOne(QualityControl::class, 'from_model')->withDefault();
    }

    public function journalEntries()
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function productionPlan(): ?ProductionPlan
    {
        return $this->manufacturingOrder?->productionPlan;
    }

    public function resolveProductionPlanLabel(): string
    {
        $productionPlan = $this->productionPlan();

        if (! $productionPlan || ! $productionPlan->exists) {
            return '-';
        }

        return sprintf('(%s) %s', $productionPlan->plan_number ?? '-', $productionPlan->name ?? '-');
    }

    public function resolveBillOfMaterialLabel(): string
    {
        $productionPlan = $this->productionPlan();
        $billOfMaterial = $productionPlan?->billOfMaterial;

        if (! $billOfMaterial || ! $billOfMaterial->exists) {
            return '-';
        }

        return sprintf('(%s) %s', $billOfMaterial->code ?? '-', $billOfMaterial->nama_bom ?? '-');
    }

    public function getMaterialRequirements(): Collection
    {
        $productionPlan = $this->productionPlan();

        if (! $productionPlan || ! $productionPlan->exists) {
            return collect();
        }

        return $productionPlan->getMaterialRequirements();
    }

    public function getFulfillmentSummary(): array
    {
        $productionPlan = $this->productionPlan();

        if (! $productionPlan || ! $productionPlan->exists) {
            return [
                'total_materials' => 0,
                'fully_available' => 0,
                'partially_available' => 0,
                'not_available' => 0,
                'fully_issued' => 0,
                'partially_issued' => 0,
                'not_issued' => 0,
                'overall_availability' => 'no_materials',
                'overall_usage' => 'no_materials',
                'can_start_production' => false,
            ];
        }

        return $productionPlan->getFulfillmentSummary();
    }

    public function resolveMaterialRequirementsSourceLabel(): string
    {
        $productionPlan = $this->productionPlan();

        if (! $productionPlan || ! $productionPlan->exists) {
            return '-';
        }

        return sprintf('(%s) %s', $productionPlan->plan_number ?? '-', $productionPlan->name ?? '-');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($production) {
            // Cascade delete related quality control
            $production->qualityControl()->delete();

            // Cascade delete related journal entries
            $production->journalEntries()->delete();
        });
    }
}
