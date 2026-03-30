# Dokumentasi Modul Accounting (Akuntansi)

**Versi Dokumen:** 1.0  
**Tanggal:** 30 Maret 2026  
**Aplikasi:** Duta Tunggal ERP

---

## 1. Gambaran Umum

Modul **Accounting (Akuntansi)** adalah inti dari sistem keuangan ERP Duta Tunggal. Modul ini mengelola Chart of Account, pencatatan jurnal (manual & otomatis), manajemen kas/bank, rekonsiliasi bank, piutang (AR), hutang (AP), deposit, voucher, dan seluruh pelaporan keuangan.

### Prinsip Dasar
- Semua transaksi keuangan menghasilkan **double-entry journal** (total Debit = total Kredit)
- Journal Entry otomatis diposting melalui Observer saat dokumen sumber berubah status
- `LedgerPostingService` adalah pusat posting semua dokumen ke General Ledger
- `JournalBranchResolver` memastikan setiap jurnal memiliki `cabang_id`, `department_id`, dan `project_id` yang benar

---

## 2. Sub-Modul & Fitur

### 2.1 Chart of Account (COA)

**File:** `app/Filament/Resources/ChartOfAccountResource.php`  
**Navigasi:** Grup `Master Data`

#### Tujuan
Master data hierarki akun — dasar dari seluruh sistem akuntansi.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `code` | Kode COA; auto-generate via `ChartOfAccountService::generateCode()` (`COA-YYYYMMDD-XXXX`) |
| `name` | Nama akun |
| `type` | Jenis: `Asset` / `Liability` / `Equity` / `Revenue` / `Expense` / `Contra Asset` |
| `parent_id` | COA induk (hierarki, nullable) |
| `is_active` | Toggle; default true |
| `description` | Deskripsi |
| `opening_balance` | Saldo pembukaan |
| `debit` / `credit` | Input saldo debit/kredit |
| `ending_balance` | Saldo akhir (disabled, auto-hitung) |

**Relation Manager:** `JournalEntryRelationManager` — jurnal terkait COA ini.

---

### 2.2 Journal Entry (Jurnal Entri)

**File:** `app/Filament/Resources/JournalEntryResource.php`  
**Navigasi:** Grup `Finance – Akuntansi`

#### Tujuan
CRUD interface untuk jurnal entri manual maupun audit jurnal otomatis sistem. Mendukung double-entry dengan validasi keseimbangan.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `reference_prefix` | Dropdown: `MANUAL` / `ADJ` / `REV` / `CORR` / `JV` / `DEP` / `BANK` / `CASH` |
| `reference_number` | Nomor; auto-increment per prefix + tombol Generate |
| `reference` | Computed: `{prefix}-{number}` (read-only) |
| `date` | Tanggal entri (default hari ini) |
| `journal_type` | `manual` / `sales` / `purchase` / `payment` / `receipt` / `transfer` / `adjustment` / `depreciation` / `manufacturing` / `inventory` |
| `source_type` | Model sumber (polymorphic: PO, SO, MO, DO, MaterialIssue, dll) |
| `source_id` | Record dari model sumber yang dipilih |
| `cabang_id` | Cabang (hanya untuk `manage_type = all`) |
| `description` | Narasi (max 500 karakter) |
| **Repeater: journal_entries** | Min 2 baris |
| ↳ `coa_id` | COA akun |
| ↳ `debit` | Nominal debit |
| ↳ `credit` | Nominal kredit (mutually exclusive dengan debit) |
| ↳ `description` | Narasi per baris |

**Aturan:** Total debit HARUS = total kredit (toleransi ±0.01).

#### Observer (`JournalEntryObserver`)

| Event | Aksi |
|---|---|
| `created` | Resolusi user; kirim notifikasi `JournalEntryCreated` |
| `updated` | Kirim notifikasi `JournalEntryUpdated` |
| `deleted` | Kirim notifikasi; `cleanupRelatedData()` → reverse AP/AR/Invoice jika ini jurnal terakhir |

