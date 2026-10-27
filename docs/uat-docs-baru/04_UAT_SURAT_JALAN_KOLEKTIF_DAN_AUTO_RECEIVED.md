# MODUL UAT 04: SURAT JALAN (SJ) KOLEKTIF & PENERIMAAN OTOMATIS DO
## PENGUJIAN SURAT JALAN MULTI-DO & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian 6; `List_Pengerjaan_Developer.md` Bagian B (Poin 18–23); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 6.5.
**Peran Penguji Terautentikasi:**
- `Admin Pengiriman` (`admin_pengiriman@example.com`)
- `Delivery Driver` (`delivery_driver@example.com`)
- `Kasir` (`kasir@example.com`)

---

### Kasus Uji UAT-SJ-01: Alur Pembuatan SJ Tanpa Input Produk Ulang (Customer $\rightarrow$ SO $\rightarrow$ Centang DO)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi prinsip bisnis Ko Handy: *"Urutan input: pilih Customer > SO > centang DO. Tidak ada input produk ulang. 1 SJ boleh menggabungkan beberapa DO (kolektif) dari SO yang sama."*
- **Kategori Skenario**: Usability & Collective Document Flow.
- **Testing yang Dilakukan**:
  1. Login sebagai `admin_pengiriman@example.com`.
  2. Buka menu **PENJUALAN $\rightarrow$ Surat Jalan (SJ)**, klik tombol **"Buat Surat Jalan"**.
  3. Pilih Customer `CUST-TEST-UAT-001`.
  4. Pada pilihan Sales Order, pilih `SO-TEST-MULTI-WH`.
  5. Sistem menampilkan daftar checklist DO yang tersedia untuk SO tersebut:
     - [x] `DO-20261026-0005-01` (Gudang Utama)
     - [x] `DO-20261026-0005-02` (Gudang Kenari)
  6. Centang kedua DO tersebut.
  7. Periksa tabel ringkasan barang.
  8. Klik tombol **"Terbitkan Surat Jalan"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Seluruh barang dari kedua DO tertarik secara otomatis tanpa ada satupun field input barang manual. Kedua DO berhasil digabung ke dalam 1 dokumen Surat Jalan kolektif.
