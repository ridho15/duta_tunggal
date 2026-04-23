# Panduan Pengguna Duta Tunggal ERP per Role

Dokumen ini merangkum analisa role, permission, struktur menu, dan panduan penggunaan aplikasi Duta Tunggal ERP berdasarkan implementasi aktual di kode aplikasi.

## Dasar Analisa

Panduan ini disusun dengan mengacu pada sumber berikut:

- `database/seeders/RoleSeeder.php` sebagai sumber utama role dan pemetaan role ke resource.
- `app/Http/Controllers/HelperController.php` sebagai sumber daftar permission, deskripsi permission, dan deskripsi role.
- Halaman hub Filament di `app/Filament/Pages/*HubPage.php` dan `resources/views/filament/pages/*hub-page.blade.php` untuk memahami struktur menu pengguna.
- Resource Filament pada `app/Filament/Resources` untuk memahami modul operasional yang benar-benar dipakai user.

## Cara Kerja Permission di Aplikasi

Setiap permission dibentuk dengan pola `aksi + resource`, misalnya `view any quotation`, `create sales order`, `approve voucher request`, atau `update status delivery schedule`.

Secara umum, jika sebuah role dipetakan ke satu resource di `RoleSeeder`, maka role tersebut mendapatkan hampir semua aksi pada resource itu, kecuali `delete` dan `force-delete` untuk role yang tidak termasuk role destruktif.

Role yang mendapatkan seluruh permission sistem adalah:

- `Owner`
- `Super Admin`

Role yang mendapatkan hak `delete` dan `force-delete` pada resource yang dipetakan adalah:

- `Owner`
- `Super Admin`
- `Purchasing Manager`
- `Inventory Manager`
- `Finance Manager`

Role `Auditor` bersifat baca-saja tingkat sistem. Role ini hanya diberi permission `view any` untuk semua resource yang terdaftar.

Selain permission role, akses user juga dipengaruhi oleh cakupan data user:

- `manage_type = all` berarti user dapat mengakses lintas cabang.
- Jika bukan `all`, banyak resource otomatis dibatasi ke `cabang_id` user.
- Beberapa proses gudang dan stok juga dibatasi oleh `warehouse_id` user atau gudang pada cabang user.

## Ringkasan Grup Menu dan Hub

Sebagian besar resource tidak tampil langsung di sidebar. Pola aplikasi ini memakai halaman hub sebagai pintu masuk utama setiap kelompok modul.

| Grup Menu | Nama Hub | Isi Utama |
| --- | --- | --- |
| Penjualan | Pusat Penjualan | Quotation, Sale Order, Sales Report |
| Pengiriman | Pusat Pengiriman | Delivery Order, Surat Jalan, Delivery Schedule |
| Pembelian | Pusat Pembelian | Order Request, Purchase Order, QC Pembelian, Purchase Receipt, Purchase Return |
| Keuangan Penjualan | Pusat Keuangan Penjualan | Account Receivable, Invoice Penjualan, Other Sale |
| Keuangan Pembelian | Pusat Keuangan Pembelian | Account Payable, Invoice Pembelian |
| Pembayaran Keuangan | Pusat Pembayaran | Payment Request, Customer Receipt, Vendor Payment, Cash/Bank, Deposit, Transfer |
| Akuntansi Keuangan | Pusat Akuntansi | Journal Entry, Grouped Journal, AR/AP Management, Bank Reconciliation, Ageing, Voucher Request |
| Laporan Keuangan | Laporan Keuangan | Balance Sheet, Profit and Loss, Trial Balance, Buku Besar, Cash Flow, HPP, Ageing, Consolidation |
| Gudang | Pusat Gudang | Stock Transfer, Stock Adjustment, Stock Opname, Return Product, Inventory Stock, Stock Movement, Warehouse Confirmation |
| Manufaktur | Pusat Manufaktur | BOM, Production Plan, Manufacturing Order, Material Issue, Production, QC Manufacture |
| Asset Management | Pusat Manajemen Aset | Asset, Asset Transfer, Asset Disposal |
| Master Data | Pusat Data Master | Product, Category, UOM, Rak, Warehouse, Cabang, Customer, Supplier, COA, Currency, Tax, Vehicle, Driver |
| User Roles Management | Pusat Manajemen User & Role | User, Role, Permission |

## Ringkasan Role dan Cakupan Modul

