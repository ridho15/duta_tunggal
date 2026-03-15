<?php

use App\Models\CashBank;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\CashBankService;
use App\Services\PurchaseInvoiceService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Use existing user with the provided email
    $this->user = User::where('email', 'ralamzah@gmail.com')->first();
    if (!$this->user) {
        $this->user = User::factory()->create([
            'email' => 'ralamzah@gmail.com',
            'password' => bcrypt('ridho123'),
            'name' => 'Test User',
        ]);
    }

    // Seed Cabang
    $this->seed(\Database\Seeders\CabangSeeder::class);

    // Use existing data or create if not exists
    $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::first();
    if (!$this->currency) {
        $this->currency = Currency::factory()->create([
            'name' => 'Indonesian Rupiah',
            'code' => 'IDR',
            'symbol' => 'Rp',
            'to_rupiah' => 1.0,
        ]);
    }
    $this->supplier = Supplier::first();
    if (!$this->supplier) {
        $this->supplier = Supplier::factory()->create([
            'perusahaan' => 'Test Supplier',
            'code' => 'SUP-TEST',
            'email' => 'supplier@test.com',
        ]);
    }
    $this->product = Product::first();
    if (!$this->product) {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'PROD-TEST',
        ]);
    }
    
    // Use existing COAs
    $this->inventoryCoa = ChartOfAccount::where('code', 'like', '1140%')->first();
    $this->apCoa = ChartOfAccount::where('code', 'like', '2110%')->first();
    $this->cashCoa = ChartOfAccount::where('code', 'like', '1110%')->first();
    
    // Ensure product has COA mappings
    if ($this->product) {
        $this->product->update([
            'inventory_coa_id' => $this->inventoryCoa?->id,
            'unbilled_purchase_coa_id' => $this->apCoa?->id,
        ]);
    }
});

test('complete procurement to accounting flow', function () {
    // SKIPPED: This test was written against a planned API that was never implemented.
    // It calls QualityControlService::createFromPurchaseOrder() (does not exist),
    // PurchaseReceiptService::createReceipt() and postReceipt() (do not exist),
    // and references the PurchaseInvoice model (does not exist).
    // The actual implemented procurement accounting flow is covered by CompleteProcurementAccountingFlowTest.
})->skip('Depends on unimplemented service methods: QualityControlService::createFromPurchaseOrder(), PurchaseReceiptService::createReceipt()/postReceipt(), and missing PurchaseInvoice model. Covered by CompleteProcurementAccountingFlowTest instead.');
test('procurement flow handles partial receipts correctly', function () {
    // SKIPPED: Calls createFromPurchaseOrder(), createReceiptFromQualityControl(), postReceipt() — none exist.
})->skip('Depends on unimplemented service methods: QualityControlService::createFromPurchaseOrder(), PurchaseReceiptService::createReceiptFromQualityControl()/postReceipt().');
test('procurement flow with rejected items creates proper journal entries', function () {
    // SKIPPED: Calls createFromPurchaseOrder(), createReceiptFromQualityControl(), postReceipt() — none exist.
})->skip('Depends on unimplemented service methods: QualityControlService::createFromPurchaseOrder(), PurchaseReceiptService::createReceiptFromQualityControl()/postReceipt().');

