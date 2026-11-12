<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemCheck;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

echo "=== [RESET DATA SIKLUS G: HULU PENGADAAN, LOGISTIK LANJUTAN, NOTA KREDIT & SIKLUS AKHIR ASET] ===\n";

DB::transaction(function () {
    $today = now()->toDateString();

    // 1. Hapus Order Requests test Siklus G
    $orIds = OrderRequest::withTrashed()
        ->where('request_number', 'like', 'OR-G-%')
        ->orWhere('purpose', 'Pengadaan Monitor LED Cadangan Siklus G')
        ->pluck('id')->all();
    if (!empty($orIds)) {
        OrderRequestItem::withTrashed()->whereIn('order_request_id', $orIds)->forceDelete();
        OrderRequest::withTrashed()->whereIn('id', $orIds)->forceDelete();
    }
    echo "Dihapus: " . count($orIds) . " OrderRequest (OR-G-%)\n";

    // 2. Hapus Credit Notes & Invoice test Siklus G
    $invIds = Invoice::withTrashed()->where('invoice_number', 'like', 'INV-G-%')->pluck('id')->all();
    $cnIds = CreditNote::withTrashed()
        ->where('credit_note_number', 'like', 'CN-G-%')
        ->when(!empty($invIds), fn($q) => $q->orWhereIn('invoice_id', $invIds))
        ->pluck('id')->all();

    if (!empty($cnIds)) {
        JournalEntry::withTrashed()->where('source_type', CreditNote::class)->whereIn('source_id', $cnIds)->forceDelete();
        CreditNoteItem::whereIn('credit_note_id', $cnIds)->delete();
        CreditNote::withTrashed()->whereIn('id', $cnIds)->forceDelete();
    }
    echo "Dihapus: " . count($cnIds) . " CreditNote (CN-G-% / INV-G-%)\n";

    if (!empty($invIds)) {
        JournalEntry::withTrashed()->where('source_type', Invoice::class)->whereIn('source_id', $invIds)->forceDelete();
        AccountReceivable::withTrashed()->whereIn('invoice_id', $invIds)->forceDelete();
        InvoiceItem::withTrashed()->whereIn('invoice_id', $invIds)->forceDelete();
        Invoice::withTrashed()->whereIn('id', $invIds)->forceDelete();
    }
    echo "Dihapus: " . count($invIds) . " Invoice (INV-G-%)\n";

    // 3. Hapus Delivery Schedules, Surat Jalan, Delivery Orders, Sale Orders Siklus G
    $dsIds = DeliverySchedule::withTrashed()->where('schedule_number', 'like', 'DS-G-%')->pluck('id')->all();
    $sjIds = SuratJalan::withTrashed()->where('sj_number', 'like', 'SJ-G-%')->pluck('id')->all();
    $doIds = DeliveryOrder::withTrashed()->where('do_number', 'like', 'DO-G-%')->pluck('id')->all();
    $soIds = SaleOrder::withTrashed()->where('so_number', 'like', 'SO-G-%')->pluck('id')->all();

    if (!empty($doIds)) {
        $linkedDsIds = DB::table('delivery_schedule_delivery_orders')->whereIn('delivery_order_id', $doIds)->pluck('delivery_schedule_id')->all();
        $dsIds = array_values(array_unique(array_merge($dsIds, $linkedDsIds)));
    }
    if (!empty($dsIds)) {
        DB::table('delivery_schedule_delivery_orders')->whereIn('delivery_schedule_id', $dsIds)->delete();
        DB::table('delivery_schedule_surat_jalans')->whereIn('delivery_schedule_id', $dsIds)->delete();
        DeliverySchedule::withTrashed()->whereIn('id', $dsIds)->forceDelete();
    }
    if (!empty($sjIds)) {
        DB::table('surat_jalan_delivery_orders')->whereIn('surat_jalan_id', $sjIds)->delete();
        SuratJalan::withTrashed()->whereIn('id', $sjIds)->forceDelete();
    }
    if (!empty($doIds)) {
        JournalEntry::withTrashed()->where('source_type', DeliveryOrder::class)->whereIn('source_id', $doIds)->forceDelete();
        DB::table('surat_jalan_delivery_orders')->whereIn('delivery_order_id', $doIds)->delete();
        DB::table('delivery_sales_orders')->whereIn('delivery_order_id', $doIds)->delete();
        DB::table('warehouse_confirmations')->where('confirmable_type', DeliveryOrder::class)->whereIn('confirmable_id', $doIds)->delete();
        $doItemIds = DeliveryOrderItem::withTrashed()->whereIn('delivery_order_id', $doIds)->pluck('id')->all();
        if (!empty($doItemIds)) {
            DeliveryOrderItemCheck::whereIn('delivery_order_item_id', $doItemIds)->delete();
        }
        DeliveryOrderItem::withTrashed()->whereIn('delivery_order_id', $doIds)->forceDelete();
        DeliveryOrder::withTrashed()->whereIn('id', $doIds)->forceDelete();
    }
    if (!empty($soIds)) {
        SaleOrderItem::withTrashed()->whereIn('sale_order_id', $soIds)->forceDelete();
        SaleOrder::withTrashed()->whereIn('id', $soIds)->forceDelete();
    }
    echo "Dihapus: " . count($dsIds) . " DeliverySchedule, " . count($doIds) . " DO, " . count($soIds) . " SO\n";

    // 4. Hapus Asset Transfers, Disposals, dan Test Assets Siklus G
    $assetIds = Asset::withTrashed()->where('code', 'like', 'AST-G-%')->pluck('id')->all();
    if (!empty($assetIds)) {
        $trfIds = AssetTransfer::withTrashed()->whereIn('asset_id', $assetIds)->pluck('id')->all();
        $dispIds = AssetDisposal::withTrashed()->whereIn('asset_id', $assetIds)->pluck('id')->all();

        if (!empty($trfIds)) {
            JournalEntry::withTrashed()->where('source_type', AssetTransfer::class)->whereIn('source_id', $trfIds)->forceDelete();
            AssetTransfer::withTrashed()->whereIn('id', $trfIds)->forceDelete();
        }
        if (!empty($dispIds)) {
            JournalEntry::withTrashed()->where('source_type', AssetDisposal::class)->whereIn('source_id', $dispIds)->forceDelete();
            AssetDisposal::withTrashed()->whereIn('id', $dispIds)->forceDelete();
        }
        JournalEntry::withTrashed()->where('source_type', Asset::class)->whereIn('source_id', $assetIds)->forceDelete();
        AssetDepreciation::whereIn('asset_id', $assetIds)->delete();
        Asset::withTrashed()->whereIn('id', $assetIds)->forceDelete();
    }
    echo "Dihapus: " . count($assetIds) . " Asset (AST-G-%)\n";

    // 5. Seed Prasyarat Deterministik Siklus G
    $superUser = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first() ?? User::first();
    $rakId = Rak::where('warehouse_id', 1)->value('id') ?? Rak::first()?->id;

    // Pastikan stok Product 4 (PRD004) di Warehouse 1 cukup (minimal 50 unit)
    $stock = InventoryStock::firstOrCreate(
        ['product_id' => 4, 'warehouse_id' => 1],
        ['qty_available' => 100, 'qty_reserved' => 0, 'rak_id' => $rakId, 'qty_min' => 5]
    );
    if ((float) $stock->qty_available < 50) {
        $stock->update(['qty_available' => 100, 'qty_reserved' => 0]);
    }

    // 5A. Sale Order & Delivery Order siap cek Gudang (DO-G-2026-001) -> status 'approved'
    $so1 = SaleOrder::create([
        'customer_id' => 1,
        'so_number' => 'SO-G-2026-001',
        'order_date' => $today,
        'delivery_date' => $today,
        'status' => 'confirmed',
        'tempo_pembayaran' => 30,
        'total_amount' => 14000000,
        'shipping_cost' => 0,
        'currency_id' => 1,
        'exchange_rate' => 1,
        'cabang_id' => 1,
        'created_by' => $superUser->id,
    ]);
    $soItem1 = SaleOrderItem::create([
        'sale_order_id' => $so1->id,
        'product_id' => 4,
        'quantity' => 5,
        'delivered_quantity' => 0,
        'unit_price' => 2800000,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
        'currency_id' => 1,
        'warehouse_id' => 1,
        'rak_id' => $rakId,
    ]);

    $do1 = DeliveryOrder::create([
        'do_number' => 'DO-G-2026-001',
        'delivery_date' => $today,
        'warehouse_id' => 1,
        'cabang_id' => 1,
        'status' => 'approved',
        'notes' => 'Fixture logistik lanjutan Siklus G (siap dicek checker)',
        'created_by' => $superUser->id,
    ]);
    DB::table('delivery_sales_orders')->insert([
        'delivery_order_id' => $do1->id,
        'sales_order_id' => $so1->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DeliveryOrderItem::create([
        'delivery_order_id' => $do1->id,
        'sale_order_item_id' => $soItem1->id,
        'product_id' => 4,
        'quantity' => 5,
        'status' => 'confirmed',
    ]);
    echo "Dibuat fixture logistik: SO-G-2026-001 & DO-G-2026-001 (status: approved)\n";

    // 5B. Sales Invoice + AR untuk uji Nota Kredit (INV-G-2026-001)
    $so2 = SaleOrder::create([
        'customer_id' => 1,
        'so_number' => 'SO-G-2026-002',
        'order_date' => $today,
        'delivery_date' => $today,
        'status' => 'completed',
        'tempo_pembayaran' => 30,
        'total_amount' => 11100000,
        'shipping_cost' => 0,
        'currency_id' => 1,
        'exchange_rate' => 1,
        'cabang_id' => 1,
        'created_by' => $superUser->id,
    ]);
    $arCoaId = ChartOfAccount::where('code', '1120')->value('id');
    $revCoaId = ChartOfAccount::where('code', '4000')->value('id');
    $ppnOutCoaId = ChartOfAccount::where('code', '2120.06')->value('id');

    $inv1 = Invoice::withoutEvents(function () use ($so2, $today, $arCoaId, $revCoaId, $ppnOutCoaId) {
        return Invoice::create([
            'invoice_number' => 'INV-G-2026-001',
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so2->id,
            'currency_id' => 1,
            'exchange_rate' => 1,
            'invoice_date' => $today,
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000000,
            'dpp' => 10000000,
            'ppn_rate' => 11,
            'tax' => 1100000,
            'other_fee' => 0,
            'total' => 11100000,
            'tipe_pajak' => 'Exclude',
            'status' => 'unpaid',
            'customer_name' => 'PT Maju Bersama',
            'cabang_id' => 1,
            'ar_coa_id' => $arCoaId,
            'revenue_coa_id' => $revCoaId,
            'ppn_keluaran_coa_id' => $ppnOutCoaId,
        ]);
    });
    InvoiceItem::create([
        'invoice_id' => $inv1->id,
        'product_id' => 1,
        'quantity' => 10,
        'price' => 1000000,
        'discount' => 0,
        'gross_amount' => 10000000,
        'discount_amount' => 0,
        'tax_rate' => 11,
        'tax_amount' => 1100000,
        'subtotal' => 10000000,
        'total' => 11100000,
        'coa_id' => $revCoaId,
    ]);
    AccountReceivable::create([
        'invoice_id' => $inv1->id,
        'customer_id' => 1,
        'cabang_id' => 1,
        'currency_id' => 1,
        'exchange_rate' => 1,
        'total' => 11100000,
        'paid' => 0,
        'remaining' => 11100000,
        'total_original' => 11100000,
        'paid_original' => 0,
        'remaining_original' => 11100000,
        'status' => 'Belum Lunas',
        'created_by' => $superUser->id,
    ]);
    echo "Dibuat fixture Nota Kredit: INV-G-2026-001 (Total: 11.100.000, Sisa Piutang: 11.100.000)\n";

    // 5C. Assets untuk Mutasi Cabang (AST-G-2026-001) & Pelepasan Aset (AST-G-2026-002)
    $assetCoaId = ChartOfAccount::where('code', '1210.01')->value('id');
    $accumCoaId = ChartOfAccount::where('code', '1220.01')->value('id');
    $deprExpCoaId = ChartOfAccount::where('code', '6311')->value('id');

    Asset::withoutEvents(function () use ($today, $assetCoaId, $accumCoaId, $deprExpCoaId) {
        Asset::create([
            'code' => 'AST-G-2026-001',
            'name' => 'Forklift Listrik Gudang Siklus G',
            'purchase_date' => '2026-06-01',
            'usage_date' => '2026-06-01',
            'purchase_cost' => 60000000,
            'salvage_value' => 0,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $assetCoaId,
            'accumulated_depreciation_coa_id' => $accumCoaId,
            'depreciation_expense_coa_id' => $deprExpCoaId,
            'annual_depreciation' => 12000000,
            'monthly_depreciation' => 1000000,
            'accumulated_depreciation' => 0,
            'book_value' => 60000000,
            'status' => 'active',
            'cabang_id' => 1,
            'notes' => 'Fixture Mutasi Aset Siklus G (Cabang 1 -> Cabang 2)',
        ]);

        Asset::create([
            'code' => 'AST-G-2026-002',
            'name' => 'Mesin Generator Cadangan Siklus G',
            'purchase_date' => '2026-02-01',
            'usage_date' => '2026-02-01',
            'purchase_cost' => 50000000,
            'salvage_value' => 0,
            'useful_life_years' => 5,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => $assetCoaId,
            'accumulated_depreciation_coa_id' => $accumCoaId,
            'depreciation_expense_coa_id' => $deprExpCoaId,
            'annual_depreciation' => 10000000,
            'monthly_depreciation' => 833333.33,
            'accumulated_depreciation' => 10000000,
            'book_value' => 40000000,
            'status' => 'active',
            'cabang_id' => 1,
            'notes' => 'Fixture Disposal Sale Aset Siklus G (NBV 40jt -> Jual 45jt -> Gain 5jt)',
        ]);
    });
    echo "Dibuat fixture Aset Tetap: AST-G-2026-001 (active, Cabang 1) & AST-G-2026-002 (NBV 40.000.000)\n";
});

echo "=== [RESET DATA SIKLUS G SELESAI] ===\n";

