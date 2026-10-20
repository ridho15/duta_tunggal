<?php

/*
|--------------------------------------------------------------------------
| Mode latihan (Spesifikasi UI/UX Bagian 9, Tahap 3)
|--------------------------------------------------------------------------
| Dipakai pada instans TERPISAH (mis. latihan.dutatunggal.playgo.id) dengan database sendiri.
| Jangan aktifkan di server produksi.
|
|   APP_TRAINING_MODE=true          → banner "MODE LATIHAN" di semua halaman
|   TRAINING_RESET_DAILY=true       → database direset tiap malam (perintah latihan:reset)
|   Nama database WAJIB mengandung "latihan" agar reset mau berjalan (pengaman).
*/

return [
    'enabled' => (bool) env('APP_TRAINING_MODE', false),
    'reset_daily' => (bool) env('TRAINING_RESET_DAILY', true),
    'reset_at' => env('TRAINING_RESET_AT', '02:00'),
    'database_marker' => 'latihan',
    'banner' => env('TRAINING_BANNER', 'MODE LATIHAN — data di sini bukan data asli dan dihapus setiap malam. Silakan coba semua langkah.'),
];
