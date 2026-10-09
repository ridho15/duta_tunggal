# Audit Mendalam ERP Duta Tunggal — Fase 3: Keuangan & Akuntansi

**Tanggal audit:** 8 Oktober 2026
**Metodologi:** Sama seperti Fase 1 & 2 — kombinasi pengujian langsung di browser dengan data riil dan verifikasi silang ke kode (grep/Read langsung, bukan dugaan). Catatan metodologi: agent audit-statis-kode latar belakang yang biasa dipakai di Fase 1 & 2 **gagal/stall setelah 1 jam** di sesi ini (tidak ada progres 600 detik) — seluruh temuan di laporan ini berasal dari verifikasi langsung saya sendiri (Tinker, grep, Read, dan interaksi browser nyata), bukan dari laporan agent.
**Cakupan:** Mesin Jurnal Otomatis (LedgerPostingService), Tutup Buku Periode Akuntansi, Kas & Bank (Vendor Payment, Customer Receipt, Bank Transfer), Laporan Keuangan (Neraca, Laba Rugi), dan isolasi multi-cabang untuk model Finance.

---

## Ringkasan Eksekutif

Mesin jurnal inti (`LedgerPostingService`) ternyata adalah salah satu bagian **paling matang** dari seluruh aplikasi yang sudah diaudit di 3 fase ini — anti-double-posting dengan `lockForUpdate()` diterapkan konsisten di semua jalur posting (Invoice, Deposit, Vendor Payment, Customer Receipt), dan Neraca terbukti otomatis seimbang karena dihitung langsung dari Buku Besar, bukan dari angka tersimpan terpisah yang bisa Out-of-sync.

Tapi fitur yang justru paling eksplisit diminta diuji di awal — **Tutup Buku Periode Akuntansi** — ternyata **sepenuhnya tidak bisa diakses dari UI mana pun**. Mekanisme penguncian memang ada dan bekerja benar di level data (dikonfirmasi di Fase 1 dan diverifikasi ulang secara live di sesi ini), tapi tidak ada satu pun tombol, menu, atau perintah untuk benar-benar menutup sebuah periode. Dan ketika periode berhasil ditutup (saya lakukan manual lewat service), mencoba memposting transaksi ke periode itu **tidak menampilkan pesan error yang informatif — melainkan layar "500 SERVER ERROR" mentah**, persis kebalikan dari yang diminta brief awal soal validasi yang ramah untuk staf non-teknis.

---

## 1. Critical Bugs & Logical Flaws

### 🔴 P0-1 — Tutup Buku Periode Akuntansi tidak punya jalur akses sama sekali (BARU, terverifikasi penuh)

**Dampak finansial/bisnis:** Ini adalah salah satu kontrol internal akuntansi paling fundamental — mengunci periode yang sudah direkonsiliasi agar tidak ada transaksi baru/mundur yang mengubah laporan yang sudah difinalisasi dan dilaporkan ke stakeholder/pajak. Tanpa jalur akses, kontrol ini **tidak pernah bisa dipakai di dunia nyata**, membuat seluruh periode akuntansi perusahaan selamanya "terbuka" — siapa pun dengan izin jurnal bisa mengubah data bulan-bulan yang sudah lama ditutup secara akuntansi.

**Bukti teknis (diverifikasi langsung):**
- `app/Services/AccountingPeriodService.php` adalah service yang lengkap dan matang: `createPeriod()`, `closePeriod()` (dengan `lockForUpdate()` dan opsi auto-generate jurnal penutup), `reopenPeriod()`, `postClosingEntries()` (bahkan menangani resolusi akun Laba Ditahan/Retained Earnings).
- **Tidak ada satu pun pemanggil** `AccountingPeriodService` di luar file itu sendiri — dikonfirmasi lewat `grep -rln "AccountingPeriodService" app/ routes/` yang hanya menghasilkan file service itu sendiri.
- **Tidak ada Filament Resource/Page** yang mereferensikan `AccountingPeriod` — dikonfirmasi lewat `grep -rln "AccountingPeriod" app/Filament/` yang kosong total.
- **Tidak ada Artisan command** terkait — `php artisan list` yang difilter kata "period/close/tutup" kosong.
- Tabel `accounting_periods` di database **kosong (0 baris)** sebelum saya mengisinya manual lewat Tinker untuk keperluan pengujian — bukti langsung bahwa fitur ini belum pernah dipakai sejak aplikasi berjalan.

