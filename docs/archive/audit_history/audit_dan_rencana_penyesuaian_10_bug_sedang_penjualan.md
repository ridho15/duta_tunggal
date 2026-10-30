# AUDIT, REVIEW, DAN TAHAPAN PENYESUAIAN 10 BUG SEDANG UAT MODUL PENJUALAN
**PT DUTA TUNGGAL ERP**
*Dokumen Hasil Audit Teknis & Rencana Tindak Lanjut*

---

## 1. PENDAHULUAN
Dokumen ini disusun sebagai hasil audit investigasi menyeluruh dan evaluasi teknis terhadap **10 Bug Kategori Sedang (*Medium Severity*)** yang ditemukan selama pengujian UAT alur penjualan:
`Quotation (QO) > Sales Order (SO) > Delivery Order (DO) > Surat Jalan (SJ) > Jadwal Pengiriman (SCH) > Invoice (INV) > Penerimaan Pembayaran (Customer Receipt)`.

Setiap butir temuan telah diperiksa langsung ke baris kode program, skema database, controller API, antarmuka React/Filament, serta template PDF cetak terkait.

---

## 2. MATRIKS AUDIT & INVESTIGASI 10 BUG SEDANG

| No | Poin Masalah | Lokasi Komponen / File Utama | Akar Masalah Teknis (*Root Cause*) | Solusi & Penyesuaian yang Diterapkan |
|---|---|---|---|---|
| **1** | **Data Quotation tidak terbawa ke SO**<br>• Tempo pembayaran kosong<br>• Mata uang kosong (-- Mata Uang --)<br>• Alamat kirim kosong<br>• Catatan tidak tersalin | • `app/Filament/Resources/QuotationResource.php`<br>• `app/Http/Controllers/Api/SaleOrderApiController.php`<br>• `resources/js/components/SaleOrderForm/SaleOrderApp.tsx`<br>• Tabel `sale_orders` | 1. `SaleOrder::create` di `QuotationResource` tidak menyertakan kolom `shipped_to`.<br>2. Fallback `tempo_pembayaran` dari data customer tidak dipasang jika tempo quotation null.<br>3. Tabel database `sale_orders` belum memiliki kolom `notes`.<br>4. Di React `SaleOrderApp.tsx`, `currency_id` bernilai null tanpa fallback `default_currency_id` sehingga menampilkan placeholder "-- Mata Uang --". | • Tambah kolom `notes` (text, nullable) di tabel `sale_orders`.<br>• Salin `shipped_to` (fallback ke customer address), `tempo_pembayaran` (fallback ke tempo kredit customer / 30 hari), dan `notes`.<br>• Pastikan API & React form selalu menginisialisasi `currency_id` dengan default IDR. |
| **2** | **Angka di modal konversi SO melonjak 100x lipat**<br>• PPN: Rp1.815.583 (seharusnya Rp18.155,83)<br>• Subtotal: Rp18.320.883 (seharusnya Rp183.208,83) | • `app/Filament/Resources/QuotationResource.php`<br>• `app/Filament/Resources/QuotationResource/Pages/ViewQuotation.php`<br>• `app/Providers/AppServiceProvider.php` | 1. Input `subtotal` dan `tax_nominal` bersifat `readOnly` tetapi dipasangi macro `indonesianMoney()` dengan mask interaktif `$money($input, ',', '.', 2)`.<br>2. Delimiter (ribuan) dan separator (desimal) pada mask Alpine terbalik (koma ribuan, titik desimal).<br>3. Saat menerima string yang sudah terformat (`183.208,83`), mask menganggap digit numeriknya bulat tanpa desimal sehingga melonjak 100x lipat. | • Pada modal konversi SO, lepaskan macro mask `$money` untuk input yang bersifat read-only / preview.<br>• Gunakan `number_format` 2 desimal murni yang stabil.<br>• Standarisasi konfigurasi macro `indonesianMoney` di `AppServiceProvider.php`. |
| **3** | **Ringkasan SO tidak terupdate & status keliru loncat ke Completed**<br>• Kirim 12 dari 20, SO tetap tulis "Terkirim 0 \| Sisa 20"<br>• Status SO prematur "completed" | • `app/Models/SaleOrderItem.php`<br>• `app/Models/SaleOrder.php`<br>• `app/Filament/Resources/SaleOrderResource.php`<br>• `app/Observers/DeliveryOrderObserver.php` | 1. Ringkasan SO hanya membaca kolom fisik `delivered_quantity` di tabel `sale_order_items`. Kolom ini hanya terupdate saat DO berstatus `sent` / `completed`.<br>2. Ketika DO tertahan di jadwal atau invoice dibuat duluan, `delivered_quantity` tetap 0.<br>3. Status SO sebelumnya diubah paksa menjadi `completed` oleh generator invoice tanpa memeriksa kelengkapan fisik pengiriman. | • Buat perhitungan kuantitas terkirim dan sisa kuantitas di model `SaleOrderItem` dan infolist SO dihitung secara dinamis dari relasi item DO yang aktif.<br>• Kunci status SO tetap `partially_delivered` (Dikirim Sebagian) sampai total terkirim mencapai kuantitas pesanan. |
| **4** | **Quotation approved masih bebas diubah/dihapus, kedaluwarsa tetap approved, created_by kosong** | • `app/Filament/Resources/QuotationResource.php`<br>• `app/Filament/Resources/QuotationResource/Pages/CreateQuotation.php`<br>• `app/Models/Quotation.php` | 1. `EditAction` dan `DeleteAction` tidak diberi pembatas status `visible(fn ($record) => in_array($record->status, ['draft', 'reject']))`.<br>2. Model dan form create Quotation tidak mengisi `created_by = Auth::id()`.<br>3. Tidak ada proteksi kedaluwarsa saat tanggal `valid_until` terlewati. | • Sembunyikan tombol Edit, Hapus, dan Bulk Delete jika Quotation berstatus selain `draft` dan `reject`.<br>• Tambahkan boot hook di model `Quotation` untuk otomatis mencatat `created_by = Auth::id()`.<br>• Tandai badge merah "Kedaluwarsa" dan nonaktifkan action "Buat Sales Order" jika tanggal sekarang > `valid_until`. |
| **5** | **Pemilihan SO di DO tidak dibatasi & tampilan belum rapi**<br>• SO selesai masih bisa dipilih<br>• Bisa gabung SO beda customer / beda alamat<br>• Status tampil bahasa Inggris mentah | • `app/Filament/Resources/DeliveryOrderResource.php` | 1. Query opsi SO belum memfilter secara ketat SO yang sisa kirimnya sudah 0.<br>2. Validasi form hanya mengecek `customer_id`, belum mengecek kesamaan alamat pengiriman `shipped_to`.<br>3. Kolom status DO memakai `Str::upper($state)` atau string mentah bahasa Inggris. | • Kunci pilihan SO hanya untuk SO berstatus `approved` atau `partially_delivered` dengan sisa kirim > 0.<br>• Tambahkan validasi kesamaan customer DAN kesamaan alamat kirim `shipped_to`.<br>• Terjemahkan seluruh status DO ke Bahasa Indonesia resmi (*Draf, Menunggu Stok, Disetujui, Dikirim Sebagian, Sedang Dikirim, Selesai, Ditolak*). |
| **6** | **Surat Jalan belum layak cetak & belum terkunci**<br>• Dokumen cetak tanpa item lengkap, tanpa driver & kendaraan<br>• Tanpa tanda tangan penerima yang sah<br>• Kolom "Terbit" kosong, dokumen bebas diubah/dihapus | • `resources/views/pdf/surat-jalan.blade.php`<br>• `app/Http/Controllers/PdfPreviewController.php`<br>• `app/Filament/Resources/SuratJalanResource.php` | 1. Template cetak PDF belum menampilkan info pengemudi, plat nomor armada, dan format tanda tangan formal 4 pihak.<br>2. Relasi eager loading di controller memuat relasi salah (`deliveryOrder.customer`).<br>3. Kolom status di tabel memakai `IconColumn` bertipe boolean padahal nilai di database berupa integer status.<br>4. Tombol edit/hapus tidak dikunci saat status sudah terbit. | • Perbarui template PDF Surat Jalan: Kop Duta Tunggal, No SJ, DO, SO, Driver, Plat Kendaraan, Tabel Barang (SKU, Nama, Qty, Satuan, Keterangan), dan 4 kolom tanda tangan (Gudang, Driver, Penerima + Cap, Mengetahui).<br>• Kunci edit dan hapus jika SJ sudah terbit (`status == 1`).<br>• Tampilkan badge status Terbit yang jelas di tabel. |
| **7** | **Kontrol penerimaan uang customer lemah**<br>• Cabang otomatis terisi cabang lain sebelum invoice dipilih<br>• Akun induk kas `1110` bisa dipilih<br>• Overpayment terpotong diam-diam<br>• Kolom "Penyesuaian Sisa" tanpa approval | • `app/Filament/Resources/CustomerReceiptResource.php`<br>• `resources/views/components/customer-receipt-invoice-table.blade.php`<br>• `resources/views/components/customer-receipt-javascript-init.blade.php` | 1. Event `afterStateUpdated` customer langsung mengisi `cabang_id` form dengan cabang customer.<br>2. Query COA tidak mengecek `is_parent = false`, sehingga akun teratas `1110 KAS DAN SETARA KAS` otomatis terpilih.<br>3. Script JavaScript langsung memotong angka lebih tanpa opsi pencatatan deposit.<br>4. Kolom Penyesuaian Sisa dapat digunakan siapa saja tanpa otorisasi. | • Sinkronkan cabang penerimaan uang otomatis mengikuti cabang invoice yang dipilih.<br>• Filter daftar akun kas/bank hanya untuk akun detail (leaf account) non-induk.<br>• Berikan dialog/notifikasi jelas jika nominal bayar melebihi tagihan dengan opsi simpan ke Saldo Deposit Customer.<br>• Batasi akses Penyesuaian Sisa (write-off) hanya untuk user dengan hak otorisasi/approval manajer. |
| **8** | **Master driver dan kendaraan kosong tapi wajib di jadwal pengiriman**<br>• Form memblokir pengiriman jika master kosong<br>• Alur pengiriman berhenti tanpa panduan | • `app/Filament/Resources/DeliveryScheduleResource.php` | 1. Field `driver_id` dan `vehicle_id` diberi validasi `required` tanpa deteksi kekosongan master data.<br>2. Pengguna tidak mengetahui bahwa terdapat opsi metode pengiriman "Ekspedisi" yang tidak membutuhkan armada internal. | • Tampilkan banner panduan jika master driver/kendaraan masih kosong dengan tautan langsung (Quick Link) untuk mengisi master data.<br>• Saat metode "Ekspedisi" dipilih, sembunyikan driver & kendaraan internal dan wajibkan input Nama Ekspedisi/Kurir & Nomor Resi.<br>• Sediakan tombol tambah cepat (*Quick Create*) pada dropdown bila memiliki izin. |
| **9** | **Laporan penjualan belum memadai**<br>• Hanya berbasis SO<br>• Filter status tidak sinkron dengan sistem<br>• Tanpa data HPP dan margin keuntungan | • `app/Filament/Pages/SalesReportPage.php`<br>• `app/Services/Reports/SalesReportService.php`<br>• `app/Exports/SalesReportExport.php` | 1. Kueri laporan hanya membaca tabel `sale_orders`, belum mengakomodasi penjualan berbasis Invoice (akrual).<br>2. Opsi filter status meng-hardcode nilai yang tidak ada di enum SO (`confirmed`, `processing`).<br>3. Perhitungan laporan belum menyertakan estimasi HPP barang dari pergerakan stok. | • Sediakan filter pilihan basis laporan: **Berbasis Sales Order** atau **Berbasis Sales Invoice**.<br>• Perbaiki pilihan filter status agar identik dengan database: *Draf, Menunggu Persetujuan, Disetujui, Dikirim Sebagian, Selesai, Dibatalkan*.<br>• Tambahkan kolom analitis: **HPP**, **Laba Kotor (Gross Margin)**, **Persentase Margin (%)**, dan **Status Pembayaran**.<br>• Perbarui template Export Excel & PDF. |
| **10** | **Tampilan nilai di invoice membingungkan**<br>• Item invoice menulis "Price Rp8.687 x 20 = Rp183.208,83"<br>• Diskon 5% dan PPN tidak ditampilkan per baris | • `app/Filament/Resources/SalesInvoiceResource.php`<br>• `resources/views/pdf/sale-order-invoice.blade.php` | 1. Skema repeater item invoice hanya menampilkan kolom `Product`, `Quantity`, `Price`, dan `Total`.<br>2. Nilai `Total` langsung memuat angka bersih setelah diskon dan PPN, sehingga perhitungan perkalian dasar tampak salah di mata pembaca. | • Lengkapi baris tabel item invoice dengan kolom terperinci: **Harga Satuan**, **Qty**, **Subtotal Kotor**, **Diskon (Rp/%)**, **DPP**, **PPN (11%)**, dan **Total Akhir**.<br>• Pastikan template cetak invoice menyajikan perincian yang sama persis agar customer dapat mencocokkan perhitungan pajak secara transparan. |

