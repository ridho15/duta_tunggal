<?php

/** Tanda tangan digital pada PO (Ko Handy 28 Nov 2026): disalin saat disetujui; PDF menampilkan nama pembuat & penyetuju. */

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('PO disetujui: tanda tangan pembuat & penyetuju tersalin, PDF menampilkan nama asli', function () {
    Storage::fake('public');
    $ctx = stkContext();
    $creator = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'name' => 'Rina Purchasing']);
    $approver = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'name' => 'Budi Direktur']);
    $approver->assignRole(Role::findByName('Owner'));
    $sig = app(\App\Services\SignatureService::class);
    $sig->saveFromUpload($creator, UploadedFile::fake()->image('a.png', 200, 80));
    $sig->saveFromUpload($approver, UploadedFile::fake()->image('b.png', 200, 80));

    $po = PurchaseOrder::factory()->create(['status' => 'draft', 'created_by' => $creator->id, 'cabang_id' => $ctx['cabang']->id]);
    PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 1000]);

    $this->actingAs($approver);
    app(\App\Services\PurchaseOrderService::class)->approvePo($po->fresh(), $approver->id);

    $po->refresh();
    expect($po->signatureFor('approved')?->signer_name)->toBe('Budi Direktur')
        ->and($po->signatureFor('created')?->signer_name)->toBe('Rina Purchasing')
        ->and(Storage::disk('public')->exists($po->signatureFor('approved')->signature_path))->toBeTrue();

    // ganti tanda tangan: dokumen lama tetap memakai salinannya
    $old = $po->signatureFor('approved')->signature_path;
    $sig->saveFromUpload($approver, UploadedFile::fake()->image('c.png', 200, 80));
    expect($po->fresh()->signatureFor('approved')->signature_path)->toBe($old);

    $html = view('pdf.purchase-order', ['purchaseOrder' => $po->fresh()->load(['supplier', 'cabang', 'warehouse', 'approvedBy', 'createdBy', 'purchaseOrderItem.product.uom', 'purchaseOrderItem.currency', 'purchaseOrderCurrency.currency'])])->render();
    expect($html)->toContain('Rina Purchasing')->toContain('Budi Direktur')->toContain('Ditandatangani digital');
});
