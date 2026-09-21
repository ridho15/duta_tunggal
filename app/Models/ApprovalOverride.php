<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Audit override persetujuan (D24): pembuat dokumen (Owner/Super Admin) menyetujui dokumennya sendiri dengan alasan tercatat. */
class ApprovalOverride extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_type', 'document_id', 'user_id', 'approval_rule_id', 'amount', 'reason', 'context'];

    protected $casts = ['context' => 'array', 'amount' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
