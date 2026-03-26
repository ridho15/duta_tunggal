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

                        // Legacy flow: auto-create DO from confirmed SO-linked WC
                        $wc->refresh();
                        $wc->load('warehouseConfirmationItems.saleOrderItem.product');
                        static::createDeliveryOrderForConfirmedWarehouseConfirmation($wc);
                    }
                }
            }

            // SO-linked WC reverts confirmed → request: delete the auto-created DO
            if (strtolower($originalStatus) === 'confirmed'
                && strtolower($newStatus) === 'request'
                && $wc->confirmable_type === SaleOrder::class) {
                $so = $wc->confirmable;
                if ($so) {
                    $existingDO = $so->deliveryOrder()->first();
                    if ($existingDO) {
                        $existingDO->delete();
                        Log::info('DO deleted due to WC revert to request', [
                            'do_id' => $existingDO->id,
                            'wc_id' => $wc->id,
                        ]);
                    }
                }
            }
        });

        static::deleting(function ($wc) {
            // SO-linked WC deleted: cascade delete linked DO & revert SO status
            if ($wc->confirmable_type === SaleOrder::class) {
                $so = $wc->confirmable;
                if ($so) {
                    $existingDO = $so->deliveryOrder()->first();
                    if ($existingDO) {
                        $existingDO->delete();
                        Log::info('DO deleted due to WC deletion', [
                            'do_id' => $existingDO->id,
                            'wc_id' => $wc->id,
                        ]);
                    }
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

    // ─── Legacy helper: auto-create DO when a SO-linked WC gets confirmed ────

    /**
     * Create a Delivery Order automatically when a SO-linked WC is confirmed (legacy flow).
     * In the new DO-centric flow, DOs are created first and WCs are auto-generated per warehouse.
     */
    protected static function createDeliveryOrderForConfirmedWarehouseConfirmation(WarehouseConfirmation $wc): void
    {
        if ($wc->confirmable_type !== SaleOrder::class) {
            return;
        }

        $so = $wc->confirmable;
        if (!$so) {
            return;
        }

        Log::info('WC: Creating DO for confirmed SO-linked WC', [
            'wc_id' => $wc->id,
            'so_id' => $wc->confirmable_id,
        ]);

        $wc->loadMissing('confirmable.customer', 'warehouseConfirmationItems.saleOrderItem.product');

        // Don't create a second DO if one already exists for this SO
        $existingDO = $so->deliveryOrder()->first();
        if ($existingDO) {
            if ($existingDO->deliveryOrderItem()->count() === 0 && $wc->warehouseConfirmationItems->isNotEmpty()) {
                Log::info('Existing DO has no items — adding items now', ['do_id' => $existingDO->id]);
                foreach ($wc->warehouseConfirmationItems as $wcItem) {
                    if ($wcItem->status === 'confirmed' && $wcItem->confirmed_qty > 0) {
                        try {
                            DeliveryOrderItem::create([
                                'delivery_order_id'  => $existingDO->id,
                                'sale_order_item_id' => $wcItem->sale_order_item_id,
                                'product_id'         => $wcItem->saleOrderItem->product_id ?? null,
                                'quantity'           => $wcItem->confirmed_qty,
                                'reason'             => 'From warehouse confirmation',
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('Failed to add item to existing DO', [
                                'do_id' => $existingDO->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } else {
                Log::info('DO already exists for SO — skipping creation', ['do_id' => $existingDO->id]);
                \Filament\Notifications\Notification::make()
                    ->title('Gagal Membuat Delivery Order')
                    ->danger()
                    ->body('Delivery Order sudah ada untuk Sales Order ini.')
                    ->send();
            }
            return;
        }

        if (!in_array($so->tipe_pengiriman, ['Kirim Langsung', 'Ambil Sendiri'])) {
            Log::info('Skipping DO creation — unrecognized tipe_pengiriman', [
                'tipe_pengiriman' => $so->tipe_pengiriman,
            ]);
            return;
        }

        $warehouseId = $wc->warehouseConfirmationItems->first()?->warehouse_id;
        $driver      = \App\Models\Driver::first();
        $vehicle     = \App\Models\Vehicle::first();
        $doNumber    = \App\Services\DeliveryOrderService::generateStaticDoNumber();

        try {
            $deliveryOrder = DeliveryOrder::create([
                'do_number'     => $doNumber,
                'delivery_date' => now()->toDateString(),
                'driver_id'     => $driver?->id,
                'vehicle_id'    => $vehicle?->id,
                'warehouse_id'  => $warehouseId,
                'status'        => 'draft',
                'notes'         => 'Auto-generated from confirmed WC #' . $wc->id,
                'created_by'    => $wc->confirmed_by ?? $so->approve_by ?? \App\Models\User::first()?->id,
                'cabang_id'     => $so->cabang_id
                    ?? Auth::user()?->cabang_id
                    ?? \App\Models\Cabang::first()?->id,
            ]);

            foreach ($wc->warehouseConfirmationItems as $wcItem) {
                if ($wcItem->status === 'confirmed' && $wcItem->confirmed_qty > 0) {
                    DeliveryOrderItem::create([
                        'delivery_order_id'  => $deliveryOrder->id,
                        'sale_order_item_id' => $wcItem->sale_order_item_id,
                        'product_id'         => $wcItem->saleOrderItem->product_id ?? null,
                        'quantity'           => $wcItem->confirmed_qty,
                        'reason'             => 'From warehouse confirmation',
                    ]);
                }
            }

            $so->deliveryOrder()->attach($deliveryOrder->id);

            Log::info('DO created from confirmed SO-linked WC', [
                'do_id'     => $deliveryOrder->id,
                'do_number' => $deliveryOrder->do_number,
                'wc_id'     => $wc->id,
                'so_id'     => $wc->confirmable_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WC: Failed to auto-create DO', [
                'wc_id' => $wc->id,
                'so_id' => $wc->confirmable_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            \Filament\Notifications\Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->danger()
                ->body('Terjadi kesalahan saat membuat Delivery Order otomatis. Silakan cek log.')
                ->send();
        }
    }
}
