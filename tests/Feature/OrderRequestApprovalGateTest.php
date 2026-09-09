<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderRequestResource;
use App\Models\OrderRequestItem;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderRequestApprovalGateTest extends TestCase
{
    public function test_passes_when_all_items_have_valid_decisions_and_suppliers_for_auto_po(): void
    {
        $payload = [
            'create_purchase_order' => true,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Baut M6',
                    'item_supplier_id' => 10,
                    'approval_status' => OrderRequestItem::STATUS_APPROVED,
                    'include' => true,
                    'rejection_note' => null,
                ],
            ],
        ];

        // Should not throw any exception
        OrderRequestResource::validateApprovalGateItemDecisions($payload);
        $this->assertTrue(true);
    }

    public function test_throws_validation_exception_when_selected_items_is_empty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Pilih keputusan untuk semua item sebelum menyetujui Order Request.');

        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => false,
            'selected_items' => [],
        ]);
    }

    public function test_throws_validation_exception_when_decision_is_draft_or_missing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Pilih keputusan untuk semua item sebelum menyetujui Order Request.');

        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => false,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Baut M6',
                    'approval_status' => OrderRequestItem::STATUS_DRAFT,
                ],
            ],
        ]);
    }

    public function test_throws_validation_exception_when_rejected_without_rejection_note(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Alasan reject wajib diisi untuk item yang ditolak.');

        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => false,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Baut M6',
                    'approval_status' => OrderRequestItem::STATUS_REJECTED,
                    'rejection_note' => '',
                ],
            ],
        ]);
    }

    public function test_passes_when_rejected_with_rejection_note(): void
    {
        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => false,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Baut M6',
                    'approval_status' => OrderRequestItem::STATUS_REJECTED,
                    'rejection_note' => 'Stok di gudang masih mencukupi.',
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_throws_validation_exception_when_auto_po_enabled_but_item_missing_supplier(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('belum memiliki supplier. Tentukan supplier terlebih dahulu atau matikan opsi Buat Purchase Order otomatis.');

        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => true,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Mur M6',
                    'item_supplier_id' => null,
                    'approval_status' => OrderRequestItem::STATUS_APPROVED,
                    'include' => true,
                    'rejection_note' => null,
                ],
            ],
        ]);
    }

    public function test_passes_when_item_has_no_supplier_but_auto_po_is_disabled(): void
    {
        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => false,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Mur M6',
                    'item_supplier_id' => null,
                    'approval_status' => OrderRequestItem::STATUS_APPROVED,
                    'include' => true,
                    'rejection_note' => null,
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_passes_when_item_has_no_supplier_and_auto_po_enabled_but_item_is_not_included(): void
    {
        OrderRequestResource::validateApprovalGateItemDecisions([
            'create_purchase_order' => true,
            'selected_items' => [
                [
                    'item_id' => 1,
                    'product_name' => 'Mur M6',
                    'item_supplier_id' => null,
                    'approval_status' => OrderRequestItem::STATUS_APPROVED,
                    'include' => false,
                    'rejection_note' => null,
                ],
            ],
        ]);

        $this->assertTrue(true);
    }
}
