# SOP Penggunaan Duta Tunggal ERP per Departemen

Dokumen ini adalah turunan praktis dari panduan role. Fokusnya adalah urutan kerja harian per departemen, sehingga tim bisa langsung memakai aplikasi sesuai proses bisnis.

## Sales

Tujuan departemen ini adalah mengubah kebutuhan pelanggan menjadi transaksi penjualan yang valid.

Role yang biasanya memakai alur ini adalah Sales, Sales Manager, Customer Service, dan pada beberapa proses Finance Sales.

Langkah kerja:

1. Pastikan data Customer tersedia dan benar.
2. Buat Quotation bila perlu penawaran harga.
3. Ajukan approval atau response jika alur persetujuan dipakai.
4. Lanjutkan ke Sale Order saat penawaran disetujui.
5. Koordinasikan Delivery Order dan jadwal pengiriman.
6. Pastikan invoice penjualan dibuat setelah transaksi siap ditagihkan.
7. Pantau penerimaan pembayaran melalui Customer Receipt.

Output yang harus terlihat:

- Quotation tersimpan dan statusnya jelas.
- Sales Order terbentuk dari quotation atau input langsung.
- Delivery Order dan Delivery Schedule sinkron.
- Invoice dan piutang tercatat benar.

## Purchasing

Tujuan departemen ini adalah memastikan barang atau material dibeli dari supplier yang tepat dan diterima sesuai kebutuhan.

Role yang biasanya memakai alur ini adalah Purchasing dan Purchasing Manager.

Langkah kerja:

1. Buat Order Request bila proses pembelian diawali dari permintaan internal.
2. Susun Purchase Order ke supplier.
3. Periksa Quality Control Pembelian jika barang harus diinspeksi.
4. Buat Purchase Receipt saat barang diterima.
5. Buat Purchase Return jika barang tidak sesuai.
6. Koordinasikan invoice pembelian dan pembayaran vendor dengan finance.

Output yang harus terlihat:

- Purchase Order disetujui dan dapat dilacak.
- Penerimaan barang sesuai PO dan QC.
- Retur pembelian hanya dipakai bila ada ketidaksesuaian.

## Gudang

Tujuan departemen ini adalah menjaga stok fisik dan stok sistem tetap sinkron.

Role yang biasanya memakai alur ini adalah Warehouse Staff, Inventory Manager, Admin Inventory, dan Checker.

Langkah kerja:

1. Pantau Inventory Stock untuk posisi stok real-time.
2. Gunakan Stock Movement untuk menelusuri histori stok.
3. Jalankan Stock Transfer untuk perpindahan antar gudang atau rak.
4. Gunakan Stock Adjustment untuk koreksi selisih.
5. Lakukan Stock Opname untuk stok fisik berkala.
6. Selesaikan Warehouse Confirmation sebelum proses gudang dianggap final.

Output yang harus terlihat:

- Mutasi stok tercatat per gudang, rak, dan produk.
- Konfirmasi gudang selesai sebelum barang dianggap bergerak.
- Selisih stok dapat ditelusuri ke adjustment atau opname.

## Manufaktur

Tujuan departemen ini adalah mengubah bahan baku menjadi barang jadi secara terkontrol.

Role yang biasanya memakai alur ini adalah Inventory Manager, Checker, dan user manufaktur yang diberi akses.

Langkah kerja:

1. Siapkan Bill of Material.
2. Susun Production Plan.
3. Buat Manufacturing Order.
4. Keluarkan material melalui Material Issue.
5. Pastikan Warehouse Confirmation untuk material issue selesai.
6. Jalankan Production.
7. Tutup dengan Quality Control Manufacture.

Output yang harus terlihat:

- BOM, plan, order, issue, production, dan QC berurutan.
- Pengeluaran material dan hasil produksi tercatat dalam jurnal dan stok.

## Keuangan Penjualan

Tujuan departemen ini adalah mengelola tagihan pelanggan dan hak tagih perusahaan.

Role yang biasanya memakai alur ini adalah Finance Manager, Accounting, Admin Keuangan, dan Kasir sesuai tugasnya.

Langkah kerja:

1. Review Invoice Penjualan.
2. Catat Account Receivable.
3. Pantau pembayaran pelanggan melalui Customer Receipt.
4. Analisis Aging Report untuk piutang menunggak.
5. Gunakan laporan keuangan penjualan bila perlu ringkasan performa.

Output yang harus terlihat:

- Piutang selalu cocok dengan invoice.
- Pembayaran pelanggan mengurangi saldo outstanding.
- Aging memudahkan tindak lanjut penagihan.

## Keuangan Pembelian

Tujuan departemen ini adalah mengelola kewajiban bayar ke supplier.