---

## 3. TAHAP-TAHAPAN UPDATE DAN PENYESUAIAN (ROADMAP EKSEKUSI)

Untuk memastikan implementasi berjalan aman, terstruktur, dan tidak mengganggu alur transaksi yang sudah stabil, pekerjaan dibagi ke dalam **5 Fase Berurutan**:

### **Fase 1: Alur Data Quotation ke Sales Order & Format Nominal (Bug 1 & 2)**
- **Langkah 1.1**: Buat migrasi penambahan kolom `notes` (`text`, `nullable`) pada tabel `sale_orders`.
- **Langkah 1.2**: Update action `create_sale_order` pada `QuotationResource.php`:
  - Salin `shipped_to` dari quotation atau alamat customer.
  - Salin `tempo_pembayaran` dengan fallback ke tempo customer jika kosong.
  - Salin `notes` penawaran ke sales order.
  - Salin `currency_id` dan nilai tukar.
- **Langkah 1.3**: Perbaiki inisialisasi input `subtotal` dan `tax_nominal` di modal `QuotationResource.php` dan `ViewQuotation.php`:
  - Lepas macro `$money` mask interaktif pada input read-only.
  - Tampilkan sebagai angka 2 desimal standar yang presisi.
- **Langkah 1.4**: Update `SaleOrderApiController.php` method `getQuotation()` dan `resources/js/components/SaleOrderForm/SaleOrderApp.tsx` agar selalu memetakan mata uang default dan menyalin catatan penawaran.

