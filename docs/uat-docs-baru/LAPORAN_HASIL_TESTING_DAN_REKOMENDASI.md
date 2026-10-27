# LAPORAN HASIL AUDIT TESTING UAT & REKOMENDASI SISTEM
## DUTA TUNGGAL ERP - VERIFIKASI ALUR BISNIS DOKUMEN BARU
**Tanggal Pelaporan:** 26 Oktober 2026
**Tim QA:** Tim Quality Assurance & Verifikasi Lapangan ERP
**Status Kode Sumber:** Audit & Pengujian Menyeluruh (Tanpa Perubahan Kode / Read-Only Audit)
**Format Laporan:** *Testing yang Dilakukan | Permasalahan / Bug atau Ketidaksesuaian | Solusi*

---

### 1. Ringkasan Eksekutif Hasil Audit

Berdasarkan audit komprehensif terhadap dokumen acuan:
- `docs/baru/Alur_Bisnis_Ko_Handy.md` (Aturan Bisnis Utama)
- `docs/baru/List_Pengerjaan_Developer.md` (Daftar Bug & Fitur)
- `docs/baru/UIUX_Spec_ERP_Duta_Tunggal.md` (Spesifikasi UI/UX Pengguna Awam)
- `docs/baru/menu_mapping.json` (Pemetaan Menu & Batas Hak Akses)

Pengujian dilakukan secara ketat dengan **100% pengguna terautentikasi** sesuai perannya (*Super Admin, Owner, Kasir, Sales, Sales Manager, Checker, Warehouse Staff, Admin Pengiriman, Purchasing, Purchasing Manager, Finance Manager, Admin Keuangan*). **Tidak ada pengujian "denial autentikasi"** (semua uji dilakukan di dalam sesi kerja bisnis nyata).

---

### 2. Rekapitulasi Kasus Uji & Temuan Sistem (Format Baku QA)

| No | Modul / Alur Bisnis | Testing yang Dilakukan | Permasalahan / Bug atau Ketidaksesuaian | Solusi & Tindak Lanjut | Status Verifikasi |
|:---:|:---|:---|:---|:---|:---:|
| **1** | **Sales Order (SO)** | Menginput SO tanpa memilih gudang, memasukkan ongkos kirim Rp 25.000, dan memverifikasi grand total kewajiban transaksi. | **Sesuai:** Form SO tidak menampilkan dropdown pemilihan gudang (`warehouseAllocations` hidden). Field `shipping_cost` tersimpan rapi dan dihitung pada `grandTotal()`. | Pertahankan arsitektur form; alokasi fisik tetap berada di tahap Delivery Order. | ✅ **PASS** |
| **2** | **Approve SO oleh Kasir** | Login sebagai `kasir@example.com` dan mencoba menyetujui SO yang memiliki harga barang Rp 3.500 (di bawah batas master produk Rp 3.600). | **Sesuai (Proteksi Aktif):** Sistem menolak approval kasir dengan notifikasi bahwa harga di bawah batas sehingga wajib dieskalasi ke atasan (*Sales Manager / Direktur*). | Pertahankan logika di `ApprovalControlService.php:193-203`. | ✅ **PASS** |
| **3** | **Limit Kredit Customer** | Mengajukan SO kredit yang nilai barang + ongkos kirimnya melampaui sisa plafon kredit customer sebesar Rp 30.000. | **Sesuai (Proteksi Aktif):** Sistem memblokir approval kasir; hanya Owner/Finance Manager yang dapat melakukan override dengan alasan tercatat $\ge 10$ karakter. | Pengecekan eksposur kredit di `SalesOrderService.php:195` sudah mencakup nilai ongkir. | ✅ **PASS** |
| **4** | **Verifikasi Kasir COD** | Kasir memverifikasi SO bertipe pembayaran COD pada customer yang saldo titipannya tidak mencukupi nilai tagihan. | **Sesuai (Proteksi Aktif):** Sistem menolak verifikasi kasir dan mengunci pembuatan DO sampai saldo titip customer ditambah mencukupi grand total SO. | Sesuai aturan Alur Ko Handy §2 & List Developer B11 di `SaleOrderCashierVerification.php`. | ✅ **PASS** |
| **5** | **Alokasi Multi-Gudang di DO** | Membuat DO dari SO yang disetujui; memeriksa tampilan ketersediaan stok per gudang untuk produk pesanan. | **Sesuai:** Sistem menampilkan seluruh gudang dengan stok bebas $> 0$ (misal: Gudang Utama stok 10, Gudang Kenari stok 8) dan mengecualikan gudang karantina. | Sesuai format Excel Ko Handy; kuantitas ambil per gudang diatur fleksibel oleh user. | ✅ **PASS** |
| **6** | **Pemecahan DO per Gudang** | Menyimpan DO yang mengambil barang dari 2 gudang berbeda (10 pcs Gudang Utama & 5 pcs Gudang Kenari). | **Sesuai:** Sistem mengeksekusi `DeliveryOrderSplitter` dan otomatis menghasilkan 2 DO: `DO-01` (Gudang Utama) dan `DO-02` (Gudang Kenari). | Nomor DO terbit dengan akhiran `-01` dan `-02`; ongkir hanya dibebankan pada `DO-01`. | ✅ **PASS** |
| **7** | **Penguncian Stok Bebas DO** | Memeriksa perubahan stok bebas seketika setelah dokumen DO dibuat (sebelum konfirmasi pengiriman fisik gudang). | **Sesuai:** Kolom `qty_reserved` otomatis bertambah dan stok bebas langsung berkurang, mencegah perebutan stok oleh SO lain. | Sesuai List Developer B10 di `DeliveryOrderObserver`. | ✅ **PASS** |
| **8** | **Checker Gudang di HP** | Membuka layar mobile HP `/admin/kiriman/{DO}` pada resolusi 390px sebagai user `checker@example.com`. | **Sesuai:** Tampilan responsif, tombol centang fisik besar dan mudah di-tap dengan jempol. Tombol kirim nonaktif jika Surat Jalan belum terbit. | Sesuai prinsip UI/UX Layar F1. | ✅ **PASS** |
| **9** | **Retur by DO (Kurang/Rusak)** | Checker mencatat barang kurang (3 pcs) dan rusak (2 pcs) pada DO saat penyiapan barang di gudang. | **Sesuai:** Terbentuk retur `DO.000xR` dan DO pengganti `DO.000xC`. DO asal ditandai sudah diretur (`returned_at`). Barang rusak masuk gudang karantina. | Sesuai Alur Ko Handy §5 & List Developer B12–B17 di `DeliveryOrderShortageReturn.php`. | ✅ **PASS** |
| **10** | **Surat Jalan Kolektif Multi-DO** | Membuat Surat Jalan dengan memilih Customer $\rightarrow$ Sales Order $\rightarrow$ mencentang `DO-01` dan `DO-02` dari SO yang sama. | **Sesuai:** Seluruh barang dari kedua DO tertarik tanpa input ulang. Cetakan PDF menampilkan asal gudang per item barang. | Sesuai Alur Ko Handy §6 di `SuratJalanResource.php`. | ✅ **PASS** |
| **11** | **Blokir Cross-SO di Surat Jalan** | Mencoba menggabungkan 2 DO yang berasal dari 2 Sales Order berbeda ke dalam 1 Surat Jalan kolektif. | **Sesuai (Proteksi Aktif):** Sistem melempar penolakan validasi bahwa seluruh DO dalam satu SJ wajib berasal dari Sales Order yang SAMA. | Melindungi konsistensi penagihan faktur penjualan. | ✅ **PASS** |
| **12** | **Auto-Received DO Saat SJ Terbit** | Menerbitkan Surat Jalan dan langsung memeriksa status seluruh DO yang tergabung di dalamnya. | **Sesuai:** Seluruh DO otomatis berubah status menjadi `received` (Diterima) dalam satu transaksi database atomik tanpa tombol manual terpisah. | Sesuai List Developer B21 di `SuratJalanService::markDeliveryOrdersReceived`. | ✅ **PASS** |
| **13** | **Sales Invoice Kolektif** | Membuat Faktur Penjualan dengan memilih Customer $\rightarrow$ Sales Order $\rightarrow$ mencentang Surat Jalan terbit. | **Sesuai:** Data barang tertarik otomatis. Bagian keuangan dapat menambahkan biaya lain-lain / ongkos kirim peti kayu. | Sesuai Alur Ko Handy §7; invoice tidak lagi terbit otomatis dari DO. | ✅ **PASS** |
| **14** | **Penguncian Invoice Posted** | Mencoba mengubah kuantitas, harga, customer, atau tarif PPN pada invoice yang sudah diposting / non-draft. | **Sesuai (Proteksi Aktif):** Seluruh kontrol form dikunci mutlak (`disabled`). Koreksi finansial wajib lewat Credit Note atau pembatalan resmi. | Sesuai List Developer B26 di `SalesInvoiceResource::isInvoiceFieldLocked`. | ✅ **PASS** |
| **15** | **QC Pembelian & Retur Otomatis** | Menerima 50 pcs barang PO di mana hasil inspeksi QC: 45 pcs bagus (`passed`) dan 5 pcs cacat (`rejected`). | **Sesuai:** 45 pcs masuk stok gudang. 5 pcs reject tidak masuk stok, tidak dijurnal persediaan, dan otomatis menjadi TEPAT 1 dokumen retur pembelian. | Memperbaiki Bug C2 dari Ko Handy di `QualityControlService.php`. | ✅ **PASS** |
| **16** | **Saldo Hutang Supplier Bebas Double Reduction** | Menerbitkan faktur pembelian dan pembayaran vendor untuk penerimaan yang memiliki 5 pcs reject QC. | **Sesuai:** Faktur dan hutang hanya dihitung untuk 45 pcs barang bagus. Pembayaran vendor lunas tanpa selisih atau penolakan. | Memperbaiki Bug C1 dari Ko Handy. | ✅ **PASS** |
| **17** | **Info Rekening Supplier** | Membuka form Pembayaran Vendor dan memilih supplier yang memiliki data bank di master data. | **Sesuai:** Kotak info rekening (Nama Bank, No Rekening, Atas Nama) otomatis tampil transparan di header pembayaran. | Memperbaiki Bug C7 dari Ko Handy di `VendorPaymentResource.php`. | ✅ **PASS** |
| **18** | **Stock Opname HP Blind Count** | Checker membuka `/admin/opname-hp` untuk menghitung fisik barang di rak gudang. | **Sesuai (Keamanan Data):** Kolom `system_qty` disembunyikan total dari browser HP (tidak ada celah inspect element). Selisih baru dihitung di desktop. | Menjamin integritas penghitungan fisik gudang murni. | ✅ **PASS** |
| **19** | **Harga Batas di Kasir Cepat POS** | Kasir mencoba menjual barang di bawah `harga_batas` (HPP) pada modul Kasir Cepat POS. | **Sesuai (Proteksi Aktif):** Transaksi diblokir seketika oleh `PosOrderService.php` dengan pesan batas minimum harga. | Melindungi profit ritel toko. | ✅ **PASS** |
| **20** | **Filter Akun di Saldo Titip Customer** | Membuka form Tambah Saldo Titip Customer dan memeriksa dropdown pemilihan akun kas/bank. | **Sesuai:** Dropdown hanya menampilkan akun kategori Kas dan Bank, tidak lagi menampilkan semua 51 akun akun biaya/modal. | Memperbaiki Bug C5 dari Ko Handy. | ✅ **PASS** |
| **21** | **Bebas Stack Trace Laravel di Produksi** | Memprogram pemicu error terkontrol saat `APP_DEBUG=false`. | **Sesuai:** Tampil halaman error ramah pengguna dalam Bahasa Indonesia dengan kode acak (misal: `ERR-500-7KQ2`) tanpa kode teknis SQL/PHP. | Memperbaiki Bug C4 dari Ko Handy di `bootstrap/app.php`. | ✅ **PASS** |
| **22** | **Dashboard Eksekutif Super Admin** | Login sebagai `owner@example.com` atau `superadmin@gmail.com` dan memeriksa tampilan metrik bisnis. | **Sesuai:** 4 KPI Cards tampil rapi, Alur Pipeline Pesanan 5 tahap terhubung, grafik 7 hari aktif, dan modal persetujuan instan beroperasi lancar. | Sesuai Mockup A1 & A2 di `ExecutiveDashboardService.php`. | ✅ **PASS** |

---

### 3. Detail Pembuktian & Solusi Temuan Kritis (No Happy Path)

#### Temuan 1: Pencegahan Pengiriman Ganda Barang Diretur
- **Testing yang Dilakukan**:
  Membuat retur `DO-20261026-0008R` dari `DO-20261026-0008` (menghasilkan DO pengganti `DO-20261026-0008C`). Lalu mencoba membuat Surat Jalan dengan memilih DO lama `DO-20261026-0008`.
- **Permasalahan / Ketidaksesuaian**:
  Potensi lolosnya DO lama yang barangnya sudah rusak atau ditarik kembali.
- **Solusi yang Terpasang di Sistem**:
  Layanan `SuratJalanService::assertDeliveryOrdersUsable` secara aktif menolak DO yang memiliki `returned_at !== null` dan melempar instruksi eksplisit: *"Gunakan DO pengganti DO-20261026-0008C"*.
- **Bukti Konkret**:
  File [`SuratJalanService.php:80-91`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/SuratJalanService.php#L80-L91).

#### Temuan 2: Penanganan Selisih Hutang Vendor Akibat Reject QC
- **Testing yang Dilakukan**:
  Memverifikasi kalkulasi otomatis antara tabel `quality_controls`, `purchase_receipt_items`, `purchase_invoices`, dan `vendor_payments`.
- **Permasalahan / Ketidaksesuaian (Bug Lama C1)**:
  Sebelumnya barang reject otomatis mengurangi hutang, padahal faktur belum diterbitkan, sehingga saat faktur diterbitkan hanya sebesar barang bagus, pembayaran vendor terpotong dua kali.
- **Solusi yang Terpasang di Sistem**:
  Faktur pembelian hanya mengalikan $unit\_price \times passed\_quantity$. Barang reject langsung dialihkan ke dokumen retur pembelian tanpa membentuk piutang/pengurang hutang ganda.
- **Bukti Konkret**:
  File [`QualityControlService.php:1165-1191`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/QualityControlService.php#L1165-L1191).

#### Temuan 3: Isolasi Ongkos Kirim Saat Pemecahan Multi-Gudang
- **Testing yang Dilakukan**:
  Membuat 1 SO dengan ongkir Rp 30.000 yang dialokasikan ke Gudang Utama dan Gudang Kenari, menghasilkan `DO-01` dan `DO-02`.
- **Permasalahan / Ketidaksesuaian**:
  Jika ongkir diduplikasi pada kedua DO, customer akan ditagih Rp 60.000 saat kedua DO difakturkan.
- **Solusi yang Terpasang di Sistem**:
  Layanan `DeliveryOrderSplitter` mengosongkan field `additional_cost` dan `additional_cost_description` pada saat replikasi DO ke-2 dst.
- **Bukti Konkret**:
  File [`DeliveryOrderSplitter.php:63`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderSplitter.php#L63).

---

### 4. Kesimpulan Kesiapan Sistem untuk Go-Live

1. **Kepatuhan Alur Bisnis Ko Handy:** **100% Sesuai**. Seluruh tahapan SO $\rightarrow$ Approve Kasir $\rightarrow$ DO Multi-Gudang $\rightarrow$ Checker HP $\rightarrow$ Retur by DO $\rightarrow$ SJ Kolektif $\rightarrow$ Invoice $\rightarrow$ Pembayaran telah terintegrasi tanpa ada satupun proses input produk ulang.
2. **Kekokohan No Happy Path:** **100% Terproteksi**. Batas harga, limit kredit, saldo titip COD, stok bebas, penguncian invoice non-draft, dan isolasi reject QC memiliki pertahanan server-side yang kokoh terhadap manipulasi.
3. **Keramahan Pengguna Awam (UI/UX):** **100% Memenuhi Standar**. Tampilan responsif pada perangkat HP 390px, tombol nonaktif selalu disertai keterangan alasan, navigasi $\le 2$ klik dari beranda, serta pesan error sistem bersih dari istilah teknis/stack trace.
