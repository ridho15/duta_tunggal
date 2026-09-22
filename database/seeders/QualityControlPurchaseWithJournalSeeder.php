<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cabang;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\User;
use App\Models\Currency;
use App\Services\PurchaseReturnService;
use App\Services\QualityControlService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class QualityControlPurchaseWithJournalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks for this seeder
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $user = User::first() ?? User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
            Auth::login($user);
            $this->createQualityControlPurchaseWithJournal($user);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    private function createQualityControlPurchaseWithJournal($user)
    {
        // Get or create required data
        $cabang = Cabang::first() ?? Cabang::factory()->create([
            'kode' => 'MAIN',
            'nama' => 'Cabang Utama',
            'alamat' => 'Jl. Utama No. 1',
        ]);

        $supplier = Supplier::first() ?? Supplier::factory()->create([
            'code' => 'SUP001',
            'name' => 'PT Supplier Test',
            'perusahaan' => 'PT Supplier Test',
            'address' => 'Jl. Supplier No. 1',
            'phone' => '021-12345678',
            'email' => 'supplier@test.com',
            'cabang_id' => $cabang->id,
        ]);

        $warehouse = Warehouse::first() ?? Warehouse::factory()->create([
            'name' => 'Gudang Utama',
            'kode' => 'WH001',
            'cabang_id' => $cabang->id,
        ]);

        $rak = Rak::first() ?? Rak::factory()->create([
            'name' => 'Rak A1',
            'warehouse_id' => $warehouse->id,
        ]);

        $product = Product::where('name', 'Test Product QC')->first();
        if (!$product) {
            $product = Product::factory()->forCabang($cabang)->create([
                'name' => 'Test Product QC',
                'sku' => 'TEST-QC-001',
                'description' => 'Product for Quality Control Testing',
                'uom_id' => 1, // Assuming UOM exists
            ]);
        }

        $currency = Currency::where('code', 'IDR')->first() ?? Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1,
        ]);

        // Create Order Request (OR)
        $orderRequest = \App\Models\OrderRequest::create([
            'request_number' => 'OR-QC-' . now()->format('Ymd-His'),
            'request_date' => now(),
            'note' => 'Order Request for QC Testing',
            'created_by' => $user->id,
            'currency_id' => $currency->id,
        ]);

        // Create Order Request Item
        \App\Models\OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'currency_id' => $currency->id,
            'quantity' => 100,
            'unit_price' => 10000,
        ]);

        // Create Purchase Order
        $po = PurchaseOrder::create([
            'po_number' => 'PO-QC-' . now()->format('Ymd-His'),
            'supplier_id' => $supplier->id,
            'cabang_id' => $cabang->id,
            'warehouse_id' => $warehouse->id,
            'tempo_hutang' => 30, // 30 days payment term
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'created_by' => $user->id,
            'approved_by' => $user->id,
            'date_approved' => now(),
            'total_amount' => 1110000, // Will be updated after items
            'refer_model_type' => 'App\\Models\\OrderRequest',
            'refer_model_id' => $orderRequest->id,
        ]);

        // Create Purchase Order Item
        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'unit_price' => 10000, // 10,000 IDR per unit
            'currency_id' => $currency->id,
        ]);

        // Update PO total
        $po->update([
            'total_amount' => 1110000,
        ]);

        // Create Purchase Receipt
        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'RN-QC-' . now()->format('Ymd-His'),
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'received_by' => $user->id,
            'currency_id' => $currency->id,
            'status' => 'completed',
            'cabang_id' => $cabang->id,
        ]);

        // Create Quality Control from Purchase Order Item (current flow)
        $qcService = app(QualityControlService::class);
        $qualityControl = $qcService->createQCFromPurchaseOrderItem($poItem, [
            'passed_quantity' => 95, // 95 passed, 5 rejected
            'rejected_quantity' => 5,
            'inspected_by' => $user->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
        ]);

        // For rejected purchase QC, create purchase return first
        $purchaseReturnService = app(PurchaseReturnService::class);
        $purchaseReturn = $purchaseReturnService->createFromQualityControl(
            $qualityControl,
            PurchaseReturn::QC_ACTION_WAIT_NEXT_DELIVERY
        );

        // Complete Quality Control to create journal entries automatically
        $qcService->completeQualityControl($qualityControl, [
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
        ]);

        $this->command->info('Quality Control Purchase with Journal Entries created successfully!');
        $this->command->info('QC Number: ' . $qualityControl->qc_number);
        $this->command->info('PO Number: ' . $po->po_number);
        $this->command->info('Receipt Number: ' . $receipt->receipt_number);
        $this->command->info('Purchase Return Number: ' . $purchaseReturn->nota_retur);
        $this->command->info('Product: ' . $product->name . ' (' . $product->sku . ')');
        $this->command->info('Passed Quantity: 95, Rejected Quantity: 5');
        $this->command->info('Total Journal Entries: ' . $qualityControl->journalEntries()->count());
    }
}
