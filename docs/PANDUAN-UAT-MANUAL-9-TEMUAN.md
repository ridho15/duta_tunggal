# Panduan UAT Manual — 9 Temuan Duta Tunggal ERP

Panduan ini berdiri sendiri: **Bagian A** menyiapkan semua data (persis nilai yang diketik), **Bagian B** adalah skrip uji per temuan.
Semua nama data diberi awalan **UAT** supaya mudah dicari di dropdown dan tidak bercampur dengan data asli.

## 0. Sebelum mulai

- **Alamat aplikasi:** staging `https://dutatunggal.gpt-biomekanika.id` (atau lokal `http://localhost:8009` — jangan `127.0.0.1`).
- **Pastikan perbaikan sudah ter-deploy** (migrasi dijalankan, aset frontend di-build). Tanpa itu kolom "Gudang Tujuan" di form PO tidak muncul dan hasil uji tidak berarti. Setelah deploy, buka aplikasi dengan **Ctrl+F5**.
- Semua alamat halaman di bawah ditulis relatif, misalnya `/admin/suppliers` = alamat aplikasi + `/admin/suppliers`. Bisa juga lewat sidebar (**Data Master**, **Pembelian**, **Penjualan**, **Pengiriman**, **Inventory**, **Akuntansi**, **Manajemen User & Role**).
- **Aturan emas untuk data master:** kolom **"Status (Aktif / Tidak Aktif)" pada Cabang dan Gudang default-nya TIDAK tercentang.** Data yang tidak diaktifkan **tidak muncul di dropdown** PO, DO, dan Penyesuaian Stok. Selalu centang.
- **Urutan langkah = urutan field di layar, dari atas ke bawah.** Bila suatu kolom harus diisi lebih dulu dari urutan layarnya (mis. Gudang sebelum Generate Kode Rak), panduan menyebutnya secara eksplisit. Nama kolom ditulis persis seperti label di form.
- Tombol berlabel ikon "Generate" (panah melingkar) di samping kolom kode/nomor mengisi kode otomatis. Klik saja.
- Jika data yang Anda butuhkan **sudah ada** (misalnya cabang Pusat, satuan PCS), pakai yang ada dan **catat nama aslinya** — jangan dibuat ganda.

## A. Data master (kerjakan berurutan)

**Cara membaca tabel:** kolom **Wajib** berisi ✔ bila kolom itu harus diisi (di layar bertanda `*`). Kolom yang tidak wajib boleh dikosongkan atau dibiarkan default. Klik tombol **Buat** (atau **Simpan**) di bawah form; bila ada kolom berwarna merah, isi lalu simpan lagi.

**Urutan tidak boleh dibalik** karena saling bergantung: Cabang → Gudang → Rak → Satuan → Kategori → Supplier → Produk → Customer → User → Stok awal → Akun bank.

**Aturan format yang berlaku di semua form:**
- *Telepon / Handphone / Fax:* hanya boleh angka, `+`, spasi, titik, kurung, dan tanda hubung, dengan **6–20 digit**. Contoh benar: `021 12345678`, `081234567890`, `+62 21 12345678`.
- Tombol berikon **panah melingkar** di samping kolom kode = *Generate* (mengisi kode otomatis).
- Bila dropdown tidak menampilkan data yang baru Anda buat, lihat tabel "Cek cepat" di akhir bagian ini.

### A1. Cabang — `/admin/cabangs`
**Cabang Pusat = cabang dengan kode `CBG-001`.** Sistem mengenali Pusat dari **kode** ini (PO dan QC memakai `CBG-001` untuk "Cabang Akuntansi (Pusat)"), bukan dari kata "Pusat" pada nama. Di data lokal namanya "Cabang Pusat Jakarta". Buka daftar Cabang, cari kode `CBG-001`, dan **jangan membuat Pusat baru**.
> **Hati-hati:** "Cabang Administrasi Jakarta **Pusat**" (kode CBG-144) namanya memuat kata "Pusat" tetapi **bukan** kantor pusat. Yang dipakai selalu yang berkode `CBG-001`.
> Bila di lingkungan Anda tidak ada cabang berkode `CBG-001`, hentikan dan minta admin data memastikan kantor pusat, karena PO/QC akan salah menentukan cabang akuntansi.
**Cabang kedua** — klik **Buat**, isi (hati-hati: **Nama** dan **Alamat** adalah dua kolom berbeda; isi Nama dengan `UAT Cabang B`, bukan alamat):

| Kolom | Wajib | Isi | Keterangan |
|---|---|---|---|
| Kode | ✔ | klik Generate | maksimal 20 karakter, harus unik |
| Nama | ✔ | `UAT Cabang B` | maksimal 100 karakter |
| Alamat | ✔ | `Jl. Uji Cabang B No. 2, Surabaya` | |
| Telepon | ✔ | `031 12345678` | lihat aturan format |
| Kenaikan Harga (%) | – | `0` | default 0 |
| Warna Background | ✔ | klik kotak warna, pilih warna apa saja | wajib dipilih |
| Tipe Penjualan | ✔ | `Semua` | default |
| Kode Invoice Pajak / Non Pajak / Pajak Walk-in | – | kosongkan | |
| Nama di Kwitansi, Label Invoice Pajak / Non Pajak | – | kosongkan | |
| Logo Invoice Non Pajak | – | kosongkan | |
| Bisa Lihat Stok Cabang Lain saat Penjualan | – | biarkan mati | |
| **Status (Aktif / Tidak Aktif)** | **✔ (centang)** | **centang** | **default tidak tercentang** |

### A2. Gudang — `/admin/warehouses`
Buat **dua** gudang. Klik **Buat**, isi:

| Kolom | Wajib | Gudang 1 | Gudang 2 |
|---|---|---|---|
| Kode | ✔ | Generate | Generate |
| Nama | ✔ | `UAT Gudang Utama` | `UAT Gudang Cabang B` |
| Cabang | ✔ | **`(CBG-001) Cabang Pusat Jakarta`** | **`(…) UAT Cabang B`** |
| Tipe | ✔ | `Kecil` | `Kecil` |
| Alamat | ✔ | `Jl. Gudang Uji No. 1, Jakarta` | `Jl. Gudang Uji No. 2, Surabaya` |
| Telepon | ✔ | `021 87654321` | `031 87654321` |
| **Status (Aktif / Tidak Aktif)** | **✔ (centang)** | **centang** | **centang** |
| Warna Background | – | kosongkan | kosongkan |

**Hubungan Gudang ↔ Cabang:** setiap gudang terhubung ke **tepat satu cabang** (kolom Cabang wajib; tidak ada pilihan "semua cabang"). "Gudang pusat" berarti gudang yang cabangnya `CBG-001`. Di dropdown, cabang tampil sebagai **`(KODE) Nama`** — ketik `CBG-001` atau `Pusat` di kolom pencarian dropdown.
> **Jika dropdown Cabang tidak tampil sama sekali:** kolom itu hanya muncul untuk akun berkelola **"Semua Cabang / Gudang"**; akun lain otomatis memakai cabangnya sendiri. Pakai `uat.admin`/Super Admin.
> **Jika cabang yang Anda cari tidak ada di daftar:** periksa di `/admin/cabangs` bahwa cabang itu ada dan **Status-nya aktif**. Cabang yang baru dibuat tampil dengan nama yang Anda ketik, jadi salah isi Nama (mis. diisi alamat) membuatnya sulit ditemukan.

