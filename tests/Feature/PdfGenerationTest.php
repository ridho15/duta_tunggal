<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    private $cabang;
    private $currency;
    private $user;
    private $customer;
    private $uom;
    private $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = \App\Models\Cabang::factory()->create(['nama' => 'Jakarta Pusat']);
        $this->currency = \App\Models\Currency::factory()->create(['code' => 'IDR', 'symbol' => 'Rp']);
        $this->user = User::factory()->create(['name' => 'John Doe']);
        $this->customer = \App\Models\Customer::factory()->create([
            'name' => 'PT ABC Indonesia',
            'address' => 'Jl. Sudirman No. 123, Jakarta',
            'phone' => '021-12345678',
        ]);
        $this->uom = \App\Models\UnitOfMeasure::factory()->create(['name' => 'Pcs']);
        $this->product = \App\Models\Product::factory()->create([
            'sku' => 'SKU-001',
            'name' => 'Product Test',
        ]);
    }

    #[Test]
    public function quotation_pdf_loads_required_relations()
    {
        $quotation = Quotation::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        \App\Models\QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
        ]);

        // Verify relations can be loaded (this tests the config in controller)
        $quotation->load(['customer', 'quotationItem.product.uom', 'cabang', 'createdBy', 'approveBy']);

        $this->assertTrue($quotation->relationLoaded('customer'));
        $this->assertTrue($quotation->relationLoaded('cabang'));
        $this->assertTrue($quotation->relationLoaded('createdBy'));
        $this->assertTrue($quotation->relationLoaded('quotationItem'));
    }

    #[Test]
    public function order_request_config_has_required_relations()
    {
        $controller = new \App\Http\Controllers\PdfPreviewController();
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('documentConfig');
        $property->setAccessible(true);
        $config = $property->getValue($controller);

        // Verify relations are correctly configured (order request doesn't have cabang or approveBy relations)
        $relations = $config['order-request']['relations'];
        $this->assertContains('createdBy', $relations);
        $this->assertContains('orderRequestItem.product.uom', $relations);
        $this->assertContains('orderRequestItem.supplier', $relations);

        // Verify cabang is NOT included (it doesn't exist in the model)
        $this->assertNotContains('cabang', $relations);
    }

    #[Test]
    public function quotation_pdf_config_has_landscape_orientation_and_required_relations()
    {
        $controller = new \App\Http\Controllers\PdfPreviewController();
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('documentConfig');
        $property->setAccessible(true);
        $config = $property->getValue($controller);

        // Verify paper and orientation
        $this->assertEquals('a4', $config['quotation']['paper']);
        $this->assertEquals('landscape', $config['quotation']['orientation']);

        // Verify relations include createdBy and approveBy
        $relations = $config['quotation']['relations'];
        $this->assertContains('createdBy', $relations);
        $this->assertContains('approveBy', $relations);
        $this->assertContains('customer', $relations);
        $this->assertContains('cabang', $relations);
        $this->assertContains('quotationItem.product.uom', $relations);

        // Verify blade template
        $this->assertEquals('pdf.quotation', $config['quotation']['blade']);
        $this->assertEquals('quotation', $config['quotation']['bladeVar']);
    }

    #[Test]
    public function order_request_pdf_config_has_landscape_orientation_and_required_relations()
    {
        $controller = new \App\Http\Controllers\PdfPreviewController();
        $reflection = new \ReflectionClass($controller);
        $property = $reflection->getProperty('documentConfig');
        $property->setAccessible(true);
        $config = $property->getValue($controller);

        // Verify paper and orientation
        $this->assertEquals('a4', $config['order-request']['paper']);
        $this->assertEquals('landscape', $config['order-request']['orientation']);

        // Verify relations include createdBy (order request doesn't have cabang or approveBy relations)
        $relations = $config['order-request']['relations'];
        $this->assertContains('createdBy', $relations);
        $this->assertContains('orderRequestItem.product.uom', $relations);
        $this->assertContains('orderRequestItem.supplier', $relations);

        // Verify blade template
        $this->assertEquals('pdf.order-request', $config['order-request']['blade']);
        $this->assertEquals('orderRequest', $config['order-request']['bladeVar']);
    }

    #[Test]
    public function quotation_pdf_view_has_required_sections()
    {
        $bladeContent = file_get_contents(resource_path('views/pdf/quotation.blade.php'));

        // Verify header section exists
        $this->assertStringContainsString('PT DUTA TUNGGAL', $bladeContent);

        // Verify info table has new fields
        $this->assertStringContainsString('Alamat', $bladeContent);
        $this->assertStringContainsString('Telepon', $bladeContent);
        $this->assertStringContainsString('Dibuat Oleh', $bladeContent);
        $this->assertStringContainsString('Disetujui Oleh', $bladeContent);
        $this->assertStringContainsString('Tgl. Approval', $bladeContent);

        // Verify footer page number
        $this->assertStringContainsString('counter(page)', $bladeContent);
        $this->assertStringContainsString('Hal.', $bladeContent);

        // Verify Terbilang section
        $this->assertStringContainsString('Terbilang', $bladeContent);
    }

    #[Test]
    public function order_request_pdf_view_has_required_sections()
    {
        $bladeContent = file_get_contents(resource_path('views/pdf/order-request.blade.php'));

        // Verify header section exists
        $this->assertStringContainsString('PT DUTA TUNGGAL', $bladeContent);

        // Verify info table has new fields (order request doesn't have cabang field)
        $this->assertStringContainsString('Dibuat Oleh', $bladeContent);
        $this->assertStringContainsString('Keterangan', $bladeContent);

        // Verify footer page number
        $this->assertStringContainsString('counter(page)', $bladeContent);
        $this->assertStringContainsString('Hal.', $bladeContent);

        // Verify table header styling
        $this->assertStringContainsString('background-color: #f4f6f8', $bladeContent);
    }
}