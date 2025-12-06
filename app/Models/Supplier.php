<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'suppliers';
    protected $fillable = [
        'code',
        'name',
        'perusahaan',
        'address',
        'phone',
        'email',
        'handphone',
        'fax',
        'npwp',
        'tempo_hutang', // hari,
        'kontak_person',
        'keterangan',
        'cabang_id'
    ];

    protected static function booted()
    {
        // Removed CabangScope - suppliers are global entities that can serve multiple branches
        // static::addGlobalScope(new CabangScope);
    }

    public function purchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    public function deposit()
    {
        return $this->morphOne(Deposit::class, 'from_model')->withDefault();
    }

    public function cabang()
    {
        return $this->belongsTo(Cabang::class, 'cabang_id')->withDefault();
    }
}
