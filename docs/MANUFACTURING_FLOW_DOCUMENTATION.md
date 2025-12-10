# DOKUMENTASI LENGKAP: MANUFACTURING FLOW

**Sistem ERP Duta Tunggal**  
**Tanggal Pembuatan**: 9 Desember 2025  
**Versi**: 1.0

---

## DAFTAR ISI

1. [Overview Manufacturing Flow](#1-overview-manufacturing-flow)
2. [Struktur Database](#2-struktur-database)
3. [Model Relationships](#3-model-relationships)
4. [Flow Detail Manufacturing](#4-flow-detail-manufacturing)
5. [Material Issue & Stock Management](#5-material-issue--stock-management)
6. [Production Process](#6-production-process)
7. [Quality Control Manufacturing](#7-quality-control-manufacturing)
8. [Journal Entries Creation](#8-journal-entries-creation)
9. [Status Lifecycle](#9-status-lifecycle)
10. [Observers & Business Logic](#10-observers--business-logic)
11. [Impact Analysis](#11-impact-analysis)
12. [Costing & BOM Calculation](#12-costing--bom-calculation)

---

## 1. OVERVIEW MANUFACTURING FLOW

### 1.1 Alur Umum
```
Production Plan (Rencana Produksi)
    ↓
Bill of Material (BOM) Selection
    ↓
Manufacturing Order (MO) Creation
    ↓
Material Issue (Pengeluaran Bahan Baku)
    ↓
Stock Reservation & Deduction
    ↓
Manufacturing Order Start (In Progress)
    ↓
Production Process
    ↓
Quality Control (QC)
    ↓
Finished Goods Completion
    ↓
Stock Update (Produk Jadi)
    ↓
Journal Entries (WIP, FG, COGS)
```

### 1.2 Actors/Peran
- **Production Planner**: Membuat production plan
- **Manufacturing Manager**: Membuat & approve manufacturing order
- **Warehouse**: Mengeluarkan material (material issue)
- **Production Operator**: Melakukan produksi
- **QC Inspector**: Quality control produk jadi
- **System**: Otomatis membuat journal entries & update stock

### 1.3 Key Concepts

#### Bill of Material (BOM)
- Daftar material/bahan baku yang dibutuhkan untuk membuat 1 unit produk jadi
- Termasuk labor cost & overhead cost
- Bisa ada multiple BOM untuk 1 produk (pilih yang active)

#### Work in Progress (WIP)
- Produk yang sedang dalam proses produksi
- Dicatat sebagai asset di Balance Sheet (akun 1140.02)

#### Finished Goods (FG)
- Produk jadi hasil produksi
- Masuk ke inventory dengan cost dari BOM

---

## 2. STRUKTUR DATABASE

### 2.1 Table: `production_plans`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `plan_number` | varchar(255) | NO | - | Nomor rencana produksi |
| `name` | varchar(255) | NO | - | Nama production plan |
| `source_type` | varchar(255) | YES | NULL | sale_order / manual |
| `sale_order_id` | bigint unsigned | YES | NULL | FK ke sale_orders |
| `bill_of_material_id` | bigint unsigned | YES | NULL | FK ke bill_of_materials |
| `product_id` | int | NO | - | FK ke products (produk jadi) |
| `quantity` | decimal(18,2) | NO | - | Jumlah yang akan diproduksi |
| `uom_id` | int | NO | - | FK ke unit_of_measures |
| `warehouse_id` | int | NO | - | FK ke warehouses |
| `start_date` | datetime | YES | NULL | Tanggal mulai produksi |
| `end_date` | datetime | YES | NULL | Tanggal target selesai |
| `status` | enum | NO | 'draft' | draft/scheduled/in_progress/completed/cancelled |
| `notes` | text | YES | NULL | Catatan |
| `created_by` | int | YES | NULL | FK ke users |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

---

### 2.2 Table: `bill_of_materials`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `cabang_id` | bigint unsigned | YES | NULL | FK ke cabangs |
| `product_id` | int | NO | - | FK ke products (produk jadi) |
| `quantity` | decimal(18,2) | NO | 1.00 | Jumlah output (biasanya 1) |
| `code` | varchar(255) | NO | - | Kode BOM (unique) |
| `nama_bom` | varchar(255) | NO | - | Nama BOM |
| `note` | text | YES | NULL | Catatan |
| `is_active` | tinyint(1) | NO | 1 | BOM aktif atau tidak |
| `uom_id` | int | YES | NULL | FK ke unit_of_measures |
| `labor_cost` | decimal(18,2) | YES | 0.00 | Biaya tenaga kerja |
| `overhead_cost` | decimal(18,2) | YES | 0.00 | Biaya overhead |
| `total_cost` | decimal(18,2) | YES | 0.00 | Total biaya BOM |
| `finished_goods_coa_id` | bigint unsigned | YES | NULL | COA untuk finished goods |
| `work_in_progress_coa_id` | bigint unsigned | YES | NULL | COA untuk WIP |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

**Calculated Fields**:
- `total_cost` = sum(material_cost) + labor_cost + overhead_cost
- `material_cost` = sum(items.quantity × items.product.cost_price)

---

### 2.3 Table: `bill_of_material_items`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `bill_of_material_id` | bigint unsigned | NO | FK ke bill_of_materials |
| `product_id` | int | NO | FK ke products (raw material) |
| `quantity` | decimal(18,2) | NO | Qty bahan baku per unit output |
| `uom_id` | int | NO | FK ke unit_of_measures |
| `notes` | text | YES | Catatan item |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.4 Table: `manufacturing_orders`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `production_plan_id` | bigint unsigned | YES | NULL | FK ke production_plans |
| `mo_number` | varchar(255) | NO | - | Nomor MO (unique) |
| `status` | enum | NO | 'draft' | draft/in_progress/completed |
| `start_date` | datetime | YES | NULL | Tanggal mulai produksi |
| `end_date` | datetime | YES | NULL | Tanggal selesai produksi |
| `items` | json | YES | NULL | Material items (dari BOM) |
| `cabang_id` | bigint unsigned | YES | NULL | FK ke cabangs |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

**Status Values**:
- `draft` - MO baru dibuat, belum mulai
- `in_progress` - Produksi sedang berjalan
- `completed` - Produksi selesai, produk jadi masuk stock

---

### 2.5 Table: `material_issues`

| Field | Type | Nullable | Default | Keterangan |
|-------|------|----------|---------|------------|
| `id` | bigint unsigned | NO | AUTO_INCREMENT | Primary Key |
| `issue_number` | varchar(255) | NO | - | Nomor pengeluaran material |
| `production_plan_id` | bigint unsigned | YES | NULL | FK ke production_plans |
| `manufacturing_order_id` | bigint unsigned | YES | NULL | FK ke manufacturing_orders |
| `warehouse_id` | bigint unsigned | YES | NULL | FK ke warehouses |
| `issue_date` | date | NO | - | Tanggal pengeluaran |
| `type` | varchar(255) | NO | - | issue / return |
| `status` | enum | NO | 'draft' | draft/pending_approval/approved/completed |
| `total_cost` | decimal(18,2) | YES | 0.00 | Total cost material |
| `notes` | text | YES | NULL | Catatan |
| `created_by` | int | YES | NULL | FK ke users |
| `approved_by` | int | YES | NULL | FK ke users |
| `approved_at` | datetime | YES | NULL | Tanggal approval |
| `created_at` | timestamp | YES | NULL | - |
| `updated_at` | timestamp | YES | NULL | - |
| `deleted_at` | timestamp | YES | NULL | Soft delete |

**Status Workflow**:
- `draft` → `pending_approval` → `approved` → `completed`

**Type Values**:
- `issue` - Pengeluaran material ke produksi
- `return` - Pengembalian material dari produksi

---

### 2.6 Table: `material_issue_items`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `material_issue_id` | bigint unsigned | NO | FK ke material_issues |
| `product_id` | int | NO | FK ke products (raw material) |
| `quantity` | decimal(18,2) | NO | Qty yang dikeluarkan |
| `uom_id` | int | NO | FK ke unit_of_measures |
| `unit_cost` | decimal(18,2) | YES | Cost per unit |
| `total_cost` | decimal(18,2) | YES | Total cost (qty × unit_cost) |
| `notes` | text | YES | Catatan |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.7 Table: `productions`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `production_number` | varchar(255) | NO | Nomor produksi (unique) |
| `manufacturing_order_id` | bigint unsigned | NO | FK ke manufacturing_orders |
| `production_date` | date | NO | Tanggal produksi |
| `status` | enum | NO | draft / finished |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

**Status Values**:
- `draft` - Production baru dibuat, belum selesai
- `finished` - Production selesai, menunggu QC

---

### 2.8 Table: `quality_controls` (Manufacturing)

Quality Control untuk manufacturing menggunakan table yang sama dengan purchase, tapi dengan `from_model_type = 'App\Models\Production'`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `from_model_type` | varchar(255) | NO | 'App\Models\Production' |
| `from_model_id` | bigint unsigned | NO | FK ke productions |
| `qc_date` | date | NO | Tanggal QC |
| `inspector_id` | int | YES | FK ke users |
| `qty_inspected` | int | NO | Qty yang di-QC |
| `qty_accepted` | int | NO | Qty diterima |
| `qty_rejected` | int | YES | Qty reject |
| `status` | enum | NO | pending/pass/fail |
| `notes` | text | YES | Catatan QC |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.9 Table: `stock_reservations`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `product_id` | int | NO | FK ke products |
| `warehouse_id` | int | NO | FK ke warehouses |
| `quantity` | decimal(18,2) | NO | Qty reserved |
| `material_issue_id` | bigint unsigned | YES | FK ke material_issues |
| `sale_order_item_id` | bigint unsigned | YES | FK ke sale_order_items |
| `reservation_type` | varchar(255) | NO | production / sales |
| `status` | enum | NO | reserved/released/fulfilled |
| `notes` | text | YES | Catatan |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

---

### 2.10 Table: `inventory_stocks`

| Field | Type | Nullable | Keterangan |
|-------|------|----------|------------|
| `id` | bigint unsigned | NO | Primary Key |
| `product_id` | int | NO | FK ke products |
| `warehouse_id` | int | NO | FK ke warehouses |
| `rak_id` | bigint unsigned | YES | FK ke raks |
| `qty_available` | decimal(18,2) | NO | Qty tersedia |
| `qty_reserved` | decimal(18,2) | NO | Qty direserve |
| `qty_min` | decimal(18,2) | YES | Minimum stock |
| `created_at` | timestamp | YES | - |
| `updated_at` | timestamp | YES | - |
| `deleted_at` | timestamp | YES | Soft delete |

**Calculated**:
- `qty_available` = total stock - qty_reserved
- Saat material issue: qty_reserved += quantity
- Saat material issue completed: qty_available -= quantity, qty_reserved -= quantity

---

## 3. MODEL RELATIONSHIPS

### 3.1 ProductionPlan Model

```php
// Located: app/Models/ProductionPlan.php

// Relationships
- saleOrder() : belongsTo(SaleOrder)
- billOfMaterial() : belongsTo(BillOfMaterial)
- product() : belongsTo(Product) // produk jadi
- uom() : belongsTo(UnitOfMeasure)
- warehouse() : belongsTo(Warehouse)
- creator() : belongsTo(User, 'created_by')
- manufacturingOrders() : hasMany(ManufacturingOrder)
- materialIssues() : hasMany(MaterialIssue)
- journalEntries() : morphMany(JournalEntry, 'source')

// Methods
- getMaterialRequirements() // hitung kebutuhan material dari BOM
```

### 3.2 BillOfMaterial Model

```php
// Located: app/Models/BillOfMaterial.php

// Relationships
- cabang() : belongsTo(Cabang)
- product() : belongsTo(Product) // produk jadi
- uom() : belongsTo(UnitOfMeasure)
- items() : hasMany(BillOfMaterialItem)
- productionPlans() : hasMany(ProductionPlan)

// Methods
- getActiveProductionPlans() // production plan yang aktif
- getTotalPlannedQuantity() // total qty yang direncanakan
- isInUse() // apakah BOM sedang digunakan
```

### 3.3 ManufacturingOrder Model

```php
// Located: app/Models/ManufacturingOrder.php

// Relationships
- production() : hasOne(Production)
- productions() : hasMany(Production)
- productionPlan() : belongsTo(ProductionPlan)
- journalEntries() : morphMany(JournalEntry, 'source')
- materialIssues() : hasManyThrough(MaterialIssue, ProductionPlan)
- completedMaterialIssues() : materialIssues dengan status completed
- cabang() : belongsTo(Cabang)

// Methods
- areAllMaterialsIssued() : bool // cek apakah semua material sudah issued
```

### 3.4 MaterialIssue Model

```php
// Located: app/Models/MaterialIssue.php

// Relationships
- manufacturingOrder() : belongsTo(ManufacturingOrder)
- productionPlan() : belongsTo(ProductionPlan)
- warehouse() : belongsTo(Warehouse)
- items() : hasMany(MaterialIssueItem)
- createdBy() : belongsTo(User)
- approvedBy() : belongsTo(User)
- journalEntry() : morphOne(JournalEntry, 'source')

// Methods
- calculateTotalCost() : float
- updateTotalCost() : void
```

### 3.5 Production Model

```php
// Located: app/Models/Production.php

// Relationships
- manufacturingOrder() : belongsTo(ManufacturingOrder)
- qualityControl() : morphOne(QualityControl, 'from_model')
- journalEntries() : morphMany(JournalEntry, 'source')
```

---

## 4. FLOW DETAIL MANUFACTURING

### 4.1 Step 1: Membuat Production Plan

**Trigger**: User membuat production plan dari Filament Resource

**Action**:
1. User mengisi form production plan:
   - Product (produk jadi yang akan diproduksi)
   - Quantity
   - Warehouse tujuan
   - BOM selection (pilih BOM yang akan digunakan)
   - Start date & End date
   - Source (manual atau dari Sale Order)

2. System generates plan number
   - Format: `PLAN-{YYYYMMDD}-{sequence}`
   - Example: `PLAN-20251209-0001`

3. Production plan disimpan dengan status `draft` atau `scheduled`

**Database Changes**:
```sql
INSERT INTO production_plans (
    plan_number, name, product_id, quantity,
    bill_of_material_id, warehouse_id,
    start_date, end_date, status, created_by
) VALUES (...);
```

**Impact**:
- ✅ Production plan record dibuat
- ✅ Material requirements calculated dari BOM
- ❌ Stock BELUM direserve
- ❌ Manufacturing Order BELUM dibuat

**Status**: `draft` atau `scheduled`

---

### 4.2 Step 2: Membuat Manufacturing Order (MO)

**Trigger**: User membuat MO dari production plan

**Action**:
1. User pilih production plan (status: in_progress)
2. System auto-load:
   - Product dari production plan
   - Quantity dari production plan
   - BOM items (material requirements)
   - Start date & End date

3. System generates MO number
   - Format: `MO-{YYYYMMDD}-{sequence}`
   - Example: `MO-20251209-0001`

4. MO disimpan dengan status `draft`

**Database Changes**:
```sql
INSERT INTO manufacturing_orders (
    mo_number, production_plan_id, status,
    start_date, end_date, items, cabang_id
) VALUES (...);
```

**Impact**:
- ✅ Manufacturing Order record dibuat
- ✅ Items (material requirements) disimpan di JSON field
- ❌ Stock BELUM direserve
- ❌ Material BELUM dikeluarkan

**Status MO**: `draft`

---

### 4.3 Step 3: Material Issue (Pengeluaran Bahan Baku)

**Trigger**: Warehouse mengeluarkan material untuk produksi

**Action**:
1. User membuat Material Issue dari production plan
2. Mengisi:
   - Warehouse asal
   - Issue date
   - Type: `issue` (pengeluaran)
   - Items: produk & quantity dari BOM

3. Material issue disimpan dengan status `draft`

4. **Approval workflow**:
   - User submit → status: `pending_approval`
   - Approver approve → status: `approved`
   - System complete → status: `completed`

**Database Changes**:
```sql
INSERT INTO material_issues (
    issue_number, production_plan_id,
    warehouse_id, issue_date, type, status
) VALUES (...);

INSERT INTO material_issue_items (
    material_issue_id, product_id, quantity,
    unit_cost, total_cost
) VALUES (...);
```

**Observer Trigger**: `MaterialIssueObserver@updated()`

**Saat status = `approved`**:
```php
// 1. Create stock reservations
StockReservation::create([
    'product_id' => $item->product_id,
    'warehouse_id' => $materialIssue->warehouse_id,
    'quantity' => $item->quantity,
    'material_issue_id' => $materialIssue->id,
    'reservation_type' => 'production',
    'status' => 'reserved'
]);

// 2. Update inventory_stocks.qty_reserved
InventoryStock::where('product_id', $item->product_id)
    ->where('warehouse_id', $warehouse_id)
    ->increment('qty_reserved', $item->quantity);
```

**Saat status = `completed`**:
```php
// 1. Deduct actual stock
InventoryStock::where('product_id', $item->product_id)
    ->where('warehouse_id', $warehouse_id)
    ->decrement('qty_available', $item->quantity);

// 2. Release reservation
InventoryStock::where('product_id', $item->product_id)
    ->where('warehouse_id', $warehouse_id)
    ->decrement('qty_reserved', $item->quantity);

// 3. Update stock reservation status
StockReservation::where('material_issue_id', $materialIssue->id)
    ->update(['status' => 'fulfilled']);

// 4. Create WIP journal entry
JournalEntry::create([
    'coa_id' => wipCoa, // 1140.02
    'debit' => total_cost,
    'credit' => 0,
    'description' => 'WIP - Material Issue for MO',
    'source_type' => 'MaterialIssue',
    'source_id' => $materialIssue->id
]);

JournalEntry::create([
    'coa_id' => inventoryCoa, // 1101.01
    'debit' => 0,
    'credit' => total_cost,
    'description' => 'Inventory reduction for production',
    ...
]);
```

**Impact**:
- ✅ Material issue record dibuat
- ✅ (Approved) Stock reserved (qty_reserved bertambah)
- ✅ (Completed) Stock berkurang (qty_available berkurang)
- ✅ (Completed) **Journal Entry dibuat** (Dr. WIP, Cr. Inventory)
- ✅ Material siap untuk produksi

**Status Material Issue**: `draft` → `pending_approval` → `approved` → `completed`

---

### 4.4 Step 4: Start Manufacturing Order

**Trigger**: User start produksi (button "Produksi")

**Action**:
1. System check stock material:
   - Apakah semua material sudah di-issue?
   - Apakah qty tersedia cukup?

2. Jika OK, update MO status ke `in_progress`

3. **Auto-create Production record**:
   ```php
   Production::create([
       'production_number' => generateProductionNumber(),
       'manufacturing_order_id' => $mo->id,
       'production_date' => now(),
       'status' => 'draft'
   ]);
   ```

**Database Changes**:
```sql
UPDATE manufacturing_orders SET
    status = 'in_progress',
    start_date = NOW()
WHERE id = {mo_id};

INSERT INTO productions (
    production_number, manufacturing_order_id,
    production_date, status
) VALUES (...);
```

**Observer Trigger**: `ManufacturingOrderObserver@updated()`

```php
// Update ProductionPlan status
if ($mo->status === 'in_progress') {
    $productionPlan->update(['status' => 'in_progress']);
}
```

**Impact**:
- ✅ MO status = `in_progress`
- ✅ Production record auto-created
- ✅ ProductionPlan status = `in_progress`
- ❌ Finished goods BELUM masuk stock
- ❌ Journal entry untuk FG BELUM dibuat

**Status MO**: `in_progress`

---

### 4.5 Step 5: Finish Production

**Trigger**: Production operator selesai produksi

**Action**:
1. User update production status ke `finished`
2. System mencatat:
   - Quantity produced (dari production plan)
   - Production completion time

**Database Changes**:
```sql
UPDATE productions SET
    status = 'finished'
WHERE id = {production_id};
```

**Impact**:
- ✅ Production status = `finished`
- ✅ Siap untuk QC
- ❌ Stock finished goods BELUM masuk
- ❌ Journal entry BELUM dibuat

**Status Production**: `finished`

---

### 4.6 Step 6: Quality Control (QC)

**Trigger**: QC inspector melakukan inspeksi

**Action**:
1. User membuat QC record untuk production
2. Mengisi:
   - QC date
   - Inspector
   - Qty inspected
   - Qty accepted
   - Qty rejected
   - Status: pending/pass/fail
   - Notes

**Database Changes**:
```sql
INSERT INTO quality_controls (
    from_model_type, from_model_id,
    qc_date, inspector_id,
    qty_inspected, qty_accepted, qty_rejected,
    status, notes
) VALUES ('App\Models\Production', {production_id}, ...);
```

**Observer Trigger**: `QualityControlObserver@updated()`

**Saat status = `pass`**:
```php
// 1. Add finished goods to inventory
$mo = $production->manufacturingOrder;
$plan = $mo->productionPlan;
$product = $plan->product; // finished goods
$qty = $qc->qty_accepted;

InventoryStock::updateOrCreate(
    [
        'product_id' => $product->id,
        'warehouse_id' => $plan->warehouse_id
    ],
    [
        'qty_available' => DB::raw('qty_available + ' . $qty)
    ]
);

// 2. Complete Manufacturing Order
$mo->update([
    'status' => 'completed',
    'end_date' => now()
]);

// 3. Complete Production Plan
$plan->update(['status' => 'completed']);

// 4. Create Finished Goods journal entries
$bom = $plan->billOfMaterial;
$materialCost = sum(bom_items.quantity × cost_price);
$laborCost = $bom->labor_cost ?? 0;
$overheadCost = $bom->overhead_cost ?? 0;
$totalCost = ($materialCost + $laborCost + $overheadCost) × $qty;

// Transfer from WIP to Finished Goods
JournalEntry::create([
    'coa_id' => finishedGoodsCoa, // 1140.01
    'debit' => $totalCost,
    'credit' => 0,
    'description' => 'Finished Goods - Production completed',
    'source_type' => 'Production',
    'source_id' => $production->id
]);

JournalEntry::create([
    'coa_id' => wipCoa, // 1140.02
    'debit' => 0,
    'credit' => $totalCost,
    'description' => 'WIP reduction - Production completed',
    ...
]);

// 5. Update product cost_price (weighted average)
$product->updateCostPrice($totalCost, $qty);
```

**Impact**:
- ✅ QC record dibuat
- ✅ (Pass) Finished goods masuk stock
- ✅ (Pass) **Journal Entry dibuat** (Dr. FG, Cr. WIP)
- ✅ (Pass) MO status = `completed`
- ✅ (Pass) ProductionPlan status = `completed`
- ✅ (Pass) Product cost price updated

**Status QC**: `pass` / `fail`

---

## 5. MATERIAL ISSUE & STOCK MANAGEMENT

### 5.1 Material Issue Status Workflow

```
draft
  ↓ (User submit)
pending_approval
  ↓ (Approver approve)
approved (Stock reserved)
  ↓ (System complete)
completed (Stock deducted)
```

### 5.2 Stock Reservation Mechanism

**Purpose**: Memastikan material tersedia untuk produksi

**Flow**:
1. Material Issue `approved` → Create stock reservation
2. Inventory stock `qty_reserved` bertambah
3. Inventory stock `qty_available` berkurang (karena = total - reserved)
4. Material Issue `completed` → Actual stock deduction
5. `qty_available` berkurang lagi, `qty_reserved` berkurang

**Example**:
```
Initial state:
- Total stock: 100
- qty_reserved: 0
- qty_available: 100

After Material Issue approved (reserve 20):
- Total stock: 100
- qty_reserved: 20
- qty_available: 80

After Material Issue completed (deduct 20):
- Total stock: 80
- qty_reserved: 0
- qty_available: 80
```

### 5.3 Material Return

Jika ada material yang tidak terpakai, bisa di-return:

**Action**:
1. Create Material Issue dengan type: `return`
2. Status workflow sama: draft → pending_approval → approved → completed
3. Saat completed:
   - Stock bertambah (inventory_stocks.qty_available += quantity)
   - Journal entry: Dr. Inventory, Cr. WIP

---

## 6. PRODUCTION PROCESS

### 6.1 Production Record

**Purpose**: Track actual production activity

**Lifecycle**:
```
Auto-created saat MO start (status: draft)
  ↓
Production process (manual work)
  ↓
User finish production (status: finished)
  ↓
QC inspection
  ↓
If pass: Finished goods masuk stock, MO completed
```

### 6.2 Multiple Productions per MO

System mendukung multiple production records per MO:
- 1 MO bisa punya beberapa production batch
- Useful untuk produksi bertahap
- Total qty produced = sum(all productions.qty_accepted in QC)

---

## 7. QUALITY CONTROL MANUFACTURING

### 7.1 QC Criteria

QC untuk manufacturing bisa punya multiple criteria:
- Visual inspection
- Functional testing
- Performance testing
- Packaging quality
- Documentation completeness

### 7.2 QC Pass vs Fail

**If Pass**:
- Finished goods masuk stock
- MO completed
- Journal entries dibuat
- Cost calculated dan masuk FG

**If Fail**:
- Production bisa di-rework
- Material bisa di-return
- MO tetap `in_progress`
- No journal entry

### 7.3 Partial Acceptance

QC bisa accept sebagian:
- `qty_inspected` = total yang di-QC
- `qty_accepted` = yang pass
- `qty_rejected` = yang fail
- Stock yang masuk = `qty_accepted`

---

## 8. JOURNAL ENTRIES CREATION

### 8.1 Timing - Kapan Journal Entry Dibuat

| Event | Journal Entry Created | Status |
|-------|----------------------|--------|
| Production Plan Created | ❌ TIDAK | scheduled |
| MO Created | ❌ TIDAK | draft |
| Material Issue Approved | ❌ TIDAK (hanya reserve) | approved |
| Material Issue Completed | ✅ YA - WIP | completed |
| MO Start | ❌ TIDAK | in_progress |
| Production Finished | ❌ TIDAK | finished |
| QC Pass | ✅ YA - Finished Goods | pass |

---

### 8.2 Journal Entry Details

#### 8.2.1 Material Issue Completed - WIP Entry

**Trigger**: `MaterialIssueObserver@updated()` (status = completed)  
**Method**: `ManufacturingJournalService::postMaterialIssue()`

**When**: Saat material dikeluarkan ke produksi

```
Dr. Work in Progress (1140.02)         xxx
Cr. Inventory - Raw Material (1101.01)    xxx

Keterangan: Material issued for production - {issue_number}
Source: MaterialIssue
```

**COA Used**:
- WIP: `1140.02` - Work in Progress
- Inventory: `1101.01` - Inventory atau product-specific inventory COA

**Calculation**:
```php
$totalCost = sum(
    material_issue_items.quantity × material_issue_items.unit_cost
);
```

---

#### 8.2.2 QC Pass - Finished Goods Entry

**Trigger**: `QualityControlObserver@updated()` (status = pass)  
**Method**: `ManufacturingJournalService::postFinishedGoods()`

**When**: Saat QC pass, produk jadi masuk stock

**Step 1: Transfer WIP to Finished Goods**
```
Dr. Finished Goods (1140.01)            xxx
Cr. Work in Progress (1140.02)             xxx

Keterangan: Finished goods from production - {production_number}
Source: Production
```

**Step 2: (Optional) Labor & Overhead**
Jika ada labor_cost & overhead_cost di BOM:
```
Dr. Work in Progress (1140.02)          xxx (labor + overhead)
Cr. Labor Cost (5xxx)                      xxx (labor)
Cr. Manufacturing Overhead (5xxx)          xxx (overhead)

Keterangan: Labor & overhead for production
```

**COA Used**:
- Finished Goods: `1140.01` atau dari BOM.finished_goods_coa_id
- WIP: `1140.02` atau dari BOM.work_in_progress_coa_id
- Labor Cost: `5xxx` (expense account)
- Manufacturing Overhead: `5xxx` (expense account)

**Calculation**:
```php
$bom = $productionPlan->billOfMaterial;

// Material cost
$materialCost = sum(
    bom_items.quantity × product.cost_price
);

// Labor cost
$laborCost = $bom->labor_cost ?? 0;

// Overhead cost
$overheadCost = $bom->overhead_cost ?? 0;

// Total cost per unit
$unitCost = $materialCost + $laborCost + $overheadCost;

// Total cost for production
$totalCost = $unitCost × $qtyAccepted;
```

---

#### 8.2.3 Material Return

**Trigger**: Material Issue dengan type='return' completed

**When**: Material dikembalikan dari produksi

```
Dr. Inventory - Raw Material (1101.01)  xxx
Cr. Work in Progress (1140.02)             xxx

Keterangan: Material returned from production - {issue_number}
Source: MaterialIssue
```

---

### 8.3 Cost Flow Summary

```
1. Material Issue Completed:
   Dr. WIP (1140.02)
   Cr. Inventory (1101.01)
   
   → Material cost masuk WIP

2. (Optional) Labor & Overhead:
   Dr. WIP (1140.02)
   Cr. Labor Cost (5xxx)
   Cr. Manufacturing Overhead (5xxx)
   
   → Labor & overhead masuk WIP

3. QC Pass:
   Dr. Finished Goods (1140.01)
   Cr. WIP (1140.02)
   
   → WIP transfer ke Finished Goods

4. Saat dijual (Sales):
   Dr. COGS (6101)
   Cr. Finished Goods (1140.01)
   
   → Finished Goods jadi COGS
```

---

## 9. STATUS LIFECYCLE

### 9.1 Production Plan Status

| Status | Description | Next Status |
|--------|-------------|-------------|
| `draft` | Rencana baru dibuat | scheduled |
| `scheduled` | Dijadwalkan untuk produksi | in_progress |
| `in_progress` | Sedang produksi | completed |
| `completed` | Produksi selesai | - |
| `cancelled` | Dibatalkan | - |

---

### 9.2 Manufacturing Order Status

| Status | Description | Trigger | Impact |
|--------|-------------|---------|--------|
| `draft` | MO baru dibuat | Creation | Bisa diedit |
| `in_progress` | Produksi berjalan | User start | Production created, material issued |
| `completed` | Produksi selesai | QC pass | FG masuk stock, journal posted |

**Transition Rules**:
- `draft` → `in_progress`: Harus ada material issue completed
- `in_progress` → `completed`: Harus QC pass

---

### 9.3 Material Issue Status

| Status | Description | Trigger | Stock Impact |
|--------|-------------|---------|--------------|
| `draft` | Baru dibuat | Creation | Tidak ada |
| `pending_approval` | Menunggu approval | User submit | Tidak ada |
| `approved` | Disetujui | Approver approve | Stock reserved |
| `completed` | Selesai | System complete | Stock deducted |

---

### 9.4 Production Status

| Status | Description | Next Action |
|--------|-------------|-------------|
| `draft` | Produksi berlangsung | Finish production |
| `finished` | Produksi selesai | Quality control |

---

### 9.5 Quality Control Status

| Status | Description | Impact |
|--------|-------------|--------|
| `pending` | Menunggu inspeksi | - |
| `pass` | Lolos QC | FG masuk stock, MO completed |
| `fail` | Gagal QC | Produksi rework/scrap |

---

## 10. OBSERVERS & BUSINESS LOGIC

### 10.1 ManufacturingOrderObserver

**Location**: `app/Observers/ManufacturingOrder.php`

**Events**:

#### updated()
- Update ProductionPlan status based on MO status
  - MO `in_progress` → ProductionPlan `in_progress`
  - MO `completed` → ProductionPlan `completed`
- **Tidak** membuat stock movements (handled by MaterialIssue & QC)

#### deleting()
- Cascade delete productions
- Cascade delete journal entries

---

### 10.2 MaterialIssueObserver

**Location**: `app/Observers/MaterialIssueObserver.php`

**Events**:

#### updated()
**Saat status berubah ke `approved`**:
```php
// 1. Create stock reservations
foreach ($materialIssue->items as $item) {
    StockReservation::create([
        'product_id' => $item->product_id,
        'warehouse_id' => $materialIssue->warehouse_id,
        'quantity' => $item->quantity,
        'material_issue_id' => $materialIssue->id,
        'reservation_type' => 'production',
        'status' => 'reserved'
    ]);
    
    // 2. Update qty_reserved
    InventoryStock::where('product_id', $item->product_id)
        ->where('warehouse_id', $materialIssue->warehouse_id)
        ->increment('qty_reserved', $item->quantity);
}
```

**Saat status berubah ke `completed`**:
```php
// 1. Deduct actual stock
foreach ($materialIssue->items as $item) {
    InventoryStock::where('product_id', $item->product_id)
        ->where('warehouse_id', $materialIssue->warehouse_id)
        ->decrement('qty_available', $item->quantity);
    
    // 2. Release reservation
    InventoryStock::where('product_id', $item->product_id)
        ->where('warehouse_id', $materialIssue->warehouse_id)
        ->decrement('qty_reserved', $item->quantity);
    
    // 3. Update stock reservation status
    StockReservation::where('material_issue_id', $materialIssue->id)
        ->where('product_id', $item->product_id)
        ->update(['status' => 'fulfilled']);
}

// 4. Create WIP journal entry
$manufacturingJournalService->postMaterialIssue($materialIssue);
```

---

### 10.3 QualityControlObserver (Manufacturing)

**Location**: `app/Observers/QualityControlObserver.php`

**Events**:

#### updated()
**Saat status berubah ke `pass` untuk Production**:
```php
if ($qc->from_model_type === 'App\Models\Production') {
    $production = $qc->from_model;
    $mo = $production->manufacturingOrder;
    $plan = $mo->productionPlan;
    $product = $plan->product;
    $qty = $qc->qty_accepted;
    
    // 1. Add finished goods to inventory
    InventoryStock::updateOrCreate(
        [
            'product_id' => $product->id,
            'warehouse_id' => $plan->warehouse_id
        ],
        [
            'qty_available' => DB::raw('qty_available + ' . $qty)
        ]
    );
    
    // 2. Complete MO
    $mo->update([
        'status' => 'completed',
        'end_date' => now()
    ]);
    
    // 3. Complete ProductionPlan
    $plan->update(['status' => 'completed']);
    
    // 4. Create finished goods journal entries
    $manufacturingJournalService->postFinishedGoods($production, $qc);
    
    // 5. Update product cost_price
    $product->updateCostPrice($totalCost, $qty);
}
```

---

### 10.4 ProductionObserver

**Location**: `app/Observers/ProductionObserver.php`

**Events**:

#### deleting()
- Cascade delete quality control
- Cascade delete journal entries

---

## 11. IMPACT ANALYSIS

### 11.1 Saat Membuat Production Plan

**Database Impact**:
- ✅ production_plans +1 record
- ❌ Material requirements calculated (in memory, not stored)

**System Impact**:
- ❌ Inventory stock: TIDAK berubah
- ❌ Journal entries: TIDAK dibuat
- ❌ Stock reservation: TIDAK dibuat

**User Impact**:
- ✅ Production plan bisa diedit/dihapus
- ✅ Bisa dilihat material requirements dari BOM

---

### 11.2 Saat Membuat Manufacturing Order

**Database Impact**:
- ✅ manufacturing_orders +1 record
- ✅ Items (JSON) berisi material requirements

**System Impact**:
- ❌ Inventory stock: TIDAK berubah
- ❌ Journal entries: TIDAK dibuat
- ❌ Stock reservation: TIDAK dibuat

**User Impact**:
- ✅ MO bisa diedit sebelum start
- ✅ Material requirements sudah clear

---

### 11.3 Saat Material Issue Approved

**Database Impact**:
- ✅ material_issues status = `approved`
- ✅ stock_reservations +N records
- ✅ inventory_stocks.qty_reserved += quantity

**System Impact**:
- ✅ Stock reserved (tidak bisa digunakan untuk tujuan lain)
- ✅ Available stock berkurang (qty_available = total - reserved)
- ❌ Journal entries: BELUM dibuat
- ❌ Actual stock: BELUM berkurang

**User Impact**:
- ✅ Material sudah dialokasikan untuk produksi
- ✅ Warning jika stock tidak cukup

---

### 11.4 Saat Material Issue Completed

**Database Impact**:
- ✅ material_issues status = `completed`
- ✅ inventory_stocks.qty_available -= quantity
- ✅ inventory_stocks.qty_reserved -= quantity
- ✅ stock_reservations status = `fulfilled`
- ✅ journal_entries +2 records (WIP & Inventory)

**System Impact**:
- ✅ Actual stock berkurang
- ✅ **Journal entries dibuat** (Dr. WIP, Cr. Inventory)
- ✅ Balance Sheet: WIP bertambah, Inventory berkurang
- ✅ Material cost masuk production cost

**User Impact**:
- ✅ Material sudah keluar dari gudang
- ✅ Siap untuk produksi

---

### 11.5 Saat MO Start (In Progress)

**Database Impact**:
- ✅ manufacturing_orders status = `in_progress`
- ✅ productions +1 record (auto-created)
- ✅ production_plans status = `in_progress`

**System Impact**:
- ❌ Inventory stock: TIDAK berubah lagi
- ❌ Journal entries: TIDAK dibuat
- ✅ Production tracking started

**User Impact**:
- ✅ Produksi officially dimulai
- ✅ Production record untuk tracking

---

### 11.6 Saat QC Pass

**Database Impact**:
- ✅ quality_controls status = `pass`
- ✅ inventory_stocks.qty_available += qty_accepted (FG)
- ✅ manufacturing_orders status = `completed`
- ✅ production_plans status = `completed`
- ✅ journal_entries +2 records (FG & WIP)
- ✅ products.cost_price updated

**System Impact**:
- ✅ Finished goods masuk stock
- ✅ **Journal entries dibuat** (Dr. FG, Cr. WIP)
- ✅ Balance Sheet: FG bertambah, WIP berkurang
- ✅ Product cost price updated (weighted average)
- ✅ Income Statement: TIDAK terpengaruh (masih di FG, belum COGS)

**User Impact**:
- ✅ Produk jadi tersedia untuk dijual
- ✅ Muncul di inventory report
- ✅ Cost price ter-update

---

### 11.7 Dampak ke Laporan Keuangan

#### Balance Sheet (Neraca)

| Account | Debit | Credit | Event |
|---------|-------|--------|-------|
| Work in Progress (1140.02) | +xxx | | Material Issue completed |
| Inventory - Raw Material (1101.01) | | +xxx | Material Issue completed |
| Finished Goods (1140.01) | +xxx | | QC pass |
| Work in Progress (1140.02) | | +xxx | QC pass |

**Net Effect**:
- ↓ Raw Material Inventory (saat material issue)
- ↑ Work in Progress (saat material issue)
- ↓ Work in Progress (saat QC pass)
- ↑ Finished Goods Inventory (saat QC pass)
- **Total Asset tidak berubah** (hanya perpindahan antar akun)

#### Income Statement (Laba Rugi)

**Saat Manufacturing**:
- **Tidak ada dampak** karena cost masih di Balance Sheet (FG)

**Saat Sales** (di luar manufacturing flow):
```
Dr. COGS (6101)                         xxx
Cr. Finished Goods (1140.01)               xxx
```
- Expense (COGS) baru diakui saat produk terjual

#### Cash Flow Statement

**Tidak ada dampak langsung** karena:
- Manufacturing adalah non-cash transaction
- Cash impact hanya saat:
  - Purchase raw material (sudah dicatat di purchase flow)
  - Pay labor & overhead (operational expenses)
  - Sales (cash inflow)

---

### 11.8 Dampak ke Inventory Management

#### Inventory Movement

```
1. Material Issue Approved:
   - Raw Material: qty_reserved += quantity
   - Available for sale: berkurang

2. Material Issue Completed:
   - Raw Material: qty_available -= quantity
   - Raw Material: qty_reserved -= quantity
   - WIP: conceptual increase (tracked in journal)

3. QC Pass:
   - Finished Goods: qty_available += quantity
   - Available for sale: bertambah
```

#### Inventory Valuation

**Raw Material**:
- Menggunakan cost dari purchase
- Bisa FIFO, Weighted Average, atau LIFO (tergantung config)

**Finished Goods**:
- **Absorption Costing**: Material + Labor + Overhead
- Cost calculated dari BOM:
  ```
  FG Cost per unit = Material Cost + Labor Cost + Overhead Cost
  ```
- Saat QC pass, product.cost_price di-update dengan weighted average:
  ```
  New Cost = (Old Stock × Old Cost + New Qty × New Cost) / (Old Stock + New Qty)
  ```

---

## 12. COSTING & BOM CALCULATION

### 12.1 BOM Cost Structure

```
Total BOM Cost = Material Cost + Labor Cost + Overhead Cost

Material Cost = Σ(BOM Item Quantity × Product Cost Price)
Labor Cost = Manual input di BOM
Overhead Cost = Manual input di BOM
```

**Example**:
```
Product: Chair
BOM Quantity: 1 unit

Materials:
- Wood: 2 pcs × Rp 50,000 = Rp 100,000
- Screws: 10 pcs × Rp 500 = Rp 5,000
- Paint: 0.5 liter × Rp 20,000 = Rp 10,000
Material Cost = Rp 115,000

Labor Cost: Rp 30,000 (manual input)
Overhead Cost: Rp 15,000 (manual input)

Total BOM Cost per unit = Rp 160,000
```

### 12.2 Production Cost Calculation

```
Production Cost = BOM Cost × Quantity Produced

Example:
- Quantity Produced: 10 chairs
- BOM Cost per unit: Rp 160,000
- Total Production Cost: Rp 1,600,000
```

### 12.3 Cost Allocation

**Material Issue Completed**:
```
Dr. WIP (1140.02)                    Rp 1,150,000 (material only)
Cr. Inventory (1101.01)                  Rp 1,150,000
```

**Labor & Overhead** (optional entry):
```
Dr. WIP (1140.02)                    Rp 450,000 (labor + overhead)
Cr. Labor Cost (5xxx)                    Rp 300,000
Cr. Manufacturing Overhead (5xxx)        Rp 150,000
```

**QC Pass**:
```
Dr. Finished Goods (1140.01)         Rp 1,600,000
Cr. WIP (1140.02)                        Rp 1,600,000
```

### 12.4 Cost per Unit Tracking

System menghitung cost per unit untuk finished goods:

```php
$totalCost = $materialCost + $laborCost + $overheadCost;
$unitCost = $totalCost / $qtyProduced;

// Update product cost_price dengan weighted average
$oldStock = $product->current_stock;
$oldCost = $product->cost_price;
$newStock = $oldStock + $qtyProduced;
$newCost = (($oldStock × $oldCost) + ($qtyProduced × $unitCost)) / $newStock;

$product->update(['cost_price' => $newCost]);
```

**Impact**:
- COGS saat sales akan menggunakan cost_price terbaru
- Profit margin calculation lebih akurat
- Inventory valuation ter-update

---

## KESIMPULAN

### Key Takeaways:

1. **Manufacturing Flow** memiliki 6 tahap utama:
   - Production Plan → MO Creation → Material Issue → Production → QC → Finished Goods

2. **Stock Management** dengan reservation system:
   - Material Issue Approved: Stock reserved
   - Material Issue Completed: Stock deducted
   - QC Pass: Finished goods masuk

3. **Journal Entries** dibuat di 2 titik:
   - Material Issue Completed: Dr. WIP, Cr. Inventory
   - QC Pass: Dr. Finished Goods, Cr. WIP

4. **Cost Flow**:
   - Raw Material → WIP → Finished Goods → COGS
   - Absorption costing (Material + Labor + Overhead)

5. **BOM** adalah pusat dari manufacturing:
   - Menentukan material requirements
   - Menghitung production cost
   - Base untuk cost allocation

6. **Quality Control** adalah gate keeper:
   - Hanya produk yang pass QC yang masuk stock
   - Trigger journal entries untuk FG
   - Update product cost price

7. **Observer Pattern** menjaga konsistensi:
   - Auto-update status related models
   - Auto-create journal entries
   - Auto-update inventory

---

**Dibuat oleh**: GitHub Copilot  
**Untuk**: Sistem ERP Duta Tunggal  
**Tanggal**: 9 Desember 2025  
**Versi**: 1.0

---

*Dokumentasi ini dapat diupdate seiring perubahan sistem.*
