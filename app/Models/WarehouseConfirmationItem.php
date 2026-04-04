<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseConfirmationItem extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'warehouse_confirmation_items';
    protected $fillable = [
        'warehouse_confirmation_id',
        'sale_order_item_id',
        'material_issue_item_id',
        'product_id',
        'product_name',
        'requested_qty',
        'confirmed_qty',
        'warehouse_id',
        'rak_id',
        'status'
    ];

    protected $appends = [
        'product_display',
        'source_item_display',
        'material_summary',
    ];

    public function warehouseConfirmation()
    {
        return $this->belongsTo(WarehouseConfirmation::class)->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class)->withDefault();
    }

    public function materialIssueItem()
    {
        return $this->belongsTo(MaterialIssueItem::class)->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault();
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class)->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class)->withDefault();
    }

    public function getProductDisplayAttribute(): string
    {
        $product = $this->saleOrderItem?->product;

        if (! $product || ! $product->exists) {
            $product = $this->materialIssueItem?->product;
        }

        if (! $product || ! $product->exists) {
            $product = $this->product;
        }

        if (! $product || ! $product->exists) {
            return '-';
        }

        return sprintf('(%s) %s', $product->sku ?? '-', $product->name ?? '-');
    }

    public function getSourceItemDisplayAttribute(): string
    {
        if ($this->material_issue_item_id) {
            return 'Material Issue Item #' . $this->material_issue_item_id;
        }

        if ($this->sale_order_item_id) {
            return 'Sales Order Item #' . $this->sale_order_item_id;
        }

        return '-';
    }

    public function getMaterialSummaryAttribute(): string
    {
        $productDisplay = $this->product_display;

        return sprintf(
            '%s | Request %s | Confirm %s | Gudang %s | Status %s',
            $productDisplay,
            (string) $this->requested_qty,
            (string) $this->confirmed_qty,
            $this->warehouse?->name ?? '-',
            ucfirst((string) $this->status)
        );
    }

    protected static function booted()
    {
        static::updating(function ($warehouseConfirmationItem) {
            // When item status changes, check if all items in the warehouse confirmation are confirmed
            if ($warehouseConfirmationItem->isDirty('status')) {
                $warehouseConfirmation = $warehouseConfirmationItem->warehouseConfirmation;

                if ($warehouseConfirmation) {
                    $confirmationType = strtolower((string) $warehouseConfirmation->confirmation_type);
                    $statuses = $warehouseConfirmation->warehouseConfirmationItems()
                        ->get(['id', 'status'])
                        ->mapWithKeys(function ($item) {
                            return [$item->id => strtolower((string) $item->status)];
                        })
                        ->toArray();

                    $statuses[$warehouseConfirmationItem->id] = strtolower((string) $warehouseConfirmationItem->status);
                    $statusValues = collect(array_values($statuses));

                    $allConfirmed = $statusValues->every(fn ($status) => $status === 'confirmed');
                    $allRejected = $statusValues->every(fn ($status) => $status === 'rejected');
                    $hasPartial = $statusValues->contains('partial_confirmed');
                    $hasConfirmed = $statusValues->contains('confirmed');
                    $hasRejected = $statusValues->contains('rejected');

                    $parentStatus = 'request';
                    if ($confirmationType === 'material_issue') {
                        if ($allConfirmed) {
                            $parentStatus = 'confirmed';
                        } elseif ($hasRejected || $allRejected) {
                            $parentStatus = 'rejected';
                        } elseif ($hasConfirmed) {
                            $parentStatus = 'partial_confirmed';
                        }
                    } elseif ($allConfirmed) {
                        $parentStatus = 'confirmed';
                    } elseif ($allRejected) {
                        $parentStatus = 'rejected';
                    } elseif ($hasPartial || ($hasConfirmed && $hasRejected)) {
                        $parentStatus = 'partial_confirmed';
                    }

                    $updatePayload = [
                        'status' => $parentStatus,
                    ];

                    if (in_array($parentStatus, ['confirmed', 'partial_confirmed', 'rejected'])) {
                        $updatePayload['confirmed_by'] = \Illuminate\Support\Facades\Auth::id();
                        $updatePayload['confirmed_at'] = now();
                    }

                    $warehouseConfirmation->update($updatePayload);
                }
            }
        });
    }
}