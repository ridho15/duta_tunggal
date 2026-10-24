# PANDUAN PENGUJIAN UAT (NO HAPPY PATH) — ROLE 8: SUPER ADMIN, OWNER & AUDITOR
## SISTEM ERP DUTA TUNGGAL

- **Peran yang Diuji:** `Super Admin`, `Owner`, `Auditor`, & `IT Support`
- **Modul Terkait:** Manajemen Pengguna & Hak Akses RBAC (`/pengaturan/users`), Master Cabang & Multi-Tenancy Boundary (`/admin/cabangs`), Konfigurasi Mata Uang & Pajak, Audit Trail Global
- **Tujuan Pengujian:** Memverifikasi keamanan otorisasi tingkat tinggi (*high-level authorization*), isolasi batas cabang (*multi-branch security boundary*), penegakan sifat *read-only* murni pada peran Auditor, pencegahan penguncian sistem akibat self-deletion (*system lockout prevention*), dan integritas penugasan hak akses.

---

## 1. MATRIKS HAK AKSES & PEMISAHAN TUGAS (SEGREGATION OF DUTIES)

| Fitur / Aksi | Role `Auditor` | Role `IT Support` | Role `Super Admin / Owner` | Aturan Bisnis & Pengamanan |
| :--- | :---: | :---: | :---: | :--- |
| **Lihat Seluruh Modul Sistem (View Any)** | ✔ (Read-Only) | ✔ | ✔ | Auditor memiliki hak baca menyeluruh. |
| **Tambah / Ubah / Hapus Transaksi Finansial** | ❌ (DILARANG) | ❌ (DILARANG) | ✔ | Auditor & IT dilarang mencatat mutasi uang. |
| **Kelola Akun Pengguna & Password** | ❌ | ✔ | ✔ | Mengatur akses login karyawan. |
| **Ubah Struktur Role & Permissions** | ❌ | ❌ | ✔ | Mengontrol pembagian wewenang RBAC. |
| **Akses Lintas Seluruh Cabang** | ✔ (Read-Only) | ✔ (Teknis) | ✔ (Penuh) | Pemilik perusahaan melihat konsolidasi. |
| **Hapus Akun Sendiri yang Sedang Aktif** | ❌ (DILARANG) | ❌ (DILARANG) | ❌ (DILARANG) | Pencegahan admin terkunci di luar sistem. |

---

## 2. SKENARIO PENGUJIAN NEGATIF (NO HAPPY PATH)

### Kasus UAT-SEC-NEG-01: Pengguna Non-Admin Mencoba Mengakses Halaman Manajemen Pengguna
- **Tipe Pengujian:** Penegakan Batasan Akses Administrasi (*Administrative Boundary Enforcement*)
- **Prasyarat:** Login dengan akun role **Sales**, **Warehouse Staff**, atau **Kasir**.
- **Langkah-langkah Pengujian:**
  1. Login sebagai staf biasa (misal: role `Sales`).
  2. Ketik langsung URL manajemen pengguna pada address bar browser:
     `http://localhost:8009/pengaturan/users`.
  3. Tekan Enter.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem langsung memblokir akses dan menampilkan halaman **HTTP 403 Forbidden** atau me-redirect ke dashboard dengan flash message: *"Akses ditolak: Anda tidak memiliki izin untuk mengelola akun pengguna."*
  - Data daftar pengguna dan nomor handphone tidak boleh bocor ke staf operasional.
- **Verifikasi Codebase:** `app/Policies/UserPolicy.php:14` & Spatie Permission Middleware.

---

### Kasus UAT-SEC-NEG-02: Pengguna Mengakses Transaksi Cabang Lain Tanpa Wewenang
- **Tipe Pengujian:** Isolasi Data Antar Cabang (*Multi-Branch Data Isolation*)
- **Prasyarat:** Akun `sales.surabaya` ditugaskan di **Cabang Surabaya** (tidak memiliki hak akses `canAccessCabang` untuk Pusat). Terdapat Sales Order `SO-PUSAT-001` milik **Cabang Pusat Jakarta**.
- **Langkah-langkah Pengujian:**
  1. Login sebagai `sales.surabaya`.
  2. Buka URL langsung ke detail transaksi milik Cabang Pusat:
     `/penjualan/so/{id_so_pusat}`.
  3. Tekan Enter.
- **Hasil yang Diharapkan (Expected Result):**
  - Server mengembalikan respon **HTTP 403 Forbidden** (*Unauthorized: You do not have access to data from another branch*).
  - Pengguna cabang tidak dapat mengintip nilai transaksi atau mengubah status dokumen cabang lain.
- **Verifikasi Codebase:** `canAccessCabang()` helper pada semua Controller dan Policy (`SaleOrderPolicy`, `QuotationPolicy`, `DeliveryOrderPolicy`).

---

### Kasus UAT-SEC-NEG-03: Auditor Mencoba Melakukan Operasi Modifikasi Data (Create, Update, Delete)
- **Tipe Pengujian:** Penegakan Sifat Read-Only Murni (*Auditor Read-Only Enforcement*)
- **Prasyarat:** Login dengan akun role **Auditor**.
- **Langkah-langkah Pengujian:**
  1. Buka menu Jurnal Umum, Faktur Penjualan, atau Stok Gudang.
  2. Periksa apakah tombol Tambah, Edit, atau Hapus ditampilkan di antarmuka.
  3. Coba kirim request manipulasi data via API/cURL:
     - `POST /keuangan/journal`
     - `PUT /penjualan/so/{id}`
     - `DELETE /master/customer/{id}`