### **Fase 2: Lifecycle Quotation & Tracking Realtime Sales Order (Bug 3 & 4)**
- **Langkah 2.1**: Kunci Quotation yang sudah disetujui di `QuotationResource.php`:
  - Sembunyikan `EditAction`, `DeleteAction`, dan `DeleteBulkAction` jika status bukan `draft` atau `reject`.
  - Pasang validasi batas waktu: jika tanggal sekarang > `valid_until`, tampilkan status *"Kedaluwarsa"* dan nonaktifkan action "Buat Sales Order".
- **Langkah 2.2**: Tambahkan hook otomatis di model `Quotation` untuk mencatat `created_by = Auth::id()`.
- **Langkah 2.3**: Update model `SaleOrderItem.php` dan `SaleOrderResource.php`:
  - Buat perhitungan ringkasan terkirim dan sisa barang dihitung secara dinamis dari relasi DO yang valid (`sent`, `received`, `completed`).
  - Tampilkan rincian real-time: Total Pesanan: 20 | Terkirim: 12 | Sisa: 8 | Status: Dikirim Sebagian (*partially_delivered*).

### **Fase 3: Kontrol Kualitas Delivery Order & Standardisasi Surat Jalan Cetak (Bug 5 & 6)**
- **Langkah 3.1**: Perketat pemilihan SO pada `DeliveryOrderResource.php`:
  - Filter hanya SO berstatus `approved` atau `partially_delivered` dengan sisa kuantitas kirim > 0.
  - Tambahkan validasi: semua SO yang digabung dalam satu DO wajib berasal dari customer yang sama DAN memiliki alamat pengiriman (`shipped_to`) yang identik.
  - Perbaiki label dan terjemahkan seluruh status DO ke Bahasa Indonesia.