| Role | Fokus Utama | Modul Inti | Pola Akses |
| --- | --- | --- | --- |
| Owner | Pengawasan penuh bisnis | Semua modul | Semua permission |
| Super Admin | Administrasi penuh sistem | Semua modul | Semua permission |
| Admin | Administrasi umum | User, Role, Permission, Currency, COA, Tax, Cabang, Asset, Journal, Delivery Schedule | Operasional penuh tanpa delete permanen |
| Sales Manager | Pengawasan penjualan | Sales Order, Quotation, Invoice, Customer, Customer Receipt | Operasional dan approval penjualan tanpa delete permanen |
| Sales | Penjualan harian | Sales Order, Quotation, Customer | Operasional penjualan tanpa delete permanen |
| Kasir | Penerimaan pelanggan | Customer Receipt, Customer Receipt Item, Invoice | Operasional kas masuk tanpa delete permanen |
| Inventory Manager | Pengawasan stok dan gudang | Warehouse, Warehouse Confirmation, Inventory Stock, Stock Movement, Stock Transfer, Product, QC, Asset Transfer | Operasional penuh dengan delete permanen |
| Admin Inventory | Admin stok | Warehouse, Inventory Stock, Stock Movement, Product | Operasional gudang terbatas tanpa delete permanen |
| Checker | Pemeriksaan gudang dan QC | Warehouse Confirmation, Quality Control, Inventory Stock | Pemeriksaan dan validasi tanpa delete permanen |
| Finance Manager | Pengawasan keuangan | AP, AR, Vendor Payment, Customer Receipt, Invoice, Deposit, Ageing, Voucher, Asset, Cash/Bank, Journal | Operasional penuh dengan delete permanen |
| Admin Keuangan | Admin keuangan | AP, Vendor Payment, Deposit, Invoice, Voucher, Asset, Cash/Bank, Journal | Operasional keuangan tanpa delete permanen |
| Accounting | Pembukuan dan akuntansi | COA, AP, AR, Deposit, Invoice, Ageing, Asset, Cash/Bank, Journal | Operasional akuntansi tanpa delete permanen |
| Purchasing | Pembelian harian | Purchase Order, Purchase Receipt, Purchase Return | Operasional pembelian tanpa delete permanen |
| Purchasing Manager | Pengawasan pembelian | Purchase Order, Purchase Receipt, Purchase Return, Vendor Payment, Asset | Operasional manajerial dengan delete permanen |
| Warehouse Staff | Operasional gudang | Warehouse, Warehouse Confirmation, Stock Transfer, Inventory Stock | Operasional gudang tanpa delete permanen |
| Delivery Driver | Pengiriman lapangan | Delivery Order, Delivery Order Item, Vehicle, Surat Jalan, Delivery Schedule | Akses pengiriman tanpa delete permanen |
| Customer Service | Layanan pelanggan | Customer, Quotation, Sales Order, Delivery Order, Surat Jalan, Delivery Schedule | Koordinasi front office tanpa delete permanen |
| Auditor | Audit dan peninjauan | Semua modul | View only |
| IT Support | Dukungan teknis | User, Role, Permission, Tax Setting, Currency | Dukungan teknis tanpa delete permanen |

## Permission Khusus yang Perlu Diketahui

Beberapa resource memiliki aksi workflow tambahan di luar CRUD biasa. Ini penting untuk dipahami karena memengaruhi alur kerja antar departemen.

| Resource | Aksi Workflow Khusus | Makna Praktis |
| --- | --- | --- |
| `quotation` | `request-approve`, `approve`, `reject` | Penawaran dapat diajukan untuk approval dan ditolak/disetujui |
| `sales order` | `request`, `response` | Order penjualan dapat diajukan untuk tindak lanjut atau approval |
| `purchase order` | `request`, `response` | PO mendukung alur permintaan dan tanggapan |
| `delivery order` | `request`, `response` | DO bergerak melalui alur koordinasi dan approval |
| `surat jalan` | `request`, `response` | Surat jalan ikut dalam alur pengiriman |
| `stock transfer` | `request`, `response` | Transfer stok harus diproses melalui alur permintaan dan respons |
| `warehouse` | `approve` | Ada approval gudang untuk proses tertentu |
| `delivery schedule` | `update status`, `rekap` | Jadwal pengiriman dapat diubah statusnya dan direkap |
| `return product` | `approve` | Retur produk memerlukan approval |
| `order request` | `approve` | Permintaan pembelian memerlukan approval |
| `customer return` | `qc`, `approve` | Retur pelanggan melalui pemeriksaan mutu dan approval |
| `voucher request` | `submit`, `approve`, `reject`, `cancel` | Permintaan voucher memiliki siklus pengajuan lengkap |

