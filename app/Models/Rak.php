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
        'warehouse_id' // Gudang
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id')->withDefault();
    }

    public function inventoryStocks()
    {
        return $this->hasMany(InventoryStock::class, 'rak_id');
    }
}
