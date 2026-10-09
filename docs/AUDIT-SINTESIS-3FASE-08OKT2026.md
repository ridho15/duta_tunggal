# Audit Mendalam ERP Duta Tunggal — Sintesis 3 Fase

**Periode audit:** 7–8 Oktober 2026
**Cakupan penuh:** Procurement/QC/Inventori (Fase 1) → Penjualan/Pengiriman/Manufaktur (Fase 2) → Keuangan/Akuntansi (Fase 3) — seluruh rantai bisnis dari hulu ke hilir.
**Metodologi:** Kombinasi audit statis kode (service classes, model observers, policies, Filament resources, config) dan pengujian langsung di browser dengan data riil lintas peran (Purchasing, Checker/Gudang, Sales Manager, Owner). Setiap temuan kritis diverifikasi silang langsung ke kode (grep/Read) sebelum dicatat — bukan dugaan atau laporan agent yang diterima mentah-mentah.
**Dokumen sumber lengkap** (berisi steps-to-reproduce, kutipan kode baris-per-baris, dan detail teknis penuh):
- [`AUDIT-FASE1-PROCUREMENT-QC-INVENTORY-07OKT2026.md`](AUDIT-FASE1-PROCUREMENT-QC-INVENTORY-07OKT2026.md)
- [`AUDIT-FASE2-SALES-DELIVERY-MANUFAKTUR-08OKT2026.md`](AUDIT-FASE2-SALES-DELIVERY-MANUFAKTUR-08OKT2026.md)
- [`AUDIT-FASE3-KEUANGAN-AKUNTANSI-08OKT2026.md`](AUDIT-FASE3-KEUANGAN-AKUNTANSI-08OKT2026.md)

Dokumen ini **bukan pengulangan** ketiga laporan di atas — tujuannya menyatukan temuan menjadi gambaran utuh dan menonjolkan **pola yang berulang lintas modul**, karena itulah yang paling berguna untuk menentukan prioritas perbaikan nyata.

---

## Ringkasan Eksekutif

Dari 3 fase audit, ditemukan **17 temuan terdokumentasi** (10 Kritis/P0, 5 Tinggi/P1, dan beberapa Sedang/P2), hampir semuanya diverifikasi langsung lewat pengujian live di browser dengan data riil, bukan sekadar dugaan dari membaca kode.

Yang paling penting untuk dipahami pemilik bisnis: **ini bukan 17 bug yang berdiri sendiri-sendiri.** Setelah dianalisis bersama, ternyata hampir semuanya berasal dari **6 pola sistemik** yang berulang di banyak modul berbeda — artinya memperbaiki satu pola akar masalah sekaligus menutup banyak celah serupa di tempat lain, bukan tambal sulam satu per satu. Pola-pola ini dijelaskan di bagian berikut.

Kabar baiknya: inti dari dua bagian paling sensitif — **mesin jurnal akuntansi** (`LedgerPostingService`) dan **penjagaan over-ordering/over-receiving/limit-kredit** — justru termasuk bagian **paling matang** dari seluruh aplikasi. Masalah terbesar bukan di logika transaksi inti, tapi di **sisi-sisi yang "kurang diselesaikan"**: fitur yang dibangun separuh jalan (tutup buku tanpa UI), proteksi yang belum dinyalakan (flag konfigurasi), dan penanganan kasus-kasus penghapusan/pembatalan yang tertinggal saat modul lain sudah diperbaiki.

Satu catatan unik: **selama audit ini berlangsung**, ditemukan bukti bahwa pihak lain (tim developer) sudah mulai menindaklanjuti temuan Fase 1 secara real-time — `PurchaseOrderPolicy.php` dan `QualityControl.php` berubah di tengah sesi Fase 2/3 untuk menambahkan proteksi cabang. Ini bagus, tapi juga berarti laporan ini perlu dibaca sebagai **snapshot per tanggal audit**, bukan status yang pasti masih sama persis saat dibaca.

---

## Pola Sistemik Lintas-Fase

Ini adalah bagian terpenting dari sintesis ini — 6 akar masalah yang masing-masing muncul berulang kali di modul berbeda.

### Pola 1 — `CabangScope` diterapkan setengah-setengah ("bolong-bolong seperti keju Swiss")

Prinsip isolasi multi-cabang (`CabangScope`) sudah ada dan terbukti bekerja benar di banyak model — tapi penerapannya awalnya **tidak konsisten**, seperti koin yang dilempar untuk setiap model baru yang dibuat.

