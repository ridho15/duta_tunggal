<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WarehouseConfirmation extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;

    protected $table = 'warehouse_confirmations';

    protected $fillable = [
        'sale_order_id',
        'confirmable_type',
        'confirmable_id',
        'confirmation_type',
        'note',
        'rejection_reason',
        'status',
        'confirmed_by',
        'confirmed_at',
    ];

    // ─────────────────────────── Relationships ───────────────────────────

    /**
     * Polymorphic source of this WC:
     *  - App\Models\SaleOrder          (legacy SO-linked flow)
     *  - App\Models\ManufacturingOrder (MO confirmation)
     *  - App\Models\DeliveryOrder      (DO-centric flow — default for new WCs)
     */
    public function confirmable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'confirmed_by')->withDefault();
    }

    public function warehouseConfirmationItems()
    {
        return $this->hasMany(WarehouseConfirmationItem::class);
    }

    public function getPrimaryItemAttribute(): ?WarehouseConfirmationItem
    {
        return $this->relationLoaded('warehouseConfirmationItems')
            ? $this->warehouseConfirmationItems->first()
            : $this->warehouseConfirmationItems()->with(['warehouse', 'saleOrderItem.product', 'materialIssueItem.product', 'product'])->first();
    }

    public function getItemCountAttribute(): int
    {
        return $this->relationLoaded('warehouseConfirmationItems')
            ? $this->warehouseConfirmationItems->count()
            : $this->warehouseConfirmationItems()->count();
    }

    public function getPrimaryItemSourceLabelAttribute(): string
    {
        return $this->primary_item?->source_item_display ?? '-';
    }

    public function getPrimaryItemProductLabelAttribute(): string
    {
        return $this->primary_item?->product_display ?? '-';
    }

    public function getPrimaryItemWarehouseLabelAttribute(): string
    {
        $warehouse = $this->primary_item?->warehouse;

        if (! $warehouse || ! $warehouse->exists) {
            return '-';
        }

        return sprintf('(%s) %s', $warehouse->kode ?? '-', $warehouse->name ?? '-');
    }

    public function getRequestQtySummaryAttribute(): string
    {
        $items = $this->relationLoaded('warehouseConfirmationItems')
            ? $this->warehouseConfirmationItems
            : $this->warehouseConfirmationItems()->get(['requested_qty']);

        if ($items->isEmpty()) {
            return '-';
        }

        $totalQty = (string) $items->sum('requested_qty');

        if ($items->count() === 1) {
            return $totalQty;
        }

        return sprintf('%d baris / qty %s', $items->count(), $totalQty);
    }

    public function getItemAuditSummaryAttribute(): string
    {
        $items = $this->relationLoaded('warehouseConfirmationItems')
            ? $this->warehouseConfirmationItems
            : $this->warehouseConfirmationItems()->with(['warehouse', 'saleOrderItem.product', 'materialIssueItem.product', 'product'])->get();

        if ($items->isEmpty()) {
            return '-';
        }

        if ($items->count() === 1) {
            $item = $items->first();

            return sprintf(
                '%s | %s | Gudang %s | Qty %s',
                $item->source_item_display,
                $item->product_display,
                $item->warehouse?->name ?? '-',
                (string) $item->requested_qty
            );
        }

        return sprintf(
            '%d item request | Qty %s',
            $items->count(),
            (string) $items->sum('requested_qty')
        );
    }

    // ─────────────────────────── Helpers ─────────────────────────────────

    /** Return the linked SaleOrder when confirmable is SaleOrder, else null. */
    public function getLinkedSaleOrder(): ?SaleOrder
    {
        if ($this->confirmable_type === SaleOrder::class) {
            return $this->confirmable instanceof SaleOrder ? $this->confirmable : null;
        }
        return null;
    }

    /** Return the linked DeliveryOrder when confirmable is DeliveryOrder, else null. */
    public function getLinkedDeliveryOrder(): ?DeliveryOrder
    {
        if ($this->confirmable_type === DeliveryOrder::class) {
            return $this->confirmable instanceof DeliveryOrder ? $this->confirmable : null;
        }
        return null;
    }

    /**
     * Human-readable label for the source record shown in table columns.
     * Accessor: $wc->source_label
     */
    public function getSourceLabelAttribute(): string
    {
        $model = $this->confirmable;
        if (!$model) {
            return '-';
        }
        return match ($this->confirmable_type) {
            SaleOrder::class          => $model->so_number ?? 'SO #' . $this->confirmable_id,
            ManufacturingOrder::class => $model->mo_number ?? 'MO #' . $this->confirmable_id,
            MaterialIssue::class      => $model->issue_number ?? 'MI #' . $this->confirmable_id,
            DeliveryOrder::class      => $model->do_number ?? 'DO #' . $this->confirmable_id,
            default                   => '#' . $this->confirmable_id,
        };
    }

    /** Short type label: "Sales Order", "Manufacturing Order", or "Delivery Order". */
    public function getConfirmableTypeLabelAttribute(): string
    {
        return match ($this->confirmable_type) {
            SaleOrder::class          => 'Sales Order',
            ManufacturingOrder::class => 'Manufacturing Order',
            MaterialIssue::class      => 'Material Issue',
            DeliveryOrder::class      => 'Delivery Order',
            default                   => $this->confirmable_type ?? '-',
        };
    }

    // ─────────────────────────── Model Hooks ─────────────────────────────

    protected static function booted()
    {
        static::creating(function (WarehouseConfirmation $wc) {
            if (empty($wc->confirmable_type) && !empty($wc->sale_order_id)) {
                $wc->confirmable_type = SaleOrder::class;
                $wc->confirmable_id = $wc->sale_order_id;
            }

            if (!empty($wc->sale_order_id)) {
                $wc->offsetUnset('sale_order_id');
            }
        });

        static::created(function ($wc) {
            $wc->load(['confirmable', 'warehouseConfirmationItems.saleOrderItem.product', 'warehouseConfirmationItems.materialIssueItem.product']);

            // SO-linked WC created as already confirmed: update SO status immediately
            if ($wc->status === 'confirmed' && $wc->confirmable_type === SaleOrder::class) {
                $so = $wc->confirmable;
                if ($so) {
                    $so->update([
                        'status'                 => 'confirmed',
                        'warehouse_confirmed_at' => now(),
                    ]);
                    Log::info('SO status updated after WC created as confirmed', [
                        'wc_id' => $wc->id,
                        'so_id' => $wc->confirmable_id,
                    ]);
                }
            }

            if ($wc->status === 'confirmed' && $wc->confirmable_type === ManufacturingOrder::class) {
                static::syncPendingManufacturingMaterialIssues($wc);
            }

            if (in_array(strtolower((string) $wc->status), ['confirmed', 'rejected', 'partial_confirmed'], true)) {
                static::syncLinkedMaterialIssueItems($wc);
                static::syncMaterialIssueStatus($wc);
            }

            if ($wc->confirmable_type === DeliveryOrder::class) {
                static::syncLinkedDeliveryOrderItems($wc);
            }
        });

        static::updating(function ($wc) {
            $originalStatus = $wc->getOriginal('status');
            $newStatus      = $wc->status;

            if ($wc->isDirty('status') && $newStatus === 'confirmed') {
                // Bulk-confirm all WC items
                $wc->warehouseConfirmationItems()->update([
                    'status'        => 'confirmed',
                    'confirmed_qty' => DB::raw('requested_qty'),
                ]);

                // SO-linked WC confirmed → update SO status (legacy flow)
                if ($wc->confirmable_type === SaleOrder::class) {
                    $so = $wc->confirmable;
                    if ($so) {
                        $so->update([
                            'status'                 => 'confirmed',
                            'warehouse_confirmed_at' => now(),
                        ]);
                        Log::info('SO status updated after WC confirmed', [
                            'wc_id' => $wc->id,
                            'so_id' => $wc->confirmable_id,
                        ]);

                    }
                }
            } elseif ($wc->isDirty('status') && $newStatus === 'rejected') {
                $wc->warehouseConfirmationItems()->update([
                    'status'        => 'rejected',
                    'confirmed_qty' => 0,
                ]);
            }

        });

        static::deleting(function ($wc) {
            // SO-linked WC deleted: revert SO status
            if ($wc->confirmable_type === SaleOrder::class) {
                $so = $wc->confirmable;
                if ($so) {
                    $so->update([
                        'status'                 => 'request_approve',
                        'warehouse_confirmed_at' => null,
                    ]);
                    Log::info('SO reverted to request_approve after WC deletion', [
                        'so_id' => $wc->confirmable_id,
                        'wc_id' => $wc->id,
                    ]);
                }
            }

            if ($wc->confirmable_type === DeliveryOrder::class) {
                static::syncLinkedDeliveryOrderItems($wc, 'pending');
            }

            if ($wc->isForceDeleting()) {
                $wc->warehouseConfirmationItems()->forceDelete();
            } else {
                $wc->warehouseConfirmationItems()->delete();
            }
        });

        static::deleted(function ($wc) {
            if ($wc->confirmable_type === DeliveryOrder::class) {
                $do = DeliveryOrder::find($wc->confirmable_id);
                $do?->updateStatusFromWarehouseConfirmations();
            }
        });

        static::restoring(function ($wc) {
            $wc->warehouseConfirmationItems()->withTrashed()->restore();
        });

        // DO-linked WC status changed → re-evaluate DO's overall status
        static::updated(function ($wc) {
            if ($wc->wasChanged('status')) {
                if ($wc->confirmable_type === DeliveryOrder::class) {
                    $do = DeliveryOrder::find($wc->confirmable_id);
                    $do?->updateStatusFromWarehouseConfirmations();
                    static::syncLinkedDeliveryOrderItems($wc);
                }

                if ($wc->confirmable_type === ManufacturingOrder::class && strtolower((string) $wc->status) === 'confirmed') {
                    static::syncPendingManufacturingMaterialIssues($wc);
                }

                if ($wc->confirmable_type === MaterialIssue::class) {
                    static::syncLinkedMaterialIssueItems($wc);
                    static::syncMaterialIssueStatus($wc);
                }
            }
        });
    }

    protected static function syncLinkedMaterialIssueItems(WarehouseConfirmation $wc): void
    {
        if ($wc->confirmable_type !== MaterialIssue::class) {
            return;
        }

        $materialIssue = $wc->confirmable;
        if (! $materialIssue instanceof MaterialIssue) {
            return;
        }

        $confirmationItems = $wc->fresh(['warehouseConfirmationItems.materialIssueItem'])?->warehouseConfirmationItems ?? collect();

        foreach ($confirmationItems as $confirmationItem) {
            $status = strtolower((string) $confirmationItem->status);

            if (! $confirmationItem->material_issue_item_id || ! in_array($status, ['confirmed', 'partial_confirmed'], true)) {
                continue;
            }

            $materialIssueItem = $confirmationItem->materialIssueItem;

            if (! $materialIssueItem || ! $materialIssueItem->exists) {
                continue;
            }

            $materialIssueItem->update([
                'status' => MaterialIssueItem::STATUS_APPROVED,
                'approved_by' => $wc->confirmed_by ?? $materialIssueItem->approved_by,
                'approved_at' => $wc->confirmed_at ?? $materialIssueItem->approved_at ?? now(),
            ]);
        }
    }

    protected static function syncMaterialIssueStatus(WarehouseConfirmation $wc): void
    {
        if ($wc->confirmable_type !== MaterialIssue::class) {
            return;
        }

        $materialIssue = $wc->confirmable;
        if (! $materialIssue instanceof MaterialIssue) {
            return;
        }

        $materialIssue->approveFromWarehouseConfirmation($wc);
    }

    protected static function syncLinkedDeliveryOrderItems(WarehouseConfirmation $wc, ?string $overrideStatus = null): void
    {
        if ($wc->confirmable_type !== DeliveryOrder::class) {
            return;
        }

        $wc = $wc->fresh(['warehouseConfirmationItems']);

        if (! $wc) {
            return;
        }

        $wc->warehouseConfirmationItems->each(function (WarehouseConfirmationItem $warehouseConfirmationItem) use ($overrideStatus) {
            $warehouseConfirmationItem->syncLinkedDeliveryOrderItemStatus($overrideStatus);
        });
    }

    protected static function syncPendingManufacturingMaterialIssues(WarehouseConfirmation $wc): void
    {
        $wc->loadMissing('confirmable');

        $productionPlanId = $wc->confirmable?->production_plan_id;

        MaterialIssue::query()
            ->where('status', MaterialIssue::STATUS_PENDING_APPROVAL)
            ->where(function ($query) use ($wc, $productionPlanId) {
                $query->where('manufacturing_order_id', $wc->confirmable_id);

                if ($productionPlanId) {
                    $query->orWhere(function ($nestedQuery) use ($productionPlanId) {
                        $nestedQuery->whereNull('manufacturing_order_id')
                            ->where('production_plan_id', $productionPlanId);
                    });
                }
            })
            ->get()
            ->each(function (MaterialIssue $materialIssue) use ($wc) {
                $materialIssue->approveFromWarehouseConfirmation($wc);
            });
    }

}
