# MODUL UAT 06: PERMINTAAN PEMBELIAN & PURCHASE ORDER (PO) APPROVAL
## PENGUJIAN PENGADAAN BARANG & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian Pembelian; `List_Pengerjaan_Developer.md` Bagian B & D; `UIUX_Spec_ERP_Duta_Tunggal.md` Layar C1.  
**Tanggal Pengujian:** 28 Oktober 2026  
**Status Pengujian:** **100% SUKSES (4/4 LULUS VERIFIKASI BROWSER & DATABASE NYATA)**  
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
  2. Buka menu **PEMBELIAN $\rightarrow$ Permintaan Pembelian (OR)** (`/pembelian/permintaan/9`).
  3. Periksa Order Request `OR-TEST-UAT-01` yang telah disetujui oleh Inventory Manager untuk 100 pcs `Pipa Seamless 5/8 Inch` (`PROD-TEST-A`) @ Rp 5.667.
  4. Dari halaman detail OR yang disetujui, klik tombol aksi **"Terbitkan Purchase Order (PO)"**.
  5. Formulir Buat PO terbuka otomatis dengan banner hijau *"Menerbitkan PO dari Permintaan: OR-TEST-UAT-01 (TERKUNCI SESUAI OR)"*.
  6. Seluruh data supplier (`PT Supplier Logam Sejahtera`), produk (`Pipa Seamless 5/8 Inch`), kuantitas (100 pcs), harga (Rp 5.667), dan pajak (Eksklusif 11%) terisi otomatis tanpa perlu pengetikan ulang.
  7. Simpan dokumen dengan menekan tombol **"Ajukan Approval Atasan"**.
- **Hasil Pengujian**:
  - PO berhasil tersimpan dengan nomor dokumen baru berurutan `PO-20261028-0001` dan berstatus `request_approve` (badge *"Menunggu Approval"*).
  - Tautan referensi `OR-TEST-UAT-01` tersimpan dan tampil di overview kartu Logistik PO.
  - Item pesanan terhubung ke item OR dengan `refer_item_model_id = 10`.
- **Bukti Pengujian Nyata**:
  - **Database Record**:
    ```json
    {
      "id": 22,
      "po_number": "PO-20261028-0001",
      "status": "request_approve",
      "refer_model_type": "App\\Models\\OrderRequest",
      "refer_model_id": 9,
      "total_amount": "629037.00",
      "items_count": 1,
      "item_qty": 100,
      "item_unit_price": "5667.0000000000",
      "item_refer_id": 10
    }
    ```
  - **Screenshot Browser**:
    - `01_uat_po_01_or_approved_detail.png` (Detail OR Disetujui siap terbit PO)
    - `02_uat_po_01_po_create_from_or.png` (Form Buat PO terisi otomatis dari OR terkunci)
    - `03_uat_po_01_po_saved_success.png` (PO tersimpan berhasil dengan status Menunggu Approval dan referensi OR)

---

### Kasus Uji UAT-PO-02 (No Happy Path): Persetujuan Bertingkat PO Nominal Besar (> Rp 10.000.000)
- **Peran Penguji Terautentikasi**: `Purchasing Manager` (`purchasing_manager@example.com`)
- **Tujuan Pengujian**: Memastikan pembatasan wewenang (*Tier Limit*): Purchasing Manager hanya berwenang menyetujui PO $\le$ Rp 10.000.000. Di atas Rp 10.000.000 wajib dialihkan ke Direktur / Owner / Finance Manager.
- **Kategori Skenario**: **No Happy Path (Tier Approval Escalation)**.
- **Testing yang Dilakukan**:
  1. Siapkan dokumen PO bernilai total **Rp 25.000.000** (`PO-TEST-UAT-25JT`) dengan status `request_approve`.
  2. Login sebagai `purchasing_manager@example.com`.
  3. Buka halaman detail PO di `/pembelian/po/20`.
  4. Klik tombol **"Setujui PO"**, lalu klik konfirmasi **"Ya, Setujui Sekarang"** pada modal dialog.
- **Hasil Pengujian**:
  - Sistem memblokir persetujuan Purchasing Manager dan menampilkan banner notifikasi error merah:  
    *"Persetujuan bertingkat: Nilai Purchase Order di atas Rp 10.000.000 wajib disetujui oleh Direktur / Owner / Finance Manager."*
  - Wewenang terkunci rapat: dokumen tetap berstatus `request_approve` dan `approved_by` tetap bernilai `null`.
- **Bukti Pengujian Nyata**:
  - **Database Record**:
    ```json
    {
      "id": 20,
      "po_number": "PO-TEST-UAT-25JT",
      "status": "request_approve",
      "total_amount": "25000000.00",
      "approved_by": null,
      "date_approved": null
    }
    ```
  - **Screenshot Browser**:
    - `04_uat_po_02_purchasing_manager_view_po25jt.png` (Tampilan detail PO Rp 25 jt oleh Purchasing Manager)
    - `05_uat_po_02_tier_limit_blocked_error.png` (Sistem memblokir aksi persetujuan dengan pesan tier limit)

---

