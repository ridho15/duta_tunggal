# Laporan UAT, QA, dan Regresi: Prioritas Penjualan 03 Nov 2026

Tanggal uji: 4 Nov 2026. Lingkungan: salinan DB dev (`duta_tunggal_uat`), server `localhost:8001`, browser Playwright. DB dev asli tidak diubah oleh pengujian browser.
Bukti: folder `docs/bukti-uat-04nov2026/` (screenshot, PDF).

## A. Tiga kegagalan lama (diperbaiki)

| # | Gejala | Akar masalah | Perbaikan |
|---|--------|--------------|-----------|
| 1 | `BladeSuratJalanInvoiceWorkflowTest` invoice gagal (500) | Fixture tes tanpa akun COA 1120 dan 4000; `InvoiceObserver` memang menolak | COA ditambahkan di `setUp` tes |
| 2 | `BladePurchaseReceiptWorkflowTest` gagal di `setUp` + 1 asersi usang | Supplier tanpa `address`; label tombol bukan "+ Terima Barang Baru" | Fixture dilengkapi; asersi disesuaikan |
| 3 | `DeliveryOrderTransitionsTest` gagal bila digabung file lain | 3 kelas tes (`BladeSuratJalanInvoiceWorkflowTest`, `BladeSalesOrderWorkflowTest`, `SalesOrderQuickAddTest`) tidak memakai `RefreshDatabase` sehingga data bocor ke tes berikutnya | `use RefreshDatabase;` ditambahkan di ketiganya |

## B. Hasil UAT browser (alur penuh, data nyata)

| Langkah | Hasil | Bukti |
|---------|-------|-------|
| Buat SO dengan pemilih produk AJAX (pencarian, pilih, harga, stok, total) | LULUS (SO-00001) | so-01, so-03 |
| Scanner: SKU + Enter menambah baris; SKU sama menambah qty | LULUS | (log uji) |
| Setujui SO, buat DO terpecah 2 gudang | LULUS (2 DO `request_stock`) | do-01 |
| Antrean Gudang per gudang + tab "Menunggu konfirmasi stok" | LULUS (checker gudang 1 hanya melihat DO gudang 1) | gudang-01 |
| Konfirmasi stok -> `approved`, pindah ke "Siap dicek" | LULUS | gudang-02, do-02 |
| Checker (HP 390px): edit qty 6->5->4, sumber gudang ikut turun, stok kembali | LULUS | checker-01..03 |
| Cek barang -> `confirmed`, SJ terbit, DO `received` | LULUS (SJ-20261104-0001) | sj-01 |
| Faktur dari SJ | LULUS (Rp 30.000 = 4 x 7.500) | invoice-01 |
| Tombol Kirim (SJ terbit, DO belum berangkat) | LULUS, stok tidak terpotong ganda | do-03 |
| GRN: default gudang = tujuan PO, banner beda gudang, alasan wajib | LULUS | grn-01, grn-02 |
| GRN: detail berdampingan + PDF | LULUS | grn-03, grn-04 |
| GRN: edit draf beda gudang | LULUS setelah perbaikan (lihat C) | (log uji) |

## C. Temuan QA saat UAT (semua sudah diperbaiki, ada tes regresi)

1. **Tombol "Tambah Baris Manual" menambah dua baris** (listener ganda sejak HEAD). Satu pendaftaran dihapus.
2. **Edit qty checker tidak menurunkan `delivery_order_item_warehouse_sources`**. Sekarang ikut diturunkan.
3. **Konfirmasi stok dan Kirim tidak berjejak di log DO** saat flag `strict_dispatch` mati. Log eksplisit ditambahkan.
4. **Gudang tujuan PO yang nonaktif tidak ada di dropdown GRN** (dropdown tampil kosong). Gudang tujuan PO dan gudang item tersimpan selalu ikut dimuat, ditandai "nonaktif".
5. **Halaman edit GRN rusak sejak HEAD**: `@json` di dalam atribut `x-data="..."` memutus atribut, baris barang tidak pernah tampil. Diganti `Js::from`.
6. **Form edit GRN tidak punya kolom alasan beda gudang**, padahal server mewajibkannya. Kolom dan peringatan ditambahkan.
7. Salah ketik "Gudang Gudang Utama" di banner DO.

## D. Uji tak bahagia

| Skenario | Hasil |
|----------|-------|
| Role Sales melihat DO menunggu | Tombol Konfirmasi tidak muncul (nohappy-01) |
| Sales POST langsung konfirmasi stok / kirim | 403 / 403 |
| Token CSRF palsu | 419 |
| Buat SJ dari DO yang belum dikonfirmasi (POST langsung) | Ditolak dengan pesan jelas, 0 baris SJ |
| Kirim sebelum konfirmasi / tanpa SJ | Ditolak, status tidak berubah |
| Konfirmasi stok dua kali | Ditolak rapi, hanya 1 log |
| Cek tanpa mencentang barang | Ditolak |
| Naikkan qty checker (POST langsung) | Ditolak |
| Edit qty tanpa alasan | Ditahan validasi |
| SO: baris produk kosong | Ditahan, fokus ke baris bermasalah |
| Pencarian produk: hasil kosong | Pesan "Tidak ada produk yang cocok" |
| Pencarian produk: jaringan gagal, lalu "Coba lagi" | Pesan galat, retry berhasil |
| Nama produk berisi HTML (`<img onerror>`) | Tampil sebagai teks, tidak dieksekusi |
| GRN: beda gudang tanpa alasan | Ditahan (browser) dan ditolak (server) |
| GRN: PO tanpa gudang tujuan | Gudang kosong, wajib dipilih, tanpa banner |
| Keyboard: panah/Esc pada daftar produk | Berfungsi, fokus kembali ke input |
| HP 390px: SO dan checker | Tanpa overflow horizontal, target tap 48px |

## E. Skala (butir 2)

DB UAT diisi 29.557 produk. Halaman Buat SO: **0,15 MB, 37 ms** (laporan awal: 14,4 MB, 3.976 ms). Pencarian: 43 sampai 51 ms. Tidak ada data produk massal di HTML.

## F. Catatan dan keterbatasan

- Pintasan yang dipakai di UAT: `selectedPoId` GRN diset lewat state Alpine (memicu fetch AJAX yang sama); fixture SJ-terbit-tapi-DO-belum-berangkat dibuat langsung di DB UAT; akun, IDR, COA, dan PO uji hanya ada di DB UAT.
- Data dev: tidak ada mata uang sama sekali (SO tidak bisa disimpan), COA produk kosong (penerbitan SJ menghasilkan halaman 500 polos), dan Gudang Utama/Kenari/Karantina berstatus nonaktif. Ini perlu dibereskan di data master, di luar kode.
- Penerbitan SJ otomatis menandai DO terkirim dan diterima (aturan lama `markDeliveryOrdersReceived`), jadi tombol Kirim terutama melayani DO yang SJ-nya sudah terbit tetapi belum berangkat.
- Kegagalan COA saat SJ terbit tampil sebagai halaman 500, bukan pesan validasi. Belum diperbaiki.
- Nama DO gudang kedua memakai prefix gudang pertama (`DO-GDG-UTM-...-01-02`). Belum diperbaiki.
