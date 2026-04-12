# Legacy Request Mapping Analysis - 2026-04-11

## Kesimpulan

`knr_requests` dan `dtm_requests` tidak aman dipetakan langsung ke ERP `order_requests` saat ini.

Rekomendasi saat ini adalah:

1. simpan request legacy sebagai arsip / referensi,
2. jangan rehydrate ke `order_requests`,
3. jika ingin mengaktifkan kembali alurnya di ERP, bangun modul `internal_request` atau `stock_transfer_request` yang memang menyimpan asal lokasi, tujuan lokasi, progres kirim, dan progres proses.

## Bukti Struktur Data

### Header request legacy

Kolom inti pada kedua source (`inventory.knr_requests` dan `inventory_cab.dtm_requests`) sama:

- `request_no`
- `request_date`
- `delivery_date`
- `request_status`
- `delivery_status`
- `process_status`
- `request_place`, `request_place_id`, `request_place_name`
- `dest_place`, `dest_place_id`, `dest_place_name`
- `process_document_no`, `process_document_id`
- `result_document_no`, `result_document_id`
- `from_sales_id`

Maknanya jelas: dokumen ini bukan hanya permintaan beli, tetapi lifecycle fulfillment antar lokasi yang bisa melahirkan dokumen proses lain.

### Detail request legacy

Kolom inti pada `knr_requests_detail` dan `dtm_requests_detail`:

- `product_id`
- `product_code`
- `qty`
- `qty_deliver`
- `qty_process`
- `deliver_date`, `deliver_by`
- `process_date`, `process_by`

Ini menunjukkan request legacy menyimpan progres eksekusi fisik, bukan sekadar daftar kebutuhan procurement.

## Bukti Data Nyata

### Volume

- `inventory.knr_requests`: `64`
- `inventory.knr_requests_detail`: `78`
- `inventory_cab.dtm_requests`: `6,430`
- `inventory_cab.dtm_requests_detail`: `15,307`

### Pola lokasi

- `inventory`: dominan `pusat -> cabang`
- `inventory_cab`: ada `cabang -> pusat` dan `cabang -> cabang`

Ini lebih dekat ke internal replenishment / transfer fulfillment daripada purchase requisition biasa.

### Pola status

Contoh status yang ditemukan:

- `Request Diproses`
- `Request Selesai`
- `Barang Belum Dikirim`
- `Barang Sudah Dikirim Semua`
- `Barang Sudah Diterima Sebagian`
- `Barang Sudah Diterima Semua`

`order_requests` ERP saat ini hanya memodelkan request pembelian dengan status approval dan fulfillment purchase. Ia tidak menyimpan status kirim-terima multi tahap seperti ini.

### Keterkaitan dokumen lain

Contoh row legacy memperlihatkan kolom seperti:

- `process_document_no = SL20240828-RXK7`
- `result_document_no = PO20240829-TQQB`

Artinya satu request legacy bisa terhubung ke dokumen sales dan purchase sekaligus, atau ke dokumen proses internal yang bukan 1:1 dengan `order_requests` ERP.

## Kenapa Tidak Cocok ke ERP `order_requests`

Model ERP `order_requests` saat ini berisi konsep berikut:

- satu request pembelian,
- satu `warehouse_id` / `cabang_id`,
- item dengan `supplier_id`, `unit_price`, `discount`, `tax`, `subtotal`,
- approval yang dapat membentuk `purchase_orders`.

Yang hilang jika dipaksa map dari legacy request:

- asal lokasi dan tujuan lokasi,
- status kirim dan status terima,
- qty yang sudah dikirim dan qty yang sudah diproses,
- link ke dokumen proses/result,
- alur `cabang -> cabang` yang bukan procurement murni.

Jika dipaksakan ke `order_requests`, data yang hilang bukan minor. Itu mengubah arti bisnis dokumennya.

## Opsi Target yang Lebih Tepat

### Opsi A: archive only

Paling aman untuk saat ini.

- semua request tetap bisa dicari sebagai histori,
- tidak memicu salah interpretasi operasional,
- tidak menuntut pembuatan supplier/pricing palsu.

### Opsi B: modul baru `internal_request` / `stock_transfer_request`

Target domain yang lebih cocok:

- header menyimpan asal lokasi, tujuan lokasi, nomor request, tanggal, status request, status kirim, status terima,
- detail menyimpan qty request, qty deliver, qty process,
- relasi ke dokumen sales/purchase/mutation/delivery bila ada.

Ini adalah opsi terbaik jika request legacy memang masih ingin dioperasionalkan lagi.

### Opsi C: partial derivation ke modul lain

Beberapa request tertentu bisa diturunkan menjadi dokumen operasional baru, misalnya:

- permintaan pusat ke cabang menjadi transfer stok,
- request dari demand sales menjadi draft replenishment,
- request yang berakhir PO menjadi procurement trail.

Namun ini harus rule-based dan bukan migration 1:1.

## Rekomendasi Akhir

Keputusan implementasi yang disarankan untuk repository ini saat ini:

1. tambahkan request family ke arsip legacy bila histori request perlu dicari di ERP,
2. jangan rehydrate ke `order_requests`,
3. buat desain modul request internal terpisah bila user ingin request lama aktif lagi,
4. jika nanti perlu, siapkan translator per-skenario dari request legacy ke mutation / purchase / fulfillment baru, bukan copy langsung.