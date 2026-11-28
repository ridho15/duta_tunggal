<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Tanda tangan digital pada dokumen (salinan gambar saat menandatangani). */
class DocumentSignature extends Model
{
    protected $fillable = ['signable_type', 'signable_id', 'role', 'user_id', 'signer_name', 'signer_title', 'signature_path', 'signed_at', 'ip_address'];

    protected $casts = ['signed_at' => 'datetime'];

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Path lokal gambar untuk PDF (null bila tidak ada / file hilang). */
    public function imagePath(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }
        $path = storage_path('app/public/' . ltrim($this->signature_path, '/'));

        return is_file($path) ? $path : null;
    }
}
