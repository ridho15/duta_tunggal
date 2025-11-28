# Role → Permission Map

This document describes the recommended mapping between roles and permission groups (based on `HelperController::listPermission()`). Permissions are created as `<action> <resource>` by `PermissionSeeder`.

Guidelines:
- Owner and Super Admin: full access (all permissions).
- Auditor: read-only access (granted `view any <resource>` for many resources).
- For other roles: all actions for the listed resources are assigned.

## Mapping (role => resource groups)

- Owner
  - All permissions

- Super Admin
  - All permissions

- Admin
  - user, role, permission, currency, chart of account, tax setting, cabang

- Finance Manager
  - account payable, account receivable, vendor payment, vendor payment detail, customer receipt, customer receipt item, invoice, deposit, deposit log, ageing schedule, voucher request

- Admin Keuangan
  - account payable, vendor payment, deposit, invoice, voucher request

- Accounting
  - chart of account, account payable, account receivable, deposit, invoice, ageing schedule

- Purchasing
  - purchase order, purchase order item, purchase receipt, purchase receipt item, purchase order biaya, purchase order currency, purchase return

- Purchasing Manager
  - purchase order, purchase order item, purchase receipt, vendor payment, purchase return, purchase order biaya

- Inventory Manager
  - warehouse, warehouse confirmation, inventory stock, stock movement, stock transfer, stock transfer item, product, product category, rak, unit of measure, product unit conversion, quality control

- Admin Inventory
  - warehouse, inventory stock, stock movement, product

- Warehouse Staff
  - warehouse, warehouse confirmation, stock transfer, stock transfer item, inventory stock

- Checker
  - warehouse confirmation, quality control, inventory stock

- Sales Manager
  - sales order, sales order item, quotation, quotation item, invoice, customer, customer receipt

- Sales
  - sales order, sales order item, quotation, customer

- Kasir
  - customer receipt, customer receipt item, invoice

- Customer Service
  - customer, quotation, sales order, delivery order, surat jalan

- Delivery Driver
  - delivery order, delivery order item, vehicle, surat jalan

- Auditor
  - view any for all resources (read-only)

- IT Support
  - user, role, permission, tax setting, currency


## How this is implemented
- `RoleSeeder` now uses the mapping to collect the corresponding `<action> <resource>` permission names from `HelperController::listPermission()` and syncs them to each role.
- `Auditor` role receives `view any <resource>` permissions where available.

## Notes
- The seeder assigns only permissions that exist in the DB (so run `PermissionSeeder` first).
- If you want finer-grained control (e.g., disallow `delete` for some roles), update the mapping in `RoleSeeder` to select specific actions instead of assigning all actions for a resource.

