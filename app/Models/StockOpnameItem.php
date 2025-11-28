<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    use HasFactory;

    protected $table = 'stock_opname_items';

    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'rak_id',
        'system_qty',
        'physical_qty',
        'difference_qty',
        'unit_cost',
        'average_cost',
        'difference_value',
        'total_value',
        'notes',
    ];

    protected $casts = [
        'system_qty' => 'decimal:2',
        'physical_qty' => 'decimal:2',
        'difference_qty' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'average_cost' => 'decimal:2',
        'difference_value' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function rak()
    {
        return $this->belongsTo(Rak::class, 'rak_id')->withDefault();
    }

    /**
     * Calculate average cost based on purchase transactions
     */
    public function calculateAverageCost()
    {
        // Get all purchase receipts for this product before the opname date
        $opnameDate = $this->stockOpname->opname_date ?? now();

        $purchaseItems = \App\Models\PurchaseReceiptItem::where('product_id', $this->product_id)
            ->whereHas('purchaseReceipt', function($query) use ($opnameDate) {
                $query->where('receipt_date', '<=', $opnameDate);
            })
            ->with('purchaseReceipt')
            ->orderBy('purchase_receipt_items.created_at', 'asc')
            ->get();

        if ($purchaseItems->isEmpty()) {
            return 0;
        }

        $totalQuantity = 0;
        $totalValue = 0;

        foreach ($purchaseItems as $item) {
            $quantity = $item->quantity_received ?? $item->quantity;
            $unitPrice = $item->unit_price ?? 0;

            $totalQuantity += $quantity;
            $totalValue += ($quantity * $unitPrice);
        }

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }

    /**
     * Calculate total value based on physical quantity and average cost
     */
    public function calculateTotalValue()
    {
        return $this->physical_qty * $this->average_cost;
    }

    /**
     * Calculate difference value based on variance and average cost
     */
    public function calculateDifferenceValue()
    {
        return $this->difference_qty * $this->average_cost;
    }
}
