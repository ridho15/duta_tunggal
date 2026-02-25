# NEW PURCHASE ORDER FLOW DOCUMENTATION

**Sistem ERP Duta Tunggal**  
**Tanggal Update**: 9 Februari 2026  
**Versi**: 2.0 - New QC First Flow

---

## OVERVIEW - NEW FLOW

### Flow Baru (Updated):
```
Purchase Order (Approved)
    ↓
Quality Control (QC dari PO Item)
    ↓
QC Pass → Auto-Create Purchase Receipt
    ↓
Journal Entries Otomatis
    ↓
Stock Bertambah (Inventory Update)
    ↓
Check Completion → PO Status: Completed
    ↓
Purchase Invoice (Manual by Finance)
    ↓
Vendor Payment
```

---

## PERUBAHAN UTAMA

### 1. **QC Dilakukan SEBELUM Receipt**

**Before:**
```
PO → Purchase Receipt → QC → Stock Update
```

**After (NEW):**
```
PO → QC → Auto-create Receipt → Stock Update
```

### 2. **Purchase Receipt Otomatis Dibuat**

Setelah QC pass, system otomatis membuat:
- `PurchaseReceipt` record
- `PurchaseReceiptItem` record dengan:
  - `qty_received` = `passed_quantity + rejected_quantity`
  - `qty_accepted` = `passed_quantity`
  - `qty_rejected` = `rejected_quantity`
  - `is_sent` = 1 (sudah dikirim ke QC)

### 3. **Auto-Complete Purchase Order**

PO otomatis `completed` ketika:
- Semua PO items sudah punya receipt dengan `qty_accepted >= quantity`
- Trigger: Setelah QC completion

**Manual Completion:**
- User bisa manual complete PO via button/action
- Method: `$purchaseOrder->manualComplete($userId)`
- Validation: Cek apakah PO `canBeCompleted()`

---

## DETAIL FLOW

### Step 1: Create Quality Control dari PO Item

**Trigger**: User membuat QC langsung dari PurchaseOrderItem

**Method**: `QualityControlService::createQCFromPurchaseOrderItem()`

```php
$qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
    'passed_quantity' => 100,
    'rejected_quantity' => 5,
    'inspected_by' => $userId,
    'warehouse_id' => $warehouseId,
    'rak_id' => $rakId,
]);
```

**Database Changes:**
```sql
INSERT INTO quality_controls (
    qc_number, passed_quantity, rejected_quantity,
    status, from_model_type, from_model_id,
    product_id, warehouse_id, rak_id
) VALUES (
    'QC-20260209-0001', 100, 5, 0,
    'App\Models\PurchaseOrderItem', {po_item_id},
    {product_id}, {warehouse_id}, {rak_id}
);
```

---

### Step 2: Complete Quality Control

**Trigger**: User menyelesaikan/approve QC

**Method**: `QualityControlService::completeQualityControl()`

**Business Logic:**

1. **Update QC Status**
   ```php
   $qc->update(['status' => 1, 'date_send_stock' => now()]);
   ```

2. **Create Return Product** (jika ada rejected_quantity > 0)
   ```php
   if ($qc->rejected_quantity > 0) {
       // Auto-create ReturnProduct
   }
   ```

3. **Auto-Create Purchase Receipt** (NEW!)
   ```php
   $this->autoCreatePurchaseReceiptFromQC($qc, $data);
   ```

4. **Create Journal Entries**
   ```
   Dr. Inventory (1101.01)              xxx
   Cr. Temporary Procurement (1180.01)     xxx
   ```

5. **Create Stock Movement**
   ```php
   StockMovement::create([
       'product_id' => $product_id,
       'warehouse_id' => $warehouse_id,
       'quantity' => $passed_quantity,
       'type' => 'purchase_in',
       'from_model_type' => QualityControl::class,
       'from_model_id' => $qc->id,
   ]);
   ```

6. **Check and Auto-Complete PO** (NEW!)
   ```php
   $this->checkAndCompletePurchaseOrder($qc);
   ```

