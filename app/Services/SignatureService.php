<?php

namespace App\Services;

use App\Models\DocumentSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Tanda tangan digital (permintaan Ko Handy 28 Nov 2026): tiap user menyimpan gambar tanda tangannya sekali;
 * saat menyetujui / membayar / membuat dokumen, gambar itu DISALIN ke dokumen beserta nama, jabatan, waktu & IP.
 */
class SignatureService
{
    private const MAX_BYTES = 512 * 1024;

    /** Tandatangani $document sebagai $role. Menimpa tanda tangan peran yang sama (mis. disetujui ulang). */
    public function sign(Model $document, string $role, User $user, ?string $title = null): DocumentSignature
    {
        $copy = null;
        $source = $this->userSignaturePath($user);
        if ($source) {
            $copy = 'signatures/documents/' . Str::uuid() . '.png';
            Storage::disk('public')->copy($source, $copy);
        }

        return DocumentSignature::updateOrCreate(
            ['signable_type' => $document->getMorphClass(), 'signable_id' => $document->getKey(), 'role' => $role],
            [
                'user_id' => $user->getKey(),
                'signer_name' => $user->name,
                'signer_title' => $title ?? ($user->posisi ?: $user->getRoleNames()->first()),
                'signature_path' => $copy,
                'signed_at' => now(),
                'ip_address' => request()?->ip(),
            ]
        );
    }

    /** Path relatif di disk public untuk gambar tanda tangan user, bila ada. */
    public function userSignaturePath(User $user): ?string
    {
        $path = ltrim((string) $user->signature, '/');

        return $path !== '' && Storage::disk('public')->exists($path) ? $path : null;
    }

    /** Simpan tanda tangan baru dari gambar kanvas (data URL PNG). */
    public function saveFromDataUrl(User $user, string $dataUrl): string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            throw new RuntimeException('Format gambar tidak dikenal. Solusi: gambar ulang tanda tangan lalu simpan.');
        }
        $binary = base64_decode($m[1], true);
        if ($binary === false || strlen($binary) < 100) {
            throw new RuntimeException('Tanda tangan kosong. Solusi: gambar tanda tangan di kotak lalu simpan.');
        }
        if (strlen($binary) > self::MAX_BYTES || ! str_starts_with($binary, "\x89PNG")) {
            throw new RuntimeException('Gambar tanda tangan tidak valid atau terlalu besar (maks. 512 KB).');
        }

        return $this->store($user, $binary);
    }

    public function saveFromUpload(User $user, UploadedFile $file): string
    {
        return $this->store($user, (string) file_get_contents($file->getRealPath()), $file->extension() ?: 'png');
    }

    private function store(User $user, string $binary, string $ext = 'png'): string
    {
        $path = 'signatures/users/' . $user->getKey() . '-' . now()->format('YmdHis') . '-' . Str::random(6) . '.' . $ext;
        Storage::disk('public')->put($path, $binary);
        // File lama tidak dihapus: dokumen yang sudah ditandatangani menyimpan salinannya sendiri
        $user->forceFill(['signature' => $path])->save();

        return $path;
    }
}