**Steps to Reproduce:** Coba cari menu "Tutup Buku", "Periode Akuntansi", atau "Accounting Period" di mana pun dalam aplikasi (sidebar, Data Master, Akuntansi hub) — tidak akan ditemukan.

---

### 🔴 P0-2 — Guard tutup buku bekerja benar, tapi menampilkan "500 SERVER ERROR" mentah ke pengguna, bukan pesan yang bisa dipahami (BARU, terverifikasi live dengan screenshot)

**Dampak finansial/bisnis:** Karena P0-1, saya terpaksa menutup periode September 2026 untuk Cabang Pusat Jakarta langsung lewat `AccountingPeriodService` (bukan UI) agar bisa menguji skenario yang diminta di brief awal: "Bagaimana respon sistem jika ada koreksi transaksi mundur pada periode akuntansi cabang yang sudah di-lock?" Saya lalu mencoba membuat Journal Entry manual bertanggal 15 September 2026 (di dalam periode yang baru ditutup) lewat form resmi "Buat Journal Entry".

**Hasil: transaksi BERHASIL diblokir** (tidak ada baris jurnal baru tersimpan di database — integritas data terjaga) — tapi caranya diblokir sangat buruk bagi pengguna:
- Log aplikasi mencatat: `ClosedPeriodException: "Periode 2026-09-15 sudah ditutup."` dilempar dari `app/Models/AccountingPeriod.php:59` — **pesannya sendiri sudah bagus dan jelas**.
- Tapi exception ini **tidak pernah ditangkap** oleh lapisan Filament/Livewire manapun. Browser menerima **dua response HTTP 500**, dan layar yang muncul ke pengguna adalah kotak hitam besar bertuliskan **"500 | SERVER ERROR"** menimpa seluruh form — tanpa kata "periode", "tutup", atau petunjuk apa pun (screenshot diambil saat pengujian).
- Setelah itu, console browser mencatat rentetan error lanjutan (`Could not find Livewire component in DOM tree`) — mengindikasikan state halaman menjadi rusak setelah crash ini, kemungkinan perlu reload penuh untuk pulih.

**Perbandingan dengan temuan serupa:** Ini lebih parah dari pola "validasi backend benar tapi diam di UI" yang ditemukan di Fase 2 (limit kredit SO) — di sana setidaknya tidak ada layar error yang menakutkan. Di sini, staf Finance yang mencoba koreksi legit ke tanggal yang salah input akan melihat layar yang terlihat seperti aplikasi benar-benar rusak, bukan sekadar "coba tanggal lain".

**Steps to Reproduce:**
1. (Hanya bisa lewat Tinker karena P0-1) Tutup sebuah periode: `app(\App\Services\AccountingPeriodService::class)->closePeriod(...)`.
2. Buat Journal Entry manual bertanggal di dalam periode itu lewat UI resmi.
3. Klik "Buat" — layar "500 SERVER ERROR" muncul menimpa form.

---

### 🔴 P0-3 — Hapus Customer Receipt yang sudah berjurnal: jurnal jadi yatim permanen, bukan cuma tidak dibalik (BARU, lebih parah dari pola Fase 1/2)

**Dampak finansial/bisnis:** Ini varian baru dari pola "hapus dokumen = data hilang" yang sudah ditemukan di Fase 1 (Purchase Receipt) dan Fase 2 (Delivery Order) — tapi di sini masalahnya justru **kebalikan**: alih-alih jurnal ikut terhapus (yang sebenarnya valid secara akuntansi, lihat catatan di bawah), jurnal **tidak disentuh sama sekali**, menjadikannya catatan akuntansi yang menunjuk ke dokumen sumber yang sudah tidak ada.

