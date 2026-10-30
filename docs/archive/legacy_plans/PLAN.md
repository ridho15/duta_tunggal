# Plan: Verifikasi Perbaikan Bug Inventory Tidak Mengganggu Module Lain

## Problem Statement

Perbaikan bug inventory saat "Mulai Pengiriman" telah selesai diimplementasi. Sekarang perlu memastikan perubahan tersebut:

1. **Tidak mengubah flow** yang sudah ada
2. **Tidak mengganggu fitur/module lain** yang terkait
3. **Memastikan semua test passing**

---

## Analisis Dampak Perubahan

### Perubahan yang Dilakukan

| File | Method | Sebelum | Sesudah |
|------|--------|---------|---------|
| `DeliveryOrderObserver.php` | `handleReservationReleaseStatus()` | DELETE StockReservation | Buat StockMovement + TIDAK hapus reservation |
| `DeliveryOrderObserver.php` | `createStockMovementsForShippingStart()` | - (BARU) | Buat StockMovement saat status='sent' |
| `DeliveryOrderObserver.php` | `handleCompletedStatus()` | Buat StockMovement | Skip StockMovement (sudah dibuat saat 'sent') |

### Area yang Berpotensi Terdampak

1. **StockMovement Creation** - Kemungkinan duplicate creation
2. **StockReservation Deletion** - Reservation sekarang tidak dihapus saat 'sent'
3. **InventoryStock Updates** - qty_available sekarang turun saat 'sent' bukan 'completed'
4. **Journal Entries** - Journal entries tetap dibuat saat 'completed' (tidak berubah)
5. **DeliverySchedule Flow** - Alur melalui DeliverySchedule mungkin berbeda

---

## Verifikasi Plan

### Step 1: Run Existing Tests

Jalankan semua test yang terkait untuk memastikan tidak ada regresi:

```bash
# Test Delivery Order Flow
php artisan test --filter=CompleteDeliveryOrderFlowTest

# Test Delivery Schedule
php artisan test --filter=DeliveryScheduleTest

# Test Stock Movement
php artisan test --filter=StockMovementComprehensiveTest

# Test Inventory
php artisan test --filter=InventoryReportServiceTest

# Test Sales Order to DO Flow
php artisan test --filter=SalesOrderToDeliveryOrderCompleteTest
```

### Step 2: Check StockMovement Duplicates

Identifikasi semua tempat yang membuat StockMovement dengan type='sales':

| Lokasi | Trigger | Status | Risk |
|--------|---------|--------|------|
| `DeliveryOrderObserver::createStockMovementsForShippingStart()` | DO → 'sent' | ✅ Diubah | Low - kita kontrol |
| `DeliveryOrderObserver::handleCompletedStatus()` | DO → 'completed' | ✅ Skip | Low - sudah di-skip |
| `DeliveryOrderService::postDeliveryOrder()` | Manual posting | ⚠️ Check | Potential duplicate |
| `DeliveryOrderItem::syncStockMovements()` | Quantity change | ⚠️ Check | Sync independent |
| `SaleOrderObserver::handleStockReductionForSelfPickup()` | SO completed (Ambil Sendiri) | ✅ Safe | Different flow |

### Step 3: Verifikasi postDeliveryOrder() Method

Check `DeliveryOrderService::postDeliveryOrder()` - apakah ada risk duplicate:

```php
// Di DeliveryOrderService::postDeliveryOrder()
$existingMovement = StockMovement::where('from_model_type', DeliveryOrderItem::class)
    ->whereIn('from_model_id', $itemIds)
    ->where('type', 'sales')
    ->first();

if ($existingMovement) {
    Log::info('Stock movement already exists, skipping...');
    continue; // Skip if already exists
}
```

### Step 4: Check StockReservation Behavior

StockReservation sekarang TIDAK dihapus saat DO status → 'sent'. Verifikasi:

1. Reservation tetap ada dengan qty_reserved
2. Reservation baru dihapus saat DO → 'completed' (via `deleted()` method)
3. Tidak ada flow lain yang bergantung pada reservation dihapus saat 'sent'

### Step 5: Test End-to-End Flow

