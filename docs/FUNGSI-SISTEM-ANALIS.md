# Fungsi Sistem — Catatan System Analyst

> Disarikan dari handoff system analyst / UAT (terakhir diverifikasi di aplikasi 2026-11-08),
> lalu dicocokkan dengan kode di repo ini (2026-11-09). Fokus dokumen ini adalah **fungsi
> sistem**: alur bisnis, aturan, rute, status celah fungsional, dan cara menguji.
>
> Istilah domain, label UI, dan status sengaja ditulis dalam bahasa Indonesia apa adanya
> agar sama dengan yang tampil di layar.

Dokumen terkait: `docs/baru/Alur_Bisnis_Ko_Handy.md` (aturan bisnis lengkap),
`docs/baru/List_Pengerjaan_Developer.md` (register bug & celah),
`docs/UAT-LAPORAN-04NOV2026.md` (hasil UAT terakhir).

---

## 1. Gambaran Singkat

- Laravel 12 · Blade + Alpine.js. Awalnya Filament v3, **dibangun ulang ±2026-10-26**
  mengikuti spesifikasi UI/UX; rute pindah dari `/admin/*` ke root. Panduan lama yang
  spesifik Filament (termasuk sebagian `docs/CONTEXT.md` dan `docs/baru/UIUX_Spec_*`)
  sudah usang.
- Skala data produksi yang memengaruhi performa: **±30 ribu SKU** dan **±18 ribu customer**.
  Halaman yang menanam seluruh master data di HTML akan berat.
- Master produk banyak duplikat (`CAB-<sku>`, `<sku>-DUP2-R#####`) dengan kategori tidak
  konsisten. Stok satu barang fisik bisa terpecah di beberapa record produk — jangan
  menyimpulkan "stok benar" dari satu SKU saja.

---

## 2. Peta Rute

```
/dashboard · /kasir
Penjualan : /penjualan/{penawaran,so,do,surat-jalan,jadwal-kirim,invoice,
                        terima-pembayaran,retur,nota-kredit}
Gudang    : /gudang/{kiriman,penerimaan,stok,transfer,opname}
Pembelian : /pembelian/{permintaan,po,invoice,pembayaran,retur}
Keuangan  : /keuangan/{kas-bank,jurnal,aset}
Manufaktur: /manufaktur/{bom,spk,material-issue,production,qc}
Master    : /master/{produk,customer,supplier,cabang,gudang,kategori,satuan,
                     kendaraan,mata-uang,pajak,coa}
Laporan   : /laporan/keuangan  ·  /reports/*/preview (neraca, laba rugi, arus kas, HPP, ageing, …)
Kartu stok: /gudang/stok/kartu-persediaan        (konsolidasi; ?start_date&end_date&warehouse_id)
            /gudang/stok/{productId}/kartu        (per produk — WAJIB ?start_date=&end_date=)
```

Catatan perilaku rute:

- `/penjualan/do/create` memberi 404 tanpa `?so_id=` — DO memang selalu dibuat dari SO.
- Aksi DO: `POST /penjualan/do/{id}/confirm-stock`, `/send`, `/cancel`.
- Pencarian produk AJAX: `/penjualan/so/products/search` (pola yang benar untuk master besar).
- Kartu stok per produk tampil kosong bila tanggal tidak diisi — terlihat seperti bug padahal bukan.

---

## 3. Prinsip Utama

**Sangat mudah dipakai. Produk tidak pernah diketik ulang.** Setiap dokumen mewarisi
barisnya dari dokumen sebelumnya. Bila kode dan aturan di bawah ini berbeda, **kodenya
yang salah**.

---

## 4. Alur Penjualan (customer kredit)

```
SO → Approve SO → DO (per gudang) → Konfirmasi Stok → Checker/Kirim
   → [Retur by DO bila ada selisih] → Surat Jalan → Invoice → Terima Pembayaran
```