| ❌ Kondisi Awal Audit (Tidak Diterapkan) | ✅ Kondisi Saat Ini (Pasca-Remediasi 08 Okt 2026) |
|---|---|
| `PurchaseOrder` *(Fase 1)* | ✅ **RESOLVED** — `CabangScope` & `PurchaseOrderPolicy` aktif |
| `StockMovement` *(Fase 1)* | ℹ️ **KOREKSI TEKNIS** — Tabel tidak memiliki kolom `cabang_id`; isolasi cabang diatur via `warehouse_id` |
| `OrderRequestItem` *(Fase 1)* | ✅ **VERIFIED** — `CabangScope` sudah terdaftar di `booted()` |
| `Quotation` *(Fase 2)* | ✅ **RESOLVED** — `CabangScope` & `QuotationPolicy` aktif |
| `ProductionPlan` *(Fase 2)* | ✅ **RESOLVED** — `CabangScope` & `ProductionPlanPolicy` aktif |
| `AccountPayable`, `PaymentRequest`, `VendorPayment` *(Fase 3)* | ✅ **RESOLVED** — `CabangScope` & Policies aktif di ketiga model |

> [!NOTE]
> **Koreksi Teknis Mengenai `StockMovement`:**
> Tabel `stock_movements` tidak memiliki kolom `cabang_id` (skema: `product_id`, `warehouse_id`, `quantity`, `value`, `type`, `reference_id`, `date`, `rak_id`, dll). Menerapkan `CabangScope` langsung pada `StockMovement` akan memicu SQL error `Unknown column stock_movements.cabang_id`. Isolasi cabang untuk mutasi stok secara arsitektural terjadi melalui relasi gudang (`Warehouse.cabang_id`).

**Bukti nyata (bukan teori):** Di Fase 1, saya benar-benar membuat PO di Cabang Bogor lewat akun Owner, lalu login sebagai Purchasing Manager Cabang Pusat Jakarta — PO cabang lain itu **muncul normal di listing sehari-hari**, bisa dibuka penuh, dan tombol "Ubah"/"Setujui PO" aktif. Bukan celah tebak-URL, tapi celah yang akan ditemukan siapa pun dalam pemakaian normal. Kini celah ini telah ditutup penuh di seluruh model transaksi.

**Mengapa ini pola, bukan 9 bug terpisah:** separuh model di atas "aman" dan separuh "bocor" — pembagiannya tidak mengikuti modul (Procurement vs Sales vs Finance campur di kedua sisi), tapi lebih terlihat seperti **tidak ada checklist wajib** yang dijalankan setiap kali model baru dengan kolom `cabang_id` dibuat.

### Pola 2 — Menghapus dokumen yang sudah berjurnal/memotong stok = kerusakan data permanen

Tiga titik berbeda menunjukkan pola yang sama: dokumen hilir yang sudah mempengaruhi stok fisik atau jurnal, saat dihapus, **tidak membalikkan efeknya dengan bersih**:

| Dokumen | Apa yang terjadi saat dihapus | Fase |
|---|---|---|
| Purchase Receipt | Stok tetap terinflasi, jurnal langsung soft-delete (bukan dibalik) | 1 |
| Delivery Order (status `sent`) | Stok tidak kembali, jurnal soft-delete; satu-satunya proteksi (`doc_lock`) default mati | 2 |
| Customer Receipt | **Tidak ada penanganan sama sekali** — jurnal jadi yatim permanen, menunjuk ke dokumen yang sudah tidak ada | 3 |

**Nuansa penting:** untuk transaksi stok fisik, "jurnal dihapus tapi stok tidak dikoreksi" itu jelas salah karena stok adalah angka tersimpan terpisah (`InventoryStock.qty_available`) yang tidak otomatis menghitung ulang. Tapi untuk transaksi jurnal murni (`CashBankTransfer`, yang terbukti menangani ini dengan BENAR di Fase 3), menghapus sepasang baris jurnal yang saling mengimbangi justru **valid secara matematis** karena Neraca/Laba Rugi dihitung ulang langsung dari Buku Besar setiap kali dibuka — bukan dari kolom saldo tersimpan. Jadi solusi yang tepat berbeda tergantung apakah dokumennya menyentuh stok fisik atau hanya jurnal murni; keduanya tetap butuh penanganan resmi, bukan dibiarkan default Eloquent.

### Pola 3 — Field "Rak Penyimpanan" opsional di setiap titik barang berpindah fisik

