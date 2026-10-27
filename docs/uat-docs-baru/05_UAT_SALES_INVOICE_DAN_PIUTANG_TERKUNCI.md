# MODUL UAT 05: INVOICE PENJUALAN (SALES INVOICE) & PENGUNCIAN PIUTANG
## PENGUJIAN PENAGIHAN, BIAYA LAIN & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian 7; `List_Pengerjaan_Developer.md` Bagian B (Poin 24–26); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 6.6.
**Peran Penguji Terautentikasi:**
- `Admin Keuangan` (`admin_keuangan@example.com`)
- `Finance Manager` (`finance_manager@example.com`)
- `Kasir` (`kasir@example.com`)

---

### Kasus Uji UAT-INV-01: Alur Pembuatan Faktur Tanpa Input Barang Ulang (Customer $\rightarrow$ SO $\rightarrow$ Centang SJ)
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Memverifikasi prinsip bisnis Ko Handy: *"Urutan input: pilih Customer > SO > centang SJ (kolektif, dalam SO yang sama). Tidak ada input produk manual. Bisa menambah ongkos kirim / biaya lain. Invoice TIDAK terbit otomatis dari DO."*
- **Kategori Skenario**: Usability & Financial Accounting Workflow.
- **Testing yang Dilakukan**:
  1. Login sebagai `admin_keuangan@example.com`.
  2. Buka menu **PENJUALAN $\rightarrow$ Invoice Penjualan**, klik tombol **"Buat Invoice Penjualan"**.
  3. Pilih Customer `CUST-TEST-UAT-001`.
  4. Pada dropdown Sales Order, pilih `SO-TEST-MULTI-WH`.
  5. Sistem menampilkan daftar checklist Surat Jalan terbit untuk SO tersebut:
     - [x] `SJ-20261026-0003` (gabungan DO-01 dan DO-02).
  6. Centang Surat Jalan tersebut.
  7. Amati item barang dan subtotal yang muncul otomatis.
  8. Tambahkan pada bagian **Biaya Lain-lain / Ongkos Kirim**: `Rp 15.000` (Catatan: *Biaya packing kayu peti*).
  9. Klik tombol **"Simpan & Posting Invoice"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Seluruh item barang, harga satuan, dan diskon terisi otomatis dari Surat Jalan dan Sales Order.
  - Subtotal terhitung tepat. Tambahan biaya packing kayu Rp 15.000 masuk ke total tagihan dan DPP/PPN terhitung akurat.
  - Invoice tidak terbit otomatis saat DO selesai, melainkan terkendali penuh oleh bagian keuangan.
- **Solusi**:
  Sesuai dengan Alur Ko Handy §7 dan List Developer Poin 24 & 25.