### A3. Rak — `/admin/raks`
Buat **dua** rak. Klik **Buat**. Urutan kolom di form: Nama, Kode Rak, Gudang. **Namun pilih Gudang (kolom ke-3) lebih dulu, baru klik Generate** pada Kode Rak, karena kode dibuat berdasarkan gudang yang dipilih.

| Kolom (urutan di form) | Wajib | Rak 1 | Rak 2 |
|---|---|---|---|
| Nama | ✔ | `UAT Rak A1` | `UAT Rak B1` |
| Kode Rak | ✔ | Generate (**setelah** Gudang dipilih) | Generate (setelah Gudang dipilih) |
| Gudang | ✔ | `UAT Gudang Utama` | `UAT Gudang Cabang B` |

> Dropdown Gudang hanya memuat gudang **aktif**. Bila gudang tidak muncul, kembali ke A2 dan centang Status.

### A4. Satuan — `/admin/unit-of-measures`
Bila satuan Piece belum ada, klik **Buat**:

| Kolom | Wajib | Isi |
|---|---|---|
| Nama | ✔ | `Piece` |
| Satuan | ✔ | `PCS` |

### A5. Kategori Produk — `/admin/product-categories`
| Kolom | Wajib | Isi | Keterangan |
|---|---|---|---|
| Nama Kategori | ✔ | `UAT Barang Uji` | |
| Kode Kategori | ✔ | klik Generate | harus unik |
| Kenaikan Harga (%) | – | `0` | default 0 |

### A6. Supplier — `/admin/suppliers`
| Kolom | Wajib | Isi | Keterangan |
|---|---|---|---|
| Cabang | ✔ | **Pusat** (`CBG-001`) | |
| Kode Supplier | ✔ | klik Generate | harus unik |
| Nama Perusahaan Supplier | ✔ | `UAT Supplier A` | |
| Nama Contact Person | – | `Bapak Uji` | |
| NPWP | ✔ | `01.234.567.8-901.000` | teks bebas |
| Alamat | ✔ | `Jl. Supplier Uji No. 1, Jakarta` | maksimal 255 karakter |
| Telepon | ✔ | `021 12345678` | aturan format |
| Handphone | ✔ | `081234567890` | aturan format |
| Email | ✔ | `uat.supplier.a@example.com` | harus format email |
| Fax | ✔ | `021 1234567` | **wajib**, aturan format |
| **Tempo Hutang** | ✔ | **`30`** | satuan hari |
| Keterangan | – | kosongkan | |

> Karena Tempo Hutang = 30, saat supplier ini dipilih di PO, **TOP otomatis menjadi "Credit (Tempo Hari)"** dan kolom **Tempo (Hari)** terisi 30. Anda tidak perlu memilih TOP manual.

### A7. Produk — `/admin/products` (buat dua produk)
| Kolom | Wajib | Produk 1 | Produk 2 |
|---|---|---|---|
| Diproduksi (Barang Jadi) | – | biarkan mati | biarkan mati |
| Bahan Baku | – | biarkan mati | biarkan mati |
| SKU | ✔ | Generate | Generate |
| Nama Produk | ✔ | `UAT-BRG-001 Barang Uji A` | `UAT-BRG-002 Barang Uji B` |
| Cabang | ✔ | **Pusat** (`CBG-001`) | **Pusat** (`CBG-001`) |
| Product Category | ✔ | `UAT Barang Uji` | `UAT Barang Uji` |
| Harga Beli Asli (Rp) | ✔ | `10000` | `64800` |
| Harga Jual (Rp) | ✔ | `15000` | `90000` |
| Biaya (Rp) | ✔ | `0` | `0` |
| Harga Batas (%), Item Value (Rp) | – | biarkan `0` | biarkan `0` |
| Tipe Pajak Produk | ✔ | **`Eksklusif`** | **`Eksklusif`** |
| **Pajak (%)** | – | **`11`** | **`11`** |
| Jumlah Kelipatan di Gudang Besar, Jumlah Jual Kategori Banyak | – | biarkan `0` | biarkan `0` |
| Kode Merk | ✔ | `UAT` | `UAT` |
| Satuan | ✔ | `PCS` | `PCS` |
| Description | – | kosongkan | kosongkan |
| Konversi Satuan | – | kosongkan | kosongkan |
| Status Aktif | – | hidup (default) | hidup (default) |
| Akun Perkiraan (Persediaan, Penjualan, Retur Penjualan, Diskon Penjualan) | – | biarkan terisi default | biarkan terisi default |

> **Pajak (%) harus 11.** PPN di PO dan invoice mengikuti kolom ini; bila berbeda, angka jurnal di skrip tidak akan cocok.
> Produk 2 dirancang agar 1 pcs + PPN 11% = **Rp71.928** (sama dengan angka temuan 5).

### A8. Customer — `/admin/customers`
| Kolom | Wajib | Isi | Keterangan |
|---|---|---|---|
| Cabang | ✔ | **Pusat** (`CBG-001`) | |
| Kode Customer | ✔ | terisi otomatis | boleh di-Generate ulang |
| Nama Customer | ✔ | `UAT Customer` | |
| Perusahaan | ✔ | `PT UAT Customer` | |
| NIK / NPWP | ✔ | `01.234.567.8-902.000` | |
| Alamat | ✔ | `Jl. Customer Uji No. 5, Jakarta` | |
| Telepon | ✔ | `021 22334455` | aturan format |
| Handphone | ✔ | `081298765432` | aturan format |
| Email | ✔ | `uat.customer@example.com` | |
| Fax | ✔ | `021 2233445` | |
| Tempo Kredit (Hari) | ✔ | `30` | |
| Kredit Limit (Rp.) | ✔ | `100000000` | |
| Tipe Bayar Customer | ✔ | **`Kredit (Bayar Kredit)`** | pilihan: Bebas / COD (Bayar Lunas) / Kredit |
| Tipe Customer | ✔ | **`PKP`** | pilihan: PKP / PRI |
| Spesial | – | biarkan kosong | |
| Keterangan | – | kosongkan | |

### A9. User — `/admin/users` (buat tiga user)
| Kolom | Wajib | `uat.admin` | `uat.gudangb` | `uat.keuangan` |
|---|---|---|---|---|
| Nama Depan | ✔ | `UAT Admin` | `UAT Gudang B` | `UAT Keuangan` |
| Nama Belakang | – | `Tester` | `Tester` | `Tester` |
| Username | ✔ | `uat.admin` | `uat.gudangb` | `uat.keuangan` |
| Telepon | – | kosongkan | kosongkan | kosongkan |
| Password | ✔ | `UatTest#2026` | `UatTest#2026` | `UatTest#2026` |
| Konfirmasi Password | ✔ | sama | sama | sama |
| Email | ✔ | `uat.admin@example.com` | `uat.gudangb@example.com` | `uat.keuangan@example.com` |
| Level (peran) | ✔ | **`Super Admin`** | **`Warehouse Staff`** | **`Admin Keuangan`** |
| Permissions | – | kosongkan | kosongkan | kosongkan |
| **Kelola** | ✔ | `Semua Cabang / Gudang` | **`Cabang` + `Gudang` (pilih keduanya)** | `Semua Cabang / Gudang` |
| Cabang | – | **kosong, field terkunci** | `UAT Cabang B` (pilih **sebelum** Gudang) | **kosong, field terkunci** |
| Gudang | – | (tidak muncul) | `UAT Gudang Cabang B` (setelah Cabang dipilih) | (tidak muncul) |
| Posisi | ✔ | `Tester UAT` | `Tester UAT` | `Tester UAT` |
| Tanda Tangan | – | kosongkan | kosongkan | kosongkan |
| Status User | – | tercentang (default) | tercentang | tercentang |

