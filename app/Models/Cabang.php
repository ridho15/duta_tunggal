<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'cabangs';
    protected $fillable = [
        'kode',
        'nama',
        'alamat',
        'telepon',
        'kenaikan_harga',
        'status',
        'warna_background',
        'tipe_penjualan',
        'kode_invoice_pajak',
        'kode_invoice_non_pajak',
        'kode_invoice_pajak_walkin',
        'nama_kwitansi',
        'label_invoice_pajak',
        'label_invoice_non_pajak',
        'logo_invoice_non_pajak',
        'lihat_stok_cabang_lain',
        'nama_legal',
        'npwp',
        'alamat_pajak',
        'rekening',
    ];

    protected $casts = [
        'rekening' => 'array',
    ];

    public function warehouse()
    {
        return $this->hasMany(Warehouse::class, 'cabang_id');
    }
    protected static function booted()
    {
        static::deleting(function ($cabang) {
            if ($cabang->isForceDeleting()) {
                $cabang->warehouse()->forceDelete();
            } else {
                $cabang->warehouse()->delete();
            }
        });

        static::restoring(function ($cabang) {
            $cabang->warehouse()->withTrashed()->restore();
        });
    }

    public function product()
    {
        return $this->hasMany(Product::class, 'cabang_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'cabang_id');
    }

    public function sales()
    {
        return $this->hasMany(SaleOrder::class, 'cabang_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'cabang_id');
    }

    public function getDeletionBlockers(): array
    {
        $blockers = [];

        if ($this->warehouse()->exists()) {
            $blockers[] = 'memiliki ' . $this->warehouse()->count() . ' gudang aktif';
        }

        if ($this->users()->exists()) {
            $blockers[] = 'memiliki ' . $this->users()->count() . ' pengguna (user) terdaftar';
        }

        if ($this->sales()->exists()) {
            $blockers[] = 'memiliki riwayat transaksi penjualan (Sales Order)';
        }

        if ($this->purchaseOrders()->exists()) {
            $blockers[] = 'memiliki riwayat transaksi pembelian (Purchase Order)';
        }

        return $blockers;
    }
}
