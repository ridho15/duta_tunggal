# Panduan Lengkap Penggunaan Aplikasi Duta-Tunggal ERP

## Pendahuluan

Aplikasi Duta-Tunggal ERP adalah sistem Enterprise Resource Planning (ERP) yang terintegrasi untuk mengelola operasi bisnis perusahaan manufaktur. Sistem ini mencakup modul Procurement, Manufacturing, Sales, Inventory, Accounting, dan Reporting. Panduan ini menjelaskan flow penggunaan aplikasi dari awal hingga akhir, mulai dari setup awal hingga penutupan periode.

## 1. Setup Awal Aplikasi

### 1.1 Instalasi dan Konfigurasi Sistem
- **Instalasi Laravel Framework**: Pastikan server memenuhi persyaratan Laravel (PHP 8.1+, Composer, Node.js, dll.).
- **Konfigurasi Database**: Setup database MySQL/PostgreSQL dan jalankan migrasi dengan `php artisan migrate`.
- **Konfigurasi Environment**: Atur file `.env` dengan database connection, mail settings, dan konfigurasi lainnya.
- **Install Dependencies**: Jalankan `composer install` dan `npm install`.
- **Build Assets**: Jalankan `npm run build` untuk frontend assets.
- **Generate Key**: Jalankan `php artisan key:generate`.
- **Seed Database**: Jalankan `php artisan db:seed` untuk data awal seperti roles, permissions, dan chart of accounts.

### 1.2 Setup Perusahaan dan Organisasi
- **Buat Cabang (Branch)**: Akses menu Cabang untuk membuat cabang perusahaan dengan detail alamat, telepon, dan pengaturan pajak.
- **Setup User dan Roles**: 
  - Buat user dengan email dan password.
  - Assign roles seperti Admin, Manager, Staff Procurement, dll.
  - Konfigurasi permissions untuk setiap role (view, create, update, delete pada modul tertentu).
- **Konfigurasi Chart of Accounts (COA)**: 
  - Setup akun-akun seperti Persediaan Bahan Baku (1140.01), Persediaan Barang Dalam Proses (1140.02), Persediaan Barang Jadi (1140.03), dll.
  - Pastikan COA terintegrasi dengan produk dan transaksi.

### 1.3 Setup Master Data
- **Produk dan Kategori**: Buat kategori produk dan produk dengan detail SKU, harga, unit of measure (UOM), dan COA inventory.
- **Supplier dan Customer**: Daftarkan supplier untuk procurement dan customer untuk sales.
- **Warehouse dan Rak**: Setup gudang dan rak untuk inventory management.
- **Unit of Measure (UOM)**: Buat satuan seperti pcs, kg, liter, dll.
- **Bill of Material (BOM)**: Untuk produk manufaktur, buat BOM yang mendefinisikan bahan baku, jumlah, dan biaya.

## 2. Operasi Harian

### 2.1 Procurement Flow (Pengadaan)
1. **Buat Purchase Order (PO)**:
   - Pilih supplier dan produk yang dibutuhkan.
   - Tentukan quantity, harga, dan tanggal pengiriman.
   - Approve PO jika diperlukan berdasarkan role.
2. **Terima Barang (Purchase Receipt)**:
   - Buat Purchase Receipt berdasarkan PO.
   - Catat quantity diterima dan kondisi barang.
   - Sistem otomatis update inventory dan buat journal entry untuk persediaan.
3. **Pembayaran Supplier**:
   - Buat Vendor Payment berdasarkan invoice dari supplier.
   - Sistem buat journal entry untuk hutang dan kas/bank.

### 2.2 Manufacturing Flow (Produksi)
1. **Buat Production Plan**:
   - Pilih produk dan BOM.
   - Tentukan quantity produksi dan jadwal.
2. **Buat Manufacturing Order (MO)**:
   - Generate MO dari Production Plan.
   - Sistem hitung kebutuhan bahan baku.
3. **Material Issue**:
   - Issue bahan baku dari inventory ke MO.
   - Sistem kurangi stock bahan baku dan buat journal entry (Dr. WIP, Cr. Raw Material).
4. **Production Completion**:
   - Catat penyelesaian produksi.
   - Sistem transfer dari WIP ke Finished Goods (Dr. Finished Goods, Cr. WIP).