`Level` tidak diberi tanda wajib oleh form, tetapi **diperlukan** agar user punya izin.

**Aturan kolom Kelola, Cabang, dan Gudang (sudah diuji di aplikasi):**

| Pilihan Kelola | Kolom Cabang | Kolom Gudang |
|---|---|---|
| `Semua Cabang / Gudang` (dengan atau tanpa yang lain) | **terkunci** (tidak bisa dipilih) | tampil hanya bila "Gudang" ikut dipilih, tetapi **daftarnya kosong** |
| `Cabang` saja | **aktif** | tidak tampil |
| **`Cabang` + `Gudang`** | **aktif** | **tampil**; isinya hanya gudang milik cabang yang dipilih |
| `Gudang` saja | **terkunci** | tampil, tetapi **daftarnya kosong** |

Jadi untuk user gudang, **jangan memilih "Gudang" saja**. Pilih **Cabang dan Gudang sekaligus**, lalu pilih **Cabang lebih dulu**, baru Gudang. Untuk user "Semua Cabang / Gudang", Cabang memang dikosongkan oleh sistem.
**Kenapa peran itu (data peran di sistem, sudah dicek):**

| User | Level | Alasan |
|---|---|---|
| `uat.admin` | **Super Admin** | Punya semua izin, termasuk `delete invoice`, sehingga tombol **Batalkan Invoice** tampil. Dipakai untuk hampir semua skrip. Jangan memakai peran `Admin`: peran itu **tidak** punya izin invoice sama sekali di sistem ini. |
| `uat.gudangb` | **Warehouse Staff** | Punya izin lihat, buat, dan ubah QC (dibutuhkan Poin 8 dan 9). |
| `uat.keuangan` | **Admin Keuangan** | Bisa melihat, membuat, dan mengubah invoice serta memproses pembayaran vendor, **tetapi tidak punya izin `delete invoice`**. Dipakai hanya untuk membuktikan bahwa tombol **Batalkan Invoice tersembunyi** bagi user tanpa izin itu. |

**Maksud "peran tanpa izin `delete invoice`":** tombol **Batalkan Invoice** hanya muncul untuk user yang perannya punya izin `delete invoice`. Di sistem ini izin itu dimiliki **Super Admin, Owner, Finance Manager, dan Sales Manager**. Kita butuh satu user yang **bisa membuka halaman invoice tetapi tidak punya izin itu**, supaya terbukti tombolnya hilang untuknya. `Admin Keuangan` (atau `Accounting`) memenuhi syarat itu.

**Jangan** memakai `Purchasing` atau `Purchasing Manager` untuk `uat.keuangan`: peran itu tidak punya izin melihat invoice, jadi halamannya tidak bisa dibuka dan ujinya tidak bermakna.

**Cara memastikan di lingkungan Anda** (peran di staging bisa berbeda): buka `/admin/roles`, buka peran **Admin Keuangan**, cari izin `delete invoice` (harus **tidak tercentang**) serta `view invoice` dan `view any invoice` (harus **tercentang**). Bila berbeda, pilih peran lain dengan pola yang sama.

### A10. Stok awal — `/admin/stock-adjustments` (menu Gudang → Penyesuaian Stok)
Buat **dua** adjustment (Gudang ada di kepala dokumen, jadi terpisah). **Pilih Gudang dulu**, baru Produk dan Rak (pilihan Rak bergantung pada gudang).

| Kolom | Wajib | Adjustment 1 | Adjustment 2 |
|---|---|---|---|
| Nomor Adjustment | ✔ | terisi otomatis / Generate Baru | sama |
| Tanggal Adjustment | ✔ | hari ini | hari ini |
| Gudang | ✔ | `UAT Gudang Utama` | `UAT Gudang Cabang B` |
| Tipe Adjustment | ✔ | `Penambahan Stock (+)` | `Penambahan Stock (+)` |
| Alasan | ✔ | `Saldo awal UAT` | `Saldo awal UAT` |
| Catatan | – | kosongkan | kosongkan |
| **Item →** Produk | ✔ | `UAT-BRG-001` | `UAT-BRG-001` |
| Rak | ✔ | `UAT Rak A1` | `UAT Rak B1` |
| Qty Saat Ini | otomatis | `0` | `0` |
| **Qty Setelah Adjustment** | ✔ | **`100`** | **`20`** |
| Selisih Qty | otomatis (terkunci) | `100` | `20` |
| Harga Satuan | ✔ | `10000` (otomatis terisi dari harga beli produk; boleh diketik, angka langsung berformat `10.000,00`) | sama |
| Nilai Selisih | otomatis (terkunci) | `1.000.000,00` | `200.000,00` |

Simpan, lalu di daftar klik aksi **Approve** pada masing-masing. **Status harus Approved**; jika masih Draft, stok belum masuk.
Cek di `/admin/inventory-stocks`: `UAT-BRG-001` = **100** di Gudang Utama/Rak A1 dan **20** di Gudang Cabang B/Rak B1.

### A11. Akun bank dan akun jurnal — Akuntansi → Bagan Akun (`/admin/chart-of-accounts`)
**Akun untuk pembayaran Bank Transfer:** dropdown COA di Vendor Payment **bergantung pada Payment Method**. Untuk *Bank Transfer* hanya tampil akun yang **kodenya diawali `11`** dan **namanya memuat "bank" atau "rekening"**.
Di data lokal sudah ada `1100 Bank Utama` dan `1112 Rekening Bank` — pakai salah satunya. Bila di staging tidak ada, klik **Buat**:

| Kolom | Wajib | Isi |
|---|---|---|
| Kode | ✔ | `1101` (harus diawali 11 dan belum dipakai) |
| Name | ✔ | `Bank UAT` (harus memuat kata "Bank") |
| Tipe | ✔ | `Asset` |
| Induk Akun | – | pilih `1110 - KAS DAN SETARA KAS` (opsional) |
| Aktif | ✔ | hidup |
| Saldo Awal / Debit / Kredit | – | biarkan `0` |

**Akun jurnal yang harus ada** (dipakai Poin 3–5; bila tidak ada, jurnal tidak terbentuk): `2110 Hutang Dagang`, `2100.10 Penerimaan Barang Belum Tertagih`, `1170.06 PPN Masukan`, dan akun persediaan (`1140.01`/`1140.10`). Cukup **cek keberadaannya**; jangan mengubah.

