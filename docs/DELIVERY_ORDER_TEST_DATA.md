# Delivery Order Test Data Setup

Data delivery order test telah berhasil dibuat untuk testing cascade operations yang telah diimplementasikan.

## Data yang Dibuat

### Customer
- **Name**: Test Customer DO
- **Email**: testdo@example.com
- **Phone**: 081234567890

### Product
- **Name**: Test Product DO
- **SKU**: TEST-DO-{timestamp} (contoh: TEST-DO-1765439745)
- **Sell Price**: Rp 100,000

### Warehouse
- **Name**: Test Warehouse DO
- **Code**: TEST-DO

### Sale Order
- **Number**: SO-TEST-DO-001
- **Status**: confirmed
- **Customer**: Test Customer DO
- **Total Amount**: Rp 500,000

### Sale Order Item
- **Product**: Test Product DO
- **Quantity**: 5
- **Unit Price**: Rp 100,000

### Delivery Order
- **Number**: DO-TEST-001
- **Status**: draft (awal), sent (setelah testing)
- **Warehouse**: Test Warehouse DO

### Delivery Order Item
- **Quantity**: 5 (awal), 3 (setelah perubahan)
- **Product**: Test Product DO

### Stock Reservation
- **Quantity**: 5
- **Status**: active

## Cara Menjalankan Test Data

### 1. Jalankan Seeder
```bash
php artisan db:seed --class=DeliveryOrderTestSeeder
```

### 2. Verifikasi Data
```bash
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$do = App\Models\DeliveryOrder::where('do_number', 'DO-TEST-001')->first();
echo 'DO: ' . \$do->do_number . ' | Status: ' . \$do->status . PHP_EOL;
"
```

### 3. Test Cascade Operations

#### Ubah Status ke 'sent' (membuat journal entries)
```bash
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$do = App\Models\DeliveryOrder::where('do_number', 'DO-TEST-001')->first();
\$do->status = 'sent';
\$do->save();
echo 'Status changed to: ' . \$do->status . PHP_EOL;
"
```

#### Ubah Quantity (mengupdate journal entries)
```bash
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$doi = App\Models\DeliveryOrderItem::whereHas('deliveryOrder', function(\$q) {
    \$q->where('do_number', 'DO-TEST-001');
})->first();
\$doi->quantity = 3; // Ubah dari 5 menjadi 3
\$doi->save();
echo 'Quantity changed to: ' . \$doi->quantity . PHP_EOL;
"
```

#### Hapus Delivery Order (menghapus journal entries & stock reservations)
```bash
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
\$do = App\Models\DeliveryOrder::where('do_number', 'DO-TEST-001')->first();
\$do->delete(); // Soft delete
echo 'Delivery Order deleted' . PHP_EOL;
"
```

## Expected Results

### 1. Status 'sent' → Journal Entries Created
```
Journal entries created: 2
- 54342.95 | 0.00 | Goods Delivery - Cost of Goods Sold for DO-TEST-001
- 0.00 | 54342.95 | Goods Delivery - Inventory Reduction for DO-TEST-001
```

### 2. Quantity Change (5 → 3) → Journal Entries Updated
```
Journal entries after quantity change: 2
- 32605.77 | 0.00 | Goods Delivery - Cost of Goods Sold for DO-TEST-001
- 0.00 | 32605.77 | Goods Delivery - Inventory Reduction for DO-TEST-001
```

### 3. Delivery Order Deleted → All Related Data Cleaned
```
Journal entries after deletion: 0
Stock reservations after deletion: 0
```

## Testing Commands

### Jalankan Test Suite
```bash
php artisan test --filter=DeliveryOrderJournalIntegrationTest
```

### Manual Testing via Filament Admin
1. Login ke admin panel
2. Cari Delivery Order dengan nomor "DO-TEST-001"
3. Lakukan perubahan status/quantity secara manual
4. Verifikasi journal entries terupdate/delete

## Cleanup

Untuk menghapus data test:
```bash
php -r "
require 'vendor/autoload.php';
\$app = require 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Force delete if needed
\$do = App\Models\DeliveryOrder::withTrashed()->where('do_number', 'DO-TEST-001')->first();
if (\$do) \$do->forceDelete();
"
```