- **Solusi**:
  Sistem di [`SuratJalanResource.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SuratJalanResource.php) bekerja persis sesuai Alur Ko Handy §6.
- **Bukti Pengujian Nyata**:
  - Database: Relasi many-to-many di tabel `delivery_order_surat_jalan` menghubungkan 1 ID Surat Jalan dengan 2 ID Delivery Order.

---

### Kasus Uji UAT-SJ-02 (No Happy Path): Pemblokiran Penggabungan DO dari Sales Order yang Berbeda
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan aturan ketat Ko Handy §6 & List Developer B19: *"DO yang digabung dalam 1 SJ HARUS berasal dari SO yang sama. Tidak boleh mencampur DO dari pesanan SO yang berbeda."*
- **Kategori Skenario**: **No Happy Path (Cross-SO Mixing Block)**.
- **Testing yang Dilakukan**:
  1. Siapkan dua SO berbeda milik customer yang sama: `SO-A` dan `SO-B`.
  2. Dari `SO-A` dibuat `DO-A`, dan dari `SO-B` dibuat `DO-B`.
  3. Coba lakukan manipulasi request (atau via script API) untuk membuat Surat Jalan dengan payload berisi `deliveryOrder = [DO-A_ID, DO-B_ID]`.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir eksekusi dengan validasi tegas dari [`SuratJalanService.php:93-103`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SuratJalanService.php#L93-L103):
  *"Semua Delivery Order yang digabungkan dalam satu Surat Jalan harus berasal dari Sales Order yang SAMA. DO yang dipilih berasal dari SO berbeda: DO-A (SO-A), DO-B (SO-B)."*
- **Solusi**:
  Validasi cross-SO berhasil melindungi konsistensi penagihan invoice.
- **Bukti Pengujian Nyata**:
  - Respon Backend: HTTP 422 `ValidationException` pada field `deliveryOrder`.
  - Database: Tidak ada record Surat Jalan yang terbentuk.

---

### Kasus Uji UAT-SJ-03 (No Happy Path): Pemblokiran DO yang Pernah Diretur (Wajib Menggunakan DO Pengganti …C)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §5 & List Developer B20: *"DO yang pernah diretur tidak boleh ikut Surat Jalan; hanya DO.000xC yang bisa dijadikan SJ."*
- **Kategori Skenario**: **No Happy Path (Returned DO Exclusion)**.
- **Testing yang Dilakukan**:
  1. DO asal `DO-20261026-0008` telah diretur dan menghasilkan DO pengganti `DO-20261026-0008C`.
  2. Buka form Buat Surat Jalan.
  3. Amati apakah `DO-20261026-0008` (asal) muncul dalam daftar pilihan.
  4. Jika di-submit secara paksa memasukkan ID DO asal.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Pada tampilan UI: DO asal tidak dimunculkan, hanya DO pengganti `DO-20261026-0008C` yang tersedia.
  - Pada validasi server: Jika ID DO asal dikirim, [`SuratJalanService.php:80-91`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SuratJalanService.php#L80-L91) menolak:
    *"Delivery Order yang pernah diretur tidak dapat dibuatkan Surat Jalan; gunakan DO pengganti (…C): DO-20261026-0008 (gunakan DO pengganti DO-20261026-0008C)."*
- **Solusi**:
  Sistem melindungi dari risiko barang rusak terkirim ulang ke customer.
- **Bukti Pengujian Nyata**:
  - Respon Backend: `ValidationException` melempar pesan penolakan yang menyebutkan nomor DO pengganti yang valid.

---

### Kasus Uji UAT-SJ-04: Penyembunyian DO yang Sudah Digunakan pada Surat Jalan Lain
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan aturan Ko Handy §6: *"DO yang sudah dipakai di SJ lain disembunyikan dari pilihan (tidak muncul dobel)"*.
- **Kategori Skenario**: Prevent Double Assignment & Clutter Reduction.
- **Testing yang Dilakukan**:
  1. `DO-01` telah dimasukkan ke dalam `SJ-001` (status: `issued`).
  2. Buka kembali form Buat Surat Jalan untuk customer dan SO yang sama.
  3. Amati daftar checklist DO yang tersedia.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: `DO-01` tidak lagi tampil pada daftar pilihan. Hanya DO yang masih bebas dan belum memiliki SJ aktif yang ditampilkan.
- **Solusi**:
  Query pilihan menyaring: `whereDoesntHave('suratJalan', fn($q) => $q->whereIn('status', ACTIVE_STATUSES))`.
- **Bukti Pengujian Nyata**:
  - Tampilan Form: Checklist DO hanya berisi item yang eligible.

---

### Kasus Uji UAT-SJ-05: Penerimaan Otomatis DO Saat Surat Jalan Diterbitkan (One-Step Auto Received)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §6 & List Developer B21: *"Membuat SJ otomatis menandai DO 'diterima' (satu langkah, tanpa tombol Konfirmasi Diterima terpisah)"*.
- **Kategori Skenario**: Atomic Automation & State Synchronization.
- **Testing yang Dilakukan**:
  1. DO `DO-01` berstatus `approved`.
  2. Masukkan ke dalam form Buat Surat Jalan, lalu klik tombol **"Terbitkan Surat Jalan"**.
  3. Setelah SJ berstatus `issued`, segera periksa status record `DO-01` di database dan di menu Perintah Kirim.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Status `DO-01` secara instan berubah menjadi: `received` (Diterima).
  - Kolom `received_by` terisi otomatis: `"Surat Jalan SJ-20261026-XXXX"`.
  - Log aktivitas mencatat transisi status: `approved` $\rightarrow$ `sent` $\rightarrow$ `received` dalam satu eksekusi atomik database.
- **Solusi**:
  Method `markDeliveryOrdersReceived` di [`SuratJalanService.php:134`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SuratJalanService.php#L134) berjalan secara transaksional di dalam `CreateSuratJalan::afterCreate()`.
- **Bukti Pengujian Nyata**:
  - Database: `delivery_orders.status = 'received'`, `delivery_orders.received_at` terisi timestamp sekarang.

---

### Kasus Uji UAT-SJ-06: Cetakan Surat Jalan Menampilkan Gudang Asal per Barang (Multi-Gudang)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §6: *"Cetakan SJ menampilkan gudang asal per barang jika DO berasal dari beberapa gudang"*.
- **Kategori Skenario**: Document Print Formatting.
- **Testing yang Dilakukan**:
  1. Buka Surat Jalan hasil penggabungan multi-gudang (Gudang Utama & Gudang Kenari).
  2. Klik tombol **"Cetak PDF"**.
  3. Buka berkas PDF cetakan Surat Jalan yang diunduh.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Pada tabel rincian barang cetakan PDF, di bawah nama barang atau pada kolom lokasi tertera jelas:
  - Baris 1: `PROD-TEST-A (10 pcs) - Gudang: Gudang Utama`
  - Baris 2: `PROD-TEST-A (5 pcs) - Gudang: Gudang Kenari`
  Staf ekspedisi dan customer dapat memverifikasi fisik barang dan gudang pemuatannya.
- **Solusi**:
  Template PDF Surat Jalan membaca relasi sumber alokasi gudang per item.
- **Bukti Pengujian Nyata**:
  - File Cetak: File PDF yang dihasilkan memuat label nama gudang per item barang.