| Tahap | Aturan |
|---|---|
| **SO** | Customer, produk, qty, harga, diskon, **ongkos kirim**. Gudang **tidak** dipilih di sini. |
| **Approve SO** | Oleh **kasir atau setara**, bukan Super Admin. Lolos hanya bila setiap baris **≥ harga batas bawah di master produk** (termasuk setelah diskon cabang) **dan** customer masih **dalam limit kredit**. Gagal salah satu → eskalasi ke atasan. COD: kasir memverifikasi deposit benar-benar masuk sebelum DO boleh terbit. |
| **DO** | Hanya dari SO yang sudah disetujui. **Gudang dipilih di sini.** Per produk tampilkan semua gudang dengan stok bebas > 0, user mengisi qty per gudang. Qty ≤ sisa SO; qty per gudang ≤ stok bebas gudang itu. DO terpecah otomatis per gudang (`-01`, `-02`). **Stok bebas dikunci (bukan dipotong) saat DO dibuat.** |
| **Retur by DO** (sebelum SJ) | Dicatat toko/checker: qty diterima / kurang / reject. Penomoran `DO.000x` → retur `DO.000xR` → pengganti `DO.000xC`. DO asal ditandai "sudah diretur"; **hanya `DO.000xC` yang boleh jadi SJ**. Saat disetujui: posting ke **Penyesuaian Stok** (ref `DO.000xR`) dan **stok kembali** ke gudang asal. Reject masuk karantina, bukan stok jual. Jenis mutasi sendiri "Retur DO", beda dengan Retur Penjualan setelah invoice. |
| **Surat Jalan** | Pilih **Customer > SO > centang DO**. Tanpa input produk. Satu SJ boleh menggabungkan beberapa DO **dari SO yang sama**, termasuk lintas gudang. DO yang sudah dipakai disembunyikan. Membuat SJ **otomatis menandai DO diterima** (tanpa langkah Konfirmasi Diterima terpisah). Cetakan menampilkan gudang asal per baris. |
| **Invoice** | Pilih **Customer > SO > centang SJ** (SO yang sama). Tanpa input produk. Boleh menambah ongkos kirim / biaya lain. **Tidak pernah terbit otomatis dari DO.** Terkunci setelah diposting; koreksi lewat batal/revisi yang tercatat. |

---

## 5. Alur Pembelian

```
PR → PO (disetujui) → Terima Barang + QC (satu layar) → Invoice Pembelian → Bayar
```

- QC **bukan** langkah terpisah. Qty baik masuk stok; qty reject **tidak** masuk stok,
  **tidak** dijurnal, dan otomatis membuat **tepat satu** Retur Pembelian.
- Rekening bank supplier disimpan di master dan ditampilkan saat pembayaran.

### Terima di gudang selain tujuan PO

Sah dan harus tetap bisa. Gudang GRN adalah **tempat barang benar-benar mendarat**, dan
itulah yang menggerakkan stok.

1. Gudang GRN **terisi otomatis dari tujuan PO**, tetapi bisa diubah.
2. Bila diubah: tampilkan peringatan dan **wajib alasan**, disimpan di GRN.
3. Detail + PDF menampilkan **"Gudang Tujuan PO"** dan **"Gudang Terima"**, ditandai bila beda.
4. Sediakan tombol **"Pindah ke Gudang Tujuan PO"** yang mengisi awal form Pindah Stok.
5. **PO tidak pernah diedit.** PO adalah komitmen ke vendor; penyimpangan adalah bukti
   vendor salah kirim. PO yang sudah disetujui tidak punya tombol Ubah (hanya Draf).

---

## 6. Inventori

- **Stok bebas = fisik − terkunci oleh DO.**
- Opname harus **blind count** (qty sistem disembunyikan saat menghitung), selisih
  direview, lalu diposting sebagai Penyesuaian Stok.
- Gudang lama "Gudang Pusat" masih muncul di DO sebelum Oktober tetapi tidak ada di
  dropdown mana pun — dokumen tersebut tidak terjangkau dari layar gudang (data legacy yatim).

---

## 7. Status Celah Fungsional

Status di aplikasi per 2026-11-08, ditambah hasil cek kode 2026-11-09.
**FIXED** sudah berfungsi · **HALF** sebagian · **OPEN** belum ditangani.

### Dampak tinggi

