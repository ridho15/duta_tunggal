# Prompt untuk Pemegang Server — Cek, Backup, Deploy, Laporan

Kirim teks di bawah ke orang yang memegang server (bisa juga ditempel ke Claude Code yang berjalan di server).
Tujuannya: memastikan server menjalankan kode terbaru dari GitHub, data aman di-backup, dan pemilik menerima laporan + salinan backup.

---

Halo, tolong kerjakan langkah berikut di server Duta Tunggal ERP **berurutan**. Kirim hasil tiap langkah (copy-paste output terminal) ke saya. Aturan:

- **Jangan menghapus data apa pun** (database, file, folder, container, volume).
- **Jangan mengirim password / isi `.env`** lewat WhatsApp atau chat. Kalau perlu, cukup bilang "sudah ada" / "belum ada".
- Kalau ada langkah yang error, **berhenti** dan kirim pesan errornya ke saya, jangan dipaksa.
- Kerjakan **di luar jam operasional**.

Masuk dulu ke folder aplikasi (contoh `cd /var/www/duta_tunggal` — sesuaikan). Di bawah ini `ART` artinya perintah artisan:
- Docker: `docker compose -f docker-compose.duta-tunggal.yml exec -T app php artisan`
- Non-Docker: `php artisan`

### 1. Kondisi server sekarang (sebelum mengubah apa pun)

```
git status
git branch --show-current
git log -1 --format='%h %ad %s' --date=iso
docker ps --format '{{.Names}}  {{.Status}}'
ART about --only=environment
ART migrate:status | tail -15
crontab -l
```

Kalau `git status` menunjukkan **ada file yang diubah langsung di server**, jalankan juga `git diff > ~/perubahan-server-$(date +%F).diff` dan kirim file `.diff` itu ke saya. **Jangan** di-stash / di-reset dulu — tunggu jawaban saya.

### 2. Backup database + file upload (wajib sebelum update)

Docker (password diambil dari dalam container, tidak perlu diketik):

```
mkdir -p ~/backup-dt
docker exec duta-tunggal-mysql-8 sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --events "$MYSQL_DATABASE"' | gzip > ~/backup-dt/db-$(date +%F-%H%M).sql.gz
tar -czf ~/backup-dt/storage-$(date +%F-%H%M).tar.gz storage/app
ls -lh ~/backup-dt
```

Non-Docker: `mysqldump --single-transaction --routines --triggers --events -u <user> -p <nama_db> | gzip > ~/backup-dt/db-$(date +%F-%H%M).sql.gz` (password diketik sendiri saat diminta), lalu perintah `tar` yang sama.

Cek backup tidak kosong: `gunzip -c ~/backup-dt/db-*.sql.gz | tail -1` harus berisi `-- Dump completed`.
Kirim **ukuran file** (`ls -lh`) ke saya. Salinan backup kirim ke saya lewat **Google Drive pribadi saya** (bukan grup WA).

### 3. Update ke kode terbaru (branch `main`)

```
./update.sh --branch=main
```

Kirim beberapa baris terakhir outputnya. Kalau berhenti karena "local changes", **jangan** pakai `--stash` dulu — kirim ke saya.

### 4. Pastikan migrasi & versi sudah benar

```
git log -1 --format='%h %ad %s' --date=iso
ART migrate:status | grep -i pending || echo "Tidak ada migrasi pending"
```

Versi yang diharapkan: commit `07e96021` (14 Nov) atau lebih baru.

### 5. Scheduler (jadwal otomatis) — wajib aktif

Sistem punya pekerjaan otomatis harian (memberangkatkan Surat Jalan sesuai tanggal kirim pukul 00:15, kedaluwarsa penawaran, cek invoice jatuh tempo, penyusutan aset). Semua itu **tidak jalan** tanpa cron.
Kalau di `crontab -l` (langkah 1) belum ada `schedule:run`, tambahkan lewat `crontab -e` satu baris (sesuaikan folder):

- Docker: `* * * * * cd /var/www/duta_tunggal && docker compose -f docker-compose.duta-tunggal.yml exec -T app php artisan schedule:run >> /dev/null 2>&1`
- Non-Docker: `* * * * * cd /var/www/duta_tunggal && php artisan schedule:run >> /dev/null 2>&1`

Lalu cek: `ART schedule:list` dan kirim hasilnya.

### 6. Konversi data stok DO lama — SIMULASI saja dulu

Stok dulu dipotong saat DO dibuat; sekarang hanya dikunci. DO lama yang belum dikirim perlu dirapikan sekali. **Jalankan versi simulasi (tidak mengubah data):**

```
ART stock:convert-open-do-deductions
```

Kirim output-nya + file CSV yang disebut di output (di `storage/app/audits/`). **Jangan jalankan `--apply`** sampai saya konfirmasi. Saat nanti diminta `--apply`: di luar jam operasional, bukan tanggal akhir bulan.

### 7. Cek cepat di browser (setelah langkah 3)

Buka aplikasi, tekan **Ctrl+F5** dulu, lalu cek:
1. Penawaran Harga → buka penawaran "Menunggu Persetujuan" → tombol **Setujui** memunculkan konfirmasi.
2. Menu **Gudang & Stok → Persetujuan Retur DO** muncul, dan retur `DO-DUTA-K01.2-20261113-01R` ada di daftar.
3. **Terima Pembayaran → Buat** → pilih customer tanpa tagihan → muncul panel **Catat sebagai Titip Dana**.

Screenshot hasilnya.

### Yang saya terima di akhir (checklist)

- [ ] Output langkah 1 (+ file `.diff` bila ada perubahan langsung di server)
- [ ] Ukuran file backup DB & storage + link Google Drive salinannya
- [ ] Output akhir `update.sh` dan versi commit yang terpasang
- [ ] Konfirmasi tidak ada migrasi pending
- [ ] Baris cron scheduler + output `schedule:list`
- [ ] Output simulasi konversi stok + file CSV-nya
- [ ] 3 screenshot cek browser
