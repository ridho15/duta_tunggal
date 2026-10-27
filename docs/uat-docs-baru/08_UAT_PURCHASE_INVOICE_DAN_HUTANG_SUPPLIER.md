# MODUL UAT 08: INVOICE PEMBELIAN, HUTANG SUPPLIER & VENDOR PAYMENT
## PENGUJIAN TAGIHAN PEMBELIAN, PEMBAYARAN & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `Alur_Bisnis_Ko_Handy.md` Bagian Pembelian; `List_Pengerjaan_Developer.md` Bagian C (Poin C1 & C7); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 6.7.
**Peran Penguji Terautentikasi:**
- `Admin Keuangan` (`admin_keuangan@example.com`)
- `Finance Manager` (`finance_manager@example.com`)
- `Purchasing` (`purchasing@example.com`)

---

### Kasus Uji UAT-AP-01: Verifikasi Akurasi Saldo Hutang Supplier (Penyelesaian Bug C1: Bebas Pengurangan Hutang Ganda)
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Memastikan bug lama C1 dari Ko Handy (*Nilai reject QC ikut mengurangi hutang sehingga pembayaran vendor ditolak karena selisih*) **TERATASI 100%**. Hutang supplier HANYA dihitung dari kuantitas bagus yang difakturkan.
- **Kategori Skenario**: Financial Accuracy & Anti-Double Debt Reduction.
- **Testing yang Dilakukan**:
  1. PO bernilai 10 pcs @ Rp 10.000 (Total PO = Rp 100.000).
  2. Saat QC: 8 pcs bagus diterima, 2 pcs reject (Rp 20.000) otomatis menjadi retur pembelian.
  3. Terbitkan Faktur Pembelian (`PurchaseInvoice`) untuk penerimaan barang tersebut.
  4. Amati nilai tagihan faktur pembelian.
  5. Buat Pembayaran Vendor (`VendorPayment`) untuk melunasi tagihan tersebut.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Nilai Faktur Pembelian terbit sebesar **Rp 80.000** (hanya untuk 8 pcs barang bagus).
  - Nilai hutang supplier di kartu hutang tercatat Rp 80.000.
  - Pembayaran Vendor sebesar Rp 80.000 berhasil dieksekusi lunas (status: `paid`, sisa tagihan = Rp 0).
  - Nilai retur 2 pcs (Rp 20.000) **TIDAK MENGURANGI HUTANG LAGI** (karena faktur memang tidak pernah menagih 2 pcs reject tersebut). Tidak terjadi selisih ganda.
- **Solusi**:
  Sistem menghitung DPP dan hutang supplier murni berdasarkan `passed_quantity` pada penerimaan terkait.
- **Bukti Pengujian Nyata**:
  - Saldo Hutang: `purchase_invoices.total = 80000.00`, `purchase_invoices.remaining_amount = 0.00` setelah dibayar.

---

### Kasus Uji UAT-AP-02: Tampilan Informasi Rekening Supplier di Pembayaran Vendor (Penyelesaian Bug C7)
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Memastikan aturan Ko Handy: *"Rekening supplier wajib tersimpan di master dan otomatis tampil di form pembayaran vendor / permintaan pembayaran"*.
- **Kategori Skenario**: Usability & Financial Data Transparency.
- **Testing yang Dilakukan**:
  1. Buka master data Supplier `SUPP-TEST-UAT-001`, pastikan terisi: Bank = `BCA`, No. Rekening = `1234567890`, Atas Nama = `PT Abdi Karya Sejahtera`.
  2. Buka menu **KEUANGAN $\rightarrow$ Pembayaran Vendor**, klik **"Buat Pembayaran Vendor"**.
  3. Pilih Supplier `SUPP-TEST-UAT-001`.
  4. Amati bagian informasi rekening tujuan transfer.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Bagian header pembayaran secara otomatis menampilkan kotak informasi bank berwarna biru/netral:
  `Bank Tujuan: BCA | No. Rekening: 1234567890 | Atas Nama: PT Abdi Karya Sejahtera`. Staf kasir/keuangan tidak perlu membuka master data secara terpisah.
- **Solusi**:
  Implementasi di [`VendorPaymentResource.php:172`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/VendorPaymentResource.php#L172) dan [`PaymentRequestResource.php:93`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/PaymentRequestResource.php#L93) menarik otomatis info bank.
- **Bukti Pengujian Nyata**:
  - Form UI: Komponen text info bank terisi dinamis saat dropdown supplier berubah.

---

### Kasus Uji UAT-AP-03 (No Happy Path): Pemblokiran Pembayaran Vendor Melebihi Sisa Tagihan Faktur
- **Peran Penguji Terautentikasi**: `Admin Keuangan` (`admin_keuangan@example.com`)
- **Tujuan Pengujian**: Mencegah kesalahan kasir/keuangan mentransfer nominal yang lebih besar daripada sisa hutang faktur.
- **Kategori Skenario**: **No Happy Path (Overpayment Prevention)**.
- **Testing yang Dilakukan**:
  1. Faktur pembelian memiliki sisa hutang: Rp 500.000.
  2. Pada form Pembayaran Vendor, kasir menginput nominal bayar: `Rp 600.000`.
  3. Klik tombol Simpan & Posting Pembayaran.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir aksi penyimpanan dengan pesan:
  *"Jumlah pembayaran (Rp 600.000) tidak boleh melebihi sisa tagihan faktur (Rp 500.000)."*
- **Solusi**:
  Validasi batas pembayaran di `VendorPaymentService` melindungi pengeluaran kas yang tidak sah.
- **Bukti Pengujian Nyata**:
  - Respon Backend: HTTP 422 dengan penolakan nominal overpayment.
