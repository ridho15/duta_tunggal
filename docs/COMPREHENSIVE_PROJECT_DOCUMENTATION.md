# Duta Tunggal ERP - Comprehensive Documentation

## Project Overview

**Duta Tunggal ERP** adalah sistem Enterprise Resource Planning (ERP) komprehensif yang dibangun dengan Laravel dan Filament untuk mengelola operasi bisnis perusahaan manufaktur dan distribusi. Sistem ini mengintegrasikan berbagai modul bisnis dalam satu platform terpadu.

## Teknologi Stack

### Backend Framework
- **Laravel 11.x** - Framework PHP utama
- **PHP 8.2+** - Runtime environment
- **MySQL 8.0+** - Database utama

### Frontend & Admin Panel
- **Filament 3.x** - Admin panel dan form builder
- **Tailwind CSS** - Styling framework
- **Alpine.js** - JavaScript framework (via Filament)

### Testing & Quality Assurance
- **PHPUnit** - Unit dan feature testing
- **Playwright** - End-to-end testing
- **Laravel Dusk** - Browser testing

### Additional Libraries
- **Carbon** - Date/time handling
- **DomPDF** - PDF generation
- **Laravel Excel** - Excel import/export
- **Spatie Laravel Permission** - Role-based access control
- **Laravel Activity Log** - Audit logging

## Project Statistics

- **Models**: 45+ Eloquent models
- **Database Migrations**: 150+ migration files
- **Test Files**: 50+ test classes with comprehensive coverage
- **Filament Resources**: 35+ admin resources
- **Service Classes**: 20+ business logic services
- **Documentation Files**: 15+ detailed guides

## Core Modules & Features

### 1. Master Data Management
**Purpose**: Mengelola data dasar yang digunakan di seluruh sistem

#### Sub-modules:
- **Company/Branch Management** (`Cabang`)
- **User Management** dengan role-based permissions
- **Product Management** dengan kategori dan unit of measure
- **Supplier Management**
- **Customer Management**
- **Warehouse & Rack Management**
- **Currency Management**
- **Chart of Accounts (COA)** - Dynamic accounting structure

#### Key Features:
- CRUD operations untuk semua master data
- Soft delete untuk data safety
- Activity logging untuk audit trail
- Import/export functionality
- Search dan filtering

### 2. Procurement/Purchase Management
**Purpose**: Mengelola seluruh proses pembelian dari supplier

#### Main Flow:
```
Purchase Request → Purchase Order → Purchase Receipt → Quality Control → Inventory
```

#### Sub-modules:
- **Purchase Order (PO)** - Pemesanan ke supplier
- **Purchase Receipt** - Penerimaan barang dari supplier (record-only)
- **Quality Control (QC)** - Inspeksi kualitas barang (dari PO Item)
- **Purchase Return** - Retur ke supplier
- **Purchase Invoice** - Faktur pembelian

#### Key Features:
- Multi-step approval workflow
- Partial receipt handling
- Quality control integration (QC dari PO Item, bukan receipt)
- Automatic journal entries
- Supplier credit management
- Purchase return processing

### 3. Sales & Distribution
**Purpose**: Mengelola proses penjualan dan distribusi

#### Main Flow:
```
Sales Order → Delivery Order → Sales Invoice → Payment Collection
```

#### Sub-modules:
- **Sales Order (SO)** - Pesanan dari customer
- **Delivery Order** - Pengiriman barang
- **Sales Invoice** - Faktur penjualan
- **Customer Payment** - Penerimaan pembayaran
- **Sales Return** - Retur dari customer

#### Key Features:
- Order to cash cycle
- Stock reservation system
- Multi-warehouse support
- Delivery tracking
- Credit limit management
- Automatic invoice generation

### 4. Inventory Management
**Purpose**: Mengelola stok barang dan warehouse operations

#### Sub-modules:
- **Stock Movement** - Tracking pergerakan stok
- **Inventory Stock** - Real-time stock levels
- **Stock Adjustment** - Koreksi stok manual
- **Stock Opname** - Stock taking
- **Material Issue** - Pengeluaran bahan untuk produksi

