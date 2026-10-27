# MODUL UAT 06: PERMINTAAN PEMBELIAN & PURCHASE ORDER (PO) APPROVAL
## PENGUJIAN PENGADAAN BARANG & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian Pembelian; `List_Pengerjaan_Developer.md` Bagian B & D; `UIUX_Spec_ERP_Duta_Tunggal.md` Layar C1.
**Peran Penguji Terautentikasi:**
- `Purchasing` (`purchasing@example.com`)
- `Purchasing Manager` (`purchasing_manager@example.com`)
- `Finance Manager` (`finance_manager@example.com`)
- `Owner / Direktur` (`owner@example.com`)

---

### Kasus Uji UAT-PO-01: Alur Pembuatan PO dari Permintaan Pembelian (Order Request)
- **Peran Penguji Terautentikasi**: `Purchasing` (`purchasing@example.com`)
- **Tujuan Pengujian**: Memverifikasi alur pengadaan: *"Order Request (OR) disetujui $\rightarrow$ diteruskan menjadi Purchase Order (PO) resmi ke supplier tanpa ketik ulang barang"*.
- **Kategori Skenario**: Procurement Flow & Data Integrity.
- **Testing yang Dilakukan**:
  1. Login sebagai `purchasing@example.com`.
  2. Buka menu **PEMBELIAN $\rightarrow$ Permintaan Pembelian (OR)**.
  3. Buat OR untuk kebutuhan stok barang `PROD-TEST-A` (Qty: 100 pcs, Estimasi: Rp 5.667/unit).
  4. Ajukan approval OR, lalu setujui oleh `inventory_manager@example.com`.
  5. Dari OR yang disetujui, klik tombol aksi **"Buat Purchase Order"**.
  6. Pilih Supplier `SUPP-TEST-UAT-001`.
  7. Periksa item dan nominal yang tertarik ke formulir PO.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Seluruh 100 pcs item barang tertarik sempurna ke form PO. Nomor OR referensi tersimpan di kolom `purchase_orders.order_request_id`.
- **Solusi**:
  Sistem menghubungkan modul OR dan PO secara konsisten.
- **Bukti Pengujian Nyata**:
  - Database: `purchase_orders.po_number` terbit berurutan, relasi `purchase_order_items` terhubung ke `order_request_items`.

---

### Kasus Uji UAT-PO-02 (No Happy Path): Persetujuan Bertingkat PO Nominal Besar (> Rp 10.000.000)
- **Peran Penguji Terautentikasi**: `Purchasing Manager` (`purchasing_manager@example.com`)
- **Tujuan Pengujian**: Memastikan pembatasan wewenang (*Tier Limit*): Purchasing Manager hanya berwenang menyetujui PO $\le$ Rp 10.000.000. Di atas Rp 10.000.000 wajib dialihkan ke Direktur / Owner / Finance Manager.
- **Kategori Skenario**: **No Happy Path (Tier Approval Escalation)**.
- **Testing yang Dilakukan**:
  1. Buat PO bernilai total: **Rp 25.000.000** (melebihi limit Tier 1 Rp 10.000.000).
  2. Ajukan persetujuan PO (*Request Approve*).
  3. Login sebagai `purchasing_manager@example.com`.
  4. Coba klik tombol aksi **"Setujui PO"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir persetujuan Purchasing Manager dengan notifikasi:
  *"Persetujuan bertingkat: Nilai Purchase Order di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager."*
- **Solusi**:
  Logika wewenang bertingkat di [`ApprovalControlService.php:183-209`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/ApprovalControlService.php#L183-L209) mengunci wewenang persetujuan.
- **Bukti Pengujian Nyata**:
  - Respon Backend: `allowed = false`, `reason` menyebutkan syarat wewenang Direktur / Owner.
  - State Database: `purchase_orders.status` tetap `request_approve`.

---

### Kasus Uji UAT-PO-03: Persetujuan PO Nominal Besar oleh Direktur / Owner
- **Peran Penguji Terautentikasi**: `Owner` (`owner@example.com`)
- **Tujuan Pengujian**: Memverifikasi bahwa Owner / Direktur dapat menyetujui PO nominal besar dan mengunci harga beli serta status PO menjadi `approved`.
- **Kategori Skenario**: Top-Tier Executive Approval.
- **Testing yang Dilakukan**:
  1. Login sebagai `owner@example.com`.
  2. Buka PO senilai Rp 25.000.000 yang tertahan pada Kasus Uji UAT-PO-02.
  3. Klik tombol **"Setujui PO"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: PO berhasil disetujui. Status berubah menjadi `approved`, tanggal persetujuan tercatat di `approve_at`, dan tombol cetak PO resmi aktif.
- **Solusi**:
  Alur persetujuan bertingkat berjalan lancar.
- **Bukti Pengujian Nyata**:
  - Database: `purchase_orders.status = 'approved'`, `approve_by = owner_id`.

---

### Kasus Uji UAT-PO-04 (No Happy Path): Pemblokiran Self-Approval Pembuat PO (Segregation of Duties)
- **Peran Penguji Terautentikasi**: `Purchasing Manager` (`purchasing_manager@example.com`)
- **Tujuan Pengujian**: Memastikan staf atau manajer yang membuat dokumen PO dilarang menyetujui dokumen miliknya sendiri (*Anti-Self-Approval*).
- **Kategori Skenario**: **No Happy Path (Internal Control Violation)**.
- **Testing yang Dilakukan**:
  1. Login sebagai `purchasing_manager@example.com`.
  2. Buat PO bernilai Rp 5.000.000 (di bawah batas Rp 10 juta) atas nama akunnya sendiri (`created_by = purchasing_manager_id`).
  3. Ajukan approval.
  4. Tetap login sebagai `purchasing_manager@example.com`, lalu coba klik tombol **"Setujui PO"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Persetujuan diblokir dengan pesan:
  *"Pemisahan tugas (Segregation of Duties): Pembuat Purchase Order tidak boleh menyetujui dokumen miliknya sendiri."*
- **Solusi**:
  Aturan di [`ApprovalControlService.php:94-100`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/ApprovalControlService.php#L94-L100) menegakkan kepatuhan audit.
- **Bukti Pengujian Nyata**:
  - Respon Backend: Penolakan mutlak `self_override = false`. Dokumen harus disetujui oleh atasan (Owner / Super Admin).
