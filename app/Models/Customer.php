<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, HasFactory, LogsGlobalActivity;
    protected $table = 'customers';
    protected $fillable = [
        'name',
        'code',
        'address',
        'telephone',
        'phone',
        'email',
        'perusahaan',
        'tipe', // PKP, PRI,
        'fax',
        'isSpecial', // ya / tidak
        'tempo_kredit', // harim
        'kredit_limit', // Rp
        'tipe_pembayaran', // Bebas, Kredit, Cash,
        'nik_npwp', // NIK / NPWP,
        'keterangan',
    ];

    public function sales()
    {
        return $this->hasMany(SaleOrder::class, 'customer_id');
    }

    public function scopeFilter($query, $search)
    {
        if ($search) {
            return $query->where(function ($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('address', 'LIKE', '%' . $search . '%')
                    ->orWhere('phone', 'LIKE', '%' . $search . '%')
                    ->orWhere('email', 'LIKE', '%' . $search . '%');
            });
        }
        return $query;
    }

    public function deposit()
    {
        return $this->morphOne(Deposit::class, 'from_model')->withDefault();
    }
}