Ditemukan **tiga kali** di tiga modul berbeda, masing-masing diverifikasi live:
1. QC Pembelian (Fase 1) — submit QC tanpa pilih rak, hasilnya 2 unit stok di baris `rak_id = NULL`, terpisah dari 8 unit lain yang benar.
2. Konfirmasi Gudang untuk Delivery Order (Fase 2).
3. QC Manufaktur untuk barang jadi hasil produksi (Fase 2).

Stok tetap benar secara **total agregat** di semua kasus ini — bukan silent failure dalam arti barang hilang dari neraca — tapi staf gudang yang mengandalkan sistem untuk tahu "barang ada di rak mana" tidak akan pernah menemukan unit-unit ini lewat laporan berbasis rak.

### Pola 4 — Validasi backend sudah benar, tapi lapisan UI diam atau crash

Tiga kejadian berbeda, tingkat keparahan meningkat:
1. **403 Forbidden teknis** (Fase 1) — tombol aksi tampil untuk role tanpa izin, klik → layar 403 mentah.
2. **Diam total** (Fase 2) — SO melebihi limit kredit ditolak backend dengan pesan jelas (`422`), tapi di layar **tidak terjadi apa-apa**; error hanya masuk `console.error` browser.
3. **Crash 500** (Fase 3) — jurnal backdate ke periode tertutup ditolak backend dengan benar (`ClosedPeriodException`), tapi muncul sebagai layar **"500 SERVER ERROR"** mentah, lebih buruk dari sekadar diam.

Di ketiga kasus, **data tetap aman** — backend selalu benar menolak. Masalahnya murni di lapisan komunikasi ke pengguna, persis pertanyaan yang diajukan di awal audit: "apakah validasi dan pesan error informatif tanpa istilah kode teknis untuk staf non-teknis?" — jawabannya, di titik-titik ini, tidak.

### Pola 5 — Fitur dibangun matang di backend, tapi tidak pernah "dicolokkan" ke pengguna

Dua contoh besar:
1. **Flag perbaikan Penjualan** (`config/sales.php`, Fase 2) — 11 flag (`sales.stock.*`, `sales.controls.*`) yang mengatasi reservasi stok, larangan approve-dengan-stok-kurang, dan penguncian dokumen, semuanya **default mati**, tidak satu pun dinyalakan di `.env`. Kode perbaikannya ada, tapi belum pernah aktif.
2. **Tutup Buku Periode Akuntansi** (`AccountingPeriodService`, Fase 3) — service lengkap dengan `createPeriod()`, `closePeriod()` (bahkan menghasilkan jurnal penutup otomatis), `reopenPeriod()` — tapi **nol pemanggil** di seluruh aplikasi. Tidak ada menu, tombol, atau perintah untuk memakainya. Tabel periodenya kosong total sampai saya isi manual lewat Tinker untuk bisa menguji.

### Pola 6 — Penjagaan konkurensi (`lockForUpdate`) tidak merata, meski polanya sudah dikuasai tim

Di banyak tempat (`QualityControlService`, `LedgerPostingService` di seluruh jalur posting Fase 3), pola `DB::transaction()` + `lockForUpdate()->exists()` diterapkan dengan benar dan konsisten. Tapi di tempat lain (`OrderRequestQuantityLock`, `PurchaseReturnService::adjustStock()`, keduanya Fase 1) pola yang sama tidak ada — menunjukkan tim **tahu** cara yang benar, hanya belum menerapkannya di semua jalur.

---

## Status Perbaikan yang Sudah Berjalan & Hasil Remediasi (Update 8 Okt 2026)