**Database Changes:**
```sql
-- Update QC
UPDATE quality_controls SET status = 1, date_send_stock = NOW() WHERE id = {qc_id};

-- Create Purchase Receipt
INSERT INTO purchase_receipts (
    receipt_number, purchase_order_id, receipt_date,
    received_by, notes, status, cabang_id
) VALUES (
    'PR-20260209-0001', {po_id}, NOW(),
    {user_id}, 'Auto-created from QC: QC-20260209-0001',
    'completed', {cabang_id}
);

-- Create Purchase Receipt Item
INSERT INTO purchase_receipt_items (
    purchase_receipt_id, purchase_order_item_id,
    product_id, qty_received, qty_accepted, qty_rejected,
    warehouse_id, rak_id, is_sent
) VALUES (
    {receipt_id}, {po_item_id}, {product_id},
    105, 100, 5, {warehouse_id}, {rak_id}, 1
);

-- Create Journal Entries
INSERT INTO journal_entries (
    coa_id, date, reference, description,
    debit, credit, journal_type,
    source_type, source_id
) VALUES 
    ({inventory_coa}, NOW(), 'QC-20260209-0001', 'QC Inventory...', 10000, 0, 'inventory', 'QualityControl', {qc_id}),
    ({temp_procurement_coa}, NOW(), 'QC-20260209-0001', 'QC Inventory...', 0, 10000, 'inventory', 'QualityControl', {qc_id});

-- Create Stock Movement
INSERT INTO stock_movements (
    product_id, warehouse_id, quantity, value,
    type, date, from_model_type, from_model_id
) VALUES (
    {product_id}, {warehouse_id}, 100, 10000,
    'purchase_in', NOW(), 'QualityControl', {qc_id}
);

-- Check and Complete PO
UPDATE purchase_orders SET
    status = 'completed',
    completed_by = {user_id},
    completed_at = NOW()
WHERE id = {po_id} AND {all_items_received};
```

---

### Step 3: Purchase Invoice (Manual)

**Trigger**: Finance membuat invoice (sama seperti sebelumnya)

**Status PO**: `invoiced`

**Business Logic**: (tidak berubah)
- Create Account Payable
- Post invoice journal entries

---

### Step 4: Vendor Payment (Manual)

**Trigger**: Finance membayar invoice (sama seperti sebelumnya)

**Status PO**: `paid`

**Business Logic**: (tidak berubah)
- Update Account Payable
- Post payment journal entries

---

## MANUAL COMPLETION

### Tombol Complete PO

User dapat manual complete PO melalui button/action di Filament.

**Method:**
```php
use App\Models\PurchaseOrder;

$purchaseOrder = PurchaseOrder::find($id);

// Check if can be completed
if ($purchaseOrder->canBeCompleted()) {
    $purchaseOrder->manualComplete($userId);
    
    // Success notification
} else {
    // Error: PO sudah completed/closed/paid
}
```

**Implementation di Filament Action:**
```php
Action::make('complete')
    ->label('Complete PO')
    ->icon('heroicon-o-check-circle')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (PurchaseOrder $record) => $record->canBeCompleted())
    ->action(function (PurchaseOrder $record) {
        $record->manualComplete();
        
        Notification::make()
            ->title('Purchase Order Completed')
            ->success()
            ->send();
    })
```

---

## STATUS LIFECYCLE

```
draft
    ↓
approved (user approval)
    ↓
[QC Process] (QC dari PO Item)
    ↓
completed (auto after all items received OR manual via button)
    ↓
invoiced (finance creates invoice)
    ↓
paid (finance pays invoice)
```

**Alternative:**
- `approved → completed` (manual via button, skip QC)
- `approved → closed` (cancel PO)

---

## JOURNAL ENTRIES TIMELINE