#### Key Features:
- Real-time stock tracking
- Multi-warehouse support
- FIFO/LIFO costing methods
- Stock reservation for sales
- Automatic stock adjustments
- Low stock alerts

### 5. Manufacturing/Production
**Purpose**: Mengelola proses produksi dan manufacturing

#### Main Flow:
```
Production Plan → Bill of Material → Manufacturing Order → Quality Control → Finished Goods
```

#### Sub-modules:
- **Production Plan** - Perencanaan produksi
- **Bill of Material (BOM)** - Komposisi produk
- **Manufacturing Order (MO)** - Order produksi
- **Material Issue** - Pengeluaran bahan baku
- **Quality Control** - QC untuk produk jadi
- **Production Costing** - Perhitungan biaya produksi

#### Key Features:
- Multi-level BOM support
- Work order management
- Material tracking
- Quality control integration
- Cost calculation
- Production reporting

### 6. Financial Accounting
**Purpose**: Mengelola aspek keuangan perusahaan

#### Sub-modules:
- **General Ledger** - Buku besar umum
- **Journal Entries** - Input jurnal manual
- **Chart of Accounts** - Struktur akun dinamis
- **Financial Reports**:
  - Balance Sheet
  - Income Statement
  - Cash Flow Statement
  - Trial Balance

#### Key Features:
- Double-entry accounting
- Automatic journal entries
- Dynamic COA structure
- Multi-currency support
- Financial period management
- Audit trail

### 7. Reporting & Analytics
**Purpose**: Menyediakan laporan dan analisis bisnis

#### Report Categories:
- **Financial Reports** - Laporan keuangan
- **Operational Reports** - Laporan operasional
- **Inventory Reports** - Laporan stok
- **Sales Reports** - Laporan penjualan
- **Purchase Reports** - Laporan pembelian

#### Key Features:
- Real-time reporting
- Export to Excel/PDF
- Custom date ranges
- Drill-down capabilities
- Dashboard widgets

## Database Structure

### Core Tables

#### Master Data Tables
- `users` - User accounts and authentication
- `cabangs` - Company branches
- `products` - Product catalog with categories
- `suppliers` - Supplier information
- `customers` - Customer information
- `warehouses` - Warehouse locations
- `raks` - Storage racks within warehouses
- `currencies` - Currency definitions
- `chart_of_accounts` - Dynamic accounting chart of accounts
- `product_categories` - Product categorization
- `unit_of_measures` - Measurement units

#### Transaction Tables

##### Procurement Module
- `purchase_orders` - Purchase order headers
- `purchase_order_items` - Purchase order line items
- `purchase_receipts` - Goods receipt headers (record-only)
- `purchase_receipt_items` - Goods receipt line items
- `quality_controls` - Quality control records (linked to PO items)
- `purchase_returns` - Purchase return headers
- `purchase_return_items` - Purchase return line items
- `purchase_invoices` - Purchase invoice headers

##### Sales Module
- `sales_orders` - Sales order headers
- `sales_order_items` - Sales order line items
- `delivery_orders` - Delivery order headers
- `delivery_order_items` - Delivery order line items
- `sales_invoices` - Sales invoice headers
- `customer_receipts` - Customer payment records

##### Inventory Module
- `inventory_stocks` - Current stock levels by warehouse
- `stock_movements` - Stock movement history
- `stock_adjustments` - Manual stock adjustments
- `stock_reservations` - Reserved stock for orders
- `stock_opnames` - Stock taking records

##### Manufacturing Module
- `production_plans` - Production planning
- `bills_of_material` - Product recipes with multi-level support
- `manufacturing_orders` - Production orders
- `material_issues` - Material issuance records
- `material_issue_items` - Material issue line items

##### Financial Module
- `journal_entries` - Accounting journal entries
- `account_balances` - Account balances by period
- `coa_balances` - Chart of account balances

### Key Relationships

#### Purchase Flow Relationships
```
PurchaseOrder (1) → (many) PurchaseOrderItem
PurchaseOrderItem (1) → (many) PurchaseReceiptItem
PurchaseOrderItem (1) → (1) QualityControl (NEW: QC from PO Item)
PurchaseReceipt (1) → (many) PurchaseReceiptItem (record-only)
QualityControl (1) → (1) PurchaseReturn
PurchaseReturn (1) → (many) PurchaseReturnItem
```

