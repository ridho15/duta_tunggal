# INDEKS MASTER DOKUMEN UAT PER ROLE (NO HAPPY PATH)
## DUTA TUNGGAL ERP

Dokumentasi Acceptance Testing ini telah dipecah secara modular per peran (*role-based*) untuk memudahkan pelaksanaan uji di lapangan oleh masing-masing divisi / tim penguji QA.

Semua skenario pengujian berfokus **100% pada kondisi negatif (No Happy Path)**: validasi batas nilai (*boundary limits*), penolakan hak akses ilegal (HTTP 403), pencegahan stok minus, penguncian dokumen yang sudah disetujui, integritas jurnal berpasangan, dan perlindungan isolasi cabang.

---

### Daftar Dokumen UAT Per Role:

| No | File Dokumen | Peran yang Diuji | Modul Terkait | Jumlah Skenario Negatif |
| :---: | :--- | :--- | :--- | :---: |
| **01** | [01-ROLE-SALES-DAN-SALES-MANAGER.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/01-ROLE-SALES-DAN-SALES-MANAGER.md) | `Sales`, `Sales Manager` | Customer, Penawaran (Quotation), Sales Order (SO) | 8 Kasus Uji |
| **02** | [02-ROLE-ADMIN-PENGIRIMAN-LOGISTIK-DRIVER.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/02-ROLE-ADMIN-PENGIRIMAN-LOGISTIK-DRIVER.md) | `Admin Pengiriman`, `Delivery Driver` | Delivery Order (DO), Surat Jalan (SJ), Armada Kendaraan | 8 Kasus Uji |
| **03** | [03-ROLE-INVENTORY-GUDANG-CHECKER.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/03-ROLE-INVENTORY-GUDANG-CHECKER.md) | `Inventory Manager`, `Warehouse Staff`, `Checker` | Penerimaan Barang, QC Inspeksi, Stok, Opname, Transfer Gudang | 8 Kasus Uji |
| **04** | [04-ROLE-PPIC-PRODUKSI-MANUFAKTUR.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/04-ROLE-PPIC-PRODUKSI-MANUFAKTUR.md) | `Inventory Manager (PPIC)`, `Production Staff`, `Checker QC` | BOM Resep, SPK Produksi, Material Issue, QC Output | 8 Kasus Uji |
| **05** | [05-ROLE-PURCHASING-DAN-PURCHASING-MANAGER.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/05-ROLE-PURCHASING-DAN-PURCHASING-MANAGER.md) | `Purchasing`, `Purchasing Manager` | Supplier, Order Request (OR), Purchase Order (PO), Retur Beli | 8 Kasus Uji |
| **06** | [06-ROLE-KASIR-POS-DAN-CUSTOMER-SERVICE.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/06-ROLE-KASIR-POS-DAN-CUSTOMER-SERVICE.md) | `Kasir`, `Customer Service` | Kasir Cepat POS, Sesi & Shift Kasir, Riwayat POS | 8 Kasus Uji |
| **07** | [07-ROLE-FINANCE-ADMIN-KEUANGAN-ACCOUNTING.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/07-ROLE-FINANCE-ADMIN-KEUANGAN-ACCOUNTING.md) | `Finance Manager`, `Admin Keuangan`, `Accounting` | Faktur Tagihan, Vendor Payment, Kas/Bank, Jurnal Umum, Aset Tetap | 8 Kasus Uji |
| **08** | [08-ROLE-SUPER-ADMIN-OWNER-AUDITOR.md](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/uat-per-role/08-ROLE-SUPER-ADMIN-OWNER-AUDITOR.md) | `Super Admin`, `Owner`, `Auditor`, `IT Support` | Manajemen User RBAC, Cabang Multi-Tenancy, Audit Trail | 7 Kasus Uji |

**Total Skenario Negatif Terverifikasi:** **63 Kasus Uji Ekstrem**

---

### Cara Menggunakan Dokumen:
1. Buka file panduan sesuai peran yang akan diuji oleh staf atau tim QA.
2. Ikuti kolom **Langkah-langkah Pengujian Presisi** dan gunakan nilai pada **Payload / Data Uji Ekstrem**.
3. Cek apakah respon sistem sesuai dengan **Hasil yang Diharapkan (Expected Result)**:
   - **PASS**: Sistem berhasil memblokir input/aksi ilegal dengan pesan validasi yang jelas dan tepat.
   - **FAIL**: Aksi ilegal lolos, stok bocor/minus, atau terjadi error 500 unhandled.
4. Isi tabel lembar hasil pengujian di bagian akhir setiap dokumen untuk dokumentasi rilis resmi.