| # | Item | Status app | Temuan di kode |
|---|---|---|---|
| 1 | DO macet, tak ada aksi konfirmasi | **FIXED** | `POST /penjualan/do/{id}/confirm-stock` ada. |
| 2 | Stok dipotong saat DO dibuat, bukan dikunci | **FIXED di kode (09 Nov)** | DO yang belum berangkat kini hanya **mengunci** stok (reservasi DO → kolom Terpesan); stok fisik berkurang saat DO **Dikirim** (termasuk lewat terbitnya Surat Jalan). Titik keputusan: `DeliveryOrderObserver::createStockMovementsForDeliveryOrder` → `lockStockForDeliveryOrder` / `deductShippedStock`. Retur by DO sebelum berangkat mencatat "keluar lalu kembali" agar stok tidak menggelembung. **Data lama wajib dikonversi sekali**: `php artisan stock:convert-open-do-deductions` (dry-run) lalu `--apply`. |
| 3 | Berat halaman Buat SO | **FIXED di kode (09 Nov)** | Customer dicari lewat AJAX (`/penjualan/so/customers/search`), master customer tidak lagi ikut di payload. Ukur lokal 18.000 customer: 10.821 KB → 125 KB. |
| 4 | Gudang GRN tidak mewarisi PO | **FIXED di kode** | Pre-fill dari tujuan PO, peringatan + alasan wajib (sudah ada sejak 4 Nov, terverifikasi di browser lokal untuk dua jalur masuk). Tombol **"Pindah ke Gudang Tujuan PO"** di detail GRN (status selesai) mengisi awal Pindah Stok. Bila di app masih kosong: cek PO-nya punya gudang tujuan, dan app sudah di-deploy ulang. |

Sebelum perbaikan item 2, stok fisik di sistem lebih kecil sebesar qty setiap DO yang belum
dikirim, sehingga opname menunjukkan selisih palsu. Setelah deploy, **jalankan konversi data**
sebelum opname berikutnya.

### Aturan bisnis yang belum (sepenuhnya) dibangun

Verifikasi di app sebelum melapor ulang — sebagian mungkin sudah masuk:

- Ongkos kirim di SO *(kolom `shipping_cost` sudah ada di kode)* · approve oleh kasir
  dengan gerbang harga batas bawah & limit kredit · batas harga dihitung setelah diskon
  cabang · COD terikat deposit nyata.
- Retur by DO: penomoran `R`/`C` *(sufiks `C` ada di `DeliveryOrderReturnProcessor`)*, DO
  asal ditandai, Penyesuaian Stok + stok kembali saat disetujui, lokasi karantina, jenis
  mutasi tersendiri.
- SJ: Customer > SO > centang DO; batasan SO yang sama; SJ otomatis menandai DO diterima;
  gudang asal di cetakan; SJ wajib sebelum kirim.
- Invoice: Customer > SO > centang SJ; tidak terbit otomatis dari DO; terkunci setelah posting.

### Temuan lain

- **Valuasi GL vs laporan stok berbeda**: GL membukukan persediaan pada harga PO, laporan
  stok pada HPP. Perlu keputusan akuntansi, bukan sekadar perbaikan kode.
- Penyesuaian stok bertambah mengkredit **PENDAPATAN LAINNYA (7000.04)** — perlu konfirmasi akuntan.
- Data Retur Pembelian duplikat historis masih ada (bug pembuatnya sudah diperbaiki, data lama belum dibersihkan).
- PO menampilkan `100% (15/15)` tetapi status "Diterima Sebagian".
- Kolom "Sudah DO" di detail SO bernilai 0 walau DO ada (kolom Sisa benar).
- Pesan persetujuan retur mengklaim stok dipotong & GL diposting, padahal tidak (perilaku benar, **teks salah**).
- Daftar opname menampilkan "Tidak Ada Hasil Ditemukan" bersamaan dengan baris data.
- Opname menampilkan qty sistem ke penghitung (tidak blind) dan memakai snapshot basi.
- Siapkan & Kirim tidak bisa menampilkan DO yang gudangnya tidak ada di dropdown.
- Pindah Stok menawarkan stok **fisik**, bukan stok bebas — barang yang sudah dikunci DO bisa dipindah.
- Duplikasi master data (lihat §1).

### Sudah diperbaiki selama UAT

