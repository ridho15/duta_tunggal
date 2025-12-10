# SALES ORDER FLOW DOCUMENTATION - SISTEM ERP DUTA TUNGGAL

## DAFTAR ISI
1. [Overview Sistem](#overview-sistem)
2. [Struktur Data Sales Order](#struktur-data-sales-order)
3. [Flow Lengkap Sales Order](#flow-lengkap-sales-order)
4. [Status dan Transisi](#status-dan-transisi)
5. [Journal Entries](#journal-entries)
6. [Account Receivable Management](#account-receivable-management)
7. [Customer Receipt & Pembayaran](#customer-receipt--pembayaran)
8. [Delivery Order Integration](#delivery-order-integration)
9. [Warehouse Management](#warehouse-management)
10. [Business Rules & Validations](#business-rules--validations)

---

## OVERVIEW SISTEM

Sistem ERP Duta Tunggal menggunakan Laravel dengan Filament sebagai admin panel. Flow Sales Order terintegrasi penuh dengan:
- Inventory Management
- Accounting (General Ledger)
- Warehouse Management
- Customer Relationship Management
- Delivery & Logistics

### Arsitektur Utama:
- **Models**: SaleOrder, SaleOrderItem, DeliveryOrder, Invoice, AccountReceivable, CustomerReceipt
- **Observers**: SaleOrderObserver, InvoiceObserver, CustomerReceiptObserver, DeliveryOrderObserver
- **Services**: LedgerPostingService, SalesOrderService, DeliveryOrderService
- **Resources**: Filament resources untuk UI management

---

## STRUKTUR DATA SALES ORDER

### Model: SaleOrder
```php
protected $fillable = [
    'customer_id',           // ID Customer
    'quotation_id',          // ID Quotation (opsional)
    'so_number',             // Nomor SO (auto-generated)
    'order_date',            // Tanggal order
    'delivery_date',         // Tanggal pengiriman
    'status',                // Status SO
    'total_amount',          // Total amount
    'request_approve_by',    // User yang request approve
    'request_approve_at',    // Waktu request approve
    'approve_by',            // User yang approve
    'approve_at',            // Waktu approve
    'close_by',              // User yang close
    'close_at',              // Waktu close
    'completed_at',          // Waktu completed
    'shipped_to',            // Alamat pengiriman
    'reject_by',             // User yang reject
    'reject_at',             // Waktu reject
    'reason_close',          // Alasan close
    'tipe_pengiriman',       // 'Ambil Sendiri' atau 'Kirim Langsung'
    'created_by',            // User yang buat
    'warehouse_confirmed_at', // Waktu warehouse confirm
    'cabang_id'              // ID Cabang
];
```

### Model: SaleOrderItem
```php
protected $fillable = [
    'sale_order_id',         // ID Sale Order
    'product_id',            // ID Product
    'quantity',              // Quantity ordered
    'delivered_quantity',    // Quantity sudah dikirim
    'unit_price',            // Harga per unit
    'discount',              // Discount per unit
    'tax',                   // Tax rate (%)
    'warehouse_id',          // ID Warehouse
    'rak_id',                // ID Rak
];
```

### Relasi Utama:
- **SaleOrder** belongsTo Customer
- **SaleOrder** belongsTo Quotation
- **SaleOrder** hasMany SaleOrderItem
- **SaleOrder** hasMany DeliveryOrder (many-to-many via delivery_sales_orders)
- **SaleOrderItem** belongsTo Product, Warehouse, Rak
- **SaleOrderItem** hasMany DeliveryOrderItem

---

## FLOW LENGKAP SALES ORDER

### 1. SALES ORDER CREATION

#### Step 1.1: Draft Creation
- **Trigger**: User membuat SO baru via Filament
- **Status**: `draft`
- **Actions Available**:
  - Edit items, customer, dates
  - Save as draft
  - Request Approve
- **Data Required**:
  - Customer ID
  - Order date
  - Delivery date
  - Items (product, qty, price, warehouse)
- **Validations**:
  - Customer credit limit check
  - Product availability check
  - Warehouse selection required

#### Step 1.2: Request Approve
- **Trigger**: User klik "Request Approve"
- **Status Change**: `draft` → `request_approve`
- **Actions**: Approve/Reject (by authorized users)
- **Notifications**: Email/SMS to approvers

#### Step 1.3: Approval
- **Trigger**: Authorized user approve
- **Status Change**: `request_approve` → `approved`
- **Automated Actions**:
  - Create WarehouseConfirmation
  - Create StockReservation untuk setiap item
  - Update inventory: qty_available → qty_reserved

### 2. WAREHOUSE CONFIRMATION

#### Step 2.1: Warehouse Confirmation Creation
- **Trigger**: SaleOrderObserver saat status → `approved`
- **Model**: WarehouseConfirmation + WarehouseConfirmationItem
- **Purpose**: Validasi ketersediaan stok oleh warehouse staff
- **Status**: `draft` → `confirmed`

#### Step 2.2: Stock Validation
- **Check**: qty_reserved vs actual stock
- **If insufficient**: SO tetap approved, tapi flag warning
- **If sufficient**: Proceed to delivery preparation

### 3. DELIVERY ORDER CREATION

#### Step 3.1: Delivery Order Creation
- **Trigger**: Manual creation atau otomatis saat SO completed
- **Model**: DeliveryOrder + DeliveryOrderItem
- **Link**: Via delivery_sales_orders pivot table
- **Status Flow**: `draft` → `approved` → `sent` → `received` → `completed`

#### Step 3.2: Stock Movement (DO Approved)
- **Trigger**: DeliveryOrderObserver saat status → `approved`
- **Action**: Create StockReservation untuk delivery
- **Inventory Impact**: qty_available dikurangi (reserved)

#### Step 3.3: Goods Delivery (DO Sent)
- **Trigger**: DeliveryOrderObserver saat status → `sent`
- **Journal Entries**: Cost of Goods Sold (COGS)
  - Debit: COGS (1140.20 atau custom COA)
  - Credit: Inventory (1140.01 atau custom COA)
- **Stock Movement**: Create outbound stock movement

#### Step 3.4: Delivery Completion (DO Completed)
- **Trigger**: DeliveryOrderObserver saat status → `completed`
- **Actions**:
  - Update SaleOrder status → `completed`
  - Update delivered_quantity di SaleOrderItem
  - Create Invoice otomatis

### 4. INVOICE CREATION

#### Step 4.1: Automatic Invoice Creation
- **Trigger**: SaleOrderObserver saat status → `completed`
- **Model**: Invoice + InvoiceItem
- **Data Source**: SaleOrder + SaleOrderItem + DeliveryOrder (additional costs)
- **Invoice Number**: `INV-{SO_NUMBER}-{TIMESTAMP}`

#### Step 4.2: Invoice Components
- **Subtotal**: Sum dari SaleOrderItem (qty × price - discount + tax)
- **Tax**: Calculated dari items
- **Other Fees**: Delivery costs dari DeliveryOrder
- **Total**: subtotal + tax + other_fees

#### Step 4.3: Account Receivable Creation
- **Trigger**: InvoiceObserver saat invoice created
- **Model**: AccountReceivable
- **Initial State**:
  - total = invoice.total
  - paid = 0
  - remaining = invoice.total
  - status = "Belum Lunas"

#### Step 4.4: Journal Entries (Sales Invoice)
- **Trigger**: InvoiceObserver via LedgerPostingService
- **Entries**:
  - Debit: Account Receivable (1120)
  - Credit: Revenue/Sales (4100)
  - Credit: Sales Discount (if any) (5100)
  - Debit: PPn Keluaran (if any) (2200)

### 5. CUSTOMER RECEIPT (PAYMENT)

#### Step 5.1: Receipt Creation
- **Model**: CustomerReceipt + CustomerReceiptItem
- **Methods**: Cash, Bank Transfer, Credit, Deposit
- **Multi-Invoice Support**: Bisa bayar multiple invoice sekaligus

#### Step 5.2: Payment Processing
- **Trigger**: CustomerReceiptObserver saat status → `Paid`/`Partial`
- **Account Receivable Update**:
  - paid += payment_amount
  - remaining -= payment_amount
  - status = remaining <= 0 ? "Lunas" : "Belum Lunas"

#### Step 5.3: Invoice Status Update
- **Rules**:
  - remaining = 0 → status = `paid`
  - remaining > 0 & paid > 0 → status = `partially_paid`
  - remaining > 0 & paid = 0 → status = `unpaid`

#### Step 5.4: Journal Entries (Payment)
- **Trigger**: CustomerReceiptObserver via LedgerPostingService
- **Cash Payment**:
  - Debit: Cash/Bank (1100)
  - Credit: Account Receivable (1120)
- **Bank Transfer**: Similar, different COA
- **Deposit**: Use customer deposit balance

---

## STATUS DAN TRANSISI

### SaleOrder Status Flow:
```
draft → request_approve → approved → completed → closed
   ↓           ↓            ↓           ↓
reject     reject       (auto)      closed
```

### DeliveryOrder Status Flow:
```
draft → approved → sent → received → completed
```

### Invoice Status Flow:
```
draft → sent → partially_paid → paid → overdue
```

### AccountReceivable Status:
```
Belum Lunas → Lunas
```

---

## JOURNAL ENTRIES

### 1. Sales Invoice Creation
```php
// Debit: Account Receivable
JournalEntry::create([
    'coa_id' => 1120, // Account Receivable
    'debit' => $invoice->total,
    'credit' => 0,
    'reference' => $invoice->invoice_number,
    'description' => 'Sales Invoice - Account Receivable',
]);

// Credit: Revenue
JournalEntry::create([
    'coa_id' => 4100, // Revenue
    'debit' => 0,
    'credit' => $subtotal,
    'reference' => $invoice->invoice_number,
    'description' => 'Sales Invoice - Revenue',
]);

// Credit: PPn Keluaran (if applicable)
JournalEntry::create([
    'coa_id' => 2200, // PPn Keluaran
    'debit' => 0,
    'credit' => $taxAmount,
    'reference' => $invoice->invoice_number,
    'description' => 'Sales Invoice - PPn Keluaran',
]);
```

### 2. Cost of Goods Sold (Delivery)
```php
// Debit: COGS
JournalEntry::create([
    'coa_id' => 1140.20, // COGS
    'debit' => $costAmount,
    'credit' => 0,
    'reference' => $do->do_number,
    'description' => 'Cost of Goods Sold - Delivery',
]);

// Credit: Inventory
JournalEntry::create([
    'coa_id' => 1140.01, // Inventory
    'debit' => 0,
    'credit' => $costAmount,
    'reference' => $do->do_number,
    'description' => 'Inventory Reduction - Delivery',
]);
```

### 3. Customer Payment
```php
// Debit: Cash/Bank
JournalEntry::create([
    'coa_id' => $cashCoaId,
    'debit' => $paymentAmount,
    'credit' => 0,
    'reference' => $receipt->id,
    'description' => 'Customer Payment - Cash',
]);

// Credit: Account Receivable
JournalEntry::create([
    'coa_id' => 1120, // AR
    'debit' => 0,
    'credit' => $paymentAmount,
    'reference' => $receipt->id,
    'description' => 'Customer Payment - AR Reduction',
]);
```

---

## ACCOUNT RECEIVABLE MANAGEMENT

### Model: AccountReceivable
```php
protected $fillable = [
    'invoice_id',           // Link ke Invoice
    'customer_id',          // Link ke Customer
    'total',                // Total invoice amount
    'paid',                 // Total sudah dibayar
    'remaining',            // Sisa yang harus dibayar
    'status',               // 'Lunas' / 'Belum Lunas'
    'created_by',           // User yang buat
    'cabang_id'             // ID Cabang
];
```

### Ageing Schedule
- **Model**: AgeingSchedule
- **Buckets**: Current, 1-30 days, 31-60 days, 61-90 days, 90+ days
- **Automatic Updates**: Based on due_date vs current_date

### AR Status Logic:
```php
if ($remaining <= 0) {
    $status = 'Lunas';
    // Delete ageing schedule
} elseif ($paid > 0) {
    $status = 'Belum Lunas'; // Partial payment
} else {
    $status = 'Belum Lunas'; // No payment yet
}
```

---

## CUSTOMER RECEIPT & PEMBAYARAN

### Model: CustomerReceipt
```php
protected $fillable = [
    'customer_id',          // ID Customer
    'selected_invoices',    // Array invoice IDs
    'payment_date',         // Tanggal bayar
    'total_payment',        // Total pembayaran
    'payment_method',       // 'Cash', 'Bank Transfer', 'Credit', 'Deposit'
    'coa_id',               // COA untuk payment method
    'notes',                // Catatan
    'status',               // 'Draft', 'Partial', 'Paid'
    'created_by',           // User yang buat
    'cabang_id'             // ID Cabang
];
```

### Model: CustomerReceiptItem
```php
protected $fillable = [
    'customer_receipt_id',  // ID CustomerReceipt
    'invoice_id',           // ID Invoice yang dibayar
    'method',               // Payment method
    'amount',               // Jumlah pembayaran untuk invoice ini
    'coa_id',               // COA
    'payment_date',         // Tanggal bayar
    'selected_invoices'     // Array invoice IDs (legacy)
];
```

### Payment Methods:
1. **Cash**: Debit Cash COA (1100 series)
2. **Bank Transfer**: Debit Bank COA (1100 series)
3. **Credit**: Special handling untuk credit payments
4. **Deposit**: Use customer deposit balance

### Multi-Invoice Payments:
- Customer bisa bayar multiple invoice dalam satu receipt
- System otomatis alokasikan pembayaran ke invoice dengan due_date terdekat
- Support partial payments per invoice

---

## DELIVERY ORDER INTEGRATION

### Model: DeliveryOrder
```php
protected $fillable = [
    'do_number',            // Nomor DO (auto-generated)
    'delivery_date',        // Tanggal pengiriman
    'driver_id',            // ID Driver
    'vehicle_id',           // ID Vehicle
    'warehouse_id',         // ID Warehouse
    'status',               // Status DO
    'notes',                // Catatan
    'additional_cost',      // Biaya tambahan
    'additional_cost_description', // Deskripsi biaya tambahan
    'created_by',           // User yang buat
    'cabang_id'             // ID Cabang
];
```

### Model: DeliveryOrderItem
```php
protected $fillable = [
    'delivery_order_id',    // ID DeliveryOrder
    'sale_order_item_id',   // ID SaleOrderItem
    'product_id',           // ID Product
    'quantity',             // Quantity dikirim
    'reason'                // Alasan (untuk return)
];
```

### Integration Points:
1. **Stock Reservation**: Saat DO approved
2. **Stock Movement**: Saat DO sent (outbound)
3. **SaleOrder Updates**: Saat DO completed
4. **Invoice Creation**: Trigger otomatis saat SO completed
5. **Journal Entries**: COGS saat delivery

---

## WAREHOUSE MANAGEMENT

### Stock Reservation System:
- **Purpose**: Reserve stock untuk SO yang approved
- **Trigger**: SaleOrder approved → create StockReservation
- **Impact**: qty_available dikurangi, qty_reserved ditambah

### Warehouse Confirmation:
- **Model**: WarehouseConfirmation + WarehouseConfirmationItem
- **Purpose**: Validasi stok sebelum delivery
- **Status**: draft → confirmed
- **Integration**: Dengan SaleOrder approval flow

### Stock Movement Types:
1. **Sales**: Outbound movement saat delivery
2. **Purchase**: Inbound movement saat receiving
3. **Adjustment**: Manual stock adjustments
4. **Transfer**: Antar warehouse transfers

---

## BUSINESS RULES & VALIDATIONS

### 1. Credit Limit Validation
```php
// Dalam SaleOrderResource
$creditService = app(CreditValidationService::class);
$validation = $creditService->canCustomerMakePurchase($customer, $totalAmount);
if (!$validation['can_purchase']) {
    $fail(implode(' ', $validation['messages']));
}
```

### 2. Stock Availability Check
```php
// Dalam SaleOrderItem validation
$availableStock = InventoryStock::where('product_id', $productId)
    ->where('warehouse_id', $warehouseId)
    ->sum('qty_available');

if ($quantity > $availableStock) {
    $fail('Stock tidak mencukupi');
}
```

### 3. Delivery Order Quantity Validation
```php
// Dalam DeliveryOrderItem
$remainingQty = $saleOrderItem->quantity - $saleOrderItem->delivered_quantity;
if ($quantity > $remainingQty) {
    throw new Exception('Quantity melebihi remaining quantity');
}
```

### 4. Payment Amount Validation
```php
// Dalam CustomerReceipt
$totalInvoiceAmount = Invoice::whereIn('id', $selectedInvoices)->sum('total');
if ($totalPayment > $totalInvoiceAmount) {
    // Allow overpayment, but log warning
    Log::warning('Overpayment detected', [...]);
}
```

### 5. Status Transition Rules
- SO hanya bisa completed jika semua items sudah delivered
- Invoice hanya bisa paid jika AR remaining = 0
- DO hanya bisa sent jika ada Surat Jalan
- Payment hanya bisa processed jika invoice status = sent/partially_paid

---

## MONITORING & REPORTING

### Key Metrics:
1. **Sales Order Conversion Rate**: draft → completed
2. **Delivery Performance**: On-time delivery rate
3. **Payment Collection**: Average collection period
4. **AR Ageing**: Days outstanding analysis
5. **Inventory Turnover**: Stock movement efficiency

### Alerts & Notifications:
1. **Low Stock Alerts**: Saat qty_available < minimum_stock
2. **Overdue Payments**: Invoice due_date passed
3. **Credit Limit Warnings**: Customer approaching limit
4. **Delivery Delays**: DO not completed on time

---

## INTEGRATION POINTS

### With Other Modules:
1. **Purchase Order**: Untuk drop shipping
2. **Manufacturing**: Untuk produced items
3. **Accounting**: General ledger integration
4. **Inventory**: Real-time stock updates
5. **CRM**: Customer data & history
6. **Reporting**: Sales analytics & dashboards

### API Endpoints:
- RESTful APIs untuk mobile apps
- Webhooks untuk external integrations
- Export/Import untuk data migration

---

## CONCLUSION

Flow Sales Order di sistem ERP Duta Tunggal merupakan proses terintegrasi yang mencakup:
- Order management dari draft hingga completion
- Inventory control dengan stock reservations
- Delivery management dengan real-time tracking
- Automatic invoicing dan AR creation
- Flexible payment processing
- Complete audit trail dengan journal entries

Setiap step dalam flow memiliki business rules, validations, dan automated actions yang memastikan data integrity dan business compliance.