- **Langkah 3.2**: Rombak template cetak PDF `resources/views/pdf/surat-jalan.blade.php`:
  - Cantumkan informasi lengkap: No SJ, No DO, No SO, Tanggal, Customer, Alamat Pengiriman, Driver, Nomor Plat Kendaraan / Ekspedisi.
  - Tampilkan tabel logistik resmi: No, SKU, Nama Barang, Qty, Satuan (UOM), Keterangan.
  - Tambahkan 4 kolom tanda tangan: Yang Menyerahkan (Gudang), Pengemudi, Penerima Barang (+ Cap & Tgl), Mengetahui.
- **Langkah 3.3**: Kunci form edit dan delete Surat Jalan pada `SuratJalanResource.php` setelah diterbitkan (`status == 1`).

### **Fase 4: Pengendalian Internal Penerimaan Kas & Fleksibilitas Logistik (Bug 7 & 8)**
- **Langkah 4.1**: Perbaiki pengendalian di `CustomerReceiptResource.php` dan komponen JavaScript:
  - Cabang penerimaan uang otomatis sinkron mengikuti cabang invoice yang dipilih user.
  - Filter daftar COA kas/bank hanya untuk akun leaf non-parent (`where('is_parent', false)`), memblokir pemilihan akun induk `1110`.
  - Berikan dialog konfirmasi/peringatan jika nominal yang diinput melebihi sisa tagihan invoice.
  - Batasi input "Penyesuaian Sisa" (write-off AR) hanya bagi user dengan wewenang/approval khusus.
