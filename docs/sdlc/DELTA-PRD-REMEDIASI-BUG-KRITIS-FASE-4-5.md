# Delta-PRD: Remediasi Bug Kritis Operasional ERP Fase 4 & Fase 5
Dokumen Spesifikasi Kebutuhan Produk & Manajemen Perubahan Arsitektur (*Brownfield Mode*).
Mematuhi standar **Ponytail (Minimal Diff & YAGNI)**, **Antislop (Zero Placeholder)**, **UI/UX ProMax (Tailwind CSS)**, dan **No Happy Path (Defensive Concurrency & State Locking)**.

---

## 1. Ringkasan Eksekutif & Informasi Proyek
- **Nama Aplikasi / Modul:** ERP Duta Tunggal — Pembelian (AP), Penjualan (AR), Gudang (Transfer & Penerimaan GRN), dan Manufaktur (PPIC).
- **Mode Siklus SDLC:** Brownfield (Melanjutkan Project & Pengerasan Sistem Eksisting).
- **Tech Stack Utama:** Laravel 11 + Blade + Tailwind CSS + Alpine.js + PostgreSQL / MySQL.
- **Tujuan Bisnis Utama:** Menutup celah konkurensi finansial (pembayaran ganda vendor & kasir), mencegah hilangnya jejak mutasi persediaan gudang akibat penghapusan dokumen disetujui, dan memblokir transaksi penerimaan/produksi liar yang menyimpang dari otorisasi manajemen.

---

## 2. Role-Based Access Control (RBAC) Matrix

| Role | Vendor Payment (AP) | Customer Receipt (AR) | Stock Transfer (Gudang) | Penerimaan Barang (GRN) | Hasil Produksi (PPIC) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Super Admin** | Create, View, Post | Create, View, Post | Create, Approve (Hapus HANYA Draft) | Create, Confirm PO Sah | Create, Finish sesuai SPK |
| **Finance / Kasir** | Create, Post AP | Create, Post AR | View Only | View Only | View Only |
| **Gudang / Checker** | No Access | No Access | Create, Request, Approve | Create & Confirm PO Sah | View / Receive FG |
| **PPIC / Operator** | No Access | No Access | View Only | View Only | Create & Finish sesuai SPK |

*Aturan Khusus:* Super Admin dilarang keras menghapus Transfer Stok yang sudah berstatus `Approved` / `Completed` untuk menjaga integritas fisik dan buku besar akuntansi.

---

## 3. Spesifikasi Fitur & No-Happy-Path Acceptance Criteria

