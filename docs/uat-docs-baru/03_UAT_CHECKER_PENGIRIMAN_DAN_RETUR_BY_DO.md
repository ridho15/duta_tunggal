# MODUL UAT 03: CHECKER GUDANG, SIAPKAN & KIRIM HP, SERTA RETUR BY DO
## PENGUJIAN PENGECEKAN FISIK, PENGIRIMAN & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian 4 & 5; `List_Pengerjaan_Developer.md` Bagian B (Poin 12–17); `UIUX_Spec_ERP_Duta_Tunggal.md` Layar F1, F2, F3 & F4.
**Peran Penguji Terautentikasi:**
- `Checker Gudang` (`checker@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Admin Pengiriman` (`admin_pengiriman@example.com`)

---

### Kasus Uji UAT-CHK-01: Tampilan Antarmuka Siapkan & Kirim di HP (Layar Checker 390px)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memastikan layar HP `/admin/kiriman` dan `/admin/kiriman/{DO}` sangat mudah digunakan oleh staf gudang yang awam teknologi (Prinsip P6 & Mockup F1).
- **Kategori Skenario**: Usability & Mobile Layout Responsiveness.
- **Testing yang Dilakukan**:
  1. Login sebagai `checker@example.com` pada perangkat mobile (atau simulasi viewport 390 × 844 px).
  2. Buka menu **Kiriman** (ikon paket di navigasi bawah HP).
  3. Buka salah satu DO yang siap disiapkan (`status = approved`).
  4. Periksa elemen antarmuka: nomor DO, nama customer, nama gudang, daftar item dengan checkbox centang, dan tombol aksi bawah.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Antarmuka tampil rapi, kartu item besar mudah di-tap dengan jempol (tinggi tap target $\ge 48$ px). Terdapat tombol aksi jelas: **"Barang Sesuai (Kirim)"** dan tombol sekunder **"Ada Kurang / Rusak (Retur DO)"**.
- **Solusi**:
  Sesuai dengan Mockup F1 (`F1_Kiriman-HP.html`).
- **Bukti Pengujian Nyata**:
  - URL Halaman: `/admin/kiriman/{DO}`.
  - State Tombol: Tombol Kirim dinonaktifkan dengan penjelasan jika Surat Jalan belum terbit (`SALES_STOCK_REQUIRE_SURAT_JALAN=true`).

---

### Kasus Uji UAT-CHK-02 (No Happy Path): Blokir Kirim Barang Jika Belum Selesai Centang Fisik Seluruh Item
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Mencegah kelalaian checker mengklik Kirim sebelum seluruh barang fisik selesai dicocokkan.
- **Kategori Skenario**: **No Happy Path (Incomplete Inspection)**.
- **Testing yang Dilakukan**:
  1. Buka DO yang berisi 3 item barang.
  2. Checker hanya mencentang 2 item (1 item belum dicentang).
  3. Klik tombol **"Kirim"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir aksi pengiriman dengan pesan peringatan:
  *"Periksa dan centang semua barang terlebih dahulu sebelum mengirim, atau pilih tombol 'Ada Kurang / Rusak' jika ada selisih."*
- **Solusi**:
  Tabel `delivery_order_item_checks` menyimpan riwayat centang fisik per item per user.
- **Bukti Pengujian Nyata**:
  - Respon UI: Modal peringatan berwarna amber muncul, pengiriman tidak diproses.

---

### Kasus Uji UAT-CHK-03 (No Happy Path): Validasi Input Qty Retur (Kurang + Rusak Melebihi Qty DO)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memastikan input retur di layar HP tidak mengizinkan jumlah barang kurang + rusak melebihi total kuantitas barang yang ada di DO.
- **Kategori Skenario**: **No Happy Path (Return Quantity Overflow)**.
- **Testing yang Dilakukan**:
  1. DO berisi 10 pcs `PROD-TEST-A`.
  2. Checker mengklik tombol **"Ada Kurang / Rusak"** $\rightarrow$ terbuka form Retur DO HP (`/admin/kiriman/{DO}/retur`).
  3. Pada item tersebut, checker memasukkan:
     - Qty Kurang: `7 pcs`
     - Qty Rusak / Reject: `5 pcs`
     - Total selisih: $7 + 5 = \mathbf{12\text{ pcs}}$ (melebihi kuantitas DO 10 pcs).
  4. Klik tombol **"Simpan & Ajukan Retur DO"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem menolak penyimpanan dan menandai baris input dengan pesan:
  *"PROD-TEST-A: kurang + rusak (12) melebihi qty DO (10)."*
- **Solusi**:
  Validasi ketat di [`DeliveryOrderShortageReturn.php:70-80`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/Hp/DeliveryOrderShortageReturn.php#L70-L80) membatasi:
  $$\text{short} + \text{reject} \le \text{item.quantity}$$
- **Bukti Pengujian Nyata**:
  - Respon Backend: `ValidationException` pada key `lines.{itemId}`.

---

### Kasus Uji UAT-CHK-04: Pembentukan Retur DO (DO.000xR) dan DO Pengganti (DO.000xC)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`) & `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §5: *"DO asal DO.000x $\rightarrow$ retur DO.000xR $\rightarrow$ DO pengganti DO.000xC. DO asal ditandai 'sudah diretur'. Hanya DO.000xC yang bisa dijadikan SJ."*
- **Kategori Skenario**: State Transition & Lifecycle Integrity.
- **Testing yang Dilakukan**:
  1. DO asal bernomor `DO-20261026-0008` (Qty: 20 pcs `PROD-TEST-A` dari Gudang Utama).
  2. Checker mendapati: 15 pcs kondisi baik diterima, 3 pcs kurang (tidak terbawa), 2 pcs rusak (pecah/cacat).
  3. Checker menginput selisih tersebut di layar HP dan submit.
  4. Buka menu Retur Produk di desktop, setujui retur tersebut.
  5. Periksa perubahan status DO asal dan daftar Perintah Kirim (DO).
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Terbentuk dokumen retur dengan nomor berakhiran R: `DO-20261026-0008R`.
  - DO asal `DO-20261026-0008` ditandai telah diretur (`returned_at` terisi tanggal & waktu sekarang).
  - Terbit otomatis dokumen **DO Pengganti** dengan nomor: `DO-20261026-0008C`.
  - DO pengganti `DO-20261026-0008C` berisi barang sejumlah kuantitas yang valid (15 pcs siap kirim).
- **Solusi**:
  Sistem di [`DeliveryOrderReturnProcessor.php:390-399`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderReturnProcessor.php#L390-L399) secara konsisten menghasilkan penomoran `DO.000xC`.
- **Bukti Pengujian Nyata**:
  - Kolom Database: `delivery_orders.returned_at` pada DO asal tidak null; DO baru memiliki `do_number = 'DO-20261026-0008C'`.

---

### Kasus Uji UAT-CHK-05: Penanganan Barang Rusak Masuk Gudang Karantina & Penyesuaian Stok
- **Peran Penguji Terautentikasi**: `Warehouse Staff` (`warehouse_staff@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §5: *"Retur yang disetujui tercatat di Stock Adjustment (referensi DO.000xR). Barang reject masuk lokasi karantina/rusak, bukan stok jual. Stok gudang asal bertambah sesuai qty retur."*
- **Kategori Skenario**: Stock Adjustment & Quarantine Isolation.
- **Testing yang Dilakukan**:
  1. Pada retur `DO-20261026-0008R` (Kurang: 3 pcs, Rusak: 2 pcs).
  2. Setujui retur tersebut oleh kepala gudang.
  3. Periksa mutasi stok di tabel `inventory_stocks` dan modul **Penyesuaian Stok (Stock Adjustment)**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Barang Kurang (3 pcs): Stok bebas di Gudang Utama bertambah kembali 3 pcs (batal terpotong).
  - Barang Rusak (2 pcs): Dipindahkan ke Gudang Karantina (`is_quarantine = true`). Tidak tercampur ke stok jual.
  - Tercatat riwayat Penyesuaian Stok dengan referensi dokumen `DO-20261026-0008R` dan tipe mutasi `"Retur DO"`.
- **Solusi**:
  Sesuai dengan List Developer Poin 15 & 16.
- **Bukti Pengujian Nyata**:
  - Database: Baris baru di tabel `inventory_movements` dengan tipe `"Retur DO"`, `notes = 'Referensi DO-20261026-0008R'`.
  - Stok Karantina: `inventory_stocks` pada gudang karantina bertambah 2 pcs.

---

### Kasus Uji UAT-CHK-06 (No Happy Path): Blokir Retur pada DO yang Sudah Memiliki Surat Jalan Terbit
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memastikan bahwa jika DO sudah dibuatkan Surat Jalan resmi (`issued`), DO tersebut **TIDAK BISA** diretur lagi lewat menu Retur DO HP (koreksi setelah SJ wajib lewat Retur Penjualan resmi).
- **Kategori Skenario**: **No Happy Path (Post-Dispatch Return Block)**.
- **Testing yang Dilakukan**:
  1. Ambil DO yang sudah terdaftar di Surat Jalan berstatus `issued`.
  2. Buka URL `/admin/kiriman/{DO}/retur` sebagai checker.
  3. Coba isi selisih kurang/rusak dan submit.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem menolak sebelum form dibuka atau saat submit dengan pesan:
  *"DO-20261026-XXXX sudah punya Surat Jalan aktif, jadi tidak bisa diretur dari sini."*
- **Solusi**:
  Fungsi `blockReason()` di [`DeliveryOrderShortageReturn.php:25`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/Hp/DeliveryOrderShortageReturn.php#L25) memblokir retur jika `suratJalan()->whereIn('status', ACTIVE_STATUSES)->exists()`.
- **Bukti Pengujian Nyata**:
  - Respon Backend: HTTP 422 `ValidationException` dengan pesan jelas.
