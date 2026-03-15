# DUTA TUNGGAL ERP — Quick Reference & Developer Guide
**Tanggal:** 14 Maret 2026  
**Versi Dokumen:** 1.0  

---

## 1. QUICK START UNTUK DEVELOPER BARU

### 1.1 Setup Local Environment

```bash
# Clone repository
git clone <repository-url>
cd Duta-Tunggal-ERP

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# Konfigurasi database di .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=duta_tunggal_local
DB_USERNAME=root
DB_PASSWORD=

# Jalankan migrasi + seed
php artisan migrate:fresh --seed

# Link storage
php artisan storage:link

# Build assets
npm run dev

# Jalankan server
php artisan serve
```

### 1.2 Login Default (setelah seed)

| Role | Email | Password |
|------|-------|----------|
| Superadmin | admin@dutatunggal.com | (sesuai seed) |
| Admin | (sesuai seed) | (sesuai seed) |

---

## 2. KONVENSI KODE

### 2.1 Naming Conventions

| Tipe | Convention | Contoh |
|------|------------|--------|
| Model | PascalCase | `SaleOrder`, `ChartOfAccount` |
| Controller | PascalCase + Controller | `InventoryCardController` |
| Service | PascalCase + Service | `InvoiceService` |
| Observer | PascalCase + Observer | `DeliveryOrderObserver` |
| Policy | PascalCase + Policy | `SaleOrderPolicy` |
| Filament Resource | PascalCase + Resource | `SaleOrderResource` |
| Migration | snake_case timestamp | `2026_03_14_create_*` |
| Table | snake_case plural | `sale_orders`, `delivery_orders` |
| COA ID field | `*_coa_id` | `ar_coa_id`, `inventory_coa_id` |
| Status field | `status` | string enum |
| Branch FK | `cabang_id` | standar seluruh sistem |
| User FK | `created_by`, `approved_by` | FK ke users |

### 2.2 Status String Standards

```php
// ✅ Benar — gunakan lowercase
$order->status = 'draft';
$order->status = 'approved';

// ❌ Salah — jangan mixed case (kecuali AR/AP yang masih legacy)
$order->status = 'Draft';  // JANGAN
```

---

## 3. PANDUAN MENAMBAH FITUR BARU

### 3.1 Checklist Fitur Baru

```
[ ] 1. Buat Migration
[ ] 2. Buat/Update Model dengan fillable, casts, relations
[ ] 3. Buat Service class di app/Services/
[ ] 4. Buat Observer jika ada side effects (app/Observers/)
[ ] 5. Daftarkan Observer di AppServiceProvider
[ ] 6. Buat Filament Resource (app/Filament/Resources/)
[ ] 7. Daftarkan Resource di AdminPanelProvider
[ ] 8. Buat Policy di app/Policies/
[ ] 9. Daftarkan Policy di AuthServiceProvider
[10. Buat Permission di PermissionSeeder (jika role-based)
[11. Tambahkan ke navigation group yang tepat
[12. Buat Feature Test
[13. Buat Unit Test untuk Service
[14. Update dokumentasi ini
```

### 3.2 Template Service Class

```php
<?php

namespace App\Services;

use App\Models\ModelName;
use App\Traits\JournalValidationTrait;
use Illuminate\Support\Facades\DB;

class NewFeatureService
{
    use JournalValidationTrait;

    /**
     * Process the main business operation.
     */
    public function processOperation(ModelName $model, array $data): ModelName
    {
        return DB::transaction(function () use ($model, $data) {
            // 1. Update model state
            $model->update([...]);
            
            // 2. Create related records
            // ...
            
            // 3. Post journal if necessary
            // ...
            
            return $model->fresh();
        });
    }
}
```

### 3.3 Template Observer