- **Bukti Pengujian Nyata**:
  - Formulir: [`SalesInvoiceResource.php:222-260`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SalesInvoiceResource.php#L222-L260).
  - Database: `invoices.total` memuat nilai barang + biaya packing.

---

### Kasus Uji UAT-INV-02 (No Happy Path): Penguncian Mutlak Field Invoice Setelah Diposting (Non-Draft)
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`) & `Finance Manager` (`finance_manager@example.com`)
- **Tujuan Pengujian**: Memastikan aturan List Developer Poin 26: *"Invoice yang sudah posting DIKUNCI (tidak boleh lagi bisa diedit kuantitas, harga, tipe pajak, tarif PPN, maupun nama customernya)"*. Koreksi finansial wajib lewat pembatalan/Credit Note.
- **Kategori Skenario**: **No Happy Path (Posted Document Immutability)**.
- **Testing yang Dilakukan**:
  1. Buka faktur penjualan yang berstatus `posted`, `unpaid`, atau `paid`.
  2. Klik tombol **"Ubah" (Edit)** pada faktur tersebut.
  3. Coba ubah kuantitas salah satu barang dari 10 pcs menjadi 8 pcs.
  4. Coba ubah customer atau tarif PPN.
  5. Coba lakukan inspeksi elemen browser untuk menghapus atribut `disabled` dan mengirim request PUT manual.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Pada UI: Seluruh input kontrol (customer, cabang, item barang, kuantitas, harga, tarif PPN) tampil berlatar abu-abu (*read-only/disabled*) dengan kursor tidak diizinkan.
  - Pada Backend: Method `isInvoiceFieldLocked()` mengembalikan nilai `true`. Jika ada payload manipulasi yang lolos, controller/observer menolak perubahan data.
- **Solusi**:
  Implementasi penguncian di [`SalesInvoiceResource.php:156-163`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SalesInvoiceResource.php#L156-L163) melindungi integritas buku besar akuntansi.
- **Bukti Pengujian Nyata**:
  - Atribut HTML: `disabled="disabled"`, class CSS `bg-gray-100 cursor-not-allowed`.
  - Database: Nilai di tabel `invoices` dan `invoice_items` tidak berubah sama sekali.

---

### Kasus Uji UAT-INV-03 (No Happy Path): Pemblokiran Penagihan Ganda Surat Jalan yang Sama
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Mencegah kelalaian penagihan di mana 1 Surat Jalan ditagihkan pada 2 invoice yang berbeda.
- **Kategori Skenario**: **No Happy Path (Double Invoicing Prevention)**.
- **Testing yang Dilakukan**:
  1. Surat Jalan `SJ-20261026-0003` telah difakturkan pada `INV-20261026-0010`.
  2. Buka form Buat Invoice Penjualan baru untuk customer yang sama.
  3. Pilih Sales Order yang sama.
  4. Periksa apakah `SJ-20261026-0003` muncul di daftar checklist Surat Jalan.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Surat Jalan yang sudah memiliki faktur non-batal secara otomatis disaring keluar dari opsi checklist. Jika dicoba di-submit paksa, sistem memvalidasi bahwa Surat Jalan tersebut telah difakturkan.
- **Solusi**:
  Query penyaringan menyaring: `whereDoesntHave('invoiceItem')`.
- **Bukti Pengujian Nyata**:
  - Form UI: Checklist hanya menampilkan Surat Jalan yang berstatus belum tertagih (*unbilled*).

---

### Kasus Uji UAT-INV-04: Pengisian dan Validasi Nomor Faktur Pajak 16 Digit
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Memverifikasi fitur modal aksi *"Isi No. Faktur Pajak"* yang tervalidasi 16 digit sesuai standar Ditjen Pajak tanpa mengganggu jurnal akuntansi.
- **Kategori Skenario**: Tax Compliance & Modal Action Verification.
- **Testing yang Dilakukan**:
  1. Buka detail invoice penjualan berstatus `posted`.
  2. Klik tombol aksi **"Isi No. Faktur Pajak"**.
  3. Masukkan nomor pajak tidak valid (kurang digit): `"010.000-26.123"` $\rightarrow$ Klik Simpan.
  4. Amati respon validasi.
  5. Masukkan nomor pajak valid (16 digit): `"010.000-26.12345678"` $\rightarrow$ Klik Simpan.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Saat format salah: Ditolak oleh rule `TaxInvoiceNumber` dengan notifikasi harus 16 digit.
  - Saat format benar: Nomor faktur pajak tersimpan rapi di kolom `tax_invoice_number`, dan notifikasi muncul: *"No. Faktur Pajak disimpan. Jurnal dan piutang tidak berubah."*
- **Solusi**:
  Sesuai implementasi [`SalesInvoiceResource.php:88-126`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SalesInvoiceResource.php#L88-L126).
- **Bukti Pengujian Nyata**:
  - Database: `invoices.tax_invoice_number = '010.000-26.12345678'`, jurnal debet piutang dan kredit penjualan tetap utuh.

---

### Kasus Uji UAT-INV-05: Verifikasi Jurnal Otomatis Penjualan & Kartu Piutang Customer
- **Peran Penguji Terautentikasi**: `Finance Manager` (`finance_manager@example.com`)
- **Tujuan Pengujian**: Memastikan posting invoice menghasilkan jurnal akuntansi berpasangan yang seimbang dan kartu piutang customer bertambah sesuai nilai tagihan.
- **Kategori Skenario**: Accounting Ledger & Balance Verification.
- **Testing yang Dilakukan**:
  1. Buka invoice yang baru saja diposting (Total: Rp 1.110.000, DPP: Rp 1.000.000, PPN 11%: Rp 110.000).
  2. Buka menu **KEUANGAN $\rightarrow$ Jurnal Umum** dan cari nomor referensi invoice tersebut.
  3. Buka menu **KEUANGAN $\rightarrow$ Piutang Customer**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Jurnal Akuntansi Seimbang:
    - Debet: `1130-01 Piutang Usaha` = Rp 1.110.000
    - Kredit: `4110-01 Penjualan Barang` = Rp 1.000.000
    - Kredit: `2150-01 PPN Keluaran` = Rp 110.000
    - Total Debet = Total Kredit = Rp 1.110.000 (Selisih = 0).
  - Kartu piutang customer bertambah Rp 1.110.000.
- **Solusi**:
  Layanan posting jurnal akuntansi beroperasi dengan integritas tinggi.
- **Bukti Pengujian Nyata**:
  - Database: Tabel `journal_entries` dan `journal_entry_lines` seimbang (*balanced*).
