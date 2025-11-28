<?php

namespace Database\Seeders;

use App\Models\DeliveryOrder;
use App\Models\ProductCategory;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            // Core System Setup
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            
            // Master Data
            // CurrencySeeder::class, // REMOVED: Handled by FinanceSeeder
            UnitOfMeasureSeeder::class,
            CabangSeeder::class, // ✅ TAMBAHKAN: Data cabang untuk multi-cabang
            ChartOfAccountSeeder::class, // ✅ TAMBAHKAN: COA lengkap
            MasterDataSeeder::class, // ✅ TAMBAHKAN: Data master lengkap
            
            // Business Entities
            ProductCategorySeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            ProductSeeder::class,
            BillOfMaterialSeeder::class,
            
            // Inventory & Warehouse
            WarehouseSeeder::class,
            RakSeeder::class,
            InventoryStockSeeder::class, // ✅ TAMBAHKAN: Stok inventory
            StockAdjustmentSeeder::class, // ✅ TAMBAHKAN: Stock adjustments
            StockOpnameSeeder::class, // ✅ TAMBAHKAN: Stock opname
            
            // Sales & Purchase Flow
            OrderRequestSeeder::class, // ✅ TAMBAHKAN: Purchase requests
            OrderRequestItemSeeder::class, // ✅ TAMBAHKAN: Purchase request items
            QuotationSeeder::class, // ✅ TAMBAHKAN: Quotations
            QuotationItemSeeder::class, // ✅ TAMBAHKAN: Quotation items
            PurchaseOrderSeeder::class, // ✅ TAMBAHKAN: Purchase orders
            PurchaseOrderItemSeeder::class, // ✅ TAMBAHKAN: Purchase order items
            SaleOrderSeeder::class, // ✅ TAMBAHKAN: Sales orders
            SaleOrderItemSeeder::class, // ✅ TAMBAHKAN: Sales order items
            CompleteSalesFlowSeeder::class, // ✅ TAMBAHKAN: Complete sales flow with approved quotation, SO, and DO
            
            // Operations
            DeliveryOrderSeeder::class,
            SuratJalanSeeder::class,
            ReturnProductSeeder::class, // ✅ TAMBAHKAN: Product returns
            ReturnProductItemSeeder::class, // ✅ TAMBAHKAN: Return items
            StockMovementSeeder::class, // ✅ TAMBAHKAN: Stock movements
            
            // Finance
            CashBankDemoSeeder::class,
            BankReconciliationDemoSeeder::class,
            FinanceSeeder::class,
            OtherSaleSeeder::class, // ✅ TAMBAHKAN: Other sales (building rental, etc.)
        ]);
    }
}