**Bukti teknis (diverifikasi langsung):**
- `app/Observers/CustomerReceiptObserver.php` hanya punya 4 method: `creating`, `updated`, `created`, `__construct` — **tidak ada `deleted()` atau `deleting()` sama sekali**.
- Bandingkan dengan `app/Observers/CashBankTransferObserver.php` yang punya `deleting()` DAN `deleted()` — saat transfer bank dihapus, baris jurnal terkait **ikut dihapus** (`$entry->delete()`, baris 48). Karena Neraca/Buku Besar dihitung LANGSUNG dari agregasi tabel `journal_entries` setiap kali laporan dibuka (dikonfirmasi: footer laporan Neraca menyatakan "Data diambil dari Buku Besar... per tanggal yang dipilih" — bukan kolom saldo tersimpan terpisah), menghapus sepasang baris jurnal yang saling mengimbangi **memang secara matematis setara dengan membalikkannya** untuk kebutuhan laporan. Pola `CashBankTransfer` ini sudah BENAR.
- `CustomerReceipt` tidak melakukan ini. Perlindungan satu-satunya — `CustomerReceiptPolicy::delete()` (baris 51-59) yang memanggil `DocumentLock::blocks($customerReceipt, 'delete')` — **bergantung pada flag `sales.controls.doc_lock`** yang sudah dikonfirmasi di Fase 2 **default `false`, tidak diaktifkan di `.env`**. Artinya **saat ini**, siapa pun dengan izin `delete customer receipt` bisa menghapus penerimaan kas yang sudah terposting, dan jurnalnya (Debit Kas/Bank, Kredit Piutang) akan tetap ada selamanya menunjuk ke `source_id` yang sudah tidak eksis — memutus jejak audit sepenuhnya, bukan cuma "tidak dibalik".

**Catatan positif terkait**: `VendorPayment` (sisi hutang, kebalikan dari Customer Receipt) justru menangani ini dengan BENAR — `VendorPaymentPolicy::delete()` memblokir penghapusan kecuali status masih `draft` (bukan bergantung flag), dan `VendorPaymentObserver.php:296` punya method `reverseJournalEntries()` khusus. Ada inkonsistensi penanganan antara sisi AR (Customer Receipt, lemah) dan sisi AP (Vendor Payment, kuat) padahal keduanya setara pentingnya.

---

### 🔴 P0-4 — `AccountPayable`, `PaymentRequest`, dan `VendorPayment` tidak menerapkan `CabangScope` meski punya kolom `cabang_id` (pola sama Fase 1 & 2, kini di Finance)

**Dampak finansial/bisnis:** Persis pola P0-1 Fase 1 (`PurchaseOrder`) dan P1-2 Fase 2 (`Quotation`) — data hutang dagang dan pembayaran vendor (sangat sensitif: nama supplier, nominal, termin) berpotensi terlihat/diakses lintas cabang.

**Bukti teknis (diverifikasi langsung):**
- Dikonfirmasi lewat grep `CabangScope` di setiap file model: `AccountPayable.php` — TIDAK ADA; `PaymentRequest.php` — TIDAK ADA; `VendorPayment.php` — TIDAK ADA.
- Ketiganya DIKONFIRMASI punya kolom `cabang_id` di skema tabel (`account_payables`, `payment_requests`, `vendor_payments` — dicek langsung via `Schema::getColumnListing`).
- **Sebagai pembanding, sisi yang benar**: `JournalEntry.php`, `AccountReceivable.php`, dan `CustomerReceipt.php` (model-nya, bukan soal penghapusan di P0-3) **SEMUA sudah benar** menerapkan `CabangScope` — menunjukkan ini bukan keterbatasan arsitektur, tapi celah yang konsisten tertinggal di sisi Purchase/AP sementara sisi Sales/AR sudah diperbaiki.

---

## 2. Business Gap & Internal Control

### 🟢 Hal yang justru SUDAH SANGAT BAIK — perlu ditegaskan

Agar laporan ini adil, mesin jurnal inti ternyata adalah bagian **terbaik** dari seluruh sistem yang diaudit di 3 fase:

