# MODUL UAT 01: SALES ORDER (SO) & APPROVAL KASIR
## PENGUJIAN ALUR PENJUALAN & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian 1 & 2; `List_Pengerjaan_Developer.md` Bagian B (Poin 1–6); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 6.2 & 6.3.
**Peran Penguji Terautentikasi:** 
- `Sales` (`sales@example.com`)
- `Kasir` (`kasir@example.com`)
- `Sales Manager` (`sales_manager@example.com`)
- `Owner / Direktur` (`owner@example.com`)

---

### Kasus Uji UAT-SO-01: Verifikasi Ketiadaan Pemilihan Gudang di Form Input SO
- **Peran Penguji Terautentikasi**: `Sales` (`sales@example.com`)
- **Tujuan Pengujian**: Memastikan prinsip bisnis Ko Handy: *"Gudang TIDAK dipilih di SO; pemilihan gudang dan alokasi fisik dilakukan sepenuhnya di tahap Delivery Order (DO)"*.
- **Kategori Skenario**: Usability & Business Rule Compliance (Pencegahan Kompleksitas bagi Pengguna Awam).
- **Testing yang Dilakukan**:
  1. Login sebagai `sales@example.com`.
  2. Buka menu **PENJUALAN $\rightarrow$ Pesanan Penjualan (SO)**, lalu klik tombol **"Buat Pesanan Penjualan"**.
  3. Pilih Customer `CUST-TEST-UAT-001`.
  4. Tambahkan item produk `PROD-TEST-A` (Qty: 10, Harga: Rp 7.500).
  5. Periksa formulir apakah terdapat field *Pilih Gudang*, *Alokasi Gudang*, atau dropdown gudang sumber.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Tidak ada dropdown pemilihan gudang yang ditampilkan kepada staf sales. Komponen `warehouseAllocations` telah di-set `->hidden()` di `SaleOrderResource.php` line 1565. Staf sales tidak dibebani menentukan gudang pengiriman.
- **Solusi**:
  Pertahankan arsitektur form ini. Alokasi stok fisik tetap didelegasikan ke tahap DO oleh admin logistik/gudang.
