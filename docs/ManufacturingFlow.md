# Manufacturing / Production Flow (Duta-Tunggal-ERP)

Dokumen ini menjelaskan kapan Journal Entry dibuat dalam fitur Manufacturing, dan memberikan alur lengkap proses produksi sesuai implementasi sistem saat ini.

## Ringkasan Kapan Journal Dibuat

- BoM/Formulasi Produksi: Tidak membuat jurnal.
- Issue Bahan Baku (Material Issue) saat status menjadi `completed` dan `type = 'issue'`:
  - Dr `1140.02` Persediaan Barang dalam Proses (BDP)
  - Cr `1140.01` Persediaan Bahan Baku
- Retur Bahan Baku (Material Return) saat status menjadi `completed` dan `type = 'return'`:
  - Dr `1140.01` Persediaan Bahan Baku
  - Cr `1140.02` Persediaan Barang dalam Proses (BDP)
- Alokasi Tenaga Kerja Langsung (TKL) & BOP ke BDP (opsional, dilakukan terpisah):
  - Dr `1140.02` BDP
  - Cr `6xxx` Beban/Kas (akun biaya/kas yang dipilih)
- Penyelesaian Produksi (Finished Goods Completion / Production Finished):
  - Dr `1140.03` Persediaan Barang Jadi
  - Cr `1140.02` Persediaan Barang dalam Proses (BDP)

Referensi Kode:
- `app/Observers/MaterialIssueObserver.php`
- `app/Services/ManufacturingJournalService.php`

## Alur Lengkap Manufacturing / Produksi

### Navigasi Menu → Proses

- BoM/Formulasi:
  - Menu: Manufacturing → Bill of Materials (BoM)
  - Aksi: Buat/aktifkan BoM untuk produk, set komponen, estimasi TKL & BOP.
  - Jurnal: Tidak ada.

- Material Issue (Issue Bahan Baku):
  - Menu: Manufacturing → Material Issue
  - Aksi: Buat dokumen issue, tambah item bahan baku dari gudang/rak, set tanggal issue, lalu set status ke `completed` untuk memposting.
  - Efek: Jurnal Dr 1140.02 / Cr 1140.01, Stock Movement `manufacture_out`, update `qty_used` MO.

- Material Return (Retur Bahan Baku):
  - Menu: Manufacturing → Material Issue (gunakan tipe `return`)
  - Aksi: Buat retur untuk bahan berlebih, lalu set status ke `completed` untuk memposting.
  - Efek: Jurnal Dr 1140.01 / Cr 1140.02, Stock Movement `manufacture_in`.

- Alokasi TKL & BOP (opsional/terpisah):
  - Menu: Finance → Journal Entries (Manual) atau proses batch internal
  - Aksi: Gunakan layanan `allocateLaborAndOverhead` atau buat jurnal manual sesuai kebijakan.
  - Efek: Jurnal Dr 1140.02 / Cr 6xxx.

- Penyelesaian Produksi (Barang Jadi):
  - Menu: Manufacturing → Production / Finished Goods Completion
  - Aksi: Tandai produksi sebagai `finished` atau selesaikan dokumen Finished Goods Completion.
  - Efek: Jurnal Dr 1140.03 / Cr 1140.02.

### 1) Formulasi Produksi (BoM)
- Input komponen bahan baku, estimasi TKL, dan BOP ke dalam Bill of Material (BoM) untuk produk.
- Tujuan: Mendefinisikan kebutuhan material dan estimasi biaya produksi.
- Efek akuntansi: Tidak ada jurnal yang diposting pada tahap ini.

### 2) Pembuatan dan Penyelesaian Material Issue (Issue Bahan Baku)
- Pengguna membuat Material Issue untuk mengambil bahan baku dari gudang guna produksi.
- Saat Material Issue dibuat dan masih draft/pending: Tidak ada jurnal.
- Saat Material Issue diubah menjadi `completed` dan `type='issue'`:
  - Sistem mem-posting jurnal otomatis:
    - Dr `1140.02` BDP sebesar `total_cost` Material Issue
    - Cr `1140.01` Persediaan Bahan Baku sebesar nilai yang sama
  - Sistem juga membuat Stock Movement (tipe `manufacture_out`) untuk tiap item yang di-issue.
  - Sistem meng-update penggunaan material pada Manufacturing Order (MO) (`qty_used`).

