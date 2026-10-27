# MODUL UAT 09: INVENTORI, STOK BEBAS, PINDAH STOK & STOCK OPNAME HP
## PENGUJIAN AKURASI FISIK GUDANG, BLIND COUNT & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `Alur_Bisnis_Ko_Handy.md` Bagian Inventory; `List_Pengerjaan_Developer.md` Bagian C (Poin C3) & D; `UIUX_Spec_ERP_Duta_Tunggal.md` Layar D1, D2, D3.
**Peran Penguji Terautentikasi:**
- `Checker Gudang` (`checker@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Inventory Manager` (`inventory_manager@example.com`)

---

### Kasus Uji UAT-INV-01: Verifikasi Rumus Stok Bebas Sadar Reservasi DO
- **Peran Penguji Terautentikasi**: `Warehouse Staff` (`warehouse_staff@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §Inventory: *"Stok bebas = stok fisik − stok terkunci DO. Angka inilah yang dipakai untuk menjual dan membuat DO."*
- **Kategori Skenario**: Stock Calculation & Selling Availability.
- **Testing yang Dilakukan**:
  1. Periksa produk `PROD-TEST-A` di Gudang Utama:
     - Fisik (`qty_available`) = 100 pcs.
     - Terkunci DO (`qty_reserved`) = 30 pcs.
  2. Buka layar Cek Stok di desktop maupun HP.
  3. Coba buat Sales Order baru untuk produk tersebut sebanyak 75 pcs (melebihi stok bebas 70 pcs, meskipun stok fisik 100 pcs).
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Pada layar Cek Stok: Tampil jelas 3 angka: Fisik: `100 pcs`, Terkunci: `30 pcs`, Bebas: `70 pcs`.
  - Pada input SO: Muncul indikator status stok *"Stok Kurang (Bebas: 70 pcs)"*.
  - Sistem konsisten menggunakan angka stok bebas (70 pcs) sebagai acuan jual, bukan stok fisik bruto (100 pcs).
- **Solusi**:
  Rumus di [`StockAvailability.php:47-56`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/StockAvailability.php#L47-L56) diterapkan di seluruh modul.
- **Bukti Pengujian Nyata**:
  - Kolom Database: `(inventory_stocks.qty_available - inventory_stocks.qty_reserved) = 70.00`.

---

### Kasus Uji UAT-INV-02: Stock Opname HP Blind Count (Angka Sistem Tidak Terkirim ke Browser HP)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy: *"Stock opname: hitung fisik (blind count), checker tidak boleh melihat angka sistem agar penghitungan fisik murni dan objektif"*.
- **Kategori Skenario**: Blind Count Security & Anti-Fraud.
- **Testing yang Dilakukan**:
  1. Supervisor membuat sesi Stock Opname baru untuk Gudang Utama.
  2. Checker login di HP membuka `/admin/opname-hp`.
  3. Periksa tampilan daftar barang yang harus dihitung.
  4. Lakukan inspeksi jaringan / inspect source browser (mencari variabel `system_qty`, `qty_available`, dsb.).
  5. Masukkan hasil hitung fisik riil: 48 pcs (stok sistem sebenarnya 50 pcs).
  6. Klik tombol **"Selesai Menghitung"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Pada layar HP checker, hanya ada kolom: Nama Barang, Lokasi Rak, dan Input Hasil Hitung Fisik. **Sama sekali tidak ada angka sistem**.
  - Pada payload JSON / Livewire: Kolom `system_qty` tidak di-serialize ke frontend (terbukti aman dari inspect element).
  - Selisih (-2 pcs) baru muncul di layar desktop supervisor setelah checker menekan selesai.
- **Solusi**:
  Implementasi di `OpnameHpPage.php` secara ketat memproteksi kerahasiaan stok sistem.
- **Bukti Pengujian Nyata**:
  - Tampilan HP: Form blind count murni sesuai Mockup D3 (`D3_Opname-HP.html`).

---

### Kasus Uji UAT-INV-03 (No Happy Path): Pindah Stok (Transfer Gudang) dengan Kuantitas Melebihi Stok Bebas Asal
- **Peran Penguji Terautentikasi**: `Warehouse Staff` (`warehouse_staff@example.com`)
- **Tujuan Pengujian**: Memastikan transfer antar gudang ("Pindah Stok") tidak mengizinkan pengiriman melebihi stok bebas gudang asal (mencegah stok asal minus).
- **Kategori Skenario**: **No Happy Path (Inter-Warehouse Stock Transfer Overflow)**.
- **Testing yang Dilakukan**:
  1. Gudang Kenari memiliki stok bebas `PROD-TEST-B` sebanyak 10 pcs.
  2. Buka menu **INVENTORY $\rightarrow$ Pindah Stok (Transfer Stok)**, klik **"Buat Pindah Stok"**.
  3. Pilih Dari Gudang: `Gudang Kenari`, Ke Gudang: `Gudang Utama`.
  4. Masukkan kuantitas transfer: `15 pcs` (lebih 5 pcs).
  5. Klik tombol Simpan & Kirim.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir transfer dengan pesan validasi:
  *"Kuantitas transfer untuk PROD-TEST-B (15 pcs) melebihi stok bebas yang tersedia di Gudang Kenari (10 pcs)."*
- **Solusi**:
  Layanan `StockTransferService` memverifikasi ketersediaan stok bebas gudang asal sebelum mutasi in-transit dilakukan.
- **Bukti Pengujian Nyata**:
  - Respon Backend: HTTP 422 `ValidationException`, stok Gudang Kenari tetap utuh 10 pcs.