### Cek cepat (bila ada yang tidak muncul di dropdown)
| Yang tidak muncul | Penyebab yang paling sering |
|---|---|
| Gudang di dropdown Rak/PO/DO/Adjustment | Status gudang belum dicentang Aktif |
| Rak di form Adjustment | Gudang belum dipilih lebih dulu, atau rak dibuat di gudang lain |
| COA di Vendor Payment | Payment Method belum dipilih, atau nama akun tidak memuat "bank"/"rekening" |
| Cabang/produk di dropdown | Akun yang dipakai tidak punya akses cabang itu (Kelola), atau produk tidak aktif |
| Cabang terkunci / Gudang kosong di form User | Kelola belum memuat "Cabang" (Cabang hanya aktif bila "Cabang" dipilih tanpa "Semua"); daftar Gudang mengikuti Cabang yang dipilih |
| Kolom "Gudang Tujuan" di form PO | Aset frontend belum di-build/deploy; tekan Ctrl+F5 |
| PO di form QC | PO belum berstatus **Approved** |
| GRN/PO di form Invoice | GRN belum terbit (QC belum diproses), atau PO sudah berlabel "[Sudah di-invoice]" |

## B. Skrip uji

Kerjakan berurutan: **8 → 9 → 7 → 3 → 4 → 5 → 6 → 1 → 2**. Masuk sebagai `uat.admin` kecuali disebut lain. Catat nomor dokumen (PO, QC, GRN, invoice, PR, VP) di lembar hasil.

### Poin 8 — Gudang tujuan di kepala PO
1. **Pembelian → Pesanan Pembelian → Buat** (`/admin/purchase-orders/create`). Form ini terdiri dari **kepala dokumen** (bagian atas) lalu **kartu item** (bagian bawah). Isi **dari atas ke bawah** seperti urutan berikut.

   **Kepala dokumen:**
   1. **Referensi Dokumen (Opsional):** pilih **Tanpa Referensi**.
   2. **Nomor PO:** sudah terisi otomatis (mis. `PO-20260922-0001`). Ikon panah melingkar di sampingnya = nomor baru, tidak perlu diklik.
   3. **Supplier:** klik kolom, pilih `(SP-…) UAT Supplier A`. (Bisa juga ketik `UAT` di kotak pencarian.)
   4. **Cabang:** klik kolom, pilih `(CBG-001) Cabang Pusat Jakarta`. Kolom ini **kosong** di awal; daftarnya juga memuat cabang lain, jadi pastikan kodenya `CBG-001`.
   5. **Gudang Tujuan:** **jangan diisi dulu** (untuk menguji pesan error di langkah 2 di bawah).
   6. **Tanggal PO:** biarkan hari ini. **Estimasi Datang:** biarkan, terisi otomatis 7 hari setelah Tanggal PO.
   7. **Terms of Payment (TOP)** dan **Tempo (Hari):** jangan diubah, cukup **perhatikan**. Setelah Supplier dipilih, TOP otomatis **"Credit (Tempo Hari)"** dan Tempo = **30**. (Sebelum supplier dipilih, TOP masih COD dan Tempo 0.)
   8. **Beli Aset** dan **Impor:** jangan dicentang. **Catatan Dokumen:** kosongkan.

   **Item:** gulir ke bawah. **Kartu item pertama sudah otomatis ada** (bertuliskan "Belum memilih produk"), jadi **tidak perlu** klik Tambah Item Pembelian Baru. Isi kartu itu berurutan: **Produk:** klik kolom, ketik `UAT-BRG-001`, pilih hasilnya → **Qty:** ubah dari `1` menjadi `30` → **Mata Uang:** biarkan `IDR (Rp)` → **Harga Satuan:** sudah terisi otomatis `10.000,00` dari harga beli produk (biarkan) → **Diskon (%)** biarkan `0` → **Pajak:** biarkan **Eks** (sudah terpilih). **Diharapkan** di kartu dan ringkasan bawah: Subtotal Rp 300.000,00, PPN Rp 33.000,00, **Grand Total Rp 333.000,00**.
2. Klik **Buat PO** (tombol biru melayang di kanan bawah layar). **Diharapkan:** form tidak tersimpan; halaman tetap di form; gulir ke atas: kolom Gudang Tujuan berbingkai merah dengan pesan **"Gudang tujuan wajib dipilih"** di bawahnya.
3. Di kolom **Gudang Tujuan** ketik `Cabang B`, pilih `(GD-…) UAT Gudang Cabang B (Cabang: UAT Cabang B)`. Bingkai merah dan pesan lama **masih tampil** sampai Anda klik Buat PO lagi; itu wajar. Klik **Buat PO**. **Diharapkan:** pindah ke halaman **Lihat Pesanan Pembelian** dengan status **Draft**, kolom **Gudang Tujuan Penerimaan** = UAT Gudang Cabang B, Cabang = (CBG-001) Cabang Pusat Jakarta, TOP = Kredit 30 hari, Total Qty Dipesan 30. Lalu klik **Setujui PO** → **Ya, Setujui**. Catat nomor PO (**PO-A**). **Diharapkan:** status Approved.
4. **Pembelian → Kontrol Kualitas Pembelian → Buat**. Kolom pertama adalah **Purchase Order**: pilih PO-A. Di daftar, PO tampil sebagai `PO: PO-… | UAT Supplier A | Gudang: UAT Gudang Cabang B | Status QC: Belum ada QC`; PO terbaru berada **paling bawah** daftar (gulir ke bawah di dalam dropdown), dan kotak pencarian belum andal menyaring daftar (lihat Poin 2). **Diharapkan:** kolom di sebelahnya, *Gudang Penerimaan (Terkunci ke PO)*, terisi **UAT Gudang Cabang B** dan tidak bisa diganti; *Cabang Akuntansi (Pusat)* = Pusat. Berhenti di sini (jangan disimpan), pengisian lengkap ada di Poin 9.
5. Login sebagai `uat.gudangb` dan sebagai user gudang lain: cek apakah PO-A hanya bisa di-QC oleh user Gudang B.
   *(Belum pernah diverifikasi — catat hasil apa adanya.)*

> Temuan terbuka yang diketahui: cabang di header PO masih dropdown bebas (belum otomatis Pusat); modal "Create Purchase Order" dari OR belum punya Gudang. Catat sebagai "diketahui belum", bukan hasil baru.

### Poin 9 — QC draft mengunci qty (memakai PO-A: 30 pcs)
1. **QC-1:** Kontrol Kualitas Pembelian → Buat. Isi **dari atas ke bawah**:
   1. **Purchase Order:** PO-A. (QC Number, Gudang Penerimaan, Cabang Akuntansi, dan Petugas QC terisi/terkunci otomatis; jangan diubah.)
   2. **Tanggal Kedatangan Barang:** biarkan hari ini. **Catatan QC / No. Surat Jalan Supplier:** kosongkan.
   3. **Matikan** toggle **"Langsung Terbitkan Penerimaan Barang (GRN)"**. Toggle ini ada di **atas** tabel item dan bawaannya **menyala**.
   4. Tabel **Item Kedatangan** (muncul setelah PO dipilih; **Diterima** dan **Lolos** langsung terisi `30` = sisa PO): ubah **Diterima** menjadi `20`. Kolom **Lolos** ikut berubah menjadi `20` sendiri dan **Reject** menjadi `0`; cukup periksa. (Bila mengetik di Lolos, hapus isi lamanya dulu, kalau tidak angkanya menempel, mis. `2020`.) Lalu **Rak Penyimpanan** (di baris bawah tabel) = `(RAK-…) UAT Rak B1`.
   5. Klik **Buat**. **Diharapkan:** muncul "Data berhasil dibuat" dan halaman Lihat QC berstatus **Belum diproses** (draft); catatan status: "Passed Quantity masih draft sampai QC dijalankan". Kolom **Rack** di header halaman ini tampil kosong; rak tersimpan per item, itu wajar.