**`cleanupRelatedData()` — Cascade Reversal:**
- Vendor Payment: balikkan AP paid/remaining + status invoice
- Customer Receipt: balikkan AR paid/remaining + status invoice
- Invoice: reset AP ke unpaid
- CashBankTransfer: reset status ke `draft` jika tidak ada jurnal tersisa

---

### 2.3 Cash/Bank Account (Akun Kas/Bank)

**File:** `app/Filament/Resources/CashBankAccountResource.php`

#### Tujuan
Master data akun kas/bank yang digunakan dalam transaksi keuangan.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `name` | Nama akun (nickname) |
| `bank_name` | Nama bank (nullable) |
| `account_number` | Nomor rekening (nullable) |
| `coa_id` | Terkait ke COA |
| `notes` | Catatan |

---

### 2.4 Cash/Bank Transaction (Transaksi Kas/Bank)

**File:** `app/Filament/Resources/CashBankTransactionResource.php`  
**Navigasi:** Grup `Finance – Pembayaran`  
**Nomor Dokumen:** Format `CB-YYYYMMDD-XXXX`

#### Tujuan
Mencatat pemasukan/pengeluaran kas/bank dengan detail breakdown COA dan integrasi voucher.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `number` | Nomor unik, auto-generate |
| `numbering_format` | Format penomoran (default/simple/monthly/yearly) |
| `date` | Tanggal transaksi |
| `type` | `cash_in` / `cash_out` / `bank_in` / `bank_out` |
| `amount` | Total; read-only jika ada detail (auto-sum dari repeater) |
| `account_coa_id` | COA Kas/Bank utama (kode 1111x atau 1112x) |
| `offset_coa_id` | COA counter (bukan kas/bank; harus berbeda dari COA utama) |
| `counterparty` | Nama pihak terkait |
| `description` | Narasi |
| `attachment_path` | Upload lampiran ke direktori `cashbank/` |
| `voucher_request_id` | Link ke VoucherRequest yang approved |
| **Repeater: transactionDetails** | Breakdown ke sub-COA |
| ↳ `chart_of_account_id` | COA detail |
| ↳ `description` | Narasi per baris |
| ↳ `amount` | Nominal (bisa negatif untuk membalik arah) |
| ↳ `ntpn` | NTPN untuk PPH 22 impor (tombol Generate) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Posting ke Jurnal** | Status draft | `CashBankService::postTransaction()` → post jurnal ke GL |
| **Lihat Voucher Request** | Ada `voucher_request_id` | Navigasi ke voucher terkait |

#### Pola Jurnal (via `CashBankService::postTransaction`)

| Tipe | Debit | Kredit |
|---|---|---|
| cash_in/bank_in (tanpa breakdown) | COA Utama | COA Offset |
| cash_in/bank_in (dengan breakdown) | COA Utama (full) | Masing-masing detail COA |
| cash_out/bank_out (tanpa breakdown) | COA Offset | COA Utama |
| cash_out/bank_out (dengan breakdown) | Masing-masing detail COA | COA Utama |

---

### 2.5 Cash/Bank Transfer (Transfer Kas/Bank)

**File:** `app/Filament/Resources/CashBankTransferResource.php`  
**Navigasi:** Grup `Finance – Pembayaran`  
**Nomor Dokumen:** Format `TRF-YYYYMMDD-XXXX`

#### Tujuan
Mencatat pemindahan dana antar akun kas/bank (bank-to-bank, cash-to-bank, bank-to-cash).

#### Field Formulir

| Field | Keterangan |
|---|---|
| `number` | Nomor unik, auto-generate |
| `date` | Tanggal transfer |
| `amount` | Nominal transfer |
| `other_costs` | Biaya admin/bank (opsional) |
| `from_coa_id` | COA sumber (kas/bank, kode 1111x/1112x) |
| `to_coa_id` | COA tujuan (kas/bank); harus berbeda dari sumber |
| `other_costs_coa_id` | COA biaya (visible hanya jika `other_costs > 0`) |
| `description` | Narasi |
| `attachment_path` | Upload lampiran |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Posting Jurnal** | Status `draft` | `CashBankService::postTransfer()` → post jurnal; status → `posted`; dispatch `TransferPosted` event |
| **Lihat Rekonsiliasi Bank** | Status `posted` | Redirect ke rekonsiliasi bank |