#### Sales Flow Relationships
```
SalesOrder (1) → (many) SalesOrderItem
SalesOrderItem (1) → (many) DeliveryOrderItem
DeliveryOrderItem (1) → (1) SalesInvoice
SalesInvoice (1) → (many) CustomerReceipt
```

#### Inventory Relationships
```
Product (1) → (many) InventoryStock
Warehouse (1) → (many) InventoryStock
InventoryStock (1) → (many) StockMovement
Product (1) → (many) StockReservation
```

#### Manufacturing Relationships
```
ProductionPlan (1) → (many) ManufacturingOrder
BillOfMaterial (1) → (many) BillOfMaterialItem
ManufacturingOrder (1) → (many) MaterialIssue
ManufacturingOrder (1) → (1) QualityControl
```

## Business Process Flows

### 1. Complete Purchase to Pay Cycle

#### Step 1: Purchase Order Creation
1. User creates Purchase Order (PO) with line items
2. PO requires approval based on company policy and amount
3. Approved PO becomes active for receiving goods

#### Step 2: Goods Receipt (Record-Only)
1. Supplier delivers goods to warehouse
2. Warehouse staff creates Purchase Receipt
3. Receipt records actual quantities received (accepted/rejected)
4. Receipt serves as audit trail but doesn't trigger QC automatically

#### Step 3: Quality Control (From PO Item)
1. QC is initiated from Purchase Order Item (not receipt)
2. QC inspects goods for quality standards
3. Passed quantities go to inventory
4. Rejected quantities trigger supplier return process

#### Step 4: Inventory Posting
1. System automatically posts passed goods to inventory
2. Creates stock movements with proper costing
3. Updates inventory balances by warehouse
4. Generates accounting journal entries

#### Step 5: Invoice Processing & Payment
1. Supplier sends invoice matching receipt
2. System validates 3-way match (PO-Receipt-Invoice)
3. Processes payment to supplier
4. Updates accounts payable

### 2. Order to Cash Cycle

#### Step 1: Sales Order Processing
1. Customer places order via sales team
2. Sales team creates Sales Order with line items
3. System checks inventory availability
4. Reserves stock if available, creates backorder if not

#### Step 2: Order Fulfillment
1. Warehouse picks and packs goods based on reservations
2. Creates Delivery Order with actual shipped quantities
3. Updates stock reservations and inventory
4. Ships goods to customer with tracking

#### Step 3: Invoicing & Accounting
1. System generates Sales Invoice linked to Delivery Order
2. Creates automatic accounting entries (revenue, COGS, tax)
3. Sends invoice to customer
4. Updates accounts receivable

#### Step 4: Payment Collection
1. Customer makes payment (full or partial)
2. Records Customer Receipt with payment details
3. Updates accounts receivable balances
4. Closes invoice when fully paid

### 3. Manufacturing Process Flow

#### Step 1: Production Planning
1. Create Production Plan with target quantities
2. Define production schedule and timelines
3. Allocate resources and materials

#### Step 2: Manufacturing Execution
1. Create Manufacturing Order from production plan
2. Issue materials from inventory using BOM
3. Record production progress and labor

#### Step 3: Quality Control
1. Inspect finished goods against quality standards
2. Record pass/fail results
3. Handle rejected products appropriately

#### Step 4: Finished Goods Posting
1. Post completed goods to inventory
2. Calculate and record production costs
3. Update manufacturing order status

## Security & Access Control

### Role-Based Access Control (RBAC)
- **Super Admin**: Full system access across all companies
- **Company Admin**: Company-wide access within their organization
- **Branch Manager**: Branch-level access with approval rights
- **Department Manager**: Department-specific access
- **Standard User**: Limited access based on assigned permissions

### Key Permissions
- Create/Edit/Delete business records
- Approve transactions (PO, payments, etc.)
- View financial reports
- Access specific modules
- Branch/company-specific data access

