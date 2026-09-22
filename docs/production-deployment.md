# Production Deployment

Production must use the following application settings:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

After updating the production environment, rebuild Laravel's cached
configuration so workers and web requests use the same values:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Restart PHP-FPM, queue workers, and long-running application processes after
the cache is rebuilt. Verify the deployment by opening a controlled failing
route or request and confirming that the response does not expose SQL, local
paths, environment values, or a stack trace. Full exception details must only
be available in the server logs.

## Lingkungan UAT/produksi: log, sesi, cache, opcache (rekomendasi)

Keluhan "aksi lambat" sering berasal dari konfigurasi, bukan kode. Periksa lebih dulu:

| Pengaturan | UAT | Produksi | Catatan |
|---|---|---|---|
| `APP_DEBUG` | `false` | `false` | `true` menambah overhead dan membocorkan detail galat |
| `LOG_LEVEL` | `warning` | `error` | **`info` TIDAK mengurangi `Log::info`** — level minimum yang dicatat adalah `info`, jadi ratusan `Log::info` di observer/service tetap ditulis. Turunkan ke `warning`/`error` untuk menghentikannya |
| `SESSION_DRIVER` | `file` atau `redis` | `redis` bila ada | `database` = tiap request membaca/menulis tabel `sessions` |
| `CACHE_STORE` | `file` atau `redis` | `redis` bila ada | `database` = tiap `Cache::get` menjadi query |
| `QUEUE_CONNECTION` | `database` boleh | `redis`/`database` + worker | Pastikan worker berjalan bila ada pekerjaan antrean |
| opcache | aktif | aktif | Lihat di bawah |

Periksa opcache di server: `php -i | grep -E "^opcache\.(enable|memory_consumption|validate_timestamps)"`.
Nilai yang disarankan (php.ini web/FPM): `opcache.enable=1`, `opcache.memory_consumption=256`,
`opcache.max_accelerated_files=20000`, `opcache.validate_timestamps=0` (produksi; muat ulang FPM saat deploy).
Setelah mengubah `.env` jalankan `php artisan config:cache`.

Lingkungan dev Docker (`docker/dev/php.ini`) kini mengaktifkan opcache dengan `validate_timestamps=1`
(perubahan kode tetap terbaca) dan `revalidate_freq=2` agar tidak memeriksa berkas di setiap request.

### Profiler request (mengukur sebelum mengoptimasi)

Default **mati** dan tidak menjalankan aksi apa pun; hanya mencatat request yang Anda lakukan.

```dotenv
PERF_PROFILE=true          # aktifkan sementara di UAT
PERF_MIN_MS=300            # catat request >= 300 ms ...
PERF_MIN_QUERIES=40        # ... atau >= 40 query
PERF_SAMPLE=1.0            # proporsi request yang diprofil
PERF_LOG_DAYS=7            # retensi log
```

Lalu `php artisan config:clear` (atau `config:cache` ulang), lakukan aksi yang terasa lambat
(mis. "Tandai Selesai" pada jadwal, Approve DO), dan jalankan:

```bash
php artisan perf:report --since=1d           # ringkasan per aksi: jumlah, rata-rata, p95, maks, query, SQL berulang
php artisan perf:report --since=2h --action=delivery-schedule
```

Log ada di `storage/logs/perf-YYYY-MM-DD.log` (satu baris JSON per request; **SQL dicatat tanpa nilai binding**).
Matikan kembali (`PERF_PROFILE=false`) setelah selesai. Hasil `perf:report` menjadi dasar keputusan optimasi (T7).