2. Buka **Kontrol Kualitas Pembelian → Buat** lagi, pilih PO-A yang sama. **Diharapkan:** tabel item menampilkan **"Sisa yang bisa di-QC 10 pcs (20 pcs sedang di QC-…)"** (30 − 20), dan Diterima/Lolos terisi `10`. Ubah Diterima menjadi `30` (Lolos ikut `30`), klik **Buat** (toggle GRN biarkan menyala, aman karena ditolak sebelum QC dibuat). **Diharapkan:** ditolak dengan pesan jelas di layar (bukan baru gagal saat Complete), dan tidak ada QC/GRN baru.
   > **Hasil uji 22/09/2026:** penolakan bekerja (tidak ada QC/GRN baru), tetapi **pesan tidak tampil sama sekali** di layar. Server mengirim pesan yang benar, tetapi tidak sampai ke pengguna (kunci pesan tanpa awalan `data.` dan tidak ada notifikasi). Catat sebagai **Gagal (pesan tidak terlihat)** sampai diperbaiki.
3. Buka QC-1 → klik **Complete QC** (di panduan lama disebut "Process QC"; di layar labelnya **Complete QC**, dialognya "Selesaikan Quality Control") → **Selesaikan QC**. **Diharapkan:** notifikasi "QC Selesai … GRN telah diterbitkan"; status QC **Sudah diproses**; **GRN** terbit (Penerimaan Pembelian, mis. `GRN-20260922-0001`, status completed); stok UAT-BRG-001 di **UAT Gudang Cabang B / UAT Rak B1 = 20** (cek `/admin/inventory-stocks`); status PO-A menjadi **Partially Received**; jurnal persediaan terbentuk (Dr Persediaan / Cr Penerimaan Barang Belum Tertagih Rp200.000).
   > **Hasil uji 22/09/2026:** GRN, stok, dan status PO sesuai. **Cabang pada jurnal = UAT Cabang B (cabang gudang), bukan Pusat.** Header QC dan GRN sendiri bercabang Pusat. Jika harapan Anda "jurnal selalu Pusat", catat **Gagal**; cek di Akuntansi → Jurnal, kolom Cabang.
4. **QC draft batal saat PO ditutup:** buat PO kecil (Tanpa Referensi, Supplier A, Cabang Pusat, **Gudang Tujuan `UAT Gudang Utama`** (pilih yang berawalan **UAT**; ada juga "Gudang Utama" bawaan berkode GU001), item `UAT-BRG-001` **Qty 5**), **Setujui**. Buat **QC draft** untuk PO itu (toggle GRN **dimatikan**, Diterima `2`, Lolos `2`), simpan. Lalu tutup PO. Tombol **Request Close** **tidak muncul di halaman Lihat PO berstatus Approved**; gunakan **Pembelian → Pesanan Pembelian** (daftar), klik **Action** pada baris PO → **Request Close** → isi **Close Reason** → **Konfirmasi**. PO menjadi **Request Close** (baris merah); QC draft **belum** berubah. Kemudian **Action → Konfirmasi** (perlu izin `response purchase order`) → isi **Close Reason** → **Konfirmasi**. **Diharapkan:** PO berstatus **Closed** dan QC draft **otomatis batal**.
   > **Hasil uji 22/09/2026:** fitur ini **sudah diimplementasi** (bukan "belum"): QC draft otomatis berstatus batal saat PO Closed/Completed/Cancelled. Temuan turunan: (a) QC yang batal di halaman Lihat tetap berlabel **"Sudah diproses"** dengan catatan lama "Belum diproses…", (b) tombol **Ubah, Hapus, dan Complete QC masih tampil** pada QC batal, (c) baris item QC yang batal masih berstatus draft. Jangan klik Complete QC pada QC batal itu.

### Poin 7 — Status Penerimaan hanya label
Prasyarat: sudah mengerjakan Poin 9 langkah 3 (ada GRN dan PO-A yang sudah di-QC sebagian).
1. **Pembelian → Penerimaan Pembelian** (`/admin/purchase-receipts`). Kolom **Status** ada di **sisi kanan** tabel, sesudah "QC Status"; geser tabel ke kanan bila tidak terlihat.
2. **Diharapkan:** kolom Status berupa **label berwarna** (`Completed` hijau, `Partial` kuning, `Draft` abu-abu), **tanpa dropdown/tombol ubah**. Arahkan kursor ke label: muncul penjelasan status. **Klik label** → hanya membuka halaman **Lihat Penerimaan Pembelian** (bukan mengubah status), dan di sana Status tampil sebagai teks biasa. Tidak ada tombol Buat/Ubah/Hapus. Coba alamat `/admin/purchase-receipts/{id}/edit` → **404 Not Found**.
3. **Status berubah otomatis dari proses QC:** buka **PO-A** (`/admin/purchase-orders`, klik PO-nya) → bagian **Ringkasan Quantity → Status Penerimaan** berupa label: **Belum Diterima** sebelum QC-1 diselesaikan (Poin 9 langkah 3), berubah sendiri menjadi **Sebagian Diterima** (20 dari 30) setelahnya. Label yang sama ada di kolom **Status Penerimaan** pada daftar PO (arahkan kursor untuk rincian "diterima/dipesan/sisa"). Nilainya dihitung dari penerimaan yang benar-benar ada, jadi tidak bisa diisi manual.
   > **Hasil uji 22/09/2026: Lulus.** Kolom Status daftar penerimaan berupa badge tanpa kontrol ubah (satu-satunya elemen klik adalah tautan baris ke halaman lihat), rute edit/buat tidak terdaftar (404), dan "Status Penerimaan" PO berupa badge yang berubah otomatis "Belum Diterima" → "Sebagian Diterima".
   > **Temuan kecil (di luar Poin 7):** halaman Lihat GRN yang lahir dari QC multi-item (mis. `GRN-20260922-0001`, catatan "Auto-created from QC: QC-…") menampilkan **"Belum ada QC purchase"** dan "Item tanpa QC: 1", padahal QC-nya ada. Informasi QC di halaman itu belum terhubung untuk QC multi-item. Catat sebagai temuan tambahan.

### Poin 3 — Invoice pembelian tanpa OR
**Aturan nomor (penting sebelum mulai):** **No. Faktur Pajak** dicek **global** (tidak boleh sama dengan invoice mana pun yang belum dibatalkan), sedangkan **No. Invoice Supplier** unik **per supplier**. Bila lingkungan Anda pernah dipakai uji ini sebelumnya, nomor contoh di bawah bisa sudah terpakai dan ditolak; ganti dengan nomor lain yang belum ada (mis. `010.000-26.00000101`). Panduan menyebut nomor "Faktur-B" dan "Faktur-C" untuk dua nomor faktur yang berbeda.

