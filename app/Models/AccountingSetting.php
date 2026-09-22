<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Model;

/** Satu kunci Pengaturan Akuntansi → akun COA (T3.4). Perubahan tercatat di log aktivitas. */
class AccountingSetting extends Model
{
    use LogsGlobalActivity;

    protected $fillable = ['key', 'coa_id', 'updated_by'];

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }
}