## Alur Bisnis Utama dalam Aplikasi

### 1. Sales to Cash

Alur paling umum pada sisi penjualan adalah:

1. Siapkan `Customer` bila pelanggan belum ada.
2. Buat `Quotation` dan ajukan approval jika dibutuhkan.
3. Lanjutkan ke `Sale Order` setelah penawaran siap diproses.
4. Koordinasikan `Delivery Order` untuk pengiriman barang.
5. Buat `Surat Jalan` sebagai dokumen pengiriman.
6. Lanjutkan ke `Delivery Schedule` untuk penjadwalan dan status kirim.
7. Buat `Invoice Penjualan` dan catat `Account Receivable`.
8. Saat pelanggan membayar, input `Customer Receipt`.

Catatan implementasi penting:

- Delivery Order menjadi pusat proses pengiriman.
- Warehouse Confirmation terkait DO akan menentukan apakah DO dapat lanjut ke status berikutnya.
- Saat status Delivery Schedule berubah menjadi `delivered`, DO terkait dapat diproses menjadi selesai.

### 2. Procure to Pay

Alur umum pembelian adalah:

1. Ajukan `Order Request` bila perusahaan memakai alur permintaan pembelian.
2. Buat `Purchase Order` ke supplier.
3. Lakukan `Quality Control Pembelian` bila barang perlu inspeksi.
4. Proses `Purchase Receipt` saat barang diterima.
5. Jika ada ketidaksesuaian, buat `Purchase Return`.
6. Catat `Invoice Pembelian` dan `Account Payable`.
7. Ajukan `Payment Request` atau proses langsung `Vendor Payment` sesuai kebijakan internal.

### 3. Warehouse and Inventory

Alur gudang dan persediaan yang lazim dipakai adalah:

1. Pantau posisi stok di `Inventory Stock`.
2. Gunakan `Stock Movement` untuk audit mutasi stok.
3. Gunakan `Stock Transfer` untuk perpindahan antar gudang atau lokasi rak.
4. Gunakan `Stock Adjustment` untuk koreksi stok manual.
5. Gunakan `Stock Opname` untuk stok fisik periodik.
6. Gunakan `Warehouse Confirmation` sebagai titik validasi proses keluar-masuk barang.

Catatan implementasi penting:

- Transfer stok tidak boleh mengubah persediaan sebelum disetujui.
- Stock adjustment dan stock opname memakai approval eksplisit.
- Gudang, rak, dan stok sering difilter oleh cabang user.

### 4. Manufacturing

Alur manufaktur yang tercermin di kode adalah:

1. Siapkan `Bill of Material`.
2. Buat `Production Plan`.
3. Buat `Manufacturing Order`.
4. Buat `Material Issue` untuk pengeluaran bahan baku.
5. Selesaikan `Warehouse Confirmation` yang terkait material issue.
6. Jalankan `Production`.
7. Lakukan `QC Manufacture` atas hasil produksi.

Catatan implementasi penting:

- Manufacturing Order tidak boleh lanjut ke produksi sebelum Material Issue selesai dan konfirmasi gudang terkait sudah confirmed.
- Material Issue menjadi sumber konsumsi bahan baku dan jurnal WIP.
- Warehouse Confirmation pada manufaktur adalah bagian kritis dari kontrol stok.

### 5. Asset Management

Alur aset yang ada di aplikasi adalah:

1. Catat aset di `Aset Tetap`.
2. Bila aset berpindah lokasi atau cabang, gunakan `Transfer Aset`.
3. Bila aset dihentikan atau dihapus, gunakan `Disposal Aset`.

### 6. Accounting and Reporting

Alur akuntansi dan pelaporan biasanya mencakup:

1. Review dan koreksi `Journal Entry`.
2. Pantau `AR & AP Management` dan `Ageing Schedule`.
3. Kelola `Voucher Request` bila dipakai untuk pengeluaran berbasis approval.
4. Gunakan `Laporan Keuangan` untuk Neraca, Laba Rugi, Trial Balance, Buku Besar, Cash Flow, HPP, dan laporan analitis lain.