1. **PO-B:** buat PO dengan urutan form yang sama seperti Poin 8: Tanpa Referensi → Supplier A → Cabang Pusat → **Gudang Tujuan UAT Gudang Utama** (pilih yang berawalan **UAT**; ada juga "Gudang Utama" bawaan berkode GU001) → isi kartu item pertama yang sudah ada: `UAT-BRG-001`, **Qty 10** (Harga Satuan otomatis `10.000,00`, Pajak Eks sudah terpilih). Klik Buat PO, lalu **Setujui**. Buat QC (Purchase Order = PO-B; Gudang Penerimaan terkunci Utama; toggle **"Langsung Terbitkan Penerimaan Barang (GRN)"** biarkan **menyala**; Diterima dan Lolos sudah terisi `10`, tidak perlu diubah) → klik **Buat**. **Diharapkan:** **GRN-B** terbit dan muncul notifikasi "Purchase Order Completed … has been automatically completed" (teks notifikasi ini berbahasa Inggris); PO-B berstatus **Completed**.
2. **Invoice Pembelian → Buat** (`/admin/purchase-invoices/create`). Isi **dari atas ke bawah**:
   - **Bagian "Sumber Invoice":** **Supplier:** klik kolom, ketik `UAT` (kata kunci pendek lebih andal dari `UAT Supplier`), pilih `(SP-…) UAT Supplier A` → kolom **Order Request (OR) — Opsional: kosongkan** → **Cabang** masih kosong sampai PO dipilih → di **Purchase Orders** (muncul setelah supplier dipilih) centang **PO-B**; semua PO berlabel **[Direct PO]**. **Diharapkan** setelah dicentang: **Cabang** terisi `(CBG-001) Cabang Pusat Jakarta` dan **Mata Uang / Rate** "Mata uang transaksi: IDR". PO yang sudah ditutup/di-invoice tidak bisa dipilih.
   - **Bagian data invoice:** **Invoice Number:** klik ikon Generate (mis. `PINV-20260922-0001`) → **No. Invoice Supplier:** `SUP-INV-UAT-001` → **No. Faktur Pajak:** **Faktur-B** `010.000-26.00000001` → **Invoice Date:** biarkan hari ini → **Due Date:** terisi otomatis 30 hari setelah Invoice Date.
   - **Bagian "Silahkan Pilih Purchase Receipt"** (**di bawah** data invoice, bukan di atas): centang **GRN-B**, tertulis `[PO-…] GRN-… — Total tagihan: Rp 111.000,00`. **Diharapkan:** tabel **Item Invoice** terisi `UAT-BRG-001, Qty 10, Harga Satuan Faktur 10000 (Harga PO 10.000,00)`, DPP Baris **Rp 100.000,00**, PPN **Rp 11.000,00**, Total **Rp 111.000,00**; jangan diubah.
   - Gulir ke bawah: **Ringkasan Nilai Invoice** (DPP Rp 100.000,00; PPN Rate 11% terkunci; Nilai PPN Rp 11.000,00; Biaya Lain Rp 0,00), **Grand Total Rp 111.000,00**, dan **Status Invoice** yang hanya menampilkan "Draft" (terkunci). Bagian **Pemilihan COA** tertutup dan sudah terisi bawaan; biarkan.
   - Klik **Buat**. **Diharapkan:** pindah ke halaman **Lihat Invoice Pembelian**; tersimpan sebagai **Draft**; total **Rp111.000** (100.000 + PPN 11.000); **belum ada** jurnal maupun Utang Usaha untuk invoice ini.
3. **Nomor ganda:** buat **PO-C/GRN-C** dengan langkah yang sama seperti langkah 1 tetapi **Qty 5** (total tagihan GRN-C **Rp 55.500,00**). Di Invoice → Buat isi seperti langkah 2 (Supplier A → centang PO-C → Generate Invoice Number → **No. Invoice Supplier `SUP-INV-UAT-001`** (sama dengan invoice B) → No. Faktur Pajak baru **Faktur-C** `010.000-26.00000003`), lalu centang **GRN-C** di bagian Purchase Receipt. Klik **Buat**. **Diharapkan:** muncul notifikasi merah **"Invoice pembelian belum dapat disimpan"** berisi pesan, **dan** pesan **"Nomor invoice supplier 'SUP-INV-UAT-001' sudah pernah digunakan untuk supplier ini."** di bawah kolom No. Invoice Supplier; invoice tidak tersimpan. (Notifikasi hilang dalam beberapa detik; klik Buat lagi bila terlewat.) Ulangi dengan **No. Faktur Pajak = Faktur-B** dan **No. Invoice Supplier diganti** `SUP-INV-UAT-002`; **diharapkan** ditolak dengan notifikasi yang sama dan pesan **"Nomor faktur pajak '…' sudah pernah digunakan pada invoice lain."** di bawah kolom No. Faktur Pajak.
4. Setelah itu isi nomor yang benar (**No. Invoice Supplier** `SUP-INV-UAT-002`, **No. Faktur Pajak** Faktur-C) → tersimpan sebagai **Draft (Invoice-C)**, total **Rp55.500**.

> **Hasil uji 22/09/2026: Lulus** untuk semua langkah (memakai nomor yang belum terpakai: Invoice-B `SUP-INV-UAT-001` / faktur `…00000101`, Invoice-C `SUP-INV-UAT-102` / faktur `…00000102`). Kedua penolakan nomor ganda menampilkan notifikasi dan pesan di bawah kolom. PO yang sudah tercakup invoice berlabel **"[Sudah di-invoice]"** dan tidak bisa dicentang lagi.
> Diketahui belum: halaman **Edit** invoice draft belum mengecek nomor ganda.

### Poin 4 — Draft tidak menjurnal; status oleh sistem; pembatalan
1. Buka **Invoice-B** (draft dari Poin 3). Klik **Lihat Journal Entries**. **Diharapkan:** tidak ada jurnal untuk invoice ini. Buka Akuntansi → **Utang Usaha**: tidak ada baris untuk invoice ini.
   > **Catatan penting:** tombol **Lihat Journal Entries** membuka daftar Journal Entries dengan filter "Source Type: Invoice" saja — **filter Source ID tidak ikut terpasang** (bug terpisah, lihat di bawah), jadi daftar yang muncul berisi jurnal **semua** invoice, bukan invoice ini saja. Untuk memeriksa jurnal invoice ini secara spesifik, **ketik nomor invoice-nya sendiri** di kotak pencarian (mis. `PINV-20260922-0001`); **diharapkan** hasilnya "Tidak ada data yang ditemukan".
   > **Bug ditemukan (di luar Poin 4):** kotak pencarian di halaman **Utang Usaha** (`/admin/account-payables`) menyebabkan **error 500** ("Column 'total' in EXISTS subquery is ambiguous") karena kolom `total` ada di dua tabel yang digabung. Jangan gunakan kotak pencarian di halaman itu untuk sementara; buka halamannya tanpa pencarian (daftar tetap tampil normal) untuk memastikan tidak ada baris invoice ini.
2. Di form buat invoice, gulir ke bagian **Status Invoice** (di bawah Grand Total): kolom **Status hanya tampilan terkunci "Draft"** dan tidak ada pilihan Lunas. Berlaku untuk semua peran, termasuk Super Admin; boleh diulang dengan `uat.keuangan`.
3. Buka Permintaan Pembayaran → Buat, pilih Supplier A: **invoice draft tidak boleh muncul** di bagian "Pilih Invoice yang akan Dibayar" (kosong, karena supplier ini baru punya invoice draft).
4. Di Invoice-B klik **Posting Invoice** → **Ya, Posting Invoice**. **Diharapkan:** notifikasi "Invoice Berhasil Diposting"; status **Terkirim**; tombol Ubah dan Hapus hilang (tersisa Lihat Journal Entries, Batalkan Invoice, Preview Invoice); jurnal:
   - Dr *Penerimaan Barang Belum Tertagih* **100.000**
   - Dr *PPN Masukan* **11.000**
   - Cr *Hutang Dagang* **111.000**
   dan baris **Hutang Usaha** baru berstatus "Belum Lunas", sisa Rp111.000. Coba buka `…/admin/purchase-invoices/{id}/edit` → **403 Forbidden**.