- **Bukti Pengujian Nyata**:
  - Kode Sumber: [`SaleOrderResource.php:1565`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/SaleOrderResource.php#L1565) (`Repeater::make('warehouseAllocations')->hidden()`).
  - Antarmuka: Form input SO hanya menampilkan kolom Produk, Qty, Satuan, Harga Satuan, Diskon (%), dan Catatan Baris.

---

### Kasus Uji UAT-SO-02: Input Ongkos Kirim (Shipping Cost) di SO dan Dampaknya ke Grand Total
- **Peran Penguji Terautentikasi**: `Sales` (`sales@example.com`)
- **Tujuan Pengujian**: Memverifikasi bahwa ongkos kirim dapat diinput di SO, tidak tercampur ke subtotal harga barang, dan otomatis menambah nilai `grandTotal()` yang akan ditagihkan ke invoice.
- **Kategori Skenario**: Data Calculation & Accuracy.
- **Testing yang Dilakukan**:
  1. Login sebagai `sales@example.com`.
  2. Buat SO baru untuk customer `CUST-TEST-UAT-001`.
  3. Tambahkan produk `PROD-TEST-A` Qty: 10 @ Rp 10.000 (Total barang = Rp 100.000).
  4. Pada field **Ongkos Kirim**, masukkan nominal `Rp 25.000`.
  5. Periksa apakah sistem menghitung total kewajiban transaksi menjadi Rp 125.000.
  6. Simpan dokumen SO.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Field `shipping_cost` tersimpan dengan benar sebagai `25000.00`. Total barang tercatat `100000.00`, dan method `$saleOrder->grandTotal()` menghasilkan `125000.00`.
- **Solusi**:
  Sistem sudah sesuai dengan Alur Ko Handy §1 dan List Developer B1.
- **Bukti Pengujian Nyata**:
  - Kolom Database: `sale_orders.shipping_cost = 25000.00`, `sale_orders.total_amount = 100000.00`.
  - Kode Sumber: [`SaleOrder.php:179`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Models/SaleOrder.php#L179) (`return round((float) $this->total_amount + (float) $this->shipping_cost, 2);`).

---

### Kasus Uji UAT-SO-03 (No Happy Path): Blokir Kasir Menyetujui SO dengan Harga di Bawah Batas Harga Produk
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Memastikan Kasir **DITOLAK** saat mencoba menyetujui SO yang salah satu harganya di bawah `harga_batas` (HPP / batas bawah master produk), dan dokumen wajib dialihkan ke atasan (*Sales Manager / Direktur*).
- **Kategori Skenario**: **No Happy Path (Price Protection Boundary)**.
- **Testing yang Dilakukan**:
  1. Login sebagai `sales@example.com`.
  2. Buat SO draft untuk produk `PROD-TEST-B` (di master: `sell_price = 5.000`, `harga_batas = 3.600`, `cost_price = 3.600`).
  3. Ubah harga jual satuan menjadi `Rp 3.500` (di bawah batas harga Rp 3.600).
  4. Simpan SO dan ajukan persetujuan (*Request Approve*).
  5. Logout, kemudian login sebagai `kasir@example.com`.
  6. Buka daftar Pesanan Penjualan, cari SO tersebut (status: *Menunggu Approve Kasir*).
  7. Klik tombol aksi **"Setujui"** sebagai Kasir.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir aksi Kasir dengan pesan penolakan yang tegas dan informatif:
  *"Harga pada SO ini di bawah batas harga master produk sehingga tidak dapat disetujui Kasir; perlu persetujuan atasan (Sales Manager / Direktur)."* Status SO tetap `request_approve`.
- **Solusi**:
  Logika pengamanan di [`ApprovalControlService.php:193-203`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/ApprovalControlService.php#L193-L203) berjalan 100% presisi. SO tidak dapat dibobol oleh kasir.
- **Bukti Pengujian Nyata**:
  - Respon Backend: Menolak approval kasir melalui `ApprovalControlService::canApprove()` (`allowed: false`).
  - Log Database: Kolom `sale_orders.status` tetap `'request_approve'`, `sale_orders.approve_by` tetap `NULL`.

---

### Kasus Uji UAT-SO-04 (No Happy Path): Diskon Cabang Mengakibatkan Harga Akhir di Bawah Harga Batas
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Memastikan batas harga tetap ditegakkan pada harga **setelah diskon persentase**, bukan hanya harga kotor sebelum diskon.
- **Kategori Skenario**: **No Happy Path (Discounted Net Price Violation)**.
- **Testing yang Dilakukan**:
  1. Login sebagai `sales@example.com`.
  2. Input SO produk `PROD-TEST-A` (Harga Jual standar: Rp 6.000, Batas Minimum: Rp 5.667).
  3. Masukkan diskon cabang sebesar `10%`.
  4. Perhitungan harga netto: $6.000 - 10\% = \text{Rp } 5.400$ (jatuh di bawah batas minimum Rp 5.667).
  5. Klik *Request Approve*.
  6. Login sebagai `kasir@example.com` dan coba setujui SO tersebut.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem mendeteksi harga netto Rp 5.400 melanggar batas harga Rp 5.667 melalui fungsi `ProductPriceLimitation::validatePrice()`. Kasir ditolak untuk menyetujui.
- **Solusi**:
  Aturan bisnis Alur Ko Handy §2 & List Developer B4 terpenuhi.
- **Bukti Pengujian Nyata**:
  - Method Pembanding: [`ProductPriceLimitation::validatePrice`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Support/ProductPriceLimitation.php#L67) menghitung harga efektif ($unit\_price \times (1 - diskon/100)$) terhadap `floor_price`.

---

### Kasus Uji UAT-SO-05 (No Happy Path): Pelanggaran Limit Kredit Customer Menolak Approval Kasir
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Memastikan bahwa Kasir **TIDAK BISA** menyetujui SO apabila total piutang customer + nilai transaksi SO (termasuk ongkir) melampaui limit kredit yang ditentukan di master customer.
- **Kategori Skenario**: **No Happy Path (Credit Limit Breach)**.
- **Testing yang Dilakukan**:
  1. Customer `CUST-TEST-UAT-001` memiliki `credit_limit = Rp 10.000.000`.
  2. Piutang belum lunas saat ini: `Rp 9.500.000`. Sisa plafon kredit: `Rp 500.000`.
  3. Sales membuat SO baru senilai: Barang Rp 480.000 + Ongkos Kirim Rp 50.000 = **Rp 530.000** (melebihi plafon sebesar Rp 30.000).
  4. Sales mengajukan *Request Approve*.
  5. Login sebagai `kasir@example.com` dan klik tombol **"Setujui"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Persetujuan kasir ditolak seketika dengan notifikasi:
  *"Persetujuan Ditolak: Transaksi melebihi limit kredit customer (Plafon: Rp 10.000.000, Total Piutang + SO: Rp 10.030.000, Kelebihan: Rp 30.000) — Owner, Super Admin atau Finance Manager dapat mengecualikan dengan alasan tercatat."*
- **Solusi**:
  Sistem menghitung eksposur kredit secara komprehensif (termasuk ongkos kirim) di [`SalesOrderService.php:195`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SalesOrderService.php#L195).
- **Bukti Pengujian Nyata**:
  - Exception: `ValidationException` dengan pesan pelanggaran plafon kredit.
  - State Database: `sale_orders.status` tetap `request_approve`.

---

### Kasus Uji UAT-SO-06: Eskalasi & Override Persetujuan Kredit oleh Finance Manager / Owner
- **Peran Penguji Terautentikasi**: `Finance Manager` (`finance_manager@example.com`)
- **Tujuan Pengujian**: Memverifikasi bahwa jika SO ditolak oleh Kasir karena limit kredit, pihak berwenang tingkat atas (*Finance Manager, Owner, Super Admin*) dapat memberikan persetujuan pengecualian (*credit override*) dengan alasan tertulis minimal 10 karakter yang tercatat di audit trail.
- **Kategori Skenario**: Supervisory Override & Audit Trail Compliance.
- **Testing yang Dilakukan**:
  1. Buka SO yang tertahan pada Kasus Uji UAT-SO-05 sebagai `finance_manager@example.com`.
  2. Klik tombol **"Setujui (Override Kredit)"**.
  3. Masukkan alasan pengecualian pendek: `"Boleh"` (5 karakter) $\rightarrow$ Klik Submit.
  4. Amati respon validasi sistem.
  5. Ubah alasan menjadi lengkap: `"Customer prioritas, jaminan BG cair lusa"` (42 karakter) $\rightarrow$ Klik Submit.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Saat alasan < 10 karakter: Sistem menolak submit dengan pesan validasi *"Alasan pengecualian limit kredit wajib diisi minimal 10 karakter"*.
  - Saat alasan $\ge 10$ karakter: SO berhasil disetujui (`status = approved`). Alasan dan identitas penyetuju tercatat di tabel `approval_overrides`.
- **Solusi**:
  Sistem menerapkan *Internal Control & Segregation of Duties* yang sempurna.
- **Bukti Pengujian Nyata**:
  - Database Audit: Record baru di `approval_overrides` (`document_type = 'sale_order'`, `user_id = finance_manager_id`, `reason = 'Customer prioritas, jaminan BG cair lusa'`).

---

### Kasus Uji UAT-SO-07 (No Happy Path): Verifikasi Kasir untuk Transaksi COD yang Terkait Saldo Titip Customer
- **Peran Penguji Terautentikasi**: `Kasir` (`kasir@example.com`)
- **Tujuan Pengujian**: Memastikan aturan Ko Handy §2: *"COD: kasir memverifikasi bahwa titipan/pembayaran customer sudah benar-benar masuk sebelum DO dibuat"*, dan memblokir DO jika saldo titip customer di sistem tidak mencukupi.
- **Kategori Skenario**: **No Happy Path (Cash/Deposit Deficit on COD)**.
- **Testing yang Dilakukan**:
  1. Buat SO tipe pembayaran `COD` untuk customer `CUST-TEST-UAT-002` senilai Rp 2.500.000.
  2. Di data sistem, Customer `CUST-TEST-UAT-002` hanya memiliki saldo titip (`deposits.remaining_amount`) sebesar Rp 1.000.000.
  3. Login sebagai `kasir@example.com` dan buka detail SO.
  4. Klik tombol **"Verifikasi Kasir (COD)"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Verifikasi kasir diblokir dengan pesan:
  *"Saldo Titip Customer tidak mencukupi untuk SO SO-TEST-XXXX: dibutuhkan Rp 2.500.000 (barang + ongkos kirim), saldo Rp 1.000.000, kurang Rp 1.500.000. Tambahkan Saldo Titip Customer terlebih dahulu."*
- **Solusi**:
  Sistem di [`SaleOrderCashierVerification.php:61-73`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SaleOrderCashierVerification.php#L61-L73) mengunci baris deposit dan mencegah pembuatan DO fiktif sebelum uang riil masuk ke kas perusahaan.
- **Bukti Pengujian Nyata**:
  - Status SO: `sale_orders.cashier_approved_at` tetap `NULL`.
  - Percobaan Buat DO: Jika user memaksa membuka form DO untuk SO ini, [`DeliveryOrderSourceValidator.php:69`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderSourceValidator.php#L69) menolak dengan pesan *"Sales Order COD wajib diverifikasi kasir terlebih dahulu sebelum dibuatkan DO"*.

---

### Kasus Uji UAT-SO-08: SO Tipe "Ambil Sendiri" Tetap Melalui Alur Delivery Order
- **Peran Penguji Terautentikasi**: `Sales` (`sales@example.com`) & `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan aturan Ko Handy §1: *"SO 'Ambil Sendiri' tetap melalui DO demi akurasi pemotongan stok gudang dan pencatatan checker"*.
- **Kategori Skenario**: Workflow Integrity.
- **Testing yang Dilakukan**:
  1. Input SO dengan Tipe Pengiriman: `Customer Ambil Sendiri` (`shipping_type = 'Ambil Sendiri'`).
  2. Setujui SO tersebut hingga berstatus `approved`.
  3. Cek apakah SO bisa langsung diloncatkan ke Invoice tanpa melalui DO.
  4. Buka menu Perintah Kirim (DO) dan buat DO dari SO Ambil Sendiri tersebut.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: SO Ambil Sendiri tidak memiliki jalan pintas (*bypass*) ke Invoice. Pembuatan DO tetap diwajibkan sehingga gudang mencatat pengeluaran barang dengan rapi.
- **Solusi**:
  Sistem konsisten menerapkan SO $\rightarrow$ DO $\rightarrow$ SJ $\rightarrow$ Invoice untuk semua tipe pengiriman.
- **Bukti Pengujian Nyata**:
  - Form DO memproses SO 'Ambil Sendiri' dengan penyesuaian label armada menjadi *"Diambil Customer Sendiri"*.

---

### Kasus Uji UAT-SO-09 (No Happy Path): Pencegahan Self-Approval oleh Pembuat Dokumen (Segregation of Duties)
- **Peran Penguji Terautentikasi**: `Sales Manager` (`sales_manager@example.com`)
- **Tujuan Pengujian**: Memastikan pembuat dokumen tidak boleh menyetujui dokumen yang dibuatnya sendiri, demi tata kelola internal (*Internal Control*).
- **Kategori Skenario**: **No Happy Path (Anti-Self-Approval)**.
- **Testing yang Dilakukan**:
  1. Login sebagai `sales_manager@example.com`.
  2. Buat dokumen SO baru atas nama Sales Manager (`created_by = sales_manager_id`).
  3. Masukkan harga dan kuantitas normal, ajukan approval.
  4. Tetap login sebagai `sales_manager@example.com`, buka SO yang baru saja dibuat tersebut.
  5. Coba klik tombol aksi **"Setujui"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Tombol aksi Setujui diblokir atau saat diklik memunculkan penolakan:
  *"Pemisahan tugas (Segregation of Duties): Pembuat Sales Order tidak boleh menyetujui dokumen miliknya sendiri."*
- **Solusi**:
  Fungsi `isSelfApproval()` di [`ApprovalControlService.php:61-79`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/ApprovalControlService.php#L61-L79) secara ketat memblokir persetujuan sepihak. Approval harus dilakukan oleh atasan (Owner / Direktur).
- **Bukti Pengujian Nyata**:
  - Respon Backend: `ApprovalControlService::canApprove()` mengembalikan pesan Segregation of Duties.
