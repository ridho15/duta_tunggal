# LIST PENGERJAAN DEVELOPER – ERP DUTA TUNGGAL

Update: 13/10/2026 · Sumber: feedback Ko Handy (dokumen "DISKUSI dengan Rey 12 OKT 2026") + hasil UAT di https://dutatunggal.playgo.id/admin
Marker data uji: `TEST-RL-HASSEE-<tanggal>`

Dokumen ini berisi **bug dan fitur alur bisnis**. Perubahan tampilan/navigasi ada di `UIUX_Spec_ERP_Duta_Tunggal.md`. Aturan bisnis lengkap ada di `Alur_Bisnis_Ko_Handy.md`.

---

## A. BUG PENGHAMBAT (PRIORITAS TERTINGGI)

| # | Bug | Detail / cara reproduksi |
|---|---|---|
| A1 | Buat DO tidak tersimpan, tanpa pesan apa pun | Uji dari SO-00007, dengan 1 gudang maupun 2 gudang, lewat tombol Buat di UI. Request `livewire/update` mengembalikan 200 tanpa error dan tanpa redirect; DO tidak terbentuk. |
| A2 | Buat Surat Jalan error 500 | `Unknown column 'order_number'`. Di tabel `sale_orders` nama kolomnya `so_number`. |
| A3 | Transfer Stok error saat memilih "Dari Gudang" | `Unknown column 'stock'`. |

## B. FITUR BELUM ADA – SESUAI ALUR KO HANDY

### Sales Order
1. Kolom **ongkos kirim** di SO (terbawa ke invoice).
2. **Approve SO oleh kasir** (atau setara), jika harga ≥ batas harga di master produk. Peran Kasir perlu dibuat dan ditambahkan di Aturan Persetujuan SO.
3. **Blokir approve SO** jika melewati limit kredit customer (naik ke atasan).
4. Batas harga juga berlaku untuk **harga setelah diskon per cabang** (perlu dicek).
5. **Menunggu keputusan Ko Handy:** SO dengan harga di bawah batas (A) ditolak saat simpan seperti sekarang, atau (B) boleh disimpan dan approve naik ke atasan.
6. Label "Harga Batas (%)" di master produk berisi nominal, bukan persen. Perjelas label/isi (sistem saat ini memakai harga beli sebagai minimum).

### Delivery Order
7. Tampilan DO per produk: tampil **semua gudang dengan stok bebas > 0**, user mengisi qty per gudang (seperti contoh Excel Ko Handy).
8. Default gudang mengikuti gudang yang stoknya cukup (sekarang terisi gudang yang stoknya kurang, contoh KENARI 5 pcs padahal stok 2).
9. DO dari beberapa gudang **dipecah otomatis per gudang** (DO 1-01, DO 1-02).
10. **Stok bebas terkunci saat DO dibuat** (sekarang baru saat Konfirmasi Gudang).
11. Verifikasi Kasir COD dikaitkan dengan **saldo titip customer** yang benar-benar masuk (sekarang hanya catatan).

### Retur by DO (sebelum Surat Jalan)
12. Toko/checker mencatat **qty diterima, qty kurang, dan qty reject** per DO.
13. Nomor retur turunan DO asal: **DO.000xR**.
14. Setelah retur disetujui terbentuk **DO pengganti DO.000xC**, dan DO asal ditandai "sudah diretur".
15. **Retur DO yang disetujui otomatis tercatat di Stock Adjustment** (kode/referensi DO.000xR) dan **stok gudang asal bertambah** sesuai qty retur.
16. Barang reject masuk lokasi karantina/rusak, bukan stok jual.
17. Jenis mutasi stok sendiri "Retur DO" (sekarang tercatat "Customer Return", sama dengan retur penjualan; dokumen memakai nomor RN-).