- **Anti-double-posting konsisten**: `LedgerPostingService::postInvoice()`, `postDeposit()`, `postVendorPayment()`, dan `postCustomerReceipt()` SEMUA memakai pola `DB::transaction()` + `lockForUpdate()->exists()` sebelum posting — pola yang sama benarnya dengan `QualityControlService` di Fase 1, diterapkan secara konsisten di seluruh mesin jurnal, bukan cuma satu tempat.
- **Validasi balance built-in**: setiap pemanggilan posting memvalidasi `debit = credit` sebelum commit (`LedgerPostingService.php` baris ~378-388), dan form Journal Entry manual punya indikator real-time "✅ Balance / ❌ Not Balance" di layar SEBELUM submit — UX yang baik untuk mencegah human error.
- **Neraca terbukti auto-balance secara live**: diuji langsung di `/admin/reports/balance-sheets` — Total Aset Rp53.508.000 = Total Liabilitas (Rp1.000.000) + Total Ekuitas (Rp52.508.000), dengan indikator "✓ Seimbang" dan catatan jelas "Data diambil dari Buku Besar per tanggal yang dipilih" (bukan dari tabel saldo terpisah yang bisa out-of-sync).
- **Laba Rugi konsisten secara formula**: diuji langsung di `/admin/reports/profit-and-losses` — Laba Kotor = Penjualan − HPP, Laba Usaha = Laba Kotor − Beban Operasional, Laba Bersih = Laba Sebelum Pajak − Pajak, semuanya cocok persis dengan angka yang ditampilkan.
- **Reversal resmi untuk Invoice**: `LedgerPostingService::reverseInvoiceJournalEntries()` dan `reverseJournalEntries()` adalah mekanisme pembalikan jurnal yang proper (bukan soft-delete langsung), dipakai juga oleh `OtherSaleResource`/`OtherSaleService` — pola yang seharusnya dicontoh untuk Customer Receipt (P0-3).

### 🟡 P2-1 — Tidak ada cara mengetahui periode mana yang "seharusnya" sudah ditutup

Karena P0-1, bahkan jika fitur tutup buku diaktifkan nanti lewat UI, saat ini tidak ada indikator di dashboard/laporan mana pun yang menunjukkan status periode (terbuka/tertutup) secara visual — Finance Manager tidak punya cara melihat "periode mana yang masih perlu ditutup" tanpa query database langsung.

---

## 3. Action Priority Checklist

| Prioritas | Temuan | Area |
|---|---|---|
| **P0 — Kritis** | Bangun Filament Resource/Page untuk `AccountingPeriod` (buat, tutup, buka kembali periode) — service backend-nya sudah siap pakai (`AccountingPeriodService`) | Tutup buku / kontrol internal |
| **P0 — Kritis** | Tangkap `ClosedPeriodException` (dan exception serupa) di layer Filament/Livewire agar tampil sebagai notifikasi ramah, bukan 500 mentah | UX kritis untuk staf Finance |
| **P0 — Kritis** | Tambahkan `deleted()`/pembalikan resmi ke `CustomerReceiptObserver`, contoh polanya sudah ada di `CashBankTransferObserver`/`VendorPaymentObserver` | Integritas jurnal, audit trail |
| **P0 — Kritis** | Tambahkan `CabangScope` ke `AccountPayable`, `PaymentRequest`, `VendorPayment` | Isolasi multi-cabang |
| **P2 — Sedang** | Tambahkan indikator status periode akuntansi (terbuka/tertutup) di dashboard Finance setelah P0-1 selesai | Visibilitas kontrol internal |

---

## Lampiran A — Hasil Positif Tambahan (Terverifikasi)

1. Validasi balance jurnal (debit=kredit) ditegakkan di level service DAN ditampilkan real-time di form sebelum submit.
2. Guard anti-double-posting (`lockForUpdate`) konsisten di seluruh jalur posting utama (Invoice, Deposit, Vendor Payment, Customer Receipt) — bukan cuma di satu tempat seperti temuan parsial di Fase 1.
3. `VendorPayment` menangani pembatalan dengan benar: diblokir di level policy (bukan bergantung flag) + punya method reversal jurnal khusus.
4. `CashBankTransfer` membersihkan jurnal terkait dengan benar saat dihapus — pola yang matematis valid karena laporan keuangan dihitung live dari Buku Besar.
5. Neraca dan Laba Rugi terverifikasi akurat dan konsisten secara formula lewat pengujian langsung, bukan cuma dugaan dari kode.

---

*Bagian terakhir dari audit 3 fase. Lanjutan: dokumen sintesis/rekap lintas-fase jika diminta. Laporan sebelumnya: `docs/AUDIT-FASE1-PROCUREMENT-QC-INVENTORY-07OKT2026.md`, `docs/AUDIT-FASE2-SALES-DELIVERY-MANUFAKTUR-08OKT2026.md`.*
