# MODUL UAT 07: TERIMA BARANG, QC INSPEKSI & RETUR PEMBELIAN OTOMATIS
## PENGUJIAN PENERIMAAN LOGISTIK, PEMISAHAN REJECT & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `Alur_Bisnis_Ko_Handy.md` Bagian Pembelian; `List_Pengerjaan_Developer.md` Bagian C (Poin C1 & C2) & Bagian D; `UIUX_Spec_ERP_Duta_Tunggal.md` Layar C2.
**Peran Penguji Terautentikasi:**
- `Checker Gudang` / QC (`checker@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Purchasing` (`purchasing@example.com`)

---

### Kasus Uji UAT-QC-01: Penerimaan Barang & Inspeksi Kualitas (QC) Parsial (Ada Qty Bagus & Qty Reject)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy: *"QC saat terima barang: qty bagus masuk stok; qty reject TIDAK masuk stok, TIDAK dijurnal, dan otomatis menjadi TEPAT 1 dokumen retur pembelian"*.
- **Kategori Skenario**: Receiving & Inspection Segregation.
- **Testing yang Dilakukan**:
  1. PO disetujui untuk pemesanan: 50 pcs `PROD-TEST-A` @ Rp 5.667 dari Supplier `SUPP-TEST-UAT-001`.
  2. Barang datang di gudang, checker membuka menu **Penerimaan Barang (Purchase Receipt)**.
  3. Pada form QC Inspeksi:
     - Qty Diterima (Received): `50 pcs`
     - Qty Bagus (Passed): `45 pcs`
     - Qty Cacat / Rusak (Rejected): `5 pcs`
     - Alasan Reject: `"Karat dan kemasan pecah saat pengiriman vendor"`
     - Tindakan Reject: `Return to Supplier`
  4. Klik tombol **"Selesai Inspeksi & Simpan"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - **Stok Fisik:** Hanya 45 pcs (qty passed) yang masuk ke kartu stok gudang `inventory_stocks`. 5 pcs reject tidak pernah menambah stok fisik.
  - **Jurnal Persediaan:** Nilai persediaan yang dicatat hanya untuk 45 pcs ($45 \times 5.667 = \text{Rp } 255.015$). Tidak ada jurnal persediaan untuk 5 pcs reject.
  - **Retur Otomatis:** Terbentuk otomatis tepat **1 dokumen Retur Pembelian (Purchase Return)** untuk 5 pcs tersebut.
- **Solusi**:
  Sistem di [`QualityControlService.php:1167-1191`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/QualityControlService.php#L1167-L1191) berhasil memisahkan perlakuan barang bagus dan reject secara konsisten.
- **Bukti Pengujian Nyata**:
  - Kartu Stok: `inventory_movements` mencatat penambahan stok masuk sebesar +45 pcs.
  - Database Retur: 1 record baru di `purchase_returns` dan `purchase_return_items` bernilai 5 pcs.

---

### Kasus Uji UAT-QC-02 (No Happy Path): Pencegahan Duplikasi Dokumen Retur Pembelian (Anti-Duplicate Return Bug C2)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memastikan bug lama C2 dari Ko Handy (*Reject QC menghasilkan 2 dokumen retur untuk 1 pcs yang sama*) **TIDAK PERNAH TERULANG**.
- **Kategori Skenario**: **No Happy Path (Duplicate Return Document Guard)**.
- **Testing yang Dilakukan**:
  1. Ambil transaksi QC pada Kasus Uji UAT-QC-01 yang telah memproses 5 pcs reject.
  2. Coba simpan ulang atau pemicu pemicuan ulang event QC melalui update form penerimaan.
  3. Periksa tabel `purchase_returns` untuk PO yang sama.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memeriksa `hasItem` dan flag `purchase_return_processed`. Tidak ada dokumen retur kedua yang terbuat. Jumlah dokumen retur pembelian untuk transaksi tersebut tetap **tepat 1 dokumen**.
- **Solusi**:
  Pemeriksaan idempotensi di [`QualityControlService.php:1171-1178`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/QualityControlService.php#L1171-L1178) mencegah duplikasi:
  `$hasItem = PurchaseReturnItem::where('purchase_return_id', ...)->where('product_id', ...)->exists(); if (!$hasItem) { ... }`
- **Bukti Pengujian Nyata**:
  - Query Database: `SELECT COUNT(*) FROM purchase_returns WHERE quality_control_id = QC_ID` menghasilkan tepat `1`.

---

### Kasus Uji UAT-QC-03 (No Happy Path): Validasi Qty Passed + Rejected Melebihi Kuantitas Datang
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Mencegah kesalahan input di mana jumlah barang bagus + barang rusak melebihi fisik barang yang dibongkar dari truk vendor.
- **Kategori Skenario**: **No Happy Path (Inspection Quantity Overflow)**.
- **Testing yang Dilakukan**:
  1. Surat jalan vendor mencatat barang datang sebanyak 20 pcs.
  2. Checker memasukkan: Passed = 18 pcs, Rejected = 5 pcs (Total = 23 pcs, lebih 3 pcs).
  3. Klik Simpan QC.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem menolak penyimpanan dengan pesan kesalahan:
  *"Total QC passed dan rejected (23) tidak boleh melebihi Qty Received (20)."*
- **Solusi**:
  Validasi matematis di [`QualityControlService.php:135`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/QualityControlService.php#L135) memblokir inkonsistensi data.
- **Bukti Pengujian Nyata**:
  - Respon Form: Notifikasi error merah, data tidak tersimpan ke database.
