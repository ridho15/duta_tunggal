# Legacy Data Migration Matrix - 2026-04-11

## Ringkasan Eksekusi

- Source `inventory` direhidrasi ke cabang import ERP `CBG-LEG-INV` / warehouse `WH-LEG-INV`.
- Source `inventory_cab` direhidrasi ke cabang import ERP `CBG-LEG-CAB` / warehouse `WH-LEG-CAB`.
- Status implementasi saat dokumen ini dibuat:
  - master data import: selesai
  - opening stock import: selesai
  - sales rehydration: selesai
  - purchase rehydration: selesai
  - quotation archive + rehydration: selesai
  - request mapping: dianalisis, belum di-live-import

## Status Legend

- `Live import`: dipindahkan ke master ERP aktif.
- `Live rehydration`: dipindahkan ke dokumen ERP aktif secara idempotent dari arsip legacy.
- `Archive only`: disimpan di `legacy_transaction_archives`, tidak dibuat dokumen ERP aktif.
- `Reference only`: dipakai untuk mapping / audit, tidak disalin 1:1 ke ERP aktif.
- `Do not migrate`: tabel sistem legacy, tidak dibawa ke ERP.

## Matrix

| Legacy table(s) | Rows inventory | Rows inventory_cab | Makna bisnis | Target ERP / Metode | Keputusan saat ini |
| --- | ---: | ---: | --- | --- | --- |
| `knr_product_categories` / `dtm_product_categories` | 310 | 924 | kategori produk | master `product_categories` | `Live import` selesai |
| `knr_customers` / `dtm_customers` | 8,835 | 9,083 | master customer | master `customers` | `Live import` selesai |
| `knr_suppliers` / `dtm_suppliers` | 406 | 427 | master supplier | master `suppliers` | `Live import` selesai |
| `knr_products` / `dtm_products` | 10,092 | 19,447 | master produk | master `products` | `Live import` selesai |
| `knr_product_stocks` / `dtm_product_stocks` | 133,398 | 52,451 | saldo stok per produk/lokasi | dikonversi menjadi opening stock ERP | `Live import` selesai via saldo awal, bukan copy 1:1 |
| `knr_inventories` / `dtm_inventories` | 89,125 | 7,444 | stok agregat / snapshot legacy | dipakai sebagai sumber hitung saldo awal | `Reference only` |
| `knr_sales` / `dtm_sales` | 164,925 | 11,173 | header penjualan | `sale_orders` via archive + rehydration | `Live rehydration` selesai |
| `knr_sales_detail` / `dtm_sales_detail` | not sampled | not sampled | item penjualan | `sale_order_items` via archive + rehydration | `Live rehydration` selesai |
| `knr_sales_payment` / `dtm_sales_payment` | not sampled | not sampled | pembayaran penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_sales_cost` / `dtm_sales_cost` | not sampled | not sampled | biaya penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_sales_delivery_info` / `dtm_sales_delivery_info` | not sampled | not sampled | info pengiriman penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_sales_inventory` / `dtm_sales_inventory` | not sampled | not sampled | link stok ke penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_sales_photo` / `dtm_sales_photo` | not sampled | not sampled | lampiran foto penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_sales_retur_payment` / `dtm_sales_retur_payment` | not sampled | not sampled | retur pembayaran penjualan | `legacy_transaction_archives` | `Archive only` |
| `knr_purchases` / `dtm_purchases` | 13,512 | 8,674 | header pembelian | `purchase_orders` via archive + rehydration | `Live rehydration` selesai |
| `knr_purchases_detail` / `dtm_purchases_detail` | not sampled | not sampled | item pembelian | `purchase_order_items` via archive + rehydration | `Live rehydration` selesai |
| `knr_purchases_payment` / `dtm_purchases_payment` | not sampled | not sampled | pembayaran pembelian | `legacy_transaction_archives` | `Archive only` |
| `knr_purchases_cost` / `dtm_purchases_cost` | not sampled | not sampled | biaya pembelian | `legacy_transaction_archives` | `Archive only` |
| `knr_purchases_photo` / `dtm_purchases_photo` | not sampled | not sampled | lampiran foto pembelian | `legacy_transaction_archives` | `Archive only` |
| `knr_purchases_retur_payment` / `dtm_purchases_retur_payment` | not sampled | not sampled | retur pembayaran pembelian | `legacy_transaction_archives` | `Archive only` |
| `dtm_quotations` | n/a | 3 | header quotation | `quotations` via archive + rehydration | `Live rehydration` selesai |
| `dtm_quotations_detail` | n/a | 10 | item quotation | `quotation_items` via archive + rehydration | `Live rehydration` selesai |
| `knr_requests` / `dtm_requests` | 64 | 6,430 | permintaan internal antar lokasi / fulfillment | belum ada target ERP yang lossless | `Archive only` sementara, lihat analisis request |
| `knr_requests_detail` / `dtm_requests_detail` | 78 | 15,307 | item request dengan qty request/deliver/process | belum ada target ERP yang lossless | `Archive only` sementara, lihat analisis request |
| `knr_delivery_letters` / `dtm_delivery_letters` | 273,401 | 15,867 | surat jalan / delivery history | `legacy_transaction_archives` | `Archive only` |
| `knr_delivery_letters_detail` / `dtm_delivery_letters_detail` | 477,065 | 31,816 | detail surat jalan | `legacy_transaction_archives` | `Archive only` |
| `knr_mutations` / `dtm_mutations` | 31,458 | 846 | mutasi stok antar lokasi | `legacy_transaction_archives` | `Archive only` |
| `knr_mutations_detail` / `dtm_mutations_detail` | not sampled | not sampled | detail mutasi stok | `legacy_transaction_archives` | `Archive only` |
| `knr_mutations_photo` / `dtm_mutations_photo` | not sampled | not sampled | lampiran mutasi | `legacy_transaction_archives` | `Archive only` |
| `knr_stock_adjustment` / `dtm_stock_adjustment` | 48,686 | 1,013 | penyesuaian stok | `legacy_transaction_archives` | `Archive only` |
| `knr_stockflows` / `dtm_stockflows` | 718,387 | 60,893 | ledger pergerakan stok | `legacy_transaction_archives` | `Archive only` |
| `knr_stock_modification` / `dtm_stock_modification` | 30 | 2 | perubahan/mastering stok khusus | `legacy_transaction_archives` | `Archive only` |
| `knr_stock_modification_detail` / `dtm_stock_modification_detail` | not sampled | not sampled | detail perubahan stok | `legacy_transaction_archives` | `Archive only` |
| `knr_stock_opname` / `dtm_stock_opname` | 0 | 0 | header stock opname | `legacy_transaction_archives` | `Archive only` |
| `knr_stock_opname_results` / `dtm_stock_opname_results` | 0 | 0 | hasil stock opname | `legacy_transaction_archives` | `Archive only` |
| `knr_cashflows` / `dtm_cashflows` | 374,457 | 24,044 | histori kas legacy | `legacy_transaction_archives` | `Archive only` |
| `knr_fund_mutations` / `dtm_fund_mutations` | 16,927 | 71 | perpindahan dana legacy | `legacy_transaction_archives` | `Archive only` |
| `knr_funds` / `dtm_funds` | 778 | 0 | master rekening/fund legacy | referensi audit, bukan master ERP aktif | `Reference only` |
| `knr_currencies` / `dtm_currencies` | 5 | 5 | master currency legacy | dipakai sebagai referensi mapping | `Reference only` |
| `knr_stores` / `dtm_stores` | 4 | 3 | lokasi store legacy | dipakai untuk mapping cabang/lokasi arsip | `Reference only` |
| `knr_warehouses` / `dtm_warehouses` | 13 | 22 | gudang legacy | dipakai untuk mapping audit, bukan import langsung | `Reference only` |
| `knr_item_value_calculations` | sampled only in `inventory` | n/a | tabel bantu kalkulasi nilai item | tidak dibutuhkan ERP aktif | `Do not migrate` |
| `knr_change_transactions` / `dtm_change_transactions` | not sampled | not sampled | audit perubahan transaksi legacy | tidak dibawa ke transaksi aktif | `Do not migrate` |
| `knr_action_logs` / `dtm_action_logs` | not sampled | not sampled | action log aplikasi lama | tidak relevan untuk ERP baru | `Do not migrate` |
| `knr_notifications` / `dtm_notifications` | not sampled | not sampled | notifikasi aplikasi lama | tidak relevan untuk ERP baru | `Do not migrate` |
| `knr_session` / `dtm_session` / `dtm_ci_sessions` | not sampled | not sampled | session aplikasi lama | tidak relevan untuk ERP baru | `Do not migrate` |
| `knr_users` / `dtm_users` | not sampled | not sampled | user aplikasi legacy | ERP memakai user/access table baru | `Do not migrate` |
| `knr_levels` / `dtm_levels` | not sampled | not sampled | role/level legacy | ERP memakai authorization baru | `Do not migrate` |
| `knr_registers` / `dtm_registers` | not sampled | not sampled | register sistem lama | tidak relevan untuk ERP baru | `Do not migrate` |

## Catatan Implementasi Penting

- Purchase rehydration sudah diperbaiki agar pencocokan SKU/code `inventory_cab` bersifat case-insensitive.
- Purchase rehydration juga membuat currency default `Rupiah/IDR` bila tabel currency ERP kosong.
- Quotation legacy `inventory_cab` berhasil diarsipkan `3` header + `10` detail, lalu direhidrasi `3/3` dokumen tanpa missing product.
- Dua quotation customer yang hanya ada di header legacy dibuatkan placeholder customer ERP agar dokumen aktif tidak hilang.

## Keputusan Bisnis Saat Ini

- Data yang perlu aktif untuk operasional sekarang: master, opening stock, sales, purchases, quotations.
- Data histori operasional yang masih penting untuk audit tetapi berisiko jika diposting ulang: delivery, mutations, stockflows, cashflows, fund movements.
- Data request legacy tidak dipindahkan ke `order_requests` karena model ERP saat ini tidak mewakili asal-tujuan lokasi dan status fulfillment legacy secara lengkap.