5. **Pembatalan:** posting **Invoice-C**, lalu klik **Batalkan Invoice**. **Tanggal Pembatalan (Jurnal Balik)** sudah terisi hari ini secara otomatis; isi **Alasan Pembatalan** `Nomor faktur salah` → **Ya, Batalkan Invoice**. **Diharapkan:** notifikasi "Invoice Dibatalkan"; status **Dibatalkan**; halaman menampilkan **"Dibatalkan Pada / Oleh / Alasan"**; ada 3 baris jurnal balik (Cr Hutang Dagang, Dr Penerimaan Barang Belum Tertagih, Dr PPN Masukan — kebalikan dari langkah 4); baris Utang Usaha invoice itu terhapus (soft-delete, tidak lagi tampil di daftar); **GRN-C bisa ditagihkan lagi**: buat invoice baru untuk PO-C, GRN-C (`GRN-…-0003`) muncul kembali di "Silahkan Pilih Purchase Receipt" **tanpa** label "[Sudah di-invoice]" dan bisa disimpan.
   Login `uat.keuangan`: tombol **Batalkan Invoice** **tidak boleh muncul**. *(Terkonfirmasi dari data peran: Admin Keuangan tidak memiliki izin `delete invoice`, yang dipakai tombol ini; belum diverifikasi login langsung.)*
6. Coba **Batalkan Invoice** pada invoice yang **sudah dibayar** (Invoice-B lama dari sesi sebelumnya, atau invoice mana pun berstatus Lunas/Dibayar Sebagian). **Diharapkan:** ditolak dengan notifikasi **"Invoice Belum Dapat Dibatalkan"** berisi pesan **"Invoice sudah memiliki pembayaran vendor sebesar Rp …. Koreksi pembayaran tersebut lebih dahulu sebelum membatalkan invoice."**; status invoice tidak berubah.
7. **Terlambat:** buat invoice baru dari PO/GRN yang belum ditagih, dengan **Invoice Date 60 hari lalu**. Ubah **Invoice Date lebih dulu**; karena TOP supplier Kredit 30 hari, **Due Date otomatis terisi 30 hari lalu** — cocok dengan skenario ini tanpa perlu diubah manual (Due Date ikut terisi ulang otomatis setiap Invoice Date berubah, jadi tetap ubah Invoice Date dulu). Lalu posting. Status berubah menjadi **Terlambat** setelah job harian (00:05) berjalan. Minta IT menjalankan `php artisan invoices:check-overdue`, atau cek keesokan harinya bila cron server aktif.

> **Hasil uji 22/09/2026: Lulus** untuk semua langkah (1–7), termasuk memverifikasi langsung di database: jurnal posting dan jurnal balik pembatalan sesuai; baris Utang Usaha soft-delete saat dibatalkan; command `invoices:check-overdue` memindahkan invoice ber-due-date lewat ke status "Terlambat" (`Total diubah ke Overdue : 1`) dan menampilkan label merah "Terlambat" di halaman Lihat.
> **Temuan aplikasi (di luar Poin 4) — sudah diperbaiki 22/09/2026, sesudah ditemukan di sesi ini:**
> 1. Tombol **Lihat Journal Entries** pada invoice tidak memfilter Source ID dengan benar (parameter URL `tableFilters[source_id][value]` seharusnya `tableFilters[source_id][source_id]`), sehingga menampilkan jurnal semua invoice, bukan invoice yang dibuka. Bug yang sama ada di 11 resource lain (Vendor Payment, Sales Invoice, Deposit, Asset, Customer Receipt, Credit Note, Delivery Order, Cash/Bank Transaction, QC Pembelian) dan sudah diperbaiki sekaligus.
> 2. Pencarian di halaman **Utang Usaha** (`/admin/account-payables`) menyebabkan error 500. Dua penyebab terpisah: kolom `total` ambigu antara tabel `account_payables` dan `invoices` (kata kunci angka), dan kolom `po_number` yang dicari lewat relasi polimorfik `fromModel` ikut mencari ke tabel `sale_orders` yang tidak punya kolom itu (kata kunci nomor PO). Keduanya sudah diperbaiki dan diverifikasi ulang lewat pencarian nomor invoice, angka total, dan nomor PO.
> Tes regresi permanen: [AccountPayableSearchAndJournalFilterTest.php](../tests/Feature/AccountPayableSearchAndJournalFilterTest.php).

### Poin 5 — Permintaan Pembayaran
Buat **PO-D** (Supplier A, gudang Utama, **UAT-BRG-002 qty 1 × 64800**, Pajak Eks) → setujui → QC + GRN → invoice → **posting**. Total invoice **Rp71.928**.
1. **Permintaan Pembayaran → Buat** (`/admin/payment-requests/create`). Bagian **Informasi Dasar**, dari atas ke bawah: **Nomor PR** (sudah terisi otomatis; klik Generate hanya bila kosong) → **Vendor / Supplier** = `UAT Supplier A` → **Tanggal Request** biarkan hari ini → **Tanggal Pembayaran yang Diminta** = besok → **Cabang** = `(CBG-001) Cabang Pusat Jakarta` (kolom wajib; hanya terisi otomatis bila akun Anda terikat ke satu cabang) → **Total Pembayaran (Rp)** terkunci, masih 0.
2. Bagian **Pilih Invoice yang akan Dibayar** (muncul setelah supplier dipilih): **Diharapkan:** invoice PO-D tampil dengan **"Sisa Hutang: Rp 71.928,00"**. **Centang** invoice itu; **Total Pembayaran** di atas otomatis menjadi Rp71.928 dan tidak bisa diubah. Kolom **Catatan Permintaan** kosongkan. Klik **Buat** → **Ajukan Persetujuan** (dialog konfirmasi → **Konfirmasi**) → **Setujui** (dialog konfirmasi → **Konfirmasi**; Catatan Persetujuan opsional). Ini **PR-1**.
3. Buat PR baru untuk supplier yang sama. **Diharapkan:** invoice tadi **tidak muncul lagi** (invoice lain milik supplier yang sama tetap tampil).
4. Di halaman PR-1 klik **Buat Vendor Payment**. Bagian atas form (**Referensi Payment Request**, Nomor Pembayaran, Cabang Operasional, Payment Date, Vendor, dan **Pilih Invoice**) sudah terisi dari PR-1; jangan diubah. Lalu isi **dari atas ke bawah**:
   1. **Detail Pembayaran per Invoice → Jumlah Pembayaran** (tertulis "Jumlah Pembayaran Source"): sudah terisi otomatis penuh (`71.928,00`); ubah menjadi `50000`.
   2. **Catatan:** kosongkan. **Payment Method:** pilih `Bank Transfer`.
   3. **Informasi Bank & Bukti Transfer:** **Rekening Bank Tujuan (Vendor)** `BCA 1234567890 a.n. UAT Supplier A` → **No. Referensi Transfer / Bukti Bank** `TRF-UAT-001` → **Unggah Bukti Transfer** kosongkan.
   4. **NTPN:** kosongkan. **COA** (kolom terakhir, **di bawah** bagian bank): terisi otomatis akun bank begitu Payment Method dipilih (mis. `1100 Bank Utama`); biarkan. Bagian **Pajak Impor** jangan diubah.
   5. Klik **Buat**. **Diharapkan:** VP status **Partial**; invoice **Dibayar Sebagian**; PR-1 **Dibayar Sebagian**; sisa hutang Rp21.928.