## Panduan Penggunaan per Role

### Owner

Fokus role ini adalah pengawasan bisnis secara menyeluruh. Dalam implementasi saat ini, Owner mendapat seluruh permission sistem.

Menu utama yang paling relevan adalah Dashboard Finance, Laporan Keuangan, Pusat Penjualan, Pusat Pembelian, Pusat Pembayaran, Pusat Akuntansi, Pusat Gudang, Pusat Manufaktur, Pusat Data Master, dan Pusat Manajemen User & Role.

Langkah kerja yang disarankan adalah memulai dari Dashboard Finance untuk melihat ringkasan saldo, penjualan, outstanding, dan stok minimum, lalu membuka laporan keuangan untuk analisa periodik. Owner juga dapat masuk ke modul operasional apa pun bila perlu melakukan supervisi atau intervensi.

Karena aksesnya penuh, gunakan role ini hanya untuk kebutuhan strategis, approval tingkat tinggi, audit keputusan, dan penanganan kasus khusus.

### Super Admin

Super Admin adalah administrator penuh aplikasi. Secara implementasi, role ini juga disinkronkan dengan seluruh permission yang ada di database.

Menu utama role ini sama luasnya dengan Owner, tetapi fokus kerjanya biasanya pada setup sistem, koreksi data, investigasi error operasional, pengelolaan user, dan dukungan lintas departemen.

Gunakan role ini untuk membuat user, mengatur role dan permission, memperbaiki konfigurasi master data, mengecek jurnal, memeriksa transaksi keuangan, dan menelusuri proses gudang atau manufaktur bila ada kasus khusus.

Role ini sebaiknya tidak dipakai untuk transaksi harian biasa jika sudah ada role operasional yang lebih sempit.

### Admin

Admin berfungsi sebagai administrator umum. Berdasarkan mapping saat ini, Admin berfokus pada User, Role, Permission, Currency, Chart of Account, Tax Setting, Cabang, Asset, Journal Entry, dan Delivery Schedule.

Menu utama yang paling relevan adalah Pusat Manajemen User & Role, Pusat Data Master, Pusat Manajemen Aset, Pusat Akuntansi, dan Pusat Pengiriman untuk penjadwalan.

Alur kerja Admin biasanya mencakup pembuatan akun user baru, penetapan role, perawatan data master dasar, pembaruan tarif pajak, pembukaan cabang baru, review jurnal manual, dan bantuan pengaturan jadwal pengiriman.

Admin tidak termasuk role destruktif pada seeder saat ini, sehingga sebaiknya tidak dijadikan role untuk penghapusan data massal.

### Sales Manager

Sales Manager bertugas mengawasi siklus penjualan. Resource inti yang dipetakan adalah Sales Order, Quotation, Invoice, Customer, dan Customer Receipt.

Menu utama yang relevan adalah Pusat Penjualan, Pusat Keuangan Penjualan, Pusat Pembayaran, dan Customer pada Pusat Data Master.

Alur kerja yang disarankan adalah meninjau quotation yang diajukan tim sales, memastikan harga dan syarat sudah benar, mengawasi konversi quotation ke sales order, memantau invoice penjualan, dan melihat status penerimaan pelanggan untuk memastikan arus kas masuk berjalan.

Role ini tepat dipakai untuk monitoring target penjualan, validasi penawaran, dan pengawasan outstanding customer.

### Sales

Sales adalah role operasional penjualan harian. Resource inti yang dipetakan adalah Sales Order, Quotation, dan Customer.

Menu utama yang relevan adalah Pusat Penjualan dan Customer pada Pusat Data Master.

Alur kerja standar Sales adalah memastikan data customer tersedia, membuat quotation, menyesuaikan item, harga, dan pajak, lalu melanjutkan ke sales order saat penawaran disetujui atau siap diproses. Pada alokasi barang, pilih gudang yang benar dan perhatikan ketersediaan stok bebas.

Sales tidak mendapat akses destruktif. Role ini paling cocok untuk pembuatan transaksi penjualan awal dan koordinasi ke Customer Service atau tim pengiriman.

### Kasir

Kasir berfokus pada penerimaan pembayaran pelanggan. Resource inti yang dipetakan adalah Customer Receipt, Customer Receipt Item, dan Invoice.

Menu utama yang relevan adalah Pusat Pembayaran dan Pusat Keuangan Penjualan.

