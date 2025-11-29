# Sistem Reservasi Stock untuk Material Issue

## Overview
Sistem reservasi stock untuk Material Issue memastikan bahwa stock yang dibutuhkan untuk produksi tidak dapat digunakan oleh transaksi lain sebelum Material Issue diselesaikan.

## Cara Kerja

### 1. Status Material Issue dan Reservasi Stock
- **Draft**: Tidak ada reservasi stock
- **Pending Approval**: Tidak ada reservasi stock
- **Approved**: Stock di-reserve secara otomatis
- **Completed**: Stock reservasi dikonsumsi (dikurangi dari qty_available dan qty_reserved)
- **Rejected/Draft (dari approved)**: Stock reservasi di-release

### 2. Field Database
- `inventory_stocks.qty_reserved`: Total quantity yang di-reserve untuk semua transaksi
- `stock_reservations.material_issue_id`: Menandai reservasi milik Material Issue tertentu

### 3. Validasi Stock
Validasi stock sekarang mempertimbangkan reservasi:
```
Available Stock = qty_available - qty_reserved
```

### 4. Alur Proses

#### Ketika Material Issue di-approve:
1. Sistem membuat record di `stock_reservations` untuk setiap item
2. `qty_reserved` di inventory stock di-increment sesuai quantity yang dibutuhkan
3. Stock tersebut tidak dapat digunakan untuk transaksi lain

#### Ketika Material Issue di-complete:
1. `qty_available` dan `qty_reserved` di inventory stock di-decrement
2. Record reservasi dihapus dari `stock_reservations`
3. Stock benar-benar dikurangi dari inventory

#### Ketika Material Issue di-reject atau dihapus:
1. `qty_reserved` di inventory stock di-decrement
2. Record reservasi dihapus dari `stock_reservations`
3. Stock kembali tersedia untuk transaksi lain

## API Methods

### StockReservationService

#### Reserve Stock
```php
$stockReservationService->reserveStockForMaterialIssue($materialIssue);
```

#### Release Stock Reservations
```php
$stockReservationService->releaseStockReservationsForMaterialIssue($materialIssue);
```

#### Consume Reserved Stock
```php
$stockReservationService->consumeReservedStockForMaterialIssue($materialIssue);
```

#### Check Stock Availability
```php
$result = $stockReservationService->checkStockAvailabilityForMaterialIssue($materialIssue);
// Returns: ['valid' => true/false, 'message' => string]
```

#### Get Available Stock (considering reservations)
```php
$availableQty = $stockReservationService->getAvailableStock($productId, $warehouseId);
```

#### Get Reservations for Material Issue
```php
$reservations = $stockReservationService->getReservationsForMaterialIssue($materialIssueId);
```

## Keuntungan Sistem

1. **Mencegah Stock Conflict**: Stock yang dibutuhkan produksi tidak bisa digunakan untuk penjualan lain
2. **Real-time Validation**: Validasi stock mempertimbangkan reservasi yang ada
3. **Audit Trail**: Semua reservasi tercatat dengan jelas kepemilikannya
4. **Automatic Management**: Reservasi dikelola otomatis berdasarkan status Material Issue

## Monitoring Reservasi

Untuk melihat reservasi stock yang aktif:

```php
$stockReservationService = app(\App\Services\StockReservationService::class);
$allReservations = $stockReservationService->getAllActiveReservations();
```

Atau untuk Material Issue tertentu:

```php
$reservations = $stockReservationService->getReservationsForMaterialIssue($materialIssueId);
```</content>
<parameter name="filePath">/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/docs/STOCK_RESERVATION_SYSTEM.md