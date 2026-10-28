# MODUL UAT 07: TERIMA BARANG, QC INSPEKSI & RETUR PEMBELIAN OTOMATIS
## PENGUJIAN PENERIMAAN LOGISTIK, PEMISAHAN REJECT & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `Alur_Bisnis_Ko_Handy.md` Bagian Pembelian; `List_Pengerjaan_Developer.md` Bagian C (Poin C1 & C2) & Bagian D; `UIUX_Spec_ERP_Duta_Tunggal.md` Layar C2.
**Peran Penguji Terautentikasi:**
- `Checker Gudang` / QC (`checker@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Purchasing` (`purchasing@example.com`)

---

## Ringkasan Eksekutif Hasil UAT Modul 07

| Kode Kasus Uji | Skenario Pengujian | Target Validasi & Aturan Bisnis | Hasil Database & Browser Nyata | Status |
| :--- | :--- | :--- | :--- | :---: |
| **UAT-QC-01** | Penerimaan Barang & QC Parsial (45 Baik, 5 Reject) | 45 pcs masuk stok & jurnal persediaan ($45 \times 5.667 = \text{Rp } 255.015$). 5 pcs reject otomatis menjadi TEPAT 1 dokumen retur pembelian draft. | Receipt `GRN-20261028-0001` (ID: 5) status `completed`. Stok `PROD-TEST-A` bertambah +45 pcs (`qty_available: 45`). Jurnal debit/kredit Rp 255.015,00. Terbentuk dokumen Retur Pembelian `NR-20261028-6928` (ID: 6) status `draft` untuk 5 pcs. | **100% PASS** |
| **UAT-QC-02** | Pencegahan Duplikasi Dokumen Retur (Anti-Duplicate Return Bug C2 Guard) | Re-trigger penerimaan/konfirmasi tidak boleh menciptakan dokumen retur kedua untuk barang reject yang sama. | Retrigger dieksekusi pada Receipt ID 5. Pengecekan database `purchase_returns` mencatat `count_by_receipt = 1` dan `count_by_qc = 1`. Dokumen retur tidak terduplikasi. | **100% PASS** |
| **UAT-QC-03** | Validasi Qty Passed + Rejected Melebihi Kuantitas Datang (No Happy Path) | Form penerimaan memblokir input saat total Passed + Rejected (18 + 5 = 23) melebihi kuantitas tiba fisik (20). | Form menampilkan pesan error *"Total QC passed dan rejected (23) tidak boleh melebihi Qty Received (20)."*. Data receipt untuk PO ID 34 tidak tersimpan (`receipts_count = 0`). | **100% PASS** |

---

### Kasus Uji UAT-QC-01: Penerimaan Barang & Inspeksi Kualitas (QC) Parsial (Ada Qty Bagus & Qty Reject)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy: *"QC saat terima barang: qty bagus masuk stok; qty reject TIDAK masuk stok, TIDAK dijurnal, dan otomatis menjadi TEPAT 1 dokumen retur pembelian"*.
- **Kategori Skenario**: Receiving & Inspection Segregation.
- **Testing yang Dilakukan**:
  1. PO disetujui untuk pemesanan: 50 pcs `PROD-TEST-A` (`Pipa Seamless 5/8 Inch`) @ Rp 5.667 dari Supplier `PT Supplier Logam Sejahtera` (`PO-TEST-QC-50PCS`, ID: 33).
  2. Checker gudang membuka formulir penerimaan barang di `/gudang/penerimaan/create?purchase_order_id=33`.
  3. Mengisi rincian inspeksi fisik:
     - Qty Tiba Fisik (Received): `50 pcs`
     - Qty Diterima Baik (Passed): `45 pcs`
     - Qty Ditolak (Rejected): `5 pcs`
     - Alasan Ditolak: `"Karat dan kemasan pecah saat pengiriman vendor"`
     - Catatan: `"Penerimaan fisik 50 pcs: 45 lolos QC masuk stok gudang, 5 pcs reject retur vendor."`
  4. Klik tombol **"Simpan & Konfirmasi Masuk Stok"**.
- **Hasil Pengujian & Verifikasi Nyata**:
  - **Surat Jalan Masuk:** Terbit dokumen penerimaan resmi `GRN-20261028-0001` (ID: 5) berstatus `completed` (`Selesai Masuk Stok`).
  - **Banner Retur Otomatis di UI:** Halaman detail penerimaan menampilkan banner informatif kuning:
    *"RETUR PEMBELIAN OTOMATIS (QC REJECT) NR-20261028-6928: Sebanyak 5 pcs barang reject dialihkan otomatis ke dokumen retur supplier dan tidak masuk kartu stok gudang."* dengan tombol interaktif tautan langsung `Lihat Dokumen Retur ->`.
  - **Stok Fisik & Kartu Stok (`inventory_stocks` & `stock_movements`):**
    - `qty_available`: Tepat `45 pcs` (stok awal 0 pcs + 45 pcs passed).
    - `stock_movements`: Tercatat 1 pergerakan tipe `purchase_in` dengan kuantitas `+45 pcs` referensi `GRN-20261028-0001`. 5 pcs reject tidak pernah masuk ke kartu stok gudang.
  - **Pembukuan Jurnal Akuntansi (`journal_entries`):**
    - Debit (Persediaan Barang Dalam Perjalanan / Gudang): **Rp 255.015,00** ($45 \times \text{Rp } 5.667$).
    - Kredit (Hutang Belum Difakturkan / Unbilled AP): **Rp 255.015,00**.
    - Tidak ada jurnal persediaan yang dibukukan untuk 5 pcs reject.
  - **Dokumen Retur Pembelian Otomatis (`purchase_returns`):**
    - Dokumen Retur Otomatis `NR-20261028-6928` (ID: 6) langsung terbentuk dengan status `draft`.
    - Item retur: `Pipa Seamless 5/8 Inch`, Kuantitas: `5.00 pcs`, Nilai Barang (DPP) / Potongan Hutang Dagang (AP): **Rp 28.335,00** ($5 \times \text{Rp } 5.667$).
    - Alasan retur: `"Rejected during receiving: Karat dan kemasan pecah saat pengiriman vendor"`.
  - **Inspeksi QC (`quality_controls`):**
    - Dokumen QC `QC-20261028-0003` (ID: 7) berstatus selesai (`status = 1`), `passed_quantity = 45`, `rejected_quantity = 5`.
- **Bukti Screenshot Browser**:
  - Form Input QC Parsial: ![Form Terisi QC Parsial](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/02_uat_qc_01_form_filled_partial.png)
  - Detail Penerimaan & Banner Retur Otomatis: ![Detail Penerimaan GRN-20261028-0001](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/03_uat_qc_01_receipt_completed_with_return_banner.png)
  - Detail Dokumen Retur Pembelian Draft: ![Detail Retur Pembelian NR-20261028-6928](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/04_uat_qc_01_purchase_return_detail.png)

---

### Kasus Uji UAT-QC-02 (No Happy Path): Pencegahan Duplikasi Dokumen Retur Pembelian (Anti-Duplicate Return Bug C2)
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Memastikan bug lama C2 dari Ko Handy (*Reject QC menghasilkan 2 dokumen retur untuk 1 pcs yang sama*) **TIDAK PERNAH TERULANG**.
- **Kategori Skenario**: **No Happy Path (Duplicate Return Document Guard)**.
- **Testing yang Dilakukan**:
  1. Menggunakan transaksi penerimaan `GRN-20261028-0001` (ID: 5) yang telah menghasilkan retur otomatis untuk 5 pcs reject.
  2. Memicu ulang proses penerimaan / konfirmasi / sinkronisasi QC melalui backend `handleReceivingQcAndAutoReturn`.
  3. Memeriksa jumlah record di tabel `purchase_returns` baik berdasarkan `purchase_receipt_id` maupun `quality_control_id`.
- **Hasil Pengujian & Verifikasi Nyata**:
  - Sistem memeriksa kueri guard idempotensi:
    ```php
    $purchaseReturn = PurchaseReturn::where('purchase_receipt_id', $purchaseReceipt->id)
        ->orWhere('quality_control_id', $qualityControl->id)
        ->first();
    ```
  - Hasil audit database:
    - `count_by_receipt`: **1**
    - `count_by_qc`: **1**
  - Jumlah dokumen retur pembelian untuk transaksi tersebut tetap **TEPAT 1 DOKUMEN** (`NR-20261028-6928`), tidak ada duplikasi record retur sama sekali.
- **Bukti Screenshot Browser**:
  - Halaman Penerimaan Tetap Terhubung ke Dokumen Retur Tunggal: ![Idempotensi Anti-Duplicate Retur](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/05_uat_qc_02_retrigger_receiving_prevent_duplicate.png)

---

### Kasus Uji UAT-QC-03 (No Happy Path): Validasi Qty Passed + Rejected Melebihi Kuantitas Datang
- **Peran Penguji Terautentikasi**: `Checker Gudang` (`checker@example.com`)
- **Tujuan Pengujian**: Mencegah kesalahan input logistik di mana jumlah barang bagus + barang rusak melebihi fisik barang yang dibongkar dari armada supplier.
- **Kategori Skenario**: **No Happy Path (Inspection Quantity Overflow)**.
- **Testing yang Dilakukan**:
  1. PO disetujui: 20 pcs `PROD-TEST-A` (`PO-TEST-QC-OVERFLOW`, ID: 34).
  2. Checker membuka form penerimaan di `/gudang/penerimaan/create?purchase_order_id=34`.
  3. Memasukkan kuantitas tidak valid yang melebihi kedatangan fisik:
     - Qty Tiba Fisik (Received): `20 pcs`
     - Qty Diterima Baik (Accepted/Passed): `18 pcs`
     - Qty Ditolak (Rejected): `5 pcs`
     - Total Passed + Rejected: $18 + 5 = 23 \text{ pcs}$ (Overflow $+3 \text{ pcs}$ di atas 20 pcs).
     - Alasan Ditolak: `"Kemasan sobek dan pipa bengkok"`
  4. Klik tombol **"Simpan & Konfirmasi Masuk Stok"**.
- **Hasil Pengujian & Verifikasi Nyata**:
  - Sistem memblokir penyimpanan dengan banner peringatan validasi:
    `"Total QC passed dan rejected (23) tidak boleh melebihi Qty Received (20)."`
  - Validasi diterapkan berlapis pada Form Request Laravel ([`StorePurchaseReceiptRequest.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Http/Requests/Gudang/StorePurchaseReceiptRequest.php)) dan [`QualityControlService.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/QualityControlService.php).
  - Pengecekan database:
    `PurchaseReceipt::where('purchase_order_id', 34)->count()` = **0**.
    Tidak ada transaksi cacat atau data dummy yang masuk ke basis data.
- **Bukti Screenshot Browser**:
  - Form Input Overflow 18 + 5 > 20: ![Form Input Overflow](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/07_uat_qc_03_form_overflow_input.png)
  - Notifikasi Pemblokiran Validasi Overflow: ![Error Banner Overflow](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-docs-baru/screenshots_modul_07/08_uat_qc_03_overflow_blocked_error.png)

---

## Kesimpulan Evaluasi Modul 07
Seluruh 3 kasus uji UAT Modul 07 telah dieksekusi secara otomatis menggunakan peramban Google Chrome asli dan mencatatkan hasil **100% PASS**:
1. Pemisahan perlakuan fisik dan finansial antara barang bagus (45 pcs) dan barang reject (5 pcs) berjalan sempurna.
2. Stok gudang dan jurnal akuntansi persediaan hanya mencatat barang yang lolos QC.
3. Dokumen Retur Pembelian otomatis tercipta tepat 1 draft untuk barang reject, dilengkapi pelindung anti-duplikasi (Bug C2 guard).
4. Validasi overflow input kuantitas secara tegas melindungi integritas sistem dari kesalahan operator logistik.