```php
<?php

namespace App\Observers;

use App\Models\ModelName;

class ModelNameObserver
{
    public function created(ModelName $model): void
    {
        // Side effects saat created
    }

    public function updated(ModelName $model): void
    {
        // Side effects saat updated
        if ($model->isDirty('status')) {
            $this->handleStatusChange($model);
        }
    }

    private function handleStatusChange(ModelName $model): void
    {
        match ($model->status) {
            'approved' => $this->onApproved($model),
            'completed' => $this->onCompleted($model),
            default => null,
        };
    }
}
```

---

## 4. DEBUGGING GUIDE

### 4.1 Tools Debug

| Tool | URL | Kegunaan |
|------|-----|---------|
| Lars Debugbar | Toolbar bawah | Query count, timeline, routes |
| `php artisan pail` | Terminal | Real-time log tailing |
| `storage/logs/laravel.log` | File | Log aplikasi |
| `storage/debugbar/` | Folder | Debugbar request history |

### 4.2 Common Issues & Solutions

#### Issue: "Balance sheet tidak balance"

**Kemungkinan Penyebab:**
1. Ada Journal Entry yang tidak balanced (debit ≠ kredit)
2. Opening balance salah
3. COA type (Asset/Liability) salah

**Debug:**
```sql
-- Cek jurnal yang tidak balanced
SELECT transaction_id, SUM(debit) - SUM(credit) as diff
FROM journal_entries
WHERE cabang_id = ?
GROUP BY transaction_id
HAVING ABS(diff) > 0.01;
```

---

#### Issue: "Stok negatif"

**Kemungkinan Penyebab:**
1. Race condition pada concurrent requests
2. Observer tidak terpicu (model::withoutObservers() digunakan di seeder)
3. Direct DB update yang bypass Observer

**Debug:**
```sql
-- Cek inventory stock negatif
SELECT p.name, w.name as warehouse, s.qty_available
FROM inventory_stocks s
JOIN products p ON s.product_id = p.id
JOIN warehouses w ON s.warehouse_id = w.id
WHERE s.qty_available < 0;
```

---

#### Issue: "DO tidak muncul setelah SO approve"

**Kemungkinan Penyebab:**
1. `WarehouseConfirmation` tidak dibuat (observer tidak terpicu)
2. WC dibuat tapi tidak auto-confirmed (stok kurang → status `request`)
3. Race condition antara WC creation dan DO creation

**Debug:**
```php
// Cek WarehouseConfirmation untuk SO
$so = SaleOrder::find($id);
$wc = WarehouseConfirmation::where('sale_order_id', $id)->first();
dump($wc?->status, $wc?->items->count());
dump($so->deliveryOrders->count());
```

---

#### Issue: "N+1 Query di list view"

**Debug dengan Debugbar:**
```php
// Di AppServiceProvider, tambahkan temporary
DB::listen(function ($query) {
    \Log::info($query->sql, $query->bindings);
});
```

**Fix:**
```php
// Di Resource::getEloquentQuery()
return parent::getEloquentQuery()
    ->with(['relationA', 'relationB.subRelation']);
```

---

### 4.3 Artisan Commands Berguna

```bash
# Clear all caches
php artisan optimize:clear

# Re-cache Filament
php artisan filament:optimize

# Reset database (HATI-HATI!)
php artisan migrate:fresh --seed

# Tinker untuk debug
php artisan tinker

# Check routes
php artisan route:list --path=admin/sale

# Check queue status
php artisan queue:work --once

# Run specific test
php artisan test tests/Feature/CustomerReturnFeatureTest.php -v
```

---

## 5. JOURNAL ENTRY MAPPING

### 5.1 Sales Invoice Journal

```
Debit  : Piutang Dagang (AR COA)      ← ar_coa_id per product or invoice
Credit : Pendapatan Penjualan          ← sales_coa_id per product
Credit : PPN Keluaran                 ← ppn_keluaran_coa_id (if applicable)
```

### 5.2 Customer Receipt Journal

```
Debit  : Kas / Bank                   ← cash_bank_coa_id
Credit : Piutang Dagang (AR)          ← ar_coa_id
```

### 5.3 Purchase Invoice Journal