Alur kerja standar Kasir adalah memilih invoice pelanggan yang akan dibayar, menentukan metode pembayaran, memilih akun kas atau bank yang sesuai, lalu menyimpan customer receipt agar jurnal terkait tercatat otomatis. Kasir juga dapat memonitor invoice mana yang masih terbuka sebelum menerima pembayaran.

Gunakan role ini untuk kas masuk operasional harian, bukan untuk pengelolaan penuh AR atau analisa ageing.

### Inventory Manager

Inventory Manager adalah penanggung jawab utama persediaan dan kontrol gudang. Resource yang dipetakan mencakup Warehouse, Warehouse Confirmation, Inventory Stock, Stock Movement, Stock Transfer, Product, Product Category, Rak, Unit of Measure, Product Unit Conversion, Quality Control, dan Asset Transfer.

Menu utama yang relevan adalah Pusat Gudang, Pusat Data Master, dan bila diperlukan Pusat Manajemen Aset.

Alur kerja harian role ini adalah memonitor stok, memvalidasi mutasi, mengawasi transfer antar gudang, memastikan konfirmasi gudang selesai, mengelola struktur produk dan satuan, serta memeriksa quality control. Untuk masalah stok tidak cocok, role ini juga dapat mengarahkan stock adjustment atau stock opname.

Karena termasuk role destruktif, Inventory Manager harus berhati-hati saat menghapus data master atau transaksi stok.

### Admin Inventory

Admin Inventory adalah role admin operasional gudang dengan cakupan lebih sempit. Resource yang dipetakan adalah Warehouse, Inventory Stock, Stock Movement, dan Product.

Menu utama yang relevan adalah Pusat Gudang dan bagian produk pada Pusat Data Master.

Alur kerja role ini adalah memperbarui data barang, memeriksa posisi stok, memantau mutasi, dan membantu administrasi gudang harian tanpa mengambil keputusan approval tingkat tinggi.

Role ini cocok untuk staf back office inventory yang perlu melihat dan memperbarui data, tetapi tidak memegang seluruh kontrol gudang.

### Checker

Checker berfungsi sebagai pemeriksa dan validator. Resource yang dipetakan adalah Warehouse Confirmation, Quality Control, dan Inventory Stock.

Menu utama yang relevan adalah Pusat Gudang dan, bila tersedia dalam proses, modul quality control yang terkait penerimaan atau produksi.

Alur kerja role ini adalah memeriksa kesesuaian barang, menindaklanjuti warehouse confirmation, memastikan hasil QC tercatat, dan memverifikasi stok yang menjadi dasar keputusan gudang atau produksi.

Role ini bersifat kontrol kualitas dan verifikasi, bukan role administrasi transaksi umum.

### Finance Manager

Finance Manager adalah penanggung jawab utama fungsi keuangan. Resource yang dipetakan meliputi Account Payable, Account Receivable, Vendor Payment, Customer Receipt, Invoice, Deposit, Ageing Schedule, Voucher Request, Asset, Asset Depreciation, Asset Disposal, Asset Transfer, Cash Bank Account, Cash Bank Transaction Detail, dan Journal Entry.

Menu utama yang relevan adalah Dashboard Finance, Pusat Keuangan Penjualan, Pusat Keuangan Pembelian, Pusat Pembayaran, Pusat Akuntansi, Pusat Manajemen Aset, dan Laporan Keuangan.

Alur kerja yang disarankan adalah memantau saldo dan ageing, memastikan tagihan vendor dan pelanggan tercatat, mengawasi pembayaran vendor dan penerimaan pelanggan, mengecek mutasi kas dan bank, meninjau jurnal penting, serta mengawasi lifecycle aset tetap.

Role ini termasuk role destruktif. Gunakan dengan disiplin kontrol internal dan otorisasi yang jelas.

### Admin Keuangan

Admin Keuangan adalah eksekutor operasional keuangan. Resource yang dipetakan adalah Account Payable, Vendor Payment, Deposit, Invoice, Voucher Request, Asset, Asset Depreciation, Asset Disposal, Asset Transfer, Cash Bank Account, Cash Bank Transaction Detail, dan Journal Entry.

Menu utama yang relevan adalah Pusat Keuangan Pembelian, Pusat Pembayaran, Pusat Akuntansi, dan Pusat Manajemen Aset.