| Event | Journal Entries | Trigger |
|-------|----------------|---------|
| **QC Completion** | Dr. Inventory<br>Cr. Temporary Procurement | `QualityControlService::completeQualityControl()` |
| **Auto Receipt Creation** | (No journal, only record) | `autoCreatePurchaseReceiptFromQC()` |
| **Automatic posting retries** | `postPurchaseReceipt` will be retried with backoff when triggered automatically from QC to mitigate transient race conditions | Controlled by `config('procurement.auto_post_retries')` and `PROCUREMENT_AUTO_POST_BACKOFF_MS` environment variable |
| **Stock Movement** | (No journal, only inventory update) | `StockMovement::create()` |
| **Invoice** | Dr. Unbilled Purchase<br>Cr. Account Payable | `LedgerPostingService::postInvoice()` |
| **Payment** | Dr. Account Payable<br>Cr. Cash/Bank | `VendorPaymentObserver` |

---

## ADVANTAGES OF NEW FLOW

### 1. **Quality First Approach**
- Barang di-QC SEBELUM masuk sistem sebagai "diterima"
- Mengurangi risiko barang reject masuk inventory

### 2. **Automated Receipt**
- Mengurangi manual data entry
- Receipt otomatis akurat sesuai hasil QC

### 3. **Auto Completion**
- PO otomatis completed ketika semua item diterima
- Mengurangi human error lupa complete PO

### 4. **Flexibility**
- User masih bisa manual complete jika perlu
- Support berbagai skenario bisnis

### 5. **Better Tracking**
- Clear audit trail: QC → Receipt → Stock
- Journal entries lebih akurat

---

## MIGRATION FROM OLD FLOW

### Untuk PO yang sudah ada Receipt:
- Flow lama tetap berjalan (backward compatible)
- QC dari PurchaseReceiptItem tetap didukung

### Untuk PO baru:
- Gunakan flow baru: QC langsung dari PO Item
- System otomatis create receipt setelah QC

### Dual Support:
System mendukung kedua flow:
1. **Old Flow**: PO → Receipt → QC → Stock
2. **New Flow**: PO → QC → Auto-Receipt → Stock

---

## API METHODS

### QualityControlService

```php
// Create QC from PO Item
createQCFromPurchaseOrderItem($purchaseOrderItem, $data): QualityControl

// Complete QC (with auto-receipt creation)
completeQualityControl($qualityControl, $data): void

// Auto-create receipt from QC
protected autoCreatePurchaseReceiptFromQC($qualityControl, $data): PurchaseReceipt

// Check and complete PO
protected checkAndCompletePurchaseOrder($qualityControl): void

// Generate receipt number
protected generateReceiptNumber(): string
```

### PurchaseOrder Model

```php
// Manual completion
manualComplete($userId = null): PurchaseOrder

// Check if can be completed
canBeCompleted(): bool
```

---

## TESTING

### Test QC Flow:
```php
$poItem = PurchaseOrderItem::factory()->create([...]);

// Create QC from PO Item
$qc = $qcService->createQCFromPurchaseOrderItem($poItem, [
    'passed_quantity' => 100,
    'rejected_quantity' => 5,
]);

// Complete QC
$qcService->completeQualityControl($qc, []);

// Assert: Receipt created
$this->assertDatabaseHas('purchase_receipts', [...]);

// Assert: Stock updated
$this->assertDatabaseHas('stock_movements', [...]);

// Assert: PO completed (if all items received)
$po->refresh();
$this->assertEquals('completed', $po->status);
```

### Test Manual Completion:
```php
$po = PurchaseOrder::factory()->create(['status' => 'approved']);

// Manual complete
$po->manualComplete($userId);

// Assert
$this->assertEquals('completed', $po->status);
$this->assertEquals($userId, $po->completed_by);
$this->assertNotNull($po->completed_at);
```

---

## NOTES

- Flow lama (Receipt → QC) tetap didukung untuk backward compatibility
- System otomatis detect dari mana QC berasal (`from_model_type`)
- Auto-completion hanya untuk QC dari PurchaseOrderItem
- Manual completion tersedia via method `manualComplete()` atau Filament action
- Journal entries tetap sama, hanya timing-nya yang berubah

---

**END OF DOCUMENTATION**
