<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cabang extends Model
{
    use SoftDeletes, HasFactory;
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
}
