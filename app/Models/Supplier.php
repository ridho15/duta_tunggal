<?php

namespace App\Models;

use App\Models\Scopes\CabangScope;
use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Supplier extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'suppliers';
    protected $fillable = [
        'code',
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

        // Ensure cabang_id is set when creating via Filament forms that may hide the field
        static::creating(function ($model) {
            if (empty($model->cabang_id)) {
                $model->cabang_id = Auth::user()?->cabang_id
                    ?? \App\Models\Cabang::first()?->id
                    ?? \App\Models\Cabang::create([
                        'kode' => 'CBG-' . uniqid(),
                        'nama' => 'Cabang Default',
                        'alamat' => 'Auto-created',
                        'telepon' => '0000000000',
                        'status' => true,
                    ])->id;
            }
        });
    }

    public function purchaseOrder()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'supplier_id');
    }

    // Task 12: Multi-supplier support (many-to-many)
    public function productSuppliers()
    {
        return $this->belongsToMany(Product::class, 'product_supplier')
            ->withPivot('supplier_price', 'supplier_sku', 'is_primary')
            ->withTimestamps();
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
