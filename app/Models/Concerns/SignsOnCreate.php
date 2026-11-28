<?php

namespace App\Models\Concerns;

use App\Services\SignatureService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Pembuat dokumen otomatis menandatangani (peran "created") saat dokumen dibuat oleh user yang masuk. */
trait SignsOnCreate
{
    use HasSignatures;

    public static function bootSignsOnCreate(): void
    {
        static::created(function ($model) {
            $user = Auth::user();
            if (! $user) {
                return;
            }
            try {
                app(SignatureService::class)->sign($model, 'created', $user);
            } catch (Throwable $e) {
                Log::warning('Tanda tangan pembuat gagal disimpan', ['model' => $model::class, 'id' => $model->getKey(), 'error' => $e->getMessage()]);
            }
        });
    }
}