### Security Features
- Password hashing with bcrypt
- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- XSS protection in Filament forms
- Comprehensive audit logging
- Soft deletes for data safety
- Session management and timeouts

## Integration Points

### External Systems Integration
- **Payment Gateways** - Online payment processing
- **Shipping APIs** - Delivery tracking and updates
- **Email Services** - Automated notifications
- **SMS Services** - Critical alerts
- **Barcode/QR Scanners** - Inventory management
- **IoT Sensors** - Manufacturing monitoring

### API Architecture
- RESTful API endpoints for mobile applications
- Webhook support for real-time integrations
- OAuth 2.0 authentication for external access
- Rate limiting and API versioning
- Comprehensive API documentation

## Performance & Scalability

### Database Optimization
- Strategic indexing on frequently queried columns
- Query optimization with eager loading
- Database connection pooling
- Read/write database separation

### Caching Strategy
- Redis for session and general caching
- Database query result caching
- Configuration caching
- View caching for improved response times

### Queue Processing
- Background job processing for heavy operations
- Email sending queues
- Report generation queues
- Inventory synchronization queues

### Monitoring & Alerting
- Application performance metrics
- Error tracking and alerting
- System health dashboards
- Automated alerting system

## Testing Strategy

### Test Coverage Areas
- **Unit Tests**: Individual model methods and calculations
- **Feature Tests**: Complete business process workflows
- **Integration Tests**: Module interactions and data flow
- **API Tests**: Endpoint functionality and responses
- **E2E Tests**: Complete user journeys via Playwright

### Test Statistics
- 50+ test classes covering all major functionality
- Business logic validation tests
- Data integrity and relationship tests
- Security and permission tests
- Performance and edge case tests

## Deployment & Maintenance

### Environment Management
- **Development**: Local development environment
- **Staging**: Pre-production testing environment
- **Production**: Live system environment
- **CI/CD Pipeline**: Automated testing and deployment

### Backup & Recovery
- Automated daily database backups
- File system backups for uploads
- Point-in-time recovery capabilities
- Disaster recovery procedures
- Data retention policies (7 years financial data)

### System Monitoring
- Application performance metrics
- Error tracking and resolution
- User activity monitoring
- System health dashboards
- **Automated alerting system**

## Recent Architecture Changes

### Quality Control Refactoring (March 2026)
**Problem**: QC was previously created from Purchase Receipts, making receipts non-record-only.

**Solution**:
- Moved QC creation to originate from Purchase Order Items
- Purchase Receipts now serve as pure audit records
- Updated all related tests and UI components
- Maintained backward compatibility with deprecation warnings

**Impact**:
- Cleaner separation of concerns
- More logical business flow (QC from order, not receipt)
- Simplified receipt processing
- Better audit trail integrity

## Future Roadmap

### Planned Enhancements
- **Mobile Application**: Native iOS/Android apps for field operations
- **Advanced Analytics**: AI-powered forecasting and insights
- **IoT Integration**: Real-time manufacturing monitoring
- **Multi-Company Support**: Single instance, multiple companies
- **Workflow Automation**: Advanced approval workflows
- **API Marketplace**: Third-party integrations

### Technology Upgrades
- Laravel 12.x migration path
- PHP 8.3+ support and optimization
- Real-time notifications with WebSockets
- Microservices architecture evaluation
- Cloud-native deployment (AWS/Azure)

---

## References

For detailed documentation on specific modules, refer to:

- [Application Usage Guide](APPLICATION_USAGE_GUIDE.md)
- [Data Master Structure](DATA_MASTER_STRUCTURE.md)
- [Purchase Flow Documentation](PURCHASE_FLOW_DOCUMENTATION.md)
- [Sales Order Flow Documentation](SALES_ORDER_FLOW_DOCUMENTATION.md)
- [Manufacturing Flow Documentation](MANUFACTURING_FLOW_DOCUMENTATION.md)
- [Stock Reservation System](STOCK_RESERVATION_SYSTEM.md)
- [Dynamic COA System](DYNAMIC_COA_SYSTEM.md)
- [Income Statement Summary](INCOME_STATEMENT_SUMMARY.md)

*This documentation is continuously updated as the system evolves. Last updated: March 2026*