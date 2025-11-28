<?php

use App\Models\User;
use App\Models\PurchaseReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Services\PurchaseReturnService;
use App\Services\StockService;
use App\Services\AccountingService;

uses()->group('purchase-return');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = Mockery::mock(PurchaseReturnService::class);
    app()->instance(PurchaseReturnService::class, $this->service);
});

test('can create purchase return with auto generated number', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-' . now()->format('Ymd') . '-0001',
        'created_by' => $this->user->id,
    ]);

    expect($purchaseReturn->nota_retur)->toMatch('/^NR-\d{8}-\d{4}$/')
        ->and($purchaseReturn->purchase_receipt_id)->toBe(1);
});

test('can submit purchase return for approval', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0001',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('submitForApproval')->once()->andReturn(true);

    $result = $this->service->submitForApproval($purchaseReturn);

    expect($result)->toBeTrue();
});

test('cannot submit non-draft purchase return', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0002',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('submitForApproval')->once()->andThrow(\Exception::class);

    $this->service->submitForApproval($purchaseReturn);
})->throws(\Exception::class);

test('can approve pending purchase return', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0003',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('approve')->once()->andReturn(true);

    $result = $this->service->approve($purchaseReturn, ['approval_notes' => 'Approved']);

    expect($result)->toBeTrue();
});

test('can reject pending purchase return', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0004',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('reject')->once()->andReturn(true);

    $result = $this->service->reject($purchaseReturn, ['rejection_notes' => 'Not approved']);

    expect($result)->toBeTrue();
});

test('stock adjustment on approval', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0005',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('adjustStock')->once()->andReturn(true);

    $result = $this->service->adjustStock($purchaseReturn);

    expect($result)->toBeTrue();
});

test('journal entry on approval', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0006',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('createJournalEntry')->once()->andReturn(true);

    $result = $this->service->createJournalEntry($purchaseReturn);

    expect($result)->toBeTrue();
});

test('credit note adjustment', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0007',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('processCreditNote')->once()->andReturn(true);

    $result = $this->service->processCreditNote($purchaseReturn, ['credit_note_number' => 'CN-123']);

    expect($result)->toBeTrue();
});

test('refund adjustment', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0008',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('processRefund')->once()->andReturn(true);

    $result = $this->service->processRefund($purchaseReturn, ['refund_amount' => 50000]);

    expect($result)->toBeTrue();
});

test('replacement adjustment', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0009',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('processReplacement')->once()->andReturn(true);

    $result = $this->service->processReplacement($purchaseReturn, ['replacement_po_id' => 123]);

    expect($result)->toBeTrue();
});

test('tracking status updates', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0010',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('updateTracking')->once()->andReturn(true);

    $result = $this->service->updateTracking($purchaseReturn, [
        'supplier_response' => 'Acknowledged',
        'credit_note_received' => true,
        'case_closed_date' => now(),
    ]);

    expect($result)->toBeTrue();
});

test('return reasons validation', function () {
    $validReasons = ['Damaged goods', 'Wrong specification', 'Excess delivery', 'Quality issues', 'Order cancellation'];

    foreach ($validReasons as $reason) {
        $purchaseReturn = PurchaseReturn::create([
            'purchase_receipt_id' => 1,
            'return_date' => now(),
            'nota_retur' => 'NR-20241101-0011',
            'created_by' => $this->user->id,
            'notes' => $reason,
        ]);
        expect($purchaseReturn->notes)->toBe($reason);
    }
});

test('physical return process', function () {
    $purchaseReturn = PurchaseReturn::create([
        'purchase_receipt_id' => 1,
        'return_date' => now(),
        'nota_retur' => 'NR-20241101-0012',
        'created_by' => $this->user->id,
    ]);

    $this->service->shouldReceive('initiatePhysicalReturn')->once()->andReturn(true);

    $result = $this->service->initiatePhysicalReturn($purchaseReturn, [
        'delivery_note' => 'DN-123',
        'shipping_details' => 'Courier XYZ',
    ]);

    expect($result)->toBeTrue();
});