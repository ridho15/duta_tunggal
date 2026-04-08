<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherNumberSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'sequence_date',
        'last_sequence',
    ];

    protected $casts = [
        'sequence_date' => 'date:Y-m-d',
        'last_sequence' => 'integer',
    ];

    public $timestamps = false;
}