DO gagal dibuat tanpa pesan · Transfer Stok `Unknown column 'stock'` · SJ 500
`Unknown column 'order_number'` · item Stock Opname tidak tampil · stack trace / `APP_DEBUG`
terbuka · retur ganda dari QC reject · escaping JSON di `x-data` form GRN.

### Perilaku yang sudah benar — jadikan uji regresi

- Terima barang: qty diterima masuk stok, qty reject tidak; kartu stok mencatat
  `Penerimaan Pembelian`; GL Dr Persediaan / Cr Penerimaan Barang Belum Tertagih.
- Menyetujui retur dari QC reject **tidak** memotong stok dua kali.
- Membatalkan DO mengembalikan stok dan mencatat alasan di kartu stok.
- Aritmetika stok konsisten (`fisik − terpesan = bebas`, total cocok, tanpa negatif).

---

## 8. Keputusan Ko Handy — 09 Nov 2026

Jawaban tertulis Ko Handy ("jawaban ke developer 09 Nov 2026"). Kolom *Status* = kondisi kode di branch ini.

| # | Topik | Keputusan | Status |
|---|---|---|---|
| 1 | Kapan stok keluar | Stok terpotong saat **Surat Jalan** keluar (1 SJ boleh berisi beberapa DO dari SO yang sama). Selama masih DO, stok hanya terkunci. Stok terpotong **sesuai tanggal jadwal pengiriman**: SJ dibuat 9 Nov, jadwal kirim 10 Nov → stok terpotong 10 Nov. Saat SJ keluar juga **timbul piutang**. | **Sebagian.** Kunci saat DO + potong saat SJ terbit: sudah. Tanggal potong mengikuti jadwal kirim: **belum** (sekarang tanggal SJ terbit). Piutang saat SJ: **belum** (invoice masih manual dari SJ) — perlu klarifikasi, lihat bawah. |
| 2 | Merapikan data stok lama | Setelah jam operasional, **kecuali tanggal akhir bulan** (laporan stok/HPP ditarik). | **Sudah.** `stock:convert-open-do-deductions --apply` menolak jalan di tanggal akhir bulan kecuali `--force`. |
| 3 | Retur | Retur by DO = **internal**, saat checker/sales memeriksa sebelum SJ. Retur by SJ = **eksternal** (toko vs pembeli, sebelum invoice). Retur = setelah invoice (salah order/berubah pikiran). **Semua retur dicatat barang keluar lalu masuk kembali.** | **Retur by DO sudah** dicatat keluar lalu kembali. Retur by SJ & Retur setelah invoice: belum diverifikasi terhadap aturan "keluar lalu kembali". |
| 4 | SO dibatalkan | SO tanpa DO hanya boleh dihapus **Manager/Super Admin**. SO yang sudah ber-DO dibatalkan lewat **hapus DO**. Pengajuan pembatalan DO oleh Sales/pembuat DO/atasannya/Super Admin; yang **menghapus** hanya **Gudang/Manager/Super Admin**. | **Sebagian.** Pengaman: SO tidak bisa dibatalkan selama masih punya DO aktif. Belum ada: tombol hapus/batal SO di UI Blade (memang belum ada sama sekali), hak akses Manager/Super Admin, alur "ajukan batal DO → gudang menghapus". |
| 5 | Harga di bawah batas | **(A) Sistem menolak.** Harga di bawah batas hanya lewat **Quotation yang di-approve**, baru jadi SO. | **Sudah.** SO ditolak saat simpan kecuali harga ≥ harga Penawaran yang sudah di-approve; peringatan di form hidup. Catatan: produk tanpa harga batas (0) memakai **harga jual** sebagai batas. |
| 6 | Siapa melihat PO | PO ber-harga: **tidak ada staf gudang** yang boleh melihat. Daftar barang yang akan diterima: Super Admin, Manager, Purchasing, staf gudang tujuan (ship-to) PO. Salah gudang: yang menerima hanya keempat peran itu, **lalu dimutasi ke gudang yang salah**. | **Belum.** Dan *bertentangan* dengan keputusan 2 Nov (GRN dicatat di gudang tempat barang mendarat). Perlu klarifikasi, lihat bawah. |
| 7 | Barang reject dari supplier | Reject saat serah terima langsung dikembalikan ke kurir supplier; hanya qty lolos QC yang diterima → **Purchase Invoice < PO**. Kirim susulan → QC ke-2, PI ke-2 untuk PO yang sama. Tidak ada susulan → PO ditutup. Setelah PI: Retur Pembelian (keluar barang, SJ, **Invoice Retur** = nota kredit supplier yang memotong pembayaran). | **Belum diverifikasi.** Sistem sekarang otomatis membuat 1 Retur Pembelian dari qty reject QC — perlu dicocokkan. |
| 8 | Akuntan (nilai persediaan, penyesuaian ke pendapatan lain) | **Dijawab 10 Nov.** Penyesuaian stok **lebih → pendapatan lain-lain**, **kurang → biaya lain-lain**. Nilai persediaan **salah** bila Buku Besar memakai nilai PO; **yang benar: Laporan Stok DAN Buku Besar sama-sama memakai HPP**. | **SUDAH (10 Nov).** **Revisi #4B Ko Handy:** hasil stock opname/penyesuaian berdampak ke **COA Persediaan dan COA HPP** — lebih: Dr Persediaan / Cr HPP; kurang: Dr HPP / Cr Persediaan (menggantikan jawaban #4 sebelumnya: pendapatan/biaya lain-lain). Diterapkan di `StockAdjustmentService` (HPP per produk, default 5100.10) dan `StockOpname::createAdjustmentJournalEntries` (5100.10). **Penerimaan barang (GRN & jalur QC) kini mendebit Persediaan pada HPP** (`products.cost_price`), akun barang-diterima-belum-ditagih / pengadaan-sementara tetap pada harga PO, selisih harga PO − HPP ke **5160 Selisih Pembelian** (akun yang sama dengan selisih faktur vs PO). Nilai gerakan stok masuk juga HPP. Produk tanpa HPP (0) tetap dinilai harga PO. Jurnal pengiriman & HPP penjualan sudah HPP sebelumnya. *Data lama tidak diubah* — saldo Persediaan historis yang sudah menyimpang perlu jurnal koreksi sekali oleh akuntan. |
| 9 | Pengajuan Pembayaran & Voucher | **Satu menu Pengajuan Pembayaran**, dua jenis: (A) hutang pembelian **barang dagang** — pilih supplier → centang invoice belum lunas (no PO, no invoice, sisa, jatuh tempo) → metode bayar (cash/transfer/cek-giro) → rekening tujuan dari master → tanggal efektif → request ke akuntan/direktur → cetak **VOUCHER** bertanda tangan pembuat/approver/direktur; saat pelunasan cukup pakai no. pengajuan. (B) **non-barang** (alat kantor, plastik, jasa, sparepart kendaraan; tanpa PO), alur sama. | **Belum.** Spesifikasi fitur baru; "Pengajuan Voucher" = cetakan dari Pengajuan Pembayaran → digabung. |