- **Hasil yang Diharapkan (Expected Result):**
  - Seluruh tombol aksi create/edit/delete **tersembunyi** dari UI Auditor.
  - Setiap request HTTP selain GET (POST, PUT, PATCH, DELETE) wajib ditolak dengan **HTTP 403 Forbidden**.
  - Auditor hanya dapat melihat data dan mengunduh laporan PDF/Excel.
- **Verifikasi Codebase:** `database/seeders/RoleSeeder.php:267` (Auditor special block assigning only `view any` permissions).

---

### Kasus UAT-SEC-NEG-04: Penghapusan Akun Pengguna yang Sedang Login Sendiri (Self-Deletion Prevention)
- **Tipe Pengujian:** Pencegahan Kehilangan Akses Kritis (*System Lockout Prevention*)
- **Prasyarat:** Login sebagai **Super Admin** (User ID: `1`).
- **Langkah-langkah Pengujian:**
  1. Buka menu **Pengaturan > Manajemen Pengguna** (`/pengaturan/users`).
  2. Cari nama akun sendiri yang sedang aktif digunakan saat ini.
  3. Klik tombol Hapus Akun atau kirim request: `DELETE /pengaturan/users/1`.
  4. Konfirmasi modal penghapusan.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem menolak aksi penghapusan dengan alert peringatan: *"Tindakan tidak diizinkan: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan."*
  - Akun Super Admin tetap aktif dan sesi login tidak terputus.
- **Verifikasi Codebase:** `UserController::destroy` self-deletion safety check.

---

### Kasus UAT-SEC-NEG-05: Penonaktifan Seluruh Akun Super Admin yang Ada di Sistem
- **Tipe Pengujian:** Perlindungan Keberadaan Administrator (*Last Super-Admin Protection*)
- **Prasyarat:** Sistem hanya memiliki 1 akun Super Admin aktif.
- **Langkah-langkah Pengujian:**
  1. Buka edit profil Super Admin tersebut di `/pengaturan/users/{id}/edit`.
  2. Hapus centang pada opsi **Status Aktif (Active)**.
  3. Klik tombol Simpan.
- **Hasil yang Diharapkan (Expected Result):**
  - Sistem memblokir penonaktifan: *"Tidak dapat menonaktifkan akun. Sistem harus memiliki minimal satu akun Super Admin yang berstatus aktif."*
  - Akun tetap berstatus aktif.
- **Verifikasi Codebase:** `UserObserver` / `UserController::update` active admin count check.

---

### Kasus UAT-SEC-NEG-06: Penetapan Hak Akses (Permission) Tidak Terdaftar ke Suatu Role
- **Tipe Pengujian:** Integritas Tabel Otorisasi Spatie
- **Prasyarat:** Form pengaturan Role & Permission.
- **Langkah-langkah Pengujian:**
  1. Coba kirim request penetapan permission baru ke role Sales dengan nama permission acak yang tidak terdaftar di sistem:
     `POST /pengaturan/roles/{id}/permissions` dengan payload `{"permission": "hack system arbitrary"}`.
- **Hasil yang Diharapkan (Expected Result):**
  - Validasi menolak request: *"Hak akses (permission) yang dipilih tidak terdaftar di dalam sistem."*
  - Tidak ada record sampah yang masuk ke tabel `permissions` maupun `role_has_permissions`.
- **Verifikasi Codebase:** `RolePermissionValidator` rule `'permissions.*' => 'exists:permissions,name'`.

---

### Kasus UAT-SEC-NEG-07: IT Support Mencoba Melakukan Transaksi Finansial (Posting Jurnal / Bayar Vendor)
- **Tipe Pengujian:** Pemisahan Wewenang Teknis vs Finansial (*SoD Enforcement*)
- **Prasyarat:** Login dengan akun role **IT Support**.
- **Langkah-langkah Pengujian:**
  1. Buka menu Keuangan > Jurnal Umum (`/keuangan/journal/create`).
  2. Buka menu Pembayaran Vendor (`/pembelian/payment/create`).
  3. Coba kirim request POST transaksi keuangan.
- **Hasil yang Diharapkan (Expected Result):**
  - Role IT Support tidak memiliki akses ke pencatatan transaksi uang (**HTTP 403 Forbidden**).
  - IT Support hanya berwenang mengelola konfigurasi teknis (user, role, mata uang, pajak).
- **Verifikasi Codebase:** `RoleSeeder.php:268` IT Support resource mapping.

---

## 3. LEMBAR HASIL PENGUJIAN ROLE 8

| Kode Uji | Skenario | Hasil Aktual Pengujian | Status (PASS/FAIL) | Catatan / Bug Log |
| :--- | :--- | :--- | :---: | :--- |
| UAT-SEC-NEG-01 | Non-admin akses manajemen pengguna | | | |
| UAT-SEC-NEG-02 | Pengguna akses transaksi cabang lain | | | |
| UAT-SEC-NEG-03 | Auditor lakukan aksi modifikasi data (403) | | | |
| UAT-SEC-NEG-04 | Admin hapus akun sendiri (Self-deletion) | | | |
| UAT-SEC-NEG-05 | Nonaktifkan satu-satunya Super Admin | | | |
| UAT-SEC-NEG-06 | Tetapkan permission fiktif ke role | | | |
| UAT-SEC-NEG-07 | IT Support lakukan transaksi finansial | | | |

- **Tanggal Pengujian:** ____________________
- **Nama Penguji (Tester):** ____________________
- **Tanda Tangan:** ____________________