```
Debit  : Persediaan / Asset / Biaya   ← inventory_coa_id / asset_coa_id
Debit  : PPN Masukan                  ← ppn_masukan_coa_id (if applicable)
Credit : Hutang Dagang (AP)            ← ap_coa_id
```

### 5.4 Vendor Payment Journal

```
Debit  : Hutang Dagang (AP)            ← ap_coa_id
Credit : Kas / Bank                   ← cash_bank_coa_id
```

### 5.5 Material Issue Journal (Manufacturing)

```
Debit  : Work In Progress (WIP)       ← work_in_progress_coa_id (from BOM)
Credit : Persediaan Bahan Baku        ← inventory_coa_id per product
```

### 5.6 Production Finished Goods Journal

```
Debit  : Persediaan Barang Jadi       ← finished_goods_coa_id (from BOM)
Credit : Work In Progress (WIP)       ← work_in_progress_coa_id
```

### 5.7 Delivery Order COGS Journal

```
Debit  : Harga Pokok Penjualan (COGS) ← cogs_coa_id per product
Credit : Persediaan Barang Jadi       ← inventory_coa_id per product
```

### 5.8 Deposit Journal

```
Debit  : Kas / Bank                   ← payment_coa_id
Credit : Uang Muka Customer           ← deposit coa_id
```

---

## 6. TESTING CHEATSHEET

### 6.1 Common Test Patterns

```php
// Setup user dengan cabang
$cabang = Cabang::factory()->create();
$warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
$user = User::factory()->create(['cabang_id' => $cabang->id]);

// Acting as user
actingAs($user);

// Membuat data test dengan factory
$customer = Customer::factory()->create(['cabang_id' => $cabang->id]);

// Mengetes Filament action
Livewire::actingAs($user)
    ->test(EditSaleOrder::class, ['record' => $so->id])
    ->callAction('request_approve')
    ->assertHasNoErrors();

// Mengetes database state
expect(SaleOrder::find($soId)->status)->toBe('request_approve');
$this->assertDatabaseHas('sale_orders', ['id' => $soId, 'status' => 'request_approve']);

// Mengetes journal entry
$this->assertDatabaseHas('journal_entries', [
    'source_type' => Invoice::class,
    'source_id' => $invoiceId,
    'debit' => 1_100_000,
]);
```

---

### 6.2 Skipping Observers di Test Setup

```php
// Untuk seeder/setup test tanpa side effects
SaleOrder::withoutObservers(function () {
    SaleOrder::factory()->create(['status' => 'approved']);
});
```

---

### 6.3 Testing dengan Roles & Permissions

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Assign role ke user
$role = Role::firstOrCreate(['name' => 'Sales']);
$user->assignRole($role);

// Assign permission langsung
$user->givePermissionTo('create sale order');

// Test akses
$this->actingAs($user)
    ->get('/admin/sale-orders/create')
    ->assertStatus(200);
```

---

## 7. DEPLOYMENT CHECKLIST

### 7.1 Pre-Deployment

```bash
[ ] git pull latest changes
[ ] composer install --no-dev --optimize-autoloader
[ ] npm run build
[ ] php artisan migrate --force
[ ] php artisan filament:optimize
[ ] php artisan optimize
[ ] php artisan storage:link
```

### 7.2 Post-Deployment Verification

```bash
[ ] Cek /admin/login dapat diakses
[ ] Login sebagai admin, cek dashboard muncul
[ ] Cek route:list tidak ada _dusk/* routes (KRITIS!)
[ ] Buat 1 test sale order end-to-end
[ ] Cek balance sheet terbuka tanpa error
[ ] Cek storage/logs/laravel.log untuk errors baru
```

---

## 8. VERSION HISTORY

| Versi | Tanggal | Perubahan |
|-------|---------|---------|
| 1.0 | 14 Mar 2026 | Initial comprehensive documentation |

---

*Quick Reference ini merupakan panduan cepat untuk developer Duta Tunggal ERP per 14 Maret 2026.*