**Jawaban klarifikasi Ko Handy — 10 Nov 2026**

- **Butir 1 — invoice & piutang.** "Timbul piutang saat SJ keluar" adalah istilah akuntansi (stok berkurang ⇄ piutang terbentuk), **bukan** invoice terbit otomatis. Invoice **tetap dibuat manual dari SJ** seperti sekarang. Alur baku: Quotation (bila ada) → SO (harga di bawah batas wajib lewat Quotation approved) → DO (1 SO boleh banyak DO) → SJ (1 SJ boleh banyak DO) → Invoice (1 Invoice boleh banyak SJ).
  - **Permintaan baru:** di menu **Sales Invoicing**, tampilkan daftar **SJ yang belum jadi Invoice s/d hari ini**; saat Nama Konsumen diisi, hanya SJ milik konsumen itu yang tampil. Tujuannya agar Manager/Direktur bisa mengontrol barang terkirim yang belum jadi "duit". → **SUDAH (10 Nov).** `SalesInvoiceController::create` menampilkan SJ terbit yang belum difaktur + **filter Nama Konsumen** (dropdown; saat dipilih hanya SJ konsumen itu tampil).
- **Butir 1 — tanggal potong stok (butir 2 jawaban Ko Handy).** Tanggal jadwal pengiriman **selalu diisi** saat SJ dibuat; bila tidak di-assign, memakai **Delivery Date default = tanggal saat SJ dikerjakan**. **Stok terpotong sesuai `DELIVERY DATE` tersebut.** → **SUDAH (10 Nov).** Form SJ punya **Tanggal Kirim** (`surat_jalans.delivery_date`, default = tanggal SJ). Tanggal kirim ≤ hari ini → DO langsung berangkat, gerakan stok & jurnal pengiriman bertanggal tanggal kirim. Tanggal kirim di masa depan → DO tetap **terkunci** (stok belum berkurang), jadwal kirim berstatus *pending*; perintah harian `sales:dispatch-scheduled-surat-jalan` (00:15) memberangkatkannya saat tanggalnya tiba. SJ yang belum tiba tanggal kirimnya **tidak tampil di Sales Invoicing dan ditolak saat difaktur** (piutang timbul saat barang keluar). **Wajib di server:** scheduler Laravel (`php artisan schedule:run` tiap menit via cron) harus aktif.
- **Butir 6 — salah kirim gudang (REVISI dari keputusan 2 Nov).** Ko Handy meluruskan: PO menentukan **Gudang A** sebagai penerima; bila supplier salah kirim ke **Gudang B**, **Gudang A tetap melakukan Terima Barang** (GRN di Gudang A), lalu dibuat **mutasi Gudang A → Gudang B** dengan kolom keterangan (mis. "Mutasi karena supplier salah kirim lokasi gudang"). Tujuannya **arus barang jelas ter-record**. → **SUDAH (10 Nov).** Arah mutasi dibalik ke **A→B**: tombol "Buat Mutasi Stok ke Gudang Lain" di detail GRN selesai mengisi awal Pindah Stok dengan gudang asal = gudang GRN (tujuan PO), gudang tujuan dikosongkan (dipilih user), barang dari qty diterima, dan keterangan default. Kolom `notes` ditambahkan ke `stock_transfers` (migrasi `2026_11_10_000000`) dan tampil di form Pindah Stok. Form GRN mengarahkan ke alur ini.
  - *Catatan:* path lama "terima di gudang berbeda + alasan" (4 Nov) **dibiarkan** sebagai jaring pengaman audit (bukan dihapus), karena menghapus JS form GRN yang rumit berisiko regresi. Bila Ko Handy ingin benar-benar mengunci GRN hanya ke gudang tujuan PO, itu pengerasan lanjutan.

