# QA Test Matrix & UAT Sign-off Protocol (Fase 4 & Fase 5)

Matriks Pengujian Jaminan Mutu (*Quality Assurance*) dan Berita Acara Uji Keberterimaan Pengguna (*User Acceptance Testing*) untuk 5 Bug Kritis Operasional ERP Duta Tunggal.

---

## Bagian A: Matriks Pengujian Teknis QA (No Happy Path & Concurrency)

| Test ID | Modul / Skenario | Skenario Uji (Edge Cases & No Happy Path) | Kategori | Hasil yang Diharapkan | Status | Catatan Temuan |
| :--- | :--- | :--- | :--- | :--- | :---: | :--- |
| **QA-P4-01** | Vendor Payment | Dua transaksi pembayaran dikirim bersamaan untuk melunasi invoice bernilai Rp 10.000.000. Transaksi ke-2 dialokasikan saat transaksi ke-1 sedang committed. | Concurrency | Transaksi pertama sukses, transaksi kedua ditolak dengan `ValidationException` bahwa tagihan telah lunas. Kas GL tidak terpotong dobel. | [ ] | Otomasi PHPUnit |
| **QA-P4-02** | Customer Receipt | Kasir mengirim kuitansi pelunasan faktur yang sudah lunas (remaining <= 0). | Concurrency | Ditolak oleh sistem dengan pesan ramah kasir. Tidak ada penambahan CustomerReceipt liar atau duplikasi Debit Kas. | [ ] | Otomasi PHPUnit |
| **QA-P5-01** | Stock Transfer | Super Admin mencoba menghapus berkas Transfer Stok berstatus `Approved` via endpoint `destroy()`. | Security & Audit | Ditolak dengan redirect & pesan error. Data transfer dan mutasi stok fisik tetap utuh (*zero data-loss*). | [ ] | Otomasi PHPUnit & Playwright |
| **QA-P5-02** | Penerimaan Barang | Petugas gudang mengirim formulir GRN mengaitkan `purchase_order_id` berstatus `draft` atau `cancelled`. | Validation Guard | Validasi form request gagal (`422 Unprocessable Content`). Barang tidak masuk stok. | [ ] | Otomasi PHPUnit |
| **QA-P5-03** | Penerimaan Barang | Mencoba konfirmasi (`confirm()`) GRN berstatus draft terhadap PO yang sudah dibatalkan manajer. | Business Flow | Ditolak dengan pesan error. Mutasi stok dan jurnal GRNI diblokir. | [ ] | Otomasi PHPUnit |
| **QA-P5-04** | Manufaktur Produksi | Operator mencatat kuantitas hasil produksi melebihi kuantitas yang direncanakan pada SPK. | Business Flow | Validasi form request menolak input (*over-production blocked*). Jurnal WIP tidak terbit fiktif. | [ ] | Otomasi PHPUnit |
| **QA-P5-05** | Manufaktur Produksi | Mencoba mencatat hasil produksi untuk SPK yang sudah berstatus `completed` atau `cancelled`. | State Guard | Form request menolak input. Produksi hanya diizinkan untuk SPK aktif. | [ ] | Otomasi PHPUnit |

---

## Bagian B: Lembar Pengujian UAT Bisnis (User Acceptance Testing)

| UAT ID | Kebutuhan Alur Bisnis | Langkah Pengujian Pengguna | Kriteria Kelulusan Bisnis | Status (Pass/Fail) |
| :--- | :--- | :--- | :--- | :---: |
| **UAT-P4-01** | Pembayaran Hutang Vendor Aman | Staf Keuangan buka pelunasan hutang, alokasikan nominal sesuai sisa invoice, simpan & posting. | Invoice supplier berstatus lunas, buku kas/bank berkurang akurat tanpa saldo gantung. | [ ] |
| **UAT-P4-02** | Pelunasan Piutang Kasir Bebas Duplikasi | Kasir menerima uang dari pelanggan, pilih invoice, simpan kuitansi. Coba klik dobel cepat. | Hanya 1 kuitansi tercetak, saldo piutang lunas, kasir aman dari selisih saldo kas fisik. | [ ] |
| **UAT-P5-01** | Perlindungan Dokumen Transfer Stok | Buka detail Transfer Stok yang telah disetujui (Approved). Cek keberadaan tombol Hapus. | Tombol dan modal Hapus tidak tersedia di tampilan layar. Stok gudang asal & tujuan aman. | [ ] |
| **UAT-P5-02** | Penerimaan Barang Resmi Gudang | Buka form GRN penerimaan barang dari supplier. Cek daftar pilihan PO. | Hanya nomor PO yang telah disetujui atasan yang dapat dipilih dan diproses masuk gudang. | [ ] |
| **UAT-P5-03** | Tertib Batas Produksi Pabrik | Operator buka form input bukti produksi. Pilih SPK aktif, coba input kuantitas melebihi target. | Sistem memberi peringatan batas kuantitas rencana, mencegah over-stok dan over-biaya WIP. | [ ] |

---

## Bagian C: Berita Acara & Tanda Tangan UAT

- **Versi Remediasi:** `v1.2.0-hotfix-audit-fase-4-5`
- **Tanggal:** 14 November 2026
- **Status Akhir:** Siap Diimplementasikan & Diverifikasi