Alur kerja role ini biasanya mencakup input invoice, pencatatan deposit, pengelolaan pembayaran vendor, pengajuan atau tindak lanjut voucher, pembaruan mutasi kas dan bank, serta pencatatan jurnal yang dibutuhkan operasional.

Role ini mendukung Finance Manager, bukan menggantikannya untuk fungsi pengawasan menyeluruh.

### Accounting

Accounting berfokus pada pembukuan dan konsistensi akuntansi. Resource yang dipetakan meliputi Chart of Account, Account Payable, Account Receivable, Deposit, Invoice, Ageing Schedule, Asset, Asset Depreciation, Asset Disposal, Asset Transfer, Cash Bank Account, Cash Bank Transaction Detail, dan Journal Entry.

Menu utama yang relevan adalah Pusat Akuntansi, Pusat Keuangan Penjualan, Pusat Keuangan Pembelian, Pusat Pembayaran, Pusat Manajemen Aset, dan Laporan Keuangan.

Alur kerja standar Accounting adalah menjaga struktur COA, mereview jurnal, memastikan transaksi AP dan AR tercatat benar, memeriksa ageing, meninjau akun kas dan bank, serta mendukung penutupan periode melalui laporan keuangan.

Role ini tepat untuk tim pembukuan, rekonsiliasi, dan closing periodik.

### Purchasing

Purchasing adalah role operasional pembelian. Resource inti yang dipetakan adalah Purchase Order, Purchase Order Item, Purchase Receipt, Purchase Receipt Item, Purchase Order Biaya, Purchase Order Currency, dan Purchase Return.

Menu utama yang paling relevan adalah Pusat Pembelian dan, bila terkait tagihan, Pusat Keuangan Pembelian untuk koordinasi dengan finance.

Alur kerja standar Purchasing adalah membuat purchase order ke supplier, memantau barang yang datang, mencatat purchase receipt, menambahkan biaya pembelian terkait, dan membuat purchase return jika barang bermasalah atau tidak sesuai.

Role ini cocok untuk buyer atau staf procurement yang fokus pada eksekusi pembelian harian.

### Purchasing Manager

Purchasing Manager berperan sebagai pengawas pembelian dengan kewenangan lebih luas. Resource yang dipetakan adalah Purchase Order, Purchase Order Item, Purchase Receipt, Vendor Payment, Purchase Return, Purchase Order Biaya, dan Asset.

Menu utama yang relevan adalah Pusat Pembelian, Pusat Pembayaran, Pusat Keuangan Pembelian, dan bila diperlukan Pusat Manajemen Aset.

Alur kerja role ini adalah meninjau proses PO, memastikan penerimaan barang sesuai, mengevaluasi retur, mengoordinasikan pembayaran vendor untuk transaksi yang berkaitan dengan pembelian, dan memantau pengadaan aset.

Role ini termasuk role destruktif pada mapping saat ini, sehingga tindakan penghapusan perlu dibatasi ke prosedur yang disetujui perusahaan.

### Warehouse Staff

Warehouse Staff adalah role pelaksana operasional gudang. Resource yang dipetakan adalah Warehouse, Warehouse Confirmation, Stock Transfer, Stock Transfer Item, dan Inventory Stock.

Menu utama yang relevan adalah Pusat Gudang.

Alur kerja role ini adalah memeriksa stok, menyiapkan perpindahan barang, memproses transfer stok, dan menyelesaikan warehouse confirmation untuk proses keluar masuk barang. Role ini sangat penting dalam menjaga data stok tetap sinkron dengan kondisi fisik.

Gunakan role ini untuk kegiatan lapangan gudang sehari-hari, bukan untuk perubahan master data skala besar.

### Delivery Driver

Delivery Driver adalah role pengiriman lapangan. Resource yang dipetakan adalah Delivery Order, Delivery Order Item, Vehicle, Surat Jalan, dan Delivery Schedule.

Menu utama yang relevan adalah Pusat Pengiriman dan data kendaraan di Pusat Data Master bila memang diberikan pada akun tersebut.

Alur kerja role ini adalah melihat tugas pengiriman, membaca detail delivery order, memastikan surat jalan yang benar dibawa, memeriksa jadwal pengiriman, dan mengikuti status pengiriman yang sedang berjalan.

Role ini bersifat operasional lapangan. Fokus utamanya adalah eksekusi pengiriman, bukan pembuatan transaksi penjualan baru.

### Customer Service

