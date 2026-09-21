<?php

namespace Tests\Feature;

use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\VendorPaymentResource\Pages\CreateVendorPayment;
use App\Http\Controllers\HelperController;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tombol "Buat Vendor Payment" pada Permintaan Pembayaran yang disetujui membuka form dengan
 * ?payment_request_id=...; form harus terisi dari PR (sebelumnya crash 500 karena helper resource private).
 */
class VendorPaymentFromPaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function paymentUser(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (HelperController::listPermission() as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => sprintf('%s %s', $action, $resource), 'guard_name' => 'web']);
            }
        }

        $user = User::factory()->create(['manage_type' => 'all']);
        $user->givePermissionTo(['view any vendor payment', 'view vendor payment', 'create vendor payment']);

        return $user;
    }

    /**
     * @return array{supplier: Supplier, invoice: Invoice, paymentRequest: PaymentRequest}
     */
    private function approvedPaymentRequest(User $user, float $total, float $paid): array
    {
        $supplier = Supplier::factory()->create(['perusahaan' => 'CV Uji Pembayaran']);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-UJI-0001',
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->perusahaan,
            'subtotal' => $total,
            'total' => $total,
            'status' => 'partially_paid',
        ]);

        // InvoiceObserver dapat membuat AP otomatis untuk invoice non-draft; pastikan hanya ada satu AP dengan angka uji.
        AccountPayable::updateOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'supplier_id' => $supplier->id,
                'total' => $total,
                'paid' => $paid,
                'remaining' => $total - $paid,
                'status' => 'Belum Lunas',
                'created_by' => $user->id,
            ]
        );

        $paymentRequest = PaymentRequest::factory()->create([
            'supplier_id' => $supplier->id,
            'selected_invoices' => [$invoice->id],
            'total_amount' => $total,
            'status' => 'approved',
            'requested_by' => $user->id,
        ]);

        return compact('supplier', 'invoice', 'paymentRequest');
    }

    public function test_create_page_prefills_from_payment_request_query_with_remaining_amount(): void
    {
        $user = $this->paymentUser();
        $fixture = $this->approvedPaymentRequest($user, 25325, 10000);

        $component = Livewire::actingAs($user)
            ->withQueryParams([
                'payment_request_id' => $fixture['paymentRequest']->id,
                'supplier_id' => $fixture['supplier']->id,
            ])
            ->test(CreateVendorPayment::class)
            ->assertSuccessful();

        $this->assertSame($fixture['paymentRequest']->id, (int) $component->get('data.payment_request_id'));
        $this->assertSame($fixture['supplier']->id, (int) $component->get('data.supplier_id'));
        $this->assertSame([$fixture['invoice']->id], array_map('intval', $component->get('data.selected_invoices')));
        $this->assertSame('15.325,00', $component->get('data.total_payment'));

        $detail = array_values($component->get('data.payment_details'))[0];
        $this->assertSame($fixture['invoice']->id, (int) $detail['invoice_id']);
        $this->assertSame('15.325,00', $detail['remaining_amount']);
        $this->assertSame('15.325,00', $detail['payment_amount']);
    }

    public function test_helpers_used_by_the_create_page_are_publicly_callable(): void
    {
        $this->assertSame('1.234,50', VendorPaymentResource::formatMoneyState('1234.5'));
        $this->assertSame('0,00', VendorPaymentResource::formatMoneyState(null));
        $this->assertSame([], VendorPaymentResource::buildPaymentDetails(collect()));
    }
}