- **Langkah 4.2**: Perbarui `DeliveryScheduleResource.php`:
  - Sediakan banner peringatan dan quick link ke Master Driver & Kendaraan jika data masih kosong.
  - Sempurnakan metode pengiriman "Ekspedisi" tanpa mewajibkan driver internal.

### **Fase 5: Transparansi Rincian Nilai Invoice & Modernisasi Laporan Penjualan (Bug 9 & 10)**
- **Langkah 5.1**: Perbarui repeater item dan infolist di `SalesInvoiceResource.php`:
  - Tampilkan rincian transparan per baris: Harga Satuan, Kuantitas, Subtotal Kotor, Diskon (%), DPP, PPN (11%), dan Total Akhir.
  - Sinkronkan template cetak PDF invoice (`resources/views/pdf/sale-order-invoice.blade.php`).
- **Langkah 5.2**: Kembangkan `SalesReportPage.php` dan `SalesReportService.php`:
  - Sediakan toggle filter basis data: Berbasis Sales Order vs Berbasis Sales Invoice.
  - Selaraskan filter status dengan database.
  - Tambahkan kolom analisis finansial: Total Penjualan, Estimasi HPP, Laba Kotor (Gross Margin), Margin %, dan Status Pembayaran.
  - Perbarui ekspor Excel dan cetak PDF laporan.

---

## 4. METODE VERIFIKASI & PENGUJIAN
Setelah seluruh fase selesai diimplementasikan, verifikasi menyeluruh akan dilakukan melalui:
1. **Automated Feature Test**: Menjalankan test suite baru `tests/Feature/SalesUatMediumBugVerificationTest.php` yang mencakup 10 skenario pengujian bug sedang.
2. **Regression Test**: Menjalankan kembali seluruh test suite bug kritis sebelumnya (`tests/Feature/SalesUatCriticalBugVerificationTest.php`) untuk memastikan **nol regresi**.
3. **End-to-End Simulation**: Melakukan simulasi alur lengkap transaksi mulai dari penawaran dengan diskon & PPN, pembuatan SO, pengiriman sebagian via DO dan Surat Jalan, hingga penagihan invoice bertahap dan pelunasan kas.
