# MODUL UAT 10: KASIR POS, SALDO TITIP CUSTOMER, KAS/BANK & JURNAL OTOMATIS
## PENGUJIAN TRANSAKSI RITEL, TITIPAN SALDO & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `Alur_Bisnis_Ko_Handy.md` Bagian 2 & Keuangan; `List_Pengerjaan_Developer.md` Bagian C (Poin C5); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian POS.
**Peran Penguji Terautentikasi:**
- `Kasir` (`kasir@example.com`)
- `Admin Keuangan` (`admin_keuangan@example.com`)
- `Finance Manager` (`finance_manager@example.com`)

---

### Kasus Uji UAT-POS-01 (No Happy Path): Validasi Harga Batas (Harga Minimum) pada Transaksi Kasir POS
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Memastikan kasir ritel tidak dapat menjual barang di bawah `harga_batas` (HPP / batas bawah produk) pada menu Kasir Cepat POS.
- **Kategori Skenario**: **No Happy Path (POS Retail Floor Price Violation)**.
- **Testing yang Dilakukan**:
  1. Login sebagai `kasir@example.com`.
  2. Buka menu **Kasir Cepat POS**.
  3. Masukkan produk `PROD-TEST-B` (Batas Minimum: Rp 3.600).
  4. Ubah harga jual di baris POS menjadi `Rp 3.000` (di bawah batas minimum Rp 3.600).
  5. Klik tombol **"Bayar & Cetak Struk"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Transaksi diblokir seketika oleh [`PosOrderService.php:183`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/PosOrderService.php#L183) dengan pesan:
  *"Harga jual PROD-TEST-B (Rp 3.000/unit) di bawah batas minimum (Rp 3.600). Berikan harga minimal sesuai batas harga_batas/HPP."*
- **Solusi**:
  Sistem POS memiliki proteksi harga yang sama kuatnya dengan modul Sales Order.
- **Bukti Pengujian Nyata**:
  - Respon POS: Transaksi ditolak, struk tidak tercetak, dan stok tidak berkurang.

---

### Kasus Uji UAT-POS-02: Filter COA pada Menu Saldo Titip Customer (Penyelesaian Bug C5)
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`) & `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Memverifikasi perbaikan bug C5 dari Ko Handy: *"COA di Saldo Titip Customer dulu menampilkan semua 51 akun (termasuk biaya dan modal), seharusnya HANYA menampilkan akun kas dan bank"*.
- **Kategori Skenario**: Accounting Usability & Account Scoping.
- **Testing yang Dilakukan**:
  1. Buka menu **KEUANGAN $\rightarrow$ Saldo Titip Customer**, klik **"Tambah Saldo Titip"**.
  2. Pilih Customer `CUST-TEST-UAT-002`.
  3. Buka dropdown **Akun Kas / Bank Penerima** (`cash_bank_account_id` / COA).
  4. Amati daftar akun yang muncul di dalam dropdown.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Dropdown hanya menampilkan akun-akun kategori Kas dan Bank (misalnya: *1110-01 Kas Toko, 1120-01 Bank BCA, 1120-02 Bank Mandiri*). Akun beban operasional, modal, dan persediaan tidak lagi muncul.
- **Solusi**:
  Query pilihan di-filter secara ketat: `whereIn('type', ['Kas', 'Bank'])` atau `where('is_cash_bank', true)`.
- **Bukti Pengujian Nyata**:
  - Tampilan Dropdown: Tepat menyajikan akun likuid kas/bank, mencegah salah posting akun akuntansi.

---

### Kasus Uji UAT-POS-03 (No Happy Path): Pembayaran Kurang pada Checkout POS (Underpayment Block)
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Mencegah kasir menyelesaikan transaksi POS jika uang tunai yang diterima kurang dari total belanjaan.
- **Kategori Skenario**: **No Happy Path (Underpayment Validation)**.
- **Testing yang Dilakukan**:
  1. Keranjang belanja POS total belanja: **Rp 75.000**.
  2. Kasir memilih metode pembayaran: `Tunai`.
  3. Pada input Uang Diterima, kasir memasukkan: `Rp 50.000` (kurang Rp 25.000).
  4. Klik tombol **"Selesai Bayar"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Tombol bayar dinonaktifkan atau saat diklik memunculkan notifikasi:
  *"Uang pembayaran (Rp 50.000) kurang dari total belanja (Rp 75.000). Kekurangan: Rp 25.000."*
- **Solusi**:
  Kalkulasi kembalian mencegah transaksi minus.
- **Bukti Pengujian Nyata**:
  - Respon UI: Nilai kembalian berwarna merah dengan keterangan kekurangan nominal.

---

### Kasus Uji UAT-POS-04: Integritas Jurnal Akuntansi Kasir POS (Debit Kas, Kredit Penjualan & Persediaan)
- **Peran Penguji Terautentikasi**: `Finance Manager` (`finance_manager@example.com`)
- **Tujuan Pengujian**: Memastikan transaksi POS menghasilkan jurnal otomatis 4 kaki (Kas, Penjualan, HPP, Persediaan) yang seimbang seketika.
- **Kategori Skenario**: Automated General Ledger Posting.
- **Testing yang Dilakukan**:
  1. Eksekusi penjualan POS 1 pcs `PROD-TEST-A` (Harga Jual Rp 7.500, HPP Rp 5.667) bayar tunai.
  2. Buka modul Jurnal Umum dan periksa nomor struk POS tersebut.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Jurnal Terbentuk Sempurna & Seimbang:
    - Debet: `1110-01 Kas Toko` = Rp 7.500
    - Kredit: `4110-01 Penjualan POS` = Rp 7.500
    - Debet: `5110-01 Beban Pokok Penjualan (HPP)` = Rp 5.667
    - Kredit: `1140-10 Persediaan Barang Dagangan` = Rp 5.667
  - Total Debet = Rp 13.167 | Total Kredit = Rp 13.167 (Seimbang 100%).
- **Solusi**:
  Posting jurnal otomatis di [`PosOrderService.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/PosOrderService.php) memelihara kepatuhan akuntansi.
- **Bukti Pengujian Nyata**:
  - Database: Baris jurnal tercatat otomatis di `journal_entries` dengan status posted.