### Surat Jalan
18. Urutan input: **Customer > SO > centang DO**.
19. DO yang digabung dalam 1 SJ harus dari **SO yang sama**.
20. DO yang pernah diretur hanya muncul sebagai DO.000xC. DO yang sudah dipakai disembunyikan (keputusan Ko Handy).
21. Membuat SJ **otomatis menandai DO "diterima"** (satu langkah, tanpa tombol Konfirmasi Diterima terpisah).
22. SJ wajib ada sebelum DO bisa dikirim/diselesaikan.
23. Cetakan SJ menampilkan **gudang asal per barang** (jika DO dari beberapa gudang).

### Invoice
24. Urutan input: **Customer > SO > centang SJ** (dalam SO yang sama). Bisa menambah ongkos kirim / biaya lain.
25. Invoice **tidak lagi terbit otomatis** saat DO diselesaikan.
26. Invoice yang sudah posting **dikunci** (sekarang qty, tipe pajak, tarif PPN, dan customer masih bisa diedit).

## C. BUG LAIN YANG MASIH TERBUKA

| # | Bug | Detail |
|---|---|---|
| C1 | Saldo hutang salah | PINV-TEST-RL-HASSEE-20261010 sisa Rp30.016,59, seharusnya Rp36.306,96. Selisih Rp6.290,37 = nilai reject QC yang ikut mengurangi hutang. Pembayaran vendor Rp36.306,96 ditolak. |
| C2 | Reject QC menghasilkan 2 dokumen retur untuk 1 pcs yang sama | NR-20261010-8339 dan NR-20261010-2201. Harus 1 dokumen. |
| C3 | Daftar item Stock Opname tidak tampil | Di halaman Lihat dan Ubah. |
| C4 | Stack trace Laravel tampil ke user | Set `APP_DEBUG=false` di produksi; tampilkan pesan umum + kode error. |
| C5 | COA di "Saldo Titip Customer" menampilkan semua 51 akun | Seharusnya hanya akun kas/bank. |
| C6 | Master data dobel | Customer (contoh DAYA TEKNIK MEDIKA ada 5 versi), supplier (Abdi Karya: A001 dan CAB-A001). Perlu pembersihan + peringatan nama mirip saat input. |
| C7 | Data rekening supplier kosong | Kolom sudah ada; isi data dan tampilkan di PO / Permintaan Pembayaran. |

## D. SUDAH DIPERBAIKI (UNTUK REFERENSI, JANGAN DIUBAH)

- Batas harga diterapkan saat simpan Quotation dan SO Mandiri (minimum = harga beli: Rp5.667 untuk 5/8'', Rp3.600 untuk 1/2'').
- SO "Ambil Sendiri" tetap lewat DO. DO hanya bisa dibuat dari SO yang disetujui.
- Tombol "Verifikasi Kasir (COD)" di SO.
- Return Product dibatasi untuk DO yang belum punya SJ.
- Status retur pembelian terkunci Draft; status retur customer terkunci.
- Tombol "Terbitkan" di SJ dihapus; SJ menampilkan syarat customer+cabang sama dan preview item.
- Halaman PO-dari-SO, Laporan Stok Interaktif, edit invoice (pilih SJ), form Transfer Stok sudah bisa dibuka.
- Form DO menampilkan stok bebas per gudang, peringatan stok lebih, Total SO / Sudah dikirim / Maks; item DO mengikuti SO; qty ≤ sisa.
- Tombol Kirim dan Checker Edit Qty ada (muncul setelah DO Disetujui).
- Invoice punya "Biaya Lain-lain". SJ kolektif multi DO.
- Login tidak lagi terisi otomatis; Stok Bebas di SO benar; kolom rekening supplier ada.
- Reject QC tidak berdampak ke stok/jurnal; stok 5/8'' benar = 9.

## E. BELUM DIUJI (MENUNGGU BUG A DIPERBAIKI)

- Checker Edit Qty: mengurangi qty per gudang dan dampaknya ke sisa SO.
- Retur by DO: stok bertambah dan tercatat di Stock Adjustment.
- Alur penuh SO > DO > SJ > Invoice > Pembayaran.
- Approve SO dengan login peran Kasir.
