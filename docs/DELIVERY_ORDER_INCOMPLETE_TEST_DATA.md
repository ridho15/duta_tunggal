# Delivery Order Incomplete Test Data

Data delivery order yang belum complete telah berhasil dibuat untuk testing berbagai workflow dan cascade operations.

## Data yang Dibuat

### Customer
- **Name**: Test Customer Incomplete DO
- **Email**: testincomplete@example.com
- **Phone**: 081234567891

### Products
1. **Test Product Incomplete 1** - SKU: TEST-INCOMPLETE-1-{timestamp}, Price: Rp 150,000
2. **Test Product Incomplete 2** - SKU: TEST-INCOMPLETE-2-{timestamp}, Price: Rp 200,000

### Warehouse
- **Name**: Test Warehouse Incomplete
- **Code**: TEST-INCOMPLETE

## Delivery Orders yang Dibuat (6 orders)

### 1. DO-INCOMPLETE-1-{timestamp} - **Status: draft**
- **Description**: Draft - belum diajukan
- **Sale Order**: SO-INCOMPLETE-1-{timestamp}
- **Product**: Test Product Incomplete 1
- **Quantity**: 5 units
- **Journal Entries**: 0 (belum ada)
- **Stock Reservation**: Tidak ada

### 2. DO-INCOMPLETE-2-{timestamp} - **Status: request_approve**
- **Description**: Request Approve - menunggu approval
- **Sale Order**: SO-INCOMPLETE-2-{timestamp}
- **Product**: Test Product Incomplete 2
- **Quantity**: 6 units
- **Journal Entries**: 0 (belum ada)
- **Stock Reservation**: Tidak ada

### 3. DO-INCOMPLETE-3-{timestamp} - **Status: approved**
- **Description**: Approved - sudah diapprove tapi belum dikirim
- **Sale Order**: SO-INCOMPLETE-3-{timestamp}
- **Product**: Test Product Incomplete 1
- **Quantity**: 7 units
- **Journal Entries**: 0 (belum ada)
- **Stock Reservation**: ✅ Ada (7 units)

### 4. DO-INCOMPLETE-4-{timestamp} - **Status: sent**
- **Description**: Sent - sudah dikirim tapi belum diterima
- **Sale Order**: SO-INCOMPLETE-4-{timestamp}
- **Product**: Test Product Incomplete 2
- **Quantity**: 8 units
- **Journal Entries**: ✅ 2 entries
  - Debit: 155,320.56 | Credit: 0.00 (Cost of Goods Sold)
  - Debit: 0.00 | Credit: 155,320.56 (Inventory Reduction)
- **Stock Reservation**: ✅ Ada (8 units)

### 5. DO-INCOMPLETE-5-{timestamp} - **Status: supplier**
- **Description**: Supplier - dalam proses supplier
- **Sale Order**: SO-INCOMPLETE-5-{timestamp}
- **Product**: Test Product Incomplete 1
- **Quantity**: 9 units
- **Journal Entries**: 0 (belum ada)
- **Stock Reservation**: ✅ Ada (9 units)

### 6. DO-INCOMPLETE-6-{timestamp} - **Status: request_close**
- **Description**: Request Close - menunggu penutupan
- **Sale Order**: SO-INCOMPLETE-6-{timestamp}
- **Product**: Test Product Incomplete 2
- **Quantity**: 10 units
- **Journal Entries**: 0 (belum ada)
- **Stock Reservation**: Tidak ada

## Testing Scenarios

### 1. **Draft → Approved** (Membuat Stock Reservation)
```bash
# Ubah status DO-INCOMPLETE-1 dari draft ke approved
```

### 2. **Approved → Sent** (Menghapus Stock Reservation + Membuat Journal Entries)
```bash
# Ubah status DO-INCOMPLETE-3 dari approved ke sent
```

### 3. **Sent Status Quantity Change** (Update Journal Entries)
```bash
# Ubah quantity DO-INCOMPLETE-4 dari 8 menjadi 6
```

### 4. **Soft Delete Delivery Order** (Menghapus Journal Entries + Stock Reservations)
```bash
# Hapus salah satu delivery order
```

### 5. **Complete Delivery Order** (Update Sale Order Status)
```bash
# Ubah status salah satu DO ke completed
```

## Cara Menggunakan Data Test

### Melihat Semua Delivery Order Incomplete
```bash
php artisan tinker --execute="
\$dos = App\Models\DeliveryOrder::where('do_number', 'like', 'DO-INCOMPLETE-%')
    ->with(['salesOrders.customer', 'deliveryOrderItem.product'])
    ->get();
foreach(\$dos as \$do) {
    echo \$do->do_number . ' - ' . \$do->status . PHP_EOL;
}
"
```

### Mengubah Status Delivery Order
```bash
php artisan tinker --execute="
\$do = App\Models\DeliveryOrder::where('do_number', 'like', 'DO-INCOMPLETE-1-%')->first();
\$do->status = 'approved'; // atau status lain
\$do->save();
echo 'Status changed to: ' . \$do->status;
"
```

### Mengecek Journal Entries
```bash
php artisan tinker --execute="
\$do = App\Models\DeliveryOrder::where('do_number', 'like', 'DO-INCOMPLETE-4-%')->first();
\$entries = App\Models\JournalEntry::where('source_type', 'App\\\Models\\\DeliveryOrder')
    ->where('source_id', \$do->id)->get();
echo 'Journal entries: ' . \$entries->count();
"
```

## Cleanup

Untuk menghapus semua data test:
```bash
php artisan tinker --execute="
// Hapus delivery orders
App\Models\DeliveryOrder::where('do_number', 'like', 'DO-INCOMPLETE-%')->delete();
// Hapus sale orders
App\Models\SaleOrder::where('so_number', 'like', 'SO-INCOMPLETE-%')->delete();
// Hapus customer
App\Models\Customer::where('email', 'testincomplete@example.com')->delete();
// Hapus products
App\Models\Product::where('sku', 'like', 'TEST-INCOMPLETE-%')->delete();
// Hapus warehouse
App\Models\Warehouse::where('kode', 'TEST-INCOMPLETE')->delete();
"
```

## Status Flow Summary

| Status | Description | Stock Reservation | Journal Entries | Next Possible Status |
|--------|-------------|-------------------|-----------------|---------------------|
| draft | Belum diajukan | ❌ | ❌ | request_approve |
| request_approve | Menunggu approval | ❌ | ❌ | approved, rejected |
| approved | Sudah diapprove | ✅ | ❌ | sent, cancelled |
| sent | Sudah dikirim | ❌ | ✅ | received, completed |
| supplier | Proses supplier | ✅ | ❌ | completed |
| request_close | Menunggu penutupan | ❌ | ❌ | closed, completed |
| completed | Selesai | ❌ | ✅ | - |

**Total Data Created:** 6 Delivery Orders, 6 Sale Orders, 1 Customer, 2 Products, 1 Warehouse, 3 Stock Reservations, 2 Journal Entries 🎯