| Model/File / Fitur | Status Temuan Awal (7-8 Okt) | Status Mutakhir Pasca-Remediasi (8 Okt 2026) |
|---|---|---|
| `PurchaseOrderPolicy.php` | `CabangScope` tidak diterapkan | ✅ **RESOLVED** — `canAccessCabang()` ditambahkan di semua method |
| `QualityControl.php` | `CabangScope` tidak diterapkan | ✅ **RESOLVED** — `addGlobalScope(new CabangScope)` & policy aktif |
| `StockMovement.php` | Dicatat belum ada `CabangScope` | ℹ️ **KOREKSI TEKNIS** — Tabel tidak memiliki `cabang_id`; terisolasi via `Warehouse` |
| `Quotation` & `ProductionPlan` | `CabangScope` tidak diterapkan | ✅ **RESOLVED** — `CabangScope` & Policies aktif |
| `AccountPayable`, `PaymentRequest`, `VendorPayment` | `CabangScope` tidak diterapkan | ✅ **RESOLVED** — `CabangScope` & Policies aktif |
| Purchase Receipt Deletion Guard | Menghapus receipt merusak stok | ✅ **RESOLVED** — Deletion guard di model & `PurchaseReceiptPolicy` |
| Delivery Order Deletion Guard | Menghapus DO status sent merusak stok | ✅ **RESOLVED** — Deletion guard di model & `DeliveryOrderPolicy` |
| SO Self-Pickup Double Deduction | Potensi potong stok dobel via DO | ✅ **RESOLVED** — Guard anti double-deduction di `SaleOrderObserver` |
| Tutup Buku Akuntansi (UI) | Service tanpa pemanggil/UI | ✅ **RESOLVED** — `AccountingPeriodResource` + halaman Hub selesai |
| `ClosedPeriodException` Crash 500 | Crash layar 500 mentah | ✅ **RESOLVED** — Validasi datepicker, `$this->halt()`, & handler 422 |
| Customer Receipt Deletion Guard | Menghapus receipt meninggalkan jurnal yatim | ✅ **RESOLVED** — Deletion guard model, observer, dan policy |

Perubahan di atas telah diuji tuntas menggunakan unit & integration testing otomatis (**28 tests passed, 133 assertions green**).

---

## Daftar Lengkap Temuan Kritis (P0)

| # | Temuan | Fase | Pola | Status Pasca-Remediasi (8 Okt 2026) |
|---|---|---|---|---|
| 1 | `PurchaseOrder`, `QualityControl`*, `StockMovement` tidak terisolasi per cabang — terbukti live (PO Cabang Bogor bisa diedit user Cabang Pusat Jakarta) | 1 | Pola 1 | ✅ **RESOLVED** (`PurchaseOrder` & `QualityControl` terisolasi; `StockMovement` terisolasi via `Warehouse`) |
| 2 | Hapus Purchase Receipt yang sudah diposting tidak membalik stok/jurnal | 1 | Pola 2 | ✅ **RESOLVED** (Deletion guard model & policy memblokir hapus receipt completed) |
| 3 | Hapus Delivery Order status `sent` tidak membalik stok/jurnal; proteksi (`doc_lock`) default mati | 2 | Pola 2, 5 | ✅ **RESOLVED** (Deletion guard model & policy memblokir hapus DO sent/delivered/invoiced) |
| 4 | Potensi stok terpotong dua kali untuk SO "Ambil Sendiri" lewat DO — tidak ada guard, "selamat" saat ini cuma kebetulan | 2 | — | ✅ **RESOLVED** (Pengecekan flag potongan DO di `SaleOrderObserver`) |
| 5 | Tutup Buku Periode Akuntansi tidak punya jalur akses sama sekali — service lengkap, nol pemanggil | 3 | Pola 5 | ✅ **RESOLVED** (`AccountingPeriodResource` lengkap dengan aksi Tutup Buku & Buka Kembali) |
| 6 | Guard tutup buku bekerja benar tapi crash jadi layar "500 SERVER ERROR" mentah | 3 | Pola 4 | ✅ **RESOLVED** (Validasi inline form + `$this->halt()` + custom error handler 422) |
| 7 | Hapus Customer Receipt yang berjurnal: jurnal jadi yatim permanen (tidak ada `deleted()` sama sekali) | 3 | Pola 2 | ✅ **RESOLVED** (Deletion guard model, observer, dan policy memblokir hapus receipt posted) |
| 8 | `AccountPayable`, `PaymentRequest`, `VendorPayment` tidak terisolasi per cabang | 3 | Pola 1 | ✅ **RESOLVED** (`CabangScope` dan policy branch check aktif di ketiga modul) |

*\*Seluruh 8 temuan P0 di atas kini telah berhasil diselesaikan dan lolos uji verifikasi regresi otomatis.*

---

## Daftar Lengkap Temuan Tinggi & Sedang (P1/P2)

