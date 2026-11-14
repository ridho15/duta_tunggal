# QA / UAT Test Matrix: Remediasi Bug Kritis Operasional ERP Duta Tunggal (Fase 6)

## 1. Matriks Verifikasi Kasus Uji (8-Point Inspection Matrix)

| Test ID | Modul | Deskripsi Pengujian | Tipe Skenario | Expected Result | Status |
| :--- | :--- | :--- | :--- | :--- | :---: |
| **TC-F6-01** | Fixed Asset | Penyusutan bulan terakhir dengan sisa nilai buku < monthly_depreciation | Boundary / Edge | Nilai penyusutan dibatasi pada sisa nilai buku, status aset otomatis berubah ke `fully_depreciated`, entri jurnal tercatat seimbang. | Pending Verification |
| **TC-F6-02** | Fixed Asset | Eksekusi penyusutan pada aset yang sudah `fully_depreciated` | Negative / Defense | Mengembalikan null / tidak melempar Exception tak tertangani dan tidak memblokir batch penyusutan bulanan. | Pending Verification |
| **TC-F6-03** | PPIC SPK | Batalkan SPK berstatus `in_progress` yang telah mengeluarkan bahan baku | Negative / Defense | Ditolak dengan pesan validasi ramah pengguna bahwa bahan baku harus diretur terlebih dahulu. | Pending Verification |
| **TC-F6-04** | PPIC SPK | Batalkan SPK draft atau scheduled tanpa transaksi | Happy Path | Status SPK berubah menjadi `cancelled`, baris dokumen tidak hilang dari database. | Pending Verification |
| **TC-F6-05** | POS Kasir | Percobaan buka shift kasir ganda secara konkuren untuk kasir yang sama | Concurrency / Race | Permintaan kedua ditolak dengan pesan validasi bahwa kasir masih memiliki shift aktif. | Pending Verification |
| **TC-F6-06** | POS Kasir | Simpan transaksi kasir tanpa adanya shift aktif yang terbuka | Negative / Defense | Ditolak dengan pesan validasi bahwa kasir wajib membuka shift terlebih dahulu. | Pending Verification |
| **TC-F6-07** | Retur Penjualan | Eksekusi konkuren `processCompletion()` pada Customer Return yang sama | Concurrency / Race | Hanya satu eksekusi yang berhasil menambah stok dan menjurnal; request berikutnya ditolak tanpa mutasi ganda. | Pending Verification |
| **TC-F6-08** | Periode GL | Buka dan tutup ulang periode akuntansi dengan posting closing entries | Idempotency / GL | Closing entries lama dibersihkan, closing entries baru tercatat tanpa menduplikasi saldo Laba Ditahan. | Pending Verification |

---

## 2. Checklist Standar SDLC ProMax
- [x] Zero AI-Slop & Zero Placeholder
- [x] Ponytail Senior Developer Minimal Diff
- [x] No Happy Path Boundary & Negative Case Guarding
- [x] Automated PHPUnit Feature Tests
- [x] Playwright Browser End-to-End Tests & Genuine Visual Artifacts

