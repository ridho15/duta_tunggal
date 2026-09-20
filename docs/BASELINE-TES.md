# Baseline Tes — kegagalan yang sudah ada

> Dibuat 20 September 2026 (T0.5) · Sumber daftar: `tests/baseline-failures.txt` (dibuat `scripts/run-tests-chunked.php` pada commit `66e78a3`).
> **Aturan:** pekerjaan baru **tidak boleh menambah** kegagalan di luar daftar itu. Cek dengan
> `composer test:chunked -- --compare=tests/baseline-failures.txt` (exit 1 bila ada kegagalan baru atau crash).
> Memperbarui baseline hanya lewat commit terpisah yang menjelaskan alasannya.

## Ringkasan

| | Jumlah |
|---|---|
| Berkas uji | 356 |
| Tes | 2.981 |
| Lolos | 2.773 |
| **Gagal (baseline)** | **201** |
| Dilewati | 7 |
| Crash | 0 |
| Waktu | ± 24 menit (12 potongan × 30 berkas, satu proses per potongan) |

Satu proses tunggal untuk seluruh suite kini juga **selesai tuntas** (T0.3): 2.740 lolos / 224 gagal dalam 24 menit — sebelumnya crash memori
karena `ini_set('memory_limit','512M')` menurunkan `-d memory_limit=-1`. Selisih 224 vs 201 berasal dari pencemaran antar-tes pada proses tunggal
(sebab itu runner terpotong yang menjadi acuan).

## Yang diperbaiki pada T0.5 (dulu gagal, kini lolos)

| Berkas | Sebab (fixture usang, bukan cacat kode) | Perbaikan |
|---|---|---|
| `InvoiceObserverPostSalesTest` (5) | Item invoice tidak konsisten dengan header → penjaga saldo jurnal menolak; tes "PPN vs total turunan" tidak mungkin lagi | Fixture disamakan; tes PPN diganti; **tes baru** penjaga saldo |
| `BranchInheritanceFlowTest` (2) | Invoice bernilai Rp 0 ditolak (guard Poin 3) | Beri subtotal/total |
| `SalesInvoiceShippingCostJournalTest` (2) | Invoice SO ber-DO terbit **per DO saat DO selesai**, bukan saat SO selesai | Tes memakai jalur DO selesai; COA fixture bertipe & aktif + akun persediaan (kini menjadi regresi jurnal ongkir 43 assertion) |
| `SaleOrderFeatureTest` (2) | Mock `Auth` tak menyediakan `id()` | Tambah `Auth::id()` |
| `UATUIUXAuditVerificationTest` (1) | ID keras `cabang_id=1`, `product_id=1` → bergantung urutan | Data dibuat sendiri |
| `Api/QuotationApiTest`, `Api/SaleOrderApiTest` (5) | Endpoint tulis kini memeriksa izin; produk tanpa kategori | Izin eksplisit + factory (commit audit ulang) |

## Yang masih gagal — dikelompokkan

| Kelompok | Jml | Penyebab | Disposisi |
|---|---|---|---|
| `RekonsiliasiBankPage*Test` | 37 | Halaman `RekonsiliasiBankPage` **tidak ada di `app/`** — tes usang | Di luar penjualan. **Keputusan A-2:** dibiarkan di baseline; hapus/skip beralasan diputuskan terpisah |
| `PurchaseInvoiceResourceTest` | 33 | Tanda tangan closure `$get` berubah | Pembelian — tidak disentuh |
| `OrderRequest*`, `PurchaseOrder*`, `PurchaseReturn*`, QC, Material Issue, Vendor Payment, dll. | ± 70 | Aksi approve/FK/fixture pembelian & produksi | Pembelian/Produksi — tidak disentuh |
| **`StockReservationServiceTest` (5), `StockReservationFlowTest` (4)** | 9 | Fixture: pembuatan produk otomatis membuat baris `InventoryStock` (rak null, stok 0) sehingga `InventoryStock::create(... rak_id ...)` membuat baris **kedua**; layanan membaca baris pertama (`->first()`) → "Tersedia: 0". Tes-tes ini memakai jalur **kode mati** (`SalesOrderService::confirm()`, `DeliveryOrderService::postDeliveryOrder()`) dan menetapkan alur asli: *reservasi saat SO dikonfirmasi, dilepas saat DO diposting* | **Backlog T2** — dipakai sebagai spesifikasi ketika reservasi dihidupkan & X1 diperbaiki; fixture ditulis ulang di sana. Catatan: layanan membaca satu baris per produk×gudang, mengabaikan `rak_id` → temuan desain untuk T2 |
| `InvoiceEditAndDeliveryOrderTest` | 3 | Memanggil `WarehouseConfirmation::createDeliveryOrderForConfirmedWarehouseConfirmation()` yang sudah tidak ada (WC kini dibuat otomatis saat DO dibuat) | **Backlog T2** — ditulis ulang bersama transisi DO |
| `SalesOrderSelfPickupApprovedTest` | 1 | Mengharapkan status SO `confirmed` (status yang praktis tak terpakai; lihat X2) | Backlog T2 |
| `SaleOrderMultiWarehouseTest` (1), `StatusRowClassesTest` (1), `QuotationFeatureTest` (1), `LedgerPostingServiceTest` (1), `StockMovementComprehensiveTest` (1) | 5 | Deterministik (gagal juga di isolasi); penyebab beragam (pengurangan stok pickup, HTML kelas baris, opsi tipe pajak, fixture jurnal, data uji stok) | Belum dianalisis mendalam; tidak memblokir T1 |
| `PermissionRoleBranchTaxAuditTest` (3), `RolePermissionMappingTest`, `DestructivePermissionSmokeTest` | 5 | Peta izin vs peran menyimpang ("Sales Manager"/"Admin" memiliki izin destruktif; tarif pajak) | **Backlog T3** (matriks kunci & izin) |
| `IndonesianMoneyValidationTest` (2) | 2 | Tes pemindai sumber untuk Order Request (Pembelian) | Pembelian |
| Lain-lain (`InventoryReportTest`, `WarehouseAuditTest`, `JournalEntryTest`, aset, deposit, dll.) | sisanya | Beragam | Dicatat |

## Temuan kecil dari triase (tidak diperbaiki di T0)

- `DeliveryOrderObserver` (sekitar baris 556) menulis `'✓'` di dalam string **kutip tunggal** — tampil sebagai teks `✓` mentah pada pesan galat COA produk (kosmetik; masuk T7 bersama bahasa/pesan).
- `Customer::creating` membuat "Cabang Default" otomatis bila tak ada cabang (efek samping pada tes/data awal).
- Workflow CI (`.github/workflows/tests.yml`) bawaan Laravel — menyalin `.env.example` yang tidak ada dan tanpa layanan MySQL, sehingga tidak dapat berjalan; runner terpotong dibuat agar dapat dipakai CI kelak.