5. **Finished Goods Completion**:
   - Pindahkan produk jadi ke inventory.
   - Sistem update stock dan journal entry jika diperlukan.

### 2.3 Sales Flow (Penjualan)
1. **Buat Sales Order (SO)**:
   - Pilih customer dan produk.
   - Tentukan quantity, harga, dan tanggal pengiriman.
2. **Buat Delivery Order (DO)**:
   - Generate DO dari SO.
   - Kurangi stock inventory.
3. **Buat Invoice**:
   - Buat invoice berdasarkan DO.
   - Sistem buat journal entry untuk piutang (Dr. Piutang, Cr. Penjualan).
4. **Pembayaran Customer**:
   - Terima pembayaran dari customer.
   - Sistem buat journal entry (Dr. Kas/Bank, Cr. Piutang).

### 2.4 Inventory Management
- **Stock Movement**: Monitor pergerakan stock melalui purchase, sales, transfer, dan adjustment.
- **Stock Transfer**: Pindah stock antar warehouse atau rak.
- **Stock Adjustment**: Koreksi stock jika ada selisih fisik.

### 2.5 Accounting dan Journal Entries
- **Otomatis Journal**: Sistem buat journal entries otomatis untuk transaksi procurement, manufacturing, sales.
- **Manual Journal**: Untuk transaksi lain seperti biaya operasional.
- **Approval Workflow**: Journal memerlukan approval berdasarkan role.

## 3. Reporting dan Analisis

### 3.1 Laporan Operasional
- **Inventory Report**: Stock per produk, warehouse, dan rak.
- **Sales Report**: Penjualan per customer, produk, periode.
- **Purchase Report**: Pembelian per supplier, produk.
- **Production Report**: Efisiensi produksi, biaya per unit.

### 3.2 Laporan Keuangan
- **Balance Sheet**: Neraca per periode.
- **Profit & Loss**: Laporan laba rugi.
- **Cash Flow**: Arus kas.
- **Journal Ledger**: Detail journal entries per COA.

### 3.3 Dashboard
- **Real-time Dashboard**: Monitor KPI seperti stock level, outstanding payments, production status.

## 4. Penutupan Periode

### 4.1 Penutupan Bulanan/Tahunan
- **Inventory Valuation**: Hitung nilai inventory akhir periode.
- **Accrual Adjustments**: Sesuaikan biaya yang belum tercatat.
- **Depreciation**: Hitung penyusutan aset.
- **Tax Calculation**: Hitung pajak berdasarkan transaksi.

### 4.2 Audit dan Backup
- **Audit Trail**: Review log aktivitas user.
- **Database Backup**: Backup data sebelum penutupan.
- **Financial Close**: Finalisasi laporan keuangan.

### 4.3 Next Period Preparation
- **Carry Forward Balances**: Pindah saldo ke periode berikutnya.
- **Budget Planning**: Setup budget untuk periode baru.
- **System Maintenance**: Update aplikasi dan performa.

## 5. Troubleshooting dan Best Practices

### 5.1 Common Issues
- **Memory Exhaustion**: Tingkatkan memory_limit PHP untuk operasi besar.
- **Permission Errors**: Pastikan user memiliki role yang tepat.
- **Data Inconsistency**: Jalankan `php artisan db:seed --class=ZeroBalanceSheetSeeder` untuk reset balance.

### 5.2 Best Practices
- **Regular Backup**: Backup database harian.
- **User Training**: Latih user pada flow operasional.
- **Approval Workflow**: Gunakan approval untuk transaksi besar.
- **Data Validation**: Validasi input untuk menghindari error.

## Kesimpulan

Flow penggunaan aplikasi Duta-Tunggal ERP dimulai dari setup awal untuk mempersiapkan sistem, dilanjutkan dengan operasi harian procurement, manufacturing, dan sales, kemudian reporting untuk analisis, dan diakhiri dengan penutupan periode untuk finalisasi keuangan. Dengan mengikuti panduan ini, pengguna dapat mengoptimalkan penggunaan ERP untuk efisiensi bisnis.</content>
<parameter name="filePath">/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/APPLICATION_USAGE_GUIDE.md