# MODUL UAT 09: INVENTORI, STOK BEBAS, PINDAH STOK & STOCK OPNAME HP
## LAPORAN HASIL PENGUJIAN AKURASI FISIK GUDANG, BLIND COUNT HP & MUTASI OVERFLOW (NO HAPPY PATH)
**Tanggal Pengujian:** 28 Oktober 2026  
**Aplikasi:** Duta Tunggal ERP (v2.0 Tailwind Native & Filament Mobile Engine)  
**Acuan Bisnis:** 
- `Alur_Bisnis_Ko_Handy.md` Bagian Inventory & Stock Opname
- `List_Pengerjaan_Developer.md` Bagian C (Poin C3) & D (Poin D1-D3)
- `UIUX_Spec_ERP_Duta_Tunggal.md` Layar D1 (Cek Stok Real-Time), D2 (Pindah Stok / Transfer Gudang), D3 (Stock Opname HP Blind Count)

**Peran Penguji Terautentikasi:**
- `Checker Gudang` (`checker@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Inventory Manager / Supervisor` (`inventory_manager@example.com`)

---

## RINGKASAN EKSEKUTIF PENGUJIAN (EXECUTIVE SUMMARY)

| ID Skenario | Deskripsi Pengujian | Kategori | Hasil UAT | Status |
| :--- | :--- | :--- | :--- | :---: |
| **UAT-INV-01** | Verifikasi Rumus Stok Bebas Sadar Reservasi DO: `Stok Bebas = Fisik (100) - Terkunci DO (30) = 70`. Cek Stok Real-Time Desktop & HP menampilkan 3 angka. Form SO menampilkan badge `Stok bebas 70 pcs` & warning *"Stok kurang 5 · Sisa dikirim menyusul"* saat kuantitas 75 pcs. | Stock Calculation & Selling Availability | **100% Sesuai Spesifikasi** | **PASS** |
| **UAT-INV-02** | Stock Opname HP Blind Count: Checker menghitung via HP tanpa melihat angka sistem (`system_qty` 100% disembunyikan dan tidak pernah dikirim ke browser / DOM). Input fisik 48 pcs (stok sistem 50 pcs). Supervisor desktop baru melihat selisih (-2 pcs, defisit Rp 50.000) setelah checker menekan selesai. | Blind Count Security & Anti-Fraud | **100% Sesuai Spesifikasi** | **PASS** |
| **UAT-INV-03** | **No Happy Path**: Pindah Stok (Transfer Antar Gudang) Melebihi Stok Bebas Gudang Asal. Transfer 15 pcs `PROD-TEST-B` dari Gudang Kenari (stok bebas 10 pcs) diblokir secara client-side & server-side dengan pesan presisi *"Kuantitas transfer untuk PROD-TEST-B (15 pcs) melebihi stok bebas yang tersedia di Gudang Kenari (10 pcs)."*. | Data Integrity & Inventory Safety | **100% Sesuai Spesifikasi** | **PASS** |

---

## 1. Kasus Uji UAT-INV-01: Verifikasi Rumus Stok Bebas Sadar Reservasi DO

### A. Parameter & Data Uji Riil
- **Gudang:** `Gudang Utama` (ID: 38, Kode: `GDG-UTM`)
- **Produk:** `PROD-TEST-A` (Pipa Seamless 5/8 Inch, ID: 60)
- **Kondisi Persediaan Database (`inventory_stocks`):**
  - Stok Fisik (`qty_available`): `100.00 pcs`
  - Terkunci DO / Reservasi (`qty_reserved`): `30.00 pcs`
  - Rumus Stok Bebas: `qty_available - qty_reserved` = `70.00 pcs`
- **Uji Penjualan (Sales Order):**
  - Customer: `PT Pelanggan Logam Utama` (ID: 16)
  - Kuantitas SO yang Diinput: `75.00 pcs` (> stok bebas 70 pcs)

### B. Tahapan Pengujian & Hasil Eksekusi
1. **Layar Cek Stok Desktop (`/gudang/stok?search=PROD-TEST-A`):**
   - User `warehouse_staff@example.com` membuka katalog ketersediaan stok real-time.
   - Tabel menampilkan pembagian kolom secara transparan:
     - Stok Fisik: **100**
     - Terpesan (DO): **30**
     - Siap Kirim (Free / Bebas): **70**
     - Status: **Aman**
2. **Layar Cek Stok Mode Mobile (Viewport HP 375x667):**
   - Memastikan tampilan responsif pada layar ponsel staf gudang tetap menyajikan KPI stok bebas secara konsisten.
3. **Form Pembuatan Sales Order (`/penjualan/so/create`):**
   - Staf penjualan memasukkan produk `PROD-TEST-A`.
   - Badge ketersediaan otomatis menyala hijau: `Stok bebas 70 pcs`.
   - Saat kuantitas diubah menjadi `75 pcs`, sistem secara reaktif memunculkan teks peringatan amber:
     **`Stok kurang 5 · Sisa dikirim menyusul`**.
   - Sistem tidak memblokir order komersial, tetapi memberikan visibilitas penuh bahwa ada kekurangan 5 pcs yang harus dipenuhi dari jadwal kirim menyusul/backorder.

### C. Verifikasi Integritas Database
```json
{
  "product_id": 60,
  "sku": "PROD-TEST-A",
  "qty_available": 100.0,
  "qty_reserved": 30.0,
  "calculated_free_stock": 70.0
}
```

### D. Bukti Tangkapan Layar (Google Chrome)
1. **Cek Stok Real-Time Desktop (Fisik: 100, Terpesan: 30, Siap Kirim: 70):**  
   ![Cek Stok Real-Time Desktop](screenshots_modul_09/01_uat_inv_01_cek_stok_desktop.png)
   *File: [`screenshots_modul_09/01_uat_inv_01_cek_stok_desktop.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/01_uat_inv_01_cek_stok_desktop.png)*

2. **Cek Stok Real-Time Mobile Viewport:**  
   ![Cek Stok Real-Time Mobile](screenshots_modul_09/02_uat_inv_01_cek_stok_mobile.png)
   *File: [`screenshots_modul_09/02_uat_inv_01_cek_stok_mobile.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/02_uat_inv_01_cek_stok_mobile.png)*

3. **Form Sales Order: Badge Stok Bebas 70 Pcs & Peringatan Stok Kurang 5 Pcs:**  
   ![SO Stock Warning 75 Pcs](screenshots_modul_09/03_uat_inv_01_so_stock_warning_75pcs.png)
   *File: [`screenshots_modul_09/03_uat_inv_01_so_stock_warning_75pcs.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/03_uat_inv_01_so_stock_warning_75pcs.png)*

---

## 2. Kasus Uji UAT-INV-02: Stock Opname HP Blind Count (Kerahasiaan Angka Sistem & Anti-Fraud)

### A. Parameter & Data Uji Riil
- **Sesi Opname:** `#OPN-TEST-BLIND-001` (ID: 8)
- **Gudang:** `Gudang Utama` (ID: 38)
- **Petugas Hitung Fisik (HP):** `Checker Gudang` (`checker@example.com`)
- **Supervisor Desktop:** `Inventory Manager` (`inventory_manager@example.com`)
- **Produk Opname:** `PROD-TEST-OPNAME` (Pipa Tembaga AC 1/4 Inch, ID: 63)
  - Stok Tercatat di Sistem (`system_qty`): `50.00 pcs`
  - HPP Produk (`unit_cost`): `Rp 25.000`
  - Hasil Hitung Fisik Nyata Checker (`physical_qty`): `48.00 pcs` (Defisit 2 pcs)

### B. Tahapan Pengujian & Hasil Eksekusi
1. **Layar HP Checker (`/admin/opname-hp?opname=8` pada iPhone 14 / 390x844):**
   - Checker membuka sesi opname langsung di ponsel.
   - Layar menampilkan form hitung murni:
     - Header: `OPNAME · GDG-UTM · RAK RAK-UTM-01`
     - Nama Produk: `Pipa Tembaga AC 1/4 Inch`
     - SKU & Satuan: `PROD-TEST-OPNAME · Rak RAK-UTM-01 · satuan Piece`
     - Stepper input kuantitas: `[-] [ 0 ] [+]`
     - Catatan Anti-Fraud: *"Jumlah di sistem disembunyikan supaya hitungan apa adanya. Selisih muncul setelah selesai."*
2. **Audit Keamanan Blind Count (DOM & Payload Inspection):**
   - Evaluasi menyeluruh terhadap HTML, DOM, dan payload jaringan Livewire:
     - `hasDisembunyikanText`: `true`
     - `hasSystemQtyKeyword`: `false` (angka 50 sama sekali tidak pernah dikirim ke browser HP).
     - Query backend pada [`OpnameHpPage.php:229`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Pages/Hp/OpnameHpPage.php#L229) terbukti hanya mengambil kolom `['id', 'stock_opname_id', 'product_id', 'rak_id', 'physical_qty']`.
3. **Penyelesaian Penghitungan Fisik:**
   - Checker menginput angka riil `48 pcs` lalu menekan **"Simpan & berikutnya"**.
   - Halaman beralih ke ringkasan: *"Semua barang sudah dihitung (1 / 1 dihitung)"* dan menampilkan daftar item dengan kuantitas 48.
   - Checker menekan tombol hijau **"Selesai menghitung"** dan mengonfirmasi dialog konfirmasi browser:
     *`Selesai menghitung OPN-TEST-BLIND-001? Hasil hitung tidak bisa diubah dari HP setelah ini.`*
   - Status sesi opname otomatis beralih menjadi `completed`.
4. **Review Selisih di Layar Desktop Supervisor (`/gudang/opname/8`):**
   - Supervisor masuk sebagai `inventory_manager@example.com` di komputer kantor.
   - Status sesi: **Selesai Hitung (Menunggu Approval)**.
   - Selisih fisik vs sistem baru pertama kali dibuka dan dihitung di layar supervisor:
     - Stok Sistem: `50`
     - Hasil Fisik: `48`
     - Selisih Qty: **-2**
     - Selisih Nilai (Rp): **-Rp 50.000**
     - Metrik Card: Total Produk `1`, Telah Dihitung `1 / 1`, Defisit `1`, Total Selisih `Rp -50.000`.

### C. Verifikasi Integritas Database
```json
{
  "opname_id": 8,
  "opname_number": "OPN-TEST-BLIND-001",
  "status": "completed",
  "product_id": 63,
  "system_qty": 50.0,
  "physical_qty": 48.0,
  "difference_qty": -2.0,
  "difference_value": -50000.0
}
```

### D. Bukti Tangkapan Layar (Google Chrome)
1. **Layar Stock Opname HP Blind Count (iPhone 14 Viewport):**  
   ![Opname HP Blind Count](screenshots_modul_09/04_uat_inv_02_opname_hp_blind_count_screen.png)
   *File: [`screenshots_modul_09/04_uat_inv_02_opname_hp_blind_count_screen.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/04_uat_inv_02_opname_hp_blind_count_screen.png)*

2. **Ringkasan HP Setelah Selesai Menghitung Fisik:**  
   ![Opname HP Counted Summary](screenshots_modul_09/05_uat_inv_02_opname_hp_counted_summary.png)
   *File: [`screenshots_modul_09/05_uat_inv_02_opname_hp_counted_summary.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/05_uat_inv_02_opname_hp_counted_summary.png)*

3. **Lembar Kerja Supervisor Desktop (Review Selisih Fisik vs Sistem):**  
   ![Opname Desktop Review Selisih](screenshots_modul_09/06_uat_inv_02_opname_desktop_variance_review.png)
   *File: [`screenshots_modul_09/06_uat_inv_02_opname_desktop_variance_review.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/06_uat_inv_02_opname_desktop_variance_review.png)*

---

## 3. Kasus Uji UAT-INV-03 (No Happy Path): Pindah Stok Melebihi Stok Bebas Asal

### A. Parameter & Data Uji Riil
- **Gudang Asal (Sumber):** `Gudang Kenari` (ID: 39, Kode: `GDG-KNR`)
- **Gudang Tujuan:** `Gudang Utama` (ID: 38, Kode: `GDG-UTM`)
- **Produk:** `PROD-TEST-B` (Pipa Galvanis 1/2 Inch, ID: 61)
- **Stok Bebas Sumber Tersedia:** `10.00 pcs`
- **Kuantitas Transfer yang Dicoba Dipindahkan:** `15.00 pcs` (Overflow +5 pcs)

### B. Tahapan Pengujian & Hasil Eksekusi
1. **Form Pindah Stok Antar Gudang (`/gudang/transfer/create`):**
   - User `warehouse_staff@example.com` memilih Gudang Asal: `Gudang Kenari` dan Gudang Tujuan: `Gudang Utama`.
   - Menginput produk `PROD-TEST-B` (Stok Tersedia: 10 Piece) dengan kuantitas: `15 pcs`.
2. **Eksekusi Pengajuan Transfer:**
   - Staf menekan tombol hijau **"Simpan & Ajukan Transfer"**.
3. **Hasil Validasi Sistem:**
   - **Client-Side Validation:** Input kuantitas memiliki atribut `max="10"` yang mencegah submit lokal.
   - **Server-Side Validation:** Request ditolak oleh Laravel Controller dengan HTTP 422 `ValidationException`.
   - Muncul banner peringatan merah pekat di atas formulir:
     > **Perhatian / Validasi Ditolak:**  
     > • *Kuantitas transfer untuk PROD-TEST-B (15 pcs) melebihi stok bebas yang tersedia di Gudang Kenari (10 pcs).*
4. **Verifikasi Keamanan Stok:**
   - Tidak ada dokumen transfer (`StockTransfer`) yang terbentuk (`new_transfers_count = 0`).
   - Saldo stok di Gudang Kenari tetap utuh `10.00 pcs` tanpa mutasi keluar tidak sah.

### C. Verifikasi Integritas Database
```json
{
  "warehouse_id": 39,
  "warehouse_name": "Gudang Kenari",
  "product_id": 61,
  "sku": "PROD-TEST-B",
  "qty_available": 10.0,
  "qty_reserved": 0.0,
  "new_transfers_count": 0
}
```

### D. Bukti Tangkapan Layar (Google Chrome)
1. **Form Input Pindah Stok dengan Kuantitas Overflow (15 Pcs dari Stok 10 Pcs):**  
   ![Transfer Form Overflow Input](screenshots_modul_09/07_uat_inv_03_transfer_form_overflow_input.png)
   *File: [`screenshots_modul_09/07_uat_inv_03_transfer_form_overflow_input.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/07_uat_inv_03_transfer_form_overflow_input.png)*

2. **Form Pindah Stok Ditolak oleh Validasi Stok Bebas (Pesan Error Eksplisit):**  
   ![Transfer Overflow Blocked Error](screenshots_modul_09/08_uat_inv_03_transfer_overflow_blocked_error.png)
   *File: [`screenshots_modul_09/08_uat_inv_03_transfer_overflow_blocked_error.png`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_09/08_uat_inv_03_transfer_overflow_blocked_error.png)*

---

## 4. KESIMPULAN & STATUS AKHIR PENGUJIAN

Seluruh 3 kasus uji pada **Modul 09: Inventori, Stok Bebas, Pindah Stok & Stock Opname HP** telah dieksekusi secara end-to-end melalui browser automation nyata berbasis Google Chrome dan database audit nyata.

1. **Rumus Stok Bebas Sadar DO** (`Stok Bebas = Fisik - Terkunci`) bekerja konsisten di level database, layar Cek Stok, dan modul Sales Order.
2. **Kerahasiaan Angka Sistem pada Stock Opname HP (Blind Count)** terbukti aman dari manipulasi dan inspect element karena `system_qty` tidak pernah dikirim ke browser HP, dan selisih hanya dapat direview oleh supervisor desktop setelah sesi selesai.
3. **Pemblokiran Pindah Stok Overflow** bekerja tangguh di dua lapis (client-side dan server-side) sehingga integritas stok antar gudang terlindungi dari nilai minus.

**Status Akhir UAT Modul 09:** **100% PASS (LULUS SEMPURNA)**
