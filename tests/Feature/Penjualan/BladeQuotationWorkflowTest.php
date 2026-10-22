<?php

namespace Tests\Feature\Penjualan;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeQuotationWorkflowTest extends TestCase
{
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Currency $currency;
    protected ProductCategory $category;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-QO',
            'nama' => 'Cabang Test Quotation',
            'alamat' => 'Jl. Test Penjualan No. 1',
            'status' => true,
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
        );

        $this->customer = Customer::withoutGlobalScopes()->first() ?? Customer::create([
            'code' => 'CUST-QO-TEST',
            'name' => 'Pelanggan Uji Penawaran',
            'perusahaan' => 'PT Uji Penawaran Sejahtera',
            'address' => 'Jl. Industri No. 88 Jakarta',
            'telephone' => '021-888999',
            'phone' => '08123456789',
            'fax' => '021-888990',
            'email' => 'customer.test@example.com',
            'tipe' => 'PKP',
            'nik_npwp' => '01.234.567.8-999.000',
            'tempo_kredit' => 30,
            'kredit_limit' => 100000000,
            'tipe_pembayaran' => 'Kredit',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-QO'],
            ['name' => 'Kategori Uji Quotation', 'kenaikan_harga' => 10]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any quotation',
            'view quotation',
            'create quotation',
            'update quotation',
            'delete quotation',
            'request-approve quotation',
            'approve quotation',
            'reject quotation',
            'create sales order',
        ];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->authorizedUser = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'qo_tester',
            'first_name' => 'Quotation',
            'last_name' => 'Tester',
            'email' => 'qo-tester@dutatunggal.co.id',
            'name' => 'Quotation Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->authorizedUser->hasRole('Super Admin')) {
            $this->authorizedUser->assignRole($role);
        }

        $this->unauthorizedUser = User::withoutGlobalScopes()
            ->where('id', '!=', $this->authorizedUser->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->first() ?? User::create([
                'username' => 'unauth_user_qo',
                'first_name' => 'Unauth',
                'last_name' => 'Quotation',
                'email' => 'unauth-qo@dutatunggal.co.id',
                'name' => 'Unauth Quotation User',
                'password' => bcrypt('password'),
                'cabang_id' => $this->cabang->id,
            ]);
    }

    protected function createTestProduct(string $sku, string $name, float $sellPrice = 50000): Product
    {
        return Product::firstOrCreate(
            ['sku' => $sku],
            [
                'name' => $name,
                'kode_merk' => 'MRK-QO',
                'product_category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'status' => 1,
                'is_active' => true,
                'cost_price' => $sellPrice * 0.7,
                'sell_price' => $sellPrice,
                'price_sell' => $sellPrice,
                'price_buy' => $sellPrice * 0.7,
                'harga_batas' => $sellPrice * 0.8,
            ]
        );
    }

    public function test_user_can_access_quotation_index_and_see_kpi_cards(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('penjualan.quotations.index'));

        $response->assertOk();
        $response->assertViewIs('penjualan.penawaran.index');
        $response->assertSee('Penawaran Harga (Quotation)');
        $response->assertSee('Total Penawaran');
        $response->assertSee('Draft Disiapkan');
        $response->assertSee('Menunggu Approval');
        $response->assertSee('Disetujui');
        $response->assertSee('Kedaluwarsa');
    }

    public function test_user_can_access_quotation_create_page(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('penjualan.quotations.create'));

        $response->assertOk();
        $response->assertViewIs('penjualan.penawaran.create');
        $response->assertSee('Buat Penawaran Harga (Quotation)');
        $response->assertSee($this->customer->name);
    }

    public function test_user_can_store_quotation_as_draft(): void
    {
        $product = $this->createTestProduct('QO-SKU-DRAFT', 'Produk Penawaran Draft', 75000);

        $payload = [
            'quotation_number' => 'QO-TEST-DRAFT-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
            'tempo_pembayaran' => 30,
            'shipped_to' => 'Gudang Pelanggan di Cikarang',
            'notes' => 'Penawaran tahap awal negosiasi',
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'unit_price' => 75000,
                    'discount' => 10,
                    'tax' => 11,
                    'tax_type' => 'PPN Excluded',
                    'notes' => 'Harga partai besar',
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.store'), $payload);

        $quotation = Quotation::where('quotation_number', $payload['quotation_number'])->first();
        $this->assertNotNull($quotation);
        $response->assertRedirect(route('penjualan.quotations.show', $quotation));

        $this->assertEquals(Quotation::STATUS_DRAFT, $quotation->status);
        $this->assertEquals($this->customer->id, $quotation->customer_id);
        $this->assertEquals(1, $quotation->quotationItem()->count());

        // 10 qty * 75000 = 750000 - 10% disc = 675000 + 11% PPN = 749250
        $this->assertEquals(749250, (float) $quotation->total_amount);
    }

    public function test_user_can_store_and_directly_request_approval(): void
    {
        $product = $this->createTestProduct('QO-SKU-REQ', 'Produk Penawaran Request', 100000);

        $payload = [
            'quotation_number' => 'QO-TEST-REQ-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->format('Y-m-d'),
            'valid_until' => now()->addDays(14)->format('Y-m-d'),
            'currency_id' => $this->currency->id,
            'action' => 'request_approve',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 100000,
                    'discount' => 0,
                    'tax_type' => 'None',
                ],
            ],
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.store'), $payload);

        $quotation = Quotation::where('quotation_number', $payload['quotation_number'])->first();
        $this->assertNotNull($quotation);
        $this->assertEquals(Quotation::STATUS_REQUEST_APPROVE, $quotation->status);
        $this->assertEquals($this->authorizedUser->id, $quotation->request_approve_by);
        $this->assertNotNull($quotation->request_approve_at);
    }

    public function test_user_can_request_approval_from_draft(): void
    {
        $product = $this->createTestProduct('QO-SKU-REQ2', 'Produk Request 2', 50000);

        $quotation = Quotation::create([
            'quotation_number' => 'QO-DRAFT-TO-REQ-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_DRAFT,
            'created_by' => $this->authorizedUser->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.requestApprove', $quotation));

        $response->assertRedirect(route('penjualan.quotations.show', $quotation));
        $this->assertEquals(Quotation::STATUS_REQUEST_APPROVE, $quotation->fresh()->status);
    }

    public function test_authorized_user_can_approve_quotation(): void
    {
        $product = $this->createTestProduct('QO-SKU-APP', 'Produk Approve', 80000);

        $quotation = Quotation::create([
            'quotation_number' => 'QO-TO-APP-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_REQUEST_APPROVE,
            'created_by' => $this->authorizedUser->id,
            'request_approve_by' => $this->authorizedUser->id,
            'request_approve_at' => now(),
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 80000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.approve', $quotation));

        $response->assertRedirect(route('penjualan.quotations.show', $quotation));

        $fresh = $quotation->fresh();
        $this->assertEquals(Quotation::STATUS_APPROVE, $fresh->status);
        $this->assertEquals($this->authorizedUser->id, $fresh->approve_by);
        $this->assertNotNull($fresh->approve_at);
    }

    public function test_authorized_user_can_reject_quotation_with_reason(): void
    {
        $product = $this->createTestProduct('QO-SKU-REJ', 'Produk Reject', 40000);

        $quotation = Quotation::create([
            'quotation_number' => 'QO-TO-REJ-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_REQUEST_APPROVE,
            'created_by' => $this->authorizedUser->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 40000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.reject', $quotation), [
                'reason' => 'Margin laba terlalu tipis, tolong naikkan 5%',
            ]);

        $response->assertRedirect(route('penjualan.quotations.show', $quotation));

        $fresh = $quotation->fresh();
        $this->assertEquals(Quotation::STATUS_REJECT, $fresh->status);
        $this->assertStringContainsString('Margin laba terlalu tipis', (string) $fresh->notes);
    }

    public function test_user_can_create_revision_from_approved_or_expired_quotation(): void
    {
        $product = $this->createTestProduct('QO-SKU-REV-SRC', 'Produk Sumber Revisi', 60000);

        $source = Quotation::create([
            'quotation_number' => 'QO-ORIGINAL-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(35)->toDateString(),
            'valid_until' => now()->subDays(5)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_EXPIRED,
            'created_by' => $this->authorizedUser->id,
            'total_amount' => 120000,
        ]);

        QuotationItem::create([
            'quotation_id' => $source->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 60000,
            'tax_type' => 'None',
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('penjualan.quotations.revision', $source));

        $revision = Quotation::where('revision_of_id', $source->id)->first();
        $this->assertNotNull($revision);
        $this->assertStringContainsString('-R1', $revision->quotation_number);
        $this->assertEquals(Quotation::STATUS_DRAFT, $revision->status);
        $this->assertEquals(1, $revision->quotationItem()->count());

        $response->assertRedirect(route('penjualan.quotations.edit', $revision));
    }

    public function test_user_can_convert_approved_quotation_to_sales_order(): void
    {
        $product = $this->createTestProduct('QO-SKU-CONV', 'Produk Konversi SO', 90000);

        $quotation = Quotation::create([
            'quotation_number' => 'QO-READY-SO-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(20)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_APPROVE,
            'created_by' => $this->authorizedUser->id,
            'approve_by' => $this->authorizedUser->id,
            'approve_at' => now(),
            'total_amount' => 90000,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 90000,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('penjualan.quotations.convertToSaleOrder', $quotation));

        $response->assertRedirect(route('penjualan.so.create', ['quotation_id' => $quotation->id]));
    }

    public function test_user_can_stream_quotation_pdf_and_unauthorized_user_is_blocked(): void
    {
        $product = $this->createTestProduct('QO-SKU-PDF', 'Produk Cetak PDF', 50000);

        $quotation = Quotation::create([
            'quotation_number' => 'QO-PDF-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency_id' => $this->currency->id,
            'status' => Quotation::STATUS_APPROVE,
            'created_by' => $this->authorizedUser->id,
            'approve_by' => $this->authorizedUser->id,
            'approve_at' => now(),
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        // Authorized user can stream PDF
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('penjualan.quotations.pdf', $quotation));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));

        // Unauthorized user cannot access index
        $unauthResponse = $this->actingAs($this->unauthorizedUser)
            ->get(route('penjualan.quotations.index'));

        $this->assertContains($unauthResponse->status(), [403, 302]);
    }
}
