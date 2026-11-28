<?php

namespace App\Models\Concerns;

use App\Models\DocumentSignature;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Dokumen yang bisa ditandatangani digital (PO, Permintaan Bayar, voucher, dll). */
trait HasSignatures
{
    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }

    public function signatureFor(string $role): ?DocumentSignature
    {
        return $this->relationLoaded('signatures')
            ? $this->signatures->firstWhere('role', $role)
            : $this->signatures()->where('role', $role)->first();
    }
}
