# Legacy Migration Closure 2026-04-11

## Scope

Migrasi legacy `inventory` dan `inventory_cab` ke ERP `duta_tunggal` ditutup dalam dua jalur aman:

1. Master data dan opening stock aktif dimigrasikan ke tabel ERP operasional.
2. Histori transaksi dan proses legacy diarsipkan lengkap ke tabel ERP terpisah `legacy_transaction_archives` agar histori tetap tersedia tanpa mem-posting ulang efek stok atau jurnal.

## Final Master Data State

- Remaining blocked duplicate product groups: `0`
- Active products cabang 2: `10911`
- Active products cabang 3: `18767`
- Active inventory stocks warehouse 2: `10911`
- Active inventory stocks warehouse 3: `18767`

## Final Manual Product Resolution

Duplicate group terakhir ditutup manual pada SKU target `002008065067`.

Kondisi sebelum merge:

- `CAB-002008065067`
  - name: `COPPER MALE UNION 2 1/2 X 2 5/8`
  - category id: `635`
  - UOM id: `30` (`ROL`)
  - qty available: `0`
- `CAB-002008065067-DUP2-R13772`
  - name: `COPPER MALE UNION 2 1/2" X 2 5/8"`
  - category id: `309`
  - UOM id: `28` (`PCS`)
  - qty available: `15`

Dasar keputusan:

- label category `635` dan `309` sama-sama `SAMBUNGAN TEMBAGA STD`
- biaya dan harga sama
- stok aktif ada pada row `PCS`
- pola produk sejenis menunjukkan `COPPER MALE UNION*` memakai `PCS` sebanyak `26` row dan `ROL` hanya `1` row

Keputusan eksekusi:

- row `PCS` dipromosikan ke cabang utama dengan SKU final `002008065067`
- category diseragamkan ke `635`
- nama dinormalisasi ke `COPPER MALE UNION 2 1/2 X 2 5/8`
- row `ROL` dinonaktifkan dan stock row-nya di-soft-delete

## Transaction Archive Strategy

Replay transaksi legacy ke workflow ERP aktif tidak dipakai karena akan mendobel efek histori terhadap saldo stok dan jurnal, sementara opening stock saat ini sudah mewakili posisi live terbaru.

Sebagai gantinya, histori transaksi diimpor penuh ke tabel arsip ERP:

- table: `legacy_transaction_archives`
- mode: idempotent upsert by `source_name + table_name + legacy_id`
- payload: seluruh kolom row legacy disimpan dalam JSON `payload`
- metadata normalisasi: document number, tanggal, status, customer/supplier, product, lokasi, quantity, amount, tax, cost

Command final yang dipakai:

```bash
php artisan legacy:import-transaction-archive --truncate --execute --chunk=100
```

## Final Archive Totals

- Total archived rows: `2691520`
- UI browser untuk arsip tersedia di route Filament `admin/legacy-transaction-archives`

### By Source

| Source | Rows |
| --- | ---: |
| inventory | 2491734 |
| inventory_cab | 199786 |

### By Transaction Type

| Transaction Type | Rows |
| --- | ---: |
| sale | 1157239 |
| purchase | 87268 |
| mutation | 97064 |
| stock_adjustment | 49699 |
| stockflow | 779280 |
| stock_modification | 105471 |
| cashflow | 398501 |
| fund_mutation | 16998 |

## Included Legacy Tables

- sales: `sales`, `sales_detail`, `sales_payment`, `sales_cost`, `sales_delivery_info`, `sales_inventory`, `sales_photo`, `sales_retur_payment`
- purchases: `purchases`, `purchases_detail`, `purchases_payment`, `purchases_cost`, `purchases_photo`, `purchases_retur_payment`
- mutations: `mutations`, `mutations_detail`, `mutations_photo`
- stock and process: `stock_adjustment`, `stockflows`, `stock_modification`, `stock_modification_detail`, `stock_opname`, `stock_opname_results`
- cash and fund movement: `cashflows`, `fund_mutations`

## Zero-Row Legacy Tables Observed

- `sales_photo`
- `purchases_photo`
- `stock_opname`
- `stock_opname_results`

Zero-row tables tetap ikut dicakup command agar import tetap konsisten saat dijalankan ulang.

## Operational Result

- ERP operasional sekarang memegang master data aktif dan opening stock hasil konsolidasi akhir.
- Histori transaksi legacy dari dua database sudah tersimpan lengkap di database ERP.
- Tidak ada blocked duplicate product bucket yang tersisa.

## Active Sales Rehydration Phase

Selain arsip penuh, fase kedua untuk rehidrasi dokumen aktif dimulai pada modul `sales`.

Komponen baru:

- command: `php artisan legacy:rehydrate-sales-orders`
- source table: `legacy_transaction_archives`
- target table: `sale_orders` dan `sale_order_items`
- idempotency: `sale_orders.legacy_source_name + sale_orders.legacy_legacy_id`

Command ini sengaja memakai query builder langsung, bukan Eloquent create/update, agar histori lama tidak memicu observer yang bisa membuat invoice, warehouse confirmation, atau jurnal baru secara tidak terkontrol.

Pilot yang sudah dieksekusi:

```bash
php artisan legacy:rehydrate-sales-orders --source=inventory --limit=20 --execute
php artisan legacy:rehydrate-sales-orders --source=inventory_cab --limit=20 --execute
```

Hasil pilot:

- sale_orders aktif hasil rehidrasi: `40`
  - inventory: `20`
  - inventory_cab: `20`
- sale_order_items aktif hasil rehidrasi: `75`
- pada pilot 20 dokumen per source, mapping customer dan product valid semua; missing customer = `0`, missing products = `0`, without items = `0`

Status fase dua saat dokumen ini ditulis:

- browser arsip histori: live
- pipeline rehidrasi sales: live dan tervalidasi lewat pilot kecil
- rehidrasi sales full historical per source/periode: belum dijalankan penuh, sengaja ditahan agar dapat dikontrol per periode sesuai kebutuhan operasional dan performa UI