<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

test('returns a successful response', function () {
    $user = User::factory()->create();
    
    // Assign required permissions for Filament navigation
    $permissions = [
        'view any account payable',
        'view any account receivable', 
        'view any cash bank',
        'view any journal entry',
        'view any voucher request',
        'view any asset',
        'view any depreciation',
        'view any balance sheet',
        'view any income statement',
        'view any cash flow',
        'view any ageing schedule',
        'view any bill of material',
        'view any cabang',
        'view any chart of account',
        'view any currency',
        'view any customer receipt',
        'view any customer',
        'view any delivery order',
        'view any deposit',
        'view any driver',
        'view any inventory stock',
        'view any manufacturing order',
        'view any order request',
        'view any permission',
        'view any product category',
        'view any product',
        'view any production',
        'view any invoice',
        'view any purchase order',
        'view any purchase receipt item',
        'view any purchase receipt',
        'view any purchase return',
        'view any quality control',
        'view any quotation',
        'view any rak',
        'view any stock movement',
        'view any stock transfer',
        'view any supplier',
        'view any return product',
        'view any sales order',
        'view any surat jalan',
        'view any tax setting',
        'view any unit of measure',
        'view any user',
        'view any vehicle',
        'view any vendor payment',
        'view any warehouse confirmation',
        'view any warehouse',
        'view any role',
    ];
    
    foreach ($permissions as $permissionName) {
        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }
    
    $this->actingAs($user);

    $response = $this->get('/');

    // After login, the application redirects to Filament admin dashboard
    $response->assertRedirect(route('filament.admin.pages.my-dashboard'));
});