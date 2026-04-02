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
            $wc->load(['confirmable', 'warehouseConfirmationItems.saleOrderItem.product']);

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

            if ($wc->isForceDeleting()) {
                $wc->warehouseConfirmationItems()->forceDelete();
            } else {
                $wc->warehouseConfirmationItems()->delete();
            }
        });

        static::restoring(function ($wc) {
            $wc->warehouseConfirmationItems()->withTrashed()->restore();
        });

        // DO-linked WC status changed → re-evaluate DO's overall status
        static::updated(function ($wc) {
            if ($wc->wasChanged('status') && $wc->confirmable_type === DeliveryOrder::class) {
                $do = DeliveryOrder::find($wc->confirmable_id);
                $do?->updateStatusFromWarehouseConfirmations();
            }
        });
    }

}