Customer Service berfungsi sebagai penghubung pelanggan dan operasional internal. Resource yang dipetakan adalah Customer, Quotation, Sales Order, Delivery Order, Surat Jalan, dan Delivery Schedule.

Menu utama yang relevan adalah Pusat Penjualan, Pusat Pengiriman, dan Customer pada Pusat Data Master.

Alur kerja role ini adalah memastikan permintaan pelanggan diterjemahkan menjadi quotation atau sales order yang benar, mengoordinasikan delivery order dan surat jalan, lalu memantau penjadwalan pengiriman agar pelanggan mendapat kepastian layanan.

Role ini cocok untuk koordinasi end-to-end setelah kebutuhan pelanggan masuk sampai barang dijadwalkan dikirim.

### Auditor

Auditor adalah role baca-saja. Berdasarkan seeder, Auditor hanya menerima permission `view any` untuk semua resource.

Menu utama yang relevan secara praktis adalah seluruh hub yang menampilkan data bisnis, terutama Laporan Keuangan, Pusat Akuntansi, Pusat Pembayaran, Pusat Penjualan, Pusat Pembelian, Pusat Gudang, dan Pusat Manufaktur.

Alur kerja Auditor adalah membuka daftar data, memfilter periode atau cabang, meninjau transaksi, mencocokkan alur antar modul, dan menarik kesimpulan audit tanpa mengubah data transaksi.

Role ini sebaiknya digunakan khusus untuk audit internal, audit eksternal, atau investigasi kepatuhan.

### IT Support

IT Support adalah role dukungan teknis dengan cakupan terbatas. Resource yang dipetakan adalah User, Role, Permission, Tax Setting, dan Currency.

Menu utama yang relevan adalah Pusat Manajemen User & Role dan bagian konfigurasi tertentu di Pusat Data Master.

Alur kerja role ini adalah membantu pembukaan akses user, pengecekan assignment role, validasi permission, dan penyesuaian konfigurasi dasar sistem seperti pajak dan mata uang bila memang menjadi bagian dari tanggung jawab tim teknis.

Role ini tidak dimaksudkan untuk mengelola transaksi bisnis harian seperti penjualan, pembelian, atau keuangan.

## Dashboard dan Monitoring per Kelompok Role

Dashboard Finance memiliki perilaku berbeda tergantung role pengguna.

- `Owner` dan `Super Admin` melihat ringkasan keuangan dan penjualan yang paling lengkap.
- `Accounting` dan `Finance Manager` melihat widget yang berfokus pada saldo, AR/AP, dan ageing.
- `Sales` melihat tabel quotation dan sales order yang belum selesai.
- `Inventory Manager`, `Admin Inventory`, `Purchasing`, dan `Super Admin` melihat stok minimum dan PO belum selesai.

Panduan praktisnya adalah memulai hari kerja dari dashboard bila widget untuk role Anda tersedia, lalu masuk ke hub departemen untuk eksekusi transaksi.

## Rekomendasi Penggunaan Harian

1. Masuk ke hub yang sesuai dengan fungsi kerja Anda, bukan langsung mencari resource satu per satu.
2. Pastikan data master seperti Customer, Supplier, Product, Warehouse, Cabang, COA, dan Tax sudah benar sebelum membuat transaksi.
3. Gunakan alur approval yang tersedia daripada mengubah status secara manual di luar proses.
4. Perhatikan cabang dan gudang aktif saat membuat transaksi, karena banyak query dibatasi oleh cabang user.
5. Untuk role non-manajerial, hindari memakai akun berlevel tinggi seperti Owner atau Super Admin.

## Catatan Penutup

Dokumen ini menggambarkan desain akses yang saat ini ditanamkan di seeder dan resource aplikasi. Jika perusahaan menambahkan permission langsung ke user atau mengubah role mapping di database produksi, maka perilaku aktual akun tertentu bisa lebih sempit atau lebih luas dari panduan ini.

Untuk kebutuhan audit hak akses, selalu bandingkan panduan ini dengan data `roles`, `permissions`, `role_has_permissions`, `model_has_roles`, dan `model_has_permissions` di environment yang sedang dipakai.

## Dokumen Lanjutan

- [SOP penggunaan per departemen](SOP_PENGGUNAAN_DUTA_TUNGGAL_PER_DEPARTEMEN.md)
- [Audit referensi role legacy](AUDIT_REFERENSI_ROLE_LEGACY_DUTA_TUNGGAL.md)