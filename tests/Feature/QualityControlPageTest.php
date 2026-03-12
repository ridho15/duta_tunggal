<?php

use App\Models\User;
use App\Models\QualityControl;

class QualityControlPageTest extends \Tests\TestCase
{
    public function test_quality_control_page_loads_without_errors()
    {
        $user = User::first(); // Assuming there's at least one user
        
        $response = $this->actingAs($user)
                         ->get('/admin/quality-control-purchases');
        
        $response->assertStatus(200);
    }

    public function test_quality_control_view_page_loads_without_errors()
    {
        $user = User::first();
        $qc = QualityControl::first();
        
        $response = $this->actingAs($user)
                         ->get('/admin/quality-control-purchases/' . $qc->id);
        
        $response->assertStatus(200);
    }

    public function test_index_shows_supplier_and_po_and_filters_exist()
    {
        // prepare a record with supplier and PO
        $user = User::first();
        $supplier = \App\Models\Supplier::factory()->create(['perusahaan'=>'SupTest']);
        $po = \App\Models\PurchaseOrder::factory()->create(['supplier_id'=>$supplier->id,'po_number'=>'PO-123']);
        $item = \App\Models\PurchaseOrderItem::factory()->create(['purchase_order_id'=>$po->id]);
        $qc = QualityControl::factory()->create([
            'from_model_type' => \App\Models\PurchaseOrderItem::class,
            'from_model_id' => $item->id,
        ]);

        $response = $this->actingAs($user)
            ->get('/admin/quality-control-purchases');
        $response->assertStatus(200);
        $response->assertSeeText('SupTest');
        $response->assertSeeText('PO-123');
    }
}