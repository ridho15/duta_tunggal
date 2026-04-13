# Container Duta Tunggal

Stack ini dibuat terpisah dari container lain yang sudah berjalan.

Karakteristik:
- Menggunakan PHP 8.3 berbasis `php:8.3-apache`.
- Database MySQL dibuat sebagai container terpisah di stack yang sama.
- Web container dan database container tetap terpisah, tetapi berada di network Compose yang sama.
- MySQL dipublish ke port host `13306` agar bisa diakses dari luar host Docker.
- Port host dipasang di `18083` setelah diverifikasi kosong.
- Nama image, container, network, dan compose project dipisahkan khusus untuk Duta Tunggal.

File utama:
- `Dockerfile`
- `docker-compose.duta-tunggal.yml`
- `.env.docker`
- `.env.docker.example`
- `bin/start-duta-tunggal-container.sh`
- `bin/backup-duta-tunggal-db.sh`

Menjalankan:

```bash
cd /var/www/duta_tunggal
./bin/start-duta-tunggal-container.sh
```

Mode development dengan sinkronisasi file langsung:

```bash
cd /var/www/duta_tunggal
docker compose --env-file .env.docker \
	-f docker-compose.duta-tunggal.yml \
	-f docker-compose.override.yml \
	up -d
```

Catatan:
- Override ini memasang bind mount source code ke container app, jadi perubahan file di workspace langsung terbaca setelah refresh browser.
- File [bin/start-duta-tunggal-container.sh](../bin/start-duta-tunggal-container.sh) tetap memakai satu file compose utama, jadi tidak otomatis memakai override dev ini.
- Jika PHP tetap menahan versi lama, pastikan container app sudah membaca [docker/dev/php.ini](../docker/dev/php.ini) dan rebuild/recreate sekali setelah pertama kali menambahkan override.

Menghentikan:

```bash
cd /var/www/duta_tunggal
docker compose -f docker-compose.duta-tunggal.yml down
```

Catatan:
- Sebelum dipakai penuh, pastikan `.env.docker` sudah berisi kredensial database internal yang benar.
- Bootstrap awal tetap memakai schema dump MySQL; image web membawa wrapper `mysql` lokal untuk menonaktifkan SSL verifikasi saat schema load.
- Untuk akses remote dari luar, gunakan port `13306` pada host Docker dan akun `duta_tunggal`.
- Smoke test paling aman adalah endpoint `/up`, karena tidak bergantung pada database aplikasi.