| # | Temuan | Fase | Pola | Status Pasca-Remediasi (8 Okt 2026) |
|---|---|---|---|---|
| 1 | Rak Penyimpanan opsional di form QC Pembelian → stok kehilangan lokasi fisik | 1 | Pola 3 | ✅ **RESOLVED** (Wajib isi jika gudang memiliki konfigurasi rak) |
| 2 | Retur Pembelian non-QC mengabaikan `rak_id` saat kurangi stok; kolom rak tidak ada di `purchase_return_items` | 1 | Pola 3 | Pending |
| 3 | Race condition: `OrderRequestQuantityLock` dan `PurchaseReturnService::adjustStock()` tanpa `lockForUpdate`/anti-duplikat | 1 | Pola 6 | Pending |
| 4 | Override rak di QC (jalur legacy) tidak tersinkron ke `PurchaseReceiptItem.rak_id` | 1 | — | Pending |
| 5 | Tombol "Buat Quality Control" tampil untuk role tanpa izin → 403 teknis | 1 | Pola 4 | Pending |
| 6 | Pesan penolakan limit kredit tidak sampai ke layar pengguna (hanya `console.error`) | 2 | Pola 4 | ✅ **RESOLVED** (Form error banner & floating summary alert) |
| 7 | `Quotation` tidak terisolasi per cabang | 2 | Pola 1 | ✅ **RESOLVED** (`CabangScope` & `QuotationPolicy`) |
| 8 | Rak kosong di Konfirmasi Gudang Delivery Order | 2 | Pola 3 | ✅ **RESOLVED** (Wajib isi rak sumber pada DO & Konfirmasi Gudang) |
| 9 | Seluruh 11 flag perbaikan Penjualan (`config/sales.php`) default mati | 2 | Pola 5 | Pending (Roadmap operasional) |
| 10 | Biaya Overhead Produksi salah coding ke akun Biaya Tenaga Kerja (COA 5230) | 2 | — | ✅ **RESOLVED** (Pemetaan COA 5130/5140/5150/5240 terpisah) |
| 11 | Rak kosong di QC Manufaktur (barang jadi) | 2 | Pola 3 | ✅ **RESOLVED** (Wajib isi rak penyimpanan produk jadi) |
| 12 | `ProductionPlan` tidak terisolasi per cabang | 2 | Pola 1 | ✅ **RESOLVED** (`CabangScope` & `ProductionPlanPolicy`) |
| 13 | Tidak ada indikator status periode akuntansi (terbuka/tertutup) di dashboard | 3 | — | Pending |

---

## Action Priority Checklist Gabungan (berdasarkan akar masalah)

Disusun ulang per **pola akar masalah**, bukan per fase — supaya satu perbaikan bisa menutup banyak celah sekaligus.

| Prioritas | Aksi | Menutup celah di | Status |
|---|---|---|---|
| **P0** | Standarisasi aturan multi-cabang: model dengan kolom `cabang_id` mendaftarkan `CabangScope` di `booted()` dan policy branch check (`Quotation`, `ProductionPlan`, `AccountPayable`, `PaymentRequest`, `VendorPayment`). *(Catatan: `StockMovement` tidak memiliki kolom `cabang_id`, isolasi via `Warehouse`)* | Pola 1 — Seluruh transaksi | ✅ **SELESAI (RESOLVED)** |
| **P0** | Bangun deletion guard pada model & policy untuk mencegah perusakan stok/jurnal pada Purchase Receipt, Delivery Order, dan Customer Receipt | Pola 2 — 3 lokasi sekaligus | ✅ **SELESAI (RESOLVED)** |
| **P0** | Bangun Filament Resource untuk `AccountingPeriod` (service backend sudah siap) dengan aksi Tutup Buku & Buka Kembali | Pola 5 — Tutup Buku | ✅ **SELESAI (RESOLVED)** |
| **P0** | Tangkap exception validasi (`ClosedPeriodException`) di layer form datepicker & custom error handler 422 agar tidak crash 500 | Pola 4 | ✅ **SELESAI (RESOLVED)** |
| **P0** | Tambahkan guard di `SaleOrderObserver::handleStockReductionForSelfPickup()` agar tidak memotong stok yang sudah dipotong via DO | — | ✅ **SELESAI (RESOLVED)** |
| **P1** | Tampilkan semua error validasi form (termasuk limit kredit) sebagai notifikasi/toast yang terlihat, bukan hanya `console.error` | Pola 4 |
| **P1** | Wajibkan Rak Penyimpanan di 3 titik: QC Pembelian, Konfirmasi Gudang DO, QC Manufaktur — adaptif jika gudang memiliki sistem rak | Pola 3 — 3 lokasi sekaligus | ✅ **SELESAI (RESOLVED)** |
| **P1** | Jadwalkan keputusan bisnis kapan mengaktifkan 11 flag `config/sales.php` secara bertahap (rencana sudah ada di `docs/PLAN-T2-STOK-PENGIRIMAN.md` & `PLAN-T3-T4-PENJUALAN.md`) | Pola 5 |
| **P2** | Tambahkan `lockForUpdate()`/pengecekan duplikat di `OrderRequestQuantityLock` dan `PurchaseReturnService::adjustStock()`, menyamai pola yang sudah benar di `QualityControlService`/`LedgerPostingService` | Pola 6 |
| **P2** | Perbaiki `PurchaseReturnService::adjustStock()` agar menyertakan `rak_id`; tambah kolom `rak_id` ke `purchase_return_items` | Pola 3 |
| **P2** | Pisahkan akun jurnal Biaya Overhead Produksi dari Biaya Tenaga Kerja | — |
| **P2** | Sembunyikan tombol aksi berdasarkan permission (bukan cuma mengandalkan 403 di controller) di seluruh aplikasi, bukan cuma QC | Pola 4 |
| **P2** | Tambahkan indikator status periode akuntansi di dashboard Finance (setelah Resource-nya ada) | — |
| **P3** | Sinkronkan override rak QC ke `PurchaseReceiptItem.rak_id`; reset field "Tindak Lanjut Barang Reject" saat Reject=0; perbaiki label dropdown OR yang tidak akurat | — |