Role yang biasanya memakai alur ini adalah Finance Manager, Accounting, dan Admin Keuangan.

Langkah kerja:

1. Review Invoice Pembelian.
2. Bentuk Account Payable dari invoice pembelian.
3. Cocokkan tagihan dengan Purchase Order dan Purchase Receipt.
4. Proses Payment Request atau Vendor Payment sesuai approval.
5. Pantau umur hutang lewat Aging Report.

Output yang harus terlihat:

- Hutang usaha sesuai invoice pembelian.
- Pembayaran vendor tercatat dan terhubung ke akun kas atau bank.

## Pembayaran

Tujuan departemen ini adalah memproses uang masuk dan uang keluar operasional.

Role yang biasanya memakai alur ini adalah Kasir, Admin Keuangan, Finance Manager, dan Accounting.

Langkah kerja:

1. Buat Payment Request bila dana perlu disetujui.
2. Catat Customer Receipt untuk pembayaran pelanggan.
3. Catat Vendor Payment untuk pelunasan supplier.
4. Kelola Deposit bila transaksi memakai uang muka atau titipan.
5. Gunakan Cash Bank Transfer untuk perpindahan antar akun kas/bank.

Output yang harus terlihat:

- Setiap arus kas punya referensi transaksi yang jelas.
- Jurnal kas/bank terbentuk otomatis atau sesuai mekanisme yang dipakai.

## Akuntansi

Tujuan departemen ini adalah menjaga pembukuan dan pelaporan keuangan tetap konsisten.

Role yang biasanya memakai alur ini adalah Accounting dan Finance Manager.

Langkah kerja:

1. Kelola Journal Entry.
2. Gunakan tampilan grouped journal untuk melihat transaksi per sumber.
3. Review AR & AP Management.
4. Jalankan Rekonsiliasi Bank.
5. Pantau Ageing Schedule.
6. Review Voucher Request bila pengeluaran masih menunggu approval.

Output yang harus terlihat:

- Jurnal seimbang dan dapat ditelusuri.
- AR, AP, bank, dan voucher punya status yang bisa diaudit.

## Asset Management

Tujuan departemen ini adalah mengelola aset tetap dari pencatatan sampai penghapusan.

Role yang biasanya memakai alur ini adalah Finance Manager, Accounting, Admin, dan IT Support untuk konfigurasi tertentu.

Langkah kerja:

1. Catat Aset Tetap baru.
2. Pindahkan aset dengan Transfer Aset jika berpindah cabang.
3. Gunakan Disposal Aset saat aset dilepas atau dihapus.
4. Cek nilai buku dan riwayat aset sebelum approval.

Output yang harus terlihat:

- Aset memiliki kode, cabang, dan status yang jelas.
- Perpindahan dan disposal aset meninggalkan jejak audit.

## Master Data

Tujuan departemen ini adalah menjaga referensi dasar sistem tetap rapi dan konsisten.

Role yang biasanya memakai alur ini adalah Admin, IT Support, Inventory Manager, dan role master data tertentu.

Langkah kerja:

1. Kelola Product, Category, dan Unit of Measure.
2. Kelola Rak, Warehouse, Cabang, Customer, dan Supplier.
3. Kelola Chart of Account, Currency, dan Tax Setting.
4. Kelola Vehicle dan Driver untuk kebutuhan pengiriman.

Output yang harus terlihat:

- Data master tidak duplikatif.
- Cabang, gudang, dan produk konsisten dipakai oleh modul transaksi.

## User Roles Management

Tujuan departemen ini adalah mengatur akun dan hak akses sistem.

Role yang biasanya memakai alur ini adalah Super Admin, Admin, dan IT Support.

Langkah kerja:

1. Buat atau ubah User.
2. Atur Role yang sesuai.
3. Verifikasi Permission yang melekat ke role.
4. Pastikan akses user sesuai tanggung jawabnya.

Output yang harus terlihat:

- User memiliki role yang benar.
- Permission tidak berlebihan dan tidak menghambat pekerjaan.

## Laporan Keuangan dan Operasional

Tujuan departemen ini adalah memberi ringkasan manajemen dan analisa data bisnis.

Role yang biasanya memakai alur ini adalah Owner, Super Admin, Finance Manager, Accounting, dan Auditor.

Langkah kerja:

1. Buka Laporan Keuangan untuk neraca, laba rugi, trial balance, buku besar, cash flow, HPP, ageing, dan analisis lanjutan.
2. Buka Laporan Operasional untuk rekap penjualan dan pembelian.
3. Gunakan filter periode, cabang, atau parameter lain sebelum mengekspor.

Output yang harus terlihat:

- Ringkasan manajemen tersedia untuk pengambilan keputusan.
- Auditor dapat menelusuri transaksi tanpa mengubah data.