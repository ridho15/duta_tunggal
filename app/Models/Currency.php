<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'currencies';
    protected $fillable = [
        'name',
        'symbol',
        'code',
        'to_rupiah'
    ];
}