Test complete flow dari start sampai finish:

```
1. Create InventoryStock (qty_available=300)
2. Create DO → approve → sent → completed
3. Verify:
   - StockMovement created at 'sent' (not 'completed')
   - qty_available decreased at 'sent'
   - qty_reserved stays until 'completed'
   - Only 1 StockMovement per DO item
   - Journal entries created at 'completed'
```

---

## Test Cases yang Perlu Dijalankan

### Critical Tests (Must Pass)

| Test | File | Coverage |
|------|------|----------|
| CompleteDeliveryOrderFlowTest | `tests/Feature/` | Full DO lifecycle |
| DeliveryScheduleInventoryFixTest | `tests/Feature/` | Bug fix verification |
| StockMovementComprehensiveTest | `tests/Unit/` | All movement types |
| DeliveryOrderFeatureTest | `tests/Feature/` | Status transitions |

### Integration Tests (Should Pass)

| Test | File | Coverage |
|------|------|----------|
| SalesOrderToDeliveryOrderCompleteTest | `tests/Feature/` | SO → DO flow |
| CompleteSalesFlowFilamentTest | `tests/Feature/` | Full sales flow |
| DeliveryOrderJournalIntegrationTest | `tests/Feature/` | Journal entries |

### Edge Case Tests

1. **Multi-warehouse DO** - StockMovement per warehouse
2. **Partial quantity change** - syncStockMovements behavior
3. **DO cancel before sent** - Reservation cleanup
4. **Self-pickup (Ambil Sendiri)** - Different from delivery flow

---

## Identifikasi & Perbaikan Masalah

### Potential Issue 1: Duplicate StockMovement from Service

**Risk:** Jika `postDeliveryOrder()` dipanggil setelah observer membuat StockMovement

**Check:**
```php
// DeliveryOrderService::postDeliveryOrder() line 284-290
$existingMovement = StockMovement::where('from_model_type', DeliveryOrderItem::class)
    ->whereIn('from_model_id', $itemIds)
    ->where('type', 'sales')
    ->first();

if ($existingMovement) {
    continue; // Skip - sudah dibuat
}
```

**Verifikasi:** Test ini harus PASS

### Potential Issue 2: rak_id Mismatch

**Risk:** StockMovementObserver tidak update qty_available jika rak_id tidak match

**Check:** StockMovementObserver::adjustAvailableStockByKey()
```php
$inventoryStock = InventoryStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->where('rak_id', $rakId)  // ← rak_id harus match
    ->lockForUpdate()
    ->first();
```

**Verifikasi:** Test dengan rak_id match harus PASS

### Potential Issue 3: Reservation Not Deleted

**Risk:** Reservation tetap ada jika DO di-cancel setelah 'sent'

**Check:** `DeliveryOrderObserver::deleted()` masih menghapus reservation

**Verifikasi:** Test cancellation flow harus PASS

---

## Execution Plan

### Phase 1: Run All Tests (60 menit)
```bash
php artisan test --filter=DeliveryOrder 2>&1 | tee test-results.txt
php artisan test --filter=StockMovement 2>&1 | tee -a test-results.txt
php artisan test --filter=Inventory 2>&1 | tee -a test-results.txt
php artisan test --filter=DeliverySchedule 2>&1 | tee -a test-results.txt
```

### Phase 2: Fix Failed Tests (varies)
Identifikasi dan perbaiki test yang gagal

### Phase 3: Manual Verification (30 menit)
1. Test via UI: Complete DO flow
2. Check inventory stock changes
3. Verify journal entries

### Phase 4: Documentation (15 menit)
- Update changelog
- Document behavior changes

---

## Success Criteria

✅ Semua test PASS
✅ Tidak ada duplicate StockMovement
✅ qty_available turun saat 'sent' (bukan 'completed')
✅ qty_reserved tetap sampai 'completed'
✅ Journal entries tetap dibuat saat 'completed'
✅ Delivery schedule flow berfungsi normal

---

## Estimated Time

- Phase 1: 60 menit
- Phase 2: varies (depends on failures)
- Phase 3: 30 menit
- Phase 4: 15 menit
- **Total: ~2-4 jam**