#### Status Workflow
`draft` → `posted` → `reconciled`

#### Pola Jurnal (via `CashBankService::postTransfer`)
```
Credit  COA Asal (from_coa_id)          [amount + other_costs]
Debit   COA Tujuan (to_coa_id)          [amount]
Debit   COA Biaya (other_costs_coa_id)  [other_costs] — jika ada
```

#### Observer (`CashBankTransferObserver`)

| Event | Aksi |
|---|---|
| `deleted` | Hapus jurnal tipe transfer |
| `restored` | Re-post jurnal jika sebelumnya `posted`/`reconciled` |
| `updated` → field kritis berubah saat posted | Delete + re-create jurnal |

---

### 2.6 Bank Reconciliation (Rekonsiliasi Bank)

**File:** `app/Filament/Resources/BankReconciliationResource.php`  
**Navigasi:** Grup `Finance – Akuntansi`

#### Tujuan
Mencocokkan mutasi rekening bank (dari laporan bank) dengan jurnal entri sistem untuk periode tertentu.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `coa_id` | Akun bank/kas (kode 111x atau nama mengandung "Bank"/"Kas") |
| `statement_ending_balance` | Saldo akhir per laporan bank |
| `period_start` / `period_end` | Periode rekonsiliasi |
| `notes` | Catatan |
| `selected_entry_ids` | Multi-select jurnal belum direkonsiliasi → tandai sebagai reconciled |

**Infolist View:** COA, periode, saldo laporan bank, saldo buku, selisih (warna merah/hijau), status badge, daftar jurnal yang direkonsiliasi.

**Status:** `open` / `closed`

---

### 2.7 Account Payable (Hutang Dagang — AP)

**File:** `app/Filament/Resources/AccountPayableResource.php`  
**Navigasi:** Grup `Finance – Pembelian`

#### Tujuan
Tracking hutang per invoice pembelian dengan ageing schedule. Invoice yang sudah lunas dieksklusi dari daftar utama.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `invoice_id` | Invoice pembelian (`from_model_type = PurchaseOrder`); auto-isi supplier dan total |
| `supplier_id` | Supplier (auto-isi dari invoice) |
| `total` | Total invoice (dari `MoneyHelper::parse`) |
| `paid` | Nominal yang sudah dibayar; reactive → auto-update `remaining` |
| `remaining` | Sisa = total − paid |
| `status` | `unpaid` / `paid` (auto-set jika remaining ≤ 0.01) |

---

### 2.8 Account Receivable (Piutang Dagang — AR)

**File:** `app/Filament/Resources/AccountReceivableResource.php`  
**Navigasi:** Grup `Finance – Penjualan`

#### Tujuan
Tracking piutang per invoice penjualan. Baris lewat jatuh tempo disorot merah.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `cabang_id` | Cabang |
| `invoice_id` | Invoice penjualan (`from_model_type = SaleOrder`); auto-isi customer |
| `customer_id` | Pelanggan (auto-isi) |
| `total` | Total invoice |
| `paid` | Nominal yang sudah diterima |
| `remaining` | Sisa piutang |
| `status` | Lunas / Belum Lunas |

**Indikator Overdue:** `due_date < now()` AND status UNPAID → warna kolom `danger`.

---

### 2.9 Voucher Request (Voucher Pembayaran)

**File:** `app/Filament/Resources/VoucherRequestResource.php`  
**Navigasi:** Grup `Finance – Akuntansi`

#### Tujuan
Pre-otorisasi pembayaran kas/bank. Voucher yang disetujui dapat direferensikan di Cash/Bank Transaction.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `voucher_number` | Nomor voucher, auto-generate |
| `voucher_date` | Tanggal pengajuan (backdate diperbolehkan) |
| `amount` | Nominal |
| `related_party` | Nama pihak terkait |
| `cabang_id` | Cabang |
| `description` | Narasi |
| `approval_notes` | Catatan approver (disabled, visible jika sudah ada) |