Catatan: Tahap ini hanya memindahkan nilai bahan baku ke BDP. TKL & BOP belum diposting.

### 3) Retur Bahan Baku dari Produksi (Material Return)
- Jika ada kelebihan/retur bahan, buat Material Issue dengan `type='return'`.
- Saat status menjadi `completed`:
  - Jurnal otomatis:
    - Dr `1140.01` Persediaan Bahan Baku
    - Cr `1140.02` BDP
  - Stock Movement dibuat dengan tipe `manufacture_in` untuk mengembalikan stok.

### 4) Alokasi Tenaga Kerja Langsung (TKL) & BOP ke BDP (Opsional/Terpisah)
- Untuk mencerminkan biaya tenaga kerja langsung dan overhead pabrik dalam BDP, sistem menyediakan method:
  - `ManufacturingJournalService::allocateLaborAndOverhead(laborCost, overheadCost, reference, date, ?expenseCoaId, description)`
- Jurnal yang dibuat:
  - Dr `1140.02` BDP (sebesar `laborCost + overheadCost`)
  - Cr `6xxx` Beban/Kas (akun biaya/kas yang dipilih)
- Tahap ini tidak otomatis dari Issue; dilakukan sesuai kebijakan perusahaan (misal periodik atau saat produksi berjalan).

### 5) Penyelesaian Produksi (Finished Goods Completion / Production Finished)
- Ketika produksi dinyatakan selesai (`status='finished'` pada `Production` atau ada `FinishedGoodsCompletion` yang `completed`):
  - Sistem memindahkan biaya dari BDP ke Persediaan Barang Jadi.
  - Jurnal yang dibuat:
    - Dr `1140.03` Persediaan Barang Jadi
    - Cr `1140.02` BDP
- Nilai yang diposting diambil dari total biaya produksi (berdasarkan BOM dan kuantitas MO atau `completion->total_cost`).

## Detail Implementasi

- Observer `MaterialIssueObserver`:
  - Pada event `created` dan `updated`, jika status Material Issue menjadi `completed`:
    - Memanggil `ManufacturingJournalService::generateJournalForMaterialIssue($materialIssue)` atau `generateJournalForMaterialReturn($materialIssue)` sesuai `type`.
    - Membuat Stock Movement untuk setiap item.
    - Mengupdate `qty_used` material pada `manufacturing_order_materials`.

- Service `ManufacturingJournalService`:
  - `generateJournalForMaterialIssue(...)`: Dr 1140.02 / Cr 1140.01.
  - `generateJournalForMaterialReturn(...)`: Dr 1140.01 / Cr 1140.02.
  - `allocateLaborAndOverhead(...)`: Dr 1140.02 / Cr 6xxx (Biaya/Kas), dilakukan terpisah.
  - `generateJournalForProductionCompletion(...)` dan `createFinishedGoodsCompletionJournal(...)`: Dr 1140.03 / Cr 1140.02.

## Contoh Kasus (Sejalan dengan Requirement)

Produksi 1.000 unit produk A:
- Bahan Baku: Rp 10.000.000 → Tahap 2 (Issue Completed):
  - Dr 1140.02 BDP Rp 10.000.000
  - Cr 1140.01 Persediaan Bahan Baku Rp 10.000.000
- TKL: Rp 3.000.000 → Tahap 4 (Alokasi TKL & BOP):
  - Dr 1140.02 BDP Rp 3.000.000
  - Cr 6xxx Beban/Kas Rp 3.000.000
- BOP: Rp 2.000.000 → Tahap 4 (Alokasi TKL & BOP):
  - Dr 1140.02 BDP Rp 2.000.000
  - Cr 6xxx Beban/Kas Rp 2.000.000
- Penyelesaian Produksi → Tahap 5:
  - Dr 1140.03 Persediaan Barang Jadi Rp 15.000.000
  - Cr 1140.02 BDP Rp 15.000.000

## Catatan Tambahan

- Penentuan `cabang_id`, `department_id`, `project_id` pada jurnal menggunakan `JournalBranchResolver` sehingga journal dapat tersegmentasi per cabang/departemen/proyek.
- Nilai `total_cost` pada Material Issue digunakan untuk menentukan jumlah jurnal saat Issue/Return.
- Stock Movement terkait produksi menggunakan tipe `manufacture_out` (issue) dan `manufacture_in` (return) serta menyimpan relasi ke `MaterialIssue`.
