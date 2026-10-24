<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rak extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'raks';
    protected $fillable = [
        'name',
        'code',
        'warehouse_id',
        'description',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function inventoryStock()
    {
        return $this->hasMany(InventoryStock::class, 'rak_id');
    }

    public function getDeletionBlockers(): array
    {
        $blockers = [];

        $hasStock = $this->inventoryStock()
            ->where(function ($q) {
                $q->where('qty_available', '>', 0)
                    ->orWhere('qty_reserved', '>', 0);
            })->exists();

        if ($hasStock) {
            $blockers[] = 'masih menyimpan barang dengan stok aktif';
        }

        return $blockers;
    }
}
