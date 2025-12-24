# Jawaban Review & Pertanyaan BBRP

## 1. CABANG: COA CABANG menurut Pak Josua bagaimana?
COA (Chart of Accounts) dalam sistem ini tampaknya bersifat global dan tidak terikat pada cabang tertentu. Setiap cabang mungkin menggunakan COA yang sama, atau COA dapat dibedakan berdasarkan kode/nama yang mencerminkan cabang. Tidak ada field cabang_id di model ChartOfAccount, sehingga COA tidak secara eksplisit di-scope per cabang. Untuk detail lebih lanjut, perlu konfirmasi dengan Pak Josua mengenai struktur COA per cabang.

## 2. CUSTOMER: Dalam membuat customer baru ada pilihan cabang, kondisi ini mempertimbangkan apa dan fungsi nya sbg apa?
Pilihan cabang saat membuat customer baru mempertimbangkan bahwa customer dapat terasosiasi dengan cabang tertentu untuk keperluan pelaporan, segmentasi, atau kontrol akses. Fungsinya adalah untuk mengelompokkan customer berdasarkan cabang operasional, meskipun customer dapat membeli dari cabang lain. Ini membantu dalam analisis penjualan per cabang dan manajemen hubungan customer.

### 2a. Customer: Type bayar "BEBAS" apa maksud nya?
"Bebas" berarti customer tidak terikat pada syarat pembayaran tertentu. Customer dapat membayar kapan saja tanpa batasan kredit atau tempo, berbeda dengan "Kredit" (ada tempo) atau "COD" (bayar di tempat).

### 2b. Ada kolom Sales (delivadate, request, app) artinya apa?
Kolom ini merujuk pada relasi Sales Order dari customer. "Delivadate" kemungkinan adalah delivery_date (tanggal pengiriman), "request" adalah request_approve_at (tanggal permintaan approval), "app" adalah approve_at (tanggal approval). Ini menunjukkan status dan timeline sales order terkait customer.

## 3. KATEGORI PRODUK
- **KODE**: Boleh diisi sesuai dengan ketentuan dari DT sendiri dan tidak boleh memiliki KODE yang sama dengan kategori lainnya.
- **NAMA**: Nama kategori produk, standar seperti biasa.
- **CABANG**: Pilihan cabang berfungsi untuk mengasosiasikan kategori produk dengan cabang tertentu, meskipun saat ini cabang_id telah dihapus dari model ProductCategory (berdasarkan migration terbaru). Aplikasinya mungkin untuk filtering produk per cabang atau pelaporan inventori per cabang.

## 4. PRODUK
- **COST PRICE**: Isinya adalah harga beli asli (nett value) ditambah biaya ongkos, pajak, dan biaya lainnya yang terkait pembelian produk.
- **ITEM VALUE**: Isinya adalah harga jual (sell_price) produk, yang merupakan nilai untuk penjualan.

## 5. DIMANA fitur rubah harga secara masal, maksud saya dalam 1 page tapi kita input satu persatu harga, ataupun secara upload excell
Fitur rubah harga secara masal belum tersedia di sistem. Saat ini, harga produk hanya dapat diubah satu per satu melalui form edit produk. Tidak ada bulk action untuk update harga atau fitur upload Excel untuk mass update harga.

**Update:** Fitur "Update Harga Cepat" telah ditambahkan sebagai action di tabel produk. Action ini membuka modal untuk update cepat harga (cost_price, sell_price, biaya, harga_batas, item_value) per produk tanpa perlu membuka form edit penuh.

**Update Lanjutan:** Bulk action "Update Harga Massal" telah ditambahkan. Pilih beberapa produk di tabel, lalu gunakan bulk action ini untuk update harga secara massal dalam satu modal. Field yang dikosongkan tidak akan diubah. Untuk upload Excel, belum tersedia tapi dapat dikembangkan jika diperlukan.

**Test Unit:** Test untuk fitur update harga telah dibuat dan berhasil pass:
- `RakServiceTest`: Test generate kode rak ✅ PASS
- `ProductTest`: Test update harga per produk dan bulk update ✅ PASS

## 6. RAK: Bagaimana cara pandang kode dan nama rak, bisa berikan contoh gambarannya?
Rak memiliki kode (code) dan nama (name), serta terasosiasi dengan warehouse (gudang). Contoh: Kode "R001", Nama "Rak A1" di Gudang Utama. Ini membantu dalam lokasi penyimpanan spesifik untuk inventory management.

## 7. Suplier: NAMA PERUSAHAAN? NAMA SUPLIER? Apa beda dan pemilihan Nama Suplier dan Nama Perusahaan, tolong beri contoh
- **NAMA PERUSAHAAN**: Nama entitas bisnis supplier (perusahaan).
- **NAMA SUPLIER**: Nama individu atau nama dagang supplier.
Beda: Nama perusahaan adalah nama legal bisnis, sedangkan nama supplier bisa nama kontak. Contoh: Nama Perusahaan "PT ABC Supplier", Nama Supplier "John Doe". Pemilihan tergantung pada bagaimana supplier terdaftar; gunakan nama perusahaan untuk entitas formal, nama supplier untuk individu.

## 8. Dimana notifikasi dan laporan minimum stok?
Notifikasi minimum stok dapat dilihat melalui Inventory Stock resource, di mana ada field qty_min untuk minimum stock level. Sistem dapat memberikan alerts ketika stock mendekati minimum. Laporan dapat diakses melalui Inventory Report atau halaman terkait inventory, yang menampilkan qty minimum dan status stock.</content>
<parameter name="filePath">/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/REVIEW_JAWABAN.md