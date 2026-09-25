<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountingHubPage;
use App\Filament\Pages\DashboardHubPage;
use App\Filament\Pages\DeliveryHubPage;
use App\Filament\Pages\InventoryHubPage;
use App\Filament\Pages\ManufacturingHubPage;
use App\Filament\Pages\MasterDataHubPage;
use App\Filament\Pages\PurchaseHubPage;
use App\Filament\Pages\SalesHubPage;
use App\Filament\Pages\UserRolesManagementHubPage;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HubAccessAndMoneyFormattingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_super_admin_can_access_all_hubs(): void
    {
        $superAdmin = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first();
        if (! $superAdmin) {
            $superAdmin = User::factory()->make();
            $superAdmin->setRelation('roles', collect([new Role(['name' => 'Super Admin'])]));
        }

        $this->actingAs($superAdmin);

        $this->assertTrue(DashboardHubPage::canAccess());
        $this->assertTrue(SalesHubPage::canAccess());
        $this->assertTrue(PurchaseHubPage::canAccess());
        $this->assertTrue(DeliveryHubPage::canAccess());
        $this->assertTrue(AccountingHubPage::canAccess());
        $this->assertTrue(InventoryHubPage::canAccess());
        $this->assertTrue(MasterDataHubPage::canAccess());
        $this->assertTrue(UserRolesManagementHubPage::canAccess());
        $this->assertTrue(ManufacturingHubPage::canAccess());
    }

    public function test_owner_can_access_all_hubs(): void
    {
        $owner = User::whereHas('roles', fn($q) => $q->where('name', 'Owner'))->first();
        if (! $owner) {
            $owner = User::factory()->make();
            $owner->setRelation('roles', collect([new Role(['name' => 'Owner'])]));
        }

        $this->actingAs($owner);

        $this->assertTrue(DashboardHubPage::canAccess());
        $this->assertTrue(SalesHubPage::canAccess());
        $this->assertTrue(PurchaseHubPage::canAccess());
        $this->assertTrue(DeliveryHubPage::canAccess());
        $this->assertTrue(AccountingHubPage::canAccess());
        $this->assertTrue(InventoryHubPage::canAccess());
        $this->assertTrue(MasterDataHubPage::canAccess());
        $this->assertTrue(UserRolesManagementHubPage::canAccess());
        $this->assertTrue(ManufacturingHubPage::canAccess());
    }

    public function test_restricted_user_cannot_access_unauthorized_hubs(): void
    {
        $guestUser = User::factory()->make(['id' => 999999]);
        $guestUser->setRelation('roles', collect([]));
        $guestUser->setRelation('permissions', collect([]));

        $this->actingAs($guestUser);

        // Dashboard is accessible
        $this->assertTrue(DashboardHubPage::canAccess());

        // Restricted hubs should not be accessible
        $this->assertFalse(UserRolesManagementHubPage::canAccess(), 'UserRolesHub should be hidden for unauthorized user');
        $this->assertFalse(ManufacturingHubPage::canAccess(), 'ManufacturingHub should be hidden for unauthorized user');
        $this->assertFalse(AccountingHubPage::canAccess(), 'AccountingHub should be hidden for unauthorized user');
    }

    public function test_approval_rule_amount_fields_use_indonesian_money(): void
    {
        $filePath = app_path('Filament/Resources/ApprovalRuleResource.php');
        $content = file_get_contents($filePath);

        $this->assertStringContainsString("TextInput::make('above_amount')", $content);
        $this->assertStringContainsString("TextInput::make('up_to_amount')", $content);

        // Ensure above_amount has indonesianMoney and no numeric
        $abovePos = strpos($content, "TextInput::make('above_amount')");
        $aboveBlock = substr($content, $abovePos, 300);
        $this->assertStringContainsString('indonesianMoney()', $aboveBlock);
        $this->assertStringNotContainsString('numeric()', $aboveBlock);

        // Ensure up_to_amount has indonesianMoney and no numeric
        $upToPos = strpos($content, "TextInput::make('up_to_amount')");
        $upToBlock = substr($content, $upToPos, 300);
        $this->assertStringContainsString('indonesianMoney()', $upToBlock);
        $this->assertStringNotContainsString('numeric()', $upToBlock);
    }

    public function test_purchase_invoice_item_price_uses_indonesian_money(): void
    {
        $filePath = app_path('Filament/Resources/PurchaseInvoiceResource.php');
        $content = file_get_contents($filePath);

        $pos = strpos($content, "TextInput::make('price')");
        $this->assertNotFalse($pos, 'price field should exist in PurchaseInvoiceResource');
        $block = substr($content, $pos, 300);
        $this->assertStringContainsString('indonesianMoney()', $block);
        $this->assertStringNotContainsString('numeric()', $block);
    }

    public function test_journal_entry_relation_manager_uses_indonesian_money(): void
    {
        $filePath = app_path('Filament/Resources/ChartOfAccountResource/RelationManagers/JournalEntryRelationManager.php');
        $content = file_get_contents($filePath);

        $debitPos = strpos($content, "TextInput::make('debit')");
        $debitBlock = substr($content, $debitPos, 200);
        $this->assertStringContainsString('indonesianMoney()', $debitBlock);
        $this->assertStringNotContainsString('numeric()', $debitBlock);

        $creditPos = strpos($content, "TextInput::make('credit')");
        $creditBlock = substr($content, $creditPos, 200);
        $this->assertStringContainsString('indonesianMoney()', $creditBlock);
        $this->assertStringNotContainsString('numeric()', $creditBlock);
    }
}
