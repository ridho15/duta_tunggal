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
}