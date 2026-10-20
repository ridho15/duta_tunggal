<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Video tutorial per peran (halaman Bantuan, Tahap 3). */
class HelpVideo extends Model
{
    protected $fillable = ['title', 'description', 'role_code', 'url', 'duration_minutes', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const ROLE_LABELS = [
        'KSR' => 'Kasir',
        'GDG' => 'Gudang / Checker',
        'ADP' => 'Admin Pengiriman',
        'SLS' => 'Sales / Toko',
        'SM' => 'Sales Manager',
        'PUR' => 'Purchasing',
        'FIN' => 'Keuangan',
        'OWN' => 'Owner / Admin',
    ];

    /** URL embed untuk YouTube (youtube-nocookie) atau null bila bukan YouTube. */
    public function youtubeEmbedUrl(): ?string
    {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', (string) $this->url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?rel=0';
        }

        return null;
    }

    public function isVideoFile(): bool
    {
        return (bool) preg_match('~\.(mp4|webm|mov)(\?.*)?$~i', (string) $this->url);
    }
}