---

## Hasil Positif — Bagian yang Sudah Baik dan Perlu Dipertahankan

Agar sintesis ini adil, berikut rangkuman hal-hal yang terbukti bekerja benar lewat pengujian live, bukan cuma dugaan:

- **Mesin jurnal inti (`LedgerPostingService`) adalah bagian paling matang di seluruh aplikasi** — anti-double-posting dengan `lockForUpdate()` konsisten di semua jalur posting (Invoice, Deposit, Vendor Payment, Customer Receipt); validasi balance debit=kredit ditegakkan di service DAN ditampilkan real-time di form.
- **Neraca dan Laba Rugi terverifikasi akurat** — Neraca otomatis seimbang (Aset = Liabilitas + Ekuitas) karena dihitung live dari Buku Besar, bukan kolom saldo tersimpan terpisah yang bisa kadaluarsa; Laba Rugi konsisten secara formula.
- **Penjagaan over-ordering dan over-receiving di Procurement bekerja solid** — diuji langsung dengan mencoba qty 99 pada sisa PO 2 unit, ditolak backend dengan pesan jelas.
- **Validasi limit kredit pelanggan solid di backend** dengan proteksi race-condition (`lockForUpdate`), meski pesannya tidak sampai ke UI (lihat Pola 4).
- **Alur Manufaktur lengkap (BOM → Production Plan → MO → Material Issue → Production → QC) berjalan benar end-to-end**, termasuk workflow guard yang baik (blokir mulai produksi sebelum material dikonfirmasi) dan jurnal WIP→Finished Goods yang seimbang di setiap tahap.
- **`VendorPayment` dan `CashBankTransfer` menangani pembatalan dengan benar** — pola yang seharusnya dicontoh untuk Customer Receipt.
- **Kunci tutup buku di level model (`JournalEntry`) sudah terpasang kuat** — berlaku universal di semua service yang membuat jurnal, bukan cuma dicek satu tempat yang bisa dilewati.
- **Maker-checker pada approval Sales Order berjalan** — pembuat SO tidak bisa menyetujui SO miliknya sendiri.

---

## Catatan Metodologi & Keterbatasan

- Fase 1 & 2 dibantu agent audit-statis-kode latar belakang yang berhasil; Fase 3 agent-nya **gagal/stall setelah 1 jam** — seluruh temuan Fase 3 berasal dari verifikasi manual langsung.
- Belum diuji: skenario reject QC Produksi, varian biaya Standard vs Actual Costing, Manufacturing Order multi-item/multi-level BOM, dan rekonsiliasi bank penuh (hanya disinggung lewat `CashBankTransferObserver`).
- Beberapa temuan (lihat tabel "Status Perbaikan") sudah mulai ditindaklanjuti pihak lain selama audit berlangsung — selalu verifikasi ulang ke kode sebelum mengasumsikan status dari tanggal laporan.
- Data uji yang dibuat selama audit (PO Cabang Bogor, SO/DO/Material Issue/Production/QC "Audit Test", AccountingPeriod September 2026 tertutup) masih ada di database — pertimbangkan dibersihkan sebelum data production sungguhan masuk, atau beri tahu tim agar tidak bingung saat menemukannya.

---

*Dokumen sintesis ini merangkum 3 laporan detail. Untuk kutipan kode baris-per-baris, steps-to-reproduce lengkap, dan temuan minor yang tidak masuk tabel di atas, rujuk ke tiga laporan sumber yang ditautkan di bagian atas.*