**Temuan UAT — 11 Nov 2026** (status = kondisi kode di branch ini)

| # | Temuan | Penyelesaian |
|---|---|---|
| A1 | DPP di detail Penawaran belum ×11/12 | **Sudah.** Detail, form Buat & Ubah Penawaran memakai **DPP Nilai Lain = 11/12 × harga jual** dan **PPN = 12% × DPP** (setara 11% efektif). Eksklusif: harga jual = netto; Inklusif: harga jual = netto/1,11. |
| A2 | Penawaran lambat (termasuk approve) | **Sudah.** Penyebab: index, Buat & Ubah Penawaran mengirim **seluruh** master customer (±18 ribu) dan produk (±29 ribu) ke halaman. Kini customer & produk dicari lewat AJAX (endpoint yang sama dengan SO); halaman hanya memuat customer/produk yang terpilih. Proses approve sendiri ringan — lambatnya dari halaman yang dimuat sesudahnya. |
| B1 | SO ditolak kasir tidak bisa dipakai lagi | **Sudah.** Penawaran yang SO-nya **ditolak/dibatalkan** dilepas dan bisa dipilih lagi saat buat SO (dulu kode mengecek status `rejected/cancelled`, padahal SO memakai `reject/canceled`). Detail SO yang ditolak/dibatalkan punya tombol **"Salin ke SO Baru"** (isi form SO dari SO lama). |
| B2 | Nomor referensi penawaran tidak muncul di Buat SO | **Sudah.** Memilih penawaran memuat ulang form dengan customer, alamat, dan item penawaran terisi; tampil "Referensi Penawaran: …". Detail SO menautkan nomor penawaran. |
| B3 | Tipe pajak tidak tampil di SO | **Sudah.** Form SO punya pilihan **Tipe Pajak** (Tanpa Pajak / Eksklusif / Inklusif) + ringkasan DPP & PPN; tersimpan per item (`tipe_pajak`, `tax` = tarif PPN aktif) sehingga invoice mengikuti. Detail SO menampilkan tipe pajak, DPP Nilai Lain, PPN. Diskon per item juga bisa diisi. |
| B4 | Saldo titip customer COD ada di mana? | **Sudah.** **Terima Pembayaran** → pilih customer → bila tidak ada tagihan muncul panel **"Catat sebagai Titip Dana"** (isi nominal → *Simpan Titip Dana*). Tercatat sebagai Deposit aktif, jurnal Dr Kas/Bank, Cr Deposit Pelanggan. Saldo titip tampil di pilihan customer form SO; verifikasi kasir COD memakai **seluruh** baris titip aktif (FIFO) dan mengembalikannya bila SO dibatalkan. |
| C1 | Retur setelah SJ diterima gudang tetapi stok tidak bertambah | **Sudah.** Stok bertambah saat retur **Diselesaikan** (alur: Diterima Gudang → Inspeksi QC → Setujui → Selesaikan), bukan saat "Diterima Gudang". Dulu retur tanpa **Gudang Penerima** dilewati diam-diam tetapi pesan menyatakan stok pulih; kini ditolak dengan pesan jelas, dan pesan sukses menyebut qty yang benar-benar kembali ke stok / perbaikan / ditolak. Detail retur menampilkan penjelasan alur. |
| C2 | Retur by DO (sebelum SJ) ada di mana? | **Gudang → Siapkan & Kirim** → buka DO (layar pengecekan) → **"Ada kurang / rusak"**. Hanya tersedia sebelum SJ terbit. Detail DO yang menunggu pengecekan kini punya tautan **"Buat Retur by DO"**. |

