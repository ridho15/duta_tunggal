# Audit Referensi Role Legacy Duta Tunggal ERP

Dokumen ini mencatat referensi role yang tidak selaras dengan role seeded di aplikasi. Tujuannya adalah memisahkan alias yang masih sengaja dipakai dari referensi yang kemungkinan besar perlu dinormalisasi.

## Role Seeded sebagai Acuan

Role yang saat ini disediakan oleh seeder adalah:

- Owner
- Super Admin
- Admin
- Sales Manager
- Sales
- Kasir
- Inventory Manager
- Admin Inventory
- Checker
- Finance Manager
- Admin Keuangan
- Accounting
- Purchasing
- Purchasing Manager
- Warehouse Staff
- Delivery Driver
- Customer Service
- Auditor
- IT Support

## Temuan Legacy atau Mismatch

### 1. Super Sales

Lokasi referensi:

- [app/Policies/DeliveryOrderPolicy.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Policies/DeliveryOrderPolicy.php)

Temuan:

- Policy delivery order masih memeriksa role `Super Sales` di beberapa kondisi view, update, dan delete.
- Role ini tidak ada di [RoleSeeder.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/database/seeders/RoleSeeder.php).

Analisa:

- Ini tampak sebagai role legacy atau alias lama.
- Karena `Sales Manager` sudah ada di seeder dan dipakai sebagai role penanggung jawab penjualan, `Super Sales` sangat mungkin harus diganti ke `Sales Manager` bila memang maksudnya role pengawas sales.

Risiko:

- User dengan role seeded resmi tidak otomatis melewati branch check yang masih memakai `Super Sales`.
- Policy bisa terlihat konsisten di komentar, tetapi secara naming tidak selaras dengan data role nyata.

### 2. super_admin lowercase

Lokasi referensi:

- [app/Filament/Resources/Reports/AgeingReportResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/Reports/AgeingReportResource.php)
- [app/Filament/Resources/Reports/AgeingReportResource/Pages/ViewAgeingReport.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/Reports/AgeingReportResource/Pages/ViewAgeingReport.php)

Temuan:

- Beberapa visibility check masih memakai `super_admin` dalam huruf kecil.
- Seeder dan helper menggunakan `Super Admin` dengan spasi dan kapitalisasi berbeda.

Analisa:

- Ini hampir pasti mismatch nama role.
- Jika tidak ada alias tambahan di database, check tersebut tidak akan cocok dengan role seeded resmi.

Risiko:

- Fitur yang hanya terlihat oleh `super_admin` bisa tersembunyi dari user `Super Admin` yang seharusnya berhak.

### 3. Finance dan Administrator

Lokasi referensi:

- [app/Filament/Resources/DepositAdjustmentResource.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Filament/Resources/DepositAdjustmentResource.php)

Temuan:

- `canAccess()` masih memeriksa role `Finance` dan `Administrator` selain `Super Admin`.

Analisa:

- Kedua nama tersebut tidak ada di seed role resmi.
- Secara fungsi, `Finance` kemungkinan maksudnya `Finance Manager` atau `Admin Keuangan`, sedangkan `Administrator` kemungkinan maksudnya `Admin`.

Risiko:

- User finance yang memakai role seeded resmi bisa gagal mengakses halaman ini walaupun secara bisnis seharusnya boleh.

### 4. Komentar lama yang belum dihapus

Lokasi referensi:

- [app/Policies/DeliveryOrderPolicy.php](/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/app/Policies/DeliveryOrderPolicy.php)

Temuan:

- Komentar masih menyebut `Super Sales` walau role resmi di seeder tidak memakai nama itu.

Analisa:

- Ini bukan bug fungsional langsung, tetapi tanda bahwa dokumentasi internal di kode belum diselaraskan dengan naming terbaru.

## Kesimpulan Audit

Referensi role yang paling bermasalah adalah `Super Sales`, `super_admin`, `Finance`, dan `Administrator` karena tidak selaras dengan role seeded resmi.

Jika tidak ada kebutuhan kompatibilitas dengan database lama, rekomendasi paling aman adalah menormalisasi referensi tersebut ke role resmi berikut:

- `Super Sales` -> `Sales Manager` bila konteksnya pengawas penjualan
- `super_admin` -> `Super Admin`
- `Finance` -> `Finance Manager` atau `Admin Keuangan` sesuai konteks proses
- `Administrator` -> `Admin`

## Rekomendasi Tindak Lanjut

1. Ganti role check legacy di policy dan resource yang sudah teridentifikasi.
2. Tambahkan test sederhana untuk memastikan role seeded resmi dapat mengakses halaman yang dimaksud.
3. Jika ada kebutuhan backward compatibility, buat alias role secara eksplisit di seeder atau helper, jangan dibiarkan hanya sebagai string lama di kode.