#### Aksi / Actions

| Aksi | Syarat | Efek |
|---|---|---|
| **Submit** | Status `draft` | Status → `pending` |
| **Approve** | Status `pending` | Status → `approved` + catat approver |
| **Reject** | Status `pending` | Status → `rejected` |
| **Cancel** | Kapan saja | Status → `cancelled` |

#### Status Workflow
`draft` → `pending` → `approved` / `rejected` / `cancelled`

#### Observer (`VoucherRequestObserver`)

| Event | Aksi |
|---|---|
| `creating` | Auto-generate `voucher_number` jika kosong; set `created_by`; pastikan status `draft` |
| `updating` | Edit lock: hanya `status`, `approved_by`, `approved_at`, `approval_notes`, `cash_bank_transaction_id` yang boleh berubah setelah submit; throw Exception untuk field lain |

---

### 2.10 Deposit

**File:** `app/Filament/Resources/DepositResource.php`  
**Navigasi:** Grup `Finance – Pembayaran`

#### Tujuan
Mengelola uang muka (titipan) dari supplier atau pelanggan yang belum diaplikasikan ke invoice.

#### Field Formulir

| Field | Keterangan |
|---|---|
| `from_model_type` | Radio: `Supplier` / `Customer` |
| `from_model_id` | Supplier atau Customer terkait |
| `deposit_number` | Nomor deposit, auto-generate via `DepositNumberGenerator` |
| `amount` | Total deposit; reactive → hitung `remaining_amount` |
| `note` | Catatan |
| `coa_id` | COA deposit (hutang/aset titipan) |
| `payment_coa_id` | COA pembayaran (Kas/Bank, kode 11x) |

**Table summary:** Total semua deposit, Total terpakai, Total saldo "Hutang Titipan Konsumen".

#### Pola Jurnal (via `LedgerPostingService::postDeposit`)

| Tipe | Debit | Kredit |
|---|---|---|
| Deposit Supplier | Advance Payment (Deposit COA) | Kas/Bank |
| Deposit Customer | Kas/Bank | Customer Deposit Liability (COA) |

---

## 3. Services

| Service | Method | Fungsi |
|---|---|---|
| `LedgerPostingService` | `postInvoice(invoice)` | Post invoice ke GL (AP / Persediaan / PPN) |
| `LedgerPostingService` | `postDeposit(deposit)` | Post deposit ke GL |
| `LedgerPostingService` | `postVendorPayment(payment)` | Post pembayaran vendor ke GL (AP / Bank) |
| `LedgerPostingService` | `postCustomerReceipt(receipt)` | Post penerimaan pelanggan ke GL (AR / Bank) |
| `BalanceSheetService` | `generate(filters)` | Hitung neraca keuangan dari JournalEntry |
| `BalanceSheetService` | `comparePeriods(date1, date2)` | Bandingkan dua periode neraca |
| `BalanceSheetService` | `calculateRetainedEarnings()` | Hitung laba ditahan (Revenue - Expense) |
| `CashBankService` | `generateNumber(prefix)` | Generate nomor CB |
| `CashBankService` | `postTransaction(transaction)` | Post transaksi ke GL |
| `CashBankService` | `postTransfer(transfer)` | Post transfer ke GL; dispatch event |
| `ChartOfAccountService` | `generateCode()` | Generate `COA-YYYYMMDD-XXXX` |
| `InvoiceService` | `generateInvoiceNumber()` | Generate `INV-YYYYMMDD-XXXX` |
| `InvoiceService` | `generatePurchaseInvoiceNumber()` | Generate `PINV-YYYYMMDD-XXXX` |
| `AccountingPeriodService` | — | Manajemen periode akuntansi |
| `JournalEntryAggregationService` | — | Aggregasi jurnal per COA/periode |

---

## 4. Observers & Events

