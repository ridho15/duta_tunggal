# Delivery Order Journal Entry Cascade Operations

## Overview
Delivery Order sekarang memiliki cascade operations untuk journal entries yang membuat data tetap sinkron dengan perubahan Delivery Order.

## Behavior Changes

### Sebelum (Immutable Journal Entries)
- Journal entries dibuat saat Delivery Order status menjadi 'sent'
- Journal entries tidak pernah diubah atau dihapus
- Perubahan quantity setelah 'sent' tidak mempengaruhi journal entries
- Soft delete Delivery Order tidak menghapus journal entries

### Sesudah (Synchronous Journal Entries)
- Journal entries dibuat saat Delivery Order status menjadi 'sent'
- Journal entries diupdate otomatis ketika quantity diubah setelah 'sent'
- Journal entries dihapus otomatis ketika Delivery Order di-soft delete
- Stock reservations juga dihapus saat Delivery Order di-soft delete

## Implementation Details

### Files Modified

#### 1. `app/Observers/DeliveryOrderObserver.php`
- **Method `deleted()`**: Menghapus journal entries dan stock reservations saat Delivery Order di-soft delete
- **Method `updated()`**: Mendeteksi perubahan quantity dan memanggil `handleQuantityUpdateAfterSent()`
- **Method `handleQuantityUpdateAfterSent()`**: Menghapus journal entries lama dan membuat yang baru dengan quantity terbaru
- **Method `hasQuantityChanges()`**: Mendeteksi apakah ada perubahan quantity pada Delivery Order items
- **Method `createJournalEntriesForDelivery()`**: Refactored untuk reusable

#### 2. `app/Observers/DeliveryOrderItemObserver.php` (New)
- **Method `updated()`**: Mendeteksi perubahan quantity pada Delivery Order Item dan memanggil DeliveryOrderObserver untuk update journal entries

#### 3. `app/Providers/AppServiceProvider.php`
- Registered `DeliveryOrderItemObserver` untuk model `DeliveryOrderItem`

#### 4. `tests/Feature/DeliveryOrderJournalIntegrationTest.php`
- Updated test cases untuk memverifikasi synchronous behavior
- Manual observer invocation untuk memastikan cascade operations ter-trigger

### Technical Implementation

#### Observer Pattern
```php
// DeliveryOrderObserver
public function deleted(DeliveryOrder $deliveryOrder)
{
    // Delete journal entries and stock reservations
}

public function updated(DeliveryOrder $deliveryOrder)
{
    if ($this->hasQuantityChanges($deliveryOrder)) {
        $this->handleQuantityUpdateAfterSent($deliveryOrder);
    }
}

// DeliveryOrderItemObserver
public function updated(DeliveryOrderItem $item)
{
    if ($item->deliveryOrder->status === 'sent' && $item->isDirty('quantity')) {
        $observer = new DeliveryOrderObserver();
        $observer->handleQuantityUpdateAfterSent($item->deliveryOrder);
    }
}
```

#### Journal Entry Management
- **Creation**: Saat status Delivery Order menjadi 'sent'
- **Update**: Saat quantity berubah setelah 'sent' (hapus yang lama, buat yang baru)
- **Deletion**: Saat Delivery Order di-soft delete

#### Financial Impact
- **Cost of Goods Sold (COGS)**: Debit account berdasarkan quantity terkirim
- **Inventory Reduction**: Credit account untuk mengurangi inventory value
- Journal entries selalu mencerminkan quantity aktual yang dikirim

## Testing
Semua test cases di `DeliveryOrderJournalIntegrationTest` berhasil:
- ✅ Journal entries dibuat saat status menjadi 'sent'
- ✅ Journal entries diupdate saat quantity berubah setelah 'sent'
- ✅ Journal entries dihapus saat Delivery Order di-soft delete

## Benefits
1. **Data Integrity**: Journal entries selalu akurat dan mencerminkan kondisi terkini
2. **Financial Accuracy**: Laporan keuangan selalu konsisten dengan operasional
3. **Audit Trail**: Perubahan quantity tercatat dalam journal entries yang baru
4. **Consistency**: Behavior serupa dengan transaction types lainnya (Purchase Receipt, Invoice, dll)

## Migration Notes
- Existing Delivery Orders dengan status 'sent' akan tetap memiliki journal entries immutable
- Perubahan quantity pada Delivery Orders yang sudah ada sebelum implementasi ini tidak akan mempengaruhi journal entries lama
- Implementasi ini hanya berlaku untuk Delivery Orders yang dibuat/diubah setelah deployment