| ID Bug | Modul & Skenario | Happy Path | No Happy Path: Validasi & Edge Cases | Solusi Teknis & Pesan Error |
| :--- | :--- | :--- | :--- | :--- |
| **BUG-NEW-01** | **Pembelian (Vendor Payment)** | Staf input pelunasan hutang vendor sesuai sisa tagihan, jurnal kas keluar diposting, status AP terupdate. | • Dua staf finance submit pembayaran untuk invoice sama bersamaan (*race condition*).<br>• Nominal bayar melebihi sisa hutang invoice saat ini. | Gunakan `lockForUpdate()` pada baris `AccountPayable`. Tolak transaksi dengan pesan: *"Alokasi pembayaran untuk invoice [Nomor] sebesar [Rp X] melebihi sisa hutang yang tersisa ([Rp Y]). Tagihan mungkin telah dilunasi oleh transaksi lain."* |
| **BUG-NEW-02** | **Penjualan (Customer Receipt)** | Kasir input pelunasan invoice pelanggan, kuitansi terbit, jurnal kas masuk terbit, status piutang lunas. | • Kasir klik ganda cepat (*rapid double-submit*) atau dua kasir mencatat invoice sama.<br>• Invoice sudah lunas saat transaksi dieksekusi. | Gunakan `lockForUpdate()` pada `Invoice` & `AccountReceivable`. Tolak pelunasan jika sisa tagihan `<= 0` dengan pesan: *"Invoice [Nomor] telah lunas atau sedang diproses oleh transaksi lain. Segera muat ulang halaman kasir."* |
| **BUG-NEW-03** | **Gudang (Stock Transfer)** | Admin mengelola transfer draft. Transfer yang disetujui memotong gudang asal dan menambah gudang tujuan. | • Super Admin mencoba menghapus transfer berstatus `Approved` / `Completed`.<br>• Penghapusan memicu *ghost inventory* karena observer tidak membalikkan stok. | Kunci permanen `destroy()` untuk semua status non-draft (termasuk Super Admin). Sembunyikan tombol dan modal hapus di view. Berikan pesan: *"Hanya transfer berstatus Draft yang dapat dihapus. Transfer yang telah disetujui tidak dapat dihapus demi integritas persediaan."* |
| **BUG-NEW-04** | **Gudang (Penerimaan GRN)** | Petugas gudang menerima barang fisik atas PO berstatus `approved` atau `partially_received`. | • Petugas memasukkan ID PO berstatus `draft`, `rejected`, atau `cancelled`.<br>• Draf GRN dikonfirmasi saat status PO sudah dibatalkan manajemen. | Tambahkan validasi `Rule::exists('purchase_orders', 'id')->whereIn('status', ['approved', 'partially_received'])` pada request & guard di `confirm()`. Pesan: *"Penerimaan barang hanya dapat diproses untuk Purchase Order yang telah disetujui (Approved) atau diterima sebagian."* |
| **BUG-NEW-05** | **Manufaktur (Hasil Produksi)** | Operator mencatat bukti hasil produksi terhadap SPK aktif dalam batas kuantitas rencana produksi. | • Operator input produksi untuk SPK yang sudah selesai (`completed`) atau dibatalkan.<br>• Kuantitas melebihi sisa target rencana produksi SPK. | Validasi status SPK harus `in_progress`/`draft` dan `total_produced + qty_input <= planned_qty`. Pesan: *"Kuantitas hasil produksi ([X] pcs) melebihi batas sisa rencana produksi SPK ([Y] pcs)."* |

---

## 4. Matriks Kepatuhan Standar UI & Data

| Halaman / Rute | Standar List Data | Search & Multi-Filter | Dynamic Async Combobox | Modal Konfirmasi |
| :--- | :--- | :--- | :--- | :--- |
| `/pembelian/payments/create` | Form Transaksi | Pencarian Supplier & Invoices | Pilihan Akun Kas/Bank, Supplier | Konfirmasi Posting Pembayaran Hutang |
| `/penjualan/receipts/create` | Form Transaksi | Pencarian Customer & Invoices | Pilihan Akun Kas/Bank, Customer | Konfirmasi Posting Penerimaan Kasir |
| `/gudang/transfer/{transfer}` | Show Detail Transfer | - | - | Modal Hapus HANYA tampil jika status Draft |
| `/gudang/penerimaan/create` | Form GRN | Pencarian PO Valid | Pilihan PO (Hanya Approved/Partial) | Konfirmasi Masuk Stok Gudang |
| `/manufaktur/production/create`| Form Produksi | Pencarian SPK Aktif | Pilihan SPK (In Progress) | Konfirmasi Penyelesaian Produksi |

---

## 5. Non-Functional Requirements & Concurrency Protection
1. **Row-Level Atomic Locking:** Semua kalkulasi saldo terutang/piutang wajib berada di dalam blok `DB::transaction()` dengan klausa `lockForUpdate()` pada entitas keuangan terkait.
2. **Deterministic Deduplication:** Mencegah pencatatan ganda jurnal akuntansi dan kartu stok persediaan akibat latensi jaringan atau submit ganda pengguna.
3. **Audit Trail Compliance:** Tidak ada dokumen transaksi fisik (Transfer Stok, Penerimaan Barang, Pembayaran) yang boleh dihapus jika telah mendebit/mengkredit saldo persediaan atau kas.