| Observer | Event | Aksi yang Dipicu |
|---|---|---|
| `JournalEntryObserver` | `created` | Notifikasi `JournalEntryCreated` ke user |
| `JournalEntryObserver` | `updated` | Notifikasi `JournalEntryUpdated` |
| `JournalEntryObserver` | `deleted` | Notifikasi + cascade reversal AP/AR/Invoice/Transfer |
| `InvoiceObserver` | `created` (PO) | Buat AccountPayable + ageing; post jurnal |
| `InvoiceObserver` | `created` (SO) | Buat AccountReceivable + ageing |
| `InvoiceObserver` | `updated` → field keuangan berubah | Hapus + re-post jurnal |
| `InvoiceObserver` | `deleted` | Hapus AP/AR + jurnal |
| `CashBankTransferObserver` | `deleted` | Hapus jurnal transfer |
| `CashBankTransferObserver` | `restored` | Re-post jurnal |
| `CashBankTransferObserver` | `updated` → field kritis berubah | Reverse + re-create jurnal |
| `VoucherRequestObserver` | `creating` | Auto-generate nomor; set creator; paksa status draft |
| `VoucherRequestObserver` | `updating` | Edit lock setelah submit |

---

## 5. Pola Jurnal Akuntansi (Summary)

| Transaksi Sumber | Debit | Kredit |
|---|---|---|
| Purchase Invoice | Hutang Pengadaan / Persediaan + PPN Masukan | Utang Dagang (AP) |
| Vendor Payment | Utang Dagang (AP) | Kas/Bank |
| Sales Invoice | Piutang Dagang (AR) | Revenue + PPN Keluaran |
| Customer Receipt (Cash) | Kas/Bank | Piutang Dagang (AR) |
| Customer Receipt (Deposit) | Hutang Titipan (2160.04) | Piutang Dagang (AR) |
| Cash/Bank Transfer | COA Tujuan + Biaya | COA Asal |
| Deposit Supplier | Advance Payment COA | Kas/Bank |
| Deposit Customer | Kas/Bank | Customer Deposit Liability |
| Asset Acquisition | Aset Tetap COA | AP atau Kas |
| Asset Depreciation | Beban Penyusutan | Akumulasi Penyusutan |
| Material Issue | WIP/BDP | Persediaan Bahan Baku |
| Production Completion | Barang Jadi | WIP/BDP |

---

## 6. Struktur COA Utama

| Kode | Nama | Type |
|---|---|---|
| 1101 | Kas | Asset |
| 1111x | Kas Kecil | Asset |
| 1112x | Rekening Bank | Asset |
| 1120 | Piutang Dagang | Asset |
| 1140.01–1140.10 | Persediaan | Asset |
| 1140.02 | WIP / Barang Dalam Proses | Asset |
| 1140.03 | Persediaan Barang Jadi | Asset |
| 1140.20 | Barang Dalam Pengiriman | Asset |
| 1180.01 | Hutang Pengadaan Sementara | Asset |
| 1210.xx | Aset Tetap | Asset |
| 1220.xx | Akumulasi Penyusutan | Contra Asset |
| 2101.01 | Utang Dagang (AP) | Liability |
| 2110 | Utang Dagang (AP Umum) | Liability |
| 2120.06 | PPN Keluaran | Liability |
| 2160.04 | Hutang Titipan Konsumen | Liability |
| 4000 | Pendapatan Penjualan | Revenue |
| 5000 / 5100.10 | HPP / COGS | Expense |
| 6100.02 | Biaya Pengiriman | Expense |
| 63xx | Beban Penyusutan | Expense |

---

## 7. Permissions

| Permission | Fungsi |
|---|---|
| `create journal entry` | Buat jurnal manual |
| `view journal entry` | Lihat jurnal |
| `approve journal entry` | Setujui jurnal |
| `create cash bank transaction` | Buat transaksi kas/bank |
| `post cash bank transaction` | Post ke GL |
| `create cash bank transfer` | Buat transfer |
| `post cash bank transfer` | Post transfer ke GL |
| `approve voucher request` | Setujui voucher |
| `view account payable` | Lihat AP |
| `view account receivable` | Lihat AR |
| `view deposit` | Lihat deposit |
| `approve bank reconciliation` | Setujui rekonsiliasi |
