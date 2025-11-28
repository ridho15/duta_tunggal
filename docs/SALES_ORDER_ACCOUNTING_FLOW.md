# Tahapan Sales Order & Jurnal Terkait

_Diperbarui: 16 November 2025_

## Ringkasan Eksekutif

Alur penjualan standar terdiri dari: **Quotation → Sales Order → Delivery Order → Invoice Penjualan → Customer Receipt**. Berdasarkan keputusan terbaru, persediaan dianggap keluar ketika **Delivery Order** diposting. Untuk itu sistem membutuhkan akun baru **1140.20 – Barang Terkirim** sebagai penampung sementara antara gudang dan pengakuan HPP.

Tujuan utama:

1. Transparan terkait barang yang sudah keluar gudang tetapi belum diakui sebagai HPP.
2. Memastikan jurnal otomatis mengikuti tahapan bisnis tanpa asumsi manual.
3. Menjaga konsistensi laporan persediaan dan laba rugi.
4. Menegaskan bahwa nilai yang dipakai pada jurnal Delivery Order selalu **harga pokok** produk, sehingga input Harga Jual dan PPN di form DO murni informasi bagi tim sales/finance.

## Detail Tahapan & Jurnal

| Tahap | Aktivitas Bisnis | Dampak Akuntansi | Jurnal Otomatis |
| --- | --- | --- | --- |
| Quotation | Negosiasi harga dan syarat | Tidak ada | – |
| Sales Order | Reservasi stok & komitmen pelanggan | Tidak ada (status logistik saja) | – |
| **Delivery Order** | Barang keluar dari gudang dan menunggu konfirmasi penerimaan | Mulai proses akuntansi persediaan | **Dr 1140.20 Barang Terkirim**<br>**Cr 1140.xx Persediaan Barang Dagang** |
| Invoice Penjualan | Penjualan diakui secara resmi | Pengakuan pendapatan, PPN keluaran, dan pelepasan “Barang Terkirim” | **Dr 1120 Piutang Dagang (nilai tagihan)**<br>**Cr 4100.x Penjualan (nilai sebelum pajak)**<br>**Cr 2140.xx PPn Keluaran**<br>**Cr 4200.xx Pendapatan Biaya Lain/Pengiriman**<br>**Dr 5100.10 HPP Barang Dagang**<br>**Cr 1140.20 Barang Terkirim** |
| Customer Receipt | Pelanggan membayar tagihan | Realisasi kas/biaya titipan & pelunasan piutang | **Jika dana langsung masuk Kas/Bank:**<br>**Dr 111x Kas/Bank**<br>**Cr 1120 Piutang Dagang**<br><br>**Jika dana berasal dari Deposit/Hutang Titipan Konsumen:**<br>**Dr 2300.xx Hutang Titipan Konsumen**<br>**Cr 1120 Piutang Dagang** |

> Catatan: kode 1140.xx menyesuaikan jenis persediaan produk (Default 1140.10).

## Keputusan 16 November 2025 – Aktivasi Jurnal Penjualan Saat Invoice

Mengikuti arahan terbaru, seluruh komponen pendapatan mulai aktif ketika **Invoice Penjualan** diterbitkan:

- **Dr 1120 Piutang Dagang** ke seluruh nilai tagihan (harga jual + biaya tambahan + PPN).
- **Cr 4100.x Penjualan** sebesar harga jual bersih.
- **Cr 2140.xx PPn Keluaran** sebesar pajak yang dihitung dari nilai jual.
- **Cr 4200.xx Pendapatan Biaya Lain/Pengiriman** bila invoice memuat ongkir atau biaya tambahan.
- **Dr 5100.10 HPP Barang Dagang** untuk melepas nilai barang terjual.
- **Cr 1140.20 Barang Terkirim** sebagai pelepasan penampung DO.

Dengan formulasi ini, invoice menjadi titik tunggal pengakuan pendapatan, PPN keluaran, serta pelepasan akun barang terkirim.

## Keputusan 16 November 2025 – Customer Receipt & Hutang Titipan Konsumen

Pembayaran pelanggan kini dibedakan berdasarkan sumber dan tujuan kas:

1. **Pembayaran masuk ke Kas/Bank langsung**
   - **Dr 111x Kas/Bank**
   - **Cr 1120 Piutang Usaha**
   - Mencerminkan kas diterima serta pelunasan piutang tanpa keterlibatan akun titipan.

