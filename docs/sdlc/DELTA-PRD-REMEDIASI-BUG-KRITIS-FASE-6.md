# DELTA PRD: Remediasi Bug Kritis Operasional ERP Duta Tunggal (Fase 6)

## 1. Ringkasan Eksekutif & Status
- **ID Proyek:** DUTA-ERP-AUDIT-PHASE-6
- **Tanggal Efektif:** 14 November 2026
- **Standar Enforced:** SDLC ProMax Kit (*Ponytail*, *Antislop*, *No Happy Path*, *Verification Data*, Playwright E2E Verification).
- **Tujuan:** Memperbaiki 5 bug kritis operasional lintas modul (Aset Tetap, Manufaktur SPK, Kasir POS Shift, Retur Penjualan, dan Tutup Buku Periode GL) yang dapat menghentikan batch penyusutan, merusak integritas data barang jadi vs bahan baku, memicu multi-shift kasir orfan, menduplikasi penambahan stok retur, dan menduplikasi jurnal penutup laba ditahan.

---

## 2. Cakupan Remediasi & Spesifikasi Teknis

### [BUG-NEW-06] Fixed Asset Final Month Depreciation Premature Abort & Infinite Active State
- **Berkas Target:** `app/Services/AssetDepreciationService.php`
- **Permasalahan:** Ketika sisa nilai buku pada bulan terakhir masa manfaat aset lebih kecil daripada penyusutan standar bulanan, pengecekan melempar Exception `Aset sudah disusutkan penuh` sebelum mencatat entri dan sebelum memanggil `updateAccumulatedDepreciation()`. Aset gagal disusutkan dan statusnya menggantung di `active` selamanya.
- **Solusi Rekayasa:**
  - Hitung sisa nilai yang dapat disusutkan: `$remaining = max(0.0, ($asset->purchase_cost - $previousTotal) - $asset->salvage_value);`.
  - Jika `$remaining <= 0.001`, transisikan status aset langsung ke `fully_depreciated` dan kembalikan `null`.
  - Jika `$remaining > 0`, gunakan nominal `$amount = min($asset->monthly_depreciation, $remaining);` untuk dibukukan pada jurnal penyusutan terakhir, lalu panggil `updateAccumulatedDepreciation()` agar status berpindah menjadi `fully_depreciated`.

### [BUG-NEW-07] PPIC Manufaktur SPK Cancel Route Permanently Deletes Record & Cascades Production Deletion
- **Berkas Target:** `app/Http/Controllers/Manufaktur/ManufacturingOrderController.php`
- **Permasalahan:** Fungsi `cancel()` memanggil `$manufacturingOrder->delete()`, memicu cascading soft delete pada `productions` dan `journalEntries`. Jika SPK sudah berjalan dengan bahan baku terbit (`MaterialIssue` selesai) atau produksi barang jadi sudah lolos QC, pembatalan ini membuang SPK dan produksi tanpa memulihkan stok fisik atau mengembalikan bahan baku.
- **Solusi Rekayasa:**
  - Tambahkan validasi penolakan pembatalan jika SPK memiliki `completedMaterialIssues` aktif atau `productions` yang sudah lolos QC.
  - Pada `cancel()`, ubah status model menjadi `cancelled` tanpa memanggil method `delete()`.

### [BUG-NEW-08] POS Shift Opening Race Condition & Transaksi POS Tanpa Shift
- **Berkas Target:** `app/Services/PosShiftService.php` & `app/Http/Controllers/Kasir/PosController.php`
- **Permasalahan:** `PosShiftService::openShift()` tidak melakukan penguncian atomik sehingga klik ganda menciptakan 2 shift aktif untuk 1 kasir. Selain itu, `PosController::store()` mengizinkan pembuatan order saat `$activeShift` bernilai `null`.
- **Solusi Rekayasa:**
  - Bungkus `openShift()` dalam `DB::transaction()` dengan pengecekan baris terkunci: `PosShift::where('user_id', $user->id)->where('status', 'open')->lockForUpdate()->first()`.
  - Pada `PosController::store()`, jika `$activeShift` tidak ditemukan, lempar `ValidationException` yang mewajibkan kasir membuka shift terlebih dahulu.

### [BUG-NEW-09] Retur Penjualan Concurrency Double Execution
- **Berkas Target:** `app/Services/CustomerReturnService.php`
- **Permasalahan:** Pengecekan `stock_restored_at` pada `processCompletion()` berada di luar transaksi tanpa `lockForUpdate()`. Jika dua proses memproses retur bersamaan, kuantitas stok bertambah ganda dan jurnal balik piutang/deposit tercatat ganda.
- **Solusi Rekayasa:**
  - Di dalam `DB::transaction()`, kunci baris retur secara atomik menggunakan `lockForUpdate()`.
  - Validasi ulang bahwa `stock_restored_at === null` dan `status === STATUS_APPROVED`.

### [BUG-NEW-10] Duplikasi Closing Entries Periode Akuntansi
- **Berkas Target:** `app/Services/AccountingPeriodService.php`
- **Permasalahan:** Saat periode dibuka kembali (`reopenPeriod()`) dan ditutup ulang (`postClosingEntries()`), jurnal penutup lama tidak dihapus dan perolehan saldo tidak memfilter entri tipe `closing`, menyebabkan penggelembungan saldo Laba Ditahan.
- **Solusi Rekayasa:**
  - Pada `postClosingEntries()`, lakukan pembersihan entri penutup lama: `JournalEntry::where('source_type', AccountingPeriod::class)->where('source_id', $period->id)->where('journal_type', 'closing')->delete();`.
  - Pada `getAccountBalanceForPeriod()`, tambahkan filter `where('journal_type', '!=', 'closing')`.

---

## 3. Kriteria Penerimaan (Acceptance Criteria)
1. Seluruh automated Feature Test lolos 100% tanpa regresi.
2. Tidak ada placeholder atau komentar generik (*Antislop*).
3. Kode perubahan minimal dan langsung mengatasi akar masalah (*Ponytail*).
4. Skenario batas negatif teruji (*No Happy Path*).
5. Pengujian Playwright Chromium menghasilkan bukti tangkapan layar valid.