### Kasus Uji UAT-PO-03: Persetujuan PO Nominal Besar oleh Direktur / Owner
- **Peran Penguji Terautentikasi**: `Owner` (`owner@example.com`)
- **Tujuan Pengujian**: Memverifikasi bahwa Owner / Direktur dapat menyetujui PO nominal besar dan mengunci harga beli serta status PO menjadi `approved`.
- **Kategori Skenario**: Top-Tier Executive Approval.
- **Testing yang Dilakukan**:
  1. Login sebagai `owner@example.com`.
  2. Buka PO senilai Rp 25.000.000 (`PO-TEST-UAT-25JT`) yang tertahan pada Kasus Uji UAT-PO-02.
  3. Klik tombol **"Setujui PO"**, lalu klik **"Ya, Setujui Sekarang"** pada modal konfirmasi.
- **Hasil Pengujian**:
  - PO berhasil disetujui tanpa penolakan. Banner hijau tampil: *"Pesanan Pembelian PO-TEST-UAT-25JT berhasil disetujui."*
  - Badge berubah menjadi hijau: **"Disetujui"**.
  - Alur workflow tahap 2 menyala hijau: *"Disetujui 28/10/2026"*.
  - Aksi operasional lanjutan terbuka: tombol **"Terima Barang (GRN)"**, **"Ajukan Tutup"**, **"Tutup PO"**, dan **"Cetak PDF"**.
- **Bukti Pengujian Nyata**:
  - **Database Record**:
    ```json
    {
      "id": 20,
      "po_number": "PO-TEST-UAT-25JT",
      "status": "approved",
      "total_amount": "25000000.00",
      "approved_by": 15,
      "date_approved": "2026-10-28 00:00:00"
    }
    ```
    *(Catatan: ID 15 = `owner@example.com`)*
  - **Screenshot Browser**:
    - `06_uat_po_03_owner_view_po25jt.png` (Tampilan detail PO Rp 25 jt oleh Owner)
    - `07_uat_po_03_owner_approved_success.png` (PO berhasil disetujui oleh Owner dan tombol Terima Barang terbuka)

---

### Kasus Uji UAT-PO-04 (No Happy Path): Pemblokiran Self-Approval Pembuat PO (Segregation of Duties)
- **Peran Penguji Terautentikasi**: `Purchasing Manager` (`purchasing_manager@example.com`)
- **Tujuan Pengujian**: Memastikan staf atau manajer yang membuat dokumen PO dilarang menyetujui dokumen miliknya sendiri (*Anti-Self-Approval / Segregation of Duties*).
- **Kategori Skenario**: **No Happy Path (Internal Control Violation)**.
- **Testing yang Dilakukan**:
  1. Siapkan dokumen PO Rp 5.000.000 (`PO-TEST-UAT-5JT-SELF`) di mana pembuat dokumen adalah Purchasing Manager sendiri (`created_by = 21` / `purchasing_manager@example.com`).
  2. Login sebagai `purchasing_manager@example.com`.
  3. Buka halaman detail PO di `/pembelian/po/21`.
  4. Coba lakukan approval dengan menekan tombol **"Setujui PO"** lalu konfirmasi modal.
- **Hasil Pengujian**:
  - Sistem memblokir upaya self-approval secara tegas dengan banner error merah:  
    *"Pemisahan tugas (Segregation of Duties): Pembuat Purchase Order tidak boleh menyetujui dokumen miliknya sendiri."*
  - State dokumen di database tetap `request_approve` dan tidak terjadi perubahan status maupun pengesahan.
- **Bukti Pengujian Nyata**:
  - **Database Record**:
    ```json
    {
      "id": 21,
      "po_number": "PO-TEST-UAT-5JT-SELF",
      "status": "request_approve",
      "total_amount": "5000000.00",
      "created_by": 21,
      "approved_by": null,
      "date_approved": null
    }
    ```
    *(Catatan: `created_by = 21` adalah `purchasing_manager@example.com`)*
  - **Screenshot Browser**:
    - `08_uat_po_04_pm_view_self_po5jt.png` (Tampilan PO buatan Purchasing Manager sendiri)
    - `09_uat_po_04_self_approval_blocked_error.png` (Sistem memblokir self-approval dengan notifikasi Segregation of Duties)

---

### RINGKASAN HASIL PENGUJIAN UAT MODUL 06

| Kode Kasus Uji | Skenario | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| **UAT-PO-01** | Terbit PO dari Order Request (OR) | Barang & harga ditarik otomatis, referensi OR tercatat | 100 pcs ditarik otomatis @ Rp 5.667, Ref OR `OR-TEST-UAT-01` tercatat, nomor PO `PO-20261028-0001` | **PASS** |
| **UAT-PO-02** | Persetujuan bertingkat PO > 10 Jt oleh PM | Ditolak sistem (Tier Limit) | Ditolak dengan pesan wajib persetujuan Direktur/Owner/Finance Manager, status tetap `request_approve` | **PASS** |
| **UAT-PO-03** | Persetujuan PO > 10 Jt oleh Owner | Berhasil disetujui | Disetujui (`status = approved`, `approved_by = 15`), tombol GRN terbuka | **PASS** |
| **UAT-PO-04** | Self-Approval oleh pembuat PO | Ditolak sistem (Segregation of Duties) | Ditolak dengan pesan Pembuat PO dilarang menyetujui dokumen miliknya sendiri, status tetap `request_approve` | **PASS** |