2. **Pembayaran melalui Deposit/Hutang Titipan Konsumen**
   - Saat pelanggan mendeposit dana, sistem sudah mencatat **Dr 111x Kas/Bank** vs **Cr 2300.xx Hutang Titipan Konsumen**.
   - Ketika invoice dilunasi menggunakan deposit, jurnal Customer Receipt menjadi **Dr 2300.xx Hutang Titipan Konsumen** vs **Cr 1120 Piutang Usaha**.
   - Saldo 2300.xx otomatis berkurang dan tercermin pada laporan/menu **Deposit** sehingga tim finance dapat memantau penggunaan dana titipan.

Implikasi:

- Tidak ada double counting kas—deposit hanya menggerakkan kewajiban sampai dikaitkan dengan invoice.
- Menu Deposit akan otomatis menyusutkan saldo ketika jurnal Dr Hutang Titipan Konsumen diposting dari Customer Receipt.
- Laporan aging piutang tetap presisi karena pelunasan terjadi pada akun piutang ketika deposit diaplikasikan.

## COA Baru: 1140.20 – Barang Terkirim

- **Kelompok**: Aset Lancar → Persediaan.
- **Fungsi**: Menampung nilai barang yang sudah keluar gudang tetapi belum dipindahkan ke HPP.
- **Normal Balance**: Debit (Asset).
- **Relasi Sistem**:
  - Menjadi default `goods_delivery_coa_id` pada master produk.
  - Digunakan oleh service posting Delivery Order untuk mendebit penampung sebelum invoice diterbitkan.

## Dampak Implementasi

1. **Seeder & Master Data**
   - `ChartOfAccountSeeder` menambahkan kode 1140.20.
   - `ProductSeeder`, `ProductFactory`, dan Form Filament memakai kode ini sebagai default `goods_delivery_coa_id`.
2. **Posting Delivery Order**
   - Observers/service Delivery Order kini otomatis menggunakan akun baru saat membuat jurnal pengeluaran stok.
3. **Pelaporan**
   - Laporan persediaan dapat menampilkan saldo "Barang Terkirim" agar tim gudang & finance melihat barang yang belum ditagih.
   - Balance Sheet menempatkan akun ini di kelompok persediaan sehingga total aset lancar tetap akurat.

## Catatan Teknis Penting

- **Delivery Order** selalu mengambil nilai dari `cost_price`/HPP produk untuk menghitung jurnal Dr Barang Terkirim vs Cr Persediaan. Kolom Harga Jual dan PPN pada DO tidak pernah masuk ke buku besar.
- **Invoice Penjualan** menjadi satu-satunya tahapan yang mengakui seluruh komponen pendapatan sekaligus melepaskan HPP: sistem otomatis membuat Dr Piutang, Cr Penjualan, Cr PPn Keluaran, opsional Cr Biaya Lain/Pengiriman, Dr HPP, dan Cr Barang Terkirim.
- **Customer Receipt** mendukung dua jalur otomatis: (1) pembayaran langsung menambah Kas/Bank dan melunasi Piutang, (2) pembayaran dari deposit menurunkan Hutang Titipan Konsumen sebelum melunasi Piutang, sekaligus menyinkronkan saldo menu Deposit.
- Jika produk belum memiliki `goods_delivery_coa_id`, aplikasi memakai fallback 1140.20 sehingga setiap DO tetap seimbang.
- Pastikan `cost_price` selalu terbarui (melalui pembelian/QC atau penyesuaian) agar nilai Barang Terkirim mencerminkan investasi aktual yang meninggalkan gudang.

## Checklist Implementasi Cepat

- [x] Tambah COA 1140.20 pada seeder.
- [x] Update default account mapping produk (resource, factory, seeder, tests).
- [x] Dokumentasikan skenario jurnal pada tahapan penjualan.
- [ ] (Opsional) Update UI posting Delivery Order bila membutuhkan label baru.
- [ ] (Opsional) Tambah laporan monitoring "Barang Terkirim" untuk rekonsiliasi DO vs Invoice.

Dengan konfigurasi ini, proses sales order mengikuti best practice: barang keluar tercatat segera, namun hanya berpindah menjadi HPP setelah invoice, sehingga laporan keuangan dan logistik tetap sinkron.
