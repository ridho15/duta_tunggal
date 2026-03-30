# Index Dokumentasi Modul — Duta Tunggal ERP

**Versi:** 1.0  
**Tanggal:** 30 Maret 2026

---

## Daftar Modul

| No | Modul | File Dokumentasi | Deskripsi Singkat |
|---|---|---|---|
| 1 | **Purchase (Pembelian)** | [01_MODULE_PURCHASE.md](01_MODULE_PURCHASE.md) | Permintaan pembelian, PO, QC, penerimaan barang, invoice, retur, dan pembayaran vendor |
| 2 | **Sales (Penjualan)** | [02_MODULE_SALES.md](02_MODULE_SALES.md) | Quotation, Sales Order, invoice penjualan, penerimaan customer, dan retur |
| 3 | **Manufacturing (Produksi)** | [03_MODULE_MANUFACTURING.md](03_MODULE_MANUFACTURING.md) | BOM, rencana produksi, Manufacturing Order, pengeluaran material, dan QC |
| 4 | **Asset (Aset Tetap)** | [04_MODULE_ASSET.md](04_MODULE_ASSET.md) | Pencatatan aset, penyusutan, transfer, dan disposal |
| 5 | **Inventory/Gudang** | [05_MODULE_INVENTORY_GUDANG.md](05_MODULE_INVENTORY_GUDANG.md) | Stok, pergerakan, transfer, opname, dan manajemen gudang |
| 6 | **Accounting (Akuntansi)** | [06_MODULE_ACCOUNTING.md](06_MODULE_ACCOUNTING.md) | COA, jurnal, kas/bank, rekonsiliasi, AR, AP, voucher, deposit |
| 7 | **Report (Laporan)** | [07_MODULE_REPORT.md](07_MODULE_REPORT.md) | Neraca, P&L, arus kas, buku besar, laporan stok, HPP |
| 8 | **Delivery Order (Pengiriman)** | [08_MODULE_DELIVERY_ORDER.md](08_MODULE_DELIVERY_ORDER.md) | DO, surat jalan, penjadwalan pengiriman, dan posting stok/GL |

---

## Arsitektur Ringkas

```
Framework:   Laravel 10 + Filament 3
Database:    MySQL
Pattern:     Filament Resource (UI) + Model Observer (side effects) + Service Class (business logic)
Multitenancy: CabangScope global scope + JournalBranchResolver
Accounting:  Double-entry; semua jurnal otomatis via LedgerPostingService
Inventory:   Real-time via StockMovementObserver + lockForUpdate()
```

---

## Alur Bisnis Utama

```
[Purchase]                          [Sales]
OrderRequest                        Quotation
  → PurchaseOrder                     → SaleOrder (Confirmed)
    → QC Purchase                       → SalesInvoice → CustomerReceipt
    → PurchaseReceipt                   → DeliveryOrder → SuratJalan
    → PurchaseInvoice → VendorPayment   → CustomerReturn (jika ada retur)

[Manufacturing]
BOM → ProductionPlan → ManufacturingOrder
  → MaterialIssue (keluarkan bahan baku)
  → Production (WIP → Barang Jadi)
  → QC Manufacture

[Asset]
Asset (Acquisition) → Depreciation (monthly) → Disposal/Transfer

[Inventory]
InventoryStock ← StockMovement (semua pergerakan)
StockTransfer / StockAdjustment / StockOpname

[Accounting]
JournalEntry ← semua modul (via Observer/Service)
COA → BukuBesar → BalanceSheet / P&L / CashFlow
```