5. Buat Vendor Payment kedua dari PR-1: **Jumlah Pembayaran** sudah terisi otomatis sisa **`21.928,00`**, tidak perlu diubah; isi Payment Method/Bank seperti langkah 4. **Diharapkan:** VP status **Paid**; invoice **Lunas**; PR-1 **otomatis tertutup (status "Dibayar")**, Sisa Bayar Rp0,00.

> **Hasil uji 22/09/2026: Lulus** untuk semua langkah, diverifikasi lewat browser dan database (AP `paid=50000/remaining=21928` setelah VP pertama, lalu `paid=71928/remaining=0/status=Lunas` setelah VP kedua; invoice `partially_paid` → `paid`; PR `partial` → `paid`).
> **Bug ditemukan dan diperbaiki di tengah pengujian (Langkah 4):** membuat Vendor Payment dari halaman Payment Request (tombol **Buat Vendor Payment**) gagal dengan **error 500** ("Column 'ppn_import_amount' cannot be null"). Penyebab: `mount()` pada halaman ini memanggil `$this->form->fill()` **dua kali** — panggilan kedua (mengisi data dari PR) tidak lagi memakai nilai default komponen (`Toggle`/`TextInput`/`Hidden` `->default(...)`) untuk field yang tidak disebutkan, sehingga `ppn_import_amount`, `pph22_amount`, `bea_masuk_amount`, `payment_adjustment`, `diskon` jadi `null` padahal kolomnya **NOT NULL** di database. Form "Buat Pembayaran Vendor" biasa (tanpa datang dari PR) tidak kena bug ini karena hanya melalui satu kali `fill()`. Sudah diperbaiki dengan menyertakan default field-field itu di `fill()` kedua. Tes regresi permanen: [VendorPaymentFromPaymentRequestTest.php](../tests/Feature/VendorPaymentFromPaymentRequestTest.php).

### Poin 6 — Penguncian dokumen
Untuk tiap dokumen di bawah, lihat tombol di **daftar** dan di **halaman lihat**, lalu coba buka alamat `/edit`-nya langsung. **Diharapkan:** tidak ada Ubah/Hapus/Set Draft dan alamat edit menjawab **403 Forbidden**.
- OR yang sudah jadi PO — `/admin/order-requests`
- SO Approved (SO dari Poin 1) — `/admin/sale-orders`
- PO Completed (PO-B) — `/admin/purchase-orders`
- Vendor Payment yang tercatat — `/admin/vendor-payments`
- Invoice yang sudah diposting

Sebagai pembanding, dokumen **Draft** masih harus punya Ubah dan Hapus. Aksi yang tersedia pada dokumen terproses hanya Lihat, PDF, **Request Close**.
> Diketahui belum: alur **Revisi (approval ulang)** belum ada; **Hitung Ulang Total** masih muncul di PO Approved. Catat keduanya.

### Poin 1 — DO untuk SO baru
1. **Penjualan → Pesanan Penjualan → Buat**. Isi **dari atas ke bawah**:
   1. Pilihan di pojok atas form: klik **SO Mandiri (None)** (bukan Refer Quotation).
   2. **Nomor SO:** sudah terisi otomatis.
   3. **Customer:** `UAT Customer` → **Cabang:** `(CBG-001) Cabang Pusat Jakarta`. (Customer dipilih **sebelum** Cabang.) Kartu info kredit/deposit yang muncul di bawahnya hanya informasi.
   4. Baris berikutnya: **Tanggal Order** biarkan → **Tanggal Kirim** biarkan/kosong → **Tipe Pengiriman** = **Kirim Ke Customer** → **Mata Uang** biarkan default → **Tempo (Hari)** biarkan → **Alamat Pengiriman (Shipped To)** = `Jl. Customer Uji No. 5, Jakarta` → **Catatan** kosongkan.
   5. Gulir ke bawah. Kartu item pertama **sudah otomatis ada**, jadi tidak perlu klik Tambah Item Pesanan Baru. Pada kartu itu: **Produk** `UAT-BRG-001` → **Qty** `10` → **Harga Satuan** `15000` → Diskon `0` → Tipe Pajak biarkan default.
   6. Klik **Buat Sales Order**. Di halaman SO: **Request Approve** → **Approve**. **Diharapkan:** status Disetujui.
2. **Pengiriman → Perintah Pengiriman → Buat** (`/admin/delivery-orders/create`): klik Generate untuk Nomor DO; **From Sales** = SO tadi; Cabang terisi otomatis; **Tanggal Pengiriman** = besok.
3. Buka item DO → **Sumber Gudang (Multi-Gudang)**. **Diharapkan:** Gudang Sumber **terisi otomatis dan bisa diganti**; pilihan menampilkan stok (mis. "UAT Gudang Utama - Stok: 100"); ada tombol tambah sumber gudang.
4. **Bagi qty:** sumber 1 = UAT Gudang Utama / Rak A1 qty **6**; **Tambahkan** sumber 2 = UAT Gudang Cabang B / Rak B1 qty **4**. **Buat**. **Diharapkan:** DO terbentuk berstatus "Menunggu Konfirmasi Stok".
5. **Uji error:** DO baru, pilih gudang yang stoknya 0. **Diharapkan:** pesan berbahasa Indonesia (bukan `validation.required`).
> Catatan: daftar gudang masih memuat semua cabang, bukan hanya cabang SO. Bila stok ada di Rak, pilih Rak-nya — tanpa Rak simpan bisa gagal walau label menunjukkan stok.

### Poin 2 — Pencarian dropdown & batas 50
Butuh **lebih dari 50 supplier dan PO**. Cek dulu jumlahnya di `/admin/suppliers` ("Menampilkan … dari N hasil"). Jika belum >50, minta IT mengisi data uji (lewat seeder/import) — membuat 55 supplier manual tidak praktis.
Ketik nama/nomor **entri ke-55** di dropdown berikut; **diharapkan** entri itu muncul dan data terbaru berada di atas:
- Permintaan Pembayaran → Vendor/Supplier
- Vendor Payment → Payment Request dan Vendor
- Kontrol Kualitas Pembelian → Purchase Order
- Invoice Pembelian → Supplier
- Form PO → Supplier
> Perkiraan: dropdown Filament (PR, Vendor Payment, QC) **masih gagal** — pencarian hanya menyaring 50 data yang dimuat dan urutan QC terlama di atas. Dropdown Supplier di Invoice Pembelian memakai pencarian server. Catat sebagai temuan terbuka.

## C. Lembar hasil
| No | Poin | Langkah | Hasil diharapkan | Hasil nyata | Lulus / Gagal | Nomor dokumen | Bukti (screenshot) |
|---|---|---|---|---|---|---|---|

Aturan penilaian: **Lulus** bila hasil nyata sama dengan yang diharapkan; **Gagal** bila berbeda; **Diketahui belum** untuk butir yang ditandai catatan di atas.
