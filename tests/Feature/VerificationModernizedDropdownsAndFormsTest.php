<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class VerificationModernizedDropdownsAndFormsTest extends TestCase
{
    protected function getAdminUser(): User
    {
        return User::first() ?? User::factory()->create();
    }

    /** @test */
    public function order_request_index_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('pembelian.or.index'));

        $response->assertStatus(200);
        $response->assertSee('cabang_id', false);
    }

    /** @test */
    public function order_request_create_renders_with_dynamic_repeater_combobox()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('pembelian.or.create'));

        $response->assertStatus(200);
        $response->assertSee('getSupplierLabel', false);
        $response->assertSee('getCabangLabel', false);
    }

    /** @test */
    public function manufaktur_spk_create_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('manufaktur.spk.create'));

        $response->assertStatus(200);
        $response->assertSee('bill_of_material_id', false);
        $response->assertSee('warehouse_id', false);
    }

    /** @test */
    public function manufaktur_bom_create_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('manufaktur.bom.create'));

        $response->assertStatus(200);
        $response->assertSee('product_id', false);
        $response->assertSee('cabang_id', false);
    }

    /** @test */
    public function manufaktur_production_create_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('manufaktur.production.create'));

        $response->assertStatus(200);
        $response->assertSee('manufacturing_order_id', false);
    }

    /** @test */
    public function manufaktur_material_issue_create_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('manufaktur.material-issue.create'));

        $response->assertStatus(200);
        $response->assertSee('manufacturing_order_id', false);
        $response->assertSee('warehouse_id', false);
    }

    /** @test */
    public function manufaktur_qc_create_renders_with_searchable_select()
    {
        $user = $this->getAdminUser();
        $response = $this->actingAs($user)->get(route('manufaktur.qc.create'));

        $response->assertStatus(200);
        $response->assertSee('from_model_id', false);
    }

    /** @test */
    public function toolbar_filters_in_purchasing_render_cleanly()
    {
        $user = $this->getAdminUser();

        // PO
        $resPo = $this->actingAs($user)->get(route('pembelian.po.index'));
        $resPo->assertStatus(200);
        $resPo->assertSee('supplier_id', false);
        $resPo->assertSee('cabang_id', false);

        // Invoice
        $resInv = $this->actingAs($user)->get(route('pembelian.invoices.index'));
        $resInv->assertStatus(200);
        $resInv->assertSee('supplier_id', false);

        // Payment
        $resPay = $this->actingAs($user)->get(route('pembelian.payments.index'));
        $resPay->assertStatus(200);
        $resPay->assertSee('supplier_id', false);

        // Retur
        $resRet = $this->actingAs($user)->get(route('pembelian.returns.index'));
        $resRet->assertStatus(200);
        $resRet->assertSee('supplier_id', false);
    }

    /** @test */
    public function toolbar_filters_in_sales_render_cleanly()
    {
        $user = $this->getAdminUser();

        // Penawaran
        $resQo = $this->actingAs($user)->get(route('penjualan.quotations.index'));
        $resQo->assertStatus(200);
        $resQo->assertSee('customer_id', false);

        // SO
        $resSo = $this->actingAs($user)->get(route('penjualan.so.index'));
        $resSo->assertStatus(200);
        $resSo->assertSee('sales', false);

        // DO
        $resDo = $this->actingAs($user)->get(route('penjualan.do.index'));
        $resDo->assertStatus(200);
        $resDo->assertSee('cabang_id', false);

        // SJ
        $resSj = $this->actingAs($user)->get(route('penjualan.sj.index'));
        $resSj->assertStatus(200);
        $resSj->assertSee('cabang_id', false);

        // Invoices
        $resInv = $this->actingAs($user)->get(route('penjualan.invoices.index'));
        $resInv->assertStatus(200);
        $resInv->assertSee('cabang_id', false);

        // Receipts
        $resRec = $this->actingAs($user)->get(route('penjualan.receipts.index'));
        $resRec->assertStatus(200);
        $resRec->assertSee('cabang_id', false);

        // Returns
        $resRet = $this->actingAs($user)->get(route('penjualan.returns.index'));
        $resRet->assertStatus(200);
        $resRet->assertSee('cabang_id', false);

        // Schedules
        $resSch = $this->actingAs($user)->get(route('penjualan.schedules.index'));
        $resSch->assertStatus(200);
        $resSch->assertSee('driver_id', false);
    }

    /** @test */
    public function toolbar_filters_in_gudang_and_finance_and_settings_render_cleanly()
    {
        $user = $this->getAdminUser();

        // Gudang Stok
        $resStok = $this->actingAs($user)->get(route('gudang.stok.index'));
        $resStok->assertStatus(200);
        $resStok->assertSee('warehouse_id', false);

        // Gudang Transfer
        $resTransfer = $this->actingAs($user)->get(route('gudang.transfer.index'));
        $resTransfer->assertStatus(200);
        $resTransfer->assertSee('from_warehouse_id', false);

        // Gudang Opname
        $resOpname = $this->actingAs($user)->get(route('gudang.opname.index'));
        $resOpname->assertStatus(200);
        $resOpname->assertSee('warehouse_id', false);

        // Gudang Penerimaan
        $resPenerimaan = $this->actingAs($user)->get(route('gudang.penerimaan.index'));
        $resPenerimaan->assertStatus(200);
        $resPenerimaan->assertSee('warehouse_id', false);

        // Jurnal Umum
        $resJournal = $this->actingAs($user)->get(route('keuangan.journal.index'));
        $resJournal->assertStatus(200);
        $resJournal->assertSee('coa_id', false);

        // Manajemen Aset
        $resAsset = $this->actingAs($user)->get(route('keuangan.asset.index'));
        $resAsset->assertStatus(200);
        $resAsset->assertSee('cabang_id', false);

        // Master Produk
        $resProduct = $this->actingAs($user)->get(route('master.products.index'));
        $resProduct->assertStatus(200);
        $resProduct->assertSee('category_id', false);

        // Master Supplier
        $resSupplier = $this->actingAs($user)->get(route('master.suppliers.index'));
        $resSupplier->assertStatus(200);
        $resSupplier->assertSee('cabang_id', false);

        // Pengaturan Pengguna
        $resUsers = $this->actingAs($user)->get(route('settings.users.index'));
        $resUsers->assertStatus(200);
        $resUsers->assertSee('cabang_id', false);

        // Master Customer
        $resCustomer = $this->actingAs($user)->get(route('master.customers.index'));
        $resCustomer->assertStatus(200);
        $resCustomer->assertSee('cabang_id', false);

        // Master Gudang
        $resGudang = $this->actingAs($user)->get(route('master.gudang.index'));
        $resGudang->assertStatus(200);
        $resGudang->assertSee('cabang_id', false);

        // Master Kendaraan
        $resKendaraan = $this->actingAs($user)->get(route('master.kendaraan.index'));
        $resKendaraan->assertStatus(200);
        $resKendaraan->assertSee('cabang_id', false);

        // Master Driver
        $resDriver = $this->actingAs($user)->get(route('master.driver.index'));
        $resDriver->assertStatus(200);
        $resDriver->assertSee('cabang_id', false);
    }
}