*Catatan lanjutan:* rumus DPP 11/12 di dokumen lain (invoice, PO, nota kredit, retur) belum diperiksa dalam batch ini.

---

## 9. Cara Menguji

**Aturan praktis**

- Uji lewat interaksi UI sungguhan. Mengubah properti Alpine `x-data` dari console membuat
  komponen tidak sinkron dan memunculkan "bug" palsu.
- Elemen tersembunyi `x-show` tetap muncul di `innerText`; cek `offsetParent !== null`.
- Laporan kosong belum tentu bug laporan — cek dulu datanya (mis. barang dibukukan ke gudang lain).
- Ukur, jangan menduga: ukuran payload & waktu muat membuat keluhan "lemot" bisa ditindaklanjuti.
- Setiap transaksi diverifikasi **tiga cara**: status dokumen, angka stok, dan kartu stok.
- Data uji ditandai `TEST-<inisial>-<yyyymmdd>`; batal/void boleh, hapus permanen tidak.

**Probe dari console (sesi login)**

```js
// Sapu rute untuk error server
for (const r of ROUTES) {
  const res = await fetch(r), t = await res.text();
  const err = /SQLSTATE|Unknown column|Exception|Stack trace|Whoops/.test(t);
  console.log(res.status, r, err ? '‼ERROR' : '');
}

// Deteksi x-data rusak (JSON tak ter-escape di atribut)
[...document.querySelectorAll('[x-data]')].filter(e => {
  try { new Function('return (' + e.getAttribute('x-data') + ')'); return false }
  catch { return true }
});

// Berat halaman & latensi
const t0 = performance.now(); const txt = await fetch(u).then(r => r.text());
({ ms: Math.round(performance.now() - t0), kb: Math.round(txt.length / 1024) });

// Enumerasi form sebelum mengisi — action form membuktikan aksi ada / tidak
[...document.querySelectorAll('input,select,textarea')].map(e => `${e.tagName} ${e.name}=${e.value}`);
[...document.querySelectorAll('form')].map(f => f.getAttribute('action'));
```

**Titik cek data**

- Baseline stok: `/gudang/stok?status=in_stock` — catat Fisik / Terpesan / Bebas per produk
  dan per gudang **sebelum** transaksi uji.
- Kartu stok (sumber kebenaran mutasi):
  `/gudang/stok/{productId}/kartu?start_date=2026-01-01&end_date=2026-12-31`.
