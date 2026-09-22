<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegacyTransactionArchive extends Model
{
    use HasFactory;

    protected $table = 'legacy_transaction_archives';

    protected $guarded = [];

    protected $casts = [
        'document_date' => 'datetime',
        'payload' => 'array',
    ];
}