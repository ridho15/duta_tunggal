# MODUL UAT 02: DELIVERY ORDER (DO), ALOKASI MULTI-GUDANG & PENGUNCIAN STOK
## PENGUJIAN ALUR PERINTAH PENGIRIMAN & SKENARIO NO HAPPY PATH
**Acuan Bisnis:** `docs/baru/Alur_Bisnis_Ko_Handy.md` Bagian 3; `List_Pengerjaan_Developer.md` Bagian B (Poin 7–11); `UIUX_Spec_ERP_Duta_Tunggal.md` Bagian 6.4.
**Peran Penguji Terautentikasi:**
- `Admin Pengiriman` (`admin_pengiriman@example.com`)
- `Warehouse Staff` (`warehouse_staff@example.com`)
- `Kasir` (`kasir@example.com`)

---

### Kasus Uji UAT-DO-01 (No Happy Path): Pemblokiran Pembuatan DO dari SO yang Belum Disetujui (Draft / Request Approve)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan aturan mutlak: *"DO HANYA boleh dibuat dari SO yang berstatus disetujui (approved). Barang dan harga mengikuti SO tanpa input ulang."*
- **Kategori Skenario**: **No Happy Path (Invalid Source State Transition)**.
- **Testing yang Dilakukan**:
  1. Siapkan SO draft bernomor `SO-TEST-DRAFT-001` (status: `draft`).
  2. Buka menu **PENJUALAN $\rightarrow$ Perintah Kirim (DO)**, klik **"Buat Perintah Kirim"**.
  3. Pada pilihan Sales Order, cari `SO-TEST-DRAFT-001`.
  4. Jika dicoba bypass melalui URL form Create dengan parameter query `?sales_orders=SO_ID_DRAFT`.
  5. Klik tombol Simpan DO.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - SO yang berstatus `draft` atau `request_approve` **tidak muncul** dalam opsi dropdown pilihan SO.
  - Jika dicoba di-inject secara paksa via parameter, validator [`DeliveryOrderSourceValidator.php:40`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderSourceValidator.php#L40) melempar pesan kesalahan:
    *"Sales Order SO-TEST-DRAFT-001 belum disetujui (status: draft); hanya SO berstatus approved yang dapat dibuatkan DO."*
- **Solusi**:
  Sistem melindungi alur pengiriman dari pesanan yang belum sah.
- **Bukti Pengujian Nyata**:
  - Respon Backend: HTTP 422 `ValidationException` pada field `salesOrders`.
  - Database: Tidak ada record baru yang terbentuk di tabel `delivery_orders`.

---

### Kasus Uji UAT-DO-02: Tampilan Alokasi Multi-Gudang per Produk Berdasarkan Stok Bebas > 0
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan implementasi format Excel Ko Handy: *"Per produk ditampilkan semua gudang yang memiliki stok bebas > 0, user mengisi kuantitas yang diambil per gudang"*.
- **Kategori Skenario**: UI/UX & Data Allocation Accuracy.
- **Testing yang Dilakukan**:
  1. Buat SO `SO-TEST-MULTI-WH` dengan item `PROD-TEST-A` sebanyak 15 pcs.
  2. Kondisi stok fisik:
     - Gudang Utama (A): Stok bebas = 10 pcs.
     - Gudang Kenari (B): Stok bebas = 8 pcs.
     - Gudang Karantina (C): Stok rusak = 20 pcs (tidak boleh muncul).
  3. Buka form Buat DO dan pilih `SO-TEST-MULTI-WH`.
  4. Periksa baris alokasi item `PROD-TEST-A`.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Tabel alokasi menampilkan otomatis Gudang Utama (stok bebas: 10) dan Gudang Kenari (stok bebas: 8). Gudang Karantina tidak ditampilkan karena dikecualikan oleh query [`StockAvailability.php:33`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/StockAvailability.php#L33) (`where is_quarantine = false`).
- **Solusi**:
  Sesuai dengan List Developer Poin 7 & Alur Ko Handy §3.
- **Bukti Pengujian Nyata**:
  - Tampilan Form: Sub-tabel sumber gudang menampilkan kolom: *Nama Gudang, Rak, Stok Bebas Tersedia, dan Qty Ambil*.

---

### Kasus Uji UAT-DO-03 (No Happy Path): Pemblokiran Input Qty Melebihi Stok Bebas Gudang Tertentu
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Mencegah staf gudang memasukkan kuantitas pengambilan yang lebih besar daripada stok bebas fisik pada gudang yang dipilih.
- **Kategori Skenario**: **No Happy Path (Warehouse Stock Overflow)**.
- **Testing yang Dilakukan**:
  1. Pada form DO untuk `PROD-TEST-A`, di baris *Gudang Kenari* (stok bebas: 8 pcs).
  2. Masukkan Qty Ambil sebesar `12 pcs` (melebihi stok bebas sebanyak 4 pcs).
  3. Coba klik tombol Simpan DO.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem memblokir penyimpanan dengan pesan peringatan:
  *"Kuantitas ambil untuk PROD-TEST-A di Gudang Kenari (12 pcs) melebihi stok bebas yang tersedia (8 pcs)."*
- **Solusi**:
  Validasi batas stok bekerja aktif pada tingkat baris gudang (`StockAvailability::assertQuantityAvailable`).
- **Bukti Pengujian Nyata**:
  - Respon Form: Field input diberi highlight merah dengan pesan validasi instan sebelum submit database.

---

### Kasus Uji UAT-DO-04 (No Happy Path): Pemblokiran Input Qty Melebihi Sisa Kuantitas SO
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan total kuantitas DO tidak dapat melebihi sisa pesanan pada SO (mencegah pengiriman berlebih).
- **Kategori Skenario**: **No Happy Path (Over-Delivery Protection)**.
- **Testing yang Dilakukan**:
  1. SO memesan 10 pcs `PROD-TEST-A`. Sebelumnya sudah dibuat DO-01 sebanyak 6 pcs (sisa SO = 4 pcs).
  2. Buat DO kedua dari SO tersebut.
  3. Masukkan Qty Ambil: Gudang Utama = 4 pcs, Gudang Kenari = 3 pcs (Total DO baru = 7 pcs, melebihi sisa SO 4 pcs).
  4. Klik tombol Simpan DO.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*: Sistem menolak penyimpanan dengan pesan:
  *"Total kuantitas DO untuk PROD-TEST-A (7 pcs) melebihi sisa kuantitas pesanan SO (sisa: 4 pcs)."*
- **Solusi**:
  Method `availableQuantityForDelivery()` pada `SaleOrderItem` secara akurat menghitung:
  $$\text{Sisa Qty} = \text{Qty SO} - \sum \text{Qty DO Aktif}$$
- **Bukti Pengujian Nyata**:
  - Kode Sumber: [`SaleOrderItem.php`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Models/SaleOrderItem.php) menghitung kuantitas DO non-batal.

---

### Kasus Uji UAT-DO-05: Pemecahan Otomatis DO Menjadi Multi-DO per Gudang (DO 1-01, DO 1-02)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan bisnis Ko Handy §3 & List Developer B9: *"DO yang mengambil barang dari beberapa gudang dipecah otomatis per gudang (contoh: DO 1-01 dari gudang A, DO 1-02 dari gudang B)"*.
- **Kategori Skenario**: Automatic Splitting & Data Reorganization.
- **Testing yang Dilakukan**:
  1. Siapkan SO dengan pesanan 15 pcs `PROD-TEST-A`.
  2. Input form DO dengan alokasi:
     - Gudang Utama (A): Ambil 10 pcs.
     - Gudang Kenari (B): Ambil 5 pcs.
  3. Nomor dasar DO: `DO-20261026-0005`.
  4. Klik tombol **"Simpan & Proses DO"**.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Sistem tidak membuat 1 DO campur gudang, melainkan mengeksekusi [`DeliveryOrderSplitter::splitByWarehouse`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderSplitter.php#L22).
  - Terbentuk **2 dokumen DO terpisah**:
    1. `DO-20261026-0005-01`: Gudang Utama, Qty 10 pcs.
    2. `DO-20261026-0005-02`: Gudang Kenari, Qty 5 pcs.
  - Muncul notifikasi banner: *"DO diambil dari 2 gudang sehingga dipecah otomatis: DO-20261026-0005-01, DO-20261026-0005-02."*
- **Solusi**:
  Logika pemecahan di `DeliveryOrderSplitter.php` berjalan sempurna secara transaksional.
- **Bukti Pengujian Nyata**:
  - Database: Dua baris terdaftar di `delivery_orders` dengan `warehouse_id` masing-masing gudang yang tepat.

---

### Kasus Uji UAT-DO-06: Isolasi Ongkos Kirim (Shipping Cost) Hanya pada DO Pertama (-01)
- **Peran Penguji Terautentikasi**: `Admin Pengiriman` (`admin_pengiriman@example.com`)
- **Tujuan Pengujian**: Memastikan bahwa ketika DO dipecah menjadi beberapa dokumen gudang, ongkos kirim (`additional_cost`) **TIDAK TERDUPLIKASI** pada DO kedua (-02) atau seterusnya.
- **Kategori Skenario**: Financial Isolation & Anti-Double Billing.
- **Testing yang Dilakukan**:
  1. Buka kembali kedua dokumen hasil pemecahan pada Kasus Uji UAT-DO-05 (di mana SO asal memiliki ongkos kirim Rp 30.000).
  2. Periksa field `additional_cost` pada `DO-20261026-0005-01`.
  3. Periksa field `additional_cost` pada `DO-20261026-0005-02`.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - `DO-20261026-0005-01`: `additional_cost = 30000.00`.
  - `DO-20261026-0005-02`: `additional_cost = 0.00` (atau `NULL`).
  Customer tidak akan ditagih ongkos kirim dua kali saat masing-masing DO dibuatkan faktur.
- **Solusi**:
  Kode [`DeliveryOrderSplitter.php:63`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Services/DeliveryOrderSplitter.php#L63) secara eksplisit mengecualikan duplikasi kolom `additional_cost`:
  `$target = $deliveryOrder->replicate(['additional_cost', 'additional_cost_description']);`
- **Bukti Pengujian Nyata**:
  - Kolom Database: `delivery_orders.additional_cost` bernilai Rp 30.000 hanya pada record DO ke-1.

---

### Kasus Uji UAT-DO-07: Penguncian Stok Bebas Seketika Saat DO Terbentuk
- **Peran Penguji Terautentikasi**: `Warehouse Staff` (`warehouse_staff@example.com`)
- **Tujuan Pengujian**: Memverifikasi aturan Ko Handy §3 & List Developer B10: *"Stok bebas TERKUNCI saat DO dibuat (bukan menunggu Konfirmasi Gudang)"*.
- **Kategori Skenario**: Stock Reservation & Ledger Integrity.
- **Testing yang Dilakukan**:
  1. Cek stok `PROD-TEST-B` di Gudang Utama: Fisik = 50 pcs, Reserved = 0 pcs, Stok Bebas = 50 pcs.
  2. Simpan DO baru untuk produk tersebut sebanyak 15 pcs. Status DO masih baru dibuat (`status = draft` / `waiting_confirmation`).
  3. Segera periksa kembali kartu stok `inventory_stocks` dan layar Cek Stok.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - Kuantitas fisik tetap 50 pcs (barang belum keluar gudang).
  - Kolom `qty_reserved` otomatis bertambah menjadi `15 pcs`.
  - Nilai **Stok Bebas** menjadi $50 - 15 = \mathbf{35\text{ pcs}}$.
  - Percobaan menjual 40 pcs pada SO lain ditolak karena stok bebas hanya 35 pcs.
- **Solusi**:
  Observer `DeliveryOrderObserver` langsung membuat `StockReservation` saat DO di-insert ke database.
- **Bukti Pengujian Nyata**:
  - Record Database: Tabel `stock_reservations` mencatat reservasi 15 pcs dengan `delivery_order_id = ID_DO` dan `inventory_stocks.qty_reserved = 15.00`.

---

### Kasus Uji UAT-DO-08 (No Happy Path): Perebutan Stok Bersamaan (Race Condition Concurrent DO)
- **Peran Penguji Terautentikasi**: Dua sesi Admin Pengiriman (`admin_pengiriman@example.com` & `kasir@example.com`)
- **Tujuan Pengujian**: Menguji keandalan sistem saat 2 user mencoba membuat DO untuk sisa stok bebas yang sama (tersisa 5 pcs, User A minta 5 pcs, User B minta 5 pcs pada milidetik yang sama).
- **Kategori Skenario**: **No Happy Path (Concurrency & Deadlock Prevention)**.
- **Testing yang Dilakukan**:
  1. Siapkan produk `PROD-TEST-C` dengan sisa stok bebas tepat 5 pcs.
  2. Buka dua browser window terpisah (User A dan User B).
  3. Keduanya membuka form DO dengan kuantitas 5 pcs.
  4. Submit kedua form secara serempak.
- **Permasalahan / Bug atau Ketidaksesuaian**:
  *Hasil Pengujian*:
  - User A berhasil membuat DO (stok terkunci menjadi 5 pcs, stok bebas menjadi 0).
  - User B diblokir dengan pesan transaksional:
    *"Delivery Order Gagal Dibuat: Stok bebas untuk PROD-TEST-C di Gudang Utama tidak mencukupi (tersedia 0 pcs, diminta 5 pcs)."*
  - Tidak terjadi stok minus (`qty_available` tidak pernah tembus $< 0$).
- **Solusi**:
  Implementasi `lockForUpdate()` dan database transaction di [`CreateDeliveryOrder.php:26-38`](file:///Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/DeliveryOrderResource/Pages/CreateDeliveryOrder.php#L26-L38) mengunci baris inventori secara serial.
- **Bukti Pengujian Nyata**:
  - Log Transaksi: Rollback bersih dieksekusi untuk request User B tanpa meninggalkan data DO yatim (*